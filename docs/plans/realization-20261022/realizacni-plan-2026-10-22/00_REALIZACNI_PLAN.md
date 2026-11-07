# Realizační plán společného systému oddílu

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Stav zadání: 22. 10. 2026. Sjednocení etap 0–6, pouze místní dokumentace. Žádná implementace, import, aktivace služeb ani změna produkce není součástí této přípravy.

**Doporučení: realizovat společné základy jednou, potom dokončit obchodní a sportovní postupy, ověřit celý účetní měsíc a teprve následně převádět ostrou agendu. První implementační balík má ochránit existující uzavřené finanční záznamy a připravit izolované ověřování.** Samotné nasazení tohoto balíku ještě neznamená připravenost nahradit Shoptet nebo původní Evidenci.

## 1. Rozsah a význam stavů

Oddíl vede jednoduché účetnictví, není plátcem DPH a předává účetní XLSX a faktury. Povinný rozsah zahrnuje členy i veřejnost, osobní i internetový prodej, oblečení a sklady, kurzy a kroužky, rodiny, příspěvky, přihlášky a souhlasy, rozvrhy, skutečnou docházku a omluvy, zdravotní prohlídky, výdaje a zálohy, odměny trenérů a sportovců. Půjčování vybavení je mimo rozsah.

Jízdy a cestovní náhrady, licence a kvalifikace, dobírka, neprodejní výdeje a širší registr smluv se dokončí podle potvrzeného skutečného používání. Nejsou automaticky přidány k povinným agendám. Aktivně používanou agendu však nelze odložit a současně prohlásit její původní systém za nahrazený.

- **Máme:** doložený základ v kódu nebo zjištění původní analýzy. Neznamená převzetí obsluhou ani potvrzení aktuálního produkčního nastavení.
- **Doplnit:** chybějící výsledek; může znamenat propojení a ověření existující funkce, nikoli vždy nový modul.
- **Hotovo:** příslušný výstup splnil přejímku. U realizace se samostatně eviduje stav „implementováno“, „ověřeno v testu“, „nasazeno“ a „převzato obsluhou“.
- Všechny realizační úkoly níže jsou **plánované**. Dokument nepovažuje etapy 0–6 za již dokončené.

