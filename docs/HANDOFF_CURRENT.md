# Aktuální session handoff

Aktualizováno 7. 11. 2026, Europe/Prague. Stručný bod převzetí pro všechny vývojáře.
[CURRENT_STATE](CURRENT_STATE.md) vlastní release/flags; [registr](WORK_PLAN_CURRENT.md)
vlastní otevřené úkoly. Plány, koncepty a rozhodnutí jsou ve verzovaných souborech,
které při pokračování znovu načti. Chat ani interní paměť nejsou projektovou evidencí.

## Poslední práce a další krok

- P15: [čtecí ČSC/Sportsoft, UCI ID a nonstop stanice](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md).
  Plán/podklady hotové v [PR #371](https://github.com/KovoPraha/evidenceTreninku/pull/371); dále S0, implementace a aktivace NOT RUN.

- ROLE01-MOB ROZPRACOVÁNO: [plán](ACCESS_OVERVIEW_2026-11-07.md); vlastník schválil karty, deploy a čtecí vizuální kontrolu.
  Navazuje na [zjištění ROLE01-VIS](verification/access-visual-20261107/RESULTS.md), role nikomu neměnit.
  Vlastní větev `codex/access-visual-uat-20261107` / [PR #361](https://github.com/KovoPraha/evidenceTreninku/pull/361); karty zahrnuje vydání `c70939d4`; dále cílený nový Chrome, viz priorita 1.

- ROLE01 HOTOVO: [plán](ACCESS_OVERVIEW_2026-11-07.md), [produkční protokol](verification/access-release-20261107/RESULTS.md).

- DOC03: [priority a závislosti](WORK_PLAN_CURRENT.md#priority-a-závislosti); [DOC02 audit](PLAN_CATALOG_AUDIT_2026-11-07.md) a [úplný katalog](PLAN_CATALOG.md).

- P11 picker: implementace/místní měření/UAT HOTOVO, **bez našeho deploye**; [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
  [finální výsledky](verification/attendance-person-picker-20261107/final/RESULTS.md), [předání / PR #366](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md).
  1,37 MB → 92 kB, Chrome trenér 4,79 → 0,59 s; obě rozepsané části, JS/noJS, role a zápisy PASS.
  Větev `codex/attendance-person-picker-20261107`, finální CI/merge u PR; runtime uklizený, WIP zachovaný.
  Převzaté [lekce #367 nasazené jiným vláknem](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md); celé HTTP společné verze dosud nepřeměřené.
  [Původní HTTP #363](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md) je historický base; dále R13/R05/rows examined/mobil/souběh.
  R13/R05/R06 #354 zahrnuje ROLE01; [katalog #364 a cizí vydání](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md) zachované. K0.2 otevřené.
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
## Ranní serverový výsledek PASS

[Ranní serverový výsledek PASS](verification/membership-scheduler-20261107/RESULTS.md).
Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.
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
