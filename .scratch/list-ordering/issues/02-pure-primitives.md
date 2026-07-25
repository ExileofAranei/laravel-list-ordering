# 02 — Чистые примитивы: контракты, GroupKey, RankGenerator, базовые исключения

**What to build:** словарь типов, на котором строится весь пакет — без обращения к БД. Проверяется юнит-тестами напрямую, без фикстурных моделей.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] `Contracts\Orderable` — полный публичный контракт: `orderingGroupColumns(): array` (`@return list<string>`, единственный абстрактный метод, который пишет модель), `currentGroupKey(): GroupKey`, `orderingRankColumn(): string`, `placeInto(GroupKey $group, ?Orderable $after, ?Orderable $before): void`
- [ ] `Contracts\RankGenerator` — `between(?string $lower, ?string $upper): string`
- [ ] `Generators\FractionalRankGenerator implements RankGenerator` — байт-сортируемый алфавит (`0-9A-Za-z`, порядок кодпоинтов совпадает с порядком сортировки)
- [ ] `Support\GroupKey` — `of(array $columns): self`, `applyTo(Builder $query): Builder` (с `whereNull()` для `null`-значений — единственное место в пакете с этой веткой), `equals(self $other): bool` (порядок колонок не важен), `columnNames(): array`, `toArray(): array`
- [ ] `Exceptions\ListOrderingException` (базовое) и `Exceptions\InvalidGroupKeyException` — остальные исключения размещаются в тикетах, где реально выбрасываются
- [ ] Unit-тесты: `GroupKey::equals()` порядко-независим; `applyTo()` корректно строит `whereNull` на модельном query builder'е; `FractionalRankGenerator::between()` — результат строго между границами при байтовом сравнении, детерминирован, рост длины при многократной вставке в одну и ту же границу ограничен и замерен
