# Předání P11-RAP — počáteční profil sportovce

9. 11. 2026: **NASAZENO / technické ověření PASS**.
[Plán](ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md),
[měření a parita](verification/athlete-profile-parent-performance-20261109/RESULTS.md),
[produkční protokol](verification/athlete-profile-parent-release-20261109/RESULTS.md).
PR[#408](https://github.com/KovoPraha/evidenceTreninku/pull/408) /ca5fc219,
deploy42574460894 SUCCESS06:45:44UTC. Všech16CI na95305c2a SUCCESS,
celý tested/merged tree a4measured-sourceSHA256 obouDB přesné.

Parent panel nyní volá3polový SportsActivityOverviewRead se stejnými kanonickými
closure/event readers. Plný sportsActivitySummary/export/hash, historie/AJAX,
rights/session/CSRF/odměny a všechny writery byte-identické. Žádné DDL/cache.
Samostatná původní diagnóza prokázala94752nepoužitých measurement snapshotů;
výsledky této diagnózy i prvních párů zůstávají zachované, nesmí se míchat běhy.

Finální native10.3/11.4:30kparent1890→864 /3034→1356ms (−54/55%),
SQL22→17,94771→15řádků a PHPpeak96,6→1,26MB. Parita2/54 na každéDB iSQLite,
lokálně49/324,612časovanýchHTTP a52Chrome navigací. Warm default4126→2433ms,
savedMěření5074→2944ms; vlastní AJAX SQL beze změny, komponenta v browseru proměnlivá.
Celé normalizovanéHTML/JS,12rolí,bearer303,expiry/revokace/scope,CSRF/logout,
15byte-shodných AJAX variant/DB,11doménovýchchecksumů a všechny own cleanupy PASS.
Vizuální desktop/390px a UI interakce PASS; PNGpřed/po byte-identické.

Deploy2405/54909, záloha389tabulek/2triggery, migrace current/pending[], VS a
konfigurace shodné, index/aktivace/smoke/klíč PASS. Importfalse/UATfalse,
produktový import SKIPPED. Veřejné8/8HTTP i normalizovanýchDOM shodné před/po.
Přihlášené produkční UAT, fyzický mobil, produkční výkon a skutečné nové uložení
poznámky NOT RUN. Syntetická čísla nejsou produkční SLA/LCP ani paměť DB serveru.

Původní codex/fio-auto-reconciliation-recovery a WIP kis_rosters_admin.php
SHA2567a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 shodné.
Souborové důkazy: codex/athlete-parent-release-proof-20261109, předat do main
přes dokumentační PR; další deploy pro dokumentaci nespouštět.
RHA/RHC/RAS/RAP hotové, RGS vypnuté/nevybrané, neopakovat/neaktivovat.
Další diagnostický KONCEPT: sdílet drahý UNION roků/počtu headeru; nejprve nová
baseline a parita null/0dat i PHPtypů, žádná cache čerstvých práv.

## Návaznost 9. 11. 2026 — agregace dokončené

Header KONCEPT převzalo a dokončilo [P11-RAH](ATHLETE_PROFILE_HEADER_PERFORMANCE_2026-11-09.md),
#410/c47d4008/deploy42587406908 SUCCESS; [aktuální předání](SESSION_HANDOFF_ATHLETE_HEADER_2026-11-09.md).
Historická RAP měření zůstávají původní, nové úspory se k nim nepřičítají.
