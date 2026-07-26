<?php

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class)->in(__DIR__);

/**
 * Test-fixture setup helper: rank is guarded against mass assignment (see
 * HasOrdering::initializeHasOrdering()), so tests that need a row seeded at
 * a specific, known rank — without exercising placeInto()'s own anchor
 * logic — go through placeAtRank() instead of Model::create(['rank' => …]).
 *
 * @param  class-string<Model&Orderable>  $modelClass
 * @param  array<string, mixed>  $group
 */
function seedAtRank(string $modelClass, array $group, string $rank): Model&Orderable
{
    $model = new $modelClass($group);
    $model->placeAtRank(GroupKey::of($group), $rank);

    return $model;
}

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
