# Ranní audit členství 7. 11. 2026

Stav: **NAPLÁNOVÁNO**, výsledek zatím **NOT RUN**. Vlastník 6. 11. výslovně
požádal po ranním běhu ověřit dochvilnost a uložit výsledek do dokumentace i
handoffu. Skutečné P05/P07 a export P06 nadále výslovně odložil.

## Termín a návaznost

Jednorázový audit je naplánovaný na **7. 11. 2026 v 06:30 CET (05:30 UTC)**,
sedm minut po nastaveném začátku denního zpracování. Aktivní navazující úloha
Codexu `rann-audit-lenstv-7-11` se vrací do tohoto chatu a má právě jedno
naplánované vyvolání; nejde o nový plán účtování ani čekání na úspěch produkční
úlohy. Místní počítač musí být zapnutý a aplikace běžet. Při pozdějším spuštění
zaznamenat i skutečný čas auditu a nadále posuzovat konkrétní den 7. 11.

[Oprava a nastavený čas](MEMBERSHIP_SCHEDULER_2026-11-06.md),
[nasazení a odpolední tiky](verification/membership-scheduler-20261106/RESULTS.md),
[runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md#hostingový-denní-plán),
[session handoff](SESSION_HANDOFF_MEMBERSHIP_SCHEDULER_2026-11-06.md),
[registr P01–P14](WORK_PLAN_CURRENT.md).

## Přesná čtecí kontrola

1. Načíst aktuální main, instrukce a handoff, zachovat cizí WIP. Výsledky dělat
   na vlastní větvi v izolované kopii, nikoli nad původním rozpracovaným
   `C:/xampp/htdocs/evidencePavel/kis_rosters_admin.php`.
2. Chráněné `membership-scheduler-production.yml` z main: pouze `action=status`,
   `potvrzeni=OVERIT-CLENSTVI-CRON`. Dokončení této čtecí operace ověřit v témže
   vyvolání auditu; bezpečný JSON a Action ID uložit do
   `docs/verification/membership-scheduler-20261107/` až při skutečné kontrole.
3. Skutečný pokus určit z `state.last_attempt_at`, nikoli z minutového
   `last_tick_at` nebo samotného času dokončení. Porovnat s **2026-11-07
   05:23:00 UTC**, uvést UTC/CET i přesnou odchylku v sekundách. Odchylku nad
   60 s výslovně označit jako zpoždění; při retry nedokazovat první dochvilný
   start jen časem posledního pokusu. Pokud první pokus nelze doložit, uvést
   omezení namísto nepodloženého PASS.
4. Vyžadovat nový úspěch po ranním pokusu (`last_success_utc`), výsledek bez
   errors a přirozený `hosting_cron` stav. `installed_expected_source`,
   `recent_hosting_tick` a `completed_today` musí být true, `daily_due` false.
   Dnešní úspěch před časem nebo jiný ruční běh nedokazuje hostingový start.
   Při chybě či chybějícím běhu zapsat LATE/FAIL/BLOCKED a skutečný důvod.
5. Čtecím způsobem ověřit repo schedule false a zachované povolení
   automatizace/pošty. Nic nepřepínat. Prázdné zpracování může správně projít,
   ale neuzavírá přejímku skutečné platby, PDF nebo doručení.

## Zápis výsledku a konec úlohy

Uložit datovaný protokol a bezpečné důkazy, doplnit CURRENT_STATE do 80 řádků,
hlavní/session handoff, tento plán a registr P05. Propojit nové důkazy a
zachovat starší historii. Ověřit dokumentační kontrakt, odkazy a diff, stage
jen vlastní soubory a dokumentační PR sloučit po povinných kontrolách.
Nasazení aplikace samotná dokumentace nepotřebuje.

**Zakázané kroky tohoto auditu:** install, ruční tick/apply/send, posun času,
falešná členství/předpisy nebo testovací zprávy. Neměnit produkci ani tarify.
P05/P07 se skutečnou platbou a export P06 neobnovovat. Neplánovat další
opakování po dokončení jednorázové kontroly; při neúspěchu doložit stav a
upozornit v chatu, nepřehrávat běh kvůli vytvoření důkazu.

Příprava: větev `codex/membership-morning-audit-plan-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`.
