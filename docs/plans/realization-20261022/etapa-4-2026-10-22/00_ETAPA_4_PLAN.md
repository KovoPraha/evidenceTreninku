# Etapa 4 — členská a sportovní agenda

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Zadání k 22. 10. 2026. Pouze příprava; bez implementace, změn členství, soupisek, docházky, plateb, importů a produkce.

**Členská a sportovní část už má rozsáhlý základ. Potřebujeme propojit existující funkce do jednoho běžného postupu, sjednotit staré a nové výpočty členství, doplnit zdravotní prohlídky, bezpečně uzavírat a opravovat docházku a připravit převod sportovní historie. Soupisky, plánování, omluvy, náhradní lekce ani výsledky se nemají stavět znovu.**

Cílový postup: přijetí člověka → ověřená osoba a případně rodič → členství nebo účast na konkrétním programu → zařazení do soupisky → příspěvek či nákup → rozvrh a potřebné podklady → skutečná docházka → sportovní historie a podklady pro klubovou administrativu. Musí fungovat i při osobním vyřízení a dodatečném přepisu papíru.

## Podklady a hranice

Navazuje na [etapu 0](../etapa-0-2026-10-22/00_ETAPA_0.md), [etapu 1](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md), [etapu 2](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md) a [etapu 3](../etapa-3-2026-10-22/00_ETAPA_3_PLAN.md). Podrobné pravidlo, přejímku a zdrojové odkazy obsahuje [příloha](01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md).

„Máme“ znamená nalezenou implementaci v ověřené revizi `origin/main` **68b22bd2b3b666dbb4fb571a2731eae5b7f056d9**. Neznamená to potvrzené aktuální produkční nastavení ani úspěšný úplný provozní test. Počty z Evidence a KIS níže jsou měřením etapy 0 z 22. 10. 2026; v této etapě se znovu neměřily.

Oddíl je neplátce DPH, vede jednoduché účetnictví a účetní dostává XLSX a faktury. To zůstává odpovědností etapy 2. Etapa 4 dodává důvod předpisu, osobu, období, program a skutečně poskytnutou službu. Vyúčtování akcí, schvalování odměn a záloh patří do etapy 5. Převod a odstavení původních systémů do etapy 6.

Půjčování kol a vybavení je mimo rozsah. Trenérské kvalifikace a závodní licence zařazujeme podmíněně, pokud je oddíl skutečně potřebuje sledovat. Neplánujeme nový závodní časoměrný systém ani automatické zdravotní hodnocení sportovců.

## Co již máme a co je potřeba doplnit

| Oblast | Existující základ | Zbývající práce |
|---|---|---|
| Osoby a přijetí | Karty sportovců, registrace, kontrola shod, schválení a přiřazení; rodinné role | Jednotný přehled od přihlášky k přijetí a odchodu, osobní vyřízení, návaznost na etapu 1 |
| Členství | Evidované stavy, ruční výjimky, vazby na soupisky a programy | Odstranit používání starých platebních příznaků a starých skupin jako náhrady za rozhodnutí o členství |
| Skupiny a soupisky | Časově platná hierarchie, členství od–do, náhled dopadů, deduplikace | Dokončit přiřazení skutečných soupisek a sjednotit zbývající staré přehledy |
| Kroužky a kurzy | Stálý program, nabídky od–do, varianty, kapacita, placená aktivace, opakování nabídek | Přejímka osobního přijetí, schvalované výjimky, přechody a pravidla konkrétních programů |
| Příspěvky | Plány, měsíční generování, náhled, výjimky, stálý VS | Výslovný plátce, vstup uprostřed měsíce, souběh plánů, opravy a spojení s účetními doklady |
| Tréninky | Plány a skutečné tréninky, soupisky očekávaných osob, trenéři, sportoviště | Přehled neuzavřených lekcí, jednotné uzavření a bezpečná oprava či zrušení |
| Omluvy a náhrady | RSVP, lhůty, nároky, kapacita náhrad, spotřeba a vracení nároků | Nastavit používaná pravidla a prokázat celý průchod včetně osobního vyřízení a oprav |
| Závody a akce | Kalendář, účastníci, registrace, finance, propojení na závod a výsledky | Rozlišit nominaci, přihlášení a skutečný start; uzavřít akci bez zkreslení účasti |
| Sportovní historie | Docházka, výsledky, měření, zátěžové testy, roční časová osa | Přenést chybějící historii, udržet jednotky a původ, sjednotit souhrny |
| Zdravotní prohlídky | Osoba a obecné nástroje pro soukromé podklady; zátěžové testy | Samostatnou evidenci platnosti a ověření prohlídky jsem v kontrolovaných zdrojích nenalezl |
| Výkazy a odměny | Výkazy trenérů a starší výpočty sportovních odměn | Spolehlivý uzavřený podklad pro etapu 5, jednotné soupisky a ochrana před zpětnými změnami |

