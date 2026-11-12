# Aktuální session handoff
MAKEUP-AUTO-01: vlastník 12. 11. požádal nasadit zelený [PR #459](https://github.com/KovoPraha/evidenceTreninku/pull/459) s vypnutou automatikou; [plán vydání](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#makeup-auto-01--automatické-cíle-a-termíny-12-11-2026), [protokol](verification/makeup-auto-20261112/RESULTS.md). Další krok merge, chráněný deploy a kontrola receipt/migrace/HTTP. Rolové UAT a potvrzení věku, pravidel a kapacit otevřené. [Registr](WORK_PLAN_CURRENT.md#makeup-auto-01--automatické-náhradní-lekce-12-11-2026).
Aktualizováno 12. 11. 2026, Europe/Prague. [TESTER-20261110](verification/tester-orders-registration-20261110.md) #438 a [COACH-ROSTER-01](COACH_GROUP_ROSTERS_2026-11-10.md) #436 nasazeny jako `4e3df207` ve [workflow 42852365591](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42852365591) SUCCESS; následný #439 nasadil runtime `8182489e` ([workflow 42855987339](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42855987339) SUCCESS). [Technické výsledky a meze](verification/coach-group-rosters-20261110/RESULTS.md); přihlášené UAT a skuteční Aligátoři otevření.
[CURRENT_STATE](CURRENT_STATE.md) vlastní release/flags; [registr](WORK_PLAN_CURRENT.md) vede i UX01 [celoroční nabídky](club-programs.md#ux01--doplnění-celoroční-nabídky-ke-stávajícím-skupinám-8-11-2026) a Q01/Q3
vlastní otevřené úkoly včetně Q2-TIME; plány a rozhodnutí čti ve verzovaných souborech.
Q1 má [částečné přihlášené UAT](verification/quality-uat-20261108/RESULTS.md), Q2 #390 je [nasazené a cíleně ověřené](verification/planner-mobile-release-20261108/RESULTS.md) v deploy42483838259 a následném P11-RH; Q3 #389 ve vydání #385, Q4/Q5 mají [podklad](QUALITY_NEXT_SLICES_2026-11-08.md).
## Poslední práce a další krok
- **Q2-TIME NASAZENO:** #412 / `4f92b225`, deploy42622226704 SUCCESS; [plán](QUALITY_NEXT_SLICES_2026-11-08.md#q2-time--odolný-zápis-tréninku-9-11-2026).
  11 CI / 2409 testů, 6 HTTP a přesný produkční JS PASS; [protokol a scénář testerky](verification/training-form-resilience-release-20261109/RESULTS.md).
  Kanonický protokol uvádí UAT NOT RUN; otevřený [PR #417](https://github.com/KovoPraha/evidenceTreninku/pull/417) dokládá 3/3 přihlášené scénáře bez platného submitu. Je třeba převzít důkaz a dořešit úklid konceptu; žádný skutečný trénink testem nevznikl.
- **P11-RHC2 NASAZENO.** [Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md#p11-rhc2--omezená-agregace-vzorů-kopírování-12-11-2026), [měření](verification/training-copy-20261112/RESULTS.md). #457/87171a67, [deploy 43215069511](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43215069511) SUCCESS, 13 CI. Omezená agregace a původní výběr při remízách. Přihlášené produkční UAT NOT RUN.
- **P11-ASSET8/9/10 NASAZENO:** [plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset8--plánovač-11-11-2026), [plánovač](verification/schedule-bootstrap-20261111/RESULTS.md), [přihlášení](verification/signin-bootstrap-20261111/RESULTS.md), [formuláře](verification/training-form-bootstrap-20261112/RESULTS.md). Deploy 42979420239/43104809135/43164465663 SUCCESS, syntetické MariaDB/Chrome PASS, CSS 64 454 → 31 248 B. [Revize](verification/documentation-sync-20261112/RESULTS.md). Přihlášené produkční UAT NOT RUN.
- **P11-HJS3 NASAZENO:** [plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs3--společný-skript-po-odstranění-duplicitních-stylů-10-11-2026), [předání](SESSION_HANDOFF_SHARED_HEADER_SCRIPT_2026-11-10.md), [měření](verification/shared-header-cache-20261110/RESULTS.md).
  #449/b753f6e7,14CI/native2DB/544Chrome/8pixelpárů PASS; warm gzip−25–30%, cold+955–1264B. [Deploy42920002806](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42920002806) SUCCESS, 2456/56289, UAT/importfalse; živé GET/hash/cache PASS. Doklady[#450](https://github.com/KovoPraha/evidenceTreninku/pull/450), bez redeploye; WIP zachovaný, přihlášené UAT NOT RUN.
- ROLE01-MOB NASAZENO: #361 / `3b37e053`, deploy 42365946802 SUCCESS; [důkazy](verification/access-mobile-cards-20261107/RESULTS.md).
  Čtecí otisky 48 účtů / 82 rolí shodné. Chrome desktop/390/360 px, filtr Bar,
  prázdné hledání a editor deaktivovaného #64 cíleně PASS bez zápisu. [Čtecí protokol](verification/quality-uat-20261108/RESULTS.md).
- ROLE01 HOTOVO: [plán](ACCESS_OVERVIEW_2026-11-07.md), [produkční protokol](verification/access-release-20261107/RESULTS.md).
- AUTO01 HOTOVO: [plán a výsledky 1/7/8](AUTONOMOUS_PRIORITIES_1_7_8_2026-11-07.md), P14 389 tabulek / 2 triggery PASS; inventura WIP bez mazání. [Katalog](PLAN_CATALOG.md).
- P11 picker HOTOVO: [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání #366/#372](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md).
  [Produkční protokol](verification/attendance-picker-release-20261107/RESULTS.md): schválené nasazení a čtecí kontrola dokončené, neopakovat.
  **Přihlášené UAT pickeru částečně PASS**: hledání a stránkování; omluva a výběr/verze otevřené. [Výsledek](verification/quality-uat-20261108/RESULTS.md).
  Původní WIP a oba picker worktrees zachované; výsledky a kalibrace jsou v repu, nové aktivace žádné.
  [Lekce #367](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md) a [katalog #364](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md) zahrnuté.
  [Nový Windows pár /9 scénářů/234Chrome](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md) HOTOVO: trenér6,40 →0,35s, SQLlekce203 →72.
- P11-RHC zahrnuté: [Moje tréninky / plán](COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md), [předání](SESSION_HANDOFF_COACH_HISTORY_2026-11-08.md).
  #400/da152282, deploy42494231010 SUCCESS; původní katalog222→223 a nový5/5stabilní zachované v [historickém protokolu](verification/coach-history-release-20261108/RESULTS.md). UAT samostatné.
- P11-R13-F NASAZENO: #385/519ff098, deploy42477866185 SUCCESS; [protokol](verification/medical-filter-release-20261108/RESULTS.md), index/veřejný DOM/anonym403 PASS; přihlášené UAT nového vydání NOT RUN.
- P11-R05-H zachováno: [předání](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md); nalezené [360px přetékání](verification/quality-uat-20261108/RESULTS.md) opraveno Q2 [#390](https://github.com/KovoPraha/evidenceTreninku/pull/390) a [nasazeno / cíleně čtecí PASS](verification/planner-mobile-release-20261108/RESULTS.md). Oprava je i v následném P11-RH; úplné funkční UAT ostatních rolí otevřené.
- P07 N1/N2: [automatické doklady a zprávy](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md),
  [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355), [důkazy](verification/shop-document-automation-20261107/RESULTS.md).
  #355 sloučeno a nasazeno, 2337 testů / 43257 assertions a 11 CI PASS; pověření 1 zapnuté.
  Browser blokovaný; skutečná banka a externí schránka zůstávají neověřené.
- P02/P04: [BAR předání](SESSION_HANDOFF_BAR_2026-11-06.md), [BAR-DISPLAY-STOCK](BAR_DISPLAY_STOCK_PARITY_2026-11-10.md): TV nasazené, 68 zásob obou skladů převedené včetně 5 záporných stavů, nezávisle 0 rozdílů; 44 URL PASS. [Důkazy](verification/bar-display-stock-20261110/RESULTS.md). Neimportovat znovu; fyzická inventura a lidské/účetní UAT otevřené.
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
