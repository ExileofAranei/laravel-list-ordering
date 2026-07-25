<?php

use ExileOfAranei\ListOrdering\Generators\FractionalRankGenerator;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('sorts ranks in the database in exactly the same order as PHP byte-sort', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite compares strings byte-wise by default regardless of the '
            .'macro\'s driver branch — this does not validate driver-specific '
            .'collation syntax.'
        );
    }

    $generator = new FractionalRankGenerator;

    // Interleaved appends and prepends (not just sequential appends) produce
    // ranks of varying lengths, which is what actually stresses byte-wise
    // comparison across driver collations — mirrors the generator's own
    // "interleaved appends and prepends" unit test in
    // tests/Unit/Generators/FractionalRankGeneratorTest.php.
    $ranks = [$generator->between(null, null)];

    for ($i = 0; $i < 50; $i++) {
        if ($i % 2 === 0) {
            $ranks[] = $generator->between(end($ranks), null);
        } else {
            array_unshift($ranks, $generator->between(null, $ranks[0]));
        }
    }

    // Insert in a shuffled order — proves the database is doing the
    // sorting, not that rows merely came back in insertion order.
    $shuffled = $ranks;
    shuffle($shuffled);

    foreach ($shuffled as $rank) {
        ShoppingListEntry::create(['list_id' => 1, 'rank' => $rank]);
    }

    $fromDatabase = ShoppingListEntry::where('list_id', 1)->orderBy('rank')->pluck('rank')->all();

    $expected = $ranks;
    sort($expected, SORT_STRING);

    expect($fromDatabase)->toBe($expected);
})->group('collation-critical');

it('creates the rank column with the correct driver-specific collation', function () {
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
        $this->markTestSkipped(
            'SQLite gets no explicit collation() call from the macro — '
            .'there is nothing driver-specific to assert here.'
        );
    }

    $column = collect(Schema::getColumns('shopping_list_entries'))
        ->firstWhere('name', 'rank');

    expect($column)->not->toBeNull();

    match ($driver) {
        // mysql/mariadb get no explicit collation: charset('binary') turns the
        // column into VARBINARY, which has no character set or collation of
        // its own — assert on the actual type it produces instead.
        'mysql', 'mariadb' => expect($column['type_name'])->toBe('varbinary'),
        'pgsql' => expect($column['collation'])->toBe('C'),
        default => throw new RuntimeException("Unhandled driver in this test: {$driver}"),
    };
})->group('collation-critical');
