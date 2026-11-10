# COACH-ROSTER-01 — ověření a společné vydání

Datum: 10. 11. 2026. Syntetická část začala ve větvi `codex/coach-groups-rosters-20261110` z `fb593ad4`, bez skutečných osobních dat; produkční vydání je doloženo níže.

| Kontrola | Výsledek | Důkaz / omezení |
| --- | --- | --- |
| Syntetická soupiska Aligátoři, přímé přidělení a aktivní děti | PASS | `CoachRosterReadTest`: přímý grant ukáže jen svoji soupisku, rodičovská skupina neodkryje potomky. |
| Cizí ID, odebraný/vypršelý grant, odebraná pracovní pozice | PASS | Služba odmítne čtení; 403 překládá stránka. |
| Schválení a ukončení členství | PASS | Změna kanonického `club_roster_members` se ihned projeví bez kopie seznamu. |
| Ochrana citlivých údajů | PASS | Projekce vrací jen `id,jmeno,prijmeni`; syntetické RČ a rodičovský kontakt zůstávají mimo výsledek. Stránka nemá mutační formulář. |
| Kapacita, volná místa a aktuální rozvrh | PASS | Syntetický produkt 298: kapacita 12, obsazení 1, volno 11; starý rozvrh vyřazen. |
| Cílené PHPUnit, PHP syntaxe, styl, dokumentace | PASS | `php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration/CoachRosterReadTest.php tests/Integration/CoachScopeTest.php tests/Integration/AthleteRegistrationAdminTest.php tests/Integration/ClubProgramPaidIdentityCapacityTest.php tests/Unit/StaffWorkspaceRegistryTest.php`: 45 testů, 2546 assertions. `php -l` pro tři nové PHP soubory, `php tools/check-php-style.php`, `php tools/check-current-docs.php`, `git diff --check` PASS. |
| Statická analýza změněných souborů | PASS | `php vendor/bin/phpstan analyse includes/coach_roster_read.php moje_soupisky.php tests/Integration/CoachRosterReadTest.php --memory-limit=1G --no-progress`: bez chyb. |
| GitHub CI na `69fe3a56` | PASS | [Draft PR #436](https://github.com/KovoPraha/evidenceTreninku/pull/436): všech 11 kontrol úspěšných včetně plného PHPUnit, PHPStan/style, dokumentace, CodeQL a MariaDB smoke/profilového čtení 10.3 a 11.4. Následný dokumentační commit obsahuje pouze zápis tohoto výsledku. |
| Syntetický prohlížeč při 390/1280 px | PASS | Playwright CLI nad vygenerovanou šablonou: šířka dokumentu 390/1280 při stejně širokém viewportu, 2 děti a karty čitelné. Náhled `output/playwright/coach_roster_mobile.png` je místní necommitovaný artefakt. Favicon 404 pouze v testovacím serveru. |
| Sdílené UI styly | PASS | Stránka volá `appUiAssets()` jednou v `<head>`; opakované volání z `hlavicka.php` je idempotentní. Syntetická šablona používá stejnou verzi Bootstrap CSS pro mobilní kontrolu. |
| Anonymní HTTP proti izolované větvi | NOT RUN | Místní MySQL na `127.0.0.1` odmítá spojení; `db.php` vrátil 503 ještě před autorizační kontrolou. Trasa je v inventáři anonymního CI sweepu. |
| Skutečný trenér, registrátor a Aligátoři po reálném schválení | NOT RUN | Vyžaduje testovací přihlášení a existující aktuální grant na cílovou soupisku. Po výslovné žádosti vlastníka o nasazení zůstává provozním UAT, nikoli tvrzeným výsledkem syntetického testu. |
| Přihlášené produkční UAT, docházka z nové stránky | NOT RUN | Release proběhl; skutečný trenér/registrář a Aligátoři vyžadují přihlášené čtení. Docházka je další etapa. |

Aktuální stránka zobrazuje pouze aktivní členství. Čekající a ukončená jména zůstávají skryta; jejich stav mění počet aktivních dětí. Pokud má kroužek více současných nabídek s různou kapacitou, stránka kapacitu nevypíše, aby netvrdila zavádějící součet. Počet dětí a volná místa jsou odlišné údaje: druhý zohledňuje kanonické rezervace kapacity.

## Společné produkční vydání

[Opravy #438](https://github.com/KovoPraha/evidenceTreninku/pull/438) byly sloučeny jako `f39f31578f01b1a1060552eb8dd4f35b4d52a6e3`, [trenérská agenda #436](https://github.com/KovoPraha/evidenceTreninku/pull/436) jako `4e3df207319fbce8a2f65e01213c7e4ba11cbf3d`. Oba PR měly 11 úspěšných CI kontrol včetně PHPUnit, PHPStan/style, dokumentace, CodeQL a MariaDB 10.3/11.4. [Nasazení 42852365591](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42852365591) na přesném SHA `4e3df207319fbce8a2f65e01213c7e4ba11cbf3d` skončilo SUCCESS; `uat_schvaleno=false`, `importovat_krouzky=false` a krok importu byl přeskočen. Produkční workflow: 2442 testů/56079 assertions, ověřená záloha, aktuální migrace, zachované otisky variabilních symbolů, aktivace a serverový HTTP smoke 200 PASS. Soukromý klíč nebyl přes HTTP dostupný (403).

[PR #439](https://github.com/KovoPraha/evidenceTreninku/pull/439) se sloučilo do `main` až po tomto workflow a není součástí nasazeného commitu `4e3df207`.

Po aktivaci vnější anonymní čtení: veřejný produkt 298 HTTP 200, `moje_soupisky.php` a `eshop_identity_admin.php` bez přihlášení HTTP 302. Tyto sondy nepotvrzují obsah soupisek ani oprávnění přihlášených rolí. Pokus o přihlášené browser UAT skončil blokací Computer Use kvůli nepodporovanému vynucování politiky URL ve Windows prohlížeči; skutečný trenér, registrář, audit maskovaného náhledu a chronologie živých objednávek zůstávají NOT RUN. Při provozní přejímce nepoužívat nové skutečné objednávky či platby jen kvůli tomuto ověření; použít existující testovací data a příslušné účty.