Podkladem jsou místní zadání etap, jejich zdrojové rozbory a přejímací scénáře. Výchozí revize `origin/main` je `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. Při sjednocení byly dočteny zejména finanční zápisy a centrální kontrola pracovních pozic. Produkční přejímka ani nové měření dat nebyly provedeny. Počty v migračních podkladech pocházejí z etapy 0, nejde o nový úplný databázový audit.

## 2. Jedno pořadí, společné priority

Původní etapy zůstávají podrobnou specifikací. Následujících **44 úkolů R01–R44** je jejich sjednocený realizační seznam. Převod všech 98 původních úkolů je na konci dokumentu.

**P0:** společný základ, ochrana dat nebo rozhodnutí blokující více agend. **P1:** povinné dokončení používaného procesu před jeho převzetím do nového systému. **P2:** pouze podmíněné rozšíření. P1 není volitelné; pořadí určuje také sloupec návazností. Technické podmínky přepnutí jsou P0, i když se provádějí až na konci.

| Pořadí / pracovní celek | Úkoly | Co zpřístupní | Garant výsledku |
|---|---|---|---|
| A. Pravidla, bezpečí a provozní základ | R01–R06 | Jedno zadání, oddělený test a ochranu historie | Vedení, hospodář, správce |
| B. Osoby a společná obsluha | R07–R12 | Jednoznačné osoby, členství, přístupy a dohled | Registrář a správce |
| C. Peníze a doklady | R13–R17 | Společný finanční základ pro všechny agendy | Hospodář a účetní |
| D. Sklad a prodej | R18–R24 | Nákup → rezervaci → výdej → vratku | Správce obchodu/skladu |
| E. Členská a sportovní agenda | R25–R30 | Přihlášku → zařazení → trénink → docházku | Registrář a vedoucí sportu |
| F. Výdaje, zálohy a odměny | R31–R35 | Podklad → schválení → vypořádání | Hospodář, schvalovatel, vedoucí sportu |
| G. Společný účetní a uživatelský výsledek | R36–R37 | Úplný měsíc včetně dokladů a kontrol | Účetní a skutečná obsluha |
| H. Převod a převzetí provozu | R38–R44 | Ověřená data, pilot, přepnutí a odstavení starého | Správce dat a vedení |

Nejde o osm bloků, které musejí celé čekat jeden na druhý. R01–R04 lze zahájit souběžně; skladový model R18 může vznikat při účetní metodice. Sportovní práce po společných základech může běžet vedle skladu. Inventura a mapování migrace začínají brzy, až skutečný import čeká na stabilní cílové postupy. Při malém týmu je doporučené pořadí dodávek A → B → C → D → E → F → G → H; příprava podkladů pro H probíhá průběžně.

**Kritická návaznost pro kompletní převzetí:** ověřené identity a pravidla členství → společné peněžní pohyby a doklady → dokončené používané procesy → úplné účetní předání → převod nanečisto a návratový postup → pilot → ostré přepnutí → první převzatý účetní měsíc.

## 3. Konkrétní realizační úkoly

Návaznosti označují přímé předchůdce pro dokončení úkolu. Návrh obrazovek, seznam testů a sběr podkladů mohou začít dříve. Odpovědnosti se přebírají z tabulky pracovních celků; R01 je musí přiřadit konkrétním lidem a jejich zástupcům.

### A. Pravidla, bezpečí a provozní základ

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R01 · P0 · bez předchůdce | Potvrzený rozsah a seznam papírových agend; návrhy odpovědných rolí. | Doplnit skutečné správce, zástupce, média, rozsah historie a přesný seznam nahrazovaných webů/služeb. Rozhodnout podmíněné agendy a kdo smí schválit vlastní výdaj. Výstup: registr agend, vlastníků, rozhodnutí a kritérií odstavení. |
| R02 · P0 · R01 | Zadání jednoduchého účetnictví a návrh exportu. | S účetní potvrdit vystavovatele, období, řady, členění, okamžiky vzniku dokladů, účetní program a vzor měsíčního předání. Výstup: odsouhlasený datový a procesní kontrakt; neznámý program neblokuje R04/R05. |
| R03 · P0 · R01 | Porovnání přehledů staré Evidence a KIS a seznam prvních rozdílů. | Inventarizovat zdrojové tabulky, soubory, exporty Shoptetu a existující cílová data; určit zdroj pravdy po jednotlivých údajích. Výstup: konzistentní snímky s časem, rozsahem a kontrolními součty, bez domněnky, že shodné jméno je potvrzená identita. |
| R04 · P0 · bez předchůdce | Migrace, automatické testy a místní ověřovací postupy. | Připravit izolovanou DB, úložiště a zachytávání zpráv; vypnout ostré integrace v testu. Zaznamenat revizi a schéma, použít syntetické osoby. Výstup: opakovatelná testovací instalace bez možnosti oslovit skutečné příjemce nebo strhnout peníze. |
| R05 · P0 · R04 | Uzavírání finančních akcí, účtenky, období odměn a uložené souhrny při uzavření. | Zabránit běžným zápisům do uzavřených finančních akcí a uzavřených/vyplacených období odměn; opakované uzavření nesmí přepočítat historii. Ověřit přímé zápisy a stávající pracovní oprávnění. Výstup: malý první balík popsaný v oddílu 4. |
| R06 · P0 · R03, R04 | Zálohovací a nasazovací mechanismy. | Prokázat úplnou obnovu potřebných tabulek, soukromých příloh, konfigurace a nezbytných klíčů, včetně oprávnění a vazeb. Stanovit tolerovanou ztrátu dat a dobu obnovy. Obnova nesmí přepsat cizí aplikace ve sdílené DB. Výstup: protokol obnovy v izolaci a postup při výpadku. |

### B. Osoby a společná obsluha

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R07 · P0 · R01, R03, R04 | Osoby, účty, zdrojové vazby, registrační žádosti a posuzování shod. | Sjednotit bezpečné spojování a rozlišení osob; zachovat původ a historii rozhodnutí. Ověřit duplicitní a nejednoznačné případy. Výstup: pravidla identity a funkční ruční vyřešení konfliktu; celou historickou mapu dokončuje R38. |
| R08 · P0 · R07 | Členské a programové evidence, soupisky a datované vazby; také starší odvozování statusu. | Sjednotit význam členství, účasti, účtu a placení, časové platnosti a přestupů. Trvalé členství se nemá každý rok znovu zakládat. Výstup: jeden výsledek v profilu, cenách, přihlášce i oprávnění bez duplicitního zařazení. |
| R09 · P0 · R01, R04 | Pracovní pozice, centrální kontrolu přístupu k cestám, oprávnění a deaktivaci pracovníků. | Ověřit čtení, změny, exporty, přílohy, přímé POST a odebrání přístupů; mezery opravit v existujícím modelu. Výstup: matice rolí a negativní testy pro rodinu, trenéra, registráře, sklad a finance. |
| R10 · P1 · R07, R08, R09, R11 | Rodinné vazby, účty dětí, verzované souhlasy a soukromé přílohy. | Dokončit osobní vyřízení přihlášky a doložení papírového souhlasu s autorem a verzí; ověřit změny zástupce a samostatný účet. Kontaktní e-mail není důkaz zastupování. Výstup: rodina se dvěma dětmi i člen bez vlastního účtu projdou bez neoprávněného přístupu. |
| R11 · P0 · R04, R09 | Fronty zpráv a plánované úlohy; zůstávají starší přímá odeslání v rezervacích. | Zapojit zbývající provozní zprávy do spolehlivého doručování; určit příjemce, opakování, deduplikaci a dohled nad úlohami. Výstup: chyba je viditelná, opakování nevytvoří dvojí zprávu či operaci a existuje vlastník řešení. |
| R12 · P1 · R07, R08, R09, R10, R11 | Audit osoby a dílčí přehledy rodin i obsluhy. | Propojit nevyřešené případy, stav přihlášky/členství a navazující agendy v existujících přehledech. Výstup: obsluha zjistí stav, příčinu problému, autora změny a další krok; finanční a sportovní údaje se připojí ve svých úkolech. |

### C. Peníze a doklady

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R13 · P0 · R02, R07, R09 | Objednávky, předpisy, platby a párování. | Určit jedinou identitu skutečného peněžního pohybu, alokace, data a zdroj; oddělit příjem, nárok a odvozený zaplacený stav. Výstup: opakovaný import ani souběh automatického/ručního párování nezapočte stejné peníze dvakrát. |
| R14 · P0 · R02, R07, R08 | Katalog, osoby a část obchodních údajů. | Doplnit účetní členění a samostatné role kupujícího, plátce a příjemce služby, včetně dokladových údajů vystavovatele. Výstup: oblečení, příspěvek a kurz mají jednoznačné schválené zařazení a správné adresáty. |
| R15 · P1 · R13, R14 | Obchodní podklady a návrh registru dokladů. | Dokončit registr, bezpečné číslování, neměnný obsah vydaného dokladu, PDF a vazby na případ a platby. Stanovit vystavení pro jednotlivé typy prodeje/příspěvku. Výstup: opakování a souběh nevystaví dva doklady; oprava nepřepíše původní doklad. |
| R16 · P1 · R13, R15 | Bankovní a platební integrační základy a ruční evidence. | Dokončit pokladnu, bankovní pohyby a příjmové/výdajové podklady podle R02; oddělit skutečný pohyb od schválení. Pro používané brány vyřešit výplaty a poplatky, dobírku jen pokud se používá. Výstup: počáteční stav + pohyby = konečný stav a vypořádání není druhý tržební příjem. |
| R17 · P1 · R13, R15, R16 | Dílčí storna a platební stavy. | Doplnit částečné opravy, přeplatky, vracení peněz, závazek vůči zákazníkovi a návazné doklady. Výstup: rozhodnutí vrátit peníze, skutečná refundace a skladové vrácení mají oddělené průkazné stavy. |

### D. Sklad a prodej

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R18 · P0 · R01, R04 | Katalog zboží, varianty, služby a skladové množství. | Schválit jednotky, druhy položek, varianty, pravidla ocenění s účetní a jeden výchozí fyzický sklad. Výstup: služba nemění kusový sklad a každá skladová varianta má jednoznačný identifikátor. |
| R19 · P0 · R18, R09 | Odečítání množství při rezervaci a záznamy některých úprav. | Oddělit fyzický stav P, rezervace R a blokované kusy B; dostupnost A = P − R − B. Zavést dohledatelnou knihu pohybů. Výstup: každý stav lze vysvětlit pohyby a přepočet dostupnosti neodečítá rezervaci dvakrát. |
| R20 · P1 · R19, R14 | Produkty a možnost úprav skladu. | Doplnit příjemku, dodavatele, pořizovací cenu a vazbu na výdajový podklad. Výstup: příjem zvyšuje fyzický stav jednou; oprava má historii a nevytváří druhý účetní výdaj. |
| R21 · P1 · R19, R11 | Objednávky, rezervace a dokončovací stavy. | Sjednotit rezervaci, expiraci a uvolnění ve všech prodejních cestách; doplnit skutečný a částečný výdej po položkách. Výstup: dva kupující nekoupí poslední kus; výdej se neopakuje po obnovení stránky. |
| R22 · P1 · R15, R16, R21 | Internetový a rychlý nákup, správa objednávek a základy dopravy. | Dokončit osobní prodej, převzetí hotovosti, doklad a výdej; u používané dopravy expedici a nedoručení. Výstup: obsluha prodá rodiči/veřejnosti bez nuceného založení zbytečného účtu a dohledá peníze i kusy. |
| R23 · P1 · R17, R21, R22 | Storno před dokončením. | Doplnit vratku po výdeji, částečnou vratku, výměnu velikosti, reklamaci a posouzení další prodejnosti. Výstup: vrácený kus, blokace a peníze mají vlastní historii; výměna nevytvoří skrytou druhou tržbu. |
| R24 · P1; rozšíření P2 · R20, R21, R23 | Přehledy zásob a ruční změny. | Doplnit inventuru, zdůvodněné korekce, stav rezervováno/připraveno/vydáno a skladové podklady pro účetní. Neprodejní výdeje a přesuny jen při skutečném používání. Výstup: fyzická inventura se rovná schválenému stavu a odchylky jsou vysvětlené. |

### E. Členská a sportovní agenda

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R25 · P1 · R08, R10, R13, R14, R15 | Programy, nabídky, přihlášky, kapacity a příspěvkové plány. | Dokončit osobní přijetí, plátce, přestup, nástup uprostřed měsíce a změnu předpisu podle schválených pravidel. Výstup: účast a platební povinnost odpovídají dohodě; plátce se nevolí nevysvětleně z více zástupců. |
| R26 · P1 · R08, R09, R11 | Plánovač, sportoviště, trenéry a záznamy tréninků. | Sjednotit rozvrh a skutečnou docházku, uzavírání a opravy; ošetřit souběhy a změny či mazání tréninku s návaznostmi. Výstup: RSVP není automaticky docházka a oprava nezmění tiše již schválené odměny. |
| R27 · P1 · R25, R26 | Rozsáhlou podporu omluv, kreditů a náhrad. | Ověřit a doplnit dokončení až po skutečném skončení lekce, kapacity, expiraci a opravy/zrušení tréninku. Výstup: každý náhradový kredit vznikne a spotřebuje se nejvýše jednou; obsluha rozumí zamítnutí. |
| R28 · P1 · R08, R09, R26 | Klubové akce, závody, výsledky, měření a historii. | Propojit nominaci, přihlášení a skutečnou účast/start; zpřístupnit historii podle osoby a období bez ztráty původu. Výstup: zápis skutečného výsledku i oprava jsou auditovatelné a nezdvojují událost. |
| R29 · P1; rozšíření P2 · R07, R09, R10, R11 | Profil a soukromé přílohy; sportovní testy nejsou zdravotní prohlídky. | Doplnit zdravotní prohlídku, platnost, omezený přístup a upozornění. Licence/kvalifikace jen podle R01. Výstup: oprávněná obsluha vidí platnost, ostatní nezískají citlivý dokument; postup při expiraci je výslovně stanoven. |
| R30 · P1 · R25, R27, R28, R29, R12 | Trvalé programy, datované nabídky a dílčí role/přehledy. | Dokončit navazující nabídky, věkové přechody, přehled rodiny/trenéra a spolehlivé vstupy do odměn. Výstup: nové období nezaloží duplicitní osobu ani členství; automatické opakování se nezapíná bez samostatného rozhodnutí. |

### F. Výdaje, zálohy a odměny

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R31 · P1 · R01, R05, R09, R10, R14, R16, R17 | Účtenky, soukromé soubory, audit a rozlišení klubových/vlastních peněz. | Doplnit kontrolu duplicity, rozdělení výdaje, předložení, schválení, závazek a proplacení. Navázat na jediný skutečný peněžní pohyb R13/R16. Výstup: vlastní peníze pracovníka se proplatí právě jednou, oprava uzavřeného případu zachová původní verzi. |
| R32 · P1 · R28, R31 | Klubové akce a oddělené finanční události; jednoduchou částku zálohy. | Propojit akci s finančním případem; jednotlivě evidovat předání záloh, příjemce, výdaje, vrácení a doplatek. Výstup: záloha = uznané čerpání + vratka − doplatek, s doloženými skutečnými pohyby; uzavření neproběhne při nevysvětleném zůstatku. |
| R33 · P1 · R26, R30, R31 | Výkazy trenérů, další činnosti a XLSX. | Doplnit předložení/schválení výkazu, uchování schválené verze, schválené sazby a podklad k odměně podle způsobu stanoveného účetní. Výstup: nárok, schválení a skutečná výplata jsou oddělené; systém nepočítá automaticky neodsouhlasené mzdy či odvody. |
| R34 · P1 · R05, R08, R26, R30, R31 | Období odměn sportovců, uzavírací souhrn a příznak vyplacení. | Dokončit schvalování, datované sazby, opravy/revize a vazbu na skutečnou výplatu. Zachovat existující souhrny; stará období bez souhrnu označit k vyřešení. Výstup: změna docházky nebo sazby tiše nepřepíše schválený nárok a příznak „vyplaceno“ sám není důkaz platby. |
| R35 · P1; rozšíření P2 · R01, R09, R10, R11, R31, R32 | Dílčí klubové záznamy, dokumenty a základ jízd. | Doplnit dohledatelné rozhodnutí, odpovědnou osobu, termín a související podklady. Pokud používáno, dokončit jízdy/cestovní podklady a širší smluvní agendu. Výstup: každý schvalovaný finanční případ má podklad a vlastníka; obecný projektový nebo mzdový systém se nezavádí. |

### G. Společný účetní a uživatelský výsledek

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R36 · P1 · R02, R15, R16, R17, R24, R25, R31, R32, R33, R34 | Kontrakt účetního balíku a dílčí exporty. | Sestavit jednotný XLSX + PDF, vazby, salda, kontroly úplnosti, uzavření předání a opravnou verzi. Navržené listy: Manifest, Doklady, Polozky, Penize, Parovani, Cleneni_penez, Predpisy, Saldo, Vydaje, Sklad; finální rozsah potvrzuje R02. Výstup: účetní dohledá každý řádek na doklad/pohyb a odsouhlasí měsíc včetně skladů, záloh a odměn. |
| R37 · P1 · R06, R12, R24, R27, R28, R29, R30, R35, R36 | Přejímací scénáře etap 1–5. | Spojit je do souvislého provozního měsíce bez duplicitních kontrol; doplnit role, chybové a souběžné situace. Výstup: obsluha a účetní podepíší protokol, žádná otevřená chyba nesmí způsobovat cizí přístup, ztrátu dat, dvojí peníze nebo nesoulad skladu. |

Export se vyvíjí postupně: první kostra nad R13–R17, následně se připojí sklad a klubové výdaje. R36 označuje **dokončení celého předání**, nikoli zákaz začít export dříve. Obdobně se přejímka provádí při každé dodávce; R37 je závěrečná souvislá zkouška.

### H. Převod a převzetí provozu

| ID / priorita / návaznost | Co už máme | Co doplnit a podle čeho převzít |
|---|---|---|
| R38 · P0 · R03, R07, R08 | Kandidáty shod osob a návrh převodu historie. | Potvrdit mapu osob, rodinných vztahů, rolí, skupin, programů a sportovních záznamů. Výstup: každá převáděná vazba má jednoznačný cíl nebo výslovné vyřazení/karanténu; nejasné osoby se automaticky nespojují. |
| R39 · P0 · R03, R16, R17, R24, R32, R34 | Současná cílová data, Shoptetové podklady a zjištěné finanční rozdíly. | Převzít otevřené objednávky, rezervace, fyzickou inventuru, doklady a finanční zůstatky včetně záloh/odměn. Zachovat čísla dokladů a VS; nepřevést historickou úhradu znovu jako nový příjem. Výstup: schválené počáteční stavy a vysvětlené rozdíly včetně Harrachova. |
| R40 · P1 · R01, R03, R09 | Existující soukromé soubory a veřejné weby; seznam všech webů ještě není uzavřen. | Zpracovat mapu příloh, vlastníků, veřejného obsahu, URL a cílových stránek; určit co migrovat a co archivovat. Výstup: odkazy a oprávnění fungují, nezůstanou osiřelé doklady a žádný provozovaný web nezmizí bez náhrady používané funkce. |
| R41 · P0 · R06, R37, R38, R39, R40 | Importní základy a migrační kontrakty; plný nahrazovací import osob není vhodný. | Doplnit aditivní opakovatelný převod se zdrojovým ID, dávkou, náhledem, protokolem, řešením konfliktů a rozdílovými změnami. Provést celý převod nanečisto. Výstup: druhý běh netvoří duplicity, cílová novější data zůstávají zachována, počty/vazby/peníze/kusy/přílohy jsou odsouhlaseny. |
| R42 · P0 · R06, R09, R11, R37, R41 | Provozní a nasazovací nástroje, návrh návratového postupu. | Připravit podporu, návody, integrační inventuru, dohled a nacvičit návrat **před pilotem**. Výstup: návrat zachová nové platby, výdeje a jiné změny; samotná obnova DB není řešení externích transakcí. Každá použitá integrace má ověřené nastavení a vlastníka. |
| R43 · P0 · R41, R42 | Návrh pilotu a seznam kroků přepnutí. | Provést omezený pilot, vyhodnotit jej, uzavřít přesný časový plán a až po samostatném rozhodnutí provést ostré přepnutí. Výstup: jediný zapisující systém pro každou agendu/případ, konečné rozdílové převody, kontrolní součty a potvrzení otevření nových zápisů. |
| R44 · P1 · R43 | Přejímací pravidla a plán archivace. | Stabilizovat provoz, převzít první skutečný účetní měsíc, ověřit pozdní platby a obnovu archivu; teprve potom odstavovat staré části podle registru R01. Výstup: vedení potvrdí náhradu všech používaných agend a dohledatelnost historie. |

## 4. První balík k implementaci: ochrana uzavřené finanční evidence

**Rozsah balíku B1: R04 + R05.** Potřebuje přiřadit garanta a přijmout níže navržená pravidla; nemusí čekat na kompletní mapu osob, účetní program nebo nový sklad. Vedení/hospodář jsou navržení garanti, konkrétní osoby ještě nejsou určeny.

Proč začít zde: účtenky i odměny už existují, ale další práce musí zachovat uzavřené podklady. Jde o ohraničenou změnu stávajících postupů, kterou lze ověřit bez ostrých dat. Současně vznikne bezpečné prostředí pro následující dodávky. Následujícím balíkem má být společný základ osob a oprávnění R07–R12, poté finanční základ R13–R17; nikoli další rozšiřování první opravy do kompletního účetnictví.

### Výchozí stav a upřesnění dřívější analýzy

- `uctenky/uloz.php`, `uctenky/smazat.php` a `udalosti/uzavrit.php` obsluhují existující výdaje a uzavření. Potřebujeme souvislou ochranu změn podle stavu finanční akce, včetně přesunu účtenky mezi akcemi a souběžného uzavření.
- `sprava_sportovec_obdobi.php` už při uzavření ukládá počet tréninků a částku. Nový modul snímků se proto nezakládá; chrání se existující snímek, editace, opakované uzavření a vyplacené období.
- `hromadne_odmeny.php` nemá vlastní zjevnou kontrolu `canAccess` v úvodu, ale načítá `db.php`, které volá `staffEnforceCurrentRoute`; cesta patří do finanční pracovní pozice. **Není proto podloženo tvrzení, že jde o veřejně nebo každému trenérovi přístupný zápis.** Balík má ověřit stávající ochranu a doplnit jen skutečné mezery v oprávnění konkrétní operace.
- Příznak vyplacení není doklad peněžního pohybu. B1 tento dlouhodobý nedostatek neřeší; úplné vypořádání odměn patří do R34.

### Konkrétní pracovní položky B1

| Pořadí | Úkol | Přesný výstup / přejímka |
|---|---|---|
| B1-01 | Připravit izolaci a výchozí scénáře R04. | Syntetické otevřené/uzavřené finanční akce, účtenky a otevřená/uzavřená/vyplacená období. Připravené existující testovací nástroje; žádný přístup testu k ostrým příjemcům nebo platebnímu provedení. |
| B1-02 | Inventarizovat všechny zápisové cesty těchto dvou agend a prověřit oprávnění. | Seznam HTTP akcí, společných helperů a případných skriptů; pro každou akci pracovní pozice, oprávnění, CSRF a stavová podmínka. Opravit pouze prokázané mezery. Žádná druhá soustava rolí. |
| B1-03 | Chránit uzavřenou finanční akci. | Zakázat běžné přidání, změnu, smazání účtenky, změnu přílohy a přesun do/z uzavřené akce; chránit také přímou změnu/smazání uzavřené akce. Prověřit starý i nový případ při přesunu. Stav ověřit atomicky s mutací, před nevratným zásahem do souborů. |
| B1-04 | Chránit období odměn. | Zakázat změnu sazby, dat, osoby a mazání uzavřeného nebo vyplaceného období ve všech běžných cestách. Opakované uzavření nemění uložený počet a částku. Hromadná změna nesmí zasáhnout chráněná období. Chybějící historický snímek nepřepočítat automaticky dnešními pravidly. |
| B1-05 | Vysvětlit blokaci a dohledat důvod. | Zobrazit konkrétní důvod a ID chráněného případu; opravu předat odpovědnému hospodáři. Použít existující audit tam, kde vzniká změna. Dočasně blokovanou opravu jasně odlišit od provedené opravy; žádné skryté administrátorské obejití. |
| B1-06 | Ověřit celek a předat změnu k posouzení. | Cílené regresní a souběžné testy, ruční průchod oprávněné obsluhy v izolaci, seznam změněných cest a omezení. Výstup: konkrétní přehled změn a důkaz přejímky. Nasazení až v následném samostatně autorizovaném kroku. |

B1-02 navazuje na B1-01. B1-03 a B1-04 navazují na B1-02 a lze je připravovat souběžně. B1-05 se dokončí po obou ochranách; B1-06 uzavírá celý balík.

**Navržená stavová pravidla B1:** běžná editace otevřeného případu zůstává možná; uzavřený případ se běžnou editací nemění. Potřebná věcná oprava bude později samostatný auditovaný postup R31/R34. Starý záznam s příznakem vyplacení se chrání i při nekonzistentním označení uzavření. Stavové konflikty se vypíší do seznamu k řešení, nepřepisují se hromadně.

### Povinné důkazy pro převzetí B1

1. Oprávněný hospodář vytvoří a upraví výdaj otevřené akce; neoprávněná pozice neprovede přímý zápis ani přes znalost URL.
2. Po uzavření nelze účtenku přidat, upravit, smazat, vyměnit její soubor ani změnit/smazat samotnou uzavřenou akci.
3. Přesun účtenky selže, je-li uzavřen zdroj nebo cíl; účtenka i soubory zůstanou beze změny.
4. Souběžné uzavření a úprava nemohou obejít kontrolu stavu; neúspěšný zápis nezanechá osiřelý nebo ztracený soubor.
5. První uzavření období odměn uloží správný souhrn; opakování vrátí stávající výsledek nebo srozumitelnou blokaci a nepřepočítá jej.
6. Uzavřené i vyplacené období nelze změnit běžnou editací, smazáním ani hromadnou sazbou; otevřené období funguje jako dosud.
7. Historický uzavřený záznam bez souhrnu je viditelně nevyřešený; nevytvoří se domnělá minulá částka.
8. Zamítnutí nezmění jiné osoby, VS, peněžní stavy ani doklady; změny pod správným oprávněním mají auditovatelný původ.
9. CSRF a deaktivovaný účet/odebraná pracovní pozice jsou pokryty negativními testy.
10. Hospodář v izolaci rozpozná důvod blokace a postup dalšího řešení; omezení dočasně chybějícího opravného procesu přijme výslovně.

**Mimo B1:** nové faktury a export, účetní klasifikace, změna výpočtu otevřených odměn, skutečné výplaty, přepracování docházky, nové skladové pohyby, migrace reálných osob, přenos historie, aktivace bran/bank/CRON, nasazení. Tréninkové opravy a jejich dopad na odměny řeší R26/R34; B1 není úplnou ochranou všech historických sportovních podkladů.

## 5. Brány mezi návrhem, pilotem a ostrým provozem

| Brána | Nezbytný výsledek | Kdo potvrzuje |
|---|---|---|
| G0 — základ pro implementaci | Přijatý rozsah konkrétní dodávky, její pravidla a izolované prostředí. | Garant agendy + správce |
| G1 — společné základy | R01–R17 podle potřeby používaných agend; doložené identity, přístupy, audit, obnova a peněžní model. | Registrář, hospodář, správce |
| G2 — funkční celek | R18–R37 pro celý potvrzený rozsah; podmíněné funkce buď převzaté, nebo doloženě nepoužívané. | Obsluha + účetní |
| G3 — připravený pilot | Schválené mapy a zůstatky, úspěšný převod nanečisto, provozní dohled a nacvičený návrat R38–R42. | Správce dat + garanti |
| G4 — připravené ostré přepnutí | Úspěšný pilot, vyřešené závažné závady, pevná revize, přesný plán přepnutí a samostatné rozhodnutí zahájit jej. | Vedení |
| G5 — nahrazené staré systémy | První skutečný měsíc převzatý účetní, stabilní provoz, ověřený archiv a uzavřený registr nahrazovaných funkcí. | Vedení + účetní + garanti |

Pilot navrhuji na omezené skutečné skupině a sortimentu, nejméně přes dva celé tréninkové týdny a jedno účetní předání; konkrétní délka závisí na výskytu potřebných scénářů. Chybějící vratku či doplatek lze před pilotem prokázat v izolaci, nelze ji bez důkazu označit za ověřenou. Rozšíření pilotu není automatické.

Pro každou agendu a obchodní případ existuje jeden zapisující systém. U společných posledních kusů nelze bez společného řízení rezervací současně přijímat objednávky v obou e-shopech. Přepnutí určí příjem pozdních plateb, nedokončené objednávky, refundace a příchozí události z bran; fronty se nemažou. Návrat po nových transakcích musí převzít rozdílová data a vypořádat skutečně vydané zboží i peníze.

## 6. Sloučení překryvů a rozhodnutí, která nelze přeskočit

| Oblast | Jeden vlastník implementace / pravidlo |
|---|---|
| Osoba, rodič a členství napříč etapami | R07–R10. Migrační mapa R38 používá tentýž model, nezavádí další evidenci osob. |
| Peníze z obchodu, příspěvků, záloh i odměn | R13–R17. Výdaje a odměny vytvářejí podklady a vazby, nikoli druhou nezávislou pokladnu. |
| Výdaje a zálohy původně jako minimum etapy 2 | Kompletní používaný postup R31/R32; potvrzené papírové agendy nejsou volitelné rozšíření. |
| Práva, soukromé soubory a audit | Společné R09–R12, u každého modulu povinné doplnění jeho operací a negativních testů. |
| Účetní export a uzavření měsíce | Jeden celek R36. Sklad i odměny přidávají datové části stejného balíku. |
| Počáteční stavy z etap 2, 3 a 5 | Jedno odsouhlasení R39; pohled účetní, skladu a obsluhy musí vycházet ze stejného rozhodného okamžiku. |
| Sportovní historie a členové | R38 určuje vazby, R41 provádí schválený převod. Kandidáti shody nejsou automatické rozhodnutí. |
| Záloha a návrat | R06 prokazuje obnovu; R42 řeší návrat skutečného provozu včetně transakcí vzniklých po přepnutí. |
| Přejímky | Testy při každé dodávce, společný souvislý měsíc R37, provozní pilot R43 a první ostrý měsíc R44. |

Otevřené rozhodovací body mají vlastníka a nejzazší okamžik:

- Účetní metodika, vystavovatel, čísla řad a vzor předání: účetní/hospodář, před dokončením R13–R17. Potvrzení jednoduchého účetnictví samo tyto detaily nenahrazuje.
- Pravidla členství, souběh rolí rodič/plátce a nástup uprostřed období: registrář/vedoucí sportu, před R08/R25.
- Schvalování vlastních výdajů, odměn a oprav: vedení/hospodář, před R31–R35; pravidla B1 se přijímají již pro první balík.
- Nesrovnalosti identity, finanční rozdíly a rozsah historie: správce dat/garanti, před dokončením R38/R39. Například záloha Harrachov 0 Kč versus 2 000 Kč se nerozhoduje podle toho, který systém je novější.
- Úplný seznam nahrazovaných webů a jejich funkcí: vedení v R01, převzetí obsahu v R40. Bez seznamu nelze slíbit náhradu „všech webů“.

Celkový termín a pracnost se mají odhadnout po přiřazení lidí, ověření rozsahu mezer a přijetí prvního balíku. Počet již existujících obrazovek není spolehlivý odhad práce. Před každou dodávkou vznikne konkrétní seznam změn a relevantních přejímacích scénářů.

## 7. Vazba na původních 98 úkolů

Každý původní úkol má níže právě jednoho hlavního vlastníka v novém plánu. Jeho další závislosti a dílčí ověření zůstávají popsány v realizačních úkolech a původních specifikacích. Jde o sloučení zadání, nikoli o vypuštění původních přejímacích podmínek. Rozdíly priorit oproti jednotlivým etapám se řídí společnými definicemi tohoto dokumentu.

| Původní etapa | Přiřazení původního ID → hlavní realizační úkol |
|---|---|
| 0 — rozhodnutí a data | E0-01 → R02; E0-02 → R02; E0-03 → R01; E0-04 → R03; E0-05 → R38; E0-06 → R39; E0-07 → R41 |
| 1 — společný základ | E1-01 → R04; E1-02 → R07; E1-03 → R08; E1-04 → R10; E1-05 → R09; E1-06 → R10; E1-07 → R11; E1-08 → R11; E1-09 → R06; E1-10 → R12; E1-11 → R12; E1-12 → R37 |
| 2 — účetnictví | E2-01 → R02; E2-02 → R13; E2-03 → R14; E2-04 → R14; E2-05 → R15; E2-06 → R15; E2-07 → R16; E2-08 → R17; E2-09 → R31; E2-10 → R16; E2-11 → R36; E2-12 → R36; E2-13 → R09; E2-14 → R39; E2-15 → R37 |
| 3 — sklad a prodej | E3-01 → R18; E3-02 → R19; E3-03 → R39; E3-04 → R20; E3-05 → R21; E3-06 → R22; E3-07 → R21; E3-08 → R22; E3-09 → R23; E3-10 → R24; E3-11 → R24; E3-12 → R24; E3-13 → R36; E3-14 → R09; E3-15 → R37 |
| 4 — členové a sport | E4-01 → R08; E4-02 → R08; E4-03 → R25; E4-04 → R25; E4-05 → R26; E4-06 → R26; E4-07 → R27; E4-08 → R28; E4-09 → R28; E4-10 → R29; E4-11 → R30; E4-12 → R30; E4-13 → R38; E4-14 → R09; E4-15 → R37 |
| 5 — administrativa | E5-01 → R01; E5-02 → R32; E5-03 → R31; E5-04 → R31; E5-05 → R32; E5-06 → R32; E5-07 → R16; E5-08 → R33; E5-09 → R33; E5-10 → R34; E5-11 → R35; E5-12 → R35; E5-13 → R36; E5-14 → R05; E5-15 → R39; E5-16 → R37 |
| 6 — převod a provoz | E6-01 → R01; E6-02 → R04; E6-03 → R03; E6-04 → R06; E6-05 → R38; E6-06 → R38; E6-07 → R39; E6-08 → R39; E6-09 → R40; E6-10 → R41; E6-11 → R41; E6-12 → R42; E6-13 → R43; E6-14 → R43; E6-15 → R43; E6-16 → R42; E6-17 → R44; E6-18 → R44 |

U E5-14 představuje R05 první ochranu; úplné opravy a archivace se dokončují přes R09, R31, R34 a R36. U E2-13/R09 je obnova podkladů závazně součástí R06. Toto přiřazení nezkracuje rozsah původních úkolů.

## 8. Podrobné podklady

- [Etapa 0 — rozhodnutí, papírové agendy a porovnání dat](../etapa-0-2026-10-22/00_ETAPA_0.md)
- [Etapa 1 — společný základ](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md) a [přejímka a zdroje](../etapa-1-2026-10-22/01_PRIJEMKA_A_ZDROJE.md)
- [Etapa 2 — účetní doklady a export](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md) a [kontrakt exportu](../etapa-2-2026-10-22/01_KONTRAKT_EXPORTU_PRIJEMKA_A_ZDROJE.md)
- [Etapa 3 — sklady, prodej a vratky](../etapa-3-2026-10-22/00_ETAPA_3_PLAN.md) a [skladový model a přejímka](../etapa-3-2026-10-22/01_SKLADOVY_MODEL_PRIJEMKA_A_ZDROJE.md)
- [Etapa 4 — členská a sportovní agenda](../etapa-4-2026-10-22/00_ETAPA_4_PLAN.md) a [pravidla a přejímka](../etapa-4-2026-10-22/01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md)
- [Etapa 5 — klubová administrativa](../etapa-5-2026-10-22/00_ETAPA_5_PLAN.md) a [pravidla a přejímka](../etapa-5-2026-10-22/01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md)
- [Etapa 6 — migrace a přechod](../etapa-6-2026-10-22/00_ETAPA_6_PLAN.md), [postup přepnutí](../etapa-6-2026-10-22/01_MIGRACE_PILOT_A_PRECHOD.md) a [přejímka](../etapa-6-2026-10-22/02_PRIJEMKA_A_ZDROJE.md)

Zdrojové body dočtené při sjednocení: [centrální ochrana cest](../../../../includes/staff_workspaces.php), [její zapojení](../../../../db.php), [období odměn](../../../../sprava_sportovec_obdobi.php), [hromadné sazby](../../../../hromadne_odmeny.php), [uložení účtenky](../../../../uctenky/uloz.php), [smazání účtenky](../../../../uctenky/smazat.php), [uzavření finanční akce](../../../../udalosti/uzavrit.php). Ostatní detailní zdrojové odkazy jsou u jednotlivých etap. Všechna budoucí implementační rozhodnutí se znovu ověří proti tehdy aktuální revizi a cizí rozpracované změny se zachovají.
