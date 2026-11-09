# Předání P11-RHI — 9. 11. 2026

**HOTOVO / NASAZENO**

[Plán](ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md),
[měření](verification/athlete-profile-history-performance-20261109/RESULTS.md).

#413/c3d34b87, tested ecd47579 všech18CI SUCCESS; celý tested/merged strom
shodný a5measured-source SHA256 přesné. Přijatá oprava #412 zachovaná.
Deploy42625502461 SUCCESS 2026-11-09T13:30:32Z, testy2411/55204, backup389/2,
migrace/VS/config/index/smoke PASS, import=false/UAT=false. Veřejné8/8HTTP/DOM shodné.
[Provozní důkazy](verification/athlete-profile-history-release-20261109/RESULTS.md).

Finální30k/270k historieHTTP599.411→64.589ms (−89.2%,10.3),
953.704→63.851ms (−93.3%,11.4), PHPmanagedpeak14.53→1.56MB (−89.3%).
SQL12→12/vrácenéřádky30282→30282: méně práce a PHP kolekcí, stejná data.
Chrome warm default historie doDOM+2frames1012.85→540.20ms,
celá navigace1530.25→1058.25ms (−30.8%); dokumentTTFB neutrální.
SavedMěření v prvním běhu+9.5%, ve finálním−8.2% (2234.60→2051.90ms);
neslibovat stabilní přínos Měření, jehoSQL se nezměnilo. První vzorky zachované.
Dřívější regrese neexistujícího roku odstraněná:11.07→11.22 /10.70→10.95ms.

Native2/513 každáDB,SQLite2/261, místní7/892;1428časovanýchAJAX+52Chrome
ve finálním běhu. Přesné součty/typy/řazení/stránky i celé fragmenty,
12staffrolí/bearer/freshrevokace/CSRF/2fallbacky/11checksumů a cleanup PASS.
Desktop/390px snímky byte-identické/vizuálně ověřené, konzole/transport0.
Žádné DDL/cache/změny session, TTL nebo profilových writerů. Mimo history()
je celý reader byte-preserved; endpoint/parent/header/měření zůstávají stejné.
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný,
původní checkout/větev i untracked soubory nedotčené.

Přihlášené produkční UAT, produkční výkon, obnova této konkrétní zálohy,
nový zápis poznámky a fyzický mobil NOT RUN. Syntetická čísla nejsou SLA/LCP.
Finální důkazy: codex/athlete-history-release-proof-20261109, samostatný
PR do main bez opakovaného deploye dokumentace. Rozsah dokončen.
Další pouze KONCEPT: změřit zbývající rozdíl mezi samotným HTTP readmodelem
a browser fetchTTFB (bootstrap/čerstvé ověření/fronta). ResourceTiming sám
příčinu nedokazuje; žádné cachování práv či další implementace tímto povolené.
