# Přihlášené produkční UAT výběru osob — pouze čtením

Stav: **ČÁSTEČNĚ PROVEDENO 8. 11.** ID P11-PICKER-PROD-RO.
Původní blokace 7. 11. chybějící přihlášenou relací byla odstraněna ručním
přihlášením vlastníka; [nový čtecí průchod](../quality-uat-20261108/RESULTS.md)
ověřil hledání a stránkování, nikoli všechny scénáře ani DB stav.
[Plán a hranice](../../ATTENDANCE_PERSON_PICKER_2026-11-07.md#přihlášené-produkční-uat-pouze-čtením--7-11-2026).
Základ `69f49033`, větev `codex/attendance-picker-production-readonly-uat-20261107`,
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-picker-production-readonly-uat-20261107`.

## První pokus — původní blokace nástroje a skutečný průběh

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

## Stav scénářů původních pokusů 7. 11.

| Scénář | Stav | Důkaz / mez |
| --- | --- | --- |
| Ověřit přihlášenou produkční stránku a aktuální pracovní roli | BLOKOVANÉ | Nový pokus URL přečetl; cílová stránka i pracovní rozcestník přesměrovaly na login |
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
Ověření URL a čtení stránky je v opakovaném pokusu funkční. Aktuálně je nutné
ruční přihlášení vlastníka do ponechané kontrolní karty existujícím pracovním účtem,
bez změn rolí. Heslo se nežádá do chatu ani nehledá na disku.
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
První commit `648abf33` je pushnutý na uvedené větvi. Dokumentační předání
sdílí [PR #377](https://github.com/KovoPraha/evidenceTreninku/pull/377);
aktuální finální head, CI a merge ověř přímo u PR. Sdílení ani sloučení
dokumentace nemění stav UAT BLOKOVANÉ.

Kontroly dokumentace: `php tools/check-current-docs.php` — 0 chyb;
`git diff --check` bez nálezu. CURRENT má 80 řádků, hlavní handoff 77.

## Opakovaný pokus po #377

Nový pokyn vlastníka 7. 11. 2026: znovu zkusit přihlášené čtecí UAT, jen pokud
funguje ověření URL a neprovedlo je jiné vlákno. Main `b3d0f4bb` ani aktuální
stavy původního picker a výkonového vlákna dokončené UAT nedokládají.
Větev `codex/attendance-picker-uat-retry-20261107`, stejný vlastní worktree.
Nejdříve kontrola čtení URL/stránky; dosavadní stav BLOKOVANÉ se tím nemění.

CUA nyní úspěšně načetlo inventář a vytvořilo vlastní kontrolní kartu v Chrome.
Přímé otevření `https://kis.kovopraha.cz/training_closure_admin.php` vrátilo
čitelné přihlášení s ověřenou URL `https://kis.kovopraha.cz/login.php`.
Následná navigace na pozorovaný pracovní rozcestník `pracovni_pozice.php`
skončila na téže přihlašovací stránce. To dokládá odstranění předchozí blokace
čtení URL/stránky, nikoli přihlášené UAT nebo chybu pickeru.

Formulář nebyl vyplněn ani odeslán. Kontrolní karta je předána vlastníkovi
k ručnímu přihlášení; funkční scénáře čekají na platnou existující relaci.
Žádné uložení docházky, omluvy ani změna role se neprováděly.

První commit opakovaného pokusu `40531586` je pushnutý. Dokumentační předání
sdílí [PR #378](https://github.com/KovoPraha/evidenceTreninku/pull/378);
finální head, CI a merge ověř přímo u PR. Kontrola dokumentace: 0 chyb,
`git diff --check` bez nálezu. Toto předání neuzavírá funkční UAT.
