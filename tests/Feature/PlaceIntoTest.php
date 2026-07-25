<?php

use ExileOfAranei\ListOrdering\Events\Positioned;
use ExileOfAranei\ListOrdering\Exceptions\InvalidAnchorException;
use ExileOfAranei\ListOrdering\Exceptions\InvalidGroupKeyException;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\SpiceJar;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// --- §7: all three grouping shapes -----------------------------------------

it('places into a composite-key group (two not-null columns)', function () {
    $jar = new SpiceJar(['pantry_id' => 1, 'shelf' => 'top']);

    $jar->placeInto(GroupKey::of(['pantry_id' => 1, 'shelf' => 'top']), null, null);

    expect($jar->exists)->toBeTrue();
    expect($jar->rank)->not->toBeNull();
});

it('places into a single-column group', function () {
    $entry = new ShoppingListEntry(['list_id' => 1]);

    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    expect($entry->exists)->toBeTrue();
    expect($entry->rank)->not->toBeNull();
});

it('places into an empty-columns (global) group', function () {
    $item = new WishlistItem;

    $item->placeInto(GroupKey::of([]), null, null);

    expect($item->exists)->toBeTrue();
    expect($item->rank)->not->toBeNull();
});

// --- §8: the eight anchor-resolution scenarios ------------------------------

it('scenario 1: inserts into the end of an empty list', function () {
    $entry = new ShoppingListEntry(['list_id' => 1]);

    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    expect(ShoppingListEntry::where('list_id', 1)->count())->toBe(1);
});

it('scenario 2: inserts between two neighbors of the same list', function () {
    $left = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    $right = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'Z']);

    $middle = new ShoppingListEntry(['list_id' => 1]);
    $middle->placeInto(GroupKey::of(['list_id' => 1]), $left, $right);

    expect($middle->rank)->toBeGreaterThan('A')->toBeLessThan('Z');
});

it('scenario 3: reorders within a list — was last, becomes second', function () {
    $a = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    $b = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);
    $c = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'Z']);

    $c->placeInto(GroupKey::of(['list_id' => 1]), $a, $b);

    $order = ShoppingListEntry::ordered(GroupKey::of(['list_id' => 1]))->pluck('id')->all();

    expect($order)->toBe([$a->id, $c->id, $b->id]);
});

it('scenario 4: moves to another list, between two of its elements', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    $target = ShoppingListEntry::create(['list_id' => 2, 'rank' => 'A']);
    $targetEnd = ShoppingListEntry::create(['list_id' => 2, 'rank' => 'Z']);

    $mover = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);
    $mover->placeInto(GroupKey::of(['list_id' => 2]), $target, $targetEnd);

    $mover->refresh();

    expect($mover->list_id)->toBe(2);
    expect($mover->rank)->toBeGreaterThan('A')->toBeLessThan('Z');
});

it('scenario 5: moves to another, empty list', function () {
    $mover = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $mover->placeInto(GroupKey::of(['list_id' => 99]), null, null);
    $mover->refresh();

    expect($mover->list_id)->toBe(99);
    expect(ShoppingListEntry::where('list_id', 99)->count())->toBe(1);
});

it('scenario 6: "after X" lands in the group X currently belongs to, even if X moved since', function () {
    $x = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    // X itself moves to list 2 first.
    $x->placeInto(GroupKey::of(['list_id' => 2]), null, null);
    $x->refresh();

    $follower = new ShoppingListEntry(['list_id' => 1]);
    $follower->placeInto($x->currentGroupKey(), $x, null);

    expect($follower->list_id)->toBe(2);
    expect($follower->rank)->toBeGreaterThan($x->rank);
});

it('scenario 7: "after X" where X no longer exists, but the next neighbor Y is known', function () {
    $y = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);

    $restored = new ShoppingListEntry(['list_id' => 1]);
    $restored->placeInto(GroupKey::of(['list_id' => 1]), null, $y);

    expect($restored->rank)->toBeLessThan('M');
});

