# Aktuální session handoff

Aktualizováno 7. 11. 2026, Europe/Prague. Stručný bod převzetí pro všechny vývojáře.
[CURRENT_STATE](CURRENT_STATE.md) vlastní release/flags; [registr](WORK_PLAN_CURRENT.md)
vlastní otevřené úkoly. Plány, koncepty a rozhodnutí jsou ve verzovaných souborech,
které při pokračování znovu načti. Chat ani interní paměť nejsou projektovou evidencí.

## Poslední práce a další krok

- ROLE01-MOB ROZPRACOVÁNO: [plán](ACCESS_OVERVIEW_2026-11-07.md); vlastník schválil karty, deploy a čtecí vizuální kontrolu.
  Navazuje na [zjištění ROLE01-VIS](verification/access-visual-20261107/RESULTS.md), role nikomu neměnit.
  Vlastní větev `codex/access-visual-uat-20261107` / [PR #361](https://github.com/KovoPraha/evidenceTreninku/pull/361); sloučené `3b37e053`; dále schválený deploy a nový Chrome.

- ROLE01 HOTOVO: [plán](ACCESS_OVERVIEW_2026-11-07.md), [produkční protokol](verification/access-release-20261107/RESULTS.md).

- DOC02 [PR #360](https://github.com/KovoPraha/evidenceTreninku/pull/360): [audit](PLAN_CATALOG_AUDIT_2026-11-07.md), [úplný katalog](PLAN_CATALOG.md); koncepty C01–C04 v registru.

- P11 R06 výběr osob: implementace/místní ověření HOTOVO, **bez deploye**.
  [Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [výsledky](verification/attendance-person-picker-20261107/RESULTS.md),
  [předání a další kroky](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md), [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366).
  1,37 MB → 92 kB, Chrome trenér 5,23 → 1,11 s; JS/noJS, role, draft, zápisy a revokace PASS.
  Větev `codex/attendance-person-picker-20261107`, vlastní worktree viz předání; finální CI/merge u PR.
  Runtime uklizený, WIP zachovaný. Dále lekce 219 SQL, R13/R05, mobil/souběh; K0.2 otevřené.
  [Předchozí celé HTTP #363](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md) v main; R13/R05/R06 #354 zahrnuje ROLE01.
  Klubové ceny #358 v main bez dalšího deploye; [katalog #364](https://github.com/KovoPraha/evidenceTreninku/pull/364) vede vlastní větev.
- P07 N1/N2: [automatické doklady a zprávy](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md),
  [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355), [důkazy](verification/shop-document-automation-20261107/RESULTS.md).
  #355 sloučeno a nasazeno, 2337 testů / 43257 assertions a 11 CI PASS; pověření 1 zapnuté.
  Browser blokovaný; skutečná banka a externí schránka zůstávají neověřené.
- P02/P04: [BAR předání](SESSION_HANDOFF_BAR_2026-11-06.md),
  [účetní adaptér](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md); publikaci neopakovat.
- P05: [serverové předání](SESSION_HANDOFF_SERVER_MEMBERSHIP_AUDIT_2026-11-06.md)
  a [ranní plán](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
  Výsledek nepředpokládat z data; číst důkaz. Místní úloha zůstává PAUSED.

<!-- membership-morning-publish:start -->
## Ranní audit 7. 11. — čeká na doložený výsledek

Termín 05:30 UTC již uplynul; výsledek **NEDOLOŽEN v Gitu**.
Nejde o důkaz, že server neběžel. Výsledek má doplnit
[výsledkový PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347).
PC není potřeba. [Přesný postup a stav](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
Skutečné P05/P07 a export P06 zůstávají odložené.
<!-- membership-morning-publish:end -->

<!-- membership-morning-preflight:start -->
## Serverový preflight

[Serverový preflight](verification/membership-morning-server-20261106/RESULTS.md).
Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.
Skutečné P05/P07 a export P06 zůstávají odložené.

<!-- membership-morning-preflight:end -->

## Otevřené hranice a ostatní plány

P01 dokončené v potvrzeném rozsahu; instalaci ani rozesílku neopakovat.
Skutečné P05/P07, nový export P06, heslo a T07 vlastník odložil.
P02/P04 potřebují skutečné podklady/přejímku a účetní adaptér; P08/P09 lidské
převzetí, P12 konkrétní testerku a přístup, P13 výběr událostí.
Úplné další kroky, závislosti a tematické soubory všech P01–P14 vede
[jediný registr](WORK_PLAN_CURRENT.md). Plán nenahrazuje oprávnění k produkční změně.

## Povinné převzetí a zápis

1. Ověř `git status --short --branch`, `git fetch --prune origin`, HEAD a main.
   Cizí WIP zachovej; při starém checkoutu použij vlastní větev/worktree z main.
2. Načti [AGENTS](../AGENTS.md), [pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md),
   CURRENT, registr a příslušný plán; při rozhodování i jeho záznam v logu.
3. Už při zahájení/změně ulož koncept/plán do `docs/`, aktualizuj registr,
   tento handoff a CURRENT odkazy. Během práce zapisuj výsledky a další krok.
4. Před předáním zapiš ověření, omezení, stav PR/merge/deploy a událost do
   [deníku](EVENT_LOG.md); významná rozhodnutí do [logu rozhodnutí](DECISION_LOG.md).
   Dokumenty commituj spolu se změnou a předej přes GitHub PR. Lokální soubor není sdílený.

Historie: [deník](EVENT_LOG.md) → [rozhodnutí a uzavřené plány](DECISION_LOG.md)
→ [archiv tohoto předání](HANDOFF_ARCHIVE_2026-11-07.md) a
[provozní archiv](CHANGELOG-PROVOZ.md). Historii sem nepřidávat; limit 80 řádků.
