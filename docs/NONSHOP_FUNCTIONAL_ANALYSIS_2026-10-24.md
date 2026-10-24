# Funkční analýza oddílového systému mimo e-shop

24. 10. 2026. Cíl: dokončit běžnou práci oddílu v jednom systému a odstranit
zbytečné přepisování na papír, do Excelu a mezi jednotlivými obrazovkami.
E-shop, sklady, doklady a jejich opakovaná technická přejímka jsou odloženy
na závěr. Výdaje, zálohy a odměny zde posuzujeme jako klubové pracovní postupy.
Půjčované vybavení zůstává mimo zadání.

Podklad: čtení aktuálních formulářů, obslužných funkcí, navigace a dokumentace
v pracovní kopii `evidencePavel-finance-b1-20261022`, HEAD `f4a25f52`.
Neproběhly testovací běhy, prohlížečová přejímka, změny aplikace, aktivace
odesílání ani produkční zápisy. Jde o funkční rozbor zdrojového stavu, nikoli
tvrzení o tom, jak jsou konkrétní agendy skutečně naplněny v produkci.
Starší implementační protokoly popisují tehdejší stav; jejich formulace
„nenasazeno“ zde nejsou považovány za aktuální stav nasazení.

## Závěr

Systém už obsahuje většinu základních oddílových agend. Největší zbývající
práce spočívá v dokončení jejich obsluhy a návazností. Samotná přítomnost
databázové tabulky, formuláře nebo auditní historie neznamená, že pracovník
dokáže bez pomoci dokončit celý případ.

Potvrzené slabiny zahrnují nezobrazené pracovní případy vedoucího sportu,
ruční zadávání ID a verzí, přehledy omezené na posledních 100–200 záznamů
bez dohledání starších položek a roztříštěné vyřízení přijetí, akce či výdaje.
Část dalších potřeb vyžaduje klubové rozhodnutí, nikoli nový modul.

## Funkční mapa

| Oblast | Co už ve zdrojích máme | Co potřebuje dokončit nebo rozhodnout |
| --- | --- | --- |
| Osoby a rodiny | Evidence osoby, kontakty, ruční řešení shod, schvalované vazby rodič–dítě, samostatný sportovní přístup, historie změn. | Souvislé osobní přijetí a jeden přehled nevyřízených kroků osoby. Změnu rodinného oprávnění držet odděleně od pouhé změny kontaktu. |
| Členství | Výslovný stav s důvodem a auditní historií; členství se neodvozuje automaticky od platby. | Datovaná životní historie členství, pokud má evidence sloužit jako seznam členů k minulému datu; vyřízení odchodu/přerušení včetně návazných kroků. |
| Skupiny a soupisky | Sezony, časové zařazení, hierarchie skupin, přechody, výjimky a skutečné provedení roční obnovy. | Sjednotit běžné vstupy a průvodce pokračováním; nejde o chybějící základ soupisek. |
| Trenéři a oprávnění | Pracovní pozice, svěřené skupiny/osoby a časová platnost. | Pracovní seznam vedoucího sportu skutečně zobrazit; při zástupu převzít konkrétní práci a rozsah, neslévat oprávnění. |
| Tréninky a rozvrh | Plánování, opakování, kolize, sportoviště, více soupisek, skutečný záznam, uzavření docházky a opravy. | Ucelený postup „dnešní lekce → skutečnost → uzavření“, méně přeskakování mezi stránkami, předání neuzavřené lekce. |
| Omluvy a náhrady | RSVP rodiny/sportovce, poznámka trenérovi, osobní omluva, pravidla, nárok, rezervace a spotřeba náhrady. | Osobní omluvu otevírat přímo z lekce/osoby, bez hledání čísla plánu. Potvrdit skutečná klubová pravidla. |
| Sportovní historie a závody | Kalendář akcí, nominace/potvrzení/start, výsledky a jejich verze, měření, souhrny a export skutečné činnosti. | Výběr akce a oprava výsledku bez ručně zadávané verze; práce nad účastníky jedné akce, ne jen nad osobami jednotlivě. |
| Zdravotní podklady | Účel, pravidla, sledování osoby, nahrání, ověření, náhrada a odvolání; oddělení stavu od soukromého souboru. | Filtrovatelné a stránkované seznamy, stav u konkrétní lekce a předání nevyřešeného podkladu. Žádné odhadnuté pravidlo zdravotní způsobilosti. |
| Rodinný a sportovní portál | Přehled více osob, budoucích tréninků, účastí, omluv, náhrad, zdravotního stavu, plateb a kalendářového odběru. | Srozumitelný seznam „co mám vyřídit“; rozhodnout rozsah žádostí o změnu osobních údajů a předávání podkladů online. Nevytvářet druhý portál. |
| Klubové akce, vozidla a provoz | Klubový kalendář, účastníci, odpovědné osoby, rezervace vozidel, starší evidence událostí, jízd, servisu a účtenek. | Jedno místo pro zajištění a vyúčtování konkrétní akce; zřetelně odlišit rezervaci vozidla, skutečnou jízdu a výdaj. |
| Výdaje, zálohy a odměny | Nárok, doložení, schválení, opravné verze, částečné vypořádání a skutečné vrácení; výkazy z uzavřené činnosti. | Běžné formuláře s výběry a přílohami, dohledání staršího případu, jasný postup předložení pracovníkem nebo osobního přepisu hospodářem. |
| Komunikace a úkoly | Fronty, zprávy soupiskám, rodinné souhrny, připomínky, odpovědnost, termín a osobní dořešení. | Rozlišit připravenou funkci, schválený provoz a skutečně aktivní odesílání. Odstranit pevná tvrzení o stavu transportu z uživatelských textů. |
| Nahrazení původních webů | Importní nástroje, zdrojové vazby, přechodové registry a veřejné stránky programů/kalendáře. | Inventář konkrétních používaných obrazovek, veřejného obsahu a URL; rozhodnout, co přesunout, archivovat nebo přesměrovat. Nástroj importu není hotový převod. |

