# Druhý autonomní funkční průchod — 2. 11. 2026

Následné autorizované [sloučení PR #274 a nasazení](../functional-round2-release-20261102/RESULTS.md) mají samostatný protokol.
Níže je zachován rozsah původního místního běhu před tímto vydáním.

**35 konkrétních kontrol PASS, 1 chyba místního dema opravena.**
[Jednotlivá pozorování](observations.json) rozlišují hlavní syntetickou DB
a čerstvou DB po opravě. Nejde o úplnou přejímku aplikace ani o produkční UAT.

Výchozí main `25085e4ef4d771d9d36429265e61b0f0c5053d88`, větev
`codex/functional-uat-round2-20261102`; skutečný Chrome, Europe/Prague.
Vlastník povolil další testování a opravy. **Změna produkčního hesla a T07
jsou SKIPPED. Žádné produkční zápisy ani nové nasazení.**

## Prostředí

Nová vlastní MariaDB 10.4.32 pouze `127.0.0.1:3320`, samostatný Apache
`127.0.0.1:18420`. Vstupem bylo prázdné verzované schéma, migrace, syntetická
CSV fixture a localhost demo seed; žádný produkční dump/config nebo skutečné osoby.
Hlavní DB je `functional_round2`, samostatná reprodukce/oprava staršího stavu
`functional_round2_red2` a čerstvá oprava `functional_round2_green`; ID mezi nimi nejsou společná.
Soukromé session a storage mimo webroot. Fio import/auto-confirm, SumUp, Stripe
a Velocota false, webové mail/cURL transporty vypnuté. Bankovní údaje pouze
`LOCALHOST TEST - NEPLATIT`, neexistující bankovní kód 9999. Simulovaná úhrada
a vratka jsou výhradně změny této vlastní testovací DB, žádný převod peněz.

Přihlášení používala standardní seedované demo účty. Pracovní scénáře běžely
pod existujícím demo superadminem v kontextu katalogu, financí a objednávek;
nejde o důkaz omezených pracovních rolí. Nové granty se nepřidávaly přes UI.
Původní htdocs WIP i první syntetická databáze zůstaly nedotčené.

## F04 — P2: seed spotřebuje jediné zboží a další spuštění selže

Čerstvá fixture má po vyřazení neklasifikovaných kandidátů jediné vhodné zboží,
Klubovou lahev. Původní seed ji nejprve zveřejní jako zboží, následně nenajde
druhý produkt pro program a použije `?: $goods`. Stejnému produktu změní
`offer_type` na `program`. Katalog pak nenabízí žádné zboží pro deklarovaný
test kupónu LOCAL10 a další seed skončí `local_demo_goods_missing`.
Původní browser tento stav skutečně zobrazil: Klubová lahev jako kroužek.

Oprava používá samostatný syntetický produkt programu. Pokud starší demo už
zboží ztratilo, doplní vlastní samostatné zboží; existující program, jeho ID,
veřejný název a historické objednávky zachová. Vyhrazené SKU jsou stabilní,
opakované spuštění nepřidá další produkty/varianty. Kolizi s cizím/importovaným
produktem odmítne bez jeho převzetí. Tvorba používá stávající transakční
doménový writer a audit; publikační/účetní guardy se nemění.

[Skutečná MariaDB regrese](catalog-acceptance.json):

- Původní zdroj: první seed exit 0, **0 zveřejněných goods**; druhý exit 1,
  `local_demo_goods_missing`.
- Oprava staršího stavu: goods #5, původní program #1; dvě úspěšná spuštění,
  shodná ID a počty produktů/variant.
- Čerstvá oprava: původní goods #1 zachováno, nový program #5; dvě úspěšná
  spuštění se shodnými ID a počty. Obě veřejné nabídky doloženy
  [prohlížečem](fresh-demo-after.png).
- Hlavní DB s již vytvořenými objednávkami, platbou, vratkou a rezervacemi:
  opravený seed nezměnil celé řádky objednávek, položek, plateb ani rezervací.
  [Před](local-state-before-seed.json), [po](local-state-after-seed.json)
  obsahují kontrolní SHA-256 snapshotů a bezpečné syntetické souhrny.

Povinný styl vyžadoval mechanické rozbalení dříve minifikovaného seedu a
rozdělení dlouhých SQL literálů. [Sémantické porovnání](seed-semantic.diff)
vzniklo přes PHP parser po normalizaci formátování a složení konstantních
řetězců. Vyjma záměrné změny výběru produktu zachovává SQL a ostatní chování.
Zdrojové wiring testy byly přizpůsobeny formátování/literálům; jejich původní
kontroly zakázaných operací zůstaly. Nové integrační testy ověřují skutečné
vytvoření, idempotenci, zachování původního produktu a odmítnutí kolize SKU.

## Nově ověřené funkce

| Kontroly | Pozorovaný výsledek |
|---|---|
| R01–R06: klubová akce | Přihlášení rodiče; A přes dvě soupisky jen jednou, B nenabídnuto. Povinný souhlas blokuje prázdný formulář. Přihláška sníží kapacitu, další přihlášení A zakázané. Storno obnoví místo a ponechá historii. |
| R07–R12: velodrom | Bezplatná rezervace, odmítnutí duplicity, storno a vrácení kapacity. Placený termín vytvoří objednávku 250 Kč; výhradní místo drží ve stavu čekání na úhradu. |
| R13–R17: katalog | Neplatný prefix SKU odmítnut s uchováním hodnot. Nové zboží jako koncept se skladem 5 a cenou 123,45 Kč. Bez účetní kategorie nezveřejnitelné; po přiřazení 3/00 zveřejnění i zákaznický detail fungují. |
| R18–R23: sklad/kupón | Košík může obsahovat 6 kusů při skladu 5, checkout ale odmítne objednávku bez částečného zápisu. Kladný kupón, normalizace kódu, změna množství a přepočet; sleva se zaokrouhluje dolů podle stávající `intdiv` politiky. Objednávka uloží 12345 − 1234 = 11111 haléřů; reload nevytvoří duplikát. |
| R24–R28: zpracování | Simulovaná hotovost změní způsob a příjem společně; příprava uloží ready_at. Storno vrátí sklad na 5 a vytvoří refund_required. Předání hospodáři a simulovaná vratka skončí refunded s referencí. Následný seed zachová celé historické řádky. |
| R29–R35: oprava v browseru | Čerstvé demo oddělí zboží/program. Klubová cena zboží 224,10 Kč a kupón 22,41 Kč; program 249 Kč se nesleví. Smíšený košík 473,10 − 22,41 = 450,69 Kč, povinný souhlas. Skutečně uložená objednávka se dvěma položkami, sklad zboží 11 / program NULL, platba pending a 0 admissions. Logout ochrání objednávky. |

[Čerstvá persistence](local-state-fresh.json),
[smíšený košík](fresh-demo-mixed-cart.png),
[dokončená syntetická objednávka](fresh-demo-mixed-order.png),
[storno zaplaceného zboží](paid-order-cancelled.png).
Toast nebo HTTP 200 samy nebyly důkazem uložení; konečné stavy potvrdila
čtecí kontrola vlastní databáze. Základní veřejné/rodinné/trenérské průchody
z první vlny mají [samostatný historický protokol](../functional-uat-20261102/RESULTS.md).

## Kontroly a hranice

Místní Windows PHPUnit **1905 testů / 38260 assertions**, exit 0, tři známé
POSIX/symlink skipy; cílená regrese a seed wiring **7/71 PASS**.
PHPStan level 5, rozpočet baseline beze změny, jeho kontrakt, changed-file
styl (4 PHP), syntaxe, anonymní sweep (**32 public / 2 CLI / 237 protected,
0 chyb**) a git diff --check PASS. [Souhrn](validation.json).
Přesný Linux/MariaDB CI určuje navazující PR; místní PASS jej nenahrazuje.

Při jednom čtecím ověření přestala odpovídat vlastní MariaDB. Byla spuštěna
z téhož ověřeného datového adresáře; následné dotazy potvrdily uložené stavy.
Příčina ukončení procesu nebyla doložena, nejde o prokázaný nález aplikace.
Jedno pointer kliknutí nezaškrtlo potvrzení přípravy; formulář bezpečně
zablokoval odeslání, následné ověřené ovládání políčka přípravu uložilo.

Produkční browser se v této vlně nepoužíval. Neproběhla skutečná platba,
bankovní integrace, odeslání zprávy, import skutečných dat, změna hesla/T07,
účetní přejímka ani úplná matice omezených rolí. Tyto místní simulace nejsou
schválením skutečného finančního nebo provozního použití.

Účty odhlášeny, ochrana po logoutu ověřena, vlastní karty zavřeny; viewport
se neměnil. [Vlastní servery zastaveny](cleanup.json), porty 3320/18420
uzavřeny. Syntetická data zůstávají mimo Git a webroot pro diagnostiku.
Nová oprava není sloučená ani nasazená; produkční vydání zůstává beze změny.
