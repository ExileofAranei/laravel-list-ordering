<?php

use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\MigratingItem;

it('places repeated rows after an unranked row instead of colliding on it', function () {
    // Pre-existing row, not yet backfilled onto the rank column.
    MigratingItem::create(['list_id' => 1, 'position' => 0, 'rank' => null]);

    $c = new MigratingItem(['list_id' => 1]);
    $c->placeAtEnd(GroupKey::of(['list_id' => 1]));

    $d = new MigratingItem(['list_id' => 1]);
    $d->placeAtEnd(GroupKey::of(['list_id' => 1]));

    expect($c->rank)->not->toBeNull();
    expect($d->rank)->not->toBeNull();
    expect($d->rank > $c->rank)->toBeTrue();
});
