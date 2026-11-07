# Etapa 3 — sklady, prodej a vratky

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Zadání k 22. 10. 2026. Pouze příprava; bez implementace, změn skladových stavů, objednávek, zásilek a produkce.

**Skladový základ již máme: varianty zboží, auditované korekce, rezervaci při objednání, uvolnění při stornu a ochranu souběžných nákupů. Pro úplný provoz oddílu potřebujeme oddělit fyzické zásoby od rezervací, zavést příjemky a ocenění, dokončit osobní prodej a položkový výdej, vratky po předání a inventuru.**

Cílový běžný postup: příjem oblečení → nabídka podle velikostí → rezervace → platba → skutečné předání → případná výměna/vratka → skladový a účetní podklad. Kurz nebo kroužek používá kapacitu programu, nikoli sklad oblečení. Půjčování kol a vybavení zůstává mimo rozsah.

## Podklady a rozsah

Navazuje na [etapu 1](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md) a [etapu 2](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md). Zdrojový stav byl ověřen na čerstvém `origin/main`, revize `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. „Máme“ znamená implementaci v tomto zdroji; aktuální produkční nastavení a fyzická inventura nebyly při přípravě ověřovány.

Pro malý oddíl doporučuji začít jedním hlavním skladem, jasným stavem blokovaného zboží a odpovědným skladníkem se zástupcem. Další fyzická místa vést jen tehdy, pokud se tam skutečně skladuje nebo prodává. Automatický nákup u dodavatelů, skenery, řízení regálů, dopravní API a rozsáhlé skladové hospodářství nejsou podmínkou první verze.

## Co již máme a co chybí

| Oblast | Existující základ | Potřebné doplnění |
|---|---|---|
| Katalog a varianty | SKU, atributy, jednotky, ceny, členské ceny, viditelnost a správa variant | Kontrola skutečných velikostí a skladových režimů; rozlišení nuly, neevidovaného a neověřeného stavu |
| Sklad | `shop_inventory_movements`, rezervace, návrat rezervace, auditovaná ruční korekce | Fyzický stav, rezervováno, blokováno, příjemky, výdejky, ocenění a inventurní doklad |
| Checkout | Přihlášený nákup, rychlý nákup zboží bez účtu, transakční ochrana posledního kusu | Stejně bezpečný osobní prodej obsluhou a všechny jeho návaznosti |
| Rezervace | Odečet při objednání a expirace nezaplacených objednávek | Přesná evidence zbývajících rezervací, provozní dohled a řešení pozdních úhrad |
| Výdej | Zaplacená objednávka → připraveno → dokončeno, audit | Položky a skutečně předaná množství, částečné výdeje, příjemce a předávací podklad |
| Doprava | PPL/Packeta výběr místa, snímek dopravy, ruční evidence podání | Provozní přejímka používané dopravy, nedoručené/vrácené zásilky a odlišení podání od převzetí |
| Storno a vratka | Storno před dokončením, uvolnění rezervace, potvrzení celé peněžní vratky | Vratka již vydaných kusů, posouzení stavu, částečné vrácení, výměna a reklamace |
| Doklady a účetní export | Provozní zdroje a připravené zadání etapy 2 | Propojení výdeje/vratky s doklady a oceněnými skladovými daty |

### Zásadní technické rozlišení

`stock_quantity_decimal` se dnes snižuje již při vytvoření objednávky, pohyb má typ `reserve`. Běžný osobní výdej mění stav objednávky a čas dokončení; není samostatnou položkovou výdejkou. Současné číslo proto nelze bez převodu vydávat za počet kusů fyzicky v klubové skříni.

Storno přijímá stavy `placed`, `processing` a `ready`, vrací rezervované množství a případně požaduje vratku peněz. Dokončenou objednávku touto cestou stornovat nelze. Jde o ochranu stávajícího postupu, **nikoli o hotový proces vrácení zboží po jeho převzetí**. [Zdrojové podklady Z1–Z4](01_SKLADOVY_MODEL_PRIJEMKA_A_ZDROJE.md).

## Priority a návaznosti

**P0:** nutné před převzetím běžného skladového a prodejního provozu. **P1:** nutné pro úplné dokončení dohodnutého rozsahu; pokud se příslušná agenda používá již v pilotu, stává se jeho podmínkou. **P0 při použití:** podmínka konkrétního kanálu, který může zůstat vypnutý.

Odpovědnosti jsou návrhem rolí, nikoli již přidělenými lidmi.

| ID | Priorita | Úkol | Závisí na | Vlastník |
|---|---|---|---|---|
| E3-01 | P0 | Skladové položky, režimy a pravidla oddílu | E0-03; E1-02/05 | Skladník + vedení |
| E3-02 | P0 | Fyzický sklad, rezervace a pohybový deník | E3-01 | Vývoj + skladník |
| E3-03 | P0 | Ověřený počáteční stav a otevřené objednávky | E3-02; inventura zdrojů E0-04 | Skladník + hospodář |
| E3-04 | P0 | Příjem zboží, dodavatelé a pořizovací ceny | E3-02; metodika E2-01/09 | Skladník + účetní |
| E3-05 | P0 | Rezervace, expirace a souběžný prodej | E3-02; platby E2-02 | Vývoj + hospodář |
| E3-06 | P0 | Osobní prodej členům i veřejnosti | E3-01/05; E2-04/06/07 | Obsluha prodeje + hospodář |
| E3-07 | P0 | Skutečný výdej a částečné předání | E3-02/05/06 | Skladník |
| E3-08 | P0 při použití | Doprava a vrácené zásilky | E3-07; rozhraní vratek E3-09 | Expedice |
| E3-09 | P0 | Vratky, výměny a reklamace | E3-04/07; E2-08 | Skladník + hospodář |
| E3-10 | P0 | Inventura a opravy rozdílů | E3-02/03/04; pravidla E3-05/07/09 | Skladník + schvalující |
| E3-11 | P1 | Vlastní spotřeba, bezplatné výdeje a přesuny | E3-02/04/07; metodika E2-03 | Skladník + hospodář |
| E3-12 | P1 | Přehled skladu a práce obsluhy | E3-04 až E3-10 | Skladník + uživatelský garant |
| E3-13 | P0 | Skladové doklady a účetní export | E3-04/07/09/10; E2-05/11/12 | Účetní + vývoj |
| E3-14 | P0 | Oprávnění, audit a provozní dohled | E3-02; E1-05/08/09 | Správce systému + vedení |
| E3-15 | P0 | Přejímka a plán převzetí provozu | Předchozí úkoly v rozsahu pilotu; E2-14/15 | Vedení + skladník + účetní |

**Vlna A — pravdivý skladový základ:** E3-01/02, pravidla převodu E3-03, příjemky E3-04 a návrh práv E3-14. Současně dohodnout účetní rozhraní E3-13.

**Vlna B — prodej a oběh zboží:** E3-05 → E3-06/07 → E3-09; poté přejímka skutečně používané dopravy E3-08 a interních výdejů E3-11.

**Vlna C — kontrola a převzetí:** inventura E3-10, přehledy E3-12, dokončení E3-13/14 a přejímka E3-15. Návrh inventury vzniká již ve vlně A; finální přejímka kontroluje všechny typy pohybů.

## Konkrétní úkoly

### E3-01 — Položky, jednotky a pravidla skladu

**Máme:** varianty, SKU, atributy, katalogové a členské ceny, aktivaci/stažení a skladový údaj. Programy mají samostatnou kapacitu a skladová korekce je u nich odmítnuta.

**Doplnit / ověřit:**

- Potvrdit skladníka a zástupce, skutečná místa zásob, způsoby osobního předání a pravidlo výdeje před platbou. Výchozí návrh: prodejní výdej až po potvrzené úhradě; případná výjimka je samostatně schválený postup s pohledávkou.
- Jednoznačné varianty podle modelu/velikosti/barvy. SKU zachovat při přejmenování, starou variantu nesmazat, pokud má historii. Nedělat varianty z pouhých marketingových kategorií.
- U oblečení používat celé kusy; současný obecný skladový parser připouští desetinné hodnoty. Jednotky a povolené množství sjednotit ve formuláři i na serveru.
- U fyzického zboží určit, zda je množství sledované. `NULL` dnes znamená nesledovaný sklad, ne nulu; neověřená zásoba nesmí omylem fungovat jako neomezená dostupnost.
- Rozhodnout, zda se přijímají předobjednávky. Pro první verzi je nepovolovat bez zvláštního viditelného režimu, dodacího příslibu a následného přidělení dodávky.

**Hotovo, když:** každá prodávaná fyzická varianta má srozumitelnou jednotku, režim a odpovědný zdroj stavu. Kroužky, služby a příspěvky se nevykazují jako skladové kusy.

### E3-02 — Oddělit fyzický stav, rezervace a blokované kusy

**Máme:** pohyby `reserve`, `restock`, `manual_adjustment`, zámky při změně a audit. Nevytváříme pohybový deník od nuly.

**Doplnit:**

- Jednotný model: fyzicky evidované vlastní kusy, rezervované kusy, blokované/neprodejné kusy a volně prodejné množství. Přesné přechody jsou v příloze.
- Typované fyzické události: počátek, příjem, výdej, přijatá vratka, přeřazení do blokace, odpis, inventurní oprava a případný přesun. Rezervace je samostatná vazba na dosud nevydané množství objednávky.
- U pohybu variantu, množství, místo/stav, čas události a evidence, pracovníka, důvod, vazbu na zdrojový doklad a opravu původního pohybu. Historie se opravuje navazující událostí.
- Jednu zapisovací cestu používanou webem, hostem i osobním prodejem. Každá opakovatelná operace má stabilní identifikátor a ochranu před duplicitním zápisem.
- Rozšířit omezení dnešní evidence pro více výdejů a vratek téže položky. Původní unikátnost položka + typ pohybu nestačí pro obecné částečné pohyby.

**Hotovo, když:** z historie lze vysvětlit každý stav a rezervace nesnižuje fyzický počet. Při výdeji se sníží fyzický stav i rezervace; dostupnost se neodečte podruhé.

### E3-03 — Počáteční stav a převod rozpracovaného prodeje

**Máme:** převzatý katalog, dnešní množství a otevřené objednávky/pohyby. Samotný importovaný počet není ověřená fyzická inventura.

**Doplnit:**

- Rozhodný okamžik a soupis fyzických kusů po variantách, rozlišení prodejné/blokované, podpis odpovědných a původ ocenění.
- Soupis rozpracovaných objednávek: zaplaceno, dosud rezervováno, skutečně předáno, zásilka již mimo oddíl, čekající finanční vratka. Nevyvozovat fyzické předání jen z obecného stavu `completed`, který dnes může znamenat i předání dopravci.
- Převod staré dostupnosti do nového modelu. Neprovádět prosté „aktuální množství + všechny historické reserve“; část rezervací již patří dokončenému prodeji či stornu a některé stavy byly ručně opravované.
- U nesouladu skutečnost/KIS/Shoptet vytvořit výjimku k rozhodnutí. Počáteční množství a cena potřebují vlastní důkaz; starý audit se nepřepisuje.

**Hotovo, když:** ověřený počátek a rezervace odpovídají otevřeným případům a fyzické kontrole. Návrh i syntetický převod lze ověřovat nyní; skutečný import až v autorizovaném přechodu etapy 6.

### E3-04 — Příjemky a pořizovací ceny

**Máme:** auditovanou změnu cílového množství a evidenci přijatých účtenek. To není plnohodnotná příjemka s dodavatelem, náklady a vazbou na přijatý doklad.

**Doplnit:**

- Příjemku s dodavatelem, číslem zdrojového dokladu, datem fyzického příjmu, variantami, skutečně převzatými počty, rozdíly a pracovníkem. Objednání u dodavatele ani přijetí jeho faktury samo nezvyšuje sklad.
- Částečnou dodávku a následný dopříjem bez dvojího započtení; nevyhovující kusy přijmout do blokovaného stavu.
- Pořizovací cenu a původ jejího výpočtu, případné rozdělení dopravy/slev a pozdější změnu po doručení faktury. Metodu ocenění výdejů schválí účetní v návaznosti na E2-01; nepředpokládat automaticky prodejní cenu ani nulovou cenu.
- Rozlišení fyzicky přijatého, ale ještě neoceněného zboží. Hospodář musí vidět neuzavřené ocenění; export hodnot nesmí tuto mezeru skrýt.
- Vrácení dodavateli propojit s původním příjmem a jeho oceněním, samostatně od dodavatelského opravného dokladu a peněz.

**Hotovo, když:** lze dohledat odkud kusy přišly, kolik jich skutečně dorazilo a jakou mají doloženou hodnotu. Vyšší stav nevzniká běžnou změnou produktového popisu.

### E3-05 — Rezervace a dostupnost ve všech prodejních cestách

**Máme:** rezervaci v přihlášeném i rychlém nákupu, transakční kontrolu dostatku a expiraci nezaplacených objednávek s náhledem a omezenou dávkou.

**Doplnit / ověřit:**

- Převést všechny cesty na model E3-02 a zachovat bezpečnost posledního kusu při souběhu webu, osobního prodeje a změny skladu.
- U rezervace znát položku, původní a zbývající množství, termín, stav a důvod prodloužení/uvolnění. Placené, ale nevyzvednuté zboží nesmí expirovat jako neuhrazená objednávka.
- Prověřit zaplacenou, částečně zaplacenou a pozdě zaplacenou objednávku. Platba po uvolnění rezervace nesmí znovu prodat již vydaný kus; vznikne viditelný případ pro hospodáře a dostupnost se znovu ověří.
- Nastavit dohled nad expirací podle E1-08; existující skript sám nedokazuje běh plánovače. Pro malý objem lze přijmout pravidelný ruční přehled s vlastníkem.
- Rozlišit košík a potvrzenou rezervaci; zákazník musí vědět, kdy je zboží skutečně drženo a do kdy má zaplatit.

**Hotovo, když:** poslední kus získá nejvýše jeden kupující, storno/expirace vrátí dostupnost právě jednou a finanční příjem se neztratí, ani když už objednávku nelze splnit.

### E3-06 — Osobní prodej pro malý oddíl

**Máme:** běžný checkout, rychlý nákup zboží bez účtu, členské ceny a potvrzení hotovosti hospodářem. Rychlý zákaznický nákup není sám kompletní pokladní obrazovka pro obsluhu.

**Doplnit:**

- Jednoduchou obslužnou cestu: najít variantu/velikost → množství → člen nebo veřejnost → zkontrolovat cenu → přijmout úhradu → potvrdit předání a vydat doklad. Nákup veřejnosti nevyžaduje vytvoření sportovce.
- Použít stejná cenová, rezervovací a platební pravidla jako web. Členskou cenu určit z platného oprávnění; ruční sleva má důvod, autora a limit podle role.
- U členů rozlišit kupujícího, plátce a příjemce oblečení. Zástupce může převzít zboží podle pravidel oddílu, aniž získá rodinný přístup do systému.
- Zamezit dvojímu prodeji při opakování formuláře nebo přerušení spojení. Pokud se hotovost už fyzicky převzala, ukázat obsluze, co se skutečně uložilo a co musí dořešit; netvrdit, že technický rollback vrátil bankovky.
- Navázat na doklady a pokladnu E2-06/07. Mimořádný zápis po výpadku využije číslovaný náhradní podklad a kontrolu duplicit.

**Hotovo, když:** skladník/prodejce vyřídí běžný osobní nákup bez ruční korekce zásob a bez vytváření fiktivního účtu; vznikne právě jeden prodej, úhrada a skutečný výdej.

### E3-07 — Výdej po položkách

**Máme:** auditované označení připraveno a dokončeno, kontrolu zaplacení a ochranu proti opakovanému dokončení. Jde o stav celé objednávky.

**Doplnit:**

- Výdejku s konkrétními variantami a množstvím, skutečným časem, vydávajícím, přebírajícím či dopravcem a vazbou na objednávku. Formu důkazu převzetí zvolit přiměřeně oddílu.
- Částečný výdej a zbývající množství k předání. Jednu položku lze předávat postupně; součet nesmí překročit objednané množství po schválených změnách.
- Při vydání fyzicky odečíst jen dané kusy a spotřebovat odpovídající rezervaci. Označení „připraveno“ samo fyzický sklad nemění.
- Nepředaný zbytek musí být viditelný a dále rezervovaný, nebo výslovně zrušený s finanční návazností. Celá objednávka se nesmí tvářit jako vyřízená po předání jediného kusu.
- U povolené kombinace zboží a služby řešit plnění položkově. Současná obecná výdejová cesta odmítá objednávku obsahující kroužek; podle návrhu buď oddělit objednávky, nebo doplnit výdej pouze fyzických položek. Kurz se nikdy nevyskladňuje.

**Hotovo, když:** obsluha dohledá co, komu a kolikrát předala a kolik zbývá. Platba neznamená předání a výdej nesmí znovu odečíst dostupnost rezervovaného zboží.

### E3-08 — Expedice a nedoručené zásilky

**Máme:** PPL/Packeta výběr výdejního místa, kontrolu ceny a místa na serveru, snímek dopravy, ruční práci se štítkem/trackingem a potvrzení podání. U Packety se dočasný podací kód po podání odstraní. Aktivace dopravy není prokázána pouhou existencí kódu.

**Doplnit / ověřit před použitím:**

- Provozní kontrolu nastavení, vhodnosti produktů, balení, ceny a odpovědné osoby. Ruční tvorba štítků může zůstat cílovým řešením pro malý objem.
- Propojení zásilky s konkrétními výdeji. V první verzi lze držet jednu zásilku na objednávku; případné částečné zásilky musí mít vlastní záznamy a pravidla nákladů.
- Rozlišit připraveno, předáno dopravci a převzato zákazníkem. Dnešní `completed` při podání není doklad o doručení zákazníkovi a nesmí být jediným podkladem pro běh lhůt vrácení.
- Nevyzvednutou, ztracenou nebo poškozenou zásilku vést jako případ s odpovědným pracovníkem. Zboží vrátit do vlastních zásob až po doloženém fyzickém návratu a kontrole; ztracené kusy se nevracejí do prodeje kliknutím na storno.
- Opravu a zrušení zásilky u dopravce oddělit od objednávky, peněz a skladu. Doručovací zprávy použijí frontu z etapy 1.

**Hotovo, když:** používaný kanál má ověřenou celou cestu a dořešení výjimek. PPL/Packeta API, tisk štítků na jedno kliknutí ani dobírka nejsou automatický požadavek; nepoužívaný kanál může zůstat vypnutý.

### E3-09 — Vratky, výměny a reklamace po výdeji

**Máme:** storno před dokončením a samostatné potvrzení celé peněžní vratky. Dokončenou objednávku stávající storno odmítá; položkové vrácení a výměna tím nejsou vyřešené.

**Doplnit:**

- Případ navázaný na objednávku/výdej: požadavek zákazníka, druh řešení, položky, množství, data, kontaktní cesta, odpovědný pracovník, lhůta a průběh. Přijetí reklamace není automaticky uznání nároku ani převzetí vlastnictví zboží zpět.
- Fyzický příjem vrácených kusů a kontrolu: prodejné, blokované, určené k opravě, vrácení dodavateli nebo odpisu. Pouhá žádost zákazníka nezvyšuje sklad.
- Částečné a opakované vratky s kontrolou už vrácených množství a peněz. Vlastníkem opravného dokladu a peněžní vratky je E2-08; tato etapa dodá fyzický výsledek a přesnou položkovou vazbu.
- Výměnu velikosti jako propojený návrat původní varianty a nový výdej jiné varianty; dostupnost nové velikosti znovu rezervovat, zachovat původní cenu/historii a evidovat případný doplatek/vratku. Nejde o prosté přepsání SKU na staré objednávce.
- Odlišit odstoupení, dobrovolnou výměnu a reklamaci. Povinný interní důvod úkonu nesmí nutit zákazníka zdůvodňovat zákonné odstoupení. Lhůty, náklady dopravy a výjimky musí odpovídat použitelné schválené metodice.

**Hotovo, když:** lze vyřídit vrácení jednoho ze dvou kusů i výměnu M za L bez přepsání historie a bez automatického návratu poškozeného kusu do prodeje. Případ se uzavře až po vyřešení všech požadovaných fyzických, dokladových a peněžních kroků; jejich pořadí respektuje konkrétní situaci.

U spotřebitelského nákupu přes internet osobní odběr sám neodnímá právo odstoupit; ani běžná volba velikosti není automaticky výrobou na míru. Proto tyto údaje nesmějí samy blokovat přijetí požadavku. [ČOI — odstoupení od smlouvy u zboží](https://coi.gov.cz/faq/7-odstoupeni-od-smlouvy-do-14-dnu-u-zbozi/). Pravidla zboží se nepřenášejí automaticky na storna kurzů.

### E3-10 — Inventura a korekce

**Máme:** změnu cílového množství s důvodem, auditním pohybem a kontrolou, zda se stav od načtení formuláře nezměnil. To není kompletní inventurní proces.

**Doplnit:**

- Inventurní seznam variant a fyzických stavů k okamžiku, skutečně napočítané množství, kdo počítal, rozdíly a schvalujícího. Rezervované kusy fyzicky přítomné v oddíle se počítají také.
- Pro malý sklad preferovat krátké řízené zastavení pohybů během počítání. Pokud se prodává souběžně, inventura musí zohlednit prokázané pohyby mezi rozhodným časem a počtem.
- Rozdíly schvalovat jako typované inventurní pohyby s oceněním. Změna cílového stavu se nepoužije bez zohlednění rezervací a blokací.
- Pokud chybí kus již slíbený zákazníkovi, otevřít případ k řešení; nevyrábět záporný volný sklad nebo potichu mazat rezervaci. Oprava inventury je další dohledatelný krok.

**Hotovo, když:** schválená inventura vysvětluje množství i hodnotu a opakované potvrzení nevytvoří druhou korekci. Nulový stav je doložená nula, nikoli náhrada neprovedeného počítání.

### E3-11 — Výdeje mimo prodej a přesuny

**Máme:** obecnou korekci s důvodem; úplný dokladový postup těchto typů pohybů není v prověřené skladové cestě doložený.

**Doplnit pro skutečně používané případy:**

- Vlastní spotřebu oddílu, bezplatné předání oblečení, schválenou odměnu v naturáliích a poškození/likvidaci vést odděleně od prodeje. Každý výdej má příjemce nebo účel, autora, schválení a ocenění.
- Předání zboží trenérovi k prodeji na akci vést jako změnu místa/odpovědnosti, nikoli jako uskutečněný prodej všech kusů. Vyúčtování doloží prodané, vrácené a zbývající množství i skutečnou hotovost.
- Přesun mezi dvěma místy skladu musí zachovat celkové množství a hodnotu, s dohledatelným předáním/převzetím. Podrobnou evidenci více skladů zavést jen při reálné potřebě.

**Hotovo, když:** pohyby mimo prodej nenavyšují tržby ani nevznikají anonymní korekcí. Tento úkol není evidencí půjčovaných kol/vybavení.

### E3-12 — Přehled pro skladníka a zákazníka

**Máme:** správu produktů/variant, skladovou stránku, filtry, cenu a historii. Tyto obrazovky se rozšíří, nevznikne druhá správa katalogu.

**Doplnit:**

- U každé velikosti výrazně fyzicky, rezervováno, blokováno a dostupné. Produktový součet nesmí skrýt, že požadovaná velikost chybí; nesledované varianty nesčítat jako známé kusy.
- Pracovní seznamy „přijmout“, „připravit/vydat“, „dořešit vratku“, „chybí ocenění“, „nesedí inventura“. Každý případ odkazuje na konkrétní doklad a má vlastníka.
- Ve veřejné nabídce srozumitelnou dostupnost a způsob předání. Neprezentovat interní účetní či importní podrobnosti zákazníkovi.
- Příjemku, inventuru a editaci ceny vést jako odlišné úkony. Přehled musí být použitelný na telefonu při osobním předání.

**Hotovo, když:** obsluha pozná skutečně dostupnou velikost a další nutný krok bez otevírání několika nesouvisejících evidencí. Minimální zásoba a návrh doobjednání jsou možné pozdější vylepšení, ne podmínka.

### E3-13 — Skladové podklady pro účetní

**Máme:** množstevní pohyby a připravený kontrakt etapy 2, včetně listu Sklad. Úplné ocenění tohoto výstupu zatím chybí.

**Doplnit:**

- Provázat příjemky, výdejky, vratky, odpisy a inventurní rozdíly s odpovídajícími účetními zdroji. Skladový pohyb nevystavuje podruhé fakturu a nevytváří sám peněžní příjem.
- Po variantách exportovat počátek, fyzické pohyby, konec, rezervace/blokace a hodnotu podle metody E3-04. Rezervace je provozní vazba; není prodané či fyzicky vydané zboží.
- Uchovat pořizovací hodnotu vráceného kusu a pravidla jejího přiřazení k původnímu výdeji. Změna prodejní ceny neoceňuje zásoby znovu.
- Doplnit přesah měsíců: příjem před fakturou, pozdější nákladová oprava, výdej v jiném měsíci než platba a vratka starého prodeje. Již předaný balík se neopravuje přepsáním; použije se revize etapy 2.

**Hotovo, když:** účetní doloží množství i hodnotu ze skladových podkladů a odděleně naváže peníze/doklady. Chybějící ocenění je viditelná výjimka, ne falešná nula.

### E3-14 — Přístupy, audit a provozní bezpečnost

**Máme:** pracovní pozice, potvrzování citlivých akcí, audit korekcí, transakce a zálohovací základ.

**Doplnit / ověřit:**

- Pravomoci příjem/výdej, změna ceny, uznání vratky, odpis, inventurní rozdíl a skutečná peněžní vratka. Skladník bez finančního oprávnění nemá potvrdit odeslání peněz.
- Ochranu přímých požadavků, souběh inventury/rezervace/výdeje, opakování po výpadku a sdílenou historii oprav. U manuálních změn nesmí chybět autor a důvod.
- Nové pohyby, přílohy a doklady zahrnout do obnovy. Obnovená databáze musí obsahovat stejné zůstatky i otevřené rezervace a vratky.
- Provozní dohled nad expirací, nevyřešenými vratkami, selhanými zprávami a nesouladem skladu. Technické selhání nesmí obsluha řešit ručním přepisem čísla bez vysvětlení.

**Hotovo, když:** neautorizovaný uživatel sklad nezmění, oprava zůstane dohledatelná a po obnovení či opakování úkonu nevznikne další výdej nebo příjem.

### E3-15 — Přejímka a přechod od Shoptetu/papíru

**Máme:** testy checkoutu, ručních korekcí, expirace a dopravních akcí. Nepředstavují přejímku nového fyzického skladového modelu.

**Doplnit:**

- Projít [scénáře přílohy](01_SKLADOVY_MODEL_PRIJEMKA_A_ZDROJE.md) v izolovaném prostředí na konkrétní revizi, včetně skutečného pohledu skladníka a účetní.
- Prakticky vyzkoušet malý reprezentativní sortiment: více velikostí, poslední kus, dvě souběžné obsluhy, částečný výdej, výměna, poškození, inventura a skladový export.
- Před ostrým přechodem určit jediný systém přijímající rezervace a nové prodeje. Dva e-shopy nesmějí nezávisle prodávat tutéž poslední zásobu; přechod buď zastaví starý prodej, nebo musí mít předem vyřešenou synchronizaci a odpovědnost.
- Ověřit skutečné počáteční zásoby, otevřené případy, účetní návaznosti a náhradní postup při výpadku. Záznamy v papíru po rozhodném okamžiku mají jasné předání a jednorázové doplnění.
- Předat stručné postupy obsluhy a protokol omezení. Aktivace dopravce, první skutečný prodej a vypnutí Shoptetu jsou následné autorizované kroky etapy 6.

**Hotovo, když:** skladník zvládne běžný oběh bez vývojáře, účetní převezme doložené skladové podklady a vedení zná přesný rozsah připravenosti. Nevyřešené P0 brání převzetí prodeje.

## Co zůstává ostatním etapám

- E2-08 vlastní opravné doklady, peněžní vratky a přeplatky; E3-09 vlastní fyzické kusy a výměny. Společná položková vazba je nutná, nevzniknou dvě nezávislé vratkové evidence.
- Kapacity, účast a storna kurzů se spravují v programové agendě. Zde se ověří, že nevytvářejí skladové pohyby zboží.
- Rozpočty, úplné schvalování výdajů/odměn a dlouhodobé klubové agendy se dokončí v etapách 4/5. Fyzický výdej mimo prodej už musí mít svůj důvod a účetní návaznost.
- Historické převody a skutečný přechod ze Shoptetu/Evidence patří do etapy 6. Etapa 3 dodá model, podklady, postup a přejímací kritéria.

**První budoucí pracovní balík:** E3-01/02 a návrh převodu E3-03, souběžně příjemka a ocenění E3-04. Nejprve vyřešit význam skladových čísel; pak připojit osobní prodej, fyzické výdeje a vratky. V této přípravě byly vytvořeny jen místní dokumenty.
