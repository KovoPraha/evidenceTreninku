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
   Odmítnutý nárok má při uložení konkrétní důvod (vypnutá politika, chybějící
   pravidlo termínu, nepotvrzená placená účast či pozdní omluva). Důvod se
   nemění zpětně při úpravě nabídky a rodič jej najde také v přehledu náhrad.
   Starší omluvy bez uloženého důvodu se nesmějí dodatečně vysvětlovat odhadem.
   Jakmile k termínu existuje odpověď nebo nastavení náhrad, změna data, času
   začátku či soupisky se odmítne; termín je potřeba zrušit a vypsat nový.
4. Přiřazený trenér nebo hlavní trenér otevře v `planovac.php` odkaz
   **Příprava**. V `training_rsvps_admin.php` vidí očekávané sportovce,
   odpovědi, zprávy i součty. Skutečnou docházku zapíše dál odděleně přes
   `formular.php?plan_id=…`.

Tato část používá existující `training_rsvps`; migrace
`20261014100000_training_rsvp_notes` přidává `member_note` a migrace
`20261016100000_training_rsvp_makeup_reason` uchovává důvod odmítnutí.
Je potřeba je aplikovat před otevřením upravených stránek. Kalendář,
odpovědi rodiče i sportovce a trenérská příprava byly 16. 10. ověřeny
v prohlížeči na produkčním releasu `276a5969`; pozitivní čerpání a převod
náhrad v něm ověřeny nebyly.

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
   Nezařazená nabídka je označena samostatně, nikoli vydávána za
   „Jednorázové / jiné“. Systém typ z délky ani názvu neodvozuje.
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

## MAKEUP-AUTO-01 — automatické cíle a termíny (12. 11. 2026)

