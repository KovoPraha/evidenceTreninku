# P11-RHMS — nasazení a čtecí ověření

#422 / `17110a7f2f4338584ec36e5de835150abe17bb17`, testovaný head `730de3d6d19d037c6df9f39b52cc20a7ac45ffbd`, všech 20 CI SUCCESS.
Celý testovaný/sloučený strom a měřené runtime Gitbloby jsou shodné.
[Deploy 42679050552](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42679050552)
SUCCESS, dokončeno 2026-11-09T19:05:07Z. Testy 2418 / 55585 assertions;
ověřená úplná záloha 389 tabulek / 2 triggerů.
Migrace pending=[], konfigurace zachovaná, config_appended=false, VS beze změny,
indexy/keyguard/smoke/cleanup PASS. Import=false, UAT=false; žádné druhé nasazení
kvůli závěrečné dokumentaci. Veřejné čtecí před/po: 8/8 HTTP a normalizované DOM shodné.

Aktuální celé HTTP 30k/270k: 535.915→342.364 ms (-36.1 %, 10.3); 875.862→546.464 ms (-37.6 %, 11.4).
Chrome warm uložené Měření: celá navigace 1542.55→1209.30 ms
(-21.6 %), fetch→DOM+2frames 1042.45→696.15 ms.
Malý profil má +26.443/+14.369 ms, starý +10.033/+5.480 ms; prázdný
prakticky neutrální. Hlavní 20% gate i historický no-regression gate PASS.
Jde o syntetická data; nelze úsporu připsat všem profilům nebo stránkám.

Obě DB: 6 testů / 634 assertions, 1836 časovaných AJAX a 52 měřených Chrome
navigací plus warmupy/interakce. Úplné objekty/PHP typy/HTML/JS/role/token/
revokace/CSRF/11 checksumů a cleanup PASS. Snímky před/po byte-identické,
oba nové snímky vizuálně ověřené. Stávající úzká tabulka omezuje dlouhé
syntetické poznámky; nejde o nově opravené mobilní UX ani fyzické mobilní UAT.

Změna jen souhrnu: přesné binární COUNT/MAX datum a první tuple nejnovějšího
data typu v jednom statementu, přesné signed64/NULL řazení typů v PHP.
Původní selector/header/history/navigation a 11 canonical/bezpečnostních
souborů byte-shodné. Žádné aplikační DDL/indexy/ANALYZE/cache/session/UI/writer změny.
Předchozí kandidáty a infrastrukturní chyby zachované; oba celé potvrzující
native běhy mají stejný runtime. Obnova statistik pouze ve společné syntetické
přípravě před oběma variantami. PHP peak diagnostiky obsahuje profiler;
není to paměť prostého requestu, RSS ani paměť DB.

Přihlášené produkční UAT, produkční výkon, fyzický mobil, nový zápis poznámky
a obnova této konkrétní produkční zálohy NOT RUN. Původní checkout/větev,
untracked soubory a WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.

## Důkazy

[CI](ci-checks.json), [strom a zdroje](tree-identity.json),
[deploy a všechny kroky](deploy-summary.json), [skutečné hodnoty kontrol](release-controls.json),
[před](before.json), [po](after.json), [srovnání](public-comparison.json),
[aktuální celé měření](../checked-head/RESULTS.md),
[předchozí potvrzující měření](../validated-d8/RESULTS.md).

Finální důkazové předání: `codex/athlete-measurement-summary-release-proof-20261109`
→ samostatný dokumentační PR do main; stav publikace je ověřitelný v GitHubu.
Samotná publikace těchto důkazů nesmí spustit další produkční deploy.
