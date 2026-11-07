# Etapa 2 — účetní doklady a export pro účetní

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Zadání k 22. 10. 2026. Pouze příprava práce; bez implementace, vystavování dokladů, importu a změn produkce.

**Máme objednávky, členské předpisy, potvrzování plateb, rozdělení rodinných úhrad a účetní zařazení položek. Potřebujeme nad nimi dokončit průkazné doklady, sjednotit účetní pohled na skutečné peníze a připravit kontrolovaný měsíční balík XLSX + PDF. Číselná řada objednávky ani stav „zaplaceno“ tento celek nenahrazují.**

Oddíl podle potvrzení uživatele vede **jednoduché účetnictví a není plátcem DPH**. Účetní dostává XLSX a faktury. KIS bude zdrojem provozních podkladů; účetní knihy, daňové posouzení a roční výstupy nadále zpracovává účetní. Přímé účtování, MD/Dal, ISDOC ani napojení konkrétního účetního programu nejsou podmínkou první verze.

## Rozsah a podklady

Navazuje na [zadání etapy 0](../etapa-0-2026-10-22/01_UCETNI_DOKLADY_A_EXPORT.md) a [etapu 1](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md). Ověřený zdroj je `origin/main` na revizi `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9` po aktuálním načtení vzdálených referencí. „Máme“ označuje implementaci v tomto zdroji; aktuální produkční konfigurace a úplná provozní přejímka se tím nepotvrzují.

