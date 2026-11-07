# Audit všech plánů a konceptů — 7. 11. 2026

Stav: HOTOVO — obsahový audit a místní ověření; GitHub předání se doplní níže. ID DOC02. Vlastník zadal projít všechny existující plány
a koncepty v repozitáři, zkontrolovat jejich evidenci v registru, dohledání
aktivních z CURRENT a handoffu a dokončených z rozhodovacího logu; opravit
chybějící vazby a neaktuální stavy podle důkazů.

## Rozsah a pracovní kopie

- Výchozí main `1d88ce9e`, větev `codex/plan-catalog-audit-20261107`, vlastní kopie
  `C:/Users/Marek/Documents/Codex/evidencePavel-plan-catalog-audit-20261107`.
- Inventura všech verzovaných textových dokumentů repozitáře, včetně plánů
  uvnitř ověřovacích složek, historických promptů a konceptů mimo `docs/`.
- Pouze dokumentační opravy a odkazy; žádná implementace návrhů, nasazení,
  provozní aktivace ani změna dat. Původní WIP zůstává zachovaný.
- Stará data ani názvy větví nejsou důkaz dokončení; částečně hotové plány
  rozdělit na doložený rozsah a otevřenou návaznost. Neznámé ponechat k ověření.

## Postup a akceptace

- [x] Inventarizovat a obsahově zatřídit všechny verzované dokumenty.
- [x] Každý plán/koncept opatřit katalogovým záznamem, stavem, vazbou na registr
  a důkazem nebo výslovnou podmínkou dalšího ověření.
- [x] Opravit zavádějící aktuální statusy; historické snapshoty nepřepisovat.
- [x] Z CURRENT i handoffu zpřístupnit úplný aktivní katalog přes registr.
- [x] Z rozhodovacího logu zpřístupnit dokončené a nahrazené plány s výsledky.
- [x] Ověřit úplnost inventury, dosažitelnost, odkazy a dokumentační kontrakt;
  předat přes GitHub PR a zapsat skutečný stav.

## Souběžná práce

Při zahájení otevřené PR #355 (syntetické UAT kroužku), #356 (přístupy),
draft #358 (klubové ceny) a draft #347 (ranní důkaz). Jejich návrhy nevydávat
za aktuální main ani produkci; evidovat odděleně a stav před sloučením obnovit.

## Výsledky a další krok

[Katalog](PLAN_CATALOG.md), [úplný index](PLAN_DOCUMENT_INDEX.md) a
[výsledky ověření](verification/plan-catalog-audit-20261107/RESULTS.md) jsou dokončené.
Původních 695 textových dokumentů bylo inventarizováno; při souběžném merge
#356 do `ebb8b2e3` přibyly dva dokumenty ROLE01, které audit rovněž převzal.
18 dosud lokálních plánů/příloh etap 0–6 je nyní sdílených jako historický
podklad v `docs/plans/realization-20261022/`, bez provozních dat či JSON zdroje.
Celkem 721 indexovaných textových souborů; 306 hlavních záznamů zahrnuje
222 plánovacích podkladů/dispozic a 84 referencí. Nejde o 222 nově schválených úkolů.
Místní kontroly PASS; následuje GitHub předání, CI a začlenění dokumentace.
