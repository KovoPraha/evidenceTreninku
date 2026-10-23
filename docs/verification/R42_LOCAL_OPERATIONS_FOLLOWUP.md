# R40/R42 — opravy místního předání, 23. 10. 2026

Pouze izolované B1 prostředí a místní kód, bez produkčních změn.

## Opravené skutečné mezery

- Vestavěný PHP server nepoužívá `.htaccess`. Setup nyní vyžaduje router,
  který blokuje interní zdroje, SQL, konfiguraci, soukromé přílohy, traversal,
  windowsové aliasy a cizí Host. Povoluje skutečné veřejné soubory v checkoutu.
- Read-only probe ověřuje skutečnou DB identitu a soukromé cesty i efektivní
  restricted PHP ini. Úspěch není odvozen pouze z deklarace v environment.json.
- Stop nejprve ověří oba listenery. Při cizí databázi nevypne ani vlastněný web.
- Setup rozpozná mysqld, který ještě obnovuje data před otevřením socketu.
  Opakované spuštění nespouští druhý proces nad stejným datadirem. Skutečný
  první start narazil na delší crash recovery, další běh po dokončení prošel;
  data se neopravovala ani nemazala.
- Týdenní report ukládal osobní údaje do veřejné cesty `reports/weekly`.
  Nové HTML/PDF používají `private/week-reports/<náhodné ID>` a manifest s ID
  osob/tréninků a SHA-256 obou souborů. PDF download ověří skutečnou aktivní
  roli, manifest a shodu bajtů; endpoint zachovává původní přístup pouze
  vedoucímu sportu. HTML se veřejně neposkytuje. Starý archiv zůstává na místě
  a přímý přístup do `reports` blokují router a připravené Apache pravidlo.
- Původní PDF renderer Mpdf nebyl v závislostech. Report používá již instalovaný
  Dompdf s vypnutým vzdáleným načítáním, PHP a JavaScriptem. Bez nového balíku.

## Důkazy

`FinanceB1RouterTest` + `WeeklyReportPrivacyTest`: **10 testů / 40 assertions PASS**.
Ověřeny zakázané cesty, průchod přihlášení, hash obsahu, traversal, odebraná
role, neaktivní účet a zákaz odvozovat rozsah z pouhé znalosti ID souboru.
Závěrečná kontrola také zachovává explicitního živého superadministrátora
v pozici vedoucího sportu bez duplicitního přidělení této pozice; samotná
stará session ani odebraný grant nebo deaktivovaný účet nestačí.

`FinanceB1RouterHttpAcceptance.php`: **14 read-only HTTP kontrol PASS**.
`FinanceB1LifecycleGuards.ps1`: **1 mock procesní přejímka PASS**, žádný
skutečný proces nebyl v testu ukončen.
`probe-finance-b1-sandbox.php` se restricted ini: **PASS**; skutečný port,
datadir a soukromé cesty ověřeny, transportní/procesní funkce vypnuté.

`WeeklyReportPrivacyHttpAcceptance.php`: **11 kontrol PASS**, z toho 3
kontroly generování/uložení a 8 kontrol nad 7 skutečnými HTTP požadavky. Dvě generování
zachovávají dvě různá originální ID; vedoucí stahuje checksum-identické PDF,
ostatní role i anonymní uživatel jsou odmítnuti, odebrání role platí i pro
dříve přihlášenou relaci. Výstup má attachment a no-store.

Příklady soukromých manifestů z běhu:
`b771c9f63a01fc1124889d6a1bd844b1`, `2e86b9f9c24740feb19443f894776838`.
Jde pouze o syntetický report bez skutečných osob. Úplná obnova a regresní
sada se ověřují zvlášť v rodičovském společném předání.

### Skutečné zastavení a návrat B1

23. 10. 2026 v 07:41 CEST, po dokončení syntetických zapisujících testů,
proběhl jeden skutečný `stop-finance-b1-sandbox.ps1` a navazující setup.
Stop použil graceful mysqladmin shutdown a počkal na ukončení vlastněných
procesů; soubory ponechal na místě. Oba skripty skončily úspěšně, celý příkaz
trval přibližně 8,2 sekundy. Nový MariaDB PID byl 116408, HTTP PID 185632,
oba listenery pouze na 127.0.0.1 (33426 / 18086). Kontrola našla právě jeden
mysqld s vlastněným B1 datadirem. V novém startup úseku mysql.log nebylo
hlášení crash recovery. Následný čtecí probe v 07:41:45 CEST a všech
14 routerových HTTP kontrol znovu prošly. **Tento tehdejší mělký probe však
neprokazoval čitelnost všech tabulek a nesmí být považován za důkaz zachování
celé databáze.** Následná záloha odhalila níže uvedenou engine chybu. Nešlo
o restart XAMPP produkčního serveru ani aktivaci integrací.

