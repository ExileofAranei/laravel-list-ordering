<?php

use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;

// --- Each wrapper produces the same result as its equivalent placeInto() call.
// Two structurally identical, independent groups are used for each pair so the
// two computations don't collide with each other on the same unique index.

it('placeAtEnd is equivalent to placeInto($group, null, null)', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'A');

    $viaPrimitive = new ShoppingListEntry(['list_id' => 1]);
    $viaPrimitive->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    $viaWrapper = new ShoppingListEntry(['list_id' => 2]);
    $viaWrapper->placeAtEnd(GroupKey::of(['list_id' => 2]));

    expect($viaWrapper->rank)->toBe($viaPrimitive->rank);
});

it('placeAtStart is equivalent to placeInto($group, null, $firstOfGroup)', function () {
    $firstInGroup1 = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');
    $firstInGroup2 = seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'M');

    $viaPrimitive = new ShoppingListEntry(['list_id' => 1]);
    $viaPrimitive->placeInto(GroupKey::of(['list_id' => 1]), null, $firstInGroup1);

    $viaWrapper = new ShoppingListEntry(['list_id' => 2]);
    $viaWrapper->placeAtStart(GroupKey::of(['list_id' => 2]));

    expect($viaWrapper->rank)->toBe($viaPrimitive->rank);
});

it('placeAtStart on an empty group behaves like placeInto($group, null, null)', function () {
    $viaPrimitive = new ShoppingListEntry(['list_id' => 1]);
    $viaPrimitive->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    $viaWrapper = new ShoppingListEntry(['list_id' => 2]);
    $viaWrapper->placeAtStart(GroupKey::of(['list_id' => 2]));

    expect($viaWrapper->rank)->toBe($viaPrimitive->rank);
});

it('placeAfter is equivalent to placeInto($anchor->currentGroupKey(), $anchor, null)', function () {
    $anchor1 = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');
    $anchor2 = seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'M');

    $viaPrimitive = new ShoppingListEntry(['list_id' => 1]);
    $viaPrimitive->placeInto($anchor1->currentGroupKey(), $anchor1, null);

    $viaWrapper = new ShoppingListEntry(['list_id' => 2]);
    $viaWrapper->placeAfter($anchor2);

    expect($viaWrapper->rank)->toBe($viaPrimitive->rank);
});

it('placeBefore is equivalent to placeInto($anchor->currentGroupKey(), null, $anchor)', function () {
    $anchor1 = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');
    $anchor2 = seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'M');

    $viaPrimitive = new ShoppingListEntry(['list_id' => 1]);
    $viaPrimitive->placeInto($anchor1->currentGroupKey(), null, $anchor1);

    $viaWrapper = new ShoppingListEntry(['list_id' => 2]);
    $viaWrapper->placeBefore($anchor2);

    expect($viaWrapper->rank)->toBe($viaPrimitive->rank);
});

// --- User story 11: the anchor's group is read at call time -----------------

it('placeAfter lands in the group the anchor currently belongs to, even if the anchor moved since it was referenced', function () {
    $anchor = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');

    // The anchor itself moves to another list first.
    $anchor->placeInto(GroupKey::of(['list_id' => 2]), null, null);
    $anchor->refresh();

    $follower = new ShoppingListEntry(['list_id' => 1]);
    $follower->placeAfter($anchor);

    expect($follower->list_id)->toBe(2);
    expect($follower->rank)->toBeGreaterThan($anchor->rank);
});

it('placeBefore lands in the group the anchor currently belongs to, even if the anchor moved since it was referenced', function () {
    $anchor = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'Z');

    $anchor->placeInto(GroupKey::of(['list_id' => 2]), null, null);
    $anchor->refresh();

    $follower = new ShoppingListEntry(['list_id' => 1]);
    $follower->placeBefore($anchor);

    expect($follower->list_id)->toBe(2);
    expect($follower->rank)->toBeLessThan($anchor->rank);
});
