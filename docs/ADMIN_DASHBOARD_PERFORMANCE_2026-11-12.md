# P11-ADM — agregace administrátorského přehledu

12. 11. 2026. ROZPRACOVÁNO. Vývoj/Codex na navazující pokyn Marka pokračovat
v testovaných a měřených optimalizacích, merge a nasazení.
Base 5cd6ad07, výchozí nasazený runtime 428a2258, deploy 43269836068 SUCCESS.
Větev codex/admin-dashboard-performance-20261112, samostatný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-admin-dashboard-performance-20261112.
Původní fio checkout a WIP kis_rosters_admin.php zachované.

## Výběr a rozsah

Navazuje na [analýzu](verification/performance-analysis-20261107/RESULTS.md)
a [výsledek RHC2](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md#výsledek-p11-rhc2).
admin_dashboard.php čte sportovce čtyřmi dotazy pro počet bez skupiny,
dluh, nepřítomnost v importu po 90 dnech a současný ruční/KIS stav.
Ověříme jednu podmíněnou agregaci se stejnými predikáty a celočíselným
výstupem. Přesná parita zahrne NULL, prázdnou tabulku, více skupin,
osiřelé vazby a hranici 90 dnů s pevným časem při měření.

První alternativa ponechává čtyři dotazy jako referenci. Druhá slučuje
podmínky do jednoho čtení. Nasadí se varianta s doloženým přínosem.
Duplicitní Bootstrap na této trase zůstává dalším samostatně měřitelným
kandidátem. Schéma, oprávnění, obsah karet a nastavení náhrad patří mezi
kontrolované invarianty.

## Postup a přejímka

1. Nezávislá reference přesných původních čtyř dotazů.
2. Párové měření na stejných datech, MariaDB 10.3/11.4, velikosti 0,
   100, 10 000 a 100 000 sportovců. Čtyři zahřívací a 17 střídavých párů.
   Čas, paměť a počet SQL, čtecí transakce a otisky dat.
3. Celé HTTP a bezpečnostní hlavičky před/po na syntetické migrované
   databázi. Systémový správce, odmítnuté pracovní pozice a anonymní vstup.
   Chrome porovná desktop a mobilní rozlišení včetně pixelů.
4. Po shodě a měřeném přínosu provést commit, zelené CI, merge se shodným
   stromem a chráněné nasazení. Produkční čtecí kontrola před/po,
   UAT/import false. Zachovat souběžné změny v main.
5. Aktualizovat tento plán, registr, CURRENT, handoff, události a důkazy.

[Průběžné výsledky](verification/admin-dashboard-20261112/RESULTS.md). Další krok je implementace a párové ověření.
