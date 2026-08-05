# 10 – Milník M2: provozní pilot na localhostu

Stav: kanonický pracovní plán druhého produktového milníku

Aktualizováno: 5. 8. 2026

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
| M2.3 zkouška migrace KIS | probíhá | 99 % | cílový kontrakt, staging i auditovaný localhost promote/rollback jsou hotové; zbývá reprezentativní anonymizovaný export a finální test cutover postupu |
| M2.4 provozní e-shop | technicky hotovo | 97 % | detail, kupóny, klubové ceny podle soupisek, kapacity, opakovaný nákup a měnová hranice jsou uzavřené; zbývá vlastníkova úplná provozní zkouška |
| M2.5 přístup a obnova účtu | technicky hotovo | 96 % | trenérská a zákaznická role používají jeden účet i jedno heslo; reset obě role revokuje společně; zbývá produkční ověření doručování e-mailu |
| M2.6 integrovaná akceptace | probíhá | 98 % | technické a browser důkazy jsou připravené, výsledky A01–A10 lze ukládat a exportovat; zbývá vlastníkův průchod a vypořádání připomínek |
| M2.7 hodnota pro členy | probíhá | 94 % | veřejný a rodinný ICS feed, opt-in připomínky, admin obsluha, náhled a localhostový průchod Čeká → Odesláno do chráněného outboxu jsou hotové; zbývá akceptace v reálném kalendáři, schválení textu a kontrolované doručení na určenou testovací adresu |

Orientační stav celého M2: **86 %**. Nezapočítává produkční deploy ani ostrou
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

Dokončený technický řez M2.3b (`26076ba`):

- migrace `20260804234500_kis_import_preview_integrity` ukládá verzi kontraktu,
  stabilní fingerprint, bezpečný JSON report a počty klasifikovaných/blokujících řádků,
- každý řádek skončí právě jednou jako `create`, `exact_match`, `conflict`,
  `ambiguous`, `invalid` nebo `missing_without_archive`,
- chybějící archiv, neúplná identita, slabá/rozporná shoda a duplicate target jsou
  fail-closed blokátory; report nepřenáší jména, kontakty ani zdrojové identity,
- fingerprint nezávisí na ID běhu a je stejný pro stejný archiv a stejné pořadí
  klasifikací; celý preview vzniká atomicky s importním během,
- synchronizační centrum ukazuje integritu, blokátory a nabízí `no-store` JSON export,
- localhost-only CLI seed vyžaduje `--confirm-seed`, je idempotentní a vytvořil
  browserově ověřený běh #7 s 2/2 řádky a nulou blokátorů,
- promote ani zápis do profilů sportovců stále není implementovaný.

Dokončený technický řez M2.3c (`5caa850`):

- localhost administrátor může z detailu preview provést explicitně potvrzený
  promote do tří oddělených `kis_import_sandbox_*` tabulek,
- služba znovu přepočítá preview, kontroluje uložený i odeslaný fingerprint,
  úplnost, nulové blokátory, povolené akce, důvod, roli a localhost hranici,
- promote je transakční, idempotentní a auditovaný; reapply po rollbacku zachová
  stejný promotion záznam a přidá pravdivou auditní událost,
- rollback deaktivuje pouze sandbox položky a zůstává dostupný i při pozdějším
  driftu preview; žádný SQL zápis do sportovců, soupisek, plateb nebo objednávek neexistuje,
- browser nad run #7 ověřil stav 2/2 aktivní + 1 událost a následný rollback
  0/2 aktivní + 2 události; první průchod odhalil a opravil chybějící načtení CSRF helperu,
- vynucené selhání uprostřed položek nezanechá promotion, položku ani auditní událost,
- plná sada prošla 369 testy / 3254 assertions, syntaxe 396 first-party PHP souborů,
  migrace 41/41 a Composer audit je bez nálezu.

Samotný řez M2.3c ověřuje orchestrace, oprávnění a kompenzaci. Není náhradou za
navazující field/external-ID kontrakt a neumí provést ostrý cutover.

