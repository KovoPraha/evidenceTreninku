# Ranní audit členství 7. 11. 2026

Stav: **PŘEVOD NA SERVER ROZPRACOVANÝ**, ranní výsledek zatím **NOT RUN**. Vlastník 6. 11. výslovně
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


## Změna zadání: kontrola bez zapnutého počítače

Vlastník výslovně požádal přenést kontrolu na server. Starší popis místní úlohy
výše je výchozí stav, nikoli cílové řešení. Větev
`codex/membership-server-morning-audit-20261106`, izolovaná kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`.

Plán provedení:

1. Přidat samostatný jednorázový pozorovací cron na hostingu. Dne 7. 11. od
   05:30 UTC pouze přečte stav členství a provozní ledger, atomicky uchová
   neměnný JSON a souborový protokol/handoff mimo webroot a odstraní jen svůj
   přesně ověřený spouštěč. Nikdy nevolá worker, apply/send nebo skutečnou platbu.
2. Otestovat čekání do termínu, skutečný denní pokus oproti heartbeat, pozdní/
   chybějící/chybný běh, retry, souběh, symlinky, neměnnost a vlastní úklid.
3. Po CI sloučit a chráněně nasadit, instalovat pozorovatele a doložit jeho
   přirozený čekající tick. Čas kontroly se kvůli dnešnímu testu neposouvá.
4. Serverový GitHub workflow vyzvedne již zachycený výsledek a aktualizuje
   dokumentaci/handoff přes předem založený dokumentační PR. Bot nemá vytvářet
   nové PR ani obcházet ochrany main; pro svůj commit výslovně vyvolá povinné CI
   a sloučí až při shodě SHA a zelených kontrolách. Přenos se může opozdit,
   ale časový důkaz zůstane uložený na hostingu.
5. Stejnou publikaci nejprve skutečně ověřit na dnešním čtecím preflightu,
   nikoli na předstíraném ranním výsledku. Potom pozastavit místní úlohu
   `rann-audit-lenstv-7-11` a aktualizovat všechny navazující odkazy/stavy.

P05/P07 se skutečnou platbou a export P06 dál výslovně odložené. Výsledek
ranního auditu před skutečným termínem zůstává NOT RUN. Stávající čas účtování
05:23 UTC i soukromé příznaky automatizace/pošty se nemění.

### Průběžné ověření implementace

PHP plánovač/pozorovatel/dokumentační kontrakt: 21 testů / 104 assertions PASS,
2 místní Windows skipy unixových hranic; Python publikace 4 testy PASS.
Cílený PHPStan, syntax PHP a pěti Bash bloků PASS. Runtime účtování se nemění.
Pozorovatel má read-only SQL transakce, před termínem neotvírá DB a při chybě
čtecího kontextu uchová FAIL; doklad se znovu nepřepisuje. Přenos má tři
příležitosti 7. 11. (05:35, 06:35, 12:35 UTC) a po publikaci se vypne.
Ranní capture se neopakuje a případné LATE/FAIL se mají také zapsat, nikoli
vyrobit úspěch ručním spuštěním členství.

### Ověřená oprava předání CI

První skutečný preflight 42266360997 vytvořil dokumentační commit a explicitní
CI 42266455451 úspěšně dokončilo všech pět jobů. Merge byl přesto blokovaný:
GitHub požaduje zvlášť schválení běhů události PR, které vyvolal GITHUB_TOKEN.
Nejde o selhání hostovacího pozorovatele nebo o potřebu jiného časovače.

Podporované Actions API pro schválení těchto běhů bylo skutečně ověřené
jobem membership-approval-probe v 42268437771. Je dovoleno tokenem s actions:write;
pravidla main ani schvalování PR review se nemění. Publikace smí takto povolit
pouze vlastní botem zapsaný dokumentační SHA, se stejným PR/repozitářem a
přesně třemi známými workflow, až po ověření povolených cest. Potom vyčká na
skutečné PR testy a slučuje bez admin bypassu. Do úspěšného zopakování plného
preflightu zůstává místní úloha jako záloha.
