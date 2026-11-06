# Session handoff: denní členství — 6. 11. 2026

Zadání vlastníka: napravit opožděné automatické spouštění a zachovat odložení
skutečných P05/P07 i exportu P06. Původní GitHub cron vytvořil běh až o 7 h 42 min
po čase. Oprava nemění schválenou denní hodinu 05:23 UTC.

Technická část hotová: #341 sloučené, chráněný deploy PASS, vlastní soubor
v existujícím minutovém cronu instalovaný a skutečné tiky ověřené. Sdílený
DB zámek chrání ruční/automatický worker, retry po chybě má odstup 10 minut.
Dnešní úspěch z ledgeru zabránil novému zpracování při instalaci.
Config i flags zachované; redundantní GitHub schedule false. Ruční workflow
zůstává dostupné. Platba, nové členství, export ani testovací pošta se nedělaly.

Aktuální release/backup: [CURRENT_STATE](CURRENT_STATE.md).
[Úplné výsledky a bezpečné důkazy](verification/membership-scheduler-20261106/RESULTS.md),
[runbook a zastavení](MONTHLY_MEMBERSHIP_RUNBOOK.md#hostingový-denní-plán),
[plán](MEMBERSHIP_SCHEDULER_2026-11-06.md), [registr P01–P14](WORK_PLAN_CURRENT.md),
[hlavní handoff](HANDOFF_CURRENT.md).

Další pozorovatelný denní start je 7. 11. 2026 v 05:23 UTC (06:23 CET).
Jeho skutečný čas a dlouhodobou spolehlivost nelze prokázat dnešními tiky.
Po termínu použít pouze status a zkontrolovat novější denní úspěch, čerstvý
tick a daily_due=false. Nevytvářet heartbeat ručním tickem, neposouvat čas a
nespouštět skutečný apply jen kvůli přejímce. P05/P07 a P06 obnovit až na
pokyn vlastníka s jeho skutečnými podklady.

Vývojová větev `codex/membership-hosting-scheduler-20261106`; dokumentační
předání `codex/membership-scheduler-release-20261106`, izolovaná kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`.
Původní `C:/xampp/htdocs/evidencePavel` a jeho WIP `kis_rosters_admin.php`
zachované; žádný reset nebo úklid cizích kopií.

## Navazující pověření vlastníka: ranní audit

Aktivní jednorázová kontrola `rann-audit-lenstv-7-11` je naplánovaná na
**7. 11. v 06:30 CET (05:30 UTC)**. [Přesný postup a kritéria](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md)
vyžadují čtecí status a skutečný čas denního pokusu, nikoli pouze heartbeat.
Výsledek zatím NOT RUN. Po kontrole uložit důkazy a aktualizovat související
souborové předání; P05/P07 a export P06 nadále odložené. Místní počítač a
aplikace musí pro toto naplánované vyvolání běžet.

## Změna pověření: bez zapnutého PC

[Serverová náhrada](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md#změna-zadání-kontrola-bez-zapnutého-počítače)
je rozpracovaná. Má pouze pozorovat a automaticky doložit výsledek, bez nového
účtování, pošty, platby nebo exportu. Konkrétní rollout vede tematický plán;
před vypnutím místní úlohy doložit přirozený tick a serverové publikování.

<!-- membership-morning-preflight:start -->
## Serverový preflight

[Serverový preflight](verification/membership-morning-server-20261106/RESULTS.md).
Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.
Skutečné P05/P07 a export P06 zůstávají odložené.

<!-- membership-morning-preflight:end -->
