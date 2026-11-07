# Etapa 4 — pravidla, přejímka a zdrojové podklady

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 4](00_ETAPA_4_PLAN.md), 22. 10. 2026. Pravidla jsou cílové zadání. Scénáře jsou návrh budoucího ověření, **nebyly při této přípravě vykonány**.

## 1. Co musí systém rozlišovat

| Pojem | Co vyjadřuje | Co z něj nelze automaticky odvodit |
|---|---|---|
| Osoba | Konkrétní člověk s jednou ověřenou identitou | Účet, souhlas, členství ani nárok na službu |
| Uživatelský účet | Možnost přihlášení konkrétního uživatele | Vlastnictví všech dětí stejného příjmení ani členství |
| Rodinné oprávnění | Schválený vztah účtu k osobě a jeho platnost | Smluveného plátce všech jejích závazků |
| Členství v oddílu | Rozhodnutí o členství a jeho účinnost podle pravidel oddílu | Zaplacení, dnešní soupiska, závodní licence |
| Členství v soupisce | Sportovní zařazení platné v určitém období | Přijetí do oddílu ani přítomnost na každé lekci |
| Program a nabídka | Stálá služba a její konkrétní termín, cena a kapacita | Zaplacenou účast každého zájemce |
| Přihláška a účast v programu | Žádost a následný stav oprávnění čerpat službu | Založení nového člověka při každé objednávce |
| Předpis | Požadovaná částka, účel, plátce, osoba a období | Přijaté peníze ani uskutečněný trénink |
| Potvrzená úhrada | Skutečný finanční stav podle etapy 2 | Přítomnost, splnění zdravotních podmínek či rozhodnutí o členství |
| Plán a očekávaná soupiska | Kdo má mít lekci v daném termínu | Skutečnou docházku |
| RSVP / omluva | Záměr nebo oznámení nepřítomnosti s časem přijetí | Skutečný výsledek lekce ani automatický náhradní nárok |
| Uzavřená docházka | Ověřená skutečnost konkrétního tréninku | Schválení odměny a její vyplacení |
| Nárok na náhradní lekci | Omezené nepeněžní právo podle pravidel programu | Přeplatek nebo finanční kredit |
| Zdravotní podklad | Doložený a ověřený dokument s platností a účelem | Automatické lékařské posouzení systémem |
| Nominace / závodní přihláška | Plánovanou či potvrzenou účast | Skutečný start, dokončení nebo výsledek |
| Výkaz činnosti | Schválený rozsah skutečné aktivity a jeho verzi | Peněžní úhradu |

Tyto významy mají být stejné na kartě osoby, v administraci, rodinném portálu i exportu. Není nutné vytvářet samostatnou tabulku pro každý řádek; před implementací se má využít existující model a doplnit pouze chybějící význam či audit.

## 2. Závazná pravidla návazností

### Členství a časové zařazení

- Skupina, soupiska a program mohou mít stálou identitu. Závodní členství s otevřeným koncem se na Silvestra samo neukončuje ani znovu nezakládá.
- Každá změna má datum účinnosti a datum skutečného zápisu. Zpětný přepis papíru tyto okamžiky nesmí sloučit.
- Přestup zachová historii. Souhrn aktivity používá skutečné účastníky; dobový přehled soupisek používá vazby platné k tehdejšímu datu.
- Platnost od–do musí mít ve všech službách stejnou interpretaci hranic. Přejímka ověří poslední den, první den následujícího zařazení i souběh; nebudeme automaticky měnit všechny stávající databázové intervaly na jinou konvenci.
- Neznámé narození nebo hodnota `1900-01-01` neslouží k věkovému přiřazení. Změna věkové skupiny vyžaduje ověřený údaj a náhled dopadu.
- Členství, účast a oprávnění mají vlastní důvod ukončení. U odchodu člena zůstává finanční a sportovní historie dostupná oprávněným pracovníkům podle pravidel uchování.

Příklad: sportovec zůstává členem oddílu, od 1. ledna přechází do jiné věkové soupisky a současně zůstává v dráhové soupisce. Prosincová docházka zůstane původní. Rodinný VS zůstane. Půlroční kroužek jiného dítěte končí podle své nabídky a pokračování vyžaduje novou účast pro nové období.

