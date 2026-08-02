# 07 – Plán napojení e-shopu na členské a KIS funkce

Stav: K1 pracovní kanonický katalog je implementovaný; další je řízená aktivace
produktů a K2 identita osob. Evidence zůstává samostatnou aplikací; Velocota
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
zpět a veřejná aktivace zatím záměrně neexistuje.

### K2 – Účty, osoby a rodiče

- veřejný účet oddělený od osoby,
- vazby účet–osoba s rolemi `self` a `guardian`, platností a schválením,
- administrátorský claim workflow a historie,
- KIS matching pouze přes staged import a silné identifikátory.

Brána: rodič vidí pouze ověřené děti; dvě podobné osoby se nikdy automaticky
nesloučí.

### K3 – Klubové akce, termíny a přihlášky

- `club_events`, cílové skupiny, ceny, termíny a kapacity,
- přihláška s odděleným objednatelem a účastníkem,
- čekací listina, souhlasy a storno,
- mapování publikovaného produktu na akci nebo rezervovatelný zdroj.

Brána: kapacita, duplicita a čekací listina jsou transakční; přihláška zatím
nepotřebuje Stripe a umí i nulovou cenu.

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

1. doplnit řízenou aktivaci jednotlivých `draft` produktů pro budoucí storefront,
2. vytvořit model účet–osoba–rodič K2,
3. navrhnout klubové akce a mapování produktu na kroužek K3,
4. implementovat bezplatný kroužek jako první vertikální průchod,
5. teprve poté přidat objednávku a placenou variantu.

Stripe, Fio, kredit, Packeta a ostrý KIS cutover nejsou součástí nejbližšího
přírůstku.
