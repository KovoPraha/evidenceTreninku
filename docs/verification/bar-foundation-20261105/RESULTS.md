# Ověření základu baru v KIS

Datum 5. 11. 2026, větev `codex/bar-kis-integration-20261105`, výchozí `main` `0ced6664`. B1 je implementovaná a lokálně ověřená; produkční nasazení není povolené. [Rozsah a další etapy](../../BAR_INTEGRATION_PLAN_2026-11-05.md).

## Implementace

- Pracovní pozice Obsluha baru a Administrátor baru, samostatné vlastnictví tras a výslovná delegace nabídky administrátorovi. Žádná nová pozice není přidělena migrací. Návrat z odmítnutého přístupu používá kanonickou cestu i v podadresáři.
- Oprávnění vlastní spotřeby má samostatný grant. Administrátor baru ani superadmin ho nemá automaticky. Přidělení a odebrání provádí aktivní správce systému, s důvodem, aktuální DB autorizací a auditem v jedné transakci. Opakování nevytváří další změnu ani audit.
- Aktor ověřuje aktivitu účtu, revokační verzi, platnou aktivní pozici v DB a případně zvláštní grant. Zápisová služba používá zamykající aktuální čtení, nikoli uložené autorizační pole z dřívějšího požadavku.
- Pět nových vlastněných tabulek: kategorie, produkty, receptury, oprávnění a audit. Peníze jsou v celých haléřích; katalogové produkty mají nulový počátek zásob. Staré tabulky zůstávají nedotčené. Zálohovací kontrakt obsahuje všechny nové tabulky.
- Tři chráněné webové vstupy: nabídka stánku, čtecí administrátorský katalog a správa práva vlastní spotřeby. Obsluha nedostává nákupní ceny, předsklad ani archivované produkty. Receptura se nezobrazuje jako vlastní fyzická zásoba.
- Čistá příprava katalogu bez DB a souborových zápisů: přesné částky, původní vazby kategorií/receptur, katalogové příznaky a seznam fotografií. Kontrolní otisk nezávisí na pořadí zdrojových řádků ani testovacích zásobách. Chybné vazby, duplicitní ID, nevhodné cesty a neplatné ceny se odmítají.

## Výsledky

| Kontrola | Výsledek |
| --- | --- |
| Composer konfigurace | `composer validate --strict` PASS |
| Úplná sada | `composer test`: 2044 testů, 38883 assertions, nula chyb a selhání, 3 přeskočené testy |
| Unixové výjimky | Přeskočené pouze dva symlink testy `PrivateStorageProbeTest` a jeden `ProductionPrivateStorageProvisionTest`; důvodem je Windows, Linux je musí provést |
| Statická analýza | `composer analyse`: 820 zdrojových souborů, bez nových potlačení a bez chyb |
| Styl a syntaxe | `composer style:check` a PHP lint všech 22 změněných PHP souborů PASS |
| Anonymní HTTP | `AnonymousSweep.php`: 34 veřejných výjimek, 2 CLI, 241 chráněných tras, 0 chyb; včetně všech tří nových barových stránek |
| MariaDB oprávnění | `BarFoundationMariaDbSmoke.php`, MariaDB 10.4.32 na vlastním loopback portu 3327: opakovaná migrace, audit a idempotence grantu PASS; dvě spojení prokazují zamykající čtení aktuálního odebraného grantu i pracovní pozice po vytvoření staršího snímku |
| Záloha a obnova | `DatabaseBackupMariaDbSmoke.php`: syntetická databáze, všech 304 přítomných vlastněných tabulek obnoveno a ověřeno; včetně nových tabulek baru |
| Rozdíl | `git diff --check` PASS; existující migrace neměněné |

První úplný běh ukončil standardní 300sekundový limit Composeru. Dokončený ověřený běh použil `COMPOSER_PROCESS_TIMEOUT=0` a trval přibližně čtyři minuty. Při vývoji byly opraveny staré kontrakty počtu pozic, seznamu migrací a rozsahu záloh i anonymní login redirect; tabulka uvádí následné dokončené ověření.

## Hranice dokončení

Linux CI, MariaDB 10.3/11.4 a skutečný přihlášený browser této etapy zatím nejsou doložené. Nový MariaDB smoke je přidaný do obou CI databázových variant. Stará produkční data, katalog, fotografie a účet lucka se nepřenášely. Pokladna, skutečné skladové operace, příjem faktur, odběr vlastní spotřeby, veřejné nabídky a účetní propojení pokračují v dalších etapách. B1 neznamená dokončené začlenění celého baru.

Izolovaná lokální MariaDB byla po testech zastavená. Nebyl proveden produkční deploy, změna konfigurace, účtů, hesel, zásob, role ani odeslání zpráv.
