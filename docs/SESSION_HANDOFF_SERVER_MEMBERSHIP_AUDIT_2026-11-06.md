# Session handoff: serverová ranní kontrola — 6. 11. 2026

Pověření vlastníka: audit členství 7. 11. bez zapnutého počítače a uložení
výsledku přímo do repozitářové dokumentace/handoffu. Skutečné P05/P07 a export
P06 dále výslovně odložené.

Hotovo: #346 sloučené a chráněně nasazené, pozorovatel instalovaný a přirozeně
čeká. #350 aktivovalo správnou cestu schválení PR CI po ověření pouze vlastních
dokumentačních cest a SHA. Celý serverový preflight #348 se sám sloučil po
všech 11 kontrolách jako github-actions[bot], bez klientského Codexu a bypassu.
Místní úloha rann-audit-lenstv-7-11 je PAUSED. Nový deploy kvůli dokumentaci
nebo GitHub skriptu není potřeba.

[Release a záloha](CURRENT_STATE.md),
[důkazy](verification/membership-morning-server-release-20261106/RESULTS.md),
[plán](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md),
[runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md#jednorázová-serverová-kontrola-7-11),
[registr](WORK_PLAN_CURRENT.md), [hlavní handoff](HANDOFF_CURRENT.md).

## Další automatické kroky

7. 11. 06:23 CET hlavní zpracování, od 06:30 CET read-only audit, od 06:35 CET
vyzvednutí a dokumentační předání. Záložní přenosy 07:35 a 13:35 CET, dokud se
publikace nevypne po prvním výsledku. Připravený PR #347 obsahuje zatím NOT RUN
a nesmí se sloučit bez skutečného serverového důkazu. Případné FAIL/LATE také
zapsat; nevyrábět PASS ručním přehráním workeru.

Observer ukládá membership-morning-audit-20261107.json/.md a
membership-morning-handoff-20261107.md do soukromého data/.kis-deploy.
CI/přenos nesmějí přepsat původní čas zachycení. Po běhu má zmizet pouze vlastní
pozorovací cron; hlavní denní členství dál pokračuje.

Kopie: C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106,
vývoj #346, oprava #350, přejímka #348. Závěrečné předání vede větev
codex/membership-server-audit-release-20261106. Původní WIP kis_rosters_admin.php
v C:/xampp/htdocs/evidencePavel zachovaný; cizí kopie a BAR výstupy se nečistily.
