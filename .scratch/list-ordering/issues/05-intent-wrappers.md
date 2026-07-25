# 05 — Обёртки намерения

**What to build:** читаемые по смыслу вызовы поверх единственного примитива — без новой логики размещения. Не входят в контракт `Orderable` (добавление в интерфейс позже — ломающее изменение; удаление из трейта — почти нет).

**Blocked by:** 04

**Status:** ready-for-agent

- [x] `HasOrdering::placeAtEnd(GroupKey $group): void` — делегат в `placeInto($group, null, null)`
- [x] `HasOrdering::placeAtStart(GroupKey $group): void` — делегат в `placeInto($group, null, $firstOfGroup)` либо эквивалент через примитив
- [x] `HasOrdering::placeAfter(Model&Orderable $anchor): void` — группа берётся из `$anchor->currentGroupKey()` **на момент вызова**, не из группы, которую мог предполагать вызывающий код; параметр типа `Model&Orderable`, не `self&Orderable` (`self`/`static`/`parent` — запрещены в intersection types, это ошибка компиляции)
- [x] `HasOrdering::placeBefore(Model&Orderable $anchor): void` — аналогично, группа из `$anchor->currentGroupKey()`
- [x] Тест (user story 11): якорь сменил группу между тем, как на него сослались, и вызовом `placeAfter()` — элемент уходит в текущую (актуальную) группу якоря, не в ту, что могла быть предположена ранее (аналогично покрыт `placeBefore()`)
- [x] Тест: все четыре обёртки — тонкие делегаты, ни одна не даёт результат, отличный от эквивалентного прямого вызова `placeInto()` (сравнивались на двух структурно идентичных независимых группах, чтобы не сталкивать вычисления на одном уникальном индексе)

**Находка:** `placeAtStart()` нуждается в поиске первого элемента группы, чтобы передать его как `$before` — Eloquent `Builder::first()` возвращает `?Model`, не `?Orderable`, та же PHPStan-проблема, что уже решалась в тикете 04 (`narrowAnchor()`/`narrowResult()`). Применён тот же приём explicit-throw narrowing, а не `@var`/каст.

**Пост-ревью правка (Standards-ось):** narrowing-проверка в `placeAtStart()` дублировала форму `narrowAnchor()` (по факту — та же проверка «Model и Orderable одновременно», просто со входом в другую сторону: `?Orderable→Model` против `?Model→Orderable`). Объединено в один приватный `narrowModel(?object): (Model&Orderable)|null`, которым теперь пользуются оба места; `narrowAnchor()` стал тонким алиасом поверх него.