## Priority a návaznosti

**P0** = podmínka převzetí dané používané agendy do KIS. **P1** = navazující dokončení; před vypnutím původní Evidence musí být hotové i její používané funkce. Volitelnou funkci lze odložit jen s jasným označením, že ji nový systém ještě nepřebírá.

| Úkol | Priorita | Hlavní závislosti | Vlastník přejímky |
|---|---|---|---|
| E4-01 Životní cyklus členství | P0 | E1-02, E1-03, E1-04, E1-06 | Správce členství / vedení |
| E4-02 Skupiny, soupisky a přestupy | P0 | E4-01; E1-10 | Hlavní trenér |
| E4-03 Přihlášky a účast v programech | P0 | E4-01, E4-02; E1-04, E1-06; E2-06, E2-08 | Koordinátor kroužků |
| E4-04 Členské příspěvky | P0 | E4-01, E4-02; E2-02 až E2-08 | Správce členství + hospodář |
| E4-05 Rozvrh a plánování | P0 | E4-02, E4-03; E1-07, E1-08 | Hlavní trenér |
| E4-06 Docházka a její uzavření | P0 | E4-05; E1-05, E1-10 | Trenér + hlavní trenér |
| E4-07 Omluvy a náhradní lekce | P0 pro používané omluvy/náhrady | E4-03, E4-05, E4-06; E1-04, E1-07 | Koordinátor kroužků |
| E4-08 Akce a závody | P1; P0 pro závodní pilot | E4-02, E4-10; E1-06; E2-06, E2-08 | Vedoucí akce / trenér |
| E4-09 Výsledky, měření a sportovní historie | P1; P0 pro převzetí Evidence | E4-06, E4-08, E4-14 | Hlavní trenér |
| E4-10 Prohlídky a další časově platné podklady | P0 pro potvrzenou zdravotní agendu | E1-02, E1-05, E1-06, E1-07 | Pověřený správce podkladů |
| E4-11 Navazující období a věkové přechody | P1; před prvním takovým přechodem P0 | E4-02 až E4-04, E4-07; E1-08 | Hlavní trenér + koordinátor |
| E4-12 Přehledy a podklady pro odměny | P1; před předáním etapy 5 P0 | E4-04, E4-06 až E4-10 | Vedení + hospodář |
| E4-13 Kontrakt převodu sportovních dat | P0 před ukončením Evidence | E0-04 až E0-07; E1-02; E4-01, E4-02, E4-09 | Správce dat + hlavní trenér |
| E4-14 Přístupy, přílohy a zveřejnění | P0, průběžně | E1-04 až E1-06, E1-10 | Správce přístupů |
| E4-15 Provozní přejímka | P0 | Předchozí úkoly v rozsahu přebíraných agend | Vedení a skutečná obsluha |

### E4-01 — Sjednotit životní cyklus členství

