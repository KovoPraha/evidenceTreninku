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

## D2026-11-07-02

- **Stav / datum:** PŘIJATO, 7. 11. 2026. Vlastník po přípravě přehledu výslovně
  zadal nasazení a ověření změn rolí; stávající role lidí se nesmějí měnit.
- **Volba a důvod:** zachovat současné role a audit, rozšířit dosavadní stránku
  o společný přehled. Ověření zápisů jen na nových technických účtech, nikoli
  na existujícím zaměstnanci nebo dosavadním testovacím účtu.
- **Provozní ověření:** před nasazením čtecí otisk původních přístupů; následně
  skutečné HTTPS formuláře a odvolání všech nových testovacích přístupů s
  deaktivací účtů. Hesla jen v paměti procesu, bez e-mailů. Obnova úklidu má
  vlastní chráněnou fázi a odmítá původní ID.
- **Varianta a omezení:** Computer Use skončil kvůli neurčené URL prohlížeče;
  nepokračuje ovládání desktopu. Serverové formulářové ověření neoznačovat
  za vizuální produkční Chrome UAT. [Plán a akceptace](ACCESS_OVERVIEW_2026-11-07.md).

## D2026-11-07-03

- **Stav / datum:** PŘIJATO, 7. 11. 2026. Vlastník zadal automatické doklady,
  e-maily při vytvoření/stornu/vratce, ověření, sloučení a nasazení.
- **Volba a důvod:** nové objednávky s výslovným finančním pověřením a adresou;
  neměnný podklad při nákupu, faktura až po úhradě, opravný doklad při stornu.
  Existující minutový worker, stejné účetní služby a unikátní události chrání
  před duplicitami. PDF chyba neblokuje zaúčtování příjmu.
- **Hranice:** samotné storno nevyplácí peníze. Potvrzení vratky až po evidované
  výplatě. Nejasné doručení držet k prověření, neodesílat naslepo znovu.
  Historické objednávky bez pověření zpětně nerozesílat; ruční doklady nepřepisovat.
- **Varianty:** vystavení během platební transakce by spojilo PDF výpadek s penězi;
  plošný nový odesílač by zasáhl staré zprávy. Zvolen omezený worker a verze pověření.
- **Provozní cesta:** při nefunkčním Computer Use chráněná operace s přesným
  vystavovatelem/IČ a existujícím platným finančním pověřením; bez falešné session.
- **Realizace / akceptace:** [plán a provoz](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md),
  [lokální důkazy a otevřené meze](verification/shop-document-automation-20261107/RESULTS.md).

## Starší rozhodnutí — zachovaná v původních souborech

| Oblast | Trvalý zdroj | Jak číst |
| --- | --- | --- |
| ROLE01 — Lidé a přístupy: implementace, nasazení a technická produkční přejímka hotové | [Plán](ACCESS_OVERVIEW_2026-11-07.md), [důvod a oprávnění](#d2026-11-07-02), [produkční protokol](verification/access-release-20261107/RESULTS.md) | [Uzavření E2026-11-07-07](EVENT_LOG.md), #356; původní role přesně zachované, nové testovací účty deaktivované. Vizuální Chrome UAT NOT RUN, skutečné formuláře 16/16 PASS |
| Klubové dokončení a přejímka | [CLUB_COMPLETION_DECISIONS](CLUB_COMPLETION_DECISIONS.md) | D01–D20 a jejich skutečné podmínky; není to seznam všech hotových kroků |
| Členská evidence bez sezon | [Pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [schválená mapa](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md) | D1–D8 a provozní rozhodnutí; E6–E8 ověřit v aktuálním registru |
| Původní architektura a program | [Zadání a rozhodnutí](plan-eshop-tymova-evidence/02-zadani-a-rozhodnuti.md) | Historické programové D-xxx; dnešní stav a priority určuje registr |
| BAR a účetní hranice | [Plán](BAR_INTEGRATION_PLAN_2026-11-05.md), [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md) | Provozní přejímka a adaptér zůstávají oddělené |

## Rejstřík uzavřených prací a jejich plánů

Uzavření zde označuje jen uvedený rozsah; zbývající etapy dál patří do registru.
Při každém dokončení doplň řádek nebo odkaz na datovanou část deníku.

| Práce / rozsah | Plán, výsledek a důvody | Historie / další etapy |
| --- | --- | --- |
| DOC01 — souborová dokumentace a stručné předání | [Plán a ověření](DOCUMENTATION_GOVERNANCE_2026-11-07.md), [důvod rozhodnutí](#d2026-11-07-01) | [Předání #357](https://github.com/KovoPraha/evidenceTreninku/pull/357), [deník](EVENT_LOG.md); stav CI/přijetí určuje PR |
| P11 R13 — implementace v main #352, bez deploye | [Plán a důkazy](verification/medical-read-performance-20261106/RESULTS.md) | [Předání](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md), celé K0.2 otevřené |
| P11 R05 — implementace v main #353, bez deploye | [Plán a důkazy](verification/planner-read-performance-20261106/RESULTS.md) | [Předání](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md), hierarchický filtr/HTTP otevřené |
| P11 R06 — implementace v main #354, bez deploye | [Plán a důkazy](verification/attendance-read-performance-20261107/RESULTS.md) | [Předání](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md), role/HTTP otevřené |
| Refaktoring W6–W15 — technické dokončení | [Komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md), [uzavření](REFACTORING_CLOSURE_2026-10-30.md) | Lidská/účetní přejímka v P09; [provozní historie](CHANGELOG-PROVOZ.md) |
| Starší session a dokončené části programu | [Deník a archivní rozcestník](EVENT_LOG.md) | Původní plány, důkazy a rozhodnutí zůstávají v Gitu; nejde o zpětné schválení neověřených kroků |

## D2026-11-07-05

PŘIJATO, 7. 11. 2026, podle zadání vlastníka pokračovat katalogem.
[Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md): lehká operační projekce celého
katalogu zachová prodejnost, počty a ceny; těžká prezentace jen pro stránku.
SQL LIMIT před cenami/prodejností zamítnuto kvůli změně výsledků; trvalá cache
by potřebovala invalidaci. Parita a měření jsou podmínkou předání. Bez deploye.
