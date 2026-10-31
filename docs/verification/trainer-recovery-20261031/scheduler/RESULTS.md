# Spolehlivé plánování trenérské obnovy hesla

31. 10. 2026. Navazuje na první nasazení obnovy hesla v PR #258.
Aktuální stav vydání uvádí [CURRENT_STATE](../../../CURRENT_STATE.md).

## Důvod a omezení

Existující GitHub plánovač s deklarovaným intervalem pěti minut měl skutečné
běhy 31. 10. v 00:39 a 06:18 UTC. Takovou latenci nelze použít pro odkazy
s platností 60 minut. PR #260 odstraňuje GitHub schedule a ponechává workflow
pro chráněné ruční operace z hlavní větve.

V přihlášené administraci domény **kovopraha.cz** byl vytvořen nový
`CRON.1minuta`, každou minutu, ID 4354. Ostatní adresářové crony a oba
WordPress webcrony se neměnily. [Doklad nastavení](hosting-panel.json).
Workflow 41188081603 následně potvrdilo existující zapisovatelný adresář.
Jde o samostatný hostingový prostor KOVO, nikoli cron Velocoty.

Instalátor vytváří jen vlastní `evidence-trainer-password-reset.php` v již
existujícím adresáři. Odlišný existující soubor odmítne přepsat. Používá
atomické přejmenování a práva `0700`, která vyžaduje
[adresářový cron hostingu](https://www.cesky-hosting.cz/napoveda/skripty-na-serveru/cron/).
Již běžící worker drží soukromý zámek společný s ručním spuštěním.

Worker zpracuje nejvýše 20 trenérských resetů za běh. Nevybírá veřejnou
frontu ani tři staré klubové zprávy. Úspěšný cron zapisuje soukromý heartbeat
bez adres, tokenů nebo hesel; ruční spuštění jej nevytváří. Status označí
aktuální úspěch pouze při odpovídajícím zdroji a heartbeat do tří minut.

## Provozní kontrola

V chráněném workflow `trainer-access-production.yml` spustit `action=status`
z `main`. Ověřit `scheduler.installed_expected_source`,
`recent_successful_hosting_run`, čas `last_hosting_run.completed_at`
a počty trenérské fronty. Dva různé časy prokazují opakovaný automatický běh;
samotný instalovaný soubor ani ruční `process` to neprokazují.
Pomocný údaj `executable` pochází z PHP `is_executable()`: na tomto hostingu
vrací false i u skriptu, který hosting prokazatelně spouští. Není podmínkou
zdraví workeru a nenahrazuje heartbeat. Příčina tohoto OS/API rozdílu nebyla určena.
Souhrn `mail.scheduler_verified=false` patří obecnému diagnostickému kontraktu
všech front; nepřepisuje se důkazem výhradně trenérského workeru. Pro tento
worker rozhoduje samostatný blok `scheduler`. Ostatní fronty zůstávají neaktivované.

`action=process` je omezený ruční worker pro diagnostiku. `action=probe`
neopakovat: vlastník schválil jedinou zprávu a trvalý marker chrání proti
druhému odeslání. Při zastaralém nebo chybovém heartbeat prověřit plánovač,
oprávnění a chybu workeru; nepovolovat jiné fronty ani nemazat jejich obsah.

## Ověření zdroje

[CI](ci.json): všech 11 kontrol PASS, 1878 testů / 37987 assertions,
POSIX 3/45, MariaDB 10.3/11.4, objemové profily, PHPStan, styl a CodeQL.
Sloučené vydání má shodný strom jako přesný ověřený head PR #260.
Testy pokrývají idempotenci, ochranu cizího souboru, hostingový prostor,
spustitelná práva na Linuxu a odmítnutí starého/chybového/ručního heartbeat.

Pokus o read-only ověření z pracovní větve (41186865110) odmítla produkční
environment policy před jakýmkoli krokem. Ochrana nebyla obcházena; po
sloučení proběhlo ověření z povolené hlavní větve.

## Produkční výsledek

[Nasazení 41188114928](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41188114928)
SUCCESS, stejné kompletní PHPUnit 1878/37987. [Metadata a záloha](deployment.json).
Schéma current/pending [], ledger 143 beze změny, zachované VS,
config hash, soukromé přílohy i integrační přepínače.
[Porovnání](release-comparison.json), [před](pre-verification.json), [po](post-verification.json).
HTTP před i po 26/26: [před](pre-http.json), [po](post-http.json).

[Instalace](installation.json) úspěšná v 09:22:06 UTC.
[První skutečný běh](heartbeat-first.json) 09:23:03 UTC a
[druhý](heartbeat-second.json) 09:24:03 UTC: oba `hosting_cron`, `ok=true`,
bez chyby, prázdná trenérská fronta. Status je pouze načetl; od instalace
nebyl spuštěn žádný ruční `process`. [Počáteční status](status-before-first-run.json)
správně před prvním během vracel nulový heartbeat a nepotvrzený plánovač.

Záloha `evidence_2026-10-31_092007_2a97aa3c.sql.gz` obsahuje 337 tabulek,
včetně `trainer_password_reset_tokens`, a 2 triggery. Skutečná izolovaná
[obnova 41188508579](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41188508579)
ověřila SHA-256, import a všechny počty a názvy triggerů. [Doklad](backup-restore.json).

Technické vydání a trenérský plánovač jsou ověřené. Doručení do schránky,
konkrétní trenéři a jejich skupiny/soupisky zůstávají otevřenou lidskou přejímkou
uvedenou v [hlavním protokolu](../RESULTS.md). Žádné skutečné heslo ani grant
nebyly tímto ověřením změněny. Testovací zpráva se neopakovala.
