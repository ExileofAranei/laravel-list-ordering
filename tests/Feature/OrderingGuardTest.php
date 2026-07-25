<?php

use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Exceptions\GuardedColumnMutationException;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;

it('throws when a group column is written directly on a persisted model', function () {
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $entry->list_id = 2;
    $entry->save();
})->throws(GuardedColumnMutationException::class);

it('throws when the rank column is written directly on a persisted model', function () {
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $entry->rank = 'Z';
    $entry->save();
})->throws(GuardedColumnMutationException::class);

it('does not throw for the same mutation made through placeInto()', function () {
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $entry->placeInto(GroupKey::of(['list_id' => 2]), null, null);

    expect($entry->list_id)->toBe(2);
});

it('does not throw when create() sets an explicit rank directly', function () {
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    expect($entry->exists)->toBeTrue();
});

it('does not throw for an unrelated column change on a persisted model', function () {
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    $entry->updated_at = now()->addMinute();
    $entry->save();

    expect($entry->wasChanged('updated_at'))->toBeTrue();
});

it('updates updated_at when a model moves via placeInto()', function () {
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    // A forced time gap, not just "some time passes": updated_at is stored
    // at second precision, so a move landing in the same second as create()
    // would leave the stored value unchanged even though save() ran again —
    // that would make this test pass without proving anything.
    Carbon\Carbon::setTestNow(now()->addMinute());
    $entry->placeInto(GroupKey::of(['list_id' => 2]), null, null);
    Carbon\Carbon::setTestNow();

    expect($entry->wasChanged('updated_at'))->toBeTrue();
});

it('clears the guard bypass flag after placeInto() even when the underlying write fails', function () {
    // Force the guarded save() inside placeInto() to fail deterministically:
    // bind a generator that always returns an already-taken rank, so the
    // unique index rejects the write (no retry exists yet; that's ticket 06).
    app()->bind(RankGenerator::class, fn () => fakeRankGenerator(fn () => 'B'));

    ShoppingListEntry::create(['list_id' => 1, 'rank' => 'B']);
    $entry = ShoppingListEntry::create(['list_id' => 1, 'rank' => 'A']);

    try {
        $entry->placeInto(GroupKey::of(['list_id' => 1]), null, null);
    } catch (Throwable) {
        // expected: the forced collision below proves the bypass flag was cleared
    }

    // If the bypass flag were left set, this direct mutation would NOT throw.
    $entry->list_id = 2;
    $entry->save();
})->throws(GuardedColumnMutationException::class);