**Máme:** kartu sportovce, registrace a jejich schvalování, přiřazení do klubových struktur, rodinné role a ruční členský stav. Existuje také starší `sportovecStatusCompute()`, který odvozuje stav z importovaných příznaků `kis_aktivni`, `kis_platebne_aktivni`, `kis_neuhrazeno` a starých skupin. Tento výpočet se stále používá v kartě/přehledech. [Z1, Z2]

**Doplnit:** jedno pravidlo členství podle E1-03 a schváleného postupu oddílu: žádost, rozhodnutí o přijetí, účinnost od–do, případné přerušení a ukončení s důvodem a oprávněnou osobou. Převést všechny přehledy na stejný význam stavu. Uchovat ruční rozhodnutí a jejich původ; staré příznaky používat jako migrační podklad, nikoli jako trvalou autoritu.

Přihlášení k účtu, členství v oddílu, soupiska, zaplacení a oprávnění navštěvovat konkrétní kurz jsou samostatné věci. Nákup veřejností nevytváří členství. Dluh sám nepřepisuje členský stav. Odchod člena ukončí relevantní budoucí vazby, zachová historii a otevřené pohledávky; smazání účtu není ukončení členství.

**Hotovo, když:** osobní i online přijetí dospěje ke stejné osobě a doloženému rozhodnutí; karta, seznam, soupiska a rodinný přehled si neodporují. Je ověřen přerušený člen, návrat i člen bez účtu. Přejímka P01–P04.

### E4-02 — Dokončit skupiny, soupisky a datované přestupy

**Máme:** skupinu nad soupiskami, časově platné vazby a členství, výběr účastníků k datu, deduplikaci při více cestách, náhled dopadů změny hierarchie a ochranu navázaných budoucích aktivit. [Z3]

**Doplnit:** schválený seznam skutečných skupin/soupisek a jejich odpovědných trenérů. Dořešit staré nepřiřazené záznamy ručně tam, kde není jednoznačná shoda. Prověřit všechny zbývající výběry a reporty používající staré `skupiny`/`podskupiny`, včetně podkladů pro odměny. Člena přidávat do koncové soupisky, členství ve skupině odvozovat.

Přestup musí mít datum a náhled dopadu na rozvrh, předpisy, přihlášky a náhrady. Současná příslušnost k silniční a dráhové soupisce je přípustná; odchod z jedné neukončí druhou. Historické sestavy se počítají k datu události, nikoli podle dnešní soupisky.

**Hotovo, když:** stejný člověk není v jedné lekci dvakrát, historická účast se přestupem nepřepíše a změna s již navázanými přihláškami nabídne konkrétní řešení dopadu. P05–P06.

### E4-03 — Dokončit přihlášky a účast v kurzech a kroužcích

**Máme:** registrace sportovce s kontrolou identity, programy a datované nabídky, vazbu objednávkové položky na konkrétní nabídku, kapacitu a životní cyklus placené účasti. Není nutné znovu vyvíjet přihlašování ani kapacitní jádro. [Z2, Z4]

**Doplnit / ověřit:** ucelený postup pro osobně přijatou přihlášku: vyhledání osoby → doložení přihlášky/souhlasu podle E1-06 → výběr přesné nabídky a plátce → předpis či objednávka → potvrzená úhrada → aktivní účast. Přihlášku může zpracovat obsluha, rodinné oprávnění musí zůstat ověřené. Zaplacená nevyřešená identita má být viditelný případ k dořešení, nikoli důvod k založení druhého dítěte.

Stanovit postup zkušební návštěvy, bezplatné účasti a prominutí ceny, pokud je oddíl používá: schválený důvod a konkrétní rozsah, bez fiktivního příjmu. U přestupu mezi nabídkami řešit kapacitu, staré nároky i finanční rozdíl. Při plné kapacitě jasné odmítnutí; jednoduchý pořadník je P1, pouze pokud ho obsluha potřebuje.

