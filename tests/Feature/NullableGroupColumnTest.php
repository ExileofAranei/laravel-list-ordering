<?php

use ExileOfAranei\ListOrdering\Support\GroupKey;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\Note;

it('places into the end of an empty null-group list', function () {
    $note = new Note;

    $note->placeInto(GroupKey::of(['notebook_id' => null]), null, null);

    expect($note->exists)->toBeTrue();
    expect($note->notebook_id)->toBeNull();
    expect($note->rank)->not->toBeNull();
});

it('inserts between two neighbors that are both in the null group', function () {
    $left = Note::create(['notebook_id' => null, 'rank' => 'A']);
    $right = Note::create(['notebook_id' => null, 'rank' => 'Z']);

    $middle = new Note;
    $middle->placeInto(GroupKey::of(['notebook_id' => null]), $left, $right);

    expect($middle->notebook_id)->toBeNull();
    expect($middle->rank)->toBeGreaterThan('A')->toBeLessThan('Z');
});

it('placeAfter lands in the null group when the anchor itself currently belongs to it', function () {
    $anchor = Note::create(['notebook_id' => null, 'rank' => 'A']);

    $follower = new Note;
    $follower->placeAfter($anchor);

    expect($follower->notebook_id)->toBeNull();
    expect($follower->rank)->toBeGreaterThan($anchor->rank);
});
