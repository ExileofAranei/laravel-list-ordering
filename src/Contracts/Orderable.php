<?php

namespace ExileOfAranei\ListOrdering\Contracts;

use ExileOfAranei\ListOrdering\Support\GroupKey;

interface Orderable
{
    /**
     * The only method a model is required to write itself.
     *
     * @return list<string>
     */
    public function orderingGroupColumns(): array;

    public function currentGroupKey(): GroupKey;

    public function orderingRankColumn(): string;

    public function placeInto(GroupKey $group, ?Orderable $after, ?Orderable $before): void;
}
