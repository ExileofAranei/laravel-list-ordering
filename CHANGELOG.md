# Changelog

All notable changes to `laravel-list-ordering` will be documented in this file.

## v0.2.0 - 2026-07-26

### Added

- `BatchReorderer::apply()` — an opt-in helper for full-list drag-and-drop reordering. Diffs the desired key order against the group's current order (via LIS) and applies only the moves actually needed, as a chain of `placeAfter()` calls wrapped in a single DB transaction. `placeInto()` itself still only ever moves one record; this sits on top of it, not inside it.
- `placeAtRank(GroupKey $group, string $rank)` — a second primitive alongside `placeInto()`: occupies an exact rank value instead of computing one between neighbors. Only meaningful for a new record.
- `cloneRankFrom(Model&Orderable $source)` — thin wrapper over `placeAtRank()` for copying a record into a different group at the same rank `$source` occupies (e.g. cloning a record's own list position). Reads `$source`'s rank inside the same transaction it writes in.
- `scopeOrdered()` takes an optional third argument, `direction` (`'asc'`, the default, or `'desc'`).

### Changed

- `rank` is now guarded against mass assignment on every model instance, not just on updates to an already-persisted one — `Model::create(['rank' => ...])` silently drops the `rank` key. This closes the gap where a bare `create()` could write an arbitrary rank with no check at all. Only effective for the `$guarded = []` (or unset) pattern — a model with `rank` explicitly listed in its own `$fillable` bypasses this silently; see the README's "What the guard does — and doesn't — protect" section.
- README: documents the `BatchReorderer` integration pattern, an explicit "wire `check-index` into CI" step, and the two-guard behavior above.

### Fixed

- (v0.1.1) `Positioner::findLast()` picking an unranked row as a group's "last" anchor on Postgres, due to `ORDER BY rank DESC` defaulting to `NULLS FIRST` there.
