# 05 — Обёртки намерения

**What to build:** читаемые по смыслу вызовы поверх единственного примитива — без новой логики размещения. Не входят в контракт `Orderable` (добавление в интерфейс позже — ломающее изменение; удаление из трейта — почти нет).

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] `HasOrdering::placeAtEnd(GroupKey $group): void` — делегат в `placeInto($group, null, null)`
- [ ] `HasOrdering::placeAtStart(GroupKey $group): void` — делегат в `placeInto($group, null, $firstOfGroup)` либо эквивалент через примитив
- [ ] `HasOrdering::placeAfter(Model&Orderable $anchor): void` — группа берётся из `$anchor->currentGroupKey()` **на момент вызова**, не из группы, которую мог предполагать вызывающий код; параметр типа `Model&Orderable`, не `self&Orderable` (`self`/`static`/`parent` — запрещены в intersection types, это ошибка компиляции)
- [ ] `HasOrdering::placeBefore(Model&Orderable $anchor): void` — аналогично, группа из `$anchor->currentGroupKey()`
- [ ] Тест (user story 11): якорь сменил группу между тем, как на него сослались, и вызовом `placeAfter()` — элемент уходит в текущую (актуальную) группу якоря, не в ту, что могла быть предположена ранее
- [ ] Тест: все четыре обёртки — тонкие делегаты, ни одна не даёт результат, отличный от эквивалентного прямого вызова `placeInto()`