### Osobní vyřízení a finance

- Obsluha vyhledá existující osobu dříve, než založí novou. Telefonická nebo papírová žádost nevytváří potvrzený online účet či souhlas s neznámou verzí textu.
- Plátce, účastník a oprávněný rodič musí být rozlišitelní. Je-li plátce nejasný, případ čeká na dořešení; systém jej nesmí vybrat pouze podle pořadí rodičů bez schváleného pravidla.
- Hotovost patří do pokladního procesu etapy 2. Přijetí přihlášky nebo zapsání docházky není potvrzení hotovostní úhrady.
- Bezplatná účast je schválený typ nároku nebo výjimka, nikoli fiktivní platba. Při změně ceny se zachovává původ předepsané částky a opravný postup.
- Přestup mezi programy má sportovní i finanční část. Uvolnění staré kapacity, obsazení nové, vypořádání náhrad a rozdíl částek musí být dohledatelné; chyba nesmí ponechat dvě nechtěné aktivní účasti.
- Dluh se zobrazuje a řeší odděleně. Případné omezení služby má schválené podmínky, výjimku a audit; nezpůsobuje skryté přepsání členství.

### Trénink, omluva a náhrada

1. Plán obsahuje termín, místo, trenéra a očekávané osoby podle datovaných soupisek.
2. RSVP eviduje reakci rodiny/obsluhy, skutečný čas přijetí a použitá pravidla. Samotná absence reakce se nepovažuje za omluvu ani za přítomnost.
3. Trenér po skončení potvrdí skutečné osoby a průběh. Rozepsaná nebo nezapsaná lekce je rozeznatelná od uzavřené lekce s nulovou účastí.
4. Nárok na náhradu vzniká z ověřené skutečnosti a platné účasti v programu podle pravidel, nikoli pouze z kliknutí na omluvu.
5. Rezervace náhrady spotřebovává dostupnou kapacitu termínu a zablokuje konkrétní nárok. Souběžné požadavky nesmějí uspět nad kapacitu ani použít stejný nárok dvakrát.
6. Uzavření náhradního termínu rozliší využití a nepříchod. Zrušení pořadatelem nebo oprava docházky řeší navazující nárok auditovaně.
7. Pokud byl nárok již použit nebo činnost zahrnuta do schváleného výkazu, zdrojový trénink se nesmí odstranit bez vyřešení dopadů. Oprava vytvoří dohledatelnou změnu a případ k posouzení.

Existující náhradový model se zachovává. Konkrétní délka lhůty a převody mezi obdobími jsou nastavovaná pravidla jednotlivých programů, nikoli plošně vnucených 12 hodin či neomezený převod.

### Minimální kontrakt zdravotního podkladu

| Údaj / chování | Zadání |
|---|---|
| Osoba a účel | Vazba na jednoznačnou osobu a účel, pro který se podklad ověřuje |
| Vystavení a platnost | Hodnoty z doloženého podkladu; neznámá hodnota zůstane neznámá |
| Ověření | Kdo a kdy ověřil; podklad nebo reference na originál; důvod odmítnutí/dořešení |
| Stav | Nedoloženo, čeká na ověření, platné, platnost skončila, nahrazeno/odvoláno |
| Časové vyhodnocení | Stav k datu konkrétní aktivity; budoucí závod může být za koncem dnešní platnosti |
| Přístup | Pověřený správce ověřuje; trenér dostane potřebný provozní stav; příloha má vlastní kontrolu přístupu |
| Upozornění | Nastavitelný předstih, jeden dohledatelný případ, náhradní osobní kontakt při nedoručení |
| Uchování a výměna | Nový podklad nepřepisuje neviditelně původní ověření; schválená pravidla uchování |