Dokončený technický řez M2.3d (`2bcb346`):

- migrace přidává samostatné unikátní `sportovci.kis_external_id`; UCI licence se
  jako interní KIS identita nikdy nepoužívá,
- verzovaný `kis-import-field-v1` vyžaduje stabilní ID v exportu uživatelů,
  plateb i soupisek, kontroluje hlavičky, duplicity, rozpory identity a platební vazby,
- report neobsahuje osobní údaje ani hodnoty externího ID; ukládá jen pořadové
  reference, pevné důvody, počty a deterministický fingerprint,
- matcher upřednostní přesné KIS ID i po změně jména, ale rozdílné datum narození
  zůstává konfliktem; platby lze bezpečně spojit podle KIS ID i bez jména,
- skutečný upload archivuje všechny tři zdroje mimo webroot a kanonická synchronizace
  je fail-closed blokována, pokud field kontrakt není úplný,
- localhost run #8 prošel 2/2 bez blokátoru; browser ověřil promote 2/2, rollback
  0/2 a blokaci starého run #7 bez stabilního ID,
- plná sada prošla 377 testy / 3308 assertions, syntaxe 398 first-party PHP souborů,
  migrace 42/42 a Composer audit je bez nálezu.

Zbývá zdrojová akceptace: anonymizovaný reprezentativní export musí potvrdit,
který podporovaný alias KIS ID a které další hlavičky budou ve finálním jednorázovém
exportu. Teprve potom lze přijmout uložený paritní report jako podklad a plánovat
ostrý cutover.

Dokončený technický řez M2.3e (`95693a2`):

- nový `kis-import-parity-v1` atomicky vzniká s importním během a je svázaný
  fingerprinty preview i field kontraktu,
- porovnává osoby, aktivní členství, textový snapshot soupisek a agregované
  platební signály; report neobsahuje jména, KIS ID ani peněžní hodnoty,
- nové osoby, rozdíly, konflikty a nejednoznačnosti jsou fail-closed blokátory;
  chybění existující osoby v jednom běhu zůstává pouze informační a nic nearchivuje,
- report výslovně odhalil nepokrytou doménu jednotlivých členských platebních
  předpisů místo falešného tvrzení o úplné paritě,
- run #9 má dvě nové osoby, dvě přiřazení soupisek, dva platební řádky a tři
  blokátory; browser ověřil UI a sandbox 2/2 → 0/2,
- plná sada prošla 379 testy / 3332 assertions, syntaxe 401 first-party PHP
  souborů, migrace 43/43 a Composer audit je bez nálezu.

Dokončený technický řez M2.3f (`d69ee4f`):

- `member-charge-v1` odděluje členský předpis od skutečné peněžní transakce;
  cílové tabulky drží příjemce, volitelného plátce, cenu, měnu, stav, zdrojovou
  idempotenci a auditní události,
- platební export musí obsahovat stabilní ID předpisu i částku; prázdná či záporná
  částka je fail-closed blokátor a nevytvoří stagingový řádek,
- jednotlivé předpisy se ukládají atomicky do `kis_import_payment_rows`; duplicitní
  ID nebo nekonzistentní projekce vrátí celý import bez částečných dat,
- paritní report porovnává pouze souhrnné počty staging/cíl a nevypisuje ID ani
  peněžní hodnoty; do cíle zatím nic automaticky nepromuje,
- localhost run #12 má 2 stagingové předpisy, 2 čekající na přenos a celkem 3
  pravdivé blokátory; browser zobrazení prošlo a předchozí průchod ověřil sandbox
  2/2 → 0/2,
- plná sada prošla 388 testy / 3369 assertions, syntaxe 406 first-party PHP
  souborů, migrace 44/44 a Composer audit je bez nálezu.

Dokončený technický řez M2.3g (`7c8b444`):

- localhost administrátor může s důvodem a výslovným potvrzením transakčně přenést
  pouze přesně spárované stagingové předpisy nad čerstvým paritním fingerprintem,
