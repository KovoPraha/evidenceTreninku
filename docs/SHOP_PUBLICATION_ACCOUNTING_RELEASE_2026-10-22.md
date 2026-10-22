# Produkční vydání kontroly účetního zařazení — 22. 10. 2026

## Schválení a zdroj

Vlastník schválil push, PR, merge po zelených kontrolách a chráněné produkční
nasazení. Výslovně požadoval zachovat produkty #274, #243 a #245, existující
a stálé příspěvkové VS a aktualizovat session handoff. Ověření publikace se
provádí pouze čtením, bez skutečné objednávky nebo uložení produktu.

- Aplikační [PR #164](https://github.com/KovoPraha/evidenceTreninku/pull/164)
  byl sloučen 22. 10. 2026 v 12:33:22 UTC (14:33:22 Europe/Prague).
- Aplikační SHA: `cc9c9280f137465eb59f20523d827b5502416e20`.
- [PR CI](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779095070)
  a [PR CodeQL](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779090942)
  jsou zelené; PHPUnit 1 214 testů / 26 960 kontrol a MariaDB 10.3/11.4.
- [CI výsledného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779314416)
  a [CodeQL výsledného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779314228)
  jsou zelené.
- [Chráněný deploy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779478133)
  byl spuštěn přes `deploy-production.yml`, `potvrzeni=NASADIT`,
  `uat_schvaleno=false`, `importovat_krouzky=false`.

## Produkční důkaz

Deploy **úspěšný**, 12:34:36–12:38:07 UTC (14:34:36–14:38:07 Europe/Prague).
Ověřená záloha 246 tabulek / 2 triggerů, migrace aktuální s `pending=[]`,
release aktivován a přímý HTTP smoke z runneru vrátil **200**. Testy uvnitř
deploye rovněž prošly: 1 214 testů / 26 960 kontrol. Oba volitelné příznaky
zůstaly `false`; import kroužků byl přeskočen.

Automatické porovnání před/po migracích vrátilo `ok=true, unchanged=true`:

| Oblast | Počet | Výsledek |
|---|---:|---|
| Původní platby, ID nejvýše 15 | 8 | Shodný obsah identifikace platby a VS |
| Stálé VS příspěvků (`member_fee_standing_orders`) | 0 | Zůstaly prázdné |
| Stálé VS plátců (`member_payer_variable_symbols`) | 0 | Zůstaly prázdné |

Otisk osmi plateb před i po:
`cf005f028f34611540fe72e8881d18261b79aef1a6e137619409970778e11e9b`.
Porovnání je omezené horními ID zachycenými před migracemi; nová skutečná
platba během deploye by tedy nebyla zaměněna za změnu historických údajů.

Živé ověření administrace po aktivaci, pouze čtením:

- **#274 Magazin a #243 Předpřípravka:** hláška „Ještě nelze zveřejnit“
  s důvodem Nezařazeno a pokynem k hospodáři. Publikační formulář chybí.
  Dosavadní stav obou produktů zůstává **Zveřejněný**; kontrola je nestahuje.
- **#245 TEST – Cyklistický kroužek:** zachovaný produkt a stav Zveřejněný.
  Platná výchozí účetní řada programu 1/00 zůstává použitelná; kontrola
  nevyžaduje automatickou opravu jeho katalogových kategorií.
- U všech tří produktů je katalogový řádek před/po totožný: název, veřejný
  popis, typ, stav, kategorie, cena a pořadí. Samostatně porovnány hlavní
  údaje, příznak dopravy, varianta, SKU, cena a sklad; shodné.
- Rozdíl v počtu HTML prvků u #274 a #243 tvoří pouze potvrzovací checkbox
  z odstraněného publikačního formuláře, nikoli údaj produktu.

Předdeployový podklad je lokálně v
`output/browser/publication-guard-products-before-2026-10-22.json`.
Po obnovení práce večer již přihlášení vypršelo; výše uvedené živé závěry
pocházejí z dokončeného ověření bezprostředně po deployi, nikoli z nového loginu.
Žádný produkt, objednávka ani platba nebyly při ověření uloženy.

Dokumentační dokončení používá větev
`codex/shop-publication-release-proof-20261022`. Nevyvolává další deploy.

## Hranice

Bez migrace, automatického mapování nebo stažení dosavadních produktů.
Účetní kontrola pouze čte klasifikaci a nerezervuje VS ani pořadí.
Zařazení produktu vychází ze stejného klasifikátoru jako checkout; platná
výchozí řada druhu nabídky je povolená. Chybějící katalogová kategorie proto
sama o sobě neznamená neplatnou účetní řadu (například program s 1/00).
Produkční POST publikace, vytvoření objednávky ani úhrady se při ověření
neprovádějí. Backendové odmítnutí a rollback prokazují syntetické testy.

[Implementace a testy](SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md).
