<?php

namespace ExileOfAranei\ListOrdering\Support;

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use ExileOfAranei\ListOrdering\Exceptions\InvalidBatchOrderException;
use ExileOfAranei\ListOrdering\Exceptions\ListOrderingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Opt-in convenience for the one integration pattern a full-list reorder UI
 * (drag-and-drop over an entire list) needs and placeInto() deliberately
 * doesn't provide: turning a desired final order into the minimal set of
 * anchor moves. Not a new primitive — it only calls placeAfter()/
 * placeAtStart(), already defined on the model.
 */
final class BatchReorderer
{
    /**
     * @param  class-string<Model&Orderable>  $modelClass
     * @param  list<int|string>  $newOrderOfKeys  every key currently in $group, in the desired order
     */
    public static function apply(string $modelClass, GroupKey $group, array $newOrderOfKeys): void
    {
        DB::transaction(function () use ($modelClass, $group, $newOrderOfKeys) {
            self::reorder($modelClass, $group, $newOrderOfKeys);
        });
    }

    /**
     * @param  class-string<Model&Orderable>  $modelClass
     * @param  list<int|string>  $newOrderOfKeys
     */
    private static function reorder(string $modelClass, GroupKey $group, array $newOrderOfKeys): void
    {
        $probe = new $modelClass;

        $query = $modelClass::query();
        $group->applyTo($query);
        $query->orderBy($probe->orderingRankColumn());

        $current = $query->get();

        /** @var array<int|string, Model&Orderable> $byKey */
        $byKey = [];
        $currentPositions = [];

        foreach ($current as $index => $model) {
            if (! $model instanceof Orderable) {
                throw new ListOrderingException('Expected the query to return an Orderable model.');
            }

            $key = $model->getKey();
            $byKey[$key] = $model;
            $currentPositions[$key] = $index;
        }

        self::assertSamePermutation(array_keys($byKey), $newOrderOfKeys);

        // The row currently sorting first, before any of this batch's moves —
        // used only as the boundary for placing a new first item, and read
        // now, before anything in the group has moved.
        $originalFirst = $current->first();

        if ($originalFirst !== null && ! $originalFirst instanceof Orderable) {
            throw new ListOrderingException('Expected the query to return an Orderable model.');
        }

        $sequence = array_map(static fn ($key) => $currentPositions[$key], $newOrderOfKeys);
        $unchangedIndices = self::longestIncreasingSubsequenceIndices($sequence);

        // Walking left to right, every index skipped here is already in the
        // right relative order (it's part of the longest run that needs no
        // move). Every other index is placed right after its predecessor in
        // the desired order — which, by the time its turn comes, is always
        // already correctly positioned, either because it was never moved or
        // because this same loop just moved it. placeInto() is used directly
        // (rather than the placeAfter()/placeAtStart() wrappers) because it's
        // the one primitive Orderable itself declares — the wrappers are
        // only guaranteed to exist on models using HasOrdering, not on
        // Orderable in general.
        foreach ($newOrderOfKeys as $i => $key) {
            if (isset($unchangedIndices[$i])) {
                continue;
            }

            $model = $byKey[$key];

            if ($i === 0) {
                $model->placeInto($group, null, $originalFirst);

                continue;
            }

            $previous = $byKey[$newOrderOfKeys[$i - 1]];
            $model->placeInto($previous->currentGroupKey(), $previous, null);
        }
    }

    /**
     * @param  list<int|string>  $currentKeys
     * @param  list<int|string>  $newOrderOfKeys
     */
    private static function assertSamePermutation(array $currentKeys, array $newOrderOfKeys): void
    {
        sort($currentKeys);
        sort($newOrderOfKeys);

        if ($currentKeys !== $newOrderOfKeys) {
            throw new InvalidBatchOrderException(
                'newOrderOfKeys must contain exactly the keys currently in the group, no more and no fewer.'
            );
        }
    }

    /**
     * Standard O(n log n) patience-sorting LIS, returning the indices (into
     * $sequence) that are already in increasing order relative to each
     * other — those positions need no move at all.
     *
     * @param  list<int>  $sequence
     * @return array<int, true>
     */
    private static function longestIncreasingSubsequenceIndices(array $sequence): array
    {
        $pileTops = [];
        $predecessors = array_fill(0, count($sequence), -1);

        foreach ($sequence as $i => $value) {
            $lo = 0;
            $hi = count($pileTops);

            while ($lo < $hi) {
                $mid = intdiv($lo + $hi, 2);

                if ($sequence[$pileTops[$mid]] < $value) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid;
                }
            }

            if ($lo > 0) {
                $predecessors[$i] = $pileTops[$lo - 1];
            }

            if ($lo === count($pileTops)) {
                $pileTops[] = $i;
            } else {
                $pileTops[$lo] = $i;
            }
        }

        $indices = [];
        $cursor = $pileTops === [] ? -1 : $pileTops[count($pileTops) - 1];

        while ($cursor !== -1) {
            $indices[$cursor] = true;
            $cursor = $predecessors[$cursor];
        }

        return $indices;
    }
}
