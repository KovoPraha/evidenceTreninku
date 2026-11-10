# Předání P11-HJS3 — 11. 11. 2026

SLOUČENO / deploy běží. [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs3--společný-skript-po-odstranění-duplicitních-stylů-10-11-2026),
[měření a vydání](verification/shared-header-cache-20261110/RESULTS.md).
#449/b753f6e7; celý tree shodný s testovaným dd44cff7,14CI PASS.
Native42917624902: MariaDB10.3/11.4,1k/10k/30k,357 checksumů,
přesné HTML/security hlavičky, CSRF/role/revokace a cleanup PASS.
Chrome544 měření+128warmupů,24funkčních případů,8pixelpárů PASS.
Staff HTML−13 899B; warm gzip−25,37–29,93%, první cold+955–1264B.
Vrátí se při druhé pracovní stránce. Gzip těla HTML+staffJS bez hlaviček;
nejde o plošné zrychlení serveru. FCP/ready gate prošel, úplné mediány v dokladech.
Pouze obsahový JS cache7d/SRI;6hodnot čerstvých v HTML, ostatní JS30s.
Deploy42920002806 na b753f6e7 běží, UAT/importfalse; do SUCCESS poslední
prokázaný runtimec29642f1/deploy42905781826. Další krok receipt a živé GET/hash/cache.
Uživatelský Chrome byl na /login.php, přihlášené produkční UAT NOT RUN.
Žádné produkční POST, role/účty ani oznámení. Fyzický mobil NOT RUN.
Původní fio/d1221f35 a WIP7a7afd75 zachované. Staré HJS/HJS2 nevybrání,
ASSET4/6 LIMIT a ASSET7 přihlášené UAT NOT RUN zůstávají historicky platné.
Doklady právě na codex/header-cache-release-docs-20261111, ještě ne v main;
finální dokumentační PR po všech CI sloučit bez dalšího deploye.
