<?php

namespace ExileOfAranei\ListOrdering\Internal;

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Events\Positioned;
use ExileOfAranei\ListOrdering\Exceptions\InvalidAnchorException;
use ExileOfAranei\ListOrdering\Exceptions\ListOrderingException;
use ExileOfAranei\ListOrdering\Exceptions\RankConflictException;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * @internal Not part of the package's public contract. Reached only through
 * Concerns\HasOrdering::placeInto(), which performs the ?Orderable ->
 * Model&Orderable narrowing before calling here.
 */
final class Positioner
{
    public function __construct(
        private readonly RankGenerator $ranks,
        private readonly int $maxRetries = 3,
    ) {}

    public function place(
        Model&Orderable $model,
        GroupKey $group,
        (Model&Orderable)|null $after,
        (Model&Orderable)|null $before,
    ): void {
        $retries = 0;

        while (true) {
            try {
                $this->attempt($model, $group, $after, $before, isRetry: $retries > 0);

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($retries >= $this->maxRetries) {
                    throw new RankConflictException(sprintf(
                        'Failed to place the record after %d retries due to repeated rank conflicts.',
                        $this->maxRetries,
                    ), previous: $e);
                }

                $retries++;

                // Loop again without reusing anything computed above: the next
                // attempt calls resolveAnchors() fresh, so any auto-resolved
                // (missing) anchor is re-queried from the database rather than
                // reusing the bounds that just produced this conflict — reusing
                // them would deterministically regenerate the same rank and
                // reproduce the same conflict. When BOTH $after and $before were
                // given explicitly, resolveAnchors() (told isRetry: true) drops
                // the stale $before and re-resolves it the same way the
                // after-only case does — any row that could have caused this
                // conflict necessarily sits between $after and $before, so
                // re-querying the immediate next row after $after always finds
                // it and narrows the interval.
            }
        }
    }

    private function attempt(
        Model&Orderable $model,
        GroupKey $group,
        (Model&Orderable)|null $after,
        (Model&Orderable)|null $before,
        bool $isRetry,
    ): void {
        [$after, $before] = $this->resolveAnchors($model, $group, $after, $before, $isRetry);

        $rank = $this->ranks->between(
            $after?->getAttribute($after->orderingRankColumn()),
            $before?->getAttribute($before->orderingRankColumn()),
        );

        $from = $model->exists ? $model->currentGroupKey() : null;

        foreach ($group->toArray() as $column => $value) {
            $model->setAttribute($column, $value);
        }

        $model->setAttribute($model->orderingRankColumn(), $rank);
        $model->save();

        event(new Positioned($model, $from, $group));
    }

    /**
     * @return array{0: (Model&Orderable)|null, 1: (Model&Orderable)|null}
     */
    private function resolveAnchors(
        Model&Orderable $model,
        GroupKey $group,
        (Model&Orderable)|null $after,
        (Model&Orderable)|null $before,
        bool $isRetry,
    ): array {
        if ($after !== null && $before !== null) {
            $this->assertBelongsToGroup($after, $group);
            $this->assertBelongsToGroup($before, $group);

            if ($isRetry) {
                return [$after, $this->findNext($model, $group, $after)];
            }

            return [$after, $before];
        }

        if ($after !== null) {
            $this->assertBelongsToGroup($after, $group);

            return [$after, $this->findNext($model, $group, $after)];
        }

        if ($before !== null) {
            $this->assertBelongsToGroup($before, $group);

            return [$this->findPrevious($model, $group, $before), $before];
        }

        return [$this->findLast($model, $group), null];
    }

    private function assertBelongsToGroup(Model&Orderable $anchor, GroupKey $group): void
    {
        if (! $anchor->currentGroupKey()->equals($group)) {
            throw new InvalidAnchorException(
                'The anchor passed to placeInto() does not belong to the target group.'
            );
        }
    }

    private function findNext(Model&Orderable $model, GroupKey $group, Model&Orderable $after): (Model&Orderable)|null
    {
        $query = $this->scopedToGroup($model, $group);

        $query->where($model->orderingRankColumn(), '>', $after->getAttribute($after->orderingRankColumn()));
        $query->orderBy($model->orderingRankColumn());

        return $this->narrowResult($query->first());
    }

    private function findPrevious(Model&Orderable $model, GroupKey $group, Model&Orderable $before): (Model&Orderable)|null
    {
        $query = $this->scopedToGroup($model, $group);

        $query->where($model->orderingRankColumn(), '<', $before->getAttribute($before->orderingRankColumn()));
        $query->orderByDesc($model->orderingRankColumn());

        return $this->narrowResult($query->first());
    }

    private function findLast(Model&Orderable $model, GroupKey $group): (Model&Orderable)|null
    {
        $query = $this->scopedToGroup($model, $group);

        $query->orderByDesc($model->orderingRankColumn());

        return $this->narrowResult($query->first());
    }

    /**
     * $query is always built from $model->newQuery(), so any row it returns
     * is necessarily an instance of $model's own (Orderable-implementing)
     * class — this only makes that fact visible to the type system.
     */
    private function narrowResult(?Model $result): (Model&Orderable)|null
    {
        if ($result === null) {
            return null;
        }

        if (! $result instanceof Orderable) {
            throw new ListOrderingException('Expected the query to return an Orderable model.');
        }

        return $result;
    }

    private function scopedToGroup(Model&Orderable $model, GroupKey $group): Builder
    {
        $query = $model->newQuery();
        $group->applyTo($query);

        if ($model->exists) {
            $query->where($model->getKeyName(), '!=', $model->getKey());
        }

        // A row with a NULL rank (e.g. mid-migration from an integer position
        // column, not yet backfilled) must never be picked as an anchor: on
        // Postgres, ORDER BY rank DESC defaults to NULLS FIRST, so findLast()
        // would otherwise return it as the "last" row instead of skipping it,
        // making between() treat a non-empty group as empty.
        $query->whereNotNull($model->orderingRankColumn());

        return $query;
    }
}
