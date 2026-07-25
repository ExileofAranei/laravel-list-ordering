<?php

use ExileOfAranei\ListOrdering\Support\GroupKey;
use Illuminate\Database\Eloquent\Model;

function groupKeyProbeModel(): Model
{
    return new class extends Model
    {
        protected $table = 'group_key_probe';
    };
}

it('equals is independent of column order', function () {
    $a = GroupKey::of(['board_id' => 1, 'column' => 'done']);
    $b = GroupKey::of(['column' => 'done', 'board_id' => 1]);

    expect($a->equals($b))->toBeTrue();
});

it('equals is false when a value differs', function () {
    $a = GroupKey::of(['board_id' => 1]);
    $b = GroupKey::of(['board_id' => 2]);

    expect($a->equals($b))->toBeFalse();
});

it('equals is false when the column set differs', function () {
    $a = GroupKey::of(['board_id' => 1]);
    $b = GroupKey::of(['board_id' => 1, 'column' => 'done']);

    expect($a->equals($b))->toBeFalse();
});

it('equals treats two empty column sets as equal', function () {
    $a = GroupKey::of([]);
    $b = GroupKey::of([]);

    expect($a->equals($b))->toBeTrue();
});

it('columnNames returns the declared column names', function () {
    $key = GroupKey::of(['board_id' => 1, 'column' => 'done']);

    expect($key->columnNames())->toBe(['board_id', 'column']);
});

it('toArray returns the raw column map', function () {
    $key = GroupKey::of(['board_id' => 1, 'column' => 'done']);

    expect($key->toArray())->toBe(['board_id' => 1, 'column' => 'done']);
});

it('applyTo adds a where constraint for a non-null value', function () {
    $query = GroupKey::of(['board_id' => 42])->applyTo(groupKeyProbeModel()->newQuery());

    expect($query->getQuery()->wheres)->toContain([
        'type' => 'Basic',
        'column' => 'board_id',
        'operator' => '=',
        'value' => 42,
        'boolean' => 'and',
    ]);
});

it('applyTo uses whereNull for a null-valued column', function () {
    $query = GroupKey::of(['deleted_at' => null])->applyTo(groupKeyProbeModel()->newQuery());

    expect($query->getQuery()->wheres)->toContain([
        'type' => 'Null',
        'column' => 'deleted_at',
        'boolean' => 'and',
    ]);
});

it('applyTo mixes whereNull and where across columns in the same key', function () {
    $query = GroupKey::of(['board_id' => 42, 'archived_at' => null])
        ->applyTo(groupKeyProbeModel()->newQuery());

    $wheres = $query->getQuery()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'board_id',
        'operator' => '=',
        'value' => 42,
        'boolean' => 'and',
    ])->toContain([
        'type' => 'Null',
        'column' => 'archived_at',
        'boolean' => 'and',
    ]);
});

it('applyTo adds no constraints for an empty column set (global list)', function () {
    $query = GroupKey::of([])->applyTo(groupKeyProbeModel()->newQuery());

    expect($query->getQuery()->wheres)->toBe([]);
});
