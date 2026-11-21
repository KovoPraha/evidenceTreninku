# TRAINING-PLAN-ACL-01 — místní ověření, 21. 11. 2026

Prostředí: Windows, PHP 8.2.12, PHPUnit 11.5.56, izolovaná SQLite paměťová fixture. Bez produkčních zápisů či osobních dat. Větev `codex/training-plan-admin-collision-20261121` z `11505aed`.

| Kontrola | Výsledek | Rozsah a meze |
| --- | --- | --- |
| `php vendor/bin/phpunit tests/Integration/TrainingClosureTest.php tests/Integration/CoachTrainingAccessTest.php tests/Unit/TrainingCalendarWiringTest.php` | PASS: 21 testů, 106 tvrzení | Role, přesný čas kolize, nemožnost bypassu běžným trenérem, blokace soupisky/sportoviště, dvojí potvrzení a změna návrhu. Nenahrazuje HTTP/UAT formuláře. |
| `php -l` změněných PHP souborů a `git diff --check` | PASS | Syntaktická a whitespace kontrola. |
| `composer validate --strict` | PASS | Konfigurace balíčku je platná. |
| `php tools/check-current-docs.php` v hlavním workspace | FAIL: 10 lokálních souborů; 80 řádků CURRENT a HANDOFF | `docs/pravidla-shody-osob.md` a `docs/PROMPT-A` až `PROMPT-H` existují pouze místně, jsou v `.git/info/exclude` a nejsou v `git ls-files`. Kontrola na nich v místním workspace hlásí neevidované plány. První CI běh odhalil, že jejich dočasné zařazení do katalogu bylo chybné; tyto katalogové záznamy byly odstraněny. Nový čistý CI běh čeká. |
| `composer test` | NOT COMPLETE | Místní Windows běh dosáhl 244/2531 s jednou chybou a Composer jej po 300 s ukončil; úplná sada bez diagnostiky dané chyby není PASS. Cílené testy a samostatné `FinanceB1RouterTest` (5/56) prošly. Rozhodující je čistý Linux CI. |
| `composer style:check` | NOT COMPLETE | Místní neversionované `output/` obsahuje množství cizích PHP souborů, které kontrola přibírá mezi untracked; Windows `proc_open` selhal na příliš dlouhém příkazu (206). Na čistém CI checkoutu se kontrola spustí bez těchto souborů. |
| První Linux CI pro `3ebce1e7` | FAIL v dokumentaci a PHPStan; ostatní běhy ještě probíhaly | Dokumentace chybně katalogizovala 10 pouze místních ignorovaných souborů; katalog opraven v `bcd4d6d4`. PHPStan zjistil 49 místo 40 potlačených hlášení o `$pdo` v upraveném formuláři; doplněna přesná anotace typu a stará výjimka z baseline odstraněna. Nový běh čeká na CI. |
| PHP quality pro merge `76703224` | PHPStan PASS, styl FAIL | Čisté CI našlo pouze PSR-12 rozdíly ve dvou změněných PHP souborech. Lokálně nainstalovány závislosti přes `composer install` podle lockfile, spuštěn `php-cs-fixer fix` na třech dotčených souborech a následný `--dry-run --diff` vrátil 0 změn. Po opravě cílené testy znovu PASS: 21/106. Nový CI běh otevřený. |
| Linux CI pro kód `e693aaca` | PASS: 21/21 kontrol | [PR #508](https://github.com/KovoPraha/evidenceTreninku/pull/508): docs, PHPStan/styl, CodeQL, PHPUnit PHP 8.2 (2531 testů / 60328 tvrzení), Unix hranice soukromého úložiště (3/45), MariaDB 10.3/11.4 a dotčené HTTP/paritní scénáře. Stav platí pro toto SHA; následná dokumentační aktualizace nemění kód. |
| Přihlášený formulář, MariaDB, Linux CI, merge, nasazení, produkční přejímka | NOT RUN | Další krok po PR: Linux CI a kontrolovaný test správce i běžného trenéra v prohlížeči. |

Jeden termín s více soupiskami používá existující `training_roster_links`; plánovač nyní nevytváří další termíny kvůli souběhu trenéra. Sériové přiřazení `serie_id` aktualizuje jen ID právě vytvořených termínů.

Po otevření PR se `main` posunul na `5acd225f` (SEC01 a dokumentační podklady DOC01/P15/Q2). Větev jej převzala sloučením; v konfliktních souborech jsou zachovány obě agendy. Po sloučení je třeba znovu ověřit čistý CI běh a odkazy.

Po zeleném 21/21 CI se `main` posunul na `741c0625` (AUTH-MAIL-01). Větev jej převzala bez změny plánovací logiky; konfliktní přehledy a logy zachovávají obě agendy. Nový čistý CI běh společného stromu otevřený.

Společný `3e8f90e4` po převzetí `741c0625` prošel 21/21 CI. Během běhu byl v `main` sloučen `eb78c45b` s produkčním výsledkem AUTH-MAIL-01; poslední dokumentační konflikt byl spojen se zachováním novějšího provozního stavu. Plánovací kód beze změny, další CI společného stromu otevřené.
