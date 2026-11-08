# P11-RHA — produkční předání

8. 11. 2026: **NASAZENO, technické ověření PASS**.
[Plán](../../ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md),
[parita a měření](../athlete-profile-read-performance-20261108/RESULTS.md),
[předání](../../SESSION_HANDOFF_ATHLETE_PROFILE_2026-11-08.md).

PR [#404](https://github.com/KovoPraha/evidenceTreninku/pull/404) sloučený jako
`82efa16decf5bc380bb73bf850d829e17d5002f0` po **14CI SUCCESS** na
`0bed50c5d562e9f83dfa76e00df192f936657866`. [Snapshot](ci-checks.json),
[celý tested/merged tree a čtyři sourcehashy](tree-identity.json) shodné.
Finální [native/HTTP/Chrome42520696813](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42520696813)
3/3SUCCESS; [opakované důkazy](../athlete-profile-read-performance-20261108/checked-head/athlete-profile-integrated-http-browser/integrated-summary.json)
a původní měření3c6d1395 používají přesně stejné runtime soubory.
Zmrazené route69b69fa4 jsou totožné se skutečnou předchozí produkcída152282.

[Deploy42521982178](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42521982178)
**SUCCESS**, dokončen20:54:55UTC. [Metadata/kroky](deploy-summary.json),
[kontrolní souhrn](release-controls.json). Import=false / UAT=false;
produktový import skutečně SKIPPED. Znovu **2403testů /54847assertions** PASS.
Ověřená záloha **389tabulek /2triggery** před aktivací, migrace current/pending[],
dosavadní VS shodné. Aktivace, standardní HTTP smoke, zákaz přístupu k soukromému
klíči a jmenovaný cleanup PASS. Existující konfigurace zachovaná, config_appended=false.
Bez nové migrace/DDL/indexů, datové cache, role, providerů či automatizace.
Obnova této konkrétní zálohy NOT RUN. [Index před](index-preflight/medical-index-before.json)
a [po](index-preflight/medical-index-after.json) v obou čtecích preflight důkazech
přítomný; bez klinických řádků/DDL v diagnostice.

## Shoda a přínos

MariaDB10.3/11.4 každá3testy/651assertions, rekonstrukce všech stránek i vlastních
vazeb/souhrnů přesná. SQLite3/611. První pomalejší varianty zůstávají v protokolu.
10k historie483→135ms /546→203ms;110909→10277SQLřádků a181→4,7MB PHP delta.
Měření90909→35SQLřádků a211MB→76kB. Malý roční výběr <1ms režie přiznaný,
11.4 typ časově neutrální. Žádné tvrzení o celkové paměti databázového serveru.

Skutečný syntetický profil1001tréninků/9000měření: počátečníGET129→53ms,
6,1MB→12,8kB; historie111→33ms a8,6MB→208kB. Chrome warm historieVše
fetch→DOM+dvě animační smyčky1078→65ms.476časovanýchHTTP/78Chrome,12rolí,
pravé login/bearer303 toky, expirace/revokace a skutečné UI interakce PASS.
Nejde o produkční SLA/LCP; staré Měření bylo součástí celého profilu, nový lazy
fragment je další požadavek při otevření. Checksumy/fixture/route/server/account cleanup PASS.
Finální [desktop](../athlete-profile-read-performance-20261108/checked-head/athlete-profile-integrated-http-browser/athlete-profile-after-desktop.png)
a [390px](../athlete-profile-read-performance-20261108/checked-head/athlete-profile-integrated-http-browser/athlete-profile-after-mobile.png)
vizuálně zkontrolované po dokončení Bootstrap fade.

## Produkční čtení a meze

[Před](before.json) i [po](after.json): pět veřejných katalogových GET200,
profil bez přístupu404, historie i měřicí režim403; **8/8 očekávaných stavů**.
Tři zamítnuté profilové odpovědi mají shodný normalizovaný DOM. Žádné cookies,
POST, přesměrování nebo rawHTML/session v Gitu; system curl.exe s TLS ověřením.
**Přihlášené produkční UAT profilu, pozitivní nové uložení poznámky a fyzický
mobil/souběh tímto čtecím průchodem NOT RUN.** Writery/CSRF/access beze změny.

Původní [porovnání celého veřejného DOM](public-comparison.json) vrací u pěti
katalogových stránek NESHODU. Otisk klienta nezahrnul do normalizace náhodný
`data-app-csp-handler` ani CSS verze podle release filemtime. Tento původní
neúspěšný výsledek zachovaný; není přepsaný následným PASS. U dvou prázdných
stránek [řízené porovnání](asset-version-diagnosis.json) přesně reprodukovalo
původní hash změnou pouze tří CSS verzí z1794170915 na1794151187 (čas předchozího
checkoutu). U tří neprázdných stránek původní úplnou doménovou paritu nelze
zpětně doložit z jediného hashe s náhodnými značkami.
[Čtecí rozbor rozsahu](public-scope-check.json): katalog, assety i sharedshell
kód beze změny; runtime se mění jen ve čtyřech profilových souborech.
Žádný katalogový zápis ani rollback obsahu nebyl proveden.

Dva nové průchody **současného stavu po vydání**, s normalizací bezpečnostních
značek/CSRF a asset verzí: [před opakováním](recheck/before.json),
[po opakování](recheck/after.json), [porovnání](recheck/comparison.json)
**8/8 HTTP a normalizovaný DOM shodné**. Jde o stabilitu současného stavu,
nikoli náhradu původní před/po kontroly. [Kalibrace chybné cesty](calibration-wrong-path.json)
je oddělená: kořenové AJAX route nejsou v /booking; skutečné root URL v before/after
byly ověřeny před vydáním. [Použitý původní klient](public-probe-v1.php.txt) a [opravená normalizace](public-probe-v2.php.txt) jsou v repu. Lokální XAMPP libcurl měl protokolovou chybu;
kontrola použila systémový curl, ověřování TLS se nevypínalo.

Původní checkout codex/fio-auto-reconciliation-recovery a WIP
kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodné;
[důkaz](wip-preservation.json). Ostatní worktrees a nezařazené výstupy zachované.
Finální dokumentační předání: větev codex/athlete-profile-release-proof-20261108,
sdílení do main samostatným dokumentačním PR; další deploy tím není potřeba.
Technický řez hotový, přihlášené UAT a ostatní P11 samostatné otevřené kroky.
