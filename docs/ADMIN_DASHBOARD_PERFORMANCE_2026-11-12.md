# P11-ADM — agregace administrátorského přehledu

Zahájeno 12. 11. 2026; NASAZENO 13. 11. 2026. Vývoj/Codex na navazující pokyn Marka pokračovat
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

[Průběžné výsledky](verification/admin-dashboard-20261112/RESULTS.md). Přejímka a vydání jsou dokončené; viz výsledek níže.

## Diagnostická varianta a změna výběru

První běh 43319920379 na MariaDB 11.4 potvrdil shodné hodnoty, ale spojení
všech čtyř počtů bylo na větších sadách pomalejší. Na 100 000 záznamech
mixed 49,87 → 84,36 ms, grouped 62,92 → 90,17 ms, ungrouped 32,90 → 53,24 ms.
Varianta s jedním SQL se proto nepoužije. Původní NOT EXISTS zůstane
samostatným dotazem a tři stavové počty spojíme. Poslední varianta má dva
SQL místo čtyř; finální párové měření je v odkazovaných výsledcích.

## Převzetí souběžných změn

Pracovní větev byla po sloučení #462 aktualizována na main 22d151f7.
Zachována je oprava pozice soupisek i její plán a záznamy. Referenční
admin_dashboard.php z 5cd6ad07 je obsahově shodný s touto novou základnou.

Po souběžném #463 byla základna znovu aktualizována na 373b8d89.
Zachována je aktivace importovaných účtů, její migrace i dokumentace.
admin_dashboard.php je proti referenci 5cd6ad07 nadále shodný.

## Výsledek

**P11-ADM NASAZENO.** [Plán](ADMIN_DASHBOARD_PERFORMANCE_2026-11-12.md), [měření](verification/admin-dashboard-20261112/RESULTS.md). #465/be4ce55a, [deploy 43326177751](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43326177751) SUCCESS, 13 CI. Čtyři počty: 4→2 SQL; celé HTTP −6–9 % v CI. Přihlášené produkční UAT NOT RUN.

Na 10 000 osobách nativní část −6–14 %, na 100 000 −1,8–10,1 %.
Celé HTML, oprávnění a dva full-page snímky shodné; 358 tabulek beze změny.
Varianta jednoho SQL zamítnuta kvůli regresi. Finální varianta zachovává
NOT EXISTS a spojuje jen tři stavové počty. Paměť +112 B, FCP shodný.
Přesná data, omezení, záloha a provozní důkazy jsou v [měření](verification/admin-dashboard-20261112/RESULTS.md).
Další krok: měřit odstranění duplicitního Bootstrapu administrátorského přehledu.

**P11-ASSET11 NASAZENO.** [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset11--administrátorský-přehled-13-11-2026), [měření](verification/admin-dashboard-bootstrap-20261113/RESULTS.md). #470/cd04bad6, [deploy 43386354560](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43386354560) SUCCESS, 15 CI. Studený Bootstrap CSS 64454→31248 B; pixely shodné. Přihlášené produkční UAT NOT RUN.
