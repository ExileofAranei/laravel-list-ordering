# Laravel List Ordering

[![Latest Version on Packagist](https://img.shields.io/packagist/v/exileofaranei/laravel-list-ordering.svg?style=flat-square)](https://packagist.org/packages/exileofaranei/laravel-list-ordering)
[![GitHub Tests Action Status](https://github.com/exileofaranei/laravel-list-ordering/actions/workflows/run-tests.yml/badge.svg)](https://github.com/exileofaranei/laravel-list-ordering/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/exileofaranei/laravel-list-ordering.svg?style=flat-square)](https://packagist.org/packages/exileofaranei/laravel-list-ordering)

Tight ordering of records within grouped lists using fractional indices: insertion and movement modify a single row, not the entire list.

A list is defined by an arbitrary set of column values on the model — a composite key, a single column, or none at all (one global list). Order is stored as a byte-sortable string rank, not an integer position. Reordering within a list and moving between lists go through the same primitive: `placeInto()`, expressed entirely in terms of neighbor anchors, never integer positions.

The package knows nothing about the domain it's ordering. It has no concept of trees, hierarchies, or nested sets — it orders flat lists, however those lists are grouped.

## Installation

Install via Composer:

```bash
composer require exileofaranei/laravel-list-ordering
```

The service provider registers itself through Laravel package auto-discovery — no manual registration needed.

The package publishes no migration and no config file. You write your own migration for each model that needs ordering, using the `orderingRank()` Blueprint macro the package registers:

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('board_id');
    $table->string('column');
    $table->orderingRank(); // 64-char rank column, byte-comparable collation per driver
    $table->timestamps();

    $table->unique(['board_id', 'column', 'rank']);
});
```

The unique index always covers the model's group columns plus the rank column — this is what a concurrent write collides against, and what `list-ordering:check-index` (below) checks your migration against.

## Usage

### Declaring a model

A model implements `Orderable` and uses the `HasOrdering` trait. The trait supplies everything except `orderingGroupColumns()` — the one method that says which columns define the model's list.

**Composite key** (both columns not null):

```php
use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use ExileOfAranei\ListOrdering\Contracts\Orderable;

class Task extends Model implements Orderable
{
    use HasOrdering;

    public function orderingGroupColumns(): array
    {
        return ['board_id', 'column'];
    }
}
```

**Single column:**

```php
class GalleryImage extends Model implements Orderable
{
    use HasOrdering;

    public function orderingGroupColumns(): array
    {
        return ['gallery_id'];
    }
}
```

**Empty list of columns** (one global list spanning the whole table):

```php
class LandingFeature extends Model implements Orderable
{
    use HasOrdering;

    public function orderingGroupColumns(): array
    {
        return [];
    }
}
```

### Placing and moving records

`placeInto()` is the one primitive. Reordering within a list and moving to a different list are the same call:

```php
use ExileOfAranei\ListOrdering\Support\GroupKey;

// Insert a new task at the end of a board column.
$task->placeInto(GroupKey::of(['board_id' => 42, 'column' => 'todo']), $lastInColumn, null);

// Move it to a different column, between two existing tasks.
$task->placeInto(GroupKey::of(['board_id' => 42, 'column' => 'done']), $doneFirst, $doneSecond);
```

Anchors define bounds, not required adjacency — if other rows already sit between the two anchors you pass, the rank is still generated strictly between them. Any anchor you omit is resolved automatically (the next/previous element, or the end of the list if neither is given).

Thin wrappers read by intent, all delegating to `placeInto()`:

```php
$task->placeAtEnd(GroupKey::of(['board_id' => 42, 'column' => 'todo']));
$task->placeAtStart(GroupKey::of(['board_id' => 42, 'column' => 'todo']));
$task->placeAfter($anchorTask);  // target group is $anchorTask's group right now
$task->placeBefore($anchorTask);
```

### Placing a record at an exact rank

`placeAtRank()` is a second primitive, alongside `placeInto()`. Where `placeInto()` computes a rank between two neighbors, `placeAtRank()` occupies an exact rank value you already have — the case `placeInto()` deliberately can't cover. It only makes sense for a new record: the [saving guard](#what-the-guard-does--and-doesnt-protect) already rejects a rank/group mutation on an already-persisted one.

```php
$clone->placeAtRank(GroupKey::of(['board_id' => 42, 'column' => 'todo']), $original->rank);
```

`cloneRankFrom()` is the thin wrapper for the usual reason you'd reach for this: copying a record and wanting the copy to sort where the original does *within its own list*, even though the copy necessarily lives in a different group (a straight copy into the original's own group would collide with the original, which is still occupying that exact rank). It takes the rank from `$original` but the group from `$clone`'s own group columns — set them (e.g. via mass assignment) before calling it:

```php
$clone = new Task(['board_id' => 99, 'column' => 'todo']); // $clone's own, different group
$clone->cloneRankFrom($original);
```

A rank collision — two records ending up with the same rank in the same group — surfaces as `RankConflictException`, same as any other conflict the package's unique index catches.

### Reordering a whole list at once

`placeInto()` and its wrappers move one record relative to its neighbors — that's the only primitive the package has, and batch reordering (recomputing an entire list's order in one shot) is deliberately not a goal of the core API. But it's the first thing you'll need to write against a drag-and-drop UI that hands you a whole list's new order at once (SortableJS and similar), so the package ships it as a separate, opt-in helper: `BatchReorderer`.

```php
use ExileOfAranei\ListOrdering\Support\BatchReorderer;

// $newOrderOfKeys is every primary key currently in the group, in the order
// the UI now wants them — e.g. straight from a SortableJS `onEnd` payload.
BatchReorderer::apply(Task::class, GroupKey::of(['board_id' => 42, 'column' => 'todo']), $newOrderOfKeys);
```

It reads the group's current order itself, diffs it against `$newOrderOfKeys`, and applies only the moves actually needed — records already in the right relative order are left untouched, so a reorder that moves one item still does one `placeAfter()` call, not N. The whole operation runs in a single database transaction: either every move lands, or none do.

`$newOrderOfKeys` must be exactly the group's current keys, just reordered — adding, dropping, or duplicating a key throws `InvalidBatchOrderException` rather than silently reordering a partial list.

### Reading a list in order

```php
Task::ordered(GroupKey::of(['board_id' => 42, 'column' => 'todo']))->get();
```

Without a `GroupKey`, `ordered()` only sorts — it does not, and cannot, guarantee order across different groups in the result. Only omit it when the query is already narrowed to one group some other way (an already-scoped relationship, for example).

A third, optional argument sets sort direction (`'asc'`, the default, or `'desc'`):

```php
Task::ordered(GroupKey::of(['board_id' => 42, 'column' => 'todo']), direction: 'desc')->get();
```

### What the guard does — and doesn't — protect

There are two guards, covering the two ways a write can reach these columns:

A `saving` guard rejects direct writes to the group or rank columns on an **already-persisted** model, so a typo like `$task->column = 'done'; $task->save();` fails loudly instead of silently corrupting the list. This one only fires on updates to an existing row.

Separately, `rank` is kept out of mass assignment on **every** model, new or not — `Task::create(['rank' => 'z', ...])` silently drops the `rank` key rather than writing whatever was passed. This is what lets `rank` stay off your `$fillable` list without a workaround: `placeInto()` and `placeAtRank()` write it directly (mass-assignment guarding doesn't apply to `setAttribute()`), so there's never a legitimate reason for anything else to set it. Group columns aren't included in this one — they're routinely set via mass assignment before a `placeInto()` call (e.g. `new Task(['board_id' => 42, ...])`), and `placeInto()` overwrites them from the `GroupKey` it's given regardless of what was mass-assigned.

This second guard works by adding `rank` to the model's `$guarded` array — which Eloquent only ever consults for a key that isn't already in `$fillable`. If your own model declares `protected $fillable = [...]` and that list happens to include `'rank'` (by copy-paste or oversight), this guard is silently bypassed: `$fillable` wins before `$guarded` is checked at all. It protects the common `protected $guarded = [];` (or no `$fillable`/`$guarded` override at all) pattern; it is not a substitute for simply not listing `rank` in an explicit `$fillable`.

Neither guard sees `insert()`, `upsert()`, `withoutEvents()`, or any bulk write that bypasses Eloquent's `saving`/mass-assignment machinery. They're a developer-experience convenience, not the integrity guarantee — that's the database-level composite unique index (group columns + rank), which holds regardless of how a write reaches the table.

### Checking your index hasn't drifted

Since migrations are immutable and a model's `orderingGroupColumns()` can change over time, nothing stops them from silently diverging. Two ways to catch it, both backed by the same check, neither run automatically:

```bash
php artisan list-ordering:check-index "App\Models\Task"
php artisan list-ordering:check-index --all
```

```php
use ExileOfAranei\ListOrdering\Testing\AssertsOrderingIndex;

class TaskOrderingTest extends TestCase
{
    use AssertsOrderingIndex;

    public function test_ordering_index_matches(): void
    {
        $this->assertOrderingIndexMatches(Task::class);
    }
}
```

Neither check runs on its own — wire one of them into CI, or the drift goes uncaught until a concurrent write collides in production. The test-based check above runs wherever your existing test suite already runs; to run the artisan command as its own CI step instead:

```yaml
- name: Check ordering indexes haven't drifted
  run: php artisan list-ordering:check-index --all
```

## Testing

```bash
composer test
```

Tests run against fixture models from an intentionally unrelated domain via `orchestra/testbench` — the package's own test suite never references a consuming application. CI runs the full suite against SQLite, MySQL, MariaDB, and PostgreSQL, since collation bugs are invisible on SQLite alone.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [ExileofAranei](https://github.com/exileofaranei)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
