# Plán implementace: skupiny, soupisky, tréninky, akce a časové členství

Stav: návrh k implementaci, 20. 10. 2026. Tento soubor je plán, nikoli doklad o provedených změnách nebo o aktuálním stavu produkce. Před implementací ověřit aktuální `origin/main`, schéma a živé chování. Soupisky importované ze starého KIS a jejich historické vazby se nemažou. Import soupisek pro 2026/27 už proběhl; jejich napojení je vstupem pro tento plán, nikoli důvodem znovu importovat osoby. Automatické rozesílky ani bankovní párování se tímto plánem nezapínají.

## Produktová pravidla

- V uživatelském rozhraní znamená **skupina** nejvyšší organizační úroveň, například „Kroužek Třebešín“, „Kroužek Točná“ nebo „Závodní tým“. **Soupiska** je nižší úroveň, například „Aligátoři“, „Kadeti“ nebo „Dráha“. Sportovec smí patřit do více soupisek současně, například podle věku i disciplíny.
- Členství ve skupině se odvozuje ze všech jejích potomků a případného přímého členství. Stejný sportovec se v souhrnu počítá jednou. Odebrání z jedné soupisky jej neodebere ze skupiny, pokud má další platnou cestu členství.
- Skupina, soupiska a produkt kroužku mají stálou identitu. Konkrétní nabídka „1. pololetí“, „2. pololetí“ nebo „obě pololetí“ má datum začátku a konce. Rok nebo školní rok se pro zobrazení a filtrování odvodí z dat; v běžné správě se ručně nevybírá „sezona“.
- Závodní oddíl má členství od zadaného dne bez předem stanoveného konce. Ukončení je ruční a auditované. Měsíční příspěvky závodního týmu se řídí platným členstvím, ne sezonou.
- Historická odpověď na „kdo byl v Aligátorech v listopadu 2026 / březnu 2027“ musí vycházet z intervalů členství platných v daný den. Pozdější změna členství, názvu nebo hierarchie nesmí přepsat minulost. Stav `removed` u dnešního záznamu nesmí skrýt dřívější platné členství.
- Trénink a akce mohou cílit na celou skupinu nebo vybrané soupisky. Cíl skupiny zahrnuje všechny její platné potomky; cíl soupisky jen její větev. Sportovec vidí jednu položku a může vytvořit nejvýše jednu odpověď/přihlášku, i když splní více cílových cest.
- Skutečnou docházku tréninku potvrzuje trenér. Členství určuje očekávané účastníky a viditelnost, nikoli automaticky zaznamenanou účast.
- Jeden stálý variabilní symbol patří plátci. Více dětských měsíčních předpisů jednoho plátce může být uhrazeno společnou platbou; rozdělení a párování musí být prokazatelné a bezpečné.

## Realizační kroky

### 1. Inventura a bezpečný převod současných dat

Zmapovat tabulky a všechny čtecí i zapisovací cesty starých `skupiny`/`podskupiny` a nových `club_teams`/`club_roster_members`. Připravit náhled vazeb starých skupin na KIS soupisky a počet nepřiřazených či nejednoznačných vazeb. Přiřazení podle pouhé shody názvu nesmí být automaticky považováno za jisté. Zvlášť inventarizovat budoucí plány, uskutečněné tréninky, docházku, RSVP, omluvy/náhrady, akce s registracemi, cenové plány, e-shopové objednávky a přímé SQL dotazy na členství.

Před migrací vytvořit obnovitelnou zálohu a kontrolní součty. Staré vazby a již vydané objednávky neměnit bez převodního protokolu. Migraci rozdělit na opakovatelný náhled, vlastní převod a paritní kontrolu.

### 2. Stálá hierarchie a časová historie

V administraci umožnit hromadně vybrat soupisky pro Třebešín, Točnou a závodní tým, případně více organizačních cest pro věkové a disciplínové členění. Před uložením zobrazit členy, překryvy, soupiska bez skupiny, případné konflikty a dopad na tréninky, akce a příspěvky. Zabránit cyklům. Uložit audit toho, kdo a kdy vazbu změnil. Vazby mezi skupinou a soupiskou musí mít časovou platnost nebo rovnocenné verzování, aby šel zrekonstruovat historický stav.

Převést členství z jednoho přepisovaného řádku na nepřepisované intervaly `od`–`do` se zdrojem (nákup, ruční zařazení, import), důvodem a auditem. Opakovaný nákup v dalším roce vytvoří nový interval; neobnoví starý jeho přepsáním. Zamezit nežádoucím překryvům téhož nároku; při více nezávislých zdrojích vyhodnocovat jejich sjednocení. U nákupu výslovně určit účinný začátek a případné zpětné zařazení auditovat. Kroužkové členství skončí podle zakoupeného intervalu nebo dřívějšího storna; nesmí zůstat otevřené jen proto, že řádek soupisky má `valid_to = NULL`.