it('scenario 8: no anchor is known at all, on a non-empty list', function () {
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);

    $restored = new ShoppingListEntry(['list_id' => 1]);
    $restored->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    expect($restored->rank)->toBeGreaterThan('M');
});

// --- Anchors are bounds, not adjacency requirements -------------------------

it('generates a rank strictly between two explicit anchors even when other rows already sit between them', function () {
    $left = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    // Deliberately not 'M' (the deterministic midpoint of 'A'..'Z') — that
    // would collide with the freshly generated rank below, which is a
    // concurrency-retry concern (ticket 06), not what this test is about.
    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'C']); // sits between $left and $right
    $right = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'Z']);

    $restored = new ShoppingListEntry(['list_id' => 1]);
    $restored->placeInto(GroupKey::of(['list_id' => 1]), $left, $right);

    expect($restored->rank)->toBeGreaterThan('A')->toBeLessThan('Z');
});

// --- Validation --------------------------------------------------------------

it('throws when the GroupKey columns do not match the declared group columns', function () {
    $entry = new ShoppingListEntry(['list_id' => 1]);

    $entry->placeInto(GroupKey::of(['wrong_column' => 1]), null, null);
})->throws(InvalidGroupKeyException::class);

it('throws when an explicitly passed anchor does not belong to the target group', function () {
    $foreignAnchor = ShoppingListEntry::create(['list_id' => 2, 'rank' => 'A']);

    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeInto(GroupKey::of(['list_id' => 1]), $foreignAnchor, null);
})->throws(InvalidAnchorException::class);

// --- Т4: group columns and rank change atomically ---------------------------

it('changes the group columns and the rank in a single write', function () {
    $mover = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    DB::enableQueryLog();
    $mover->placeInto(GroupKey::of(['list_id' => 2]), null, null);
    $writes = collect(DB::getQueryLog())->filter(
        fn (array $entry) => str_starts_with(strtolower($entry['query']), 'update')
            || str_starts_with(strtolower($entry['query']), 'insert')
    );
    DB::disableQueryLog();

    expect($writes)->toHaveCount(1);
});

it('writes a brand-new, non-persistent model with a single INSERT', function () {
    $entry = new ShoppingListEntry(['list_id' => 1]);

    DB::enableQueryLog();
    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);
    $writes = collect(DB::getQueryLog())->filter(
        fn (array $entry) => str_starts_with(strtolower($entry['query']), 'update')
            || str_starts_with(strtolower($entry['query']), 'insert')
    );
    DB::disableQueryLog();

    expect($writes)->toHaveCount(1);
    expect($entry->exists)->toBeTrue();
});

// --- Untouched-row invariant --------------------------------------------------

it('leaves the rank of every untouched row byte-identical after a placeInto() call', function () {
    $a = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    $b = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'M']);
    $c = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'Z']);

    $before = ShoppingListEntry::whereIn('id', [$a->id, $b->id])->orderBy('id')->pluck('rank')->all();

    $c->placeInto(GroupKey::of(['list_id' => 1]), $a, $b);

    $after = ShoppingListEntry::whereIn('id', [$a->id, $b->id])->orderBy('id')->pluck('rank')->all();

    expect($after)->toBe($before);
});

// --- Positioned event ---------------------------------------------------------

it('dispatches Positioned with a from group that differs from the to group on a cross-list move', function () {
    Event::fake([Positioned::class]);

    $mover = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);
    $mover->placeInto(GroupKey::of(['list_id' => 2]), null, null);

    Event::assertDispatched(function (Positioned $event) use ($mover) {
        return $event->model->is($mover)
            && $event->from !== null
            && ! $event->from->equals($event->to)
            && $event->from->equals(GroupKey::of(['list_id' => 1]))
            && $event->to->equals(GroupKey::of(['list_id' => 2]));
    });
});

it('dispatches Positioned with a null from group when the model is new', function () {
    Event::fake([Positioned::class]);

    $entry = new ShoppingListEntry(['list_id' => 1]);
    $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);

    Event::assertDispatched(fn (Positioned $event) => $event->from === null);
});
