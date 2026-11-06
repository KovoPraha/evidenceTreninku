# Denní plán členství — produkční přechod 6. 11. 2026

Zadání vlastníka: opravit opožděné automatické spouštění. Skutečné sjednání a
platba P05/P07 a export P06 zůstávají odložené. Žádná testovací členství,
falešné doklady, bankovní pohyby ani rozesílka kvůli dokazování plánu.
[Plán](../../MEMBERSHIP_SCHEDULER_2026-11-06.md),
[runbook](../../MONTHLY_MEMBERSHIP_RUNBOOK.md#hostingový-denní-plán),
[registr](../../WORK_PLAN_CURRENT.md), [handoff](../../HANDOFF_CURRENT.md).

## Implementace a kontrola

[PR #341](https://github.com/KovoPraha/evidenceTreninku/pull/341) sloučen jako
`c55b1615885c9e8753bb334d95f06119022963cd`. Testovaný head
`023922e2f3cdedac135055cd02fe81e56ade00b5` a sloučený strom jsou shodné.
Všech [11 kontrol](ci-checks.json) PASS, [CI 42239600567](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42239600567).
Linux 2290 testů / 42246 assertions, skutečné MariaDB 10.3/11.4 ověřily i
odmítnutí souběžného workeru a uvolnění zámku po výjimce; [výpis](ci-evidence.txt).
UTC hranice 05:23, přechody letního času, zamezení opakování po denním úspěchu,
10min retry po chybě/pádu, disabled, souběh, cizí soubory a unixové symlinky PASS.
Místně cílené 30 testů / 155 assertions, 1 Windows skip; plné místní Composer
běhy ukončil výchozí 300s limit. Úplný důkaz pochází z Linux CI.
Tři shellové bloky nového workflow prošly Bash syntax kontrolou.

## Nasazení a provozní důkazy — PASS

- [Preflight 42240286470](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42240286470)
  čtecí PASS; [stav před](pre-impact.json).
- [Chráněný deploy 42240723957](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42240723957)
  SUCCESS na přesném sloučeném commitu; UAT/import false. Celá sada 2290/42246,
  syntax a HTTP 200 PASS, nové migrace žádné, pending [], 8 dosavadních VS shodných.
  [Souhrn](deploy-summary.json), [vybrané výsledkové řádky](deploy-evidence.txt).
- Ověřená DB záloha `evidence_2026-11-06_151340_7cc7f091.sql.gz`: 387 tabulek /
  2 triggery, 6 295 057 komprimovaných bajtů, hash
  `36be6af8f00bf8aa42c60544e09d3dfd0b411ee12034160afdf573b611f89edf`.
  Obnova této konkrétní nové generace NOT RUN; předchozí tři jsou doložené v P14.
- [Status před instalací 42241346665](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42241346665)
  [potvrdil](status-before.json) přesný nasazený worker a obě knihovny podle SHA,
  soukromou automatizaci/poštu true a úspěch dneška 13:05:33 UTC. Cron ještě
  chyběl, heartbeat se čtením nevytvořil.
- [Instalace 42241457565](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42241457565)
  SUCCESS, [installed=true / changed=true](install.json). Zápis jediného vlastního
  souboru do existující CRON.1minuta; konfigurace aplikace, členství i zprávy zachované.
- První [čtecí status 42241540735](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42241540735)
  měl ještě [prázdný stav](status-after-install.json), přirozený běh teprve čekal.
  Potom [42241711707](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42241711707),
  [42242051266](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42242051266) a
  [42242268347](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42242268347)
  doložily přirozené tiky **15:18:02, 15:20:04 a 15:21:02 UTC**, poslední interval
  **58 s**. [První](status-first-tick.json), [druhý](status-second-tick.json),
  [třetí stav](status-third-tick.json): source/čerstvost true, outcome=not_due,
  completed_today=true, daily_due=false, bez last_attempt_at. Dnešní denní
  úspěch 13:05:33 UTC shodný; worker se instalací znovu nespustil.
- Teprve po důkazu skutečného ticku byla repo proměnná
  `KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED` změněna na false. APPLY/MAIL zůstaly
  true; [stav proměnných](github-variables.json). Ruční workflow zůstává dostupné.
- [Čtecí postflight 42241933037](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42241933037)
  PASS; [stav po](post-impact.json). Hash konfigurace, kontrolované integrační
  flags, canonical-write stav a počet migračního ledgeru před/po shodné.
  Živé metadata potvrzují přesné SHA vydání; [porovnání](release-comparison.json).

Historický 44souborový manifest auditu není úplný manifest dnešní aplikace.
Doménové readiness=false se týká dřívějších provozních podkladů, nikoli chyby
scheduleru. Vydání dokládají přesné SHA, shoda otestovaného/sloučeného stromu,
chráněný deploy, živá metadata a nové SHA kontroly workeru/služeb ve statusu.

## Co zůstává odložené nebo budoucí

P05/P07: skutečné sjednání, platba, PDF a doručení vlastník odložil. P06: nový
export a jeho rozdílový sync vlastník odložil. Žádný z těchto bodů se neuzavírá
prázdným automatickým během ani testy nad syntetickými daty.

Denní brána a DST prošly testy; hosting skutečně spouští nasazený kód a eviduje
čerstvé tiky. Příští ranní zpracování **7. 11. v 05:23 UTC (06:23 CET)** je
budoucí událost a dnes se neoznačuje za proběhlé. Čas se kvůli testu neměnil,
ruční apply/tick pro vytvoření důkazu se nepoužil. Při zdravém minutovém cronu
se denní běh otevře prvním tickem po nastaveném čase; po výpadku se dožene po
obnovení hostingu. Absolutní dostupnost hostingu ani dlouhodobou dochvilnost
nelze dokázat jedním odpoledním pozorováním.

Původní WIP `kis_rosters_admin.php` zachovaný (kontrolní hash
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`),
cizí worktree ani soubory se nečistily. [Session handoff](../../SESSION_HANDOFF_MEMBERSHIP_SCHEDULER_2026-11-06.md)
a [aktuální stav](../../CURRENT_STATE.md) obsahují další postup; výsledkové
předání samo neprovádí další deploy ani nový apply.
