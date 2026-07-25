# 01 — Гигиена скелета + CI-гейты независимости

**What to build:** унаследованный от Spatie skeleton код и конфиги приводятся в соответствие с ТЗ пакета, и в CI сразу появляются механические проверки независимости — чтобы протечка в код-базу консьюмера ловилась в момент появления, а не в конце работы.

**Blocked by:** None — can start immediately

**Status:** ready-for-agent

- [x] `composer.json`: `php` constraint — `^8.3` (не `^8.4`; нижнюю границу и так задаёт `orchestra/testbench` под Laravel 13)
- [x] `LICENSE.md`: копирайт с указанием года
- [x] Удалены: `src/Facades/ListOrdering.php`, `src/Commands/ListOrderingCommand.php`, `src/ListOrdering.php`, `config/list-ordering.php`, `database/migrations/create_list_ordering_table.php.stub`, ссылки на них в `ListOrderingServiceProvider` (`hasConfigFile()`, `hasCommand()`, `hasViews()`, `hasMigration()`, публикация конфига, Facade-алиас в `composer.json`)
- [x] CI workflow: шаг `! grep -rn 'App\\' src/ tests/`
- [x] CI workflow, ярус 1 (жёсткий отказ, без исключений): `! grep -rniE 'block|canvas|slot|tgs|accordion|placement|categor|article|news|nested set' src/ tests/`
- [x] CI workflow, ярус 2 (ловится, затем фильтруются законные употребления `parent::`, LSP/ковариантность в PHPDoc): `parent|child|tree|node|hierarch|ancestor|descendant`, список исключений короткий и виден прямо в шаге workflow
- [x] Область проверки — только `src/` и `tests/`; `README`/документация и `.github/` вне области (см. пересмотренный §12: словарь трактуется как отсутствие лексики, а не как фиксированная команда — паттерн уточняется без согласования)
- [x] Все шаги падают на затравочном нарушении (проверено вручную: `parent::setUp()` из testbench-`TestCase.php` не ловится, `// parent element ... node` ловится) и зелёные на текущем `src/`/`tests/`
