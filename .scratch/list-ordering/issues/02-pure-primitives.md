# 02 — Чистые примитивы: контракты, GroupKey, RankGenerator, базовые исключения

**What to build:** словарь типов, на котором строится весь пакет — без обращения к БД. Проверяется юнит-тестами напрямую, без фикстурных моделей.

**Blocked by:** 01

**Status:** ready-for-agent

- [x] `Contracts\Orderable` — полный публичный контракт: `orderingGroupColumns(): array` (`@return list<string>`, единственный абстрактный метод, который пишет модель), `currentGroupKey(): GroupKey`, `orderingRankColumn(): string`, `placeInto(GroupKey $group, ?Orderable $after, ?Orderable $before): void`
- [x] `Contracts\RankGenerator` — `between(?string $lower, ?string $upper): string`
- [x] `Generators\FractionalRankGenerator implements RankGenerator` — байт-сортируемый алфавит (`0-9A-Za-z`, порядок кодпоинтов совпадает с порядком сортировки)
- [x] `Support\GroupKey` — `of(array $columns): self`, `applyTo(Builder $query): Builder` (с `whereNull()` для `null`-значений — единственное место в пакете с этой веткой), `equals(self $other): bool` (порядок колонок не важен), `columnNames(): array`, `toArray(): array`
- [x] `Exceptions\ListOrderingException` (базовое) и `Exceptions\InvalidGroupKeyException` — остальные исключения размещаются в тикетах, где реально выбрасываются
- [x] Unit-тесты: `GroupKey::equals()` порядко-независим; `applyTo()` корректно строит `whereNull` на модельном query builder'е; `FractionalRankGenerator::between()` — результат строго между границами при байтовом сравнении, детерминирован, рост длины при многократной вставке в одну и ту же границу ограничен и замерен

**Находка сверх чеклиста (не в исходном тикете, обнаружена и исправлена в ходе реализации):** первая версия генератора выбирала цифру «на середину до MAX/0» для открытой стороны — при монотонных вставках подряд в один и тот же конец списка (append в конец / prepend в начало, оба крайне частые паттерны) это давало линейный рост длины ранга с большой константой: переполнение выбранной в тикете 03 колонки `VARCHAR(64)` наступало уже на ~385-й последовательной вставке. Заменено на минимальный шаг от известной границы (симметрично для append и prepend, с отдельной веткой для «обе границы пусты» — сценарий пустого списка, где нет сигнала о будущем паттерне вставок, тут сохранён срединный выбор). После фикса переполнение наступает на ~3875-й вставке — то же самое рос-ограничение подтверждено тестами `bounds rank growth under repeated appends/prepends`.

**Пост-ревью правка (Standards-ось):** `$upperBoundless` изначально совмещал два разных смысла («верхней границы не было с самого начала» и «верхняя граница исчерпана вынужденным переносом вглубь») под одним булевым флагом, различаемым по дополнительному `$upperWasGiven`. Заменено на `Generators\UpperBoundOrigin` (enum: `Absent`/`Present`/`Exhausted`) — три состояния названы явно, `match(true)` в финальной ветке больше не восстанавливает смысл флага по косвенному признаку.
