# P11 / R06 — optimalizace docházky 7. 11. 2026

Zadání: navázat autonomně podle aktuálního plánu; bez produkčních změn,
se zachováním chování a původního WIP. Base main `89edfdbe` / #353.
Větev `codex/attendance-read-performance-20261107`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-performance-20261107`.
Původní `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.

## Plán a stav

1. Nahradit jednotlivé kontroly existence účastníků dávkovým čtením mimo
   transakci. Autorizační kontrola vždy první a čerstvá, v transakci zachovat
   dosavadní jednotlivé kontroly a pořadí SQL. Writer a POST nepřepisovat.
2. Regrese: prázdné/duplicitní/neplatné a chybějící osoby, více dávek,
   odvolaná role/neaktivní účet, čerstvé změny mezi čteními, transakční parita.
3. Porovnat původní a nové skutečné GET tělo/HTML na stejných 10/50/200
   účastnících × 1/12/52 revizích, 5 zahřátí a 30 vzorků, samostatně SQL/řádky.
4. Párové CI MariaDB 10.3/11.4, zachovat stávající R05/R13 profily/paritu,
   uložit trvalé výsledky, aktualizovat registr a session handoff.

Implementace hotová: dávky po 500 ID mimo transakci; transakční/prázdná cesta
a zprávy zachované. Místně **29 testů / 206 assertions PASS**, cílený PHPStan
a baseline budget PASS (497 položek / 2146 nálezů, beze změny), styl PASS.
[JUnit](targeted-tests.xml). Párové měření a MariaDB parita dosud NOT RUN. Diagnóza: `trainingClosureAssertTraining`
pro trenéra načítá účastníky a `coachTrainingAssertPeople` provádí jeden SELECT
na každou osobu. Soukromé profily i další doménové guardy se nemění.
Plné HTTP/bootstrap/navigace a další role/filtry zůstávají samostatné úkoly.
Produkční stav určuje [CURRENT_STATE](../../CURRENT_STATE.md), který tato relace nemění.
