<?php

namespace ExileOfAranei\ListOrdering\Concerns;

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use ExileOfAranei\ListOrdering\Exceptions\GuardedColumnMutationException;
use ExileOfAranei\ListOrdering\Exceptions\InvalidAnchorException;
use ExileOfAranei\ListOrdering\Exceptions\InvalidGroupKeyException;
use ExileOfAranei\ListOrdering\Internal\Positioner;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements Orderable
 */
trait HasOrdering
{
    private bool $orderingGuardBypassed = false;

    /**
     * The only method a model is required to write itself.
     *
     * @return list<string>
     */
    abstract public function orderingGroupColumns(): array;

    public function orderingRankColumn(): string
    {
        return 'rank';
    }

    /**
     * The group this model currently belongs to, read from its original
     * (pre-mutation) attribute values — not whatever placeInto() may have
     * already written into memory ahead of save().
     */
    public function currentGroupKey(): GroupKey
    {
        $columns = [];

        foreach ($this->orderingGroupColumns() as $column) {
            $columns[$column] = $this->getOriginal($column);
        }

        return GroupKey::of($columns);
    }

    /**
     * The only positioning primitive. Reordering within a list and moving
     * between lists are the same call — this method does not branch on
     * which one it is.
     *
     * Works identically whether the model is new (computes a rank and
     * inserts in one save()) or already persisted (moves it in one save()).
     */
    public function placeInto(GroupKey $group, ?Orderable $after, ?Orderable $before): void
    {
        $this->assertMatchesDeclaredGroupColumns($group);

        $after = $this->narrowAnchor($after);
        $before = $this->narrowAnchor($before);

        // The guard is lifted here, around the whole operation — anchor
        // resolution is read-only anyway, and covering it too (not just the
        // final save()) means Positioner never needs a public backdoor onto
        // the model to toggle the guard itself.
        $this->withOrderingGuardBypassed(function () use ($group, $after, $before) {
            app(Positioner::class)->place($this, $group, $after, $before);
        });
    }

    /**
     * Thin delegate to placeInto() — no positioning logic of its own.
     */
    public function placeAtEnd(GroupKey $group): void
    {
        $this->placeInto($group, null, null);
    }

    /**
     * Delegates to placeInto() — the only work of its own is resolving the
     * current first element of $group, to pass as the boundary anchor.
     */
    public function placeAtStart(GroupKey $group): void
    {
        $query = $this->newQuery()->ordered($group);

        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }

        $this->placeInto($group, null, $this->narrowModel($query->first()));
    }

    /**
     * Thin delegate to placeInto() — no positioning logic of its own. The
     * target group is $anchor's group *at call time*, not whatever group the
     * caller might otherwise assume — so this still lands correctly even if
     * $anchor moved to a different group since it was last referenced.
     */
    public function placeAfter(Model&Orderable $anchor): void
    {
        $this->placeInto($anchor->currentGroupKey(), $anchor, null);
    }

    /**
     * Thin delegate to placeInto() — no positioning logic of its own. See
     * placeAfter() for why the group comes from $anchor, not the caller.
     */
    public function placeBefore(Model&Orderable $anchor): void
    {
        $this->placeInto($anchor->currentGroupKey(), null, $anchor);
    }

    private function withOrderingGuardBypassed(\Closure $callback): mixed
    {
        $this->orderingGuardBypassed = true;

        try {
            return $callback();
        } finally {
            $this->orderingGuardBypassed = false;
        }
    }

    protected static function bootHasOrdering(): void
    {
        static::saving(function (self $model) {
            if (! $model->exists || $model->orderingGuardBypassed) {
                return;
            }

            $watched = [...$model->orderingGroupColumns(), $model->orderingRankColumn()];

            foreach ($watched as $column) {
                if ($model->isDirty($column)) {
                    throw new GuardedColumnMutationException(sprintf(
                        'The column "%s" was modified outside placeInto().',
                        $column,
                    ));
                }
            }
        });
    }

    private function assertMatchesDeclaredGroupColumns(GroupKey $group): void
    {
        $expected = $this->orderingGroupColumns();
        sort($expected);

        $actual = $group->columnNames();
        sort($actual);

        if ($expected !== $actual) {
            throw new InvalidGroupKeyException(sprintf(
                'The group key columns [%s] do not match the declared ordering group columns [%s].',
                implode(', ', $group->columnNames()),
                implode(', ', $this->orderingGroupColumns()),
            ));
        }
    }

    private function narrowAnchor(?Orderable $anchor): (Model&Orderable)|null
    {
        return $this->narrowModel($anchor);
    }

    /**
     * Both directions of the same gap: an ?Orderable might not be a Model,
     * and an ?Model (e.g. a raw query result) might not be Orderable. Either
     * way, the package's own models always satisfy both — this only makes
     * that fact visible to the type system, in one place.
     */
    private function narrowModel(?object $candidate): (Model&Orderable)|null
    {
        if ($candidate === null) {
            return null;
        }

        if (! $candidate instanceof Model || ! $candidate instanceof Orderable) {
            throw new InvalidAnchorException(
                'Expected a Model implementing Orderable.'
            );
        }

        return $candidate;
    }

    /**
     * Without $group, this only sorts — it does not, and cannot, guarantee
     * order across different groups in the result set. Only omit $group when
     * the query is already narrowed to a single group by other means (e.g.
     * an already-scoped relationship).
     */
    public function scopeOrdered(Builder $query, ?GroupKey $group = null): Builder
    {
        if ($group !== null) {
            $group->applyTo($query);
        }

        return $query->orderBy($this->orderingRankColumn());
    }
}