Základ pro jednoduché účetnictví tvoří peněžní deník, evidence pohledávek/závazků a pomocné knihy majetku. Proto musí export rozlišovat peněžní pohyb, pohledávku, doklad a sklad, nikoli pouze vypsat zaplacené objednávky. [Finanční správa — jednoduché účetnictví a účetní doklady](https://financnisprava.gov.cz/cs/dane/dane/dan-z-prijmu/ucetnictvi/obecne-informace).

Přesný vystavovatel, účetní program, skutečný vzor předání, pravidla vydání dokladů a členění příjmů/výdajů zůstávají k rozhodnutí v E2-01. Potvrzený režim účetnictví ani neplátcovství se znovu nezjišťují.

## Co již existuje a kde je mezera

| Oblast | Co máme | Co je potřeba doplnit nebo ověřit |
|---|---|---|
| Objednávky a položky | Uložené ceny, varianty, příjemce, doprava, slevy, stav a historie | Fakturační identitu a neměnný vydaný doklad s vlastním číslem a PDF |
| Číselné řady | Čísla objednávek/VS, čítače, účetní kategorie a historické zařazení položek | Samostatné číslování dokladů, návaznost na Shoptet, opravy a uzavřená období |
| Účetní zařazení | Produktová výjimka, dědění katalogové kategorie, kontrola při publikaci | Členění pro jednoduché účetnictví, středisko/činnost a rozpis skutečné úhrady |
| Platby | Společný platební přechod, ruční hotovost/převod, bankovní reference, rozdělení úhrad | Jeden účetní záznam skutečného pohybu napříč zdroji, skutečné datum a historie k rozhodnému dni |
| Vratky | Storno celé objednávky a potvrzení skutečně provedené vratky | Opravný doklad, položkové/částečné vratky, návaznost peněz a fyzického vrácení |
| Pokladna | Záznam přijetí hotovosti u objednávky | Pokladní doklady, vlastní pokladny, zůstatky a inventura hotovosti |
| Výdaje | Účtenky, částka, způsob platby, kategorie, vazba na akci a soukromá příloha | Věřitel, splatnost, skutečný plátce, úhrady, stav závazku a zahrnutí do balíku |
| Excel | Existující XLSX export tréninků a technická knihovna pro XLSX | Nový účetní export; export tréninků není účetní export |
| Audit a záloha | Doménové historie, soukromé úložiště, databázová záloha a test obnovy | Archiv vydaných PDF/balíků, záznam předání a obnova nových účetních artefaktů |

Přesné zdroje a scénáře jsou v [příloze](01_KONTRAKT_EXPORTU_PRIJEMKA_A_ZDROJE.md).

## Priority, návaznosti a pracovní vlny

**P0:** podmínka prvního správného účetního balíku a bezpečného vystavování. **P1:** dokončení běžné obsluhy a úplnosti dohodnutého rozsahu etapy; případné omezení pilotu musí být výslovně uvedeno. **P0 při použití:** povinné před zapojením daného platebního kanálu, jinak může zůstat vypnutý.

Vlastníci jsou návrh rolí, nikoli přidělení konkrétním lidem.

| ID | Priorita | Úkol | Závisí na | Vlastník |
|---|---|---|---|---|
| E2-01 | P0 | Metodika účetní a přesný rozsah předání | E0-01/02 | Účetní + hospodář |
| E2-02 | P0 | Jednotný účetní pohled na zdroje, peníze a data | E2-01; identity E1-02/03 | Vývoj + hospodář |
| E2-03 | P0 | Účetní členění položek a plateb | E2-01/02 | Účetní + hospodář |
| E2-04 | P0 | Vystavovatel, odběratel, plátce a účastník | E2-01; přístupy E1-04/05 | Registrář + hospodář |
| E2-05 | P0 | Registr dokladů, číselné řady a neměnné PDF | E2-02/03/04 | Vývoj + hospodář |
| E2-06 | P0 | Vystavení pro zboží, kurzy, příspěvky a zálohy | E2-05 a pravidla E2-01 | Hospodář + vývoj |
| E2-07 | P0 | Hotovost, banka a pokladní doklady | E2-02/03/05 | Hospodář |
| E2-08 | P0 | Opravy, částečné vratky a přeplatky | E2-06/07 | Hospodář + vývoj |
| E2-09 | P1 | Přijaté výdaje a zálohy pracovníkům | E2-02/05/07; E0-03 | Hospodář + správci akcí |
| E2-10 | P0 při použití | Vypořádání platební brány a dobírky | E2-02/03/08; dostupné výpisy kanálu | Hospodář + účetní |
| E2-11 | P0 | Měsíční XLSX a archiv dokumentů | E2-02 až E2-08; E2-09/10 podle rozsahu | Vývoj + účetní |
| E2-12 | P0 | Kontroly, uzavření balíku a pozdější opravy | E2-11 | Hospodář + účetní |
| E2-13 | P0 | Přístup, předání, archiv a obnova | E2-05/11; E1-05/07/09 | Správce systému + hospodář |
| E2-14 | P0 před přechodem | Staré doklady a počáteční stavy | E2-01/02/05; E0-04/06/07 | Hospodář + účetní |
| E2-15 | P0 | Přejímka účetní a předání obsluze | E2-06 až E2-14 v dohodnutém rozsahu | Účetní + vedení |

**Vlna A — rozhodnutí a datový základ:** E2-01, E2-02, E2-03 a E2-04. Souběžně připravit plán starých dokladů E2-14 a pravidla přístupu/archivu E2-13.

**Vlna B — doklady a skutečné peníze:** E2-05 → E2-06/07 → E2-08. Následuje minimum výdajů E2-09 a vypořádání E2-10 pro skutečně používané kanály.

**Vlna C — předání účetní:** E2-11 → E2-12, dokončení E2-13/14 a souvislá přejímka E2-15. Návrh XLSX lze s účetní projít už ve vlně A; ostrý export čeká na dokončené zdroje a kontroly.

## Konkrétní úkoly

### E2-01 — Dohodnout metodiku a rozsah účetního předání

**Máme:** potvrzené jednoduché účetnictví, neplátcovství, předávání XLSX + faktur a návrh rozhodnutí U01–U09 v etapě 0.

**Doplnit:**

- Přesnou právnickou osobu vystavovatele, IČO, sídlo, bankovní účty, pokladny a odpovědné osoby. Marketingový název oddílu sám nestačí.
- Jeden anonymizovaný příklad současného měsíčního předání a název účetního programu; rozhodnout, zda účetní XLSX pouze čte, nebo jej strojově importuje.
- Schválit události vydání pro zboží, kurz a členský příspěvek, postup záloh, splatnost, čísla řad, opravy a podpisovou/odpovědnostní stopu mezi KIS a účetní.
- Určit rozsah: pouze podklady e-shopu/členství, nebo celý oddíl. Ověřit další příjmy a výdaje mimo objednávky — například dotace, dary, bankovní poplatky či odměny — a kdo je dodá. Jejich existenci ani daňové členění nepředpokládat.
- Sepsat, které podklady zatím dodá účetní nebo hospodář externě. Balík označit přesným pokrytím, ne slovem „kompletní“, pokud část peněz chybí.

**Hotovo, když:** účetní a hospodář odsouhlasí rozhodovací tabulku, vzorové doklady a seznam zdrojů. Chybějící program nebrání návrhu, ale neumožňuje tvrdit kompatibilitu automatického importu.

### E2-02 — Sjednotit peněžní pohyby a jejich identitu

**Máme:** `payments`, bankovní pohyby Fio, `club_finance_receipts`, `member_payer_receipts` a jejich rozdělení. Kontroly bankovní reference již brání opakovanému potvrzení ve více cestách. Rodinné přidělení může změnit související `payments` na uhrazené.

**Doplnit:**

- Kontrakt jednoho peněžního pohybu: stabilní ID, původní reference, zdroj a účet/pokladna, měna, částka, příjem/výdej/převod, skutečné datum pohybu, čas evidence, potvrzující a stav ověření. Pro více účtů/providers musí být reference v jednoznačném zdrojovém kontextu.
- Přesnou mapu původních tabulek: který řádek je skutečný příjem, který platební požadavek a který jen rozdělení či odvozený stav. Nevytvářet nový příjem z každého `payments.status='paid'`.
- Oddělit skutečné datum od potvrzení. V aktuálním potvrzení objednávky se `paid_at` nastavuje na `CURRENT_TIMESTAMP`, u vratky obdobně `refund_sent_at`; automatické použití těchto časů pro účetní měsíc není bezpečné. Banka poskytne datum pohybu, ruční příjem vyžaduje doložené datum. Neověřitelná historie je výjimka k rozhodnutí, nikoli odhad.
- Zachovat původní příjem i po stavu `refunded`; vratka je nový opačný pohyb. U pozdějšího párování zachovat datum přijetí a samostatné datum přidělení.
- Zavést účetní projekci nad existujícími zdroji s dohledatelnou vazbou. Není nutný přepis všech doménových tabulek ani druhý nezávislý platební systém.

**Hotovo, když:** stejný příjem se ve všech cestách vykáže právě jednou a lze sestavit stav k minulému dni. Shodná částka či VS samy nestačí ke sloučení pohybů. Neidentifikovaná skutečná platba se z přehledu peněz neztratí.

### E2-03 — Doplnit účetní členění

**Máme:** stabilní účetní kategorie, mapování produkt/kategorie/druh, historické snímky zařazení položek a kontrolu platného zařazení při publikaci produktu.

**Doplnit:**

- Mapování na členění pro jednoduché účetnictví schválené účetní, činnost/středisko a pravidla platnosti změn. Stávající obchodní kategorie není automaticky daňové posouzení.
- Kontrolu i historických objednávek, dopravy, slev, služeb a ručně zadaných výdajů; kontrola publikace nových produktů tuto historii nepokrývá.
- Rozpis jedné skutečné úhrady mezi více účetních členění, včetně částečné platby smíšené objednávky. Metodu rozdělení a zaokrouhlení schválí účetní.
- Verzi pravidel a audit opravy zařazení; změna katalogu nesmí přepsat vydané doklady a již předané balíky.

**Hotovo, když:** každý finanční řádek má doložené členění nebo přiznanou výjimku a dílčí rozpis se sejde s peněžním pohybem na haléř. „Smíšená objednávka“ není finální účetní členění všech jejích položek.

### E2-04 — Doplnit fakturační údaje a role osob

**Máme:** účty, osoby, schválené rodičovské vazby, příjemce položek a snímky jména/e-mailu zákazníka; údaje profilu a kontaktů jsou v systému oddělené.

**Doplnit:**

- Jednoznačné role: odběratel dokladu, skutečný plátce, účastník kurzu a osoba přebírající zboží. Rodič, dítě a organizace nejsou zaměnitelné.
- Kontrolovaný sběr potřebných fakturačních údajů soukromé osoby či organizace v běžném nákupu, nákupu bez členského účtu a osobním vyřízení.
- Neměnný snímek vystavovatele a odběratele při vydání; doručovací kontakt vést samostatně. Pozdější změna profilu nepřepíše doklad.
- Doklady dítěte zpřístupňovat podle role v obchodním vztahu; samotná aktuální rodičovská vazba není univerzální oprávnění k cizím nákupům.

**Hotovo, když:** lze doložit nákup rodiče pro dítě i organizace pro účastníka bez zakládání fiktivního člena a bez přepisování starších dokladů.

### E2-05 — Registr dokladů, číslování a PDF

**Máme:** transakční čítače čísel objednávek a VS, ochranu kolizí, audit a soukromé úložiště. Neměnný registr vydaných účetních dokladů a účetní PDF vrstva v kontrolovaném řezu nejsou dokončené.

**Doplnit:**

- Registr konceptů, vydaných a opravovaných/stornovaných dokladů: typ, vlastní číslo, snímek dat, vazby na obchodní případ, autor, časy, verze šablony a otisky. Oddělit stav úhrady od stavu dokladu.
- Samostatné řady faktur, oprav a pokladních dokladů podle E2-01. **Dosavadní VS a stálé VS příspěvků se nemění.** Číslo dokladu nemusí být VS.
- Náhled bez spotřebování čísla; vydání odolné dvojkliku, opakování i souběhu. Použité číslo se nevrací do oběhu, neúspěch musí mít dohledatelný výsledek.
- Spolehlivý postup při chybě generování/uložení PDF: nevznikne „hotový“ doklad bez souboru; oprava pokusu nevystaví druhý doklad. Kontrolovat shodu uložených dat a PDF.
- Režim neplátce DPH a šablony schválené účetní; stávající produktové daňové příznaky nejsou samy metodikou vystavovatele. Neplátcovství nevydávat za sazbu DPH 0 %.

**Hotovo, když:** tentýž doklad jde znovu stáhnout se stejným obsahem a otiskem, změna katalogu/adresy jej neovlivní a souběžné vydání nevytvoří kolizi čísel.

### E2-06 — Vystavování podle obchodního případu

**Máme:** objednávky zboží/programů, členské předpisy, platby a výdejové stavy. Existující e-maily objednávky nejsou automaticky faktury.

**Doplnit:**

- Rozhodovací pravidla potvrzení objednávky, výzvy k úhradě, faktury neplátce, potvrzení úhrady a zálohy/vypořádání podle E2-01. Počáteční verze může vydávat ručně přes náhled; automatické vydávání není nezbytné.
- Pro členský příspěvek propojit předpis a potvrzení s jedinou pohledávkou. Faktura nebo potvrzení nesmějí zakládat druhý dluh.
- U kurzu zachovat účastníka a období služby i datum peněz. Prosincová platba za jarní kurz zůstane prosincovým pohybem; forma konečného dokladu se řídí dohodnutou metodikou.
- U smíšeného případu řešit položkové slevy, dopravu, částečné plnění a zúčtování záloh. Předem zvolit, zda přípustná kombinace vytvoří jeden doklad, nebo více provázaných dokladů. Součet nesmí zdvojit cenu.
- Potvrzení pro rodiče nebo zaměstnavatele vytvořit z existujícího dokladu a skutečné úhrady; nemá vytvářet novou platbu.

**Hotovo, když:** každý dohodnutý druh prodeje/příspěvku má jasný spouštěč, doklad a vazbu na jednu pohledávku. Příjem peněz není podmínkou vzniku všech pohledávek a faktura není důkaz příjmu.

### E2-07 — Pokladna a bankovní pohyby

**Máme:** hospodář může potvrdit skutečnou hotovost místo převodu bez nové objednávky; existují bankovní příjmy, Fio podklady a potvrzování vratek. Samostatnou pokladní knihu ani tisk účetního dokladu tento tok nepředstavuje.

**Doplnit:**

- Evidenci pojmenované pokladny, odpovědného pracovníka, počátečního stavu, příjmů, výdajů, oprav a fyzické inventury. Každý skutečný pohyb navázat na odpovídající PPD/VPD a doklad podle metodiky.
- Datum skutečného převzetí/vydání, případné doplnění po výpadku a kontrolu duplicit. Volba „hotově“ sama nevytváří peníze.
- Přesuny pokladna–banka a mezi vlastními účty svázat společným ID převodu. Obě strany jsou dohledatelné, nevzniká druhá tržba ani spotřební výdaj.
- Evidovat i bankovní poplatky a ostatní pohyby v odsouhlaseném rozsahu; nespoléhat jen na spárované objednávky. Pro ruční režim požadovat doloženou referenci výpisu.
- Oddělit provozní přehled pokladny od účetních knih vedených účetní. Bankovní import a automatické potvrzování se tímto zadáním nezapínají.

**Hotovo, když:** počáteční stav + příjmy − výdaje souhlasí s konečným stavem po jednotlivých pokladnách/účtech; neobjasněný rozdíl brání uzavření. Příjem za tričko, faktura a PPD nejsou tři příjmy.

### E2-08 — Opravy, vratky a přeplatky

**Máme:** storno celé objednávky, samostatné potvrzení již provedené vratky a klubové/rodinné přeplatky. Stávající storno může vrátit objednávkové množství do skladu; tento postup není univerzální fyzická reklamace.

**Doplnit:**

- Opravný doklad s vazbou na původní položky, částky, důvod a autora. Vydaný originál se nepřepisuje; u neplátce nejde automaticky o „opravný daňový doklad“.
- Částečné vrácení, více postupných oprav a kontrolu maximální opravitelné částky/množství. Dvě trička s vrácením jednoho nesmějí vyžadovat storno celé objednávky.
- Samostatnou evidenci skutečně vyplacené vratky a jejího data. Oprava ceny, zrušení účasti a peněžní výdej jsou různé události.
- Rozlišení vrácení rezervace a skutečného vrácení již vydaného zboží; poškozené zboží se nevrací automaticky do prodejného stavu. Skladový návrat dopracuje etapa 3.
- Auditované uvolnění/přesun přídělu, vrácení přeplatku a opravu chybného párování. Přeplatek, sportovní odměna a náhrada lekce mají oddělený význam; žádný automatický společný kredit.

**Hotovo, když:** lze doložit celý i částečný opravný cyklus včetně zbylé pohledávky, skutečných peněz a nezávislého stavu zboží. Podpora jedné konkrétní plné vratky se nevydává za hotovou agendu všech vratek.

### E2-09 — Minimum přijatých výdajů a záloh

**Máme:** evidenci účtenek s kategorií, částkou, způsobem platby, vazbou na akci/vozidlo, nahrávající osobou a soukromým souborem. Uživatel potvrdil papírové výdaje, zálohy a odměny.

**Doplnit:**

- Údaje dodavatele, číslo a datum dokladu, splatnost, měnu, účel/středisko, skutečného plátce, přílohu a schválení k předání. Samotná metoda „hotově“ neprokazuje, že platila klubová pokladna.
- Rozlišit neuhrazený dluh dodavateli od dluhu pracovníkovi, který nákup zaplatil ze svého. Úhrada pracovníkem není okamžitým výdajem klubové banky; následné proplacení nesmí zdvojit nákup.
- Minimum zálohy pracovníkovi: předání, příjemce, skutečné výdaje, uznané doklady, vratka/doplatek a zůstatek. Účtenka a vyúčtování téže zálohy nesmějí být dalšími peněžními výdaji.
- Převzetí schválených podkladů odměn s vlastním zdrojovým ID a stavem vyplacení. Výpočet a plný schvalovací postup odměn zůstává etapě 5; výkaz výkonů sám není důkaz výplaty.
- Ochránit již předané přílohy a historické hodnoty před běžnou editací nebo odstraněním účtenky.

**Hotovo, když:** export rozezná závazek, jeho úhradu i zůstatek a každý náklad lze doložit. Pro pilot omezený na příjmy mohou výdaje dodat externí podklady; bez jejich kontrolovaného zahrnutí nelze označit výstup za celý účetní měsíc oddílu.

### E2-10 — Vypořádání brány a případné dobírky

**Máme:** platební záznamy a reference online plateb. Potvrzení zákaznické platby není samo podkladem všech poplatků a převodů poskytovatele.

**Doplnit před použitím daného kanálu:**

- Dohodnutý výpis poskytovatele a vazbu hrubých plateb, vratek, poplatků, případných rezerv/zadržených částek a čisté výplaty na banku. Pro malý oddíl stačí kontrolovaný ruční import/evidence výpisu, API není podmínkou.
- Počáteční a konečný nevypořádaný zůstatek a přesah mezi měsíci. Výplata 980 Kč za platbu 1 000 Kč s poplatkem 20 Kč nevytváří druhý příjem z prodeje.
- Pokud bude dobírka, stejný princip pro inkaso dopravcem a následné vypořádání. Samotné sledovací číslo zásilky nedokládá zaplacení.

**Hotovo, když:** výpis poskytovatele souhlasí s přiřazenými platbami, výplatami a zůstatkem. Dokud se kanál nepoužívá, jeho aktivace není podmínkou etapy 2. Již používaný kanál nelze z „kompletního“ balíku vypustit.

### E2-11 — Měsíční účetní balík

**Máme:** zdrojové agendy a technickou podporu XLSX, v etapě 0 navržené listy. Hotový účetní balík v kontrolovaném řezu chybí.

**Doplnit:**

- Balík `KIS_ucetni_YYYY-MM_revNN.zip`: XLSX, vydané PDF, dohodnuté výdajové přílohy a manifest souborů s otisky. Bez automatického odesílání.
- Konzistentní snímek s rozhodným okamžikem, verzí formátu, obdobím, seznamem zahrnutých zdrojů a známých omezení. Každý list má vlastní jasné pravidlo zařazení dat.
- Devět listů z etapy 0 a doplněný samostatný list **Cleneni_penez** pro rozpis jednoho pohybu do více účetních členění. Jde o upřesnění již požadovaného rozpisu, odděleného od párování na děti/předpisy. Návrh deseti listů je v příloze a musí jej převzít účetní.
- Stavy pohledávek a závazků k závěru měsíce včetně počátků a starších neuhrazených případů. Pouhý filtr na datum vytvoření v měsíci je neúplný.
- Textové VS, IČO a identifikátory, částky jako čísla, jednoznačné datum/měna, ochranu před vzorci v uživatelských textech a funkční soukromé odkazy na soubory uvnitř balíku.

**Hotovo, když:** účetní otevře dokument bez ručních oprav, rozumí jednotlivým listům, dohledá souvislosti a nic nemusí sčítat napříč odlišnými pohledy na stejné peníze. Opětovné stažení stejné revize vrací archivovaný balík.

### E2-12 — Kontroly a uzavření předání

**Máme:** lokální kontroly částek, platnosti přídělů a duplicit v jednotlivých agendách. Chybí společné uzavření účetního snímku.

**Doplnit:**

- Kontroly částek dokladů, slev, zaokrouhlení, peněz, párování, účetního členění, pokladny, banky a případných zůstatků u brány podle přílohy.
- Stavy balíku návrh → neúplný / připravený → předaný → přijatý účetní, s možností vrácení k doplnění. „Předáno“ není „zaúčtováno“.
- Blokátory: duplicitní skutečný pohyb, neshoda součtů, neexistující povinný PDF/důkaz, chybějící povinné údaje/členění a nerozhodnuté zásadní finanční rozdíly. Pracovní neúplný export může vzniknout s jasným označením.
- Revize po opravě a seznam změn proti předchozímu předání. Archivovaný balík se nesmí tiše přepsat; účetní pozná, zda dostává náhradu celého balíku, nebo samostatný opravný podklad.
- Historické přepárování a dodatečně nalezený doklad musí být dohledatelné i po předání. Uzavření balíku nezastaví běžný provoz nového měsíce.

**Hotovo, když:** systém umí vysvětlit rozdíl a nedovolí vydávat nesouhlasící výstup za připravený. Stejnou položku nelze omylem zaúčtovat z původního balíku i jeho opravené náhrady bez varování o revizi.

### E2-13 — Oprávnění, archiv a obnova

**Máme:** pracovní pozice, audit, privátní soubory a zálohování databáze. Etapa 1 řeší celkovou obnovu a provozní zprávy.

**Doplnit:**

- Hospodář připravuje/vydává/opravuje a předává, účetní má dohodnutý balík či omezené čtení, trenér jen potřebné vlastní podklady. Export celé ekonomiky nesmí být běžným rodinným oprávněním.
- Záznam kdo, kdy a kterou revizi vytvořil, stáhl a předal; reakce účetní jako samostatná událost. Žádné automatické rozesílání první verze.
- Soukromý archiv původních PDF, exportů, výdajových příloh a jejich datových snímků. Uchování a oprávnění po odchodu člena dohodnout podle druhu podkladu.
- Zahrnout nové tabulky a soubory do zálohy a prokázat obnovu včetně shody otisků. Samotná kopie faktury v e-mailu není archiv.

**Hotovo, když:** účetní dostane přesně určený balík, neoprávněný uživatel jej nestáhne a obnovená kopie obsahuje stejné doklady i historii předání. Rodná čísla a zdravotní či tréninkové podrobnosti do exportu nepatří.

### E2-14 — Shoptet, staré doklady a počáteční stavy

**Máme:** původní systémy, porovnání etapy 0 a dosavadní platební identifikátory. Historické účetní soubory ani jejich úplnost nejsou tímto plánem ověřené.

**Doplnit před účetním přechodem:**

- Rozhodné datum a pravidlo, který systém vystavuje nový doklad. Existující faktury Shoptetu archivovat nebo referencovat jako původní doklady; znovu je nevystavovat pod novou identitou.
- Návaznost číselných řad bez kolizí a možnost pozdější opravy starého dokladu. Každý původní doklad má zdrojové ID, číslo a soubor/otisk nebo evidovaný důvod chybění.
- Potvrzené počáteční zůstatky: pohledávky, závazky, zálohy, přeplatky, pokladny, relevantní účty a případné vypořádání brány. Převzetí zůstatku není nový příjem/výdej.
- U sporu Harrachov z etapy 0 nejdříve rozhodnout mezi evidovanou zálohou 0 a 2 000 Kč podle prvotního dokladu. Sportovní odměny nepřevádět automaticky na zákaznický přeplatek.
- Režim souběhu a kontrolu, že účetní nedostává týž případ ze Shoptetu i KIS jako dvě transakce. Vlastní migrace a vypnutí služeb zůstávají etapě 6.

**Hotovo, když:** účetní schválí počátky a hranici odpovědnosti systémů; syntetický export lze ověřit dříve, ostrý účetní přechod bez toho ne.

### E2-15 — Přejímka účetní a obsluhy

**Máme:** scénáře A01–A15 z etapy 0 a automatické testy stávajících platebních toků. Nové doklady a export jimi nejsou automaticky pokryté.

**Doplnit:**

- Souvislou syntetickou přejímku z přílohy: vydání → skutečné peníze → oprava/vratka → měsíční balík → opravená revize → obnova.
- Reprezentativní zkušební měsíc v rozsahu schváleném pro budoucí pilot, s kontrolou banky, pokladny a externě dodaných podkladů. Není nutné vytvářet testovací prodeje v produkci.
- Převzetí vzorového XLSX a PDF účetní, potvrzení importu či ručního zpracování a seznam vyřešených výjimek. Samotný technicky validní XLSX nestačí.
- Stručný postup hospodáře: vystavit, přijmout hotovost, napravit chybu, vrátit peníze, uzavřít měsíc, předat a doplnit již předaný balík.

**Hotovo, když:** účetní potvrdí, že dohodnutý rozsah zpracuje bez dohledávání částek mimo určené podklady, a hospodář zvládne postup bez vývojáře. Nevyřešené P0 brání převzetí.

## Hranice dokončení a návaznosti

- **Etapu 2 lze připravovat souběžně s etapou 1.** Ostré vystavování a předání potřebují její ověřené identity, oprávnění a obnovu; není nutné čekat na volitelné souhrnné e-maily.
- **Sklad:** v etapě 2 definovat vazbu a exportní rozhraní. Příjemky, pořizovací ceny, fyzické vratky a inventura se dokončí v etapě 3. Chybějící pořizovací cena je neznámá, nikoli nula ani prodejní cena. Dokud chybí ocenění, skladový výstup je omezený nebo dodaný externě; celý Shoptet tím ještě nelze nahradit.
- **Výdaje a odměny:** E2-09 dodá minimum pro průkazné účetní předání. Úplný oběh žádostí, rozpočty akcí, výpočty a schvalování odměn patří do etapy 5. Již existující skutečné peníze se však z exportu nesmějí ztratit jen proto, že příslušná agenda teprve vznikne.
- **Historie:** E2-14 připraví účetní pravidla převzetí; realizace přesunu patří do etapy 6. Nepoužije se nahrazovací import osob k přenosu financí.
- **Půjčované vybavení:** zůstává mimo rozsah podle zadání uživatele.

**Doporučený první budoucí balík:** uzavřít E2-01, provést přesné mapování E2-02 a schválit vzor faktury, PPD/VPD a strukturu exportu. Následuje registr dokladů a skutečných peněz, až poté hromadné generování. V této přípravě vzniklo pouze zadání; žádný krok implementace ani produkčního přechodu nebyl proveden.
