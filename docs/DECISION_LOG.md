# Rozhodovací log a rejstřík uzavřených plánů

Společný vstup pro rozhodnutí napříč projektem. Aktuální stav je v
[CURRENT](CURRENT_STATE.md), otevřené koncepty/plány v [registru](WORK_PLAN_CURRENT.md),
časová posloupnost v [deníku](EVENT_LOG.md). Nová rozhodnutí přidávej s ID
`DYYYY-MM-DD-NN`; ID neměň. Starší doménová rozhodnutí nepřečíslovávej.
Při nahrazení doplň stav a odkaz na nástupce; zachovej původní důvody.

## D2026-11-07-01

- **Stav / datum:** PŘIJATO, 7. 11. 2026. Rozhodl vlastník explicitním zadáním.
- **Otázka:** kde uchovávat plány, koncepty, změny, zamýšlené postupy a rozhodnutí,
  aby na ně mohli navazovat všichni vývojáři přes GitHub bez původního vlákna?
- **Volba a důvod:** verzované tematické soubory jsou projektovou evidencí;
  CURRENT a hlavní session handoff jsou malé průběžně udržované rozcestníky.
  Historii a uzavřené plány zpřístupňují deník, tento log a propojené archivy.
- **Varianty:** paměť/chats samotné nejsou dostupné všem; kumulování historie
  v handoffu prodlužovalo načítání a směšovalo staré a aktuální výsledky.
- **Dopad:** povinné načtení/zápis souborů před implementací, při změně i před
  předáním; limity CURRENT/HANDOFF 80 řádků, kompatibilní session cesta 25.
  Plány se při uzavření nemažou. Dokumentace se předává spolu se změnou přes PR.
- **Realizace a ověření:** [DOC01](DOCUMENTATION_GOVERNANCE_2026-11-07.md),
  [závazný postup](WORK_DOCUMENTATION_POLICY.md), [deník](EVENT_LOG.md).
  Zavedení pravidla neuděluje nové oprávnění k produkčním zásahům.

## Starší rozhodnutí — zachovaná v původních souborech

| Oblast | Trvalý zdroj | Jak číst |
| --- | --- | --- |
| Klubové dokončení a přejímka | [CLUB_COMPLETION_DECISIONS](CLUB_COMPLETION_DECISIONS.md) | D01–D20 a jejich skutečné podmínky; není to seznam všech hotových kroků |
| Členská evidence bez sezon | [Pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [schválená mapa](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md) | D1–D8 a provozní rozhodnutí; E6–E8 ověřit v aktuálním registru |
| Původní architektura a program | [Zadání a rozhodnutí](plan-eshop-tymova-evidence/02-zadani-a-rozhodnuti.md) | Historické programové D-xxx; dnešní stav a priority určuje registr |
| BAR a účetní hranice | [Plán](BAR_INTEGRATION_PLAN_2026-11-05.md), [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md) | Provozní přejímka a adaptér zůstávají oddělené |

## Rejstřík uzavřených prací a jejich plánů

Uzavření zde označuje jen uvedený rozsah; zbývající etapy dál patří do registru.
Při každém dokončení doplň řádek nebo odkaz na datovanou část deníku.

| Práce / rozsah | Plán, výsledek a důvody | Historie / další etapy |
| --- | --- | --- |
| P11 R13 — implementace v main #352, bez deploye | [Plán a důkazy](verification/medical-read-performance-20261106/RESULTS.md) | [Předání](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md), celé K0.2 otevřené |
| P11 R05 — implementace v main #353, bez deploye | [Plán a důkazy](verification/planner-read-performance-20261106/RESULTS.md) | [Předání](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md), hierarchický filtr/HTTP otevřené |
| P11 R06 — implementace v main #354, bez deploye | [Plán a důkazy](verification/attendance-read-performance-20261107/RESULTS.md) | [Předání](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md), role/HTTP otevřené |
| Refaktoring W6–W15 — technické dokončení | [Komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md), [uzavření](REFACTORING_CLOSURE_2026-10-30.md) | Lidská/účetní přejímka v P09; [provozní historie](CHANGELOG-PROVOZ.md) |
| Starší session a dokončené části programu | [Deník a archivní rozcestník](EVENT_LOG.md) | Původní plány, důkazy a rozhodnutí zůstávají v Gitu; nejde o zpětné schválení neověřených kroků |