**Hotovo, když:** osobní a online přijetí sdílí kontrolu kapacity; souběh žádostí nepřeplní nabídku; zrušení nebo vrácení peněz nevytvoří nejasný stav účasti. P07–P10.

### E4-04 — Dokončit členské příspěvky a návaznost na účast

**Máme:** měsíční příspěvkové plány, náhled generování, časově omezené výjimky, unikátní předpisy v rámci plánu a období a stálý VS. Výběr členů již pracuje s datovanou hierarchií. [Z5]

**Doplnit:** pravidla pro vstup/odchod uprostřed měsíce, přerušení, souběh více plánů a schválené úlevy. Současný výběr zahrne osobu přítomnou alespoň některý den období a generování pracuje s částkou plánu; nelze bez rozhodnutí oddílu předpokládat poměrné krácení.

Zpřesnit určení plátce: současná funkce vybírá první vyhovující schválený účet s předností role `self`, jinak `guardian`. Pro rodinu s více oprávněnými osobami musí být smluvený plátce jednoznačný; oprávnění rodiče není samo dohodou o placení. Zachovat VS a historii změn plátce. Účetní doklad, peněžní tok a vratku řeší etapa 2.

**Hotovo, když:** náhled vysvětlí, komu, za koho, za co, za které období a proč vzniká částka. Opakované generování neduplikuje předpis; případné omezení služby pro dluh je výslovné, přezkoumatelné pravidlo, nikoli skryté zrušení členství. P11–P13.

### E4-05 — Sjednotit rozvrh, trenéry a sportoviště

**Máme:** plánované tréninky, návaznost na skutečný trénink, výběr skupiny/soupisek, očekávané účastníky a vazby na trenéry a rezervace sportovišť. Změny s existujícími RSVP či náhradami už mají ochrany. [Z6, Z7, Z8]

**Doplnit / ověřit:** jeden provozní přehled termínu, místa, odpovědného trenéra a záskoku. Rozlišit kapacitu prodané nabídky, kapacitu konkrétní lekce a kapacitu sportoviště. Prověřit kolize trenéra, místa a souběžných skupin v používaných cestách založení i úpravy. Pokud je společná lekce záměrná, zobrazit ji jako jednu událost s více soupiskami.

Při zrušení/přesunu s navázanými reakcemi zachovat původní událost a auditovanou návaznost na náhradní termín; nepřepsat mlčky datum. Zajistit správné adresáty a stav doručení přes E1-07, včetně viditelné položky k osobnímu vyřízení pro rodiny bez elektronického kontaktu.

**Hotovo, když:** trenér a rodina vidí stejný platný termín; zrušený termín neuplatní docházku a po změně nezůstane stará rezervace či nárok bez vysvětlení. P14–P15.

### E4-06 — Uzavírat skutečnou docházku a bezpečně ji opravovat

**Máme:** očekávané osoby podle plánu, samostatnou skutečnou účast, porovnání očekávaných/chybějících/nečekaných a vazbu plánu na zapsaný trénink. Skutečná účast se nemá odvozovat z RSVP. Úpravy tréninku obsahují návaznost na přepočet náhrad. [Z6–Z8]

**Doplnit:** přehled proběhlých neuzavřených lekcí, potvrzení skutečného času/délky, trenérů a účastníků, autora a okamžiku uzavření. Přepis z papíru musí rozlišit datum konání a datum zápisu. Nezapsaná lekce není hromadná absence; kopie očekávané soupisky je návrh, který trenér potvrdí. Doplnění skutečně přítomného hosta má mít důvod a vyřešenou identitu.

Zavést společnou kontrolu závislostí pro opravu i odstranění: náhradní nároky, měření, uzavřené výkazy a schválené odměny. Ve zdroji existuje přímé transakční mazání tréninku a jeho vazeb; v jeho obsluze není volání `clubMakeupReconcileTraining()`. Proto nestačí převzít bezpečnost samotné editace. U navázané či uzavřené historie má běžná obsluha provést auditovanou opravu/zneplatnění, nikoli ztrátu záznamu.

