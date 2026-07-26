<?php

use ExileOfAranei\ListOrdering\Exceptions\InvalidBatchOrderException;
use ExileOfAranei\ListOrdering\Support\BatchReorderer;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;

function seedShoppingList(int $listId, int $count): array
{
    $entries = [];

    foreach (range(1, $count) as $i) {
        $entry = new ShoppingListEntry(['list_id' => $listId]);
        $entry->placeAtEnd(GroupKey::of(['list_id' => $listId]));
        $entries[] = $entry;
    }

    return $entries;
}

it('reorders a whole list to match the given key order', function () {
    [$a, $b, $c, $d] = seedShoppingList(1, 4);

    BatchReorderer::apply(
        ShoppingListEntry::class,
        GroupKey::of(['list_id' => 1]),
        [$d->id, $b->id, $a->id, $c->id],
    );

    $ordered = ShoppingListEntry::ordered(GroupKey::of(['list_id' => 1]))->pluck('id')->all();

    expect($ordered)->toBe([$d->id, $b->id, $a->id, $c->id]);
});

it('leaves an already-correctly-ordered list untouched', function () {
    [$a, $b, $c] = seedShoppingList(1, 3);
    $ranksBefore = [$a->rank, $b->rank, $c->rank];

    BatchReorderer::apply(
        ShoppingListEntry::class,
        GroupKey::of(['list_id' => 1]),
        [$a->id, $b->id, $c->id],
    );

    $ranksAfter = ShoppingListEntry::ordered(GroupKey::of(['list_id' => 1]))->pluck('rank')->all();

    expect($ranksAfter)->toBe($ranksBefore);
});

it('moves only the one record that actually changed position', function () {
    [$a, $b, $c, $d] = seedShoppingList(1, 4);
    $rankBBefore = $b->rank;

    // Move $d to the front; a, b, c keep their relative order.
    BatchReorderer::apply(
        ShoppingListEntry::class,
        GroupKey::of(['list_id' => 1]),
        [$d->id, $a->id, $b->id, $c->id],
    );

    $b->refresh();

    expect($b->rank)->toBe($rankBBefore);
});

it('rejects a key set that does not match the group current members', function () {
    [$a, $b] = seedShoppingList(1, 2);

    expect(fn () => BatchReorderer::apply(
        ShoppingListEntry::class,
        GroupKey::of(['list_id' => 1]),
        [$a->id, $b->id, 999],
    ))->toThrow(InvalidBatchOrderException::class);
});

it('rejects a key set missing a member of the group', function () {
    [$a, $b] = seedShoppingList(1, 2);

    expect(fn () => BatchReorderer::apply(
        ShoppingListEntry::class,
        GroupKey::of(['list_id' => 1]),
        [$a->id],
    ))->toThrow(InvalidBatchOrderException::class);
});

it('does not touch a different group', function () {
    [$a, $b] = seedShoppingList(1, 2);
    [$x, $y] = seedShoppingList(2, 2);
    $xRankBefore = $x->rank;
    $yRankBefore = $y->rank;

    BatchReorderer::apply(
        ShoppingListEntry::class,
        GroupKey::of(['list_id' => 1]),
        [$b->id, $a->id],
    );

    $x->refresh();
    $y->refresh();

    expect($x->rank)->toBe($xRankBefore);
    expect($y->rank)->toBe($yRankBefore);
});
