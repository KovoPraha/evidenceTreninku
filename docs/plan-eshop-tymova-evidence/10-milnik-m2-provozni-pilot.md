# 10 – Milník M2: provozní pilot na localhostu

Stav: kanonický pracovní plán druhého produktového milníku

Aktualizováno: 4. 8. 2026

Prostředí: pouze localhost a syntetická data; produkce beze změny

## Výsledek milníku

M2 má převést integrovaný prototyp M1 do podoby, na které lze zkoušet běžný
provoz klubu bez ručních zásahů do databáze. Na konci M2 má vlastník umět projít
celou cestu rodiče, sportovce, trenéra a správce, zaznamenat připomínku a u každé
změny dohledat její původ.

M2 není produkční cutover. Starý KIS a Shoptet se nevypínají, automatické
finanční operace se nezapínají a ostrá data se neimportují.

## Aktuální stav M2

Procenta vyjadřují splněná akceptační kritéria daného řezu, nikoliv množství
kódu.

| Řez | Stav | Odhad | Hlavní výstup / mezera |
|---|---|---:|---|
| M2.0 vlastníkova prohlídka M1 | čeká | 0 % | projít A01–A10 a sepsat chyby, UX připomínky a nové požadavky |
| M2.1 provoz klubové akce | hotovo lokálně | 100 % | auditovaný CSV export účastníků `m2.event-participants.v1` |
| M2.2 opravy a UX z prohlídky | probíhá | 55 % | veřejnost prochází e-shop, kroužky, velodrom a bezpečný rozvrh bez registrace; přihlášení je vyžadováno až pro akci |
| M2.3 zkouška migrace KIS | probíhá | 55 % | parser, bezpečný matcher, parity kontrakt a neměnný raw archiv existují; chybí finální exportní kontrakt, promote/rollback a úplný paritní report |
| M2.4 provozní e-shop | technicky hotovo | 95 % | detail, kupóny, kapacity, opakovaný nákup a měnová hranice jsou uzavřené; zbývá vlastníkova úplná provozní zkouška |
| M2.5 přístup a obnova účtu | technicky hotovo | 96 % | trenérská a zákaznická role používají jeden účet i jedno heslo; reset obě role revokuje společně; zbývá produkční ověření doručování e-mailu |
| M2.6 integrovaná akceptace | probíhá | 72 % | backup, shop lifecycle, A02, A05 a jednotný master účet mají browser důkaz; zbývají KIS scénáře A06–A08/A10 a závěrečná brána |

Orientační stav celého M2: **56 %**. Nezapočítává produkční deploy ani ostrou
migraci, které mají vlastní pozdější bránu.

## Implementační pořadí

### M2.0 – zpětná vazba vlastníka

1. Na `http://localhost/evidencePavel/testovaci_scenare.php` projít A01–A10.
2. U každého problému uvést scénář, roli, očekávání a skutečný výsledek.
3. Roztřídit výsledek na chybu M1, UX úpravu nebo nový požadavek M2.
4. Chyby bezpečnosti, oprávnění, peněz nebo kapacity mají přednost před další
   funkcí.

Brána: každá připomínka má vlastníka, prioritu a cílový řez M2.

Dokončený veřejný a identitní řez:

- katalog e-shopu, produkt, kroužky/události, hodiny velodromu a zveřejněný
  rozvrh tréninků jsou čitelné bez registrace,
- vložení do košíku, přihláška nebo rezervace přesměrují nepřihlášeného uživatele
  na jednotné přihlášení,
- trenérský účet se bezpečně propojí s právě jedním zákaznickým profilem podle
  trenérského ID nebo stejného e-mailu; nevzniká druhá registrace,
- heslo trenéra je pro propojený účet kanonické a samoobslužný reset aktualizuje
  a revokuje obě role v jedné transakci,
- omezený přístup dítěte zůstává samostatnou bezpečnostní identitou; nejde o
  duplicitní registraci dospělého ani o trenérské oprávnění,
- veřejný rozvrh vybírá jen datum, čas, název, kategorii, skupinu a sportoviště;
  docházku, osoby, interní popis a poznámky vůbec nenačítá.

První dokončený UX řez (`25830e1`):

- kořenový `index.php` je společná homepage projektu, ne pouze přihlašovací brána
  Evidence,
- návštěvník vidí tři srozumitelné cesty: e-shop a služby, rodinu/sportovce a
  trenéry/vedení klubu,