- každý přenos má vlastní promotion, položky a auditní události; opakování je
  idempotentní a reapply po rollbacku zachová auditní identitu,
- uhrazený předpis vytvoří samostatný historický `payments` záznam, otevřený předpis
  žádnou platbu nevytváří; částečné úhrady, nulové částky a chybějící datum úhrady
  jsou fail-closed,
- rollback před mazáním ověří nezměněný předpis, platbu i auditní historii a odstraní
  pouze cíle vytvořené tímto přenosem; jakýkoli následný zásah rollback zablokuje,
- browser run #13 ověřil 2/2 aktivní předpisy, jednu historickou platbu a nulové
  paritní blokátory, následně bezpečný rollback na 0/2 a 0 plateb se dvěma auditními
  událostmi,
- plná sada prošla 391 testy / 3430 assertions, syntaxe 408 first-party PHP
  souborů, migrace 45/45 a Composer audit je bez nálezu.

Kontrolní audit zálohy je opraven v `281fcd0`: ownership kontrakt `.9` zahrnuje
všechny trvalé tabulky z migračního katalogu včetně M2.3g a klubových cen. Nový
generický PHPUnit test zastaví další drift a MariaDB CI skutečně vytvoří zálohu,
ověří její manifest a přítomnost všech 12 dříve chybějících tabulek. Izolovaný
smoke prošel s 90 tabulkami; plná sada má 393 testů / 3496 assertions, syntaxe
409 PHP souborů, migrace 45/45 a Composer audit bez nálezu. Starý agregovaný
platební signál se navíc převádí na haléře přesně bez násobení typu float.

1. Získat anonymizovaný vzorek přesně stejného formátu jako budoucí finální
   export KIS a potvrdit podporovaný alias stabilního externího ID osoby.
2. Uložit raw vstup jako neměnný artefakt s hashem, časem a verzí kontraktu.
3. Dry-run musí pro každý řádek vrátit `create`, `exact_match`, `conflict`,
   `ambiguous`, `invalid` nebo `missing_without_archive` a vysvětlit důvod.
4. Slabá shoda jen podle jména nebo e-mailu se nikdy automaticky nepotvrdí.
5. Na reprezentativním anonymizovaném exportu zopakovat explicitní promote i
   kompenzační rollback a přijmout výsledný paritní report.
6. Teprve v samostatném schváleném kroku připravit ostrý cutover, retenční dobu
   importních artefaktů a nevratný provozní bod rozhodnutí.

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

Dokončený řez klubových cen M2.4d (`e67eed8`):

- veřejný návštěvník vždy vidí původní cenu a na detailu výzvu „Přihlásit pro
  zobrazení klubové ceny“,
- účet rodiče nebo sportovce získá oprávnění z aktuálních schválených vazeb a
  aktivních soupisek; při více soupiskách se použije nejnižší výsledná cena,
- soupiska může mít procentní nebo pevnou slevu pro celou kategorii a přesnou
  cenu konkrétního produktu; přesná cena má uvnitř stejné soupisky přednost,
- klubová cena nikdy nezvýší veřejnou cenu a všechny změny pravidel jsou
  auditované včetně důvodu a správce,
- košík, kupón i checkout počítají stejnou serverovou cenu; změna pravidla po
  zobrazení košíku změní fingerprint a vyžádá nové potvrzení,
- objednávka ukládá použitou jednotkovou cenu jako neměnný snapshot,
- localhost seed připraví pro první publikovaný produkt desetiprocentní cenu
  soupisky `LOCALHOST U15 2026`; veřejný, rodičovský i administrační HTTP průchod
  byl ověřen.

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

Dokončený A06 řez (`dde0f3e`):

- localhost-only admin průvodce spojil tři dříve oddělené operace do jednoho
  souhrnného náhledu a jednoho potvrzeného průchodu,
- fingerprinty se kontrolují znovu před zápisem a každý dílčí rollover zůstává
  auditovaný a idempotentní,
