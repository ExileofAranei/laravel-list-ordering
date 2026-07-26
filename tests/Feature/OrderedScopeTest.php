<?php

use ExileOfAranei\ListOrdering\Exceptions\RankConflictException;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\SpiceJar;

it('sorts correctly within each group when no GroupKey is given, across two independent groups', function () {
    // Ranks are set directly, without placeInto() — this ticket only covers
    // storage and reading, not placeInto() itself.
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'B');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'D');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'Z');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'X');

    $all = ShoppingListEntry::ordered()->get();

    $listOne = $all->where('list_id', 1)->pluck('rank')->values()->all();
    $listTwo = $all->where('list_id', 2)->pluck('rank')->values()->all();

    // Order within each group is correct — nothing is asserted about how the
    // two groups interleave relative to each other.
    expect($listOne)->toBe(['A', 'B', 'D']);
    expect($listTwo)->toBe(['X', 'Z']);
});

it('filters to exactly the requested group and sorts it, given a GroupKey', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'B');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'M');

    $result = ShoppingListEntry::ordered(GroupKey::of(['list_id' => 1]))->get();

    expect($result->pluck('rank')->all())->toBe(['A', 'B']);
    expect($result->pluck('list_id')->unique()->all())->toBe([1]);
});

it('filters a composite-key group and sorts it, given a GroupKey', function () {
    seedAtRank(SpiceJar::class, ['pantry_id' => 1, 'shelf' => 'top'], 'B');
    seedAtRank(SpiceJar::class, ['pantry_id' => 1, 'shelf' => 'top'], 'A');
    seedAtRank(SpiceJar::class, ['pantry_id' => 1, 'shelf' => 'bottom'], 'M');
    seedAtRank(SpiceJar::class, ['pantry_id' => 2, 'shelf' => 'top'], 'Q');

    $result = SpiceJar::ordered(GroupKey::of(['pantry_id' => 1, 'shelf' => 'top']))->get();

    expect($result->pluck('rank')->all())->toBe(['A', 'B']);
});

it('treats an empty GroupKey as one global list spanning the whole table', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'C');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'A');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'B');

    $result = ShoppingListEntry::ordered(GroupKey::of([]))->get();

    expect($result->pluck('rank')->all())->toBe(['A', 'B', 'C']);
});

it('enforces the composite unique index of group columns plus rank', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');

    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');
})->throws(RankConflictException::class);

it('sorts descending when given direction desc', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'B');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'C');

    $result = ShoppingListEntry::ordered(GroupKey::of(['list_id' => 1]), direction: 'desc')->get();

    expect($result->pluck('rank')->all())->toBe(['C', 'B', 'A']);
});

it('allows the same rank to be reused across different groups', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'A');

    expect(ShoppingListEntry::count())->toBe(2);
});