Prověřit také předčasné uzavření: funkce pro vznik náhrad ověřuje, že lekce začala, což samo neprokazuje její skutečné dokončení. Cílové pravidlo je nárok a definitivní docházka až po skončení; předčasný zápis může být pouze rozepsaný.

**Hotovo, když:** oprava má důvod, autora a původní hodnoty; nezdvojí trénink ani nárok a nemění tiše již schválený podklad pro odměnu. P16–P18, P22, P33.

### E4-07 — Dokončit provoz omluv a náhradních lekcí

**Máme:** RSVP, pravidla omluvy, záznam lhůty, vznik nároku z oprávněné absence, rezervaci náhradního termínu s kontrolou kapacity, využití/neúčast, storna a reakci na změnu docházky či zrušení účasti v programu. Existuje také omezený převod nároku do navazující nabídky. [Z7]

**Doplnit / ověřit:** rozhodnout, které programy náhrady používají a s jakými pravidly. Čas pro RSVP není automaticky lhůtou nároku na náhradu. Pro obsluhu sjednotit osobně přijatou omluvu, čas přijetí, autora přepisu a zdůvodněnou výjimku bez zpětného přepisování skutečného času. Rodina má vidět nárok, platnost, rezervaci a srozumitelný důvod zamítnutí.

Přejmout hraniční situace: pozdní omluva, omylem zapsaná přítomnost, souběh roční/pololetní nabídky, neznámý věk, poslední místo, zrušení pořadatelem, nepříchod na náhradu a rušení zdrojového programu po využití náhrady. Náhradní lekce není peněžní přeplatek ani sportovní odměna.

**Hotovo, když:** jeden důvod nezaloží dvojí nárok, jeden nárok nelze využít dvakrát a každá výjimka je dohledatelná. Pokud oddíl náhrady nepoužije, není jejich aktivace podmínkou běžné evidence omluv. P19–P23.

### E4-08 — Dokončit klubové akce a závody

**Máme:** klubový kalendář, účastníky a registrace, platební návaznosti, plánovací údaje a vytvoření/propojení závodu v původním výsledkovém modulu. Existující propojení zachovat. [Z9]

**Doplnit:** odlišit pozvání/nominaci, potvrzení účasti, přihlášení pořadateli, skutečnou účast a výsledek. Vytvoření výsledkového záznamu ani stav dokončeného plánování neprokazují start všech přihlášených. U každé akce určit vedoucího, termíny, místo srazu, odpovědné kontakty a požadované podklady. Dopravu využít v rozsahu existujícího kalendáře a reálné potřeby oddílu.

Pro osobní přihlášení používat stejné osoby a pravidla jako pro samoobsluhu. Schválit podobu základní soupisky/přehledu pro vedoucího, včetně omezeného přístupu ke kontaktům. Výdaje, cestovní zálohy a jejich vyúčtování předat etapě 5; neodvozovat platbu z účasti.

**Hotovo, když:** nominovaný člověk bez startu nezvyšuje počet absolvovaných závodů; storno opraví účast a případný předpis přes odpovídající proces. P24–P25.

### E4-09 — Sjednotit výsledky, měření a sportovní historii

**Máme:** tréninkovou historii, závody a výsledky, strukturovaná měření, starší textová měření a zátěžové testy. Datový kontrakt už rozlišuje jednotky, normalizovaný čas a stavy výsledku včetně DNS/DNF/DSQ. Máme také roční časovou osu a pomocné posouzení kvality importovaných dat. [Z10]

**Doplnit / ověřit:** stejná definice absolvovaného tréninku, minut, závodu a výsledku ve všech kartách, souhrnech a exportech. Plán + skutečnost nesmějí být dva tréninky. Zachovat původní hodnotu, jednotku, původ a vazbu na aktivitu; neznámá hodnota není nula. Staré texty bez jednoznačných jednotek nepřevádět odhadem.

