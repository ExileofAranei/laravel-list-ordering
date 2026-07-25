<?php

use ExileOfAranei\ListOrdering\Internal\IndexInspector;
use ExileOfAranei\ListOrdering\Testing\AssertsOrderingIndex;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\MisconfiguredEntry;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\ShoppingListEntry;
use ExileOfAranei\ListOrdering\Tests\Fixtures\Models\SpiceJar;
use PHPUnit\Framework\AssertionFailedError;

uses(AssertsOrderingIndex::class);

// --- IndexInspector (shared by the command and the assertion trait) --------

it('finds no problems when the declared columns match the unique index', function () {
    expect((new IndexInspector)->diff(ShoppingListEntry::class))->toBe([]);
});

it('finds no problems for a composite-key model either', function () {
    expect((new IndexInspector)->diff(SpiceJar::class))->toBe([]);
});

it('reports missing and unexpected columns when they diverge from the unique index', function () {
    $problems = (new IndexInspector)->diff(MisconfiguredEntry::class);

    expect($problems)->not->toBe([]);
    expect(implode(' ', $problems))->toContain('wrong_column')->toContain('list_id');
});

// --- Artisan command ----------------------------------------------------------

it('command exits successfully for a model whose index matches', function () {
    $this->artisan('list-ordering:check-index', ['model' => ShoppingListEntry::class])
        ->assertExitCode(0);
});

it('command exits with a failure and reports the mismatch', function () {
    $this->artisan('list-ordering:check-index', ['model' => MisconfiguredEntry::class])
        ->assertExitCode(1)
        ->expectsOutputToContain('wrong_column');
});

it('command fails cleanly when no model and no --all are given', function () {
    $this->artisan('list-ordering:check-index')
        ->assertExitCode(1);
});

// --- Testing\AssertsOrderingIndex ------------------------------------------

it('assertOrderingIndexMatches passes for a model whose index matches', function () {
    $this->assertOrderingIndexMatches(ShoppingListEntry::class);
});

it('assertOrderingIndexMatches fails for a model whose index does not match', function () {
    $this->assertOrderingIndexMatches(MisconfiguredEntry::class);
})->throws(AssertionFailedError::class);
