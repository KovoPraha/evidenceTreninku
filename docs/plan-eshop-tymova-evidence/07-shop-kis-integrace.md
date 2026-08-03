# 07 – Plán napojení e-shopu na členské a KIS funkce

Stav: K1 pracovní katalog i řízená aktivace běžného zboží jsou implementované;
K2 identita a claim jsou implementované. K3 má první celý průchod bezplatným
kroužkem. Evidence zůstává samostatnou aplikací; Velocota
není součástí tohoto propojení.

## Cíl

E-shop nebude všechny položky zpracovávat jako obyčejné zboží. Podle typu
nabídky předá potvrzený nákup nebo bezplatnou registraci správné doméně:

| Typ nabídky | Cílová funkce |
|---|---|
| `goods` | produkt, sklad, osobní výdej a později doprava |
| `club_event` | kroužek; přihláška konkrétního dítěte nebo sportovce |
| `camp` | jednorázová akce s termínem, účastníkem, kapacitou a souhlasy |
| `bookable_service` | výběr termínu služby a rezervace účastníka |
| `rental` | evidence vydání a vrácení konkrétní půjčované věci |
| `bookable_rental` | rezervace zdroje v čase; například pronájem velodromu |
| `custom_quote` | poptávka a individuální nabídka, nikoliv běžný checkout |

Katalog pouze popisuje, co lze pořídit. Členská evidence vlastní osoby, rodinné
vztahy a soupisky. KIS zůstává během přechodu externím zdrojem pro porovnání a
export, nikoliv databází, do které checkout přímo zapisuje.

## Klíčové oddělení osob

U kroužku, kempu nebo kurzu jsou zpravidla dvě různé role:

- **objednatel** – přihlášený účet rodiče nebo dospělého,
- **účastník** – dítě či sportovec vedený v členské evidenci.

Objednávka proto nesmí mít pouze jedno `user_id`. Přihláška uloží účet
objednatele i samostatný identifikátor účastníka. Rodič smí vybrat dítě až po
ověřené vazbě `guardian`; shoda e-mailu nikdy sama nevytvoří vztah ani nesloučí
osoby.

## Navržený tok pro kroužek

1. Administrátor publikuje schválený katalogový produkt a propojí jej s klubovou
   akcí nebo novým během kroužku.
2. Rodič otevře nabídku a vybere dítě ze svých ověřených vazeb.
3. Server ověří věk, cílovou skupinu, kapacitu, duplicitu přihlášky a platnost
   vztahu rodič–dítě.
4. V jedné transakci vznikne návrh přihlášky a případný platební předpis.
5. Bezplatná nabídka může být rovnou potvrzena; placená čeká na společnou
   platební vrstvu.
6. Až potvrzený stav se objeví v klubové soupisce. Během shadow režimu se změna
   pouze zahrne do KIS paritního reportu nebo explicitního exportu.
7. Žádný neúspěšný checkout, návrat z platební brány ani samotný e-mail nesmí
   vytvořit či přepsat člena v KIS.

## Pronájem velodromu a půjčovna

`bookable_rental` potřebuje skutečně blokovat sportoviště. Stávající
`individualni_lekce` se pro tento účel nepoužijí, protože záměrně neodečítají
kapacitu sportoviště. Nová rezervační služba bude pracovat s kanonickým zdrojem,
časovým intervalem a transakční kontrolou překryvu proti interním rezervacím.

Bezplatné praktické zápůjčky kol (`rental`, cena nula) nepotřebují platbu, ale
potřebují konkrétní kus, stav `reserved → handed_over → returned` a audit osoby,
která výdej provedla. Kauce, odpovědnost a předávací protokol jsou produktová
rozhodnutí před ostrým spuštěním.

## Implementační etapy

### K1 – Kanonický katalog a explicitní převod stagingu

- tabulky `shop_products`, `shop_variants`, ceny a obrázky,
- vazba na schválený stagingový kandidát a audit publikace,
- opakovatelná publikace podle stabilního klíče a SKU,
- žádný checkout ani KIS zápis.

Brána: stejný staging nelze převést dvakrát, vyřazené a nezkontrolované
položky se nepřenášejí a změna zdroje nikdy tiše nepřepíše ruční rozhodnutí.

Implementováno: schválený běh lze jednou transakčně převést do kanonického
katalogu. Produkty a varianty vznikají ve stavu `draft`, kolize vrací celý běh
zpět a bez samostatného rozhodnutí zůstávají neaktivní.

Implementována je následná ruční aktivace jednotlivého produktu. V této etapě je
povoleno pouze `goods` s alespoň jednou neskrývanou variantou, platným SKU a
platnou pevnou nebo nulovou cenou. Administrátor zadává nový veřejný název a
prostý text popisu, výslovně potvrzuje konkrétní produkt a uvádí důvod. Aktivace,
deaktivace i reaktivace mají audit. K3 typy zůstávají blokované. Stav `active`
zatím znamená jen připravenost; storefront ani checkout stále neexistují.

