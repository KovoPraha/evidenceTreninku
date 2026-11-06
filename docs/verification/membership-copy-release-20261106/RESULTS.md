# Členské popisky #340 — produkční vydání 6. 11. 2026

## Oprávnění a přesný rozsah

Vlastník výslovně požádal **„NASADIT připravenou opravu členských popisků z PR #340
a ověřit ji na produkci. Nic dalšího nezapínej a zachovej rozpracované změny.“**

Cílový commit `7d8a62ba772f5d47fd0fa8af82f97cf63dcd1f55`, PR #340.
Předchozí doložené nasazení `9c543ff9ef7870d3503ec7f38fc05f2b376d0a82`,
deploy `42172409868`. Mezi těmito body se aplikační kód mění pouze
v `booking/eshop.php` a `booking/produkt.php`. Workflow, migrace a ostatní
aplikační soubory jsou beze změny; další rozdíly tvoří dokumentace a důkazy.
Všech 11 kontrol PR PASS, kontroly aplikačního main PASS; otestovaný a sloučený
strom PR jsou shodné. Produkční UAT celého systému se tímto neprohlašuje za hotové.

Větev `codex/membership-copy-release-20261106`, izolovaná kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-membership-copy-release-20261106`.
Původní větev/WIP v `C:/xampp/htdocs/evidencePavel` se nepřepíná ani nepřepisuje.
Kontrolní hash původního `kis_rosters_admin.php`:
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Postup a stav

1. Dokončeno: kontrola main, PR, testů, rozdílu proti produkci a zachování WIP.
2. Dokončeno: čtecí předběžný audit 42235961200 PASS, živý katalog/detail ukazoval původní chybné texty. [Snapshot před vydáním](pre-impact.json), [anonymní HTTP detaily](http-before.json), [odkazy katalogu](catalog-before.json).
3. Dokončeno: chráněné `deploy-production.yml` / [42236370197](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42236370197) na main s `potvrzeni=NASADIT`,
   `uat_schvaleno=false`, `importovat_krouzky=false`; přesné SHA běhu ověřeno shodné s cílem.
4. Dokončeno: nezávislý čtecí audit 42236997752 PASS a živé HTTP/browser ověření katalogu,
   detailu produktu 297 a zachování běžného kroužku/zboží.
5. Výsledky, záloha/schéma, konfigurace/přepínače, stav, plán a handoff zapsané.
   Následuje kontrola a souborové předání dokumentace; samo nespouští další deploy.

Žádná aktivace integrace, instalace spouštěče, import, skutečné sjednání/platba,
změna produktu/ceny/publikace ani nová rozesílka nejsou součástí tohoto vydání.

## Meze předběžného auditu

`ok/read_only_session=true`, soukromé úložiště existuje a je zapisovatelné mimo
strom aplikace. `deployment_ready=false` je dosavadní stav doménových kontrol
(např. skladové počátky a trenérské rozsahy), nikoli nová chyba této textové
opravy. Audit používá historický 44souborový manifest starších vydání; jeho
20 rozdílů proti `new_source` není měřením shody s dnešním #340. Identitu nynějšího
vydání doloží přesné SHA deploye, jeho výsledek a nové živé popisky.
Přihlášený prohlížeč má existující košík; nedotýkat se jeho obsahu ani objednávky.

## Výsledek produkčního vydání — PASS

- [Chráněný deploy 42236370197](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42236370197)
  **SUCCESS**, dokončen 6. 11. 2026 v 14:46 UTC (15:46 CET), přesné cílové SHA.
  [Strojový souhrn](deploy-summary.json): **2280 testů / 42180 assertions PASS**,
  syntaxe PASS, import kroužků **SKIPPED**, UAT false. Nové migrace žádné,
  schéma current / pending []; 8 dosavadních platebních VS zachovaných.
- Ověřená DB záloha `evidence_2026-11-06_144520_7cf0efec.sql.gz`,
  **387 tabulek / 2 triggery**, 6 294 496 komprimovaných bajtů;
  hash `81a9b6378b77ca1d07cd48b207aedbca350b310b7904712b809816f659bc4ace`.
  Počet 387 je z měření **před aktivací**, nikoli výsledek nové migrace #340.
  Obnova této nové konkrétní zálohy **NOT RUN**; předchozí tři generace mají
  samostatný doklad P14. Dvě dříve chybějící účetní přílohy zůstávají chybějící;
  úklid neodstranil žádný soubor, přesun kandidátů 0, errors 0.
- [Porovnání vydání](release-comparison.json) a [audit po](post-impact.json):
  živá identita odpovídá cíli a deploy běhu. Hash konfigurace shodný,
  Fio import/auto-confirm a SumUp true, Stripe/Velocota false, Packeta null;
  KIS canonical writes false. Žádný z těchto přepínačů se nezměnil.
  Migrační ledger 160 a všechny čtené doménové počty před/po shodné.
  Výsledek neprohlašuje nové funkční otestování všech integrací či spouštěčů.
- [Anonymní HTTP](http-after.json): **4 stránky / 19 kontrol PASS** — členský
  katalog a produkt 297, běžný kroužek Aligátoři 247 a zboží Author ODM 16 / 218.
  U dvou nečlenských detailů je normalizovaný viditelný text přesně shodný před/po.
  Cena členství **2200 Kč/měsíc** a odkaz na variantu 951 zachované.
- Skutečný přihlášený Chrome: katalog „členství v klubu“, „1 tarif“,
  „Detail členství“; detail „Tarif a sjednání členství“, nová nápověda ověření
  sportovce, kontaktní titulek/varianta/tlačítko členství **PASS**.
  [Katalog – snímek](catalog-after.png), [detail – snímek](detail-after.png),
  [čtecí strom katalogu](catalog-browser.txt), [detailu](detail-browser.txt).
  Pouze navigace a čtení; formulář, existující košík ani platba nebyly odeslané.
  Cílené ověření popisků není úplná přejímka sjednání/úhrady/PDF ani mobilní UAT.
- Původní `kis_rosters_admin.php` má po vydání stejný výše uvedený SHA256;
  větev ani ostatní rozpracované kopie se nepřepínaly, nečistily ani neslučovaly.

## Zbývající práce a předání

Oprava #340 je nasazená a provozně ověřená v požadovaném rozsahu. Skutečný
obchodní tok P05/P07, export P06 a další podklady vlastníka zůstávají ve
[společném plánu](../../WORK_PLAN_CURRENT.md); nic se kvůli tomuto vydání znovu
nepublikuje, nezapíná ani neodesílá. [Aktuální stav](../../CURRENT_STATE.md)
a [handoff](../../HANDOFF_CURRENT.md) nyní rozlišují historickou přípravu od
provedeného nasazení. Dokumentační předání bude připojené po založení PR.

Místní kontrola dokumentace **PASS**: CURRENT_STATE 80 řádků, 883 odkazů,
105 zachovaných archivních bloků, 0 chyb; 217 místních odkazů dotčených Markdown
souborů bez chyby; dokumentační kontrakt **2 testy / 14 assertions PASS**,
`git diff --check` PASS. Aplikační soubory tato dokumentační větev nemění.