- rodičovský a sportovní účet dostanou vlastní přímý vstup bez směšování oprávnění,
- trenérovi zůstává původní dashboard a nahoře přibyly rychlé volby Evidence,
  KIS/soupisky, objednávky a veřejný portál,
- veřejná navigace nabízí e-shop, kroužky/události a velodrom; administrativa
  zůstává oddělená,
- veřejný HTTP průchod vrátil 200 a správný titul, přihlášený browser ověřil čtyři
  trenérské zkratky bez konzolové chyby; plná sada je 336/3004 a audit 0.

Druhý dokončený UX řez (`18deb9c`):

- omezený účet sportovce má na `Můj sport` souhrn vlastních tréninků, soupisek,
  událostí a plateb a bezpečný návrat na společnou homepage,
- technické stavy členství, soupisky, přihlášky a platby jsou převedené do
  srozumitelné češtiny; stejně tak typ klubové události a datumy,
- browser potvrdil, že podvržené `sportovec_id=999999` nezmění zobrazenou osobu,
  a společná homepage zachová sportovní režim bez trenérského dashboardu,
- zaměřená sada prošla 11/73, plná sada 337/3014, first-party lint 367 souborů,
  migrace 37/37 a Composer audit bez nálezu.

Dokončený provozní řez A05 (`8647bce`):

- seed při resetu ponechá právě jednu kanonickou localhost identitu přechodu;
  starší syntetický duplikát bezpečně archivuje a ukončí jen jeho aktivní testovací
  vazby, přístupy a soupisky,
- browser prošel read-only náhled stejné osoby z kroužku do U17 2027, věkovou
  kontrolu, povinný důvod a explicitně potvrzený auditovaný zápis,
- opakování s novým náhledem nad již aktivním cílem je pravdivý no-op: nevznikne
  druhé členství ani další auditní běh a UI to výslovně oznámí,
- demo bylo po testu vráceno před přechod: jedna kanonická osoba, jeden aktivní
  zdrojový kroužek a žádné aktivní cílové U17 2027,
- zaměřená sada prošla 9/80, plná sada 338/3025, first-party lint 367 souborů,
  migrace 37/37 a Composer audit bez nálezu.

### M2.1 – provoz klubové akce

Dokončeno v `1b4d9e1`:

- admin-only export přes POST + CSRF,
- stabilní kontrakt `m2.event-participants.v1`,
- oddělení dat jednotlivých akcí,
- stav potvrzeno / čeká na platbu / čekací listina / zrušeno,
- neutralizace tabulkových vzorců,
- audit počtu řádků a rozpadu stavů bez ukládání exportovaných osobních údajů.

Zbývající produktová kontrola: vlastník otevře CSV v běžném tabulkovém programu
a potvrdí, zda sloupce odpovídají práci trenéra.

### M2.2 – chyby a UX z A01–A10

Každá oprava je malý samostatný řez s reprodukcí, regresním testem a browser
důkazem. Tento řez nesmí současně měnit finanční nebo migrační pravidla.

Brána: žádná otevřená HIGH chyba; MEDIUM chyby mají rozhodnutý termín nebo
výslovné přijetí rizika; všechny opravy jsou znovu prokliknuté na localhostu.

### M2.3 – bezpečná zkouška jednorázové migrace KIS

Dokončený technický řez M2.3a:

- migrace `20260804233000_kis_import_source_artifacts`,
- tabulka neměnných metadat zdroje a manifest preview běhu,
- soubor archivu je uložen pouze mimo webroot pod odvozeným storage key,
- v databázi je jen typ, verze kontraktu, hash, velikost, původní název a klíč;
  celý raw obsah se do DB nekopíruje,
- CLI `bin/kis-archive-source.php` je localhost-only, výchozí dry-run a zápis
  vyžaduje `--confirm-archive`,
- opakovaný archiv stejného zdroje je idempotentní a existující soubor se před
  přijetím znovu ověří hashem a velikostí.

Tento řez zatím nepovoluje promote ani ostrý import.

1. Získat anonymizovaný vzorek přesně stejného formátu jako budoucí finální
   export KIS a určit stabilní externí ID osoby.
2. Uložit raw vstup jako neměnný artefakt s hashem, časem a verzí kontraktu.
3. Dry-run musí pro každý řádek vrátit `create`, `exact_match`, `conflict`,
   `ambiguous`, `invalid` nebo `missing_without_archive` a vysvětlit důvod.