### K2 – Účty, osoby a rodiče

- veřejný účet oddělený od osoby,
- vazby účet–osoba s rolemi `self` a `guardian`, platností a schválením,
- administrátorský claim workflow a historie,
- KIS matching pouze přes staged import a silné identifikátory.

Brána: rodič vidí pouze ověřené děti; dvě podobné osoby se nikdy automaticky
nesloučí.

Implementováno jako bezpečný administrátorský základ: tabulky
`account_person_roles` a `account_person_role_events` oddělují veřejný účet od
sportovce, podporují role `self` a `guardian`, platnost, zrušení i neměnnou historii
rozhodnutí. Administrace je dostupná jen roli `admin`; každé schválení a zrušení
vyžaduje textový podklad. Pro výběr účastníka se vracejí pouze aktivní schválené
vazby aktivního účtu s ověřeným e-mailem. Shoda jména či e-mailu nic automaticky
nezaloží a tato etapa nic nezapisuje do KIS.

Implementována je také uživatelská žádost o vazbu (claim) a administrační fronta.
Veřejná stránka neukazuje seznam sportovců; uživatel zadá identifikační údaje a
administrátor ručně vybere ověřenou osobu. Schválení žádosti a vytvoření vazby je
jedna transakce. Zbývá bezpečné párování KIS importu přes stabilní identifikátory.

### K3 – Klubové akce, termíny a přihlášky

- `club_events`, cílové skupiny, ceny, termíny a kapacity,
- přihláška s odděleným objednatelem a účastníkem,
- čekací listina, souhlasy a storno,
- mapování publikovaného produktu na akci nebo rezervovatelný zdroj.

Brána: kapacita, duplicita a čekací listina jsou transakční; přihláška zatím
nepotřebuje Stripe a umí i nulovou cenu.

Implementován je administrační základ K3: pracovní akce typu `club_event`
nebo `camp`, cílová skupina a věkové rozmezí, kapacita, registrační okno, cenová
politika, samostatné termíny a auditované mapování kanonického produktu. Termíny
jedné akce se nesmějí překrývat, typ a měna produktu musí odpovídat akci a
bezplatná akce nepřijme placenou variantu.

Navazuje první provozní vertikála pro `free club_event`. Administrátor otevře
přihlášky až po explicitním potvrzení. Přihlásit lze jen aktivní schválenou osobu
z K2; kontroluje se registrační okno, věk k prvnímu termínu a efektivní kapacita.
Zámek akce a unikátní klíč účastníka chrání poslední místo a duplicitu. Storno
uvolní kapacitu, ale zachová audit. Tento průchod nevytváří objednávku, platbu,
soupisku ani KIS zápis.

Souhlas a storno jsou nyní verzované. Administrátor před otevřením nastaví oba
prosté texty, verzi a budoucí deadline před prvním termínem. Rodič výslovně
potvrdí právě aktuální verzi a přihláška uloží neměnný snapshot textů, verze,
času souhlasu i storno termínu. Uživatelské storno po tomto termínu selže bez
částečného zápisu. Čekací listina a administrační výjimka pozdního storna zatím
neexistují.

### K4 – Společná objednávka a platební předpis

- košík a objednávka ukládající cenový snapshot,
- jednotný platební účel pro zboží i přihlášku,
- nejprve bankovní převod a QR; Stripe až přes podepsaný webhook,
- žádná změna členského stavu jen podle návratu uživatele z platební stránky.

Brána: objednávka, platba a přihláška mají oddělené stavové automaty a každá
externí událost je idempotentní.

### K5 – KIS shadow mode a cutover

- paritní report: osoby, soupisky, přihlášky a předpisy,
- explicitní export změn potřebných pro přechodné období,
- rozdíly se řeší v administrační frontě, nikdy automatickým mazáním,
- KIS přestane být zdrojem pravdy až po samostatném schválení cutoveru.

## Otevřená produktová rozhodnutí

Před příslušnou etapou musí vlastník potvrdit:

1. zda lze fyzické zboží koupit bez účtu,
2. kdo a jak schvaluje vztah rodič–dítě,
3. stabilní identifikátor osoby dostupný v KIS exportu,
4. storno pravidla kroužků, kempů a rezervací,
5. kapacitu a délku slotu pronájmu velodromu,
6. kauci a předávací pravidla půjčovny,
7. okamžik, kdy potvrzená přihláška vstoupí do soupisky,
8. retenční dobu importních preview a auditních dat.

## Nejbližší implementační pořadí

1. přidat čekací listinu se stejně transakčním přidělením místa,
2. doplnit auditovanou administrační výjimku pozdního storna,
3. teprve poté přidat objednávku a placenou variantu.

Stripe, Fio, kredit, Packeta a ostrý KIS cutover nejsou součástí nejbližšího
přírůstku.
