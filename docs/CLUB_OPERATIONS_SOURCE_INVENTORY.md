# R42 — provozní inventura ze zdrojového kódu

Ověřeno 23. 10. 2026 v izolované pracovní kopii. Inventura dokládá přítomnost
implementace a definice úloh, **nikoli skutečné zapnutí v produkci**, dodání
zprávy, funkční externí přístup ani převzetí konkrétní osobou. Nebyly čteny
produkční klíče ani kontaktovány externí služby. Vlastníky a skutečné termíny
potvrdí D12/D13; aktivace a přechod nadále vyžadují D15.

| Integrace / úloha | Zdroj a dostupný spouštěč | Co před provozem doložit |
|---|---|---|
| Fio import a případné potvrzování | `bin/fio-import.php`; `.github/workflows/fio-import-production.yml`: `*/10 * * * *`, brána `KIS_FIO_SCHEDULE_ENABLED`; oddělené `FIO_IMPORT_ENABLED` a `FIO_AUTO_CONFIRM_ENABLED` | Vlastník, read-only token a rozsah účtu, poslední úspěch, chyby, zachování VS a ruční řešení neshod. Zapnutí importu není zapnutí automatického potvrzení. |
| Soupiskové zprávy | `bin/roster-message-worker.php`; `.github/workflows/roster-messages-production.yml`: `*/5 * * * *` | Ve workflow není samostatná proměnná `SCHEDULE_ENABLED`. Před aktivací ověřit skutečný stav workflow, omezení příjemců, frontu, chyby a obsluhu; doručení se neodvozuje ze spuštění procesu. |
| Transakční / klubové zprávy | `bin/club-event-notifications.php`, `includes/transactional_notification_worker.php`; `--status`, `--transport=mail|local-outbox` | Stav fronty, zastaralé lease, opakování a náhradní kontakt. V repozitáři nebyl nalezen vlastní plánovaný GitHub workflow tohoto CLI. |
| Rodinné týdenní souhrny | `bin/family-weekly-summaries.php`; explicitní `--generate`, `--send` nebo `--send-local` | Oddělit generování od odeslání; potvrdit adresáty a rodinná oprávnění, interval, vlastníka a dohled. |
| Připomínky členských plateb | `bin/member-charge-reminders.php`; `--generate`, `--send`, lokální transport | Pravidla připomínek a aktuální saldo, obsluha sporných položek, doručení a interval. |
| Chybějící tréninkové záznamy | `cron_upominky.php` je pouze CLI a zapisuje frontu; komentář navrhuje `0 7 * * *` | Komentář není důkaz instalovaného cronu. Ověřit vlastníka, reálný interval a následného odesílatele fronty. |
| Obnova nabídek programů | `bin/refresh-club-program-offers.php`; `.github/workflows/club-program-recurrence-production.yml`: `17 5 * * *`, brána `KIS_CLUB_PROGRAM_RECURRENCE_SCHEDULE_ENABLED` | Povolený rozsah změn, rozdílový náhled, chyby a správce katalogu. |
| Expirace objednávek | `bin/expire-shop-orders.php`; bez `--apply` pouze náhled | Odsouhlasené časové limity a rezervace, zavedení intervalu a dohled; nezaměnit expiraci za refundaci. |
| SumUp / Stripe | `booking/sumup_webhook.php`, `booking/stripe_webhook.php`, příslušné gateway v `includes/` | Konfiguraci, ověření webhooků, opakování, dohled a pravidla ručního řešení ověřit zvlášť. V B1 jsou obě integrace vypnuté. |
| Zásilkovna / Packeta | Kód `includes/shop_packeta*`, diagnostika `bin/diagnose-production-packeta.php` | Skutečný způsob dopravy, klíče, výdejové místo, chybové stavy a odpovědnou osobu; lokální simulace není potvrzení objednané přepravy. |
| Web Push | `includes/push_helper.php`, VAPID nastavení | Vlastníka klíčů a odběrů, doručení a alternativní kontakt; žádné reálné odběry se v tomto kroku neaktivují. |
| Shoptet a původní Evidence/KIS | `bin/shoptet-products-stage.php`, `bin/shoptet-products-dry-run.php`, `sync_evidence.php`, `bin/club-migration.php` | Zdrojové soubory, období, mapy a jediný zapisující systém. Existující importní nástroj není automatická synchronizace ani potvrzený migrační adaptér. |
| Týdenní sportovní PDF | `cron_report_tyden.php`, `report_tyden_lib.php`, `weekly_report_file.php` | Nové PDF a HTML se ukládají privátně; PDF stahuje jen oprávněný vedoucí sportu. Historický výstup `reports/weekly` se nemaže ani neimportuje, ale router a připravené Apache pravidlo blokují přímé stažení. Rozsah starého archivu se musí potvrdit. |

Časy GitHub Actions jsou vyjádřeny cronem workflow (UTC), nikoli českým
časem uživatele. Skutečné serverové crony a stav Actions nebyly dotazovány.
Inventura nepředstírá úplnost všech nasazených integrací nebo webů (D10).

## Místní kontrola bez změn

`bin/probe-finance-b1-sandbox.php` se spouští přes `C:\xampp\php\php.exe`
s přepínačem `-c` ukazujícím na izolované `php-web.ini` v runtime B1.
Čte konfiguraci, skutečný port/datadir DB, umístění soukromých souborů
a dostupnost transportních funkcí i čitelnost všech požadovaných vlastněných
tabulek přes skutečné COUNT. Nezapisuje data ani nevytváří zprávu.
Výstup obsahuje pouze stav kontrol, bez hesel a bankovních údajů.

`tests/Support/FinanceB1RouterHttpAcceptance.php` ověřuje localhost přes
obyčejné CLI PHP: login je dostupný, interní zdroje a soukromé cesty vrací
404 bez původního obsahu. Setup přidává router, protože PHP vestavěný server
nečte Apache `.htaccess`. Přístup z jiného Host než pevný loopback je odmítnut.

Při delší obnově databáze setup skončí s vysvětlením a ponechá probíhající
proces pracovat. Opakované spuštění rozpozná vlastněný startující mysqld
a nespustí druhou instanci nad stejnými soubory. Databázové soubory se nemažou.
Stop nejprve ověří oba listenery; při cizí službě neukončí ani první proces.
Samotný funkční socket ani SELECT DATABASE nejsou potvrzením celistvé DB.
Protokol R42 zaznamenává jednu odhalenou chybu engine a doloženou izolovanou
obnovu se zachováním původního datadiru; skutečná produkce se neopravovala.
