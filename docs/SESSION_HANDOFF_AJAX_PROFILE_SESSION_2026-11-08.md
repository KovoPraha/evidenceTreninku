# Předání P11-RAS — profilové AJAX/session

8. 11. 2026: **NASAZENO, technické ověření PASS**.
[Plán](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md),
[měření a parita](verification/ajax-profile-session-performance-20261108/RESULTS.md),
[produkční protokol](verification/ajax-profile-session-release-20261108/RESULTS.md).
PR[#406](https://github.com/KovoPraha/evidenceTreninku/pull/406) /95cb33e5,
deploy42531190845 SUCCESS22:43:32UTC. Všech16CI na b2a6c25b, celý tested/merged
tree a measured-source obouDB totožné. Jeden aplikační route,6řádků: close až
po lifecycle/fresh identity/profile/person/note gates, před dvěma čteními.
SQL/HTML/TTL/rotace/rodinný gate/writery a produkční handler beze změny.

Native10.3/11.4:1836časovanýchHTTP,72celofragmentových porovnání,12rolí,
pravé login/bearer303, expirace/revokace/scope, CSRF logout bez oživení session,
checksumy a úplný vlastní cleanup PASS. 30kactualpair834→528 /1832→1128ms,
krátký same-session wait499→6,9 /1076→8,2ms; izolované SQL podobné.
Chrome26navigací, warm fragmenty1882→1538ms a celá navigace4611→4277ms,
filtry/stránky/záložky/390px/noerrors PASS; screenshoty před/po byte-identické.
První FAIL fixture10.3/login zachovaný; neopakovat jako nevyřešenou chybu aplikace.

Deploy2403/54847, záloha389tabulek/2triggery, migrace current/pending[], VS a
konfigurace shodné, aktivace/HTTPsmoke/index/klíč PASS. Importfalse/UATfalse,
produktový import SKIPPED. Veřejné8/8stavů i normalizovanýchDOM shodné před/po.
Přihlášené produkční UAT, fyzický mobil, handler/lockwait produkce a skutečné
uložení poznámky NOT RUN. Nejde o produkční SLA ani zrychlení samotného SQL.

Původní WIP/checkout codex/fio-auto-reconciliation-recovery byte-preserved:
kis_rosters_admin.php SHA2567a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5.
Globální hledání RGS nevybrané (UIfalse), neaktivovat; lokální prototyp uchovaný.
Souborové důkazy v codex/ajax-profile-session-release-proof-20261108 patří do
společného baseline po merge do main; další deploy pro dokumentaci nespouštět.
RHA/RHC/RAS hotové. Další pouze KONCEPT: diagnostika parent profilu při30kfixture
(~2,7sTTFB); před implementací nová baseline/měření, žádná cache čerstvých práv.

Finální souborové důkazy: [PR#407](https://github.com/KovoPraha/evidenceTreninku/pull/407),
jen dokumentace; po merge další deploy nespouštět.