- browser provedl U15 → U17, přenos dráhové disciplíny a U13 → U15; výsledek byl
  3 přesunutí a 2 zachované individuální výjimky,
- v každé cílové soupisce vzniklo právě jedno aktivní členství a opakované otevření
  už nenabídlo další zápis,
- seed po skutečném průchodu odstranil pouze syntetické A06 běhy, deaktivoval
  testovací cíle a znovu připravil tři čekající náhledy pro další testování,
- plná sada prošla 349 testy / 3081 assertions, first-party lint 379 souborů,
  migrace 38/38 a Composer audit je bez nálezu.

Dokončený A07 řez (`03774db`):

- běžný trenér může zaevidovat jen vlastní plán, hlavní trenér libovolný dostupný
  plán a server nepovolí změnit plánované datum,
- snapshot cílových soupisek a očekávaných členů se při uložení atomicky kopíruje
  ke skutečnému tréninku, ale skutečná docházka zůstává ručním rozhodnutím trenéra,
- localhost průvodce porovnává očekávané a skutečné účastníky a zvlášť ukazuje
  chybějící a neočekávané osoby,
- browser zaevidoval plán jako trénink 528 a potvrdil očekávaní/skuteční/chybějící/
  neočekávaní 1/1/0/0; sportovec viděl stejný trénink ve svém omezeném přehledu,
- opakovaný seed zachoval historický trénink a připravil nový čistý plán 3,
- plná sada prošla 354 testy / 3130 assertions, syntaxe 384 first-party PHP souborů,
  migrace 39/39 a Composer audit je bez nálezu.

Dokončený A08 řez (`6ae75c1`):

- rodičovské UI nabídlo u události cílené na U15 a dráhu oprávněné dítě právě
  jednou a ostatní schválené osoby jako neoprávněné vůbec nenabídlo,
- browser vytvořil jednu potvrzenou přihlášku; databáze u stejné osoby doložila dvě
  vyhovující soupisky, nikoli dvě přihlášky,
- další spuštění localhost seedu přihlášku nemaže, ale auditovaně ji stornuje pouze
  v rámci syntetické události a demo rodiče, takže scénář lze bezpečně opakovat,
- plná sada prošla 355 testy / 3133 assertions, syntaxe 386 first-party PHP souborů,
  migrace 39/39 a Composer audit je bez nálezu.

Dokončený A10 řez (`4ce0f17`):

- browser u demo sportovce spojil auditované změny přístupu, objednávek, soupisek
  a přihlášek na události a u každé zobrazil skutečně uloženého aktéra a důvod,
- ověřená A08 registrace i následné auditované storno seedu jsou ve společné ose
  právě jednou a odkazují na příslušnou správní oblast,
- seed nyní ověří hash demo hesla a reset zapíše pouze při skutečné změně; dva
  bezprostřední běhy ponechaly počet historických resetů beze změny 26 → 26,
- plná sada prošla 355 testy / 3135 assertions, syntaxe 386 first-party PHP souborů,
  migrace 39/39 a Composer audit je bez nálezu.

Přijatý výsledek nezávislé AI revize (`ef5ec21`):

- závěry o zastaralém handoffu, nefunkčním detailu produktu, chybějící klubové ceně
  a chybových hláškách vznikly ze zastaralé bridge kopie a živá kontrola je vyvrátila,
- potvrzený nedostatek byl pouze v CI: existující MariaDB smoke skripty se nespouštěly,
- nový oddělený job používá MariaDB 11.4 a `pdo_mysql` a spouští child-access i KIS
  transition/idempotency smoke na izolovaných testovacích databázích,
- oba MariaDB smoke testy prošly lokálně, plná sada má 356 testů / 3142 assertions,
  syntaxe 386 first-party PHP souborů, migrace 39/39 a Composer audit je bez nálezu.

Dokončený nástroj vlastníkovy brány (`875c9e3`):

- každá karta A01–A10 ukládá výsledek, důležitost a pozorované/očekávané chování,
- zápis je pouze na loopbacku, vyžaduje administrátora a CSRF a používá omezené
  enumy, délky, ochranu proti symlinku a zamčený JSON zápis,
