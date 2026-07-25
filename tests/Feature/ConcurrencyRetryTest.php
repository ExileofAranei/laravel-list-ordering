<?php

use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Exceptions\RankConflictException;
use ExileOfAranei\ListOrdering\Internal\Positioner;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;

it('recovers from a forced conflict on the first attempt via retry', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    $calls = 0;

    // A regular function() at this outer level, not fn(): arrow functions
    // capture enclosing variables by value, which would sever the closure
    // below from this $calls — a byref use() on it would bind to a copy
    // local to the arrow function's own vanished invocation, not to this one.
    app()->bind(RankGenerator::class, function () use (&$calls) {
        return fakeRankGenerator(function () use (&$calls) {
            $calls++;

            // Collide on the first attempt only.
            return $calls === 1 ? 'B' : 'Z';
        });
    });

    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    expect($entry->exists)->toBeTrue();
    expect($entry->rank)->toBe('Z');
    expect($calls)->toBe(2);
});

it('re-resolves a missing anchor fresh on each retry, not just the rank between stale bounds', function () {
    $a = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $log = [];

    app()->bind(RankGenerator::class, function () use (&$log) {
        return fakeRankGenerator(function (?string $lower, ?string $upper, int $call) use (&$log) {
            $log[] = [$lower, $upper];

            if ($call === 1) {
                // Simulate a concurrent writer landing right after $a,
                // between the first attempt's read and its write, then
                // force this attempt's own write to collide with it.
                ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);

                return 'M';
            }

            return 'C';
        });
    });

    $mover = new ShoppingListEntry(['list_id' => 1]);
    $mover->placeAfter($a);

    expect($log)->toBe([
        ['A', null], // first attempt: nothing after $a yet
        ['A', 'M'],  // retry: re-queried and found the concurrently inserted row
    ]);
    expect($mover->rank)->toBe('C');
});

it('re-resolves the upper bound of two explicit anchors fresh on retry, not just the rank between stale bounds', function () {
    $a = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    $z = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'Z']);

    $log = [];

    app()->bind(RankGenerator::class, function () use (&$log) {
        return fakeRankGenerator(function (?string $lower, ?string $upper, int $call) use (&$log) {
            $log[] = [$lower, $upper];

            if ($call === 1) {
                // Simulate a concurrent writer landing between $a and $z,
                // at the exact rank this attempt is about to write, then
                // force this attempt's own write to collide with it.
                ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);

                return 'M';
            }

            return 'C';
        });
    });

    $mover = new ShoppingListEntry(['list_id' => 1]);
    $mover->placeInto(GroupKey::of(['list_id' => 1]), $a, $z);

    expect($log)->toBe([
        ['A', 'Z'], // first attempt: both anchors used exactly as given
        ['A', 'M'], // retry: upper bound re-resolved to the concurrently inserted row
    ]);
    expect($mover->rank)->toBe('C');
});

it('throws RankConflictException once retries are exhausted', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    app()->bind(RankGenerator::class, fn () => fakeRankGenerator(fn () => 'B')); // always collides

    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);
})->throws(RankConflictException::class);

it('honors a maxRetries binding smaller than the default', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    $calls = 0;

    app()->bind(RankGenerator::class, function () use (&$calls) {
        return fakeRankGenerator(function () use (&$calls) {
            $calls++;

            return 'B'; // always collides
        });
    });

    app()->bind(Positioner::class, fn ($app) => new Positioner($app->make(RankGenerator::class), maxRetries: 1));

    $entry = new ShoppingListEntry(['list_id' => 1]);

    try {
        $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);
    } catch (RankConflictException) {
        // expected
    }

    // 1 initial attempt + 1 retry = 2 calls, not the default's 1 + 3 = 4.
    expect($calls)->toBe(2);
});
