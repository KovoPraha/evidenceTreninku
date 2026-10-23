# Přejímka finančního základu R13–R17

**Lokální implementace a technické ověření PASS, 22. 10. 2026. Bez nasazení
a produkčních změn.** Tři subagenti implementovali peněžní evidenci,
doklady a vratky; hlavní agent doplnil export, společné hranice, obnovu a
ověření. Následoval nezávislý review exportu a opravy nalezených návazností.

| Kontrola | Výsledek a důkaz |
|---|---|
| Kompletní PHPUnit | **1 349 testů / 28 532 kontrol**, 0 chyb, selhání a přeskočených; [souhrn](phpunit-summary.json). Nové účetní integrační sady mají 55 testů / 344 kontrol. |
| Peněžní evidence přes HTTP | **17 scénářů PASS**, role, CSRF, skutečné pohyby, zdrojové aliasy, uzavření; [protokol](ledger-http.json). |
| Doklady přes HTTP | **18 scénářů PASS**, koncept, vydání, soukromé PDF, oprava a odmítnutí nepovolených akcí; [protokol](r15-document-http.json). První běh navíc založil dvě syntetické řady. |
| Vratky přes HTTP | **18 scénářů PASS**, rozhodnutí, dvě částečné výplaty, opakování, limit a zrušení nevyplaceného rozhodnutí; [protokol](r17-http-result.json). |
| Export přes HTTP | **27 scénářů PASS**, 4 PDF v archivu, shoda manifestu, nepovolené role/přímé URL, opakování a náhradní revize; [protokol](r13-r17-export-http.json). |
| Zdrojové aliasy a alokace v souběhu | **9 kontrol PASS**, 4 skutečně pozorovaná čekání InnoDB, jediný pohyb a ochrana nadměrného přídělu; [protokol](ledger-race.json). |
| Čísla/PDF a opravy v souběhu | **15 kontrol PASS**, dva procesy, stejné číslo a stejný otisk PDF, nepřekročení opravitelné částky a opakování po chybě PDF; [protokol](r15-document-race.json). |
| Rozhodnutí a výplaty v souběhu | **18 kontrol PASS**, dvě skutečné souběžné transakce čekají na zámek, nepřekročí dostupný příjem; [protokol](r17-race-result.json). |
| Konzistentní export při souběžné vratce | **15 kontrol PASS**, dvě skutečná DB spojení, výchozí READ COMMITTED, export si vynutí REPEATABLE READ a nezahrne polovinu pozdější transakce; negativní kontrola prokáže původní problém; [protokol](accounting-export-snapshot-mariadb.json). |
| PDF vzhled | **3 strany, všech 18 dlouhých českých položek**, diakritika, součty a stránkování ověřeny vykreslením i extrakcí textu; [metadata](r15-pdf-qa.json). |
| Obnova databáze a souborů | **272 tabulek, 2 triggery, 18 nových účetních tabulek, 51 skutečně obnovených soukromých souborů, 34 ověřených odkazů**, shodné otisky; [protokol](accounting-backup-restore.json). |
| Migrace, syntaxe a diff | **121 migrací aktuálních**, 34 změněných/nových PHP souborů bez syntaktické chyby, `git diff --check` čistý. |

HTTP část dohromady: **80 scénářů**. Samostatné scénáře nejsou přičteny k
počtu PHPUnit. Každý důkaz uvádí svůj rozsah; žádný výsledek není produkční
UAT nebo schválením účetní. Dompdf je uzamčen v Composer locku na 3.1.6;
Composer audit při doplnění závislosti nehlásil advisories.

Prostředí: PHP 8.2.12, PHPUnit 11.5.56, syntetická DB `evidence_b1` na
`127.0.0.1:33426`, web `127.0.0.1:18086`. Souběhové scénáře používají vlastní
nově vytvořená schémata. Obnova použila nové `evidence_b3_restore_290f7141`
a nový adresář souborů mimo webroot. Původní DB ani soukromé soubory se
při obnově nepřepisují. Veškeré testovací osoby/podklady jsou syntetické.

## Co společné ověření opravilo

- Napojení již existujících rodinných/klubových alokací: odvozená zaplacená
  položka není druhý příjem a další zdrojová alokace nezůstává skrytá.
- Vazbu původní a nové refundace: plné splacení po částech dokončí společný
  platební přechod; jeho marker se nepřevezme jako další výdaj. Historická
  vratka musí prokazatelně snížit dostupné peníze příslušného příjmu.
- Opravu nevydaného konceptu a zrušení konceptu bez čísla, při zachování
  původních vydaných PDF a již rezervovaných čísel.
- Samostatné účetní členění skutečných peněz, nezaměněné za rozdělení mezi
  obchodní případy; záporné peněžní částky zůstávají v XLSX čísly.
- Počátek účtu otevřeného až uvnitř období se nevydává za známý stav prvního
  dne měsíce. Zrušení vratky a odsouhlasení účtu jsou v seznamu změn revize.
- Export nepřebírá různé části souběžné transakce a před potvrzením archivu
  ověří každý soubor proti manifestu. Po závěrečném doplnění kontroly ZIP
  znovu prošlo všech 7 exportních testů / 84 kontrol.
- Nové webové stránky používají společné bezpečnostní hlavičky/UI; statická
  PDF šablona má přesně vymezenou výjimku ze skeneru webových stránek.

## Opakování

V izolované pracovní kopii:

- `php vendor/phpunit/phpunit/phpunit`
- `php tests/Support/AccountingLedgerHttpAcceptance.php`
- `php tests/Support/AccountingDocumentsHttpAcceptance.php`
- `php tests/Support/AccountingAdjustmentsHttpAcceptance.php`
- `php tests/Support/AccountingExportHttpAcceptance.php`
- `php tests/Support/AccountingLedgerMariaDbRace.php`
- `php tests/Support/AccountingDocumentsMariaDbRace.php`
- `php tests/Support/AccountingAdjustmentsMariaDbRace.php`
- `php tests/Support/AccountingExportSnapshotMariaDbTest.php`
- Lokální `bin/db-backup.php` do sandbox `backups-r13-r17`, pak
  `php tests/Support/AccountingBackupRestore.php`.

Podpůrné scénáře před zápisem odmítnou jinou než určenou syntetickou
instanci. Žádný plánovač, ostrá brána, bankovní import ani rozesílání nebyly
aktivovány. Původní pracovní kopie `C:\xampp\htdocs\evidencePavel` a její
rozpracované změny zůstaly stranou.

Export je **první pracovní rozsah R13–R17**, vždy označený neúplný. Celý
měsíc včetně skladů, výdajů, záloh, odměn a konečného historického salda
dokončí R36. Reálné právní/účetní údaje, metodika, počátky a převzetí účetní
nebyly touto syntetickou přejímkou schváleny. Podrobné chování a podmínky:
[předání implementace](../../FINANCE_R13_R17_IMPLEMENTATION.md).
