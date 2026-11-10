# COACH-ROSTER-01 — místní ověření

Datum: 10. 11. 2026. Větev `codex/coach-groups-rosters-20261110` z `fb593ad4`. Bez změny produkce a bez práce se skutečnými osobními daty.

| Kontrola | Výsledek | Důkaz / omezení |
| --- | --- | --- |
| Syntetická soupiska Aligátoři, přímé přidělení a aktivní děti | PASS | `CoachRosterReadTest`: přímý grant ukáže jen svoji soupisku, rodičovská skupina neodkryje potomky. |
| Cizí ID, odebraný/vypršelý grant, odebraná pracovní pozice | PASS | Služba odmítne čtení; 403 překládá stránka. |
| Schválení a ukončení členství | PASS | Změna kanonického `club_roster_members` se ihned projeví bez kopie seznamu. |
| Ochrana citlivých údajů | PASS | Projekce vrací jen `id,jmeno,prijmeni`; syntetické RČ a rodičovský kontakt zůstávají mimo výsledek. Stránka nemá mutační formulář. |
| Kapacita, volná místa a aktuální rozvrh | PASS | Syntetický produkt 298: kapacita 12, obsazení 1, volno 11; starý rozvrh vyřazen. |
| Cílené PHPUnit, PHP syntaxe, styl, dokumentace | PASS | `php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration/CoachRosterReadTest.php tests/Integration/CoachScopeTest.php tests/Integration/AthleteRegistrationAdminTest.php tests/Integration/ClubProgramPaidIdentityCapacityTest.php tests/Unit/StaffWorkspaceRegistryTest.php`: 45 testů, 2546 assertions. `php -l` pro tři nové PHP soubory, `php tools/check-php-style.php`, `php tools/check-current-docs.php`, `git diff --check` PASS. |
| Statická analýza změněných souborů | PASS | `php vendor/bin/phpstan analyse includes/coach_roster_read.php moje_soupisky.php tests/Integration/CoachRosterReadTest.php --memory-limit=1G --no-progress`: bez chyb. |
| Syntetický prohlížeč při 390/1280 px | PASS | Playwright CLI nad vygenerovanou šablonou: šířka dokumentu 390/1280 při stejně širokém viewportu, 2 děti a karty čitelné. Náhled `output/playwright/coach_roster_mobile.png` je místní necommitovaný artefakt. Favicon 404 pouze v testovacím serveru. |
| Sdílené UI styly | PASS | Stránka volá `appUiAssets()` jednou v `<head>`; opakované volání z `hlavicka.php` je idempotentní. Syntetická šablona používá stejnou verzi Bootstrap CSS pro mobilní kontrolu. |
| Anonymní HTTP proti izolované větvi | NOT RUN | Místní MySQL na `127.0.0.1` odmítá spojení; `db.php` vrátil 503 ještě před autorizační kontrolou. Trasa je v inventáři anonymního CI sweepu. |
| Skutečný trenér, registrátor a Aligátoři po reálném schválení | NOT RUN | Vyžaduje testovací přihlášení, existující aktuální grant na cílovou soupisku a bezpečné provozní prostředí. Před nasazením. |
| Produkční UAT, docházka z nové stránky | NOT RUN | Produkční release neproběhl; docházka je další etapa. |

Aktuální stránka zobrazuje pouze aktivní členství. Čekající a ukončená jména zůstávají skryta; jejich stav mění počet aktivních dětí. Pokud má kroužek více současných nabídek s různou kapacitou, stránka kapacitu nevypíše, aby netvrdila zavádějící součet. Počet dětí a volná místa jsou odlišné údaje: druhý zohledňuje kanonické rezervace kapacity.
