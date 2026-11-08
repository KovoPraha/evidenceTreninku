# Předání P11-RHA — profil sportovce

8. 11. 2026: **NASAZENO, technický řez hotový**.
[Plán](ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md),
[parita/vzorky](verification/athlete-profile-read-performance-20261108/RESULTS.md),
[produkční předání a meze](verification/athlete-profile-release-20261108/RESULTS.md).

PR #404 /82efa16decf5bc380bb73bf850d829e17d5002f0 po14CI na0bed50c5;
celý tested/merged tree a4runtime sourcehashy přesně shodné.
Deploy42521982178 SUCCESS20:54:55UTC, import/UATfalse;2403testů/54847assertions,
záloha389tabulek/2triggery ověřená, current/pending[], VS shodné, smoke PASS.

UNION členství, úplné roky/počty/hodiny;25karet před poznámkami/měřeními.
Měření lazy a25řádků, úplné typy v původním pořadí, binární case/mezery;
číselné ID/ordinal v SQL okně, LIMIT před širokými hodnotami. Žádné DDL/cache,
změny přístupů/tokenů/rolí/writerů/CSRF nebo dalších záložek.
SQLite3/611; native10.3/11.4 každá3/651,476HTTP/78Chrome a12rolí PASS;
pravé login/bearer303, expirace a revokace, filtry/pager/savedtab/390px PASS.
10k historie483→135ms /546→203ms; Měření90909→35řádků a211MB→76kB.
ProfilGET129→53ms a6,1MB→12,8kB; historie111→33ms /Chrome1078→65ms.
Malý roční výběr <1ms režie,11.4 typ neutrální; jde o syntetická data, nikoli SLA.
První pomalejší iterace zachované, finální screenshoty po fade zkontrolované.

Produkční čtení8/8 očekávaných stavů, profil404/AJAX403/403 shodné;
nový současný DOM8/8stabilní. Původní katalogový wholehash neměl normalizaci
nonce markerů/CSS verzí; jeho neshoda a omezení zůstávají v protokolu.
Přihlášené produkční UAT, pozitivní nové uložení poznámky a fyzický mobil/souběh
NOT RUN. Obnova této konkrétní zálohy NOT RUN. Ostatní P11 samostatné.

Původní checkout codex/fio-auto-reconciliation-recovery a WIP hash7a7afd75…
shodné; vlastní medical-state worktree, aktuální proof větev
codex/athlete-profile-release-proof-20261108. Finální důkazy sdílet dokumentačním
PR do main, bez dalšího deploye; runtime82efa16d se dokumentací nemění.