Dočasně zachovat stará `season_id` a další sezonní vazby kvůli kompatibilitě existujících plánů, objednávek a náhrad. Po převedení všech závislostí se sezona schová z běžné administrace a případné fyzické odstranění přijde až v samostatném, ověřeném kroku. Rok v přehledech a exportech se bude odvozovat z data.

### 3. Trvalý produkt a opakovaná nabídka kroužku

Produkt „Aligátoři“ a cílová soupiska zůstávají stejné i v dalších letech. Jednou se nastaví pravidlo opakování, výchozí termíny, platební možnosti (1. pololetí, 2. pololetí, obě), cena, kapacita a prodejní okno. Systém z pravidla připraví datované nabídky dalšího roku; správce řeší pouze výjimky a změny. Pokud jsou souběžně v prodeji dvě období, musí být v katalogu i košíku jednoznačně uvedeno, který rok a které datum se kupuje. Chybějící nebo rozporná konfigurace nesmí potichu otevřít prodej.

Objednávková položka musí uchovat neměnnou vazbu na přesnou datovanou nabídku, její cenu a souhlas s platnými podmínkami. Při pozdější aktivaci platby se použije původně objednaná nabídka, nikoli „právě aktuální“ nabídka produktu. Současný model dovoluje jednu `club_program_offers` na `variant_id`; tento vztah a všechna místa hledající nabídku přes samotnou variantu je třeba změnit nebo zavést automatické vytváření nových variant. Preferovaný cílový model je stálý produkt a platební možnosti s verzovanými datovanými nabídkami a explicitním ID nabídky v objednávce. Historické objednávky zůstanou navázány na původní nabídku. Kapacita se vyhodnocuje pro příslušné období a fyzickou soupisku, nikoli jako celoživotní limit produktu.

### 4. Jeden výběr cílů při plánování i záznamu tréninku

V `planovany_trenink_form.php` odstranit dvojí volbu staré skupiny/podskupiny a KIS soupisky. V `formular.php` zavést stejný ovladač „Skupina → soupiska“. Bez vybrané soupisky cílit na celou skupinu; více zvolených soupisek znamená sjednocení jejich členů. Výběr více skupin řešit jednoznačným přidáním dalšího cíle. Při editaci i duplikaci musí být původní cíl správně předvyplněn.

Uložení plánovaného i uskutečněného tréninku musí používat kanonickou vazbu na cílové skupiny/soupisky a zajistit kompatibilní čtení historických řádků. Změny cíle budoucího plánu s odeslanými RSVP nebo náhradami vyžadují náhled dopadu a vědomé potvrzení; minulou docházku nepřepočítávat. Zvlášť ověřit přechod „plán → záznam“ a opakované/kopírované tréninky.

### 5. Všechny navazující formuláře a přehledy tréninků

Neomezit změnu na dva vstupní formuláře. Zahrnout `edit_trenink.php`, `ulozit_trenink.php`, `update_trenink.php`, `sprava_vsech_treninku.php`, `sprava_treninku.php`, `duplikovat_trenink.php`, `planovac.php`, `moje_treninky.php`, `ajax_treninky.php`, `prehled_treninku_skupiny_kalendar.php` a veřejné/rodinné zobrazení tam, kde ukazuje skupinu či filtruje tréninky. Zejména `sprava_vsech_treninku.php` nyní nabízí filtry ze starých `skupiny` a `podskupiny` a filtruje přes `trenink_skupina`/`trenink_podskupina`; nové tréninky by se proto po pouhé změně formuláře nemusely správně najít. Filtry, štítky, počty, exporty, odkazy na editaci a duplikaci musí používat stejný kanonický cíl a starší tréninky musí mít čitelný historický fallback.

Před kódováním uzavřít inventář všech obrazovek/API/exportů čtoucích skupinu nebo soupisku. Akceptace: trénink založený pro skupinu či soupisku jde vyhledat ve všech správách, otevřít, upravit, zduplikovat i zobrazit rodiči/sportovci; neztratí se přitom existující účastníci a docházka.

### 6. Jeden výpočet oprávněných sportovců a zobrazení na kartě

Zavést centrální dotaz/funkci pro efektivní členství sportovce ve skupině/soupisce k určitému dni. Musí zohlednit intervaly členství a historickou podobu hierarchie, přímé i zděděné členství a odstranění duplicit. Použijí jej očekávaní účastníci, RSVP, omluvy/náhrady, kalendář, trenérské i rodinné přehledy, komunikace členům a cenové členství podle soupisky. Pro historické docházkové záznamy zůstává autoritou skutečně uložená docházka.