Neukládat preventivně diagnózy do volné poznámky. Účel, právní důvod a potřebný rozsah se potvrdí podle používaných aktivit; zdravotní údaje mají zvláštní režim. [ÚOOÚ](https://uoou.gov.cz/verejnost/qa-otazky-a-odpovedi/slovnicek-pojmu). Tato specifikace neurčuje lékařské podmínky účasti ani univerzální interval prohlídek.

## 3. Převod sportovních dat — požadovaný kontrakt

Následující přehled je zadání migračního návrhu, nikoli povolení k importu. Přesný fyzický model obou instalací se musí ověřit konzistentním exportem v etapě 6; mapování nelze odvodit jen z názvů tabulek.

| Entita | Co je nutné propojit | Kontrola a konfliktní pravidlo |
|---|---|---|
| Osoba | Identifikátor zdroje → schválené cílové ID | Kandidát podle jména/narození není důkaz totožnosti; zachovat cílová ruční rozhodnutí |
| Trenér | Starý autor/účastník vedení → cílová osoba a historická role | Neexistující aktivní účet nesmí vymazat autorství; historie nezakládá dnešní pracovní oprávnění |
| Skupina / soupiska | Starý význam a datované vazby → schválené nové struktury | Dnešní soupiska nedokazuje historické zařazení; nejistotu uvést |
| Trénink a plán | Zdrojové ID, datum, čas/délka, kategorie, trenéři a vazba na plán | Oddělit plánované/budoucí záznamy od skutečnosti; plán + realizace nepočítat dvakrát |
| Skutečná účast | Trénink + osoba + původ vazby | Počítat vazby i unikátní osoby; izolovat osiřelé a duplicitní vazby |
| Závod a účast | Událost, datum, disciplína, osoba a status výsledku | Přihláška není start; zachovat DNS/DNF/DSQ a neurčený stav |
| Strukturované měření | Zdrojové měření, osoba, aktivita, veličina, jednotka, hodnota | Uchovat surový zápis i normalizaci; kontrolovat m/km a časové formáty |
| Staré textové měření | Původní text a vztah ke konkrétní osobě/aktivitě | Nejasnou jednotku neodhadovat; označit nezařazené k posouzení |
| Zátěžový test a soubor | Osoba, datum testu, metadata, soukromá příloha | Ověřit existenci, velikost/otisk, otevření i přístupová práva; neodvodit prohlídku |
| Odměnové období | Osoba, období, pravidlo a podklad činnosti | Nezaměnit výpočet, historický příznak vyplacení a skutečný peněžní tok; dořešit s etapami 2 a 5 |

Každá dávka potřebuje identitu zdroje a dávky, schválenou mapu, verzi pravidel, počet přijatých/odmítnutých/nevyřešených záznamů, počty vazeb a seznam chybějících souborů. Opakování stejné dávky nesmí vytvářet duplicity. Oprava mapy musí mít vyčíslitelný dopad a bezpečnou cestu nápravy. Před ostrým převodem bude záloha a ověřený návrat podle E1-09 a etapy 6.

## 4. Přejímací scénáře

Všechny zápisové scénáře provádět teprve při autorizované realizaci v izolovaném prostředí se syntetickými osobami, kontakty a podklady. Žádné ostré e-maily, platby nebo automatické importy. Protokol bude obsahovat ID, testovanou revizi, prostředí, datum, zkoušejícího, skutečný výsledek, důkaz a závadu. Uvedené očekávání je cílové chování, nikoli tvrzení o současném průchodu.

| ID | Úkol | Scénář | Očekávaný výsledek |
|---|---|---|---|
| P01 | E4-01 | Papírová a online přihláška stejné osoby; jmenovec | Stejná osoba se řeší jako kandidát duplicity, jmenovec se nesloučí automaticky; rozhodnutí je dohledatelné. |
| P02 | E4-01 | Nákup veřejností, člen s dluhem a člověk s aktivním účtem bez členství | Nákup ani účet nevytvoří členství; dluh jej sám neukončí. Stejný význam ve všech přehledech. |
| P03 | E4-01 | Přerušení, ukončení a pozdější návrat člena s historií a dluhem | Platné intervaly a důvody; historie a závazek zůstávají, návrat nevytvoří druhou osobu. |
| P04 | E4-01/14 | Ruční členské rozhodnutí oproti novému importu starých příznaků | Import je nepřepíše bez schváleného pravidla; je dohledatelný původ a rozpor k řešení. |
| P05 | E4-02 | Dvě soupisky jedné skupiny se stejným člověkem; odchod jen z jedné | Ve společné lekci je jednou a zbývající zařazení trvá; datum hranice má stejný význam ve všech pohledech. |
| P06 | E4-02 | Přestup k budoucímu datu a změna hierarchie s existujícími registracemi | Náhled dopadů, řízené řešení závazků, zachovaná dobová soupiska a již potvrzená historie. |
| P07 | E4-03 | Osobně přijaté dítě bez rodinného účtu | Obsluha vyřídí osobu, podklad a nabídku bez fiktivního ověření e-mailu nebo online souhlasu. |
| P08 | E4-03 | Úhrada dorazí před dořešením identity; dva zájemci o poslední místo | Peníze se neztratí; případ čeká na oprávněné dořešení, nevznikne duplicitní osoba ani přeplněná kapacita. |
| P09 | E4-03 | Zkušební nebo schválená bezplatná účast | Konkrétní rozsah a schvalovatel, žádná fiktivní úhrada; běžný uživatel výjimku nevytvoří. |
| P10 | E4-03 | Přestup programu, storno a částečná finanční oprava | Jasný starý/nový nárok a kapacita; peněžní vypořádání má vlastní stav a vazbu na etapu 2. |
| P11 | E4-04 | Vstup/odchod uprostřed měsíce, přerušení a výjimka | Náhled vysvětlí částku podle schválené politiky; poměrná část se neodhaduje. |
| P12 | E4-04 | Dva rodiče, vlastní účet sportovce a změna plátce | Jednoznačně sjednaný plátce, historie rozhodnutí a zachování správného VS; nevolí se náhodně první účet. |
| P13 | E4-04 | Opakování generování a souběh dvou příspěvkových plánů | Žádná nechtěná duplicita; souběh se řídí výslovným pravidlem, každá částka má vysvětlení. |
| P14 | E4-05 | Kolize trenéra/místa a záměrně společná lekce dvou soupisek | Kolize je řešitelná před potvrzením, společná lekce neduplikuje osoby či rezervaci; záskok má oprávnění. |
| P15 | E4-05 | Přesun nebo zrušení termínu s RSVP a rezervovanou náhradou | Původ a návaznost zachovány, kapacita i nároky konzistentní; zpráva nebo osobní úkol má výsledek. |
| P16 | E4-06 | Plán bez zápisu, rozepsaná docházka a uzavřená lekce s nulovou účastí | Tři rozeznatelné stavy; nezapsání nevytváří absenci všech ani podklad pro odměnu. |
| P17 | E4-06 | Přepsaná papírová docházka, host a souběžný zápis dvou trenérů | Jedna skutečná lekce a unikátní účast; datum konání i zápisu, autor a důvod nečekané osoby. |
| P18 | E4-06 | Uzavření před začátkem, v průběhu a po skončení lekce | Před skončením jen návrh, žádný definitivní náhradní nárok či uzavřený výkaz. Po skončení lze řádně potvrdit. |
| P19 | E4-07 | Včasná/pozdní omluva, žádné RSVP a telefonická omluva přepsaná později | Rozhoduje skutečný doložený čas a pravidlo programu; pozdní přepis nesmí lhůtu tiše obejít. |
| P20 | E4-07 | Roční a pololetní účast pokrývají stejnou lekci; neznámé narození | Nejvýše jeden nárok pro stejnou osobu a zdrojovou lekci; věková podmínka neprojde odhadem. |
| P21 | E4-07 | Dvojí rezervace stejného nároku a dva lidé na poslední náhradní místo | Nárok ani místo se nevyužijí dvakrát; neúspěšný pokus má srozumitelné vysvětlení. |
| P22 | E4-06/07 | Oprava absence na přítomnost a smazání zdrojové lekce po rezervaci/využití náhrady | Každá vstupní cesta kontroluje závislosti; využitý nárok vyžaduje dořešení, nezmizí jeho původ. |
| P23 | E4-07 | Zrušení náhrady pořadatelem, pozdní storno rodinou, nepříchod a zrušení programu | Každý důvod má správný výsledek nároku a kapacity; použité nároky se neztrácejí bez posouzení. |
| P24 | E4-08 | Nominace, přihlášení, vytvoření závodu a DNS/DNF/DSQ | Nominace není start, založení závodu není dokončení všech účastí; výsledky uchovají význam. |
| P25 | E4-08 | Osobní registrace na akci, odhlášení a předání soupisky vedoucímu | Ověřené osoby, platné potřebné podklady, finanční storno zvlášť a jen nezbytné kontakty v exportu. |
| P26 | E4-09 | Jeden plán + skutečný trénink a historický trénink bez nové soupisky | Počet skutečných lekcí je správný, historie bez nové soupisky nezmizí a plán se nepočítá podruhé. |
| P27 | E4-09 | Měření v m/km, různé formáty času, neznámé jednotky a chybějící hodnota | Ověřená normalizace, zachovaný původ; neznámé není nula a text se nepřevede odhadem. |
| P28 | E4-10 | Prohlídka platná dnes, ale ne k datu závodu; hraniční den a nový podklad | Vyhodnocení k aktivitě, správná hranice platnosti, dohledatelné původní i nové ověření. |
| P29 | E4-10/14 | Neověřený sken, rodina cizího dítěte a trenér bez práva na celý dokument | Sken sám není ověření; přímý odkaz je chráněný; trenér získá jen povolený provozní stav. |
| P30 | E4-10/14 | Blíží se konec platnosti, nefunguje doručování, existuje pouze papírový originál | Dohledatelný úkol odpovědné osobě, možnost osobního vyřízení, odkaz na originál a ověření bez povinného veřejného skenu. |
| P31 | E4-11 | Přelom roku u otevřeného závodního členství a přechod věkové soupisky | Členství pokračuje; pouze oprávněná datovaná změna soupisky, beze ztráty historie a bez nového VS. |
| P32 | E4-11 | Opakované vytvoření příští nabídky a převod náhrad do navazujícího období | Jedna konkrétní nabídka, schválená cena/kapacita; žádná automaticky zaplacená účast ani neomezené převody. |
| P33 | E4-06/12 | Oprava tréninku po uzavření výkazu či schválení odměny | Verze a vyčíslený rozdíl k posouzení, původní podklad zachován; žádná tichá změna „vyplaceno“. |
| P34 | E4-12 | Stejné období v kartě sportovce, souhrnu trenéra a exportu pro vedení | Čísla odpovídají vysvětleným definicím, lze dohledat jednotlivé aktivity; budoucí plány nejsou skutečná práce. |
| P35 | E4-13 | Kandidátní shoda, jmenovci, zástupné narození a chybějící trenér | Řízená mapa a nevyřešené případy; žádná domyšlená identita, věk nebo dnešní pracovní oprávnění. |
| P36 | E4-09/13 | Zkušební dávka obsahuje tréninky, závody, staré texty, testy a osiřelé vazby | Souhlasí počty a kontrolní souhrny podle typů; neúplnosti jsou v protokolu, nikoli tiše vynechané. |
| P37 | E4-13 | Opakování stejné dávky a pozdější oprava převodní mapy | Bez duplicit; doložitelný dopad a opravná cesta, cílové ruční údaje zůstávají chráněné. |
| P38 | E4-13 | Chybějící příloha, přerušený převod a návrat ze zálohy | Chyba označena, žádné tvrzení o úplném převodu; ověřitelná obnova vazeb i souborů. |
| P39 | E4-10/14 | Odebrání rodinné/pracovní role při otevřené stránce; přímé URL a export; interní fotografie | Další požadavek respektuje změnu oprávnění; interní příloha není veřejný soubor, zveřejnění je samostatné rozhodnutí. |
| P40 | E4-15 | Celý pracovní týden obsluhy, rodina se dvěma dětmi a výpadek s papírovou náhradou | Obsluha zvládne přijetí, rozvrh, účast, omluvu i opravu; papír je doplněn jednou a všechny nevyřešené případy mají vlastníka. |

Scénáře P35–P38 jsou přejímací kontrakt pro budoucí migrační realizaci v etapě 6. Při předání zadání etapy 4 se kontroluje jejich úplnost a dostupnost podkladů; jejich úspěšný zápisový průchod se nyní nepředstírá.

## 5. Výstupní brány

1. **Zadání připravené k realizaci:** známý vlastník, rozhodnutá pravidla dotčené agendy, návaznosti a přejímka. Tento dokument nepovoluje implementaci ani produkční aktivaci.
2. **Funkce převzatá v testu:** povinné scénáře pro daný rozsah prošly na konkrétní revizi; existující funkce se mají přezkoušet, ne automaticky přepsat.
3. **Pilot připraven:** vyřešené identity a role skutečných účastníků, ověřené podklady, obsluha a náhradní postup, funkční potřebné notifikace nebo výslovně převzaté osobní vyřízení.
4. **Agenda převzatá v provozu:** samostatně odsouhlasený pilot prokázal celý postup a opravy. Nestačí nasazený kód, zelená úvodní stránka nebo samotný dílčí test.
5. **Původní Evidence může být odstavena:** schválený a ověřený převod historie, souborů a používaných funkcí, vyřešené odměny/finance s etapami 2 a 5, hotová etapa 6. Stav „připravený plán etapy 4“ tuto bránu nesplňuje.

## 6. Zdrojové podklady

Odkazy na kód jsou připnuté k revizi `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. U dokumentace o nasazení používáme popis modelu a implementace; historická zpráva o nasazení není čerstvým provozním ověřením. Značky Z1–Z14 odkazují z hlavního plánu na tuto tabulku.

| Značka | Podklad | Zjištění a omezení |
|---|---|---|
| Z1 | [Výpočet členského stavu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/sportovec_status_lib.php#L4), [karta sportovce](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/sportovec_karta.php#L89), [správa osob](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/sprava_sportovcu.php#L555) | Starý výpočet se stále používá; obsahuje importované finanční příznaky, staré skupiny a ruční stav. To je konkrétní místo pro sjednocení, nikoli důkaz, že žádné nové členské funkce neexistují. |
| Z2 | [Správa registrací](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/athlete_registration_admin.php#L75), [editor osoby](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/sportovec_editor.php), [rodinná oprávnění](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/account_person_role.php), [rodinný portál](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/family_portal.php) | Shody, schválení stávající/vytvoření nové osoby, přiřazení a předpis již mají implementaci. Rodinné vazby mají vlastní životní cyklus. |
| Z3 | [Časová hierarchie](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/roster_hierarchy.php#L48), [předání hierarchie](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/ROSTER_HIERARCHY_IMPLEMENTATION_HANDOFF_2026-10-20.md), [stálé skupiny a datované nabídky](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/ROSTER_GROUPS_TRAINING_SEASON_IMPLEMENTATION_PLAN_2026-10-20.md) | Efektivní členství k datu, deduplikace, náhled dopadu a přijatý model trvalých identit. Historické počty nepřiřazených soupisek nejsou vydávány za dnešní stav. |
| Z4 | [Programy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_program.php), [opakování nabídek](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/CLUB_PROGRAM_RECURRENCE_OPERATIONS.md) | Kapacita, placená aktivace, přesná nabídka a generování dalších období již existují. Existence plánovače není důkaz aktivní automatizace. |
| Z5 | [Plány příspěvků a plátce](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/member_fee_plan.php#L72) | První vyhovující účet, časový výběr členů, náhled a generování; potřeba rozhodnout měsíční politiku a explicitního plátce. |
| Z6 | [Most mezi soupiskou a tréninkem](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/training_roster_bridge.php#L139), [popis modelu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/training-roster-bridge.md), [sportoviště](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/venue_calendar.php) | Očekávané versus skutečné osoby, kopie a porovnání, rezervace míst. Samotný helper neprokazuje pokrytí všech kolizí. |
| Z7 | [RSVP](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/training_rsvp.php#L138), [vznik náhrady](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_makeup.php#L244), [opravy a storna](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_makeup.php#L526), [pravidla](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md) | Rozsáhlý existující životní cyklus. Kontrola začátku ve funkci vzniku nároku sama neprokazuje dokončení celé lekce. |
| Z8 | [Zápis tréninku](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/ulozit_trenink.php), [úprava](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/update_trenink.php#L324), [odstranění](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/smazat_trenink.php#L47) | Úprava volá náhradovou rekonciliaci, samostatná mazací obsluha maže vazby a odpojuje plán. Je nutná společná kontrola závislostí; chování případných databázových omezení se nyní nezkoušelo. |
| Z9 | [Klubový kalendář a propojení závodu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_calendar.php#L471), [zápis výsledku závodu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/ulozit_zavod.php) | Propojení na závod už existuje. Stav dokončeného plánování není sám potvrzením startu či výsledků všech osob. |
| Z10 | [Sportovní datové zdroje](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/sports-data-quality.md), [normalizace měření](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/sports_measurement_input.php), [posouzení importu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/sports_import_review.php), [časová osa](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/athlete_training_timeline.php) | Pět hlavních sportovních zdrojů, jednotky a výsledkové stavy, čtení historie; nástroj pro posouzení není dokončená migrace. |
| Z11 | [Soukromé úložiště](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/private_storage.php), [citlivé údaje](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/person_sensitive.php), [vlastní souhlasy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/CUSTOM_CLUB_CONSENTS_2026-10-21.md) | Základ ochrany a souhlasů existuje; neprokazuje kompletní evidenci platnosti prohlídek ani ochranu každého staršího souborového umístění. |
| Z12 | [Převod soupisek](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/kis-roster-rollover-execution.md), [implementace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_roster.php#L154) | Náhled, kontrola věku, výjimky, ochrana před opakováním. Použít v souladu s novějším modelem stálého členství, nikoli jako povinné každoroční kopírování. |
| Z13 | [Starší odměnový přehled](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/prehled_kreditu.php), [hromadné odměny](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/hromadne_odmeny.php), [výkaz trenéra](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/vypis_vykazu.php), [souhrn výkazů](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/prehled_vsech_vykazu.php) | Existující činnost a odměny; živý počet tréninků a staré skupiny vyžadují sjednocení. Historický příznak vyplacení není nový účetní důkaz. |
| Z14 | [Porovnání etapy 0](../etapa-0-2026-10-22/03_EVIDENCE_VS_KIS.md), [papírové agendy](../etapa-0-2026-10-22/02_PAPIROVE_AGENDY.md), [hranice úplného importu osob](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/kis-full-person-import.md) | Datované rozdíly historie, kandidáti párování a agendy potvrzené uživatelem. Rozdíl v počtu osob není počet chybějících členů. |

Existující testy pro následnou realizaci: `RosterHierarchyTemporalTest`, `TrainingRosterBridgeTest`, `TrainingRsvpTest`, `ClubMakeupTest`, `ClubProgramPaidIdentityCapacityTest`, `ClubProgramSharedCapacityTest`, `ClubProgramPaymentLifecycleTest`, `ClubProgramRecurrenceTest`, `AthleteTrainingTimelineTest`, `KisRosterRolloverExecutionTest` a další v [integračních testech](https://github.com/KovoPraha/evidenceTreninku/tree/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/tests/Integration). Jejich existence není výsledkem nového běhu; v tomto dokumentačním úkolu nebyly spouštěny.

## 7. Omezení tohoto ověření

- Prověřen zdrojový stav uvedené revize a návaznost na etapy 0–3. Nebyl nově prokázán aktuální produkční stav konfigurace, naplnění tabulek ani doručení notifikací.
- Nenalezení samostatné evidence zdravotních prohlídek je závěr z prohledaných relevantních zdrojů, nikoli tvrzení o všech externích evidencích oddílu.
- Celkové počty historie z etapy 0 pocházejí z administrátorských přehledů průběžně používaných systémů. Nejsou atomickým databázovým snímkem a před migrací se musí doplnit skryté/osiřelé vazby a přílohy.
- Při tomto úkolu vznikla pouze místní dokumentace. Nebyla provedena nová registrace, změna členství či soupisek, zápis docházky, finanční operace, odeslání zprávy, import ani nasazení.
