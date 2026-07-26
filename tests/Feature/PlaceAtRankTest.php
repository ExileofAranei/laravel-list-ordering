<?php

use ExileOfAranei\ListOrdering\Exceptions\GuardedColumnMutationException;
use ExileOfAranei\ListOrdering\Exceptions\RankConflictException;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;

it('places a new record at the exact rank given', function () {
    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeAtRank(GroupKey::of(['list_id' => 1]), 'M');

    expect($entry->rank)->toBe('M');
    expect($entry->list_id)->toBe(1);
});

it('throws RankConflictException when the exact rank is already taken in the group', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');

    $entry = new ShoppingListEntry(['list_id' => 1]);

    expect(fn () => $entry->placeAtRank(GroupKey::of(['list_id' => 1]), 'M'))
        ->toThrow(RankConflictException::class);
});

it('does not collide with the same rank value used in a different group', function () {
    seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');

    $entry = new ShoppingListEntry(['list_id' => 2]);
    $entry->placeAtRank(GroupKey::of(['list_id' => 2]), 'M');

    expect($entry->rank)->toBe('M');
});

it('rejects placeAtRank on an already-persisted record via the ordering guard', function () {
    $entry = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'A');

    expect(fn () => $entry->placeAtRank(GroupKey::of(['list_id' => 1]), 'Z'))
        ->toThrow(GuardedColumnMutationException::class);
});

it('cloneRankFrom copies the exact rank of the source into the clone\'s own (different) group', function () {
    $source = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');

    $clone = new ShoppingListEntry(['list_id' => 2]);
    $clone->cloneRankFrom($source);

    expect($clone->rank)->toBe('M');
    expect($clone->list_id)->toBe(2);
});

it('cloneRankFrom is equivalent to placeAtRank($this-own-group, $source->rank)', function () {
    $source = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');

    $viaPrimitive = new ShoppingListEntry(['list_id' => 2]);
    $viaPrimitive->placeAtRank(GroupKey::of(['list_id' => 2]), 'M');

    $viaWrapper = new ShoppingListEntry(['list_id' => 3]);
    $viaWrapper->cloneRankFrom($source);

    expect($viaWrapper->rank)->toBe($viaPrimitive->rank);
});

it('cloneRankFrom throws RankConflictException if the clone\'s own group already has that rank', function () {
    $source = seedAtRank(ShoppingListEntry::class, ['list_id' => 1], 'M');
    seedAtRank(ShoppingListEntry::class, ['list_id' => 2], 'M');

    $clone = new ShoppingListEntry(['list_id' => 2]);

    expect(fn () => $clone->cloneRankFrom($source))
        ->toThrow(RankConflictException::class);
});
