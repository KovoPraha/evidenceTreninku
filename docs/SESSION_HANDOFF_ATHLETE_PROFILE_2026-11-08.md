# Předání P11-RHA — profil sportovce

8. 11. 2026: implementace, parita a měření PASS; finální CI čeká.
[Plán](ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md),
[výsledky a všechny vzorky](verification/athlete-profile-read-performance-20261108/RESULTS.md),
[PR #404](https://github.com/KovoPraha/evidenceTreninku/pull/404).

Větev codex/athlete-profile-history-performance-20261108, base69b69fa4,
vlastní medical-state worktree; původní checkout a WIP hash zachované.
Produkceda152282/deploy42494231010 SUCCESS zatím beze změny.

UNION členství, celkové roky/počty/hodiny,25karet před poznámkami/měřeními.
Měření lazy a25řádků, úplné typy v původním pořadí, binární shoda case/mezer;
číselné ID/ordinal v SQL okně a LIMIT před širokými hodnotami. Žádné DDL/cache,
změny tokenů/rolí/writerů nebo dalších záložek. Stará pomalejší měření zachovaná.

SQLite3/611, MariaDB10.3/11.4 každá3/651 PASS. Runtime3c6d1395,
CI42519956849 3/3SUCCESS, sourcehashy proti Git blobům shodné.
10k historie483→135ms /546→203ms, PHP delta181→4,7MB.
Měření90909→35SQLřádků a211MB→76kB;11.4 typ časově neutrální.
Malý roční výběr <1ms režie přiznaný. ProfilGET129→53ms,6,1MB→12,8kB;
historie111→33ms,8,6MB→208kB, ChromeVše1078→65ms.
476časovanýchHTTP,78Chrome,12rolí, token/grant revokace, filtry/pager/savedtab
PASS, checksumy/cleanup PASS. Desktop screenshot vyžaduje čekání na fade,
poslední testovací doplnění nemění aplikaci. Přihlášené produkční UAT NOT RUN.

Další krok: finální aktuální CI, freshmain, exacttree/sourceproof, merge,
protected deploy import/UATfalse, záloha/VS/smoke a čtecí produkční kontrola.
Finální release důkazy sdílet dokumentačním PR do main bez dalšího deploye.