**Stav: ROZPRACOVÁNO — implementace zahájena 12. 11. 2026.** Vlastník: vývoj KIS;
věkové kategorie, pravidla organizace a provozní přijetí: vedení KOVO Praha.
Zadání: automaticky nabídnout náhrady v každém vhodném kroužku podle
strukturovaného věku, volné kapacity a centrálních pravidel, bez ruční vazby
mezi nabídkami a bez ručního otevření termínu trenérem. Původní dodané zadání
zůstává mimo Git, protože není doložené jako podklad bez osobních údajů;
tento oddíl je bezpečný technický přepis. Evidence: [registr](WORK_PLAN_CURRENT.md),
[rozhodnutí](DECISION_LOG.md#d2026-11-12-01).
Příprava vznikla z `origin/main` `4b9bcb1a` na větvi
`codex/makeup-auto-20261112` v odděleném worktree
`C:\xampp\evidencePavel-makeup-auto-20261112`.
Návrh je sdílen v [draft PR #459](https://github.com/KovoPraha/evidenceTreninku/pull/459).
Vlastník 12. 11. výslovně požádal po analýze také o implementaci; merge,
aktivace a lidské UAT dosud neproběhly. Výsledek kódu a testů se doplní níže.

### Ověřený současný stav a omezení

- `includes/club_makeup.php` je jediná služba náhrad. Omluva se posoudí podle
  zaplacené účasti a pravidla termínu; kredit vzniká až po dokončení zdrojové
  lekce a skutečné absence. Je unikátní pro sportovce a zdrojový plán. Storno,
  převod pololetí, dokončení cílové docházky a události již existují.
- `clubMakeupRouteAllowed()` povoluje jen vlastní nabídku nebo řádek v
  `club_makeup_offer_routes`. Migrace `20261102171000_offer_makeup_scopes`
  uchovala historické směrové vazby; nové nabídky je automaticky nedědí.
  `clubMakeupAvailableSlots()` čte jen ručně vytvořené `club_makeup_slots`.
  `clubMakeupReserve()` vše znovu kontroluje v transakci a zamyká kredit a slot.
- `club_makeup_policies` a `club_makeup_offer_policies` jsou nyní politiky
  jednotlivých nabídek. Chybí společné organizační nastavení. `training_rsvps_admin.php`
  dovoluje trenérovi otevřít slot a zvlášť nastavit počet míst. Tento počet
  není totožný s `club_program_offers.capacity`; současná rezervace kontroluje
  jen počet náhradníků proti kapacitě slotu, nikoli součet běžných účastníků.
- Nabídka má strukturované `birth_year_from/to`, platnost a kapacitu. Kalendář
  plánu má vazbu na soupisku, ale nemá jednoznačné `offer_id`; celoroční a
  pololetní varianta mohou sdílet soupisu i termín. Mapování cílové nabídky
  proto nelze odvozovat z názvu ani z jediného stejného školního roku.
- Rezervace se uchovává podle `target_plan_id`, navazující přehled/storno čtou
  `club_makeup_slots`. Přechod na čistě virtuální termíny bez kompatibilního
  záznamu slotu by tyto cesty rozbil. Trenérská docházka náhradníky již čte
  odděleně ze stejných rezervací.
- Administrace je v `club_makeup_policies_admin.php` pod aktivní pozicí
  `program_coordinator`, rodičovský zápis v `booking/nahrady.php` znovu ověřuje
  vztah k dítěti; stránka přípravy trenéra má zvláštní rozsah soupisek.
  Poslední doložený syntetický průchod starého režimu je
  [6. 11.](verification/makeup-audit-20261106/RESULTS.md): 22 cílených testů,
  23 HTTP scénářů a místní prohlížeč PASS. To není ověření nové automatiky.

### Cílový kontrakt a implementační etapy

1. **Migrace a kompatibilita.** Přidat neměnnou migraci s organizačním
   přepínačem automatiky (výchozí `off`), společnými hodnotami omluvy, platnosti,
   limitu a storna, a per-offer výjimkami/režimem účasti. Explicitní
   `allow`/`deny` výjimky směrových vazeb přidat odděleně od staré tabulky;
   staré `club_makeup_offer_routes` zůstanou jako historické pozitivní vazby.
   Pro jednotlivý plán evidovat výslovné vyloučení automatických náhrad a
   ručně otevřené sloty nepřepisovat. Nové tabulky zahrnout do
   `bin/db-backup.php` a jeho kontraktových testů. Migrace nesmí mazat kredit,
   rezervaci, slot ani původní vazbu a nesmí automatiku sama zapnout.
2. **Výběr cíle.** Jedna společná doménová funkce pro zobrazení i POST
   rezervace vyhodnotí: aktivní zdrojový nárok, účinné organizační pravidlo,
   výjimku zdroje a cíle, stav a platnost nabídky, strukturovaný rozsah
   ročníků dítěte a termínu, explicitní zákaz/povolení směru a příslušnost
   plánu k cílové soupisce. `deny` má přednost; věkový limit nelze výjimkou
   obejít. Nejednoznačné přiřazení plánu ke více různým fyzickým skupinám
   fail-closed: koordinátor vybere cílovou nabídku nebo plán vyloučí.
   Celoroční a pololetní varianty stejné fyzické skupiny tvoří jeden cílový
   termín; rodič neuvidí duplicitní plán.
3. **Automatické termíny.** Plánovaný, budoucí, nezrušený termín v platnosti
   zapojené nabídky je kandidát bez zásahu trenéra. Stávající ruční slot má
   přednost jako vědomá výjimka a existující rezervace dál odkazují na něj.
   Při první automatické rezervaci vytvořit kompatibilní slot v téže transakci
   pod zámkem plánu; slot označit jako automatický a uložit ID cílové nabídky.
   Zápis z GET stránky nepoužít. Vyloučení termínu musí být auditované a nesmí
   rušit již potvrzené rezervace; ty řeší stávající storno plánu.
4. **Kapacita.** Pro automatiku používat skutečný limit cílové skupiny a
   počet unikátních aktivních běžných účastníků pokrývajících datum, plus
   aktivní náhradní rezervace. Do pravidla zahrnout již držená místa v
   nákupním procesu, pokud jsou relevantní ke stejnému fyzickému místu;
   navázat na kanonický výpočet v `club_program.php`, ne sestavit druhý
   konkurenční výklad. Nabídka bez bezpečně určeného pevného limitu potřebuje
   explicitní organizační strop náhradníků; bez něj se v automatice nenabízí.
   Zápis musí serializovat současné nákupy/rezervace nad stejným zámkem a
   přepočítat volno po zamknutí. Počet 20/15 znamená nejvýše pět náhrad,
   nikoli pět dalších ke starému ručnímu limitu.
5. **Pravidla nároku.** Existující uložené rozhodnutí o omluvě a jeho důvod
   zůstanou historickou pravdou; nové společné hodnoty a výjimky se použijí
   jen pro budoucí termíny podle výslovné volby koordinátora. Zachovat
   odložené připsání kreditu po uzavření docházky, rezervovaný stav kreditu,
   jednorázový převod pololetí a pravidla stornování. Změna platnosti/limitu
   nesmí zpětně odebrat už vydaný či rezervovaný nárok bez řízené výjimky.
6. **Rozhraní a oprávnění.** Centrální stránka ukáže společná pravidla,
   nabídky s věkovým rozsahem a stavem zapojení, výjimky, předhled termínů,
   počty nároků a audit; zápis jen koordinátor/správce s aktivní pozicí a
   serverovou kontrolou. Trenér vidí náhradníky a zapisuje docházku, ale
   běžné termíny nemusí povolovat. Rodič spravuje pouze své děti. V přehledu
   náhrad se u termínu zobrazí skutečná cílová skupina a volná místa, při
   nedostupnosti konkrétní důvod tam, kde jej lze bezpečně sdělit.
7. **Přechod a ověření.** Před aktivací provést čtecí inventuru starých vazeb,
   ručních slotů a aktivních rezervací; konflikty ručního směru s novým
   automatickým výsledkem předložit koordinátorovi. Na syntetických nabídkách
   ověřit všechny scénáře zadání včetně hranic věku, 20/15, dvou souběžných
   POST, storna, dokončení docházky, oprávnění a zachování staré historie.
   Zopakovat plné PHP/SQLite a MariaDB 10.3/11.4 CI a přihlášený rodičovský,
   trenérský a koordinační prohlížeč na mobilu i desktopu. Produkční aktivace
   je samostatný krok až po lidské přejímce věkových kategorií a pravidel.

### Otevřené vstupy pro bezpečnou aktivaci

Implementační větev `codex/makeup-auto-20261112` obsahuje opt-in migraci,
jediný serverový výběr automatických termínů, opakovanou kontrolu při POST,
výpočet volných míst se společnou kapacitou nákupu, výjimky, audit a rozhraní
koordinátora, rodiče a trenéra. Nová nabídka po zapnutí vytvoří politiku a
pravidla omluv také pro již naplánované budoucí termíny příslušné soupisky.
[Ověření a meze](verification/makeup-auto-20261112/RESULTS.md),
[draft PR #459](https://github.com/KovoPraha/evidenceTreninku/pull/459).

Vedení musí potvrdit skutečné strukturované věkové hranice KOVO skupin,
společnou lhůtu omluvy, dobu platnosti kreditu, maximální počet náhrad,
stornovací uzávěrku a zacházení s nabídkami bez číselné kapacity. Technický
návrh a výchozí vypnutý přepínač dovolují implementovat bez těchto hodnot;
automatiku pro skutečné nabídky nelze zapnout odhadem. Současná kontrola
úplné dohody o zdrojové a cílové fyzické kapacitě ještě vyžaduje transakční
návrh proti reálnému schématu a zátěžový test na MariaDB.
