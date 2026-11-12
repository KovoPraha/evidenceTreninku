# Předání P11-RH — správní historie tréninků

8. 11. 2026: HOTOVO v technickém rozsahu / NASAZENO.
[Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md),
[parita a měření](verification/training-history-page-performance-20261108/RESULTS.md).

PR [#394](https://github.com/KovoPraha/evidenceTreninku/pull/394), HEAD
`2a02e96fb00101866e8d0d05fe460c57b305ab53`, vlastní větev
`codex/training-history-page-performance-20261108` ve worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Base cc805561, přijatý main755b20a2 včetně #390/#393 zachovaný.
Původní checkout a WIP neměnit; hash kis_rosters_admin.php
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.

Obě správní historie načítají detaily/štítky/účastníky pouze25 tréninků;
přesné souhrny a kanonický historický filtr před stránkou. Nepoužité legacy LEFT
JOIN odstraněné pouze bez filtru příslušného aliasu. Všechny záznamy dosažitelné
stránkami. Remízy data mají id DESC; hledání přes celou historii potvrdit Enter/Filtrovat.

Nativní MariaDB10.3/11.4 po3 testech/5006assertions PASS. Všechny stránky a přesné
hodnoty shodné s referencí; source SHA256 ověřeny proti Git. CI42485394262 všechny3úlohy PASS.
Na10k trénincích čtení MariaDB10.3 2733→38ms /11.4 1850→39ms,
22→4SQL a140000→10350řádků. Malý jediný výsledek +~0,4ms kvůli dalšímu SQL.
Celý bohatý GET1002tréninků398→16ms,8,41MB→253kB;408HTTP/12negative/78Chrome,
klik další stránky/filtry/reset hledání, pět checksumů a vlastní úklid PASS.

Všech14CI PASS, hlavní sada2392testů/53289assertions. #394 sloučené jako
`ab49947d9f673636d717fbbb4acb04f608eff477`, celý tree i měřené zdroje přesně shodné.
Standardní deploy42486500282 SUCCESS na tomto přesném main, import/UAT false.
Záloha389tabulek/2triggery, migrace current, VS shodné, aktivace/smoke PASS.
[Produkční protokol](verification/training-history-release-20261108/RESULTS.md):
katalog5/5HTTP200 a celý DOM shodný před/po; obě historie302/login, WIP shodný.

Finální důkazy jsou na větvi `codex/training-history-release-proof-20261108`;
[PR #398](https://github.com/KovoPraha/evidenceTreninku/pull/398) předává důkazy do main; další deploy není potřeba. Neopakovat vydání ani writery.
Další krok mimo uzavřený řez: přihlášené produkční UAT historií NOT RUN,
ostatní AJAX/profilové historie a celé K0.2 otevřené; nepředstírat jejich přejímku.

- **P11-RHC2 NASAZENO.** [Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md#p11-rhc2--omezená-agregace-vzorů-kopírování-12-11-2026), [měření](verification/training-copy-20261112/RESULTS.md). #457/87171a67, [deploy 43215069511](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43215069511) SUCCESS, 13 CI. Omezená agregace a původní výběr při remízách. Přihlášené produkční UAT NOT RUN.
