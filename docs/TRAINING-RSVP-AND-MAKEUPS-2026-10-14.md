# Účast na tréninku a návrh náhradních lekcí

Stav k 14. 10. 2026: obě části jsou implementované v kódu. Provozní stav
konkrétní instalace ověřujte podle nasazeného commitu a migrací, nikoli podle
tohoto dokumentu. Před zapnutím náhrad pro konkrétní kroužek je potřeba
otestovat role a proces na oddělených účtech v prohlížeči. Nová politika je
ve výchozím stavu vypnutá a zapíná se výslovně v administraci.

## 1. Běžný trénink a zpráva trenérovi

1. Trenér vytvoří termín v `planovany_trenink_form.php` a vybere jednu nebo
   více KIS soupisek. Uložení vytvoří `training_roster_links` a snapshot
   očekávaných sportovců. Bez vybrané soupisky zůstává podporovaný interní
   trénink, ale nelze k němu sbírat odpovědi od sportovců.
2. Rodič ve `booking/sportovni_prehled.php` nebo sportovec v
   `booking/muj_sport.php` vidí kalendář svých budoucích tréninků po měsících.
   Termín se ukáže jen při aktivním členství v připojené soupisce, platném v
   den tréninku. Po kliknutí na termín lze potvrdit účast, omluvit se a přidat
   nepovinnou zprávu trenérovi (maximálně 500 znaků). Odpověď jde změnit do
   začátku tréninku; nejde o zápis skutečné docházky.
3. Při uložení se znovu ověří oprávnění rodiče/sportovce, členství v soupisce,
   stav a čas tréninku. Změna odpovědi nebo zprávy vytvoří auditní událost.
   Obsah zprávy se nekopíruje do auditní události.
   Jakmile k termínu existuje odpověď nebo nastavení náhrad, změna data, času
   začátku či soupisky se odmítne; termín je potřeba zrušit a vypsat nový.
4. Přiřazený trenér nebo hlavní trenér otevře v `planovac.php` odkaz
   **Příprava**. V `training_rsvps_admin.php` vidí očekávané sportovce,
   odpovědi, zprávy i součty. Skutečnou docházku zapíše dál odděleně přes
   `formular.php?plan_id=…`.

Tato část používá existující `training_rsvps`; nová migrace
`20261014100000_training_rsvp_notes` přidává `member_note`. Je potřeba ji
aplikovat před otevřením upravených stránek. Kalendář a trenérská příprava
zatím nebyly ověřeny prohlížečem na produkci.

## 2. Náhradní lekce kroužků — implementované pravidlo

### Základní pravidlo

Nárok vzniká pouze z **předem omluvené, zaplacené kroužkové lekce**. Samotné
kliknutí na „Nezúčastním se“ ještě kredit nevytvoří: systém musí ověřit
uzávěrku, platnou programovou účast a po proběhnutí lekce skutečnou docházku.
Závodní a hobby oddíl, jednorázové akce ani obyčejné produkty e-shopu nárok
automaticky nevytvářejí. Jako jednotku je vhodné použít **jednu lekci**, ne
hodinu o 60 minutách; různé kroužky mají různou délku tréninku.

### Pravidla vydání a využití

- V administraci kroužků administrátor nastaví pro konkrétní program a školní
  sezonu **minimální předstih omluvy v hodinách**. Nastavení je společné pro
  celoroční a obě pololetní nabídky stejného programu, aby dítě na jedné
  soupisce nemělo různé lhůty podle zakoupené varianty. U termínu se uloží
  rozhodná hodnota nastavení. Změnu lze výslovně promítnout do vybraných
  budoucích termínů, ale nesmí zpětně změnit nárok za již uplynulý trénink.
  Omluva po uzávěrce se trenérovi stále zobrazí, ale
  nevytvoří náhradní lekci. Výchozí hodnota před prvním nastavením zůstává
  výchozím nastavením 12 hodin. Správce jej může pro daný kroužek změnit.
