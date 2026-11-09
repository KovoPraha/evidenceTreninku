# P11-RAH — produkční předání

9. 11. 2026: **NASAZENO / technické ověření PASS**.
PR[#410](https://github.com/KovoPraha/evidenceTreninku/pull/410),
merge c47d4008cf2c3814779f5f4eed4651e1448182cf.
[Plán](../../ATHLETE_PROFILE_HEADER_PERFORMANCE_2026-11-09.md),
[finální měření](../athlete-profile-header-performance-20261109/RESULTS.md),
[předání](../../SESSION_HANDOFF_ATHLETE_HEADER_2026-11-09.md).
[Všech18CI SUCCESS](ci-checks.json) na b8f910376375f774883eee7bda07c72ff8e3a7a5.
[Celý tested/merged tree a4source SHA256](tree-identity.json) přesné;
oba zmrazené baseline soubory2c61df14 shodné s předchozí produkcí ca5fc219.
Původní3metody a zbytek readeru po vyjmutí header() byte-preserved.
Canonical/overview souhrn, AJAX, closure/note writers a security stejné.

[Deploy42587406908](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42587406908)
**SUCCESS**, dokončeno 2026-11-09T08:43:14Z, přesný c47d4008.
[Metadata a kroky](deploy-summary.json), [kontrolní souhrn](release-controls.json).
NASADIT, import=false/UAT=false, produktový import skutečně SKIPPED.
Testy2407/54929PASS, ověřená záloha389/2 před aktivací,
migrace current/pending[], původníVS beze změny. Konfigurace zachovaná,
config_appended=false, aktivace/HTTPsmoke/private-key guard/index/cleanup PASS.
[Čtecí index před](index-preflight/medical-index-before.json),
[po](index-preflight/medical-index-after.json); žádný nový DDL/index.
Obnova této konkrétní zálohy NOT RUN, nepřebírat úspěch předchozí generace.

[Před](before.json), [po](after.json), [porovnání](public-comparison.json):
8/8očekávanýchHTTP a8/8celých normalizovanýchDOM shodné (5katalogGET200,
anonymníprofil404 a obaAJAX403). Bez cookies/POST/redirectů/soukromých profilů.
[Verzovaný v2probe](../athlete-profile-release-20261108/public-probe-v2.php.txt)
vynechává skripty/nonce/CSPmarker/CSRF a releaseCSSverze. RawHTML se neukládá.

## Přínos a meze

Finální30k/270kfixture parent10.3 1009.892→326.150ms (−67.7%),
11.4 1349.797→455.233ms (−66.3%). SQL17→15, vrácenéřádky15→13.
PHPmanagedpeak asi+12kB (1.250→1.262MB); nepředstírat paměťovou úsporu.
Chrome warm default2424.55→1520.05ms (−37.3%), savedMěření3010.15→2110.40ms
(−29.9%); samotnýAJAX neutrální, jeho SQL zdroj beze změny.
612časovanýchHTTP+52Chrome, native2/30 každáDB,SQLiteheader2/20,
širší místní17/807; celéHTML/JS,15rawfragmentvariant/DB,12staffrolí/4kontexty,
freshrevokace/expirace/scope/CSRF,2chybovéfallbacky a11checksumů PASS.
Kompletní vlastní cleanup PASS. Desktop/390px PNG byte-identické a vizuálně
ověřené; konzole/transport0. První neúplný i úplné běhy zachované odděleně.
Syntetické časy nejsou produkční SLA/LCP. Přihlášené produkční UAT,
produkční výkon, skutečný zápis poznámky a fyzický mobil NOT RUN.
Žádná změna produkčních rolí/osob/providerů/importů/automatizací.
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 shodný,
původní checkout/větev i untracked soubory zachované.
Důkazy předává codex/athlete-header-release-proof-20261109 samostatným
dokumentačním PR; bez opakovaného deploye dokumentace.