4. Slabá shoda jen podle jména nebo e-mailu se nikdy automaticky nepotvrdí.
5. Připravit explicitní promote s fingerprintem preview, transakcí, auditem a
   idempotencí; v M2 pouze nad izolovanou testovací DB.
6. Připravit kompenzační rollback/obnovu a paritní report osob, členství,
   soupisek a platebních předpisů.

Brána: 100 % řádků má vysvětlený výsledek, konflikty se nepřepisují, opakovaný
dry-run je stejný a žádný testovací běh nezmění produkci.

### M2.4 – provozní zkouška e-shopu

Dokončený technický řez M2.4a (`b4898f1`):

- katalog sdružuje varianty do jedné produktové karty a ukazuje cenové rozpětí,
- přihlášený zákazník má detail pouze aktivního publikovaného zboží,
- detail používá výhradně schválený veřejný název a souhrn; importované
  `description_html_untrusted` ani `short_description` nezobrazuje,
- obrázek musí mít validní absolutní HTTPS URL bez přihlašovacích údajů a načítá
  se bez referreru; neplatné, HTTP a jiné schéma se zahodí,
- detail rozlišuje skladové varianty a vyprodanou variantu nepovolí vložit,
- kroužek ukazuje pouze variantu svázanou s aktivní nabídkou a schová původní
  importovaný obrázek zboží, který by po změně významu produktu klamal.

Dokončený technický řez M2.4b (`838793d`):

- kupón má neměnný auditovaný rozsah jako kombinaci zboží, kroužků, placených
  událostí a velodromu,
- výchozí a migrační hodnota je pouze běžné zboží; žádná služba se nezlevní bez
  výslovného zaškrtnutí administrátorem,
- minimum i sleva se počítají jen z povolených kategorií, zatímco konečná cena
  objednávky stále zahrnuje celý košík,
- neznámý rozsah, nesouhlasící rozpad částek a kupón bez vhodné položky selžou
  bezpečně bez připojení kupónu,
- redemption ukládá způsobilý mezisoučet a snapshot rozsahu pro pozdější audit.

Dokončený hardening řez M2.4c (`b5f3f3f`):

- opakovaný nákup stejného programu po stornu a potvrzené vratce vytváří novou
  historickou účast, ale databáze dovolí jen jednu aktivní účast dítěte v nabídce,
- automaticky se obnoví pouze soupiska původně spravovaná e-shopem; dříve ručně
  ukončené členství automat nepřepíše,
- kapacita placeného velodromu započítá i platnou legacy rezervaci bez tokenu,
- checkout placené události sčítá všechny děti stejné události v jednom košíku,
  takže nemůže jednou transakcí obsadit více míst, než zbývá,
- reálná localhost MariaDB ověřila migraci i podpůrné/unikátní indexy; SQLite
  integrační sada kryje všechny tři regresní scénáře.

Zbývá:

1. Projít zboží i každou službu přes košík, neměnný cenový snapshot, QR,
   ruční testovací úhradu, výdej/aktivaci, storno, expiraci a refundaci.
2. Ověřit přehled objednávek rodiče i správce a srozumitelné chybové stavy.

Brána: žádná dvojitá objednávka, záporný sklad ani překročená kapacita; storno a
expirace vracejí zdroje právě jednou; každý peněžní stav je auditovatelný.

### M2.5 – přístup a obnova účtu

1. Doplnit samoobslužnou žádost o reset bez enumerace účtů.
2. Použít hashovaný, jednorázový a expirovaný token; po změně hesla revokovat
   staré relace.
3. Dokončit permission cache tak, aby revokace role nebo vazby platila ihned.
4. Otestovat rodiče s více dětmi, samostatný dětský účet, cizí osobu, zrušenou
   vazbu a souběžné použití tokenu.

Brána: IDOR a tokenové regresní testy jsou zelené a žádná odpověď neprozradí,
zda účet existuje.

### M2.6 – integrovaná akceptace

Dokončený první provozní důkaz (`fdbe30c`):

- zálohovací ownership kontrakt `.8` zahrnuje také `password_reset_tokens`,
- čerstvá záloha `evidence_2026-08-04_104915_2404dfc4.sql.gz` vznikla mimo webroot,
- manifest potvrzuje 125 vlastněných tabulek a 2 triggery,
- SHA-256 zálohy je
  `a7382f999126595fbbabffc99c7f5e926c0a134600fcf8659f167c949a0174a9`,
