<?php

use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * A RankGenerator double whose between() is driven by $behavior, called as
 * ($lower, $upper, $callNumber) with $callNumber starting at 1 — lets a test
 * vary its return value, or run a side effect, by call count without hand-
 * rolling a new anonymous class + counter each time.
 */
function fakeRankGenerator(Closure $behavior): RankGenerator
{
    return new class($behavior) implements RankGenerator
    {
        private int $calls = 0;

        public function __construct(private readonly Closure $behavior) {}

        public function between(?string $lower, ?string $upper): string
        {
            $this->calls++;

            return ($this->behavior)($lower, $upper, $this->calls);
        }
    };
}
