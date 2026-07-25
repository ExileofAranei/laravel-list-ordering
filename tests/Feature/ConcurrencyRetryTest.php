<?php

use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Exceptions\RankConflictException;
use ExileOfAranei\ListOrdering\Internal\Positioner;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;

it('recovers from a forced conflict on the first attempt via retry', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    $callCount = new stdClass;
    $callCount->n = 0;

    app()->bind(RankGenerator::class, function () use ($callCount) {
        return new class($callCount) implements RankGenerator
        {
            public function __construct(private stdClass $count) {}

            public function between(?string $lower, ?string $upper): string
            {
                $this->count->n++;

                // Collide on the first attempt only.
                return $this->count->n === 1 ? 'B' : 'Z';
            }
        };
    });

    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    expect($entry->exists)->toBeTrue();
    expect($entry->rank)->toBe('Z');
    expect($callCount->n)->toBe(2);
});

it('re-resolves a missing anchor fresh on each retry, not just the rank between stale bounds', function () {
    $a = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $log = new stdClass;
    $log->calls = [];

    app()->bind(RankGenerator::class, function () use ($log) {
        return new class($log) implements RankGenerator
        {
            public function __construct(private stdClass $log) {}

            public function between(?string $lower, ?string $upper): string
            {
                $this->log->calls[] = [$lower, $upper];

                if (count($this->log->calls) === 1) {
                    // Simulate a concurrent writer landing right after $a,
                    // between the first attempt's read and its write, then
                    // force this attempt's own write to collide with it.
                    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);

                    return 'M';
                }

                return 'C';
            }
        };
    });

    $mover = new ShoppingListEntry(['list_id' => 1]);
    $mover->placeAfter($a);

    expect($log->calls)->toBe([
        ['A', null], // first attempt: nothing after $a yet
        ['A', 'M'],  // retry: re-queried and found the concurrently inserted row
    ]);
    expect($mover->rank)->toBe('C');
});

it('throws RankConflictException once retries are exhausted', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    app()->bind(RankGenerator::class, fn () => new class implements RankGenerator
    {
        public function between(?string $lower, ?string $upper): string
        {
            return 'B'; // always collides
        }
    });

    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);
})->throws(RankConflictException::class);

it('honors a maxRetries binding smaller than the default', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    $callCount = new stdClass;
    $callCount->n = 0;

    app()->bind(RankGenerator::class, function () use ($callCount) {
        return new class($callCount) implements RankGenerator
        {
            public function __construct(private stdClass $count) {}

            public function between(?string $lower, ?string $upper): string
            {
                $this->count->n++;

                return 'B'; // always collides
            }
        };
    });

    app()->bind(Positioner::class, fn ($app) => new Positioner($app->make(RankGenerator::class), maxRetries: 1));

    $entry = new ShoppingListEntry(['list_id' => 1]);

    try {
        $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);
    } catch (RankConflictException) {
        // expected
    }

    // 1 initial attempt + 1 retry = 2 calls, not the default's 1 + 3 = 4.
    expect($callCount->n)->toBe(2);
});