### Odhalená chyba engine a izolovaná obnova

Následný rodičovský backup odmítl tabulku `child_access_events` s chybou
1932 / SQLSTATE 42S02. Read-only COUNT všech 339 tabulek v 07:46:48 CEST
prokázal 338 čitelných a tuto jedinou nečitelnou. `.frm`, `.ibd` a InnoDB
dictionary záznam existovaly. Z běžného mysql.log nelze určit začátek vady:
žádný záznam 1932 tam nebyl. Dřívější crash recovery je doložená, ale příčina
ani souvislost s posledním graceful restartem nebyla prokázána.

Rodič schválil obnovu bokem bez mazání původního datadiru. Na dočasném
loopback portu 33427 vznikl nový server s logickým dumpem všech 338 čitelných
tabulek a obou triggerů. Nečitelná tabulka se obnovila z poslední ověřené
zálohy z 2026-10-22 23:30:37 UTC (SHA-256
`2083e3e22a940e9dc950280339cafe7a35604a23b019891e1e51ed99ade38fe9`), která
obsahovala 0 jejích řádků. Také současná `child_access_accounts` měla 0 řádků.
Případná nedostupná historie mezi zálohou a zjištěním chyby zůstává výslovně
neznámá; žádné události se nevymýšlely.

Před přepnutím byly porovnány počty a multisetové SHA-256 všech řádků 338
tabulek, úplné definice tabulek a oba triggery. Jediná normalizace DDL třídí
řádky sekundárních KEY: MariaDB při obnově změnila pořadí dvou identických
UNIQUE indexů v prázdné `osoba_citlive_udaje`. Sloupce, indexové definice,
constraints ani AUTO_INCREMENT se neignorují. Původní raw DDL snapshot byl
zachován. Obsah a zdroj během porovnání zůstaly stejné.

Po graceful ukončení obou vlastněných serverů se původní celý datadir
přesunul do `mysql-quarantine-recovery-20261023-075136-e4945a30` uvnitř B1
runtime; **zůstává zachovaný včetně všech starých testovacích schémat**.
Nový ověřený datadir převzal původní `mysql` a port 33426. Po restartu znovu
souhlasilo všech 338 tabulek, oba triggery a obnovená prázdná tabulka. Soukromé
aplikační soubory se nepřeváděly ani nepřepisovaly. Závěrečnou novou zálohu
a obnova následně prošly: 326 aplikačních tabulek, 175 soukromých souborů;
viz [závěrečný společný protokol](autonomous-followup/RESULTS.md).

### Zpřísněná readiness po nálezu

Probe nyní opravdu otevře engine přes COUNT všech přítomných vlastněných
tabulek. Nečitelná nebo chybějící požadovaná tabulka znamená FAIL; pouze tři
jmenované staré volitelné tabulky `ucto_gs_kategorie`, `ucto_gs_link_targets`
a `ucto_gs_linky` smějí chybět a jsou ve výsledku viditelné. Na původní vadné
DB probe správně vracel FAIL/1932; na obnovené v 07:56:59 CEST prošlo 326
vlastněných tabulek, žádná nečitelná ani chybějící požadovaná.

Setup nesmí oznámit ready před tímto probe. Ověřuje přesný HTTP executable,
ini, bind, docroot a router; po úspěšné kontrole restartuje pouze tento
vlastněný HTTP proces, aby načetl právě ověřené ini. Strojové JSON a owner
soubory zapisuje UTF-8 bez BOM i na Windows PowerShell 5.
`FinanceB1ReadinessTest`: **3 testy / 8 assertions PASS**;
`FinanceB1ProcessGuards.ps1`: **7 kontrol PASS** bez ukončování reálných
procesů. Po finálním setup prošel nový probe a dalších 14 routerových
HTTP kontrol. Detaily bez dat a přihlašovacích údajů jsou v
`autonomous-followup/b1-recovery-summary.json`.

## Zbývající lidská rozhodnutí

Produkční crony a klíče nebyly ověřovány ani aktivovány. Source-derived
[inventura](../CLUB_OPERATIONS_SOURCE_INVENTORY.md) rozlišuje implementaci
od skutečného zapnutí. D10–D16 zůstávají otevřené. Historická týdenní PDF
nejsou tímto krokem automaticky převedena do nového soukromého archivu.