Připravit jednoduché přehledy používané trenéry: účast za období, skutečný objem tréninků, výsledky a vývoj konkrétních měření. Přílohy zátěžových testů oddělit od veřejných výsledků. Zátěžový test nenahrazuje ověřenou platnost zdravotní prohlídky.

**Hotovo, když:** vzorek osoby za stejné období dává vysvětlitelný shodný výsledek v kartě, souhrnu a exportu; změna soupisky neztratí její historii. P26–P27, P35–P36.

### E4-10 — Doplnit zdravotní prohlídky a platnost podkladů

**Máme:** kartu osoby, rodinné vazby a obecný základ pro soukromé podklady v etapě 1. Existující zátěžové testy jsou jiná agenda. Samostatný postup pro ověření a sledování platnosti prohlídek jsem v kontrolovaných zdrojích nenalezl. [Z2, Z10, Z11]

**Doplnit:** minimální záznam osoby, účelu/druhu podkladu, data vystavení, platnosti do, data a osoby ověření a případně soukromé přílohy či odkazu na uložený originál. Rozlišit nedoloženo, čeká na ověření, platné, skončená platnost a nahrazeno/odvoláno. Platnost přebírat z podkladu, nevymýšlet univerzální roční lhůtu.

Pověřený pracovník ověří podklad; trenér standardně potřebuje použitelný stav pro konkrétní aktivitu a postup k dořešení, ne celý dokument. Notifikace před koncem platnosti i seznam nevyřešených případů napojit na E1-07. Oddíl stanoví, u kterých aktivit je doložení podmínkou a kdo rozhoduje o omezení; systém neurčuje zdravotní způsobilost sám.

