# W8.2 — filtry sportovních seznamů a kopírovací náhled

30. 10. 2026. Výchozí `8c71a2d281956db1fb941aa42a3e045f1f5bca89`, před
vydáním aktualizováno na W8.1 `df09eab94ccc6545e57b71a41bc24aa0011850c0`.
[Plán](../../REFACTORING_W82_2026-10-30.md). Žádné migrace či živé mutace.

## Změna a rozpočty

Plány i skutečné tréninky zachovávají dávky 500 ID, historické cílení,
obousměrný vztah rodiče/potomka, pořadí a duplicitní vstupní řádky.
Shodný filtr je nyní v jedné čtecí projekci. Hrany čte nejvýše jednou;
datum normalizuje jednou pro každou odlišnou hodnotu, čímž nevytváří
zbytečné kopie pro každý trénink. Instance/cache se nesdílí mezi voláními.

Náhled počtu kopírovaných plánů je před renderem: jeden COUNT bez týmu,
nebo jeden výběr ID následovaný historickým filtrem. Zachována databázová
kolace `stav`, precedence „jen moje“ před trenérem a filtr skupiny.
POST znovu načítá data; jeho transakce, kontrola dostupnosti a zápisy se
nemění. Nutné formátování celé stránky/bridge ověřeno zvlášť níže.

[RED](red-budget.txt): už 1 týden potřeboval 18 načtení hran místo ≤1.
Kontrakty pokrývají 0/1/12/52 týdnů, chybějící cíle/tabulku hran, cykly,
duplicitní vazby/řádky, nesouvislé indexy vstupu a čerstvé změny platnosti.

## Měření

[Migrovaný profil](filter-profile.json), MariaDB 10.4.32, aktivní FK,
20 týmů, všechny migrace, samostatné vlastnictví plán/skutečnost v bridge.
Přesná shoda celých výsledků/hashů před/po. Čas/paměť bez instrumentace,
jeden warmup a pět střídavých vzorků. Žádné produkční výkonové měření.

| Roční scénář | SQL před → po | Hrany SQL | Medián ms | Špička paměti B |
| --- | --- | --- | --- | --- |
| Plány | 1875 → 5 | 936 → 1 | 1507,44 → 20,39 | 647040 → 629200 |
| Skutečné tréninky | 1875 → 5 | 936 → 1 | 1533,39 → 19,94 | 647048 → 629200 |
| Počet bez týmu | 3 → 1 | 0 → 0 | 4,83 → 1,96 | 44936 → 43800 |
| Počet s týmem | 1048 → 5 | 521 → 1 | 917,98 → 19,53 | 820624 → 776120 |

Rozpočty: hrany ≤1, celkové SQL neroste, paměť nejvýše původní +64 KiB.
Původní první návrh kopíroval normalizované datum pro každý řádek a měl
vyšší paměť; deduplikace dat tento náklad odstranila před vydáním.
Časové údaje nejsou tvrdý CI práh; srovnáváme výsledky, SQL, řádky a paměť.

## Formátování a hranice důkazů

[Stránka AST/HTML](page-ast.txt) a [bridge AST](bridge-ast.txt) PASS proti
snapshotům po funkční změně, před formátováním:
[stránka](page-before-format.txt), [bridge](bridge-before-format.txt).
[Skript](formatting-ast-check.txt) porovnává AST se složením literálních
konkatenací/echo, takže doslovné HTML i SQL zůstávají stejné. Samotná
funkční změna je pokryta oddělenými historickými srovnáními a plnou regresí.

[Lokální cílené testy MariaDB](native-contracts.txt): 3 testy / 354 assertions. CI navíc provádí
celý profil a kontrakty na MariaDB 10.3/11.4. Výjimky PHPStan pro smazané
PDO výrazy v plánovači se snížily o čtyři; žádná nová výjimka.
[Plná sada](phpunit-windows.txt): 1834 testů / 36614 assertions, 3 POSIX
skips. [PHPStan](phpstan.txt), styl, YAML a dokumentace PASS.
Finální CI/release důkazy se doplní po ověření přesného commitu.

Reprodukce: `php tests/Support/TrainingRosterFilterProfile.php`; pro lokální
vlastní DB `EVIDENCE_MARIADB_SMOKE_PORT=33426`, v CI 3306. Kontrakty:
`EVIDENCE_ROSTER_READ_MARIADB=1 php vendor/bin/phpunit tests/Integration/TrainingRosterFilterReadTest.php tests/Integration/PlannerCopyCountTest.php`.
Všechny vlastní náhodné databáze jsou po skončení odstraněny.

Kalendáře, formuláře/uzavření, rezervace a W9–W15 mají navazující posouzení.
Žádné lidské UAT, skutečné importy, převody ani zapnutí integrací.
