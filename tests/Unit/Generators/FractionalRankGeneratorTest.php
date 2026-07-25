<?php

use ExileOfAranei\ListOrdering\Generators\FractionalRankGenerator;

beforeEach(function () {
    $this->generator = new FractionalRankGenerator;
});

it('generates a value strictly between two explicit bounds', function () {
    $mid = $this->generator->between('A', 'Z');

    expect($mid)->toBeGreaterThan('A')->toBeLessThan('Z');
});

it('generates a seed value when both bounds are unbounded', function () {
    $seed = $this->generator->between(null, null);

    expect($seed)->not->toBe('');
});

it('generates a value greater than a lower bound with no upper bound', function () {
    $rank = $this->generator->between('m', null);

    expect($rank)->toBeGreaterThan('m');
});

it('generates a value less than an upper bound with no lower bound', function () {
    $rank = $this->generator->between(null, 'm');

    expect($rank)->toBeLessThan('m');
});

it('is deterministic for identical bounds', function () {
    expect($this->generator->between('A', 'Z'))->toBe($this->generator->between('A', 'Z'));
});

it('rejects an inverted bound pair', function () {
    $this->generator->between('Z', 'A');
})->throws(InvalidArgumentException::class);

it('rejects equal bounds', function () {
    $this->generator->between('M', 'M');
})->throws(InvalidArgumentException::class);

it('stays strictly between an interval that is repeatedly bisected', function () {
    $lower = 'A';
    $upper = 'Z';

    for ($i = 0; $i < 50; $i++) {
        $mid = $this->generator->between($lower, $upper);

        expect($mid)->toBeGreaterThan($lower)->toBeLessThan($upper);

        $upper = $mid;
    }
});

it('produces byte-sortable results for sequential appends at an open end', function () {
    $ranks = [];
    $last = null;

    for ($i = 0; $i < 200; $i++) {
        $last = $this->generator->between($last, null);
        $ranks[] = $last;
    }

    $sorted = $ranks;
    sort($sorted, SORT_STRING);

    expect($ranks)->toBe($sorted);
});

it('produces byte-sortable results for sequential prepends at an open start', function () {
    $ranks = [];
    $first = null;

    for ($i = 0; $i < 200; $i++) {
        $first = $this->generator->between(null, $first);
        array_unshift($ranks, $first);
    }

    $sorted = $ranks;
    sort($sorted, SORT_STRING);

    expect($ranks)->toBe($sorted);
});

it('stays byte-sortable under an interleaved mix of appends and prepends', function () {
    $ranks = [$this->generator->between(null, null)];

    for ($i = 0; $i < 100; $i++) {
        if ($i % 2 === 0) {
            $ranks[] = $this->generator->between(end($ranks), null);
        } else {
            array_unshift($ranks, $this->generator->between(null, $ranks[0]));
        }
    }

    $sorted = $ranks;
    sort($sorted, SORT_STRING);

    expect($ranks)->toBe($sorted);
});

it('bounds rank growth under repeated appends at the same open end', function () {
    // Measured growth is roughly one extra character every ~60 inserts (each
    // character position absorbs close to the full 62-value alphabet range
    // before a rank needs to grow). 1000 sequential appends at the same open
    // boundary measure at 17 characters; assert a generous multiple of that
    // as the regression ceiling, not the exact figure.
    $last = null;

    for ($i = 0; $i < 1000; $i++) {
        $last = $this->generator->between($last, null);
    }

    expect(strlen($last))->toBeLessThan(30);
});

it('bounds rank growth under repeated prepends at the same open start', function () {
    // Mirrors the append growth bound: prepending is symmetric to appending,
    // so it must not degrade faster than the append case.
    $first = null;

    for ($i = 0; $i < 1000; $i++) {
        $first = $this->generator->between(null, $first);
    }

    expect(strlen($first))->toBeLessThan(30);
});