- zaměřený kontrakt zálohy prošel 2 testy / 41 assertions.

Dokončený první browser řez (`4090bdc`):

- bezpečnostní rotace profilových tokenů už nerozbije identitu demo sportovce A05,
- veřejný profilový token zůstává náhodný a interní demo identita se hledá stabilním
  localhost e-mailem,
- opakovaný seed dvakrát po sobě zachoval stejné ID rodiče, dětí, sportovního účtu,
  A05 sportovce i cílené události,
- A01 v prohlížeči ukázalo právě dvě děti a samostatný vlastní profil rodiče,
- A03+A04 vytvořilo objednávku `KP2608040ECDA87D7D`, zobrazilo QR `NEPLATIT`,
  auditovaně přijalo syntetickou platbu a přesně jednou aktivovalo účast i školní
  soupisku,
- plná sada před závěrečným seed cleanupem měla 334 testů / 2977 assertions;
  finální zaměřený test seedu prošel 2/25 a živý seed i browser průchod jsou zelené.

Dokončený druhý browser řez (4. 8. 2026, bez změny kódu):

- placená událost odmítla duplicitní aktivní přihlášku bezpečnou srozumitelnou
  odpovědí,
- storno zaplacené události `KP2608047D5C1C6050` zrušilo přihlášku, vrátilo
  kapacitu na 3/3 a až samostatná potvrzená vratka
  `LOCALHOST-M26-EVENT-REFUND` uzavřela platbu jako `refunded`,
- bezplatný velodrom se rezervoval a po stornu vrátil kapacitu z 2/3 na 3/3,
- placený velodrom `KP260804813B7DE01C` držel kapacitu už ve stavu čekání na
  platbu, po úhradě rezervaci potvrdil, po stornu ji uvolnil na 1/1 a vratka
  `LOCALHOST-M26-VELO-REFUND` uzavřela finanční stav,
- skladové zboží `KP26080452226EF4BA` snížilo sklad varianty 157/MOD2 z 2 na 1;
  storno nezaplacené objednávky jej vrátilo přesně na 2 bez požadavku na refund,
- browser konzole nezaznamenala žádnou chybu a závěrečné read-only DB ověření
  potvrdilo shodu objednávek, plateb, rezervací, přihlášky i skladu.

- deterministický seed lze bezpečně spustit opakovaně,
- migrace jsou aktuální a idempotentní na podporovaných DB,
- plná testovací sada, first-party PHP lint a dependency audit jsou zelené,
- A01–A10 a nové scénáře M2 projdou v prohlížeči bez ručního SQL,
- vznikne čerstvá localhost záloha a ověření její struktury,
- `SESSION_HANDOFF.md` obsahuje přesný aktuální stav a další jedinou akci.

## Co lze dělat souběžně

- vlastník může procházet A01–A10, zatímco probíhá read-only externí revize,
- po revizi lze samostatně připravovat KIS kontrakt a návrh produktového detailu,
- implementace M2.3, M2.4 a M2.5 se může dělit pouze při jasném vlastnictví
  souborů a migrací; sdílený `includes/shop_checkout.php`, auth bootstrap,
  migrační katalog a plánové dokumenty mají vždy jednoho editora.

## Výslovně blokované oblasti

- reward/cash wallet do potvrzení D-009 a účetních pravidel,
- automatické potvrzení Fio a Stripe do samostatné finanční akceptace,
- TrainingPeaks do potvrzení integračního rozsahu a vlastnictví dat,
- ostrý import a vypnutí KIS/Shoptetu do samostatného cutover plánu,
- produkční deploy nebo produkční DB změna bez výslovného souhlasu vlastníka.

## Brána celého M2

- vlastník prošel A01–A10 a připomínky jsou vypořádané nebo zařazené,
- trenér umí připravit akci, pracovat s účastníky a bezpečně je exportovat,
- rodič projde kroužek, placenou událost, objednávku a storno bez zásahu do DB,
- KIS migrační dry-run vysvětlí každý řádek a nemění produkční data,
- přihlášení, obnova účtu a oprávnění mají automatické IDOR/regresní testy,
- full test, lint, migration check, seed, backup a browser průchod jsou zelené.
