# Aktuální session handoff

Aktualizováno 7. 11. 2026, Europe/Prague. Stručný bod převzetí pro všechny vývojáře.
[CURRENT_STATE](CURRENT_STATE.md) vlastní release/flags; [registr](WORK_PLAN_CURRENT.md)
vlastní otevřené úkoly. Plány, koncepty a rozhodnutí jsou ve verzovaných souborech,
které při pokračování znovu načti. Chat ani interní paměť nejsou projektovou evidencí.

## Poslední práce a další krok

- ROLE01: [plán a oprávnění](ACCESS_OVERVIEW_2026-11-07.md), [PR #356](https://github.com/KovoPraha/evidenceTreninku/pull/356).
  Přehled a místní ověření hotové; následně schválený deploy a formulářový test
  jen na nových technických účtech. Původní role lidí zachovat a porovnat otisk.
  CI kódu prošlo; po převzetí #357 znovu CI/merge, baseline, deploy, ověření a úklid.
- DOC01: [zadání, audit, plán a ověření](DOCUMENTATION_GOVERNANCE_2026-11-07.md).
  Obsah a místní kontroly hotové; [GitHub předání #357](https://github.com/KovoPraha/evidenceTreninku/pull/357), stav CI/merge u PR.
  Větev `codex/repo-documentation-policy-20261107`, vlastní worktree stejného tématu
  v `C:/Users/Marek/Documents/Codex/evidencePavel-repo-documentation-policy-20261107`.
- P11: R13 #352, R05 #353 a R06 #354 jsou v main `feb8fe8c`, bez nového deploye.
  [Předání R06](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md),
  [R05](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md),
  [R13](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md).
  Dále plné HTTP/bootstrap/nav, další role a propojená docházka podle P11.
  Nově [plán klubových cen](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md) a
  [předání cen](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md):
  PR #358, implementace/parita hotové, 500 produktů 2000 → 4 SQL, bez deploye.
- [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355): otevřená
  syntetická přejímka jednorázového kroužku, v kontrolovaném main dosud není.
  Před integrací zkontrolovat konflikt sdílené dokumentace a převzít věcné výsledky.
- P02/P04: [BAR předání](SESSION_HANDOFF_BAR_2026-11-06.md),
  [účetní adaptér](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md); publikaci neopakovat.
- P05: [serverové předání](SESSION_HANDOFF_SERVER_MEMBERSHIP_AUDIT_2026-11-06.md)
  a [ranní plán](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
  Výsledek nepředpokládat z data; číst důkaz. Místní úloha zůstává PAUSED.

<!-- membership-morning-publish:start -->
## Ranní audit 7. 11. — čeká na skutečný termín

Ranní výsledek **NOT RUN**. Hosting jej zachytí od 05:30 UTC (06:30 CET),
serverový přenos doplní protokol a tento handoff přes připravený
[výsledkový PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347).
PC ani místní Codex nejsou potřeba. [Přesný postup a stav](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
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
