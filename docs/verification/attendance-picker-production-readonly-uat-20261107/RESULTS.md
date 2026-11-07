# Přihlášené produkční UAT výběru osob — pouze čtením

Stav: **BLOKOVANÉ**. ID P11-PICKER-PROD-RO, 7. 11. 2026.
[Plán a hranice](../../ATTENDANCE_PERSON_PICKER_2026-11-07.md#přihlášené-produkční-uat-pouze-čtením--7-11-2026).
Základ `69f49033`, větev `codex/attendance-picker-production-readonly-uat-20261107`,
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-picker-production-readonly-uat-20261107`.

## Důvod blokace a skutečný průběh

1. Aktuální main a původní relace dosud přihlášené produkční UAT nedoložily.
   Záznamy #372/#373 dokládají jiné technické a syntetické kontroly.
2. Prohlížečové CUA při `cua.getState()` i cíleném `cua.getBrowser(...)`
   skončilo chybou `js execution timed out; kernel reset, rerun your request`.
3. Podporovaný náhradní Windows Computer Use (`@oai/sky`) vrátil seznam
   aplikací a oken. To nepotvrzuje přihlášení do KIS ani funkci pickeru.
4. Při následném načtení stavu vybraného okna Chrome nástroj běh automaticky
   zastavil, protože nedokázal dostatečně spolehlivě určit aktuální URL na Windows.
   Nebyl vrácen použitelný stav cílové produkční stránky. Původní hlášení:

> Computer Use has been stopped for this turn because it could not determine the current browser URL on Windows with enough confidence to enforce policy. Stop your work and send a final message noting why Computer Use ended.

Blokátor je čtení/ověření URL v ovládacím nástroji. Není doloženo, že jde
o chybu aplikace nebo chybějící přihlášení; nové přihlášení ani deploy se
nepovažují za ověřenou opravu tohoto problému.

## Stav scénářů

| Scénář | Stav | Důkaz / mez |
| --- | --- | --- |
| Ověřit přihlášenou produkční stránku a aktuální pracovní roli | BLOKOVANÉ | Bez spolehlivého URL a čitelného stavu stránky |
| Otevřít existující trénink/lekci a zaznamenat výchozí výběr/verzi | NEPROVEDENO | K UAT této stránky se nástroj nedostal |
| Hledání, prázdný výsledek a stránkování účastníků | NEPROVEDENO | Hledací UI nebylo použito |
| Hledání a stránkování osoby pro omluvu | NEPROVEDENO | Hledací UI nebylo použito |
| Zachování výběru/verze při hledání a po novém načtení | NEPROVEDENO | Chybí pozorování před/po |

Žádný scénář tohoto UAT nemá potvrzený úspěšný výsledek. V tomto pokusu
neproběhly klikací, klávesnicové ani formulářové akce na cílové stránce;
docházka, omluvy a role nebyly tímto UAT změněny. Nebyla ověřena produkční
identita ani provedeno přihlášení. Do Gitu nepatří skutečná jména, kontakty,
snímky, hesla nebo cookies; tento protokol je neobsahuje.

## Další krok a podmínka pokračování

Vlastník dalšího technického kroku: vývoj s funkčním podporovaným UI nástrojem.
Nejdříve obnovit spolehlivé ověření URL a čtení DOM nebo snímků v nástroji.
Pak znovu načíst aktuální plán a výsledky ostatních vláken, aby se UAT neduplikovalo.
Při dostupném čtení pokračovat v existující přihlášené relaci, ve vlastní
kontrolní kartě/okně, a otevřít existující záznam.

Povolené jsou jen čtecí kroky plánu a hledací/stránkovací POST `find_participants*`
a `find_excuse_person*`, jejichž oddělení od writeru bylo ověřeno ve zdroji.
Před/po a konečné nové načtení musí potvrdit zachování výběru/verze. UAT
zůstává BLOKOVANÉ do skutečného provedení a zapsání jednotlivých výsledků.

## Dokumentační předání

Toto pokračování dokončuje pouze dokumentační předání blokace, ne provedení UAT.
Plán, [registr](../../WORK_PLAN_CURRENT.md), [CURRENT](../../CURRENT_STATE.md)
a [handoff](../../HANDOFF_CURRENT.md) mají shodný stav a odkaz sem.
Commit/PR a konkrétní stav GitHub předání doplní výsledková větev.
