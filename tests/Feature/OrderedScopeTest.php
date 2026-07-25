<?php

use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\SpiceJar;
use Illuminate\Database\QueryException;

it('sorts correctly within each group when no GroupKey is given, across two independent groups', function () {
    // Ranks are set directly, without placeInto() — this ticket only covers
    // storage and reading, not placeInto() itself.
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'D']);
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    ShoppingListEntry::create(['list_id' => 2, 'rank' => 'Z']);
    ShoppingListEntry::create(['list_id' => 2, 'rank' => 'X']);

    $all = ShoppingListEntry::ordered()->get();

    $listOne = $all->where('list_id', 1)->pluck('rank')->values()->all();
    $listTwo = $all->where('list_id', 2)->pluck('rank')->values()->all();

    // Order within each group is correct — nothing is asserted about how the
    // two groups interleave relative to each other.
    expect($listOne)->toBe(['A', 'B', 'D']);
    expect($listTwo)->toBe(['X', 'Z']);
});

it('filters to exactly the requested group and sorts it, given a GroupKey', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    ShoppingListEntry::create(['list_id' => 2, 'rank' => 'M']);

    $result = ShoppingListEntry::ordered(GroupKey::of(['list_id' => 1]))->get();

    expect($result->pluck('rank')->all())->toBe(['A', 'B']);
    expect($result->pluck('list_id')->unique()->all())->toBe([1]);
});

it('filters a composite-key group and sorts it, given a GroupKey', function () {
    SpiceJar::create(['pantry_id' => 1, 'shelf' => 'top', 'rank' => 'B']);
    SpiceJar::create(['pantry_id' => 1, 'shelf' => 'top', 'rank' => 'A']);
    SpiceJar::create(['pantry_id' => 1, 'shelf' => 'bottom', 'rank' => 'M']);
    SpiceJar::create(['pantry_id' => 2, 'shelf' => 'top', 'rank' => 'Q']);

    $result = SpiceJar::ordered(GroupKey::of(['pantry_id' => 1, 'shelf' => 'top']))->get();

    expect($result->pluck('rank')->all())->toBe(['A', 'B']);
});

it('treats an empty GroupKey as one global list spanning the whole table', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'C']);
    ShoppingListEntry::create(['list_id' => 2, 'rank' => 'A']);
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);

    $result = ShoppingListEntry::ordered(GroupKey::of([]))->get();

    expect($result->pluck('rank')->all())->toBe(['A', 'B', 'C']);
});

it('enforces the composite unique index of group columns plus rank', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
})->throws(QueryException::class);

it('allows the same rank to be reused across different groups', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    ShoppingListEntry::create(['list_id' => 2, 'rank' => 'A']);

    expect(ShoppingListEntry::count())->toBe(2);
});