- Nárok je právě jeden za sportovce a zdrojový termín, i když je dítě v téže
  soupisce zapsané celoročně i pololetně. Programová účast musí pokrývat den
  zdrojového termínu a musí být aktivní. Při stornu či vratce se nárok znovu
  prověří; použitý nárok se řeší jako výjimka pro správce, ne tichým výmazem.
- Kredit se definitivně připíše až po skončení zdrojového tréninku a uzavření
  skutečné docházky. Pokud dítě navzdory omluvě přišlo, kredit nevznikne.
  Nespárovaná nebo neuzavřená docházka znamená stav „čeká na potvrzení“.
- Trenér může u **konkrétního termínu** zapnout náhradníky a stanovit počet
  míst. U opakovaného plánu může hromadně označit budoucí termíny v intervalu,
  ale každý termín má vlastní přepínač a vlastní kapacitu. Termín ve stavu
  zrušený či již proběhlý se nenabízí.
- Nabídka pro rodiče se filtruje podle ročníku narození dítěte,
  strukturovaného rozmezí ročníků cílové nabídky, sezony a volných míst. Volný text
  `club_teams.age_label` k rozhodování nestačí. Bez strukturovaného věkového
  pravidla se termín nenabídne, dokud jej správce nedoplní. Automatickou
  věkovou výjimku systém nepovoluje.
- Rezervace cílové lekce spotřebuje jeden dostupný kredit v jedné transakci.
  Dvě současné rezervace nesmějí utratit stejný kredit ani obsadit poslední
  místo dvakrát. Rezervace se trenérovi ukáže odděleně od kmenové soupisky.
  Samotná rezervace nevytváří členství v jiné soupisce ani nákup e-shopu.
- Zrušení rezervace před uzávěrkou cílového kroužku vrátí kredit. Pokud je
  cílový trénink zrušen pořadatelem v plánovači, kredit se vrátí vždy. Pozdní
  samostatné storno systém odmítne; nedostavení na proběhlý náhradní trénink
  nárok spotřebuje. Výjimka vyžaduje individuální zásah správce.
- Nevyčerpaný kredit z prvního pololetí přejde **jednou** do navazujícího
  druhého pololetí téhož programu/skupiny. Vazba musí být explicitní ID
  navazující nabídky, ne odhad podle názvu „Zubříci“. Celoroční nabídka pokrývá
  obě pololetí; stejný sportovec nemůže získat dva kredity z překryvných
  nabídek. **Převedený kredit lze čerpat pouze tehdy, když má dítě potvrzeně
  zakoupené navazující druhé pololetí nebo potvrzeně zakoupenou celoroční
  variantu, která druhé pololetí pokrývá.** Samotná objednávka čekající na
  platbu nestačí. Bez navazující účasti zůstane převedený kredit viditelný,
  ale uzamčený; po aktivaci nákupu se zpřístupní. Při stornu navazující
  účasti se další čerpání zablokuje. Po konci navazujícího pololetí kredit
  propadne; další převod není.

### Datový a uživatelský tok

1. Administrace `club_makeup_policies_admin.php` obsahuje číselné pole „Omluva nejpozději X hodin
   před tréninkem“ pro program a školní sezonu, s jasným textem, že pozdější
   omluva nárok nevytvoří. Při zveřejnění termínu uložit účinnou hodnotu
   této politiky; při změně ji lze výslovně použít na vypsané budoucí
   termíny. Konfigurace a změny termínů mají auditní stopu. V administraci se
   také určí celoroční, první a druhé pololetí a explicitní vazba pokračování.
2. V `training_rsvps_admin.php` trenér povolí náhradníky u konkrétního
   `planovane_treninky`, vybere kroužek, kapacitu a může zúžit strukturované
   ročníky. Lze použít hromadně na další výskyty stejné série do zvoleného
   data. Jiný termín nebo série tím nedostanou náhradníky automaticky.
