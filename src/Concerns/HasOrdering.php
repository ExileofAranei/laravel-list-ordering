<?php

namespace ExileOfAranei\ListOrdering\Concerns;

use ExileOfAranei\ListOrdering\Support\GroupKey;
use Illuminate\Database\Eloquent\Builder;

trait HasOrdering
{
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
     * Sort by rank, optionally scoped to a single group.
     *
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
