<?php

namespace ExileOfAranei\ListOrdering\Contracts;

interface RankGenerator
{
    /**
     * Generate a rank that sorts strictly between $lower and $upper.
     *
     * Either bound may be null, meaning that side is unbounded.
     */
    public function between(?string $lower, ?string $upper): string;
}
