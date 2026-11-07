# Priority 1 / 7 / 8 — výsledky autonomního průchodu

Stav: HOTOVO pro převzatý samostatný rozsah P14 + P10/C04 a kontrolu souběhu.
[Plán AUTO01](../../AUTONOMOUS_PRIORITIES_1_7_8_2026-11-07.md),
[předání PR #370](https://github.com/KovoPraha/evidenceTreninku/pull/370).
Žádná nová implementace, deploy ani obnova produkční DB touto relací.

## Bod 1 — původní vlastníci a skutečný stav

Před zahájením přečten aktuální main, PR i tři aktivní vlákna. Jejich kód,
deploy ani stejné okno Chrome se neopakovaly. Rozhoduje sdílený doklad, ne chat:

- Lekce #367 nasazené jako `269684c2`, deploy 42374036053 SUCCESS;
  výsledkové #369 je v main `2ef20aed`. [Protokol](../attendance-lesson-release-20261107/RESULTS.md).
- Výběr osob #366 původní vlákno dokončilo a sloučilo jako `faae75e8`;
  [finální parita/HTTP/browser důkazy](../attendance-person-picker-20261107/final/RESULTS.md).
  Původní zadání mělo zákaz produkční změny; tato relace jej neruší a nový deploy nedělá.
- Karty jsou ve vydaném kódu. Cílený nový Chrome 390/360 px a desktop patří
  stále původní přístupové relaci / #365. [Plán a otevřená přejímka](../../ACCESS_OVERVIEW_2026-11-07.md).

## Bod 7 — novější konkrétní DB záloha PASS

[Běh 42376793141](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42376793141),
operace `obnovit-zalohu-izolovane`, workflow na `2ef20aed`.
Obnovena **evidence_2026-11-07_125007_ce199079.sql.gz** do dočasné MariaDB 11.4
na GitHub runneru. Shoda počtu řádků všech **389** manifestových tabulek a
obou triggerů PASS. SHA-256 souboru ověřen vůči sidecaru i manifestu; aplikace
a jméno SQL rovněž ověřené. Doklad nenahrazuje jiné pozdější generace.

[Bezpečný strojový důkaz](restore-proof.json), [sanitizovaný výtah](restore-evidence.txt).
Úklid kontejneru a dočasných dat SUCCESS. Privátní SQL ani manifest se
nestahovaly do tohoto počítače ani neukládaly do Gitu. Produkční DB se neobnovovala.
Existující provozní zámek `produkce-kis` sdílí workflow s deployem.

## Bod 8 — větve, worktrees a WIP

[Úplná čtecí inventura](work-inventory.json), [zdroj kolektoru](inventory.py.txt).
Datovaný snímek na `2ef20aed`, nikoli trvalé označení pozdějšího stavu:

| Rozsah | Zjištění |
| --- | --- |
| 332 větví | 280 přesných hlav sloučených PR + 8 doložených ancestry; 30 bez důkazu merge + 11 se shodným názvem PR, ale jinou hlavou; 3 otevřená PR včetně tohoto auditu |
| 156 worktrees | 104 čistých, 52 s hlášenými změnami nebo neversionovanými položkami |
| 1595 hlášených sledovaných rozdílů | 1564 jsou chybějící soubory jediného starého dočasného bezpečnostního checkoutu; tento checkout není kandidát k deployi ani plošnému merge |
| Zbývajících 31 hlášených rozdílů | 6 má shodný obsah s pevným main po normalizaci konců řádků, 25 odlišný. Není to důkaz chybějící funkce ani schválení merge/smazání |
| 273 neversionovaných položek/složek | Pouze počty, bez kopírování jejich obsahu či názvů do důkazu |
| Původní `kis_rosters_admin.php` | SHA-256 před/po `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`, zachovaný, diverguje a vyžaduje vlastníka |

Závěry používají git metadata, hlavy sloučených PR a shodu obsahu s pevným
main blobem. Lokální aplikovatelnost patche není důkaz začlenění do main. Žádný checkout/reset/clean, odstranění větve nebo cizího souboru.
Squash nedokazuje chybějící implementaci z pouhého rozdílu commitů.
Před případnou archivací znovu ověřit vlastníka a jeho aktuální WIP; aktivní
relace během a po inventuře legitimně pokračují v úpravách.

Opakování: `gh pr list --state merged --limit 500 --json number,headRefName,headRefOid,mergedAt,mergeCommit,url`
a obdobný seznam otevřených PR uložit mimo Git; kolektor přijímá `--repo`,
`--prs`, `--open-prs`, `--out`. Nepřebírat staré počty za dnešní stav.

## Bod 8 — konkrétní staré technické kandidáty

[Ověření kandidátů a zdrojové otisky](technical-candidates.json),
[14 stávajících testů / 289 assertions PASS](technical-candidates-junit.xml).
Kontroly expozice release, legacy ochran, citlivých exportů, runtime DDL
a pravidel anonymous sweepu. Nebyl napsán ani odstraněn žádný test.

- Duplicitní export už je chráněný alias do kanonického handleru; kompatibilitu zachovat.
- `ebook.pdf` vyloučený v obou rsync fázích; skutečný anonymní HEAD **403**.
- `export.php` i `export_csv.php`: anonymní HEAD **302 → login.php**.
  Žádná session, cookies ani POST; není to přihlášené exportní UAT.
- `arial.ttf` existuje a používá se k renderování; není nepoužitý soubor.
- Plošné odstraňování introspekce nebo Wiring testů není doloženou opravou.
  Další změna potřebuje konkrétní měření a chování; staré počty nejsou dnešní závada.

## Předání a meze

Vlastní samostatné kroky mají důkazy a uzavřený rozsah. Tato inventura
neuzavírá celé C04/K0.2, fyzickou TV, hosting ani lidské/účetní přejímky.
Neprovádí archivaci cizí práce. P05/P07, P06 a heslo/T07 zůstávají odložené.
Zbývající vlastníci a další kroky jsou v [registru](../../WORK_PLAN_CURRENT.md).