Karta sportovce nabídne rozklikávací nadcházející i minulé tréninky ze všech jeho platných soupisek, vždy jednou, s cílovou skupinou/soupiskou a stavem účasti. Stejná logika se použije v rodinných a sportovních přehledech; rodič vidí jen oprávněné děti.

### 7. Akce pro skupiny a soupisky

Pořadatel vybere skupinu nebo konkrétní soupisky stejným způsobem jako u tréninku. Viditelnost a možnost přihlášení se určí podle členství k datu akce, přičemž kapacita, uzávěrka, souhlas, role a existující přihláška zůstávají samostatnými podmínkami. Více cest členství nesmí zobrazit akci víckrát ani umožnit dvojí registraci. Při změně cílení již otevřené akce nebo akce s registracemi se vyžaduje náhled dopadu a audit; historické přihlášky neměnit automaticky.

### 8. Měsíční příspěvky a stálý VS plátce

Po ověření hierarchie navázat měsíční plán závodního týmu na efektivní členství k datu příslušného měsíce. Vystavit nejvýše jeden předpis na sportovce za plán a měsíc, i při více podřízených soupiskách; zachovat výjimky a idempotentní náhled/generování. Ukončení oddílového členství zastaví další předpisy, existující předpisy se nemažou.

Stálý VS přesunout na plátce, jednoznačný mezi plátci. Pro jednu rodinnou platbu zavést bezpečné přiřazení k více dětským předpisům, evidenci zbytku/přeplatku a ruční kontrolu nejednoznačností. Do doby ověření této alokace nespouštět společný VS a trvalé příkazy v produkčním bankovním párování.

### 9. Kontrolovaná migrace a akceptace

Pro každý krok mít migrační náhled s počty původních a cílových vazeb, kolizemi a paritou historických intervalů. Dosud importované osoby a soupisky nepřepisovat hromadně bez identitního důkazu. Historické tréninky, již odeslaná RSVP, omluvy, náhrady, registrace a objednávky si uchovají původní význam. Nejasné případy ponechat v administraci k rozhodnutí.

Ověřit nejméně: sportovce ve dvou soupiskách téže skupiny; odebrání jen z jedné; trénink pro celou skupinu a jen jednu soupisku; vyhledání, editaci a duplikaci obou tréninků ve všech správách; změnu členství mezi listopadem a březnem; změnu hierarchie v čase; jeden výskyt a jednu přihlášku na akci; nákup prvního/druhého/obou pololetí; další rok se stejným produktem; pozdní platbu staré objednávky; storno; jeden měsíční předpis při dvou subsoupiskách; jednu rodinnou platbu pro více dětí. Testovat lokálně na syntetických datech a poté samostatně posoudit produkční náhled, migraci a nasazení.

## Rozhodnutí před implementací

- Přesné hranice obou pololetí a prodejních oken budou konfigurovatelné šablonou kroužku; nebudou natvrdo svázané s jediným kalendářním datem.
- U sportovce přijatého po začátku pololetí je třeba v administraci rozlišit datum nároku od data schválení/platby. Zpětná platnost smí vzniknout jen vědomě a s auditem.
- U již zaplaceného prvního pololetí je třeba určit pravidlo dokoupení druhého oproti volbě „obě pololetí“, aby nevznikl dvojí nárok ani dvojí kapacita. Do rozhodnutí má být kolize bezpečně blokována.
- U průběžného závodního oddílu zůstávají měsíční předpisy otevřené do ručního ukončení členství; datum začátku zpoplatnění a výjimky se nastaví výslovně v platebním plánu.

## Současné technické důvody pro tento rozsah

- `club_teams.season_id` a `club_program_offers.season_id` jsou nyní povinné; nabídka musí spadat do dat sezony soupisky (`migrations/20260804090000_kis_teams_rosters.php`, `migrations/20260804140000_club_programs.php`, `includes/club_program.php`).
- `club_roster_members` má unikátní dvojici `(team_id, sportovec_id)` a obnova přepisuje její datum začátku; nákup zakládá členství v soupisce s otevřeným koncem (`includes/kis_roster.php`, `includes/club_program.php`). Historii intervalů je nutné modelovat samostatně.
- Vazba `club_program_offers.variant_id` je jedinečná a aktivace objednávky hledá nabídku přes variantu (`migrations/20260804140000_club_programs.php`, `includes/club_program.php`). Trvalý produkt s opakovanou nabídkou proto vyžaduje explicitní identitu období v objednávce nebo bezpečně generované nové varianty.
- `sprava_vsech_treninku.php`, `sprava_treninku.php`, `edit_trenink.php`, `duplikovat_trenink.php` a další obrazovky stále používají staré tabulky skupin a podskupin. Rozsah migrace zahrnuje celé navazující tréninkové workflow.
