# Ranní audit členství 7. 11. 2026 — na serveru

Stav: **SERVER PŘIPRAVEN**, ranní výsledek zatím **NOT RUN**.

Vlastník požádal kontrolu bez zapnutého počítače a zápis výsledku přímo do
repozitářové dokumentace i handoffu. Skutečné P05/P07 a export P06 dále odložené.

## Automatický postup bez PC

1. Hlavní členství dál zpracovává hostingový minutový cron, denně **05:23 UTC
   (06:23 CET)**. Jeho schválené flags a čas se při této změně neměnily.
2. Samostatný jednorázový pozorovatel v existujícím CRON.1minuta od
   **7. 11. 05:30 UTC (06:30 CET)** pouze přečte stav a provozní ledger.
   V soukromém data/.kis-deploy uchová první JSON, Markdown protokol a
   souborový handoff. Po zápisu odstraní jen svůj ověřený cron.
3. GitHub workflow membership-morning-audit-production.yml má přenosové termíny
   **05:35, 06:35 a 12:35 UTC** téhož dne. Zpoždění přenosu nemění již zachycený
   čas na hostingu. Nic nepřehrává v účtování.
4. Výsledek automaticky doplní [PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347),
   aktuální stav, registr a hlavní/session handoff. Bot ověří povolené cesty
   a SHA, podporovaným Actions API povolí pouze tři známé PR CI běhy tohoto
   dokumentačního commitu, vyčká na jejich úspěch a sloučí bez admin bypassu.
5. Po publikaci se jednorázový workflow vypne. Místní úloha
   rann-audit-lenstv-7-11 je **PAUSED**, počítač může zůstat vypnutý.

## Kritéria a hranice

Skutečný start se bere z úspěšného operational_job_runs.started_at, navázaného
na hostingový state.last_attempt_at (tolerance návaznosti nejvýše 2 s).
Odchylka se počítá od **2026-11-07 05:23:00 UTC**, nikoli z heartbeat nebo času
dokončení. Nad 60 s je zpoždění. Chybějící pokus, nesoulad zdrojů, starý úspěch,
chybné flags, chyby workeru, retry či neúspěšné joby nemohou vydat nepodložený
PASS. Zapisuje se PASS/LATE/FAIL/BLOCKED, UTC/CET a přesná odchylka.

Vyžaduje se správný zdroj hlavního cronu, čerstvý tick, novější úspěch po
skutečném startu, completed_today=true, daily_due=false a výsledek bez errors.
Prázdné správně zpracované členství může projít; neprokazuje platbu/PDF/doručení.
SQL běží READ ONLY a před termínem pozorovatel DB vůbec neotvírá. Při chybě
čtecího kontextu uchová FAIL. JSON se nepřepisuje opakováním; po pádu lze
opravit jen odvozené poznámky ze stejného dokladu.

Žádný ruční tick/apply/send, změna času, falešná členství/předpisy, platba nebo
testovací pošta kvůli důkazu. Cizí crony a data se neodstraňují. P05/P07 a P06
obnovit až podle nového pověření vlastníka.

## Doložená příprava

[Úplný protokol](verification/membership-morning-server-release-20261106/RESULTS.md):
#346 nasazené, přirozený čekající tick PASS, #350 opravilo podmínku PR CI.
Server sám sloučil [preflight #348](https://github.com/KovoPraha/evidenceTreninku/pull/348)
jako github-actions[bot], všech 11 kontrol PASS. Config a integrační flags
před/po shodné; místní úloha ověřeně PAUSED. Ranní audit samotný ještě nenastal.

[Runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md#jednorázová-serverová-kontrola-7-11),
[serverový handoff](SESSION_HANDOFF_SERVER_MEMBERSHIP_AUDIT_2026-11-06.md),
[registr P01–P14](WORK_PLAN_CURRENT.md), [aktuální předání](HANDOFF_CURRENT.md).

Historie: původní místní plán #344, přesun/implementace #346, oprava CI #350,
přejímka #348. Schvalují se CI běhy dokumentačního commitu, nikoli PR review
nebo výjimka z ochrany hlavní větve.

<!-- membership-morning-publish:start -->
## Ranní audit 7. 11. — čeká na skutečný termín

Ranní výsledek **NOT RUN**. Hosting jej zachytí od 05:30 UTC (06:30 CET),
serverový přenos doplní protokol a tento handoff přes připravený
[výsledkový PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347).
PC ani místní Codex nejsou potřeba. [Přesný postup a stav](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
Skutečné P05/P07 a export P06 zůstávají odložené.
<!-- membership-morning-publish:end -->
