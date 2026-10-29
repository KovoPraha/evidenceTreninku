# Příprava komplexního refaktoringu — rozsah důkazu

29. 10. 2026, základ `49daae3a192d8168ed427c7adb4f203c916081d9`.
[Plán](../../COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).

## Provedeno

- Čerstvý fetch main a inventura otevřených PR: při přípravě žádný otevřený PR.
- Ověřeny úspěšné CI přesného main:
  [Testy 40778355358](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40778355358),
  [PHP quality 40778355350](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40778355350),
  [CodeQL 40778355202](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40778355202),
  [Current documentation 40778355396](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40778355396).
  Pozdější scheduled běhy nad stejným SHA nebyly zaměněny za CI aplikace.
- [Inventura zdrojů](source-inventory.json), [všech 238 Markdown dokumentů](documentation-inventory.json),
  [reprodukční skript](inventory-source.py). Čtení, velikost, SHA a nadpisy;
  inventarizace není ruční audit všech řádků ani runtime měření.
- Cíleně ověřeny autority dokumentace, poslední implementační protokoly,
  výchozí SQL rozpočet, současná baseline a existující testovací/CI nástroje.
- Staré nálezy přímých registračních a rezervačních e-mailů porovnány s aktuálními
  voláními `authMailEnqueue` a `bookingNotificationEnqueue`. Nebyly automaticky
  znovu zařazeny jako neopravené vady. Čtyři přímá volání `mail()` jsou pouze
  kandidáty na analýzu volajících/transportů, nikoli čtyři potvrzené incidenty.
- Plán rozdělen na K0 + W6–W15, malé balíky, závislosti, invarianty, přijetí,
  ověření a pravidla autonomního pokračování. Odkazy přidány do README a handoffu.

## Ověření dokumentů

- `php tools/check-current-docs.php`: 40 řádků CURRENT_STATE, 218 lokálních odkazů,
  74 archivních bloků, 0 chyb. Stav produkčního vydání se neměnil.
- Samostatná kontrola všech 75 lokálních odkazů v plánu, README a HANDOFF: bez chyby.
- `php vendor/bin/phpunit tests/Unit/CurrentDocumentationContractTest.php`:
  2 testy / 14 assertions PASS. `git diff --check`: bez chyby.

## Co tento krok nedokládá

Žádná nová implementace, produkční diagnostika, deploy, import, rozesílka,
autentizovaný produkční UAT ani změna skutečných dat. Nevznikla nová výkonnostní
měření mimo převzaté historické výsledky vln 1–5. Plán neprohlašuje všechny
agendy za pomalé a nezaručuje budoucí zrychlení před K0.
Přílohy PDF/XLSX/DOCX nebyly nově obsahově ani vizuálně revidovány.
Původní WIP `kis_rosters_admin.php` a místní konfigurace zůstávají mimo tento PR.