## Konkrétní mezery a navazující úkoly

Priority: **P1** brání nebo výrazně komplikuje běžnou práci; **P2** potřebné
pro souvislý provoz a odstranění ručních návazností; **P3** volitelný rozsah,
který se doplní jen podle skutečné potřeby. Pořadí níže je implementační návrh,
nikoli povolení nyní měnit aplikaci.

### F01 — Práce vedoucího sportu je načtená, ale její seznam není vidět — P1

- **Máme:** zdroj `training_attendance` a oprávnění vedoucího sportu; stránka
  načte otevřené případy a možné odpovědné osoby.
- **Potvrzená mezera:** šablona seznam vykreslí pouze pro registráře, obsluhu
  objednávek a hospodáře. Vedoucí sportu do této podmínky nespadá.
- **Doplnit:** seznam a předání neuzavřené docházky pro vedoucího sportu,
  pojmenování lekce/skupiny a přímý vstup k uzavření; zachovat jeho oprávnění.
- **Hotovo znamená:** vedoucí najde neuzavřenou lekci, přidělí vlastníka a termín,
  otevře správný podklad a po skutečném vyřízení uzavře úkol.
- **Zdroje:** [definice případu](../includes/operational_cases.php#L15),
  [načtení](../provozni_prehled_admin.php#L34),
  [omezující šablona](../includes/operational_cases_view.php#L7).
- **Návaznost:** první krok, bez čekání na novou evidenci osob nebo finance.

### F02 — Přijetí osoby a běžný trénink dokončit jako jeden pracovní postup — P1

- **Máme:** samostatné funkční agendy osoby, rodinné vazby, papírových podkladů,
  zařazení do soupisky, osobního přijetí, plánovače a uzavření docházky.
- **Mezera v obsluze:** návaznosti zná obsluha; papírový podklad výslovně odkazuje
  na další samostatné kroky. Osobní omluva umožňuje zadání čísla lekce ručně.
- **Doplnit:** na osobě seznam kroků „identita / zastupování / podklady /
  členství / zařazení“ s důvodem překážky a odkazem na konkrétní formulář.
  U lekce propojit plán, omluvy, skutečnou účast, skutečné trenéry a uzavření.
  Údaje přenášet v kontextu osoby/lekce, ne opisováním ID.
- **Hotovo znamená:** registrář přijme osobu i bez online účtu a ví, co zbývá;
  trenér z jednoho vstupu dokončí dnešní lekci a případnou osobní omluvu.
  Registrace, členství, účast a platba zůstanou oddělenými skutečnostmi.
- **Zdroje:** [papírové podklady](../paper_registrations_admin.php#L57),
  [osobní přijetí](../club_program_admissions_admin.php#L21),
  [uzavření a omluva](../training_closure_admin.php#L59).
- **Návaznost:** F01; využít stávající formuláře a oprávnění, nezakládat novou kopii dat.

### F03 — Datované členství a řízené ukončení / návrat — P2

- **Máme:** současný stav čekající/aktivní/neaktivní/archiv a audit rozhodnutí.
  Datovaná soupiska má vlastní životní cyklus.
- **Potvrzená mezera konkrétního postupu:** rozhodnutí o kmenovém členství
  přijímá stav, důvod a revizi; nemá samostatné datum účinnosti či interval.
  Čas zápisu není vždy datem skutečného vstupu nebo ukončení.
- **Doplnit po potvrzení klubových pravidel:** účinnost rozhodnutí, přehled
  členství k datu a průvodce odchodem/návratem. Ukázat dopad na soupisky,
  budoucí účasti, příspěvky, přístupy a nevyřízené podklady. Jednotlivé kroky
  potvrdit podle jejich skutečného významu; dluh ani historii automaticky nemazat.
- **Hotovo znamená:** pozdě zapsaný odchod se odliší od dne zápisu a obsluha
  vidí, které návazné agendy ještě potřebují rozhodnutí.
- **Zdroj:** [současné rozhodnutí o členství](../includes/sportovec_status_lib.php#L19).
- **Návaznost:** F02; rozhodnutí o pravidlech přijetí, přerušení a ukončení.

### F04 — Sportovní účast a výsledek zapisovat z konkrétní akce — P1

- **Máme:** verzované skutečné účasti a výsledky, souhrn osoby a podklady za období.
- **Potvrzená mezera:** formulář žádá „Číslo akce z kalendáře“ a „Aktuální verze“.
  Technickou revizi musí uživatel dohledat a zadat ručně.
- **Doplnit:** výběr akce podle názvu/data, vstup z kalendáře i osoby, výběr
  existujícího záznamu k opravě a automatické předání jeho revize. Následně
  seznam účastníků akce pro jednotlivé potvrzení skutečné účasti/startu.
- **Hotovo znamená:** trenér po závodě doplní starty a později opraví jeden
  výsledek bez znalosti interních čísel. Nominace se automaticky nestane startem.
- **Zdroj:** [formulář účasti](../sports_activity_admin.php#L33).
- **Návaznost:** lze souběžně s F02; před navazujícími sportovními výkazy.

### F05 — Dohledatelnost podkladů a zdravotní přehled — P1/P2

- **Máme:** samostatnou evidenci prohlídek a jejich stavů; u uzavření lekce
  odkaz na stav jednotlivé osoby ke dni tréninku.
- **Potvrzená mezera:** přehled prohlídek načte nejvýše 200 řádků, výdaje
  a zálohy posledních 200, odměny posledních 100; tyto stránky nemají odpovídající
  filtrování a stránkování pro dohledání celého archivu.
- **Doplnit P1:** hledání osoby/případu, období a stavu, stránkování a přímý detail.
  Limit rychlého úvodního přehledu nesmí znemožnit dohledání staršího záznamu.
- **Doplnit P2:** přehled chybějících a končících zdravotních podkladů podle
  skupiny a termínu; stav u účastníka lekce s dalším krokem. Bez zpřístupnění
  soukromých skenů trenérovi. Pravidla účasti musí dodat oddíl.
- **Hotovo znamená:** obsluha najde i starší záznam a zjistí před lekcí, komu
  chybí doložený podklad, bez postupného otevírání všech profilů.
- **Zdroje:** [prohlídky](../medical_clearance_admin.php#L27),
  [výdaje](../club_expenses_admin.php#L26), [odměny](../club_rewards_admin.php#L31).
- **Návaznost:** dohledatelnost nezávisle; sportovní kontext navazuje na F02.

### F06 — Výdaje a zálohy převést na běžné formuláře — P1

- **Máme:** schválení, opravy, zálohy, čerpání a vypořádání; příloha může
  existovat ve starší agendě účtenek, nová evidence ji umí odkazovat.
- **Potvrzená mezera:** nová stránka očekává ID osoby, akce, účtenky a platby.
  Položky výdaje se zapisují jako text oddělený středníky. Přístup má hospodář;
  osobní přepis požadavku hospodářem je výslovně podporovaný postup.
- **Doplnit:** výběry osob/akcí, tabulku položek, připojení dokladu v kontextu
  výdaje, detail případu s historií a částkou zbývající k vypořádání. Výběr
  vhodného peněžního pohybu podle data, reference a částky.
- **Rozhodnutí odložit:** zda má trenér sám předkládat výdaje a žádat zálohy,
  nebo je bude dál přepisovat hospodář. Pro malý oddíl není samoobsluha nutná
  podmínka; srozumitelné osobní vyřízení ano.
- **Hotovo znamená:** hospodář zapíše účtenku a čerpání zálohy bez znalosti
  databázových ID a bez přípravy speciálního textového formátu.
- **Zdroj:** [formuláře a zpracování výdajů](../club_expenses_admin.php#L11).
- **Návaznost:** společná dohledatelnost F05; skutečné schvalovatele a pravidla zachovat.

### F07 — Jedna akce od nominace po uzavření — P2

- **Máme:** klubový kalendář s účastníky, zajišťujícími osobami a vozidly;
  sportovní skutečnost; finanční případ s vazbou na klubovou nebo původní akci.
- **Mezera v návaznosti:** propojení finančního případu se zadává ručním ID.
  Kalendář, skutečná sportovní účast, provozní událost a finanční vypořádání
  tvoří několik oddělených pracovních míst.
- **Doplnit:** z detailu akce otevřít související účast, dopravu, výdaje,
  zálohy a stav vypořádání. Vytvoření finančního případu předvyplní vazbu.
  Starší provozní akci připojit výslovně, nevytvořit automaticky duplicitu.
- **Hotovo znamená:** vedoucí zjistí, kdo jel, kdo odpovídá za dopravu,
  které doklady chybí a co brání uzavření; rezervace vozu není záznamem jízdy.
- **Zdroje:** [kalendář](../club_calendar.php),
  [finanční případ](../club_expenses_admin.php#L30),
  [ochrana propojených starších podkladů](../includes/financial_event_guard.php#L59).
- **Návaznost:** F04 + F06. Půjčování vybavení se nepřidává.

### F08 — Rodinné požadavky a skutečný stav komunikace — P2/P3

- **Máme:** žádosti o propojení, rodinný přehled, omluvy a notifikační fronty;
  CLI podporuje generování i odeslání rodinného souhrnu.
- **Potvrzená mezera informace:** rodinný přehled obsahuje pevné tvrzení,
  že produkční e-mailový transport není aktivní, nezávisle na jeho skutečném nastavení.
  Stav plánovačů a doručování nebyl v této funkční analýze zjišťován.
- **Doplnit P2:** přehled toho, co rodina musí vyřídit, a informace o komunikaci
  vycházející ze skutečné konfigurace; chybějící elektronický kontakt má osobní postup.
- **K rozhodnutí P3:** žádost o změnu kontaktů nebo nové doložení dokumentu
  přímo z rodinného účtu. V prohlédnutém postupu „Moje osoby“ jsou žádosti
  o propojení a jejich zrušení, nikoli obecný formulář změny údajů. Úplnou absenci
  alternativní cesty v celé aplikaci tím neprohlašujeme.
- **Hotovo znamená:** rodič ví, co čeká na klub a co má udělat sám; systém
  neslibuje odeslání pouze na základě uložené preference. Osobní kontakt zůstává platnou cestou.
- **Zdroje:** [rodinný souhrn v portálu](../booking/sportovni_prehled.php#L330),
  [Moje osoby](../booking/moje_osoby.php#L34),
  [odesílací CLI](../bin/family-weekly-summaries.php).
- **Návaznost:** F02 + F05; skutečné odesílání samostatně až po určení provozu.

### F09 — Přechod sezony a náhrada původních webů — P2

- **Máme:** náhled, výjimky a provedení obnovy v `kis_rosters_admin.php`.
  Lokální A06 je navíc přejímací pomůcka, nikoli jediná implementace obnovy.
- **Doplnit:** srozumitelný sezonní postup: cílová sezona, pokračující osoby,
  výjimky, svěření trenérům a návazné termíny. Neobnovovat placenou účast
  automaticky jen podle dřívějšího zařazení.
- **Před nahrazením Evidence/KIS:** vytvořit konkrétní tabulku původní obrazovka
  → nový postup → vlastník → přenášená historie → datum přechodu. Stejně zmapovat
  běžné papírové seznamy a veřejné URL. Staré služby nevypínat jen proto,
  že existuje importér a nový kalendář.
- **Hotovo znamená:** pro každý skutečně používaný postup je určené jedno
  místo zápisu, dohledatelná historie a cesta pro běžného uživatele.
- **Zdroje:** [skutečná obnova](../kis_rosters_admin.php#L18),
  [rosterový model](../includes/kis_roster.php#L154),
  [odložené podklady](CLUB_COMPLETION_DECISIONS.md).
- **Návaznost:** F02/F03 a skutečné mapy osob a skupin. Žádné slučování podle e-mailu.

## Doporučené pořadí realizace

| Balík | Výsledek pro oddíl | Obsah | Závislosti |
| --- | --- | --- | --- |
| A — Každodenní práce trenéra a registráře | Najít práci a dokončit osobu či lekci bez opisování ID | F01, první část F02, odstranění ruční verze/ID ve F04, dohledatelnost z F05 | Stávající osoby, role a agendy; žádné nové účetní rozhodnutí |
| B — Život osoby a podklady | Řízený vstup/odchod, dohledatelné členství, zdravotní stav a další kroky | Zbytek F02, F03, zdravotní kontext F05, rodinné úkoly F08 | A; pravidla členství a podkladů |
| C — Akce a klubová administrativa | Uspořádat akci, předložit podklady a vyúčtovat ji | F06, F07, přehledy odměn | A; skutečné sazby a schvalovatelé jen tam, kde jsou nutní |
| D — Sezona, komunikace a nahrazení starých postupů | Jedno místo pro každý používaný postup | F09, provoz komunikace F08, potvrzené převody | A–C, vlastník každé původní agendy |
| E — Závěrečná společná přejímka | Dokončit ověření celého systému včetně e-shopu | Běžný týden, přijetí/odchod osoby, akce, měsíc účetní, pokračování sezony | Dokončené pracovní postupy a skutečné podklady |

### První doporučený implementační balík A

1. Zobrazit a zpřístupnit případy vedoucího sportu (F01).
2. U lekce propojit plán, omluvy, zápis a uzavření; osobní omluvu otevřít
   s vybranou lekcí a osobou (část F02).
3. U sportovní účasti nahradit číslo akce výběrem; opravu otevírat z existujícího
   záznamu s interně předanou revizí (část F04).
4. Doplnit hledání, stavové filtry a stránkování prohlídek, výdajů a odměn (F05).
5. Na osobě připravit přehled nevyřízených kroků a přímé odkazy se zachováním
   kontextu (část F02). Neměnit zatím klubová pravidla ani zavádět nové stavy členství.

Průběžné ověření bude omezené na právě upravovaný postup a jeho důležité hranice.
Opakovaná celková přejímka e-shopu patří až do balíku E. Není nutné odkládat
veškeré ověřování až na konec, ale nemá nahrazovat funkční dokončování aplikace.

## Co není automaticky chybějící základní funkcí

- Docházka, náhrady, roční obnova soupisek, sportovní historie, zdravotní
  podklady, výkazy i zálohy již mají implementaci; nestavět jejich druhou verzi.
- Samoobslužné výdaje trenérů, rozsáhlé online změny osobních údajů a další
  automatické zprávy nejsou nutnost, pokud oddílu stačí přehledná osobní obsluha.
- Mzdy, automatický výpočet odvodů, licence/kvalifikace, dotační formuláře
  a půjčované vybavení se bez potvrzené potřeby nepřidávají.
- Skutečná pravidla členství, sazby odměn, schvalovatelé, obsah původních webů
  a sporné historické vazby jsou podklady k rozhodnutí. Neurčuje je počet testů
  ani automatický odhad. Jejich nevyřešení nemá brzdit nezávislé úkoly balíku A.

## Následná implementace balíku D — 24. 10. 2026

Dokončen místní průvodce pokračováním období, komunikace s trvalými skupinami,
osobní kontakt u chybějícího ověřeného účtu, pravdivý přehled front a verzovaná
mapa nahrazovaných postupů. [Implementace a ověření](verification/season-operations-20261024/RESULTS.md)
rozlišují hotový kód od nevyřešených provozních podkladů D03/D07/D10–D17.
Skutečné převody a odstavení zůstávají odložené. E-shop nebyl do balíku D zahrnut;
společná přejímka E v této práci nezačala.