- lokální soubor je ignorovaný Gitem; export vytvoří kontrolovatelný Markdown pro
  Cowork nebo vědomý commit bez automaticky načtených hesel a osobních dat,
- browser ověřil 0/10 → PASS 1/9 → zpět na čistých 0/10 včetně reloadu a flash zprávy,
- `CLAUDE.md` už netvrdí, že jde o submodule Velocoty, a odkazuje na krátký
  `docs/CURRENT_STATE.md` jako aktuální vstup pro externí AI,
- plná sada prošla 358 testy / 3156 assertions, syntaxe 388 first-party PHP souborů,
  migrace 39/39 a Composer audit je bez nálezu.

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

### M2.7 – veřejný a osobní kalendář

Přijatý první řez z funkčního auditu je veřejný ICS kalendář. Je anonymní a
obsahuje jen údaje, které už aplikace zveřejňuje na veřejných stránkách. Nemění
databázi a nevytváří novou autorizační hranici.

Navazující osobní rodinný kalendář je hotový technicky v `004e4a6`. Používá
256bitový náhodný token, v databázi drží jen jeho SHA-256 otisk a posledních
osm kontrolních znaků. Vytvoření, rotace a zrušení jsou auditované; aktuální
vazby na profily se ověřují při každém načtení a ID osoby se z URL nepřijímá.
Feed obsahuje cílené tréninky aktivních soupisek, přihlášené termíny akcí,
rezervace a splatnosti členských předpisů. HTTP smoke ověřil platné ICS s
200 a stejný odkaz po revokaci s 404; testy ověřují i izolaci rodin. Zbývá
produktová zkouška odběru a aktualizace v reálném Google/Apple kalendáři.
Další nápady, jejich pořadí a blokátory jsou v `11-backlog-hodnota-pro-cleny.md`.

Třetí řez `29e3d5d` přidává dobrovolné připomínky splatnosti. Uživatel je
zapíná s předstihem 3, 7 nebo 14 dní. Jedna kombinace účet–předpis se zařadí
právě jednou, stav předpisu i opt-in se kontrolují před převzetím zprávy a
stejnému účtu nelze odeslat více než jednu zprávu za 20 hodin. Selhání se
opakuje nejvýše pětkrát a každá změna je auditovaná. Odkaz v e-mailu vede na
přihlášený rodinný přehled bez ID osoby nebo platby v URL. CLI umí oddělené
`--generate` a `--send`; produkční CRON ani skutečné odesílání zatím nejsou
schválené. Localhost browser ověřil zapnutí a vypnutí a skončil s vypnutým
opt-in; nebyl odeslán žádný skutečný e-mail.

Provozní dokončení `66b4241` a `68e1199` přidává admin-only přehled pěti stavů,
auditované ruční vrácení do fronty, no-store náhled přesně uloženého předmětu a
těla zprávy a explicitní CLI transport `--transport=local-outbox`. Testovací
transport je povolen jen pro `localhost`/`127.0.0.1`, ukládá zprávy do ignorovaného
adresáře `var/member-charge-reminder-outbox`, který Apache vrací jako 403, a
nikdy nevolá skutečný mail transport. Produkční CRON a skutečné odesílání jsou
nadále vypnuté. Před jejich aktivací zbývá schválit text a kontrolovaně ověřit
doručení na jednu určenou testovací adresu.

Localhost akceptační doplněk `5843f70` zpřístupňuje na stejné administrátorské
stránce potvrzené tlačítko „Připravit ukázku“. Pouze na localhostu opakovatelně
vytvoří nebo obnoví syntetický předpis `LOCAL-REMINDER-001`, zapne připomínky
syntetickému účtu `rodic@localhost.test`, vloží právě jednu čekající zprávu a
otevře její náhled. Předpis, opt-in i zpráva mají audit aktéra. Produkce akci
nenabízí ani nepřijme a žádný transport se při ní nevolá.

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
