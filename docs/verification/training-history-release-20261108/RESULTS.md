# P11-RH — produkční předání

8. 11. 2026: **NASAZENO / technické ověření PASS**.
[Plán](../../TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md),
[parita a měření](../training-history-page-performance-20261108/RESULTS.md),
[předání](../../SESSION_HANDOFF_TRAINING_HISTORY_2026-11-08.md).

PR [#394](https://github.com/KovoPraha/evidenceTreninku/pull/394) sloučené jako
`ab49947d9f673636d717fbbb4acb04f608eff477` po všech **14 CI SUCCESS** na
`2a02e96fb00101866e8d0d05fe460c57b305ab53`.
[Snapshot kontrol](ci-checks.json), [přesná shoda celého stromu a měřených zdrojů](tree-identity.json).
Původní oba route soubory baselinecc805561 jsou navíc identické se skutečnou
předchozí produkcí755b20a2. Ta byla nasazená běhěm42483838259 již13:17:54UTC;
naše předchozí anonymní GET proběhly až potom. Převzaté #390/#393 zachované.

[Deploy 42486500282](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42486500282)
**SUCCESS**, dokončen13:51:06UTC, **import=false / UAT=false**.
[Metadata a kroky](deploy-summary.json). Znovu2392testů/53289assertions PASS,
úplná záloha **389 tabulek /2triggery** ověřená před aktivací. Migrace current,
pending[], dosavadní VS beze změny. Bez nové migrace/DDL tohoto řezu.
Aktivace, standardní HTTP smoke, kontrola soukromého klíče a odstranění vlastních
diagnostických souborů PASS. Žádná nová služba, role ani automatizace aktivovaná.

Stávající zdravotní krycí index z předchozího vydání přítomný
[před](index-preflight/medical-index-before.json) i
[po](index-preflight/medical-index-after.json). Pouze metadata/READ ONLY,
žádné klinické řádky ani DDL v diagnostice.

## Výsledky a provozní meze

Syntetické nativní MariaDB10.3/11.4 každá3testy/5006assertions PASS;
všechny stránky rekonstruují přesná data a celkové souhrny původní historie.
Na10k trénincích celé čtení2733→38ms /1850→39ms,
22→4SQL,140000→10350vrácených řádků. Bohatý skutečný GET1002tréninků
398→16ms a8,41MB→253kB,408časovanýchHTTP/12negative/78Chrome a interakce PASS.
Jediný výsledek má nativní režii~0,4ms; nejde o produkční SLA.
Plné detaily/účastníci pouze25tréninků, celá historie dostupná stránkami;
kanonické historické filtrování před stránkou a úplné souhrny zachované.

[Veřejné GET před](before.json) a [po](after.json): **5/5HTTP200 a celý normalizovaný
DOM i veřejná pole shodná** (katalog, cena, stránka2, hledání a prázdný výsledek).
[Obě historie před](anonymous-history-before.json) a
[po](anonymous-history-after.json): **2/2HTTP302/login** v obou fázích.
Jen anonymní GET bez cookies/POST/přesměrování; žádné rawHTML, session či klinická data
v Gitu. **Přihlášené produkční UAT správních historií NOT RUN**.
Předchozí čtecí UAT rolí/pickeru a následná přejímka mobilního plánovače jsou samostatné.

Původní checkout zůstává na `codex/fio-auto-reconciliation-recovery`;
WIP `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
Cizí větve/worktrees/nezařazené výstupy zachované.
Finální bezpečné důkazy a aktuální stav sdílí dokumentační větev
`codex/training-history-release-proof-20261108`; po jejím merge další deploy není potřeba.
Další AJAX/profilové historie, fyzický mobil/souběh a celé K0.2 zůstávají otevřené.
