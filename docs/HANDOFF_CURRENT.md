# Aktuální session handoff

Aktualizováno 7. 11. 2026, Europe/Prague. Stručný bod převzetí pro všechny vývojáře.
[CURRENT_STATE](CURRENT_STATE.md) vlastní release/flags; [registr](WORK_PLAN_CURRENT.md)
vlastní otevřené úkoly. Plány, koncepty a rozhodnutí jsou ve verzovaných souborech,
které při pokračování znovu načti. Chat ani interní paměť nejsou projektovou evidencí.

## Poslední práce a další krok

- P11-SCALE HOTOVO: [větší R13/R05](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md),2700HTTP/90parit.
  Aktuální#374 také prověřené; oba vlastní runtime úklidy PASS, produkce bez našich změn.
  R13 stavový řez nyní uzavírá P11-R13-S; dále fyzický mobil/souběh, K0.2 otevřené.
- ROLE01-MOB ROZPRACOVÁNO: [plán](ACCESS_OVERVIEW_2026-11-07.md); vlastník schválil karty, deploy a čtecí vizuální kontrolu.
  Navazuje na [zjištění ROLE01-VIS](verification/access-visual-20261107/RESULTS.md), role nikomu neměnit.
  Vlastní větev `codex/access-visual-uat-20261107` / [PR #361](https://github.com/KovoPraha/evidenceTreninku/pull/361); karty zahrnuje vydání `c70939d4`; dále cílený nový Chrome, viz priorita 1.
- ROLE01 HOTOVO: [plán](ACCESS_OVERVIEW_2026-11-07.md), [produkční protokol](verification/access-release-20261107/RESULTS.md).
- AUTO01 HOTOVO: [plán a výsledky 1/7/8](AUTONOMOUS_PRIORITIES_1_7_8_2026-11-07.md), P14 389 tabulek / 2 triggery PASS; inventura WIP bez mazání. [Katalog](PLAN_CATALOG.md).

- P11 picker HOTOVO: [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání #366/#372](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md).
  [Produkční protokol](verification/attendance-picker-release-20261107/RESULTS.md): schválené nasazení a čtecí kontrola dokončené, neopakovat.
  **Přihlášené produkční UAT pickeru BLOKOVANÉ**: Windows nástroj neověřil aktuální URL; [neprovedené scénáře a další krok](verification/attendance-picker-production-readonly-uat-20261107/RESULTS.md).
  Původní WIP a oba picker worktrees zachované; výsledky a kalibrace jsou v repu, nové aktivace žádné.
  [Lekce #367](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md) a [katalog #364](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md) zahrnuté.
  [Nový Windows pár /9 scénářů/234Chrome](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md) HOTOVO: trenér6,40 →0,35s, SQLlekce203 →72.
- P11-R13-S HOTOVO: [plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md), [provozní důkaz](verification/medical-state-release-20261107/RESULTS.md).
  [Ověření původních sad](verification/medical-valid-scale-recheck-20261107/RESULTS.md), bez deploye; #381/938e9c50, deploy42405912779 SUCCESS; další deploy neopakovat. WIP zachovaný, přihlášené UAT NOT RUN.
- P11-R05-H HOTOVO: [plán](PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md).
  #374 v main a nasazeno; [provozní důkaz](verification/planner-hierarchy-release-20261107/RESULTS.md) PASS. WIP zachovaný; R13/K0.2 a přihlášené UAT otevřené.
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