Údaje o zdravotním stavu patří mezi zvláštní kategorie osobních údajů. Proto zadání počítá s minimem údajů, odděleným přístupem a potvrzením účelu, právního důvodu a doby uchování před zavedením. [ÚOOÚ — Slovníček pojmů](https://uoou.gov.cz/verejnost/qa-otazky-a-odpovedi/slovnicek-pojmu).

Závodní licenci a trenérské kvalifikace přidat jako samostatné druhy podkladu pouze při potvrzené potřebě. Existující UCI ID není doklad o aktuálně platné licenci.

**Hotovo, když:** systém vyhodnotí platnost k datu tréninku/závodu, nikoli pouze dnešnímu dni; upozorní odpovědnou osobu a nepřístupný dokument nelze získat přímým odkazem. P28–P30, P39.

### E4-11 — Připravit navazující nabídky a věkové přechody

**Máme:** stálou identitu programu, skupiny a soupisky, nabídky s daty od–do, generování opakovaných nabídek s náhledem a ochranu před duplicitou. Máme i starší náhled/řízení převodu soupisek včetně výjimek a kontroly věku. [Z3, Z4, Z12]

**Doplnit / ověřit:** používat tyto postupy podle skutečného účelu. Závodní členství může mít otevřený konec a pokračuje, dokud není řádně ukončeno. Nedělat povinnou každoroční kopii všech osob a členství. Půlroční/roční kroužek naopak potřebuje novou datovanou nabídku, schválenou cenu, kapacitu a rozsah služby.

Věkový přestup do jiné soupisky řešit náhledem a datovanou změnou, nikoli odhadem z neplatného data narození. Neznámý věk jde k ověření. Při změně období zachovat stálý VS a výslovně vyhodnotit náhradní nároky; nová nabídka nesmí sama předstírat zaplacenou účast. Existující plánovanou úlohu neaktivovat bez samostatného provozního rozhodnutí.

**Hotovo, když:** přechod období neduplikuje členství, nabídky ani platby; historie zůstává čitelná a rodina vidí přesné datum a cenu nové služby. P31–P32.

### E4-12 — Dokončit přehledy a podklady pro klubovou administrativu

**Máme:** karty sportovců, rodinné přehledy, výkazy trenérů a starší agendu sportovních odměn. Část odměnových přehledů ještě používá staré skupiny; počty tréninků se mohou počítat z aktuálních vazeb. [Z13]

**Doplnit:** pracovní přehled „co musím vyřídit“ podle role: nevyřešená přihláška, neověřený podklad, končící prohlídka, neuzavřená docházka, nevyřízená omluva a finanční případ. Rodině dodat vlastní členství, programy, budoucí termíny, náhrady a stav podkladů v přehledu E1-11, bez nového paralelního portálu.

Připravit ověřený podklad skutečné činnosti za období pro etapu 5: osoba, aktivita, datum, role trenéra/sportovce, rozsah, stav uzavření a verze. Schválené odměny nesmějí stát na čísle, které se bez upozornění změní pozdější editací tréninku. Etapa 5 bude vlastnit sazby, schválení a vyplacení; etapa 2 skutečný peněžní pohyb a doklad.

**Hotovo, když:** vedení dohledá souhrn k jednotlivým aktivitám a oprava po předání vytvoří rozdíl/verzi k posouzení. „Vypočteno“ není „vyplaceno“. P33–P34.

### E4-13 — Připravit kontrakt převodu historie z původní Evidence

**Máme:** porovnání etapy 0, kandidáty na spojení osob a popis sportovních zdrojů. KIS má cílové moduly, ale samotná jejich existence neznamená převedenou historii. [Z10, Z14]

Měření etapy 0: původní Evidence měla 1 651 tréninků proti 1 v KIS, 50 závodů proti 0 a 405 osob s vazbou na trénink proti 1. Z 405 osob bylo 324 jednoznačných kandidátů pro párování a 81 případů pro ruční posouzení. Kandidát není potvrzená identita; část starých tréninků byla budoucích, takže celkový počet není počet skutečně absolvovaných lekcí.

**Doplnit:** mapování osob a všech závislostí: trenéři, tréninky, skutečná docházka, skupiny/soupisky a jejich význam v čase, závody, výsledky, měření, zátěžové testy a soubory. Pro každou entitu stanovit původní ID, cílové ID, konfliktní pravidlo, kontrolní počty a způsob opakování bez duplicit. Zvlášť odlišit historický plán od skutečnosti a historickou odměnu od její úhrady.

Nepoužívat úplný import osob, který nahrazuje data, jako obecný migrační nástroj pro přidání historie. Výstupem této etapy je specifikace a seznam nevyřešených případů; migrační nástroj a ostrý převod patří do samostatně schválené etapy 6. Nyní nic neimportovat.

**Hotovo, když:** existuje schválená mapa a porovnání počtů/vazeb/souborů v návrhu; každá nejasnost má vlastníka. Při budoucí zkoušce převodu se nevytvoří druhý člověk ani duplicitní aktivita. P35–P38.

### E4-14 — Uplatnit přístupy a pravidla příloh v celé sportovní části

**Máme:** pracovní oprávnění, schválené rodinné role, jejich platnost a obecné auditní/souborové nástroje. Etapa 1 již řeší jejich společný základ. [Z2, Z11]

**Doplnit / ověřit:** jednotné vynucení na členské kartě, docházce, výsledcích, soukromých poznámkách, prohlídkách, přílohách i exportech. Trenér vidí potřebné osoby v rozsahu své činnosti; odebrání role se projeví i na přímém odkazu a novém exportu. Platební informace zpřístupnit jen v potřebném rozsahu, diagnózy nedávat do běžného seznamu.

Auditovat rovněž obrázky u tréninků a zátěžové soubory: bezpečné nahrání samo neprokazuje soukromé stažení. Oddělit interní přílohu od fotografie schválené ke zveřejnění a napojit oprávnění/podklady z E1-06. Pravidla uchování stanovit podle typu dat; ukončení členství není plošné smazání všech zákonně či provozně potřebných záznamů.

**Hotovo, když:** rodina nevidí cizí dítě, bývalý trenér nezíská soukromou přílohu a export respektuje stejné meze jako obrazovka. P29–P30, P39.

### E4-15 — Přejmout členskou a sportovní agendu s obsluhou

**Máme:** existující dílčí testy a historické ověřování jednotlivých funkcí. To samo nepotvrzuje celý běžný provoz oddílu ani aktuální stav produkce.

**Doplnit:** projít [40 scénářů přejímky](01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md) v izolovaném prostředí se správcem členství, trenérem, koordinátorem a zástupcem rodiny. Použít závodní skupinu, placený kroužek, osobně obsluhovaného člena bez účtu a rodinu s více dětmi. Ověřit také postup při výpadku a pozdějším přepisu papíru bez duplicit.

Každý scénář bude mít výsledek, doklad, verzi systému, vlastníka vady a opakované ověření po opravě. Provozní pilot a skutečné převody teprve po zvláštním odsouhlasení. Před vypnutím Evidence dokončit i historii a používané P1 funkce; před vypnutím všech ostatních agend navíc etapy 5–6.

**Hotovo, když:** vedení převezme konkrétní rozsah, obsluha zvládne běžné i opravné postupy a každá přetrvávající papírová agenda má výslovně uvedený důvod a termín náhrady. P40 a celá přejímka.

## Doporučené pořadí realizace

1. **Členský základ:** E4-01 a E4-02; souběžně E4-10 a E4-14. Uzavřít význam členství, časové vazby a přístupy. Připravit rozhodnutí pro příspěvky a programy.
2. **Běžný provoz:** E4-03 až E4-07. Dokončit přijetí, předpisy, rozvrh, skutečnou docházku a používané náhrady. Přednost má bezpečnost uzavření a opravy před kosmetikou přehledů.
3. **Závodní a trenérská práce:** E4-08, E4-09 a E4-12; E4-11 před nejbližším přechodem období. Vznikne úplný pracovní postup i podklady pro etapu 5.
4. **Historie a přejímka:** E4-13 připravovat průběžně po ustálení datových pravidel; E4-15 uzavírá předání. Ostrá migrace zůstává etapou 6.

## Rozhodnutí pro zahájení implementace

Následující body nejsou důvodem zastavit přípravu; jsou to konkrétní rozhodnutí k uzavření s odpovědnými lidmi před implementací dotčeného postupu:

| Rozhodnutí | Navržený výchozí směr | Kdo potvrdí |
|---|---|---|
| Vznik, přerušení a ukončení členství | Doložené rozhodnutí a účinnost od–do; odděleně od platby | Vedení podle pravidel oddílu |
| Skladba skutečných skupin a soupisek | Stálé identity, časově platné vazby, žádné odhadované hromadné přiřazení | Hlavní trenér |
| Příspěvek při změně během měsíce a více plánech | Výslovná politika; zachovat stálý VS, vyřešit plátce | Vedení + hospodář |
| Zkušební a bezplatná účast | Jen pojmenované schvalované výjimky bez fiktivní platby | Koordinátor |
| Kde platí náhrady a jaké mají lhůty | Nastavit jen pro skutečně používané programy | Koordinátor |
| Uzavření docházky a pozdější oprava | Odpovědný trenér, důvod opravy, kontrola navazujících nároků a výkazů | Hlavní trenér |
| Prohlídky, licence, kvalifikace a přílohy | Minimum údajů, určené účely a přístupy; licence/kvalifikace podle potřeby | Vedení + pověřený správce |
| Historie a rozsah převodu | Zachovat ověřené vazby a původ; nejasnosti řešit jednotlivě | Správce dat + hlavní trenér |

Etapa je dokončena jako zadání. Funkce nejsou tímto dokumentem implementovány ani provozně převzaty.