3. Při včasné omluvě se u RSVP uloží čas a zaplacená zdrojová účast. Po
   zapsání skutečné docházky bez dítěte vznikne nanejvýš jeden kredit na
   `(source_plan_id,sportovec_id)`. Rezervace má samostatný audit a
   transakční zamykání nároku i kapacity. Převedený nárok se bez navazujícího
   zaplaceného pololetí pouze zobrazí jako uzamčený; každý pokus o rezervaci
   znovu kontroluje aktivní účast.
4. `booking/nahrady.php` ukazuje počty čekajících, dostupných, uzamčených,
   rezervovaných a využitých lekcí, odkud nárok vznikl, a pouze kompatibilní
   volné termíny. Rodič může spravovat jen schválené dítě, sportovec pouze
   svůj profil. Počty čekajících, dostupných, uzamčených a rezervovaných lekcí
   jsou také přímo na sportovním profilu dítěte. V trenérské přípravě jsou
   náhradníci oddělení od soupisek.
5. Ve `formular.php` jsou rezervovaní náhradníci předvybráni zvlášť od
   kmenového snapshotu, trenér jejich skutečnou docházku před uložením
   opraví. `ulozit_trenink.php` nároky vydá nebo spotřebuje v téže databázové
   transakci. Oprava docházky v `update_trenink.php` nárok přepočítá, ale
   zneplatnění už využitého či rezervovaného kreditu odmítne k ručnímu řešení.

### Akceptační scénáře před nasazením

- Včasná omluva zaplaceného dítěte → po uzavření skutečné docházky jeden
  dostupný kredit. Pozdní omluva, nezaplacené členství, závodní oddíl a
  skutečně přítomné dítě → žádný kredit.
- Změna lhůty v administraci kroužků ovlivní nové termíny a po výslovném
  výběru také stávající budoucí termíny, nikoli zpětně již uplynulou lekci;
  stejná skupina má stejnou lhůtu při celoroční i
  pololetní variantě.
- Září má náhradníky vypnuté, leden zapnuté jen u vybraných termínů;
  opakovaná série nesmí přepsat ruční nastavení lednového výskytu.
- Dítě mimo věkové rozmezí nebo termín bez rozsahu se v nabídce neobjeví.
  Dítě na hranici rozsahu ano. Volné místo se při souběhu dvou rezervací
  obsadí nanejvýš jednou.
- Jeden kredit nejde utratit na dvě lekce ani použít zároveň z rodičovského
  a dětského účtu. Celoroční + pololetní nákup stejného dítěte nezaloží dvojí
  nárok.
- Zrušení cílového tréninku vrátí kredit. Převod z 1. do 2. pololetí se
  provede právě jednou. Bez zaplaceného druhého pololetí je kredit viditelný,
  ale nedostupný; po potvrzení platby se odemkne. Zaplacená celoroční varianta
  platná pro druhé pololetí podmínku splňuje, nezaplacená či stornovaná nikoli.
  Do další sezony už kredit nepřejde. Storno navazujícího nákupu se rezervovanou
  převedenou náhradou rezervaci uvolní; již vyčerpaná převedená náhrada
  automatické storno blokuje a vyžaduje individuální kontrolu. Trenér vidí náhradníka v přípravě a
  zapíše jeho skutečnou docházku.

Implementované bezpečné výchozí volby: 12 hodin předem, pozdní storno bez
automatického vrácení, platnost celoročního nároku do konce zakoupené
celoroční nabídky a žádné automatické věkové výjimky. Lhůtu lze měnit v
administraci; pro pozdní storna a věkové výjimky nyní není samoobslužný
postup a případnou změnu pravidel je třeba navrhnout samostatně.

Migrace `20261014110000_club_makeup_lessons` přidává politiku, mapování
pololetí, pravidlo omluvy pro termín, kapacitu náhradníků, nároky, rezervace
a audity. V `bin/db-backup.php` jsou nové tabulky zahrnuté do vlastněné
produkční zálohy. Produkční rolový UAT a ověření nákupu navazujícího pololetí
je samostatná akceptační brána před provozním zapnutím pravidel.
