# Produkční vydání číselných řad – 21. 10. 2026

## Schválený rozsah

Vlastník výslovně schválil push, PR, merge po zelených kontrolách a chráněné
nasazení. Ověření produkce je pouze čtením: bez objednávek, potvrzení úhrad,
změn čítačů či mapování. Existující VS a stálé VS příspěvků zachovat.

## Vydání

- Implementace: [PR #160](https://github.com/KovoPraha/evidenceTreninku/pull/160).
- Aplikační SHA: `1441683ae1ae2bb9da16d8be3af62ce40f35385c`.
- PR CI [39608955677](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39608955677):
  1 193 testů / 26 739 kontrol, MariaDB 10.3 a 11.4 úspěšné. Obě verze
  ověřily souběh řad, read-only VS sondu i zálohu/obnovu 198 tabulek.
- CodeQL a obě analýzy actions / javascript-typescript prošly před merge.
- Chráněný [deploy 39609254832](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39609254832)
  byl spuštěn nad přesným SHA s `uat_schvaleno=false`, `importovat_krouzky=false`.
  **Úspěch**: preflight PHP 8.2.33 bez varování, ověřená záloha **238 tabulek / 2 triggerů**,
  111 migrací aktuálních, `pending=[]`, zachování VS, aktivace, HTTP smoke 200
  (serverový curl fallback) a odstranění kopie konfigurace z release.
- [CI výsledného main 39609248886](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39609248886)
  a [CodeQL 39609248964](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39609248964)
  jsou také zelené. Stejnou úplnou PHP sadu zopakoval deploy.

## Kontrola zachování VS

Před vydáním byla ve správě nalezena zadaná objednávka s VS `0000000013`.
Workflow pořizuje před migrací read-only SHA-256 otisky VS a vazeb v platbách
a obou tabulkách stálých příspěvkových rezervací. Po migraci kontroluje stejný
rozsah ID a při neshodě zastaví aktivaci. Jednotlivé VS a osobní údaje se
v těchto logách nevypisují.

Výsledek po migraci: **`ok=true`, `unchanged=true`**.

| Tabulka | Kontrolovaný počet před/po | Výsledek |
|---|---:|---|
| `payments` | 8 / 8 (hranice ID 15) | stejný SHA-256 VS a vazeb |
| `member_fee_standing_orders` | 0 / 0 | prázdná tabulka zachována |
| `member_payer_variable_symbols` | 0 / 0 | prázdná tabulka zachována |

Otisk plateb před/po je
`cf005f028f34611540fe72e8881d18261b79aef1a6e137619409970778e11e9b`.
Na produkci zatím nejsou žádné rezervace stálých příspěvkových VS; jejich
neprázdné stavy a detekci změny/odstranění prokázaly automatické testy.
Po aktivaci browser znovu přečetl VS původní objednávky: **`0000000013`**.

## Prohlídka administrace

**PASS pro přihlášenou prohlídku bez obchodních zápisů.**

- Z menu Hospodář a platby → Platby → Další nástroje funguje odkaz
  **Číselné řady a účetní kategorie** na `eshop_number_series_admin.php`.
- Všech sedm výchozích řad se načetlo s následujícím pořadím 1. Příklad knih:
  VS `2630100001`, čitelné číslo `2026-3-01-00001`; smíšená řada 9/00 má
  VS `2690000001`. Jde pouze o náhled, žádné číslo se nerezervovalo.
- Výběry obsahují 91 katalogových kategorií/nadkategorií, 292 produktů a čtyři
  výchozí druhy. Produkční mapování se neukládalo ani neodhadovalo podle názvu.
- Šest měnících formulářů má POST, CSRF a povinný důvod i potvrzení;
  následující číslo má rozsah 1–99999. Hodnoty CSRF se neexportovaly.
- Audit číselných řad má 0 událostí a seznam vydaných čísel je prázdný.
  Žádný formulář nebyl odeslán; nevznikla objednávka ani platba.
- Po obnovení původní pozice **Zákaznická péče a objednávky** je finanční
  administrace správně odmítnuta. Filtrovaný přehled původní objednávky
  znovu potvrdil původní VS. Pozice byla ponechána ve výchozím stavu kontroly.
- Konzole bez warning/error, stránka bez PHP fatal/parse/warning výpisu.

Snímek je lokálně v `output/browser/shop-number-series-production-2026-10-21.png`
(nepřidává se do Git repozitáře). Tato prohlídka nepředstírá produkční zápis
nové objednávky ani plné UAT; nákupní zápisy a souběh pokrývají integrační testy.

Výchozí neklasifikované zboží, akce a velodrom používají 8/00, kanonické
kroužky 1/00. Hospodář může nyní vědomě nastavit skutečné mapování; testovací
mapování z localhostu se nepřenášelo. Faktury a účetní export nejsou vydané.

## Předání

Dokumentační větev `codex/shop-number-series-release-proof-20261021` ve složce
`C:\xampp\htdocs\evidencePavel-number-series-20261021` navazuje na nasazované
SHA. Report, plán, aktuální stav a oba handoffy jsou aktualizované tímto
dokumentačním řezem. Nepřidává aplikační změny a nevyžaduje další deploy.
