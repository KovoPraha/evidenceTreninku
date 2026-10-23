# Přejímka lidí a sportu — podklad pro společný balík

Připraveno 23. 10. 2026 z izolované pracovní kopie. Jde o scénáře pro budoucí
uživatelskou přejímku, nikoli o její výsledek. Všech 16 případů má stav
`NEPROVEDENO`; odhad přímé práce činí 312 minut bez přípravy účtů, čekání na
termíny a finančních návazností. Nebyl proveden žádný HTTP/DB zápis ani změna
aplikace či produkce. Zdrojem názvů tlačítek a očekávaného chování byly aktuální
PHP routy i jejich includované formuláře; nikoli pouze historické předání.

## Jak scénáře číst

- D je den skutečného provádění. B01 je nový suffix konkrétního běhu, při
  opakování použijte B02. Skutečná číselná ID doplní operátor po založení.
- PŘIPRAVENO označuje připravený návod a doložené UI, nikoli existující data,
  úspěšnou zkoušku nebo povolení produkčního zápisu. Předpodmínky se musí splnit.
- BLOKOVÁNO u přípravy znamená konkrétní dosud nedoloženou fixture či návaznost.
  Nemění stav lidské přejímky. Pokud předpoklad nelze splnit přes UI, zapište
  překážku; neobcházejte ji SQL, přímým POST ani aktivací odesílání.
- Zapisujte datum, operátora, aktivní roli, skutečná ID, výsledek každého kroku,
  přesnou hlášku a odkaz na důkaz. Hesla, skutečné rodné číslo a zdravotní
  údaje nepatří do přejímacího protokolu.
- Stav P0/P1 určuje pořadí důležitosti, ne závažnost případné nalezené vady.
  Časové a peněžní zkušební hodnoty nejsou klubová metodika.

## Pokrytí a pořadí

P01–P03 pokrývají osobu, zdrojovou identitu, veřejný účet a rodinnou vazbu.
P04–P06 program, verze papírových podkladů, osobní přijetí bez účtu a kapacitu.
P07–P08 členský stav, datované soupisky, dohodu plátce a přesný měsíční předpis.
P09–P12 rozvrh, uzavření skutečnosti, opravu s neměnným snímkem a náhrady.
P13–P16 odlišení nominace od startu, prohlídky, rozsah trenéra a návazné období.

Pro přípravu trenérských oprávnění lze udělat přidělovací část P15 před P09;
odvolání přístupu patří až po sportovních scénářích. Ostatní závislosti jsou
uvedené v JSON. Nevytvářet kruhové závislosti z celého P15 na jeho přípravný
krok. Pro rodičovské náhrady musí po přijetí následovat samostatná přejímka
potvrzení skutečné zkušební úhrady; očekávající objednávka není placená účast.

## Technické a datové překážky k přípravě

| Položka | Dopad | Jak uvolnit bez produkce |
|---|---|---|
| P03, navazující P08 | Není potvrzena existence dvou aktivních veřejných účtů s ověřeným e-mailem | Správce ověří dostupnost syntetických místních účtů a předá přístupy odděleně. Nespouštět skutečnou ověřovací rozesílku. |
| P12 | Nestačí izolovaně zapsat omluvu; potřebuje placenou účast, zdrojovou a cílovou lekci, pravidlo účinné ve zdrojovém plánu a rodičovský přístup | Připravit přes obslužné obrazovky spolu s finanční přejímkou, případně rozdělit přes více dnů. Bez těchto vazeb zaznamenat BLOKOVÁNO. |
| P15 | Výchozí široké kontrolní účty neprokazují odebrání úzkého rozsahu | Připravit samostatný místní trenérský účet bez superadmina, registráře a vedoucího a bez vedlejších přidělení. |

Tyto překážky nejsou prokázané vady produktu. Ze zdrojové kontroly nevyplynul
potvrzený chybějící formulář měsíčních dohod: `member_fee_plans_admin.php:38`
načítá `includes/member_fee_agreement_fields.php`, který obsahuje obě potřebné
akce. P08 proto má konkrétní kroky pro existující UI. Jeho blokací jsou účty
a jejich schválené vazby, nikoli chybějící formuláře.

P13 obsahuje i konkrétní založení vlastního interního závodu; nepředstavuje
úplnou přejímku nominací ve všech kalendářových obrazovkách ani importu
historických výsledků. Provádí jej vedoucí sportu; trenér by navíc potřeboval
konkrétní pověření k dané akci, samotný rozsah osoby nestačí. P12 není důkaz souběžné závodní
podmínky dvou rezervací; taková technická kontrola má vlastní automatizované
důkazy. R07–R12 se zde prověřují prostřednictvím lidí, rolí a auditních vazeb;
všeobecnou provozní frontu a finanční rozhodnutí pokrývá jiná část společného
balíku. Žádný doménový test PASS nenahrazuje tyto lidské kontroly.

## Odložená rozhodnutí

D03: skutečná pravidla přijetí, plátce a úlev. D07: skutečné identity a zdroje.
D11: skutečný účel a retence podkladů. D13: jména obsluhy a rozsah skutečného
pilotu. Každý zkušební scénář může použít výslovně syntetickou hodnotu, ale
nemůže tím rozhodnout tuto položku za vedení nebo účetní. Právní texty se
nevydávají za schválené podmínky oddílu. Půjčované vybavení je mimo rozsah.

## Kontrola artefaktu

JSON obsahuje 16 jedinečných ID UAT-P01–UAT-P16, žádný vyplněný výsledek
přejímky, odkazy jen na existující lokální routy a zdrojové soubory. Případy
mají pozitivní i negativní očekávání a vyžadují zachycení skutečných ID.
Veškeré nové soubory tohoto podúkolu jsou pouze dokumentace v tomto adresáři.
