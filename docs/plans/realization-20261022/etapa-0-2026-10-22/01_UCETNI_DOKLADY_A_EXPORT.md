# Zadání účetních dokladů a exportu pro oddíl

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Verze návrhu 2, 22. 10. 2026, po potvrzení jednoduchého účetnictví uživatelem. Rozsah: neplátce DPH, prodej zboží, kurzy a kroužky, členské příspěvky, osobní platby, vratky a předání podkladů externí účetní. Základní výstup je XLSX a archiv PDF. KIS má vést provozní evidenci a průkazné podklady; účetní knihy a roční přehledy zpracovává účetní ve svém systému.

## Výchozí stav a rozhodnutí

Objednávky, členské předpisy, ruční potvrzení plateb, rodinné rozdělení úhrad a účetní klasifikace již mají implementovaný základ. Číselné řady objednávek a VS samy o sobě nejsou vydané faktury. Navržená dokumentová a exportní vrstva na tyto záznamy naváže.

Uživatel potvrdil jednoduché účetnictví. Základem předání budou podklady pro peněžní deník, knihy pohledávek a závazků a pomocné knihy majetku, které pro tento režim uvádí [Finanční správa](https://financnisprava.gov.cz/cs/dane/dane/dan-z-prijmu/ucetnictvi/obecne-informace). Export nadále odděluje předpis, doklad, skutečný peněžní pohyb a jeho přiřazení. Samotné vystavení faktury nesmí vytvořit příjem peněz.

Pro návrh z toho plynou konkrétní požadavky:

- List Penize je hlavní podklad pro peněžní deník: skutečné datum příjmu/výdeje, banka či pokladna, částka, měna, účel a účetní potvrzené členění. Datum vystavení faktury nenahrazuje datum pohybu.
- Listy Predpisy a Saldo zachycují také neuhrazené pohledávky; přijaté doklady a jejich úhrady musí umožnit sestavit přehled neuhrazených závazků.
- Do listu Penize doplnit kód členění pro jednoduché účetnictví a stav jeho ověření. Konkrétní daňové posouzení příjmů/výdajů určí účetní; automaticky je neodvozovat ze stavu „zaplaceno“ ani z toho, že jde o spolek.
- Sklad a případný další majetek mají vlastní podklady. Vydaná faktura, skladový výdej a pohyb peněz zůstávají propojené, ale nesčítají se jako tři příjmy.
- Účty MD/Dal a podvojné účtovací zápisy nejsou požadavkem této verze. Období služby se zachová pro provoz a doklady; nerozpočítává automaticky skutečně přijatou platbu do jiných měsíců.

Následující rozhodnutí jsou návrhem k potvrzení účetní:

| ID | Rozhodnutí | Navržený výchozí postup |
|---|---|---|
| U01 | Vystavovatel | Používat přesnou právnickou osobu, IČO, sídlo a účet potvrzené hospodářem. Oddíl může být jen organizační jednotkou; obchodní označení samo nestačí. |
| U02 | Čísla dokladů | Oddělit číslo objednávky, číslo dokladu a VS. Samostatné číselné řady faktur, oprav a pokladních dokladů; formát a pokračování po Shoptetu určí účetní. |
| U03 | Fakturace zboží | Po přijetí objednávky výzva k platbě. Konečný doklad při doloženém prodeji/výdeji podle schválené metodiky; přijetí peněz předem se eviduje samostatně. |
| U04 | Fakturace kurzu | Doklad vždy identifikuje konkrétní kurz a období služby. U platby předem evidence zálohy a její vypořádání; okamžik konečného dokladu určí účetní. Skutečná platba zůstává v datu přijetí, období kurzu ji neposouvá. |
| U05 | Členské příspěvky | Samostatný členský předpis a potvrzení úhrady. Skutečný členský příspěvek automaticky nepřevádět na prodej kurzu ani nevystavovat druhou pohledávku. |
| U06 | Splatnost | Navrhnout 14 dní jako běžnou výchozí hodnotu, převzatou z kontrolovaného nastavení Shoptetu; možnost jiného termínu na programu/předpisu. |
| U07 | Klasifikace | Každá položka má účetní kategorii a středisko/činnost. Daňové posouzení a rozlišení hlavní a hospodářské činnosti dodá účetní; neodvozovat je jen z názvu zboží. |
| U08 | Období a archiv | Potvrdit účetní rok, uzávěrku měsíce, odpovědné osoby a retenční plán pro jednotlivé druhy dokumentů. Nezakládat automatické mazání. |
| U09 | Zálohy a cizí měny | Zálohy na akce uživatel potvrdil; doplnit způsob předání a vyúčtování. Používání zahraničních výdajů zbývá potvrdit. Prodejní MVP v CZK; výdaj v cizí měně musí uchovat původní částku/měnu a účetní přepočet. |

## Typy dokumentů a jejich význam

| Typ | Kdy vzniká | Obsah a návaznost | Co nesmí způsobit |
|---|---|---|---|
| Potvrzení objednávky | Přijetí objednávky | Položky, kupující, příjemce služby, cena, doprava, způsob platby | Samo nepotvrzuje zaplacení ani výdej. |
| Výzva k úhradě nebo zálohová faktura | Splatná objednávka/záloha | Částka, termín, účet, původní VS, odkaz na závazek | Nesmí v exportu vytvořit druhý příjem nebo zdvojit pohledávku. |
| Faktura neplátce DPH | Schválená událost podle U03/U04 | Neměnný snímek prodeje/služby, případné započtené zálohy a doplatek | Nesmí zpětně přebírat změněnou cenu nebo adresu z katalogu/profilu. |
| Předpis členského příspěvku | Schválený plán/předpis | Člen, plátce, účel, období, splatnost, částka, stálý VS | Nezaměňovat se skutečnou bankovní úhradou. |
| Potvrzení o úhradě | Potvrzený příjem peněz | Datum, částka, metoda, rozdělení na závazky; u kurzu jméno účastníka a období | Nevytváří nový příjem, pouze dokládá existující pohyb. |
| Příjmový pokladní doklad | Skutečný příjem hotovosti | Pokladna, příjemce/platící, účel, převzal, datum, vazba na objednávku/předpis | Přepnutí objednávky na „hotově“ ještě není příjem hotovosti. |
| Výdajový pokladní doklad | Skutečný výdej hotovosti | Vratka, proplacení výdaje nebo záloha, převzal/vydal, vazby | Nesmí nahradit samotnou opravu ceny nebo doklad dodavatele. |
| Opravný doklad k faktuře | Oprava ceny, částečné/celé storno již vydaného dokladu | Původní dokument/položky, důvod, záporný nebo kladný rozdíl, autor | Vydání opravy není důkaz vrácení peněz. U neplátce jej automaticky nenazývat opravným daňovým dokladem DPH. |
| Dodací nebo předávací list | Fyzické předání/odeslání | Co, kolik, komu a kdy bylo předáno, vydávající | Nesmí být zaměněn za platbu. Kurz nemá výdej zboží. |
| Přijatý výdajový doklad | Dodavatelský nákup nebo výdaj pracovníka | Originál, dodavatel, částka, kdo zaplatil, účel, středisko, schválení | Nesmí se vystavit jako naše prodejní faktura. |
| Vyúčtování akce | Uzavření zálohy a nákladů akce | Záloha, přiložené doklady, skutečně hrazené částky, doplatek/vrácení | Již evidované účtenky nejsou při vyúčtování dalšími výdaji. |

Potvrzení pro rodiče nebo zaměstnavatele má čerpat z již existující platby a dokladu. Příjemcem služby může být dítě, odběratelem či plátcem rodič nebo organizace; tyto role mají vlastní pole.

## Datový obsah dokumentu

Účetní doklad musí umožnit určit označení, účastníky a obsah účetního případu, částku, okamžik vyhotovení a případně odlišný okamžik účetního případu. Musí existovat průkazný záznam odpovědnosti za účetní případ a zaúčtování; nemusí jít o obrázek podpisu na každé faktuře. Konkrétní návaznost schvalování v KIS a účtování u účetní je nutné dohodnout. Viz [Finanční správa, účetní doklady](https://financnisprava.gov.cz/cs/dane/dane/dan-z-prijmu/ucetnictvi/obecne-informace).

| Oblast | Požadovaná data |
|---|---|
| Identita | Interní neměnné ID, typ, číslo řady a dokladu, stav, zdrojový systém a ID, původní dokument u opravy |
| Vystavovatel | Právní název, IČO, adresa, zápis v rejstříku podle potvrzených údajů, kontakt, bankovní účet, režim DPH ke dni vydání |
| Odběratel | Jméno/název, fakturační adresa; u organizace IČO a případné DIČ; kontakt odděleně od fakturačních údajů |
| Vazby | Objednávka/předpis, plátce, interní osoba účastníka, program a období, související zálohy, platby a opravy |
| Data a termíny | Vyhotovení, účetní případ, splatnost, období služby od/do; jednotlivá data přijetí peněz samostatně |
| Položka | Neměnný popis, SKU/varianta či program, množství, jednotka, jednotková cena, sleva, konečná částka, účetní kategorie, činnost/středisko |
| Součty | Celkem, zaokrouhlení, započtené zálohy, k úhradě při vydání; aktuální stav úhrad se vede vedle původního PDF |
| Průkaznost | Kdo připravil/vydal/schválil, čas a důvod změny, verze šablony, otisk PDF a datového snímku, evidence předání účetní |

Režim „neplátce DPH“ musí být výslovný. Nulová sazba DPH není ekvivalent neplátcovství. Export nemá vyrábět fiktivní daňové základy a odpočty; případné technické daňové sloupce budou prázdné s režimem `non_vat_payer`. Rodná čísla, zdravotní údaje, hesla ani podrobné tréninkové výsledky do účetního exportu nepatří.

## Pravidla vystavení a oprav

1. Koncept lze měnit. Vydání přidělí jedinečné číslo, uloží snímek a PDF; dvojklik nebo opakovaný požadavek vrátí tentýž doklad.
2. Vydané číslo se nepoužije znovu. Chybné vydání má evidované storno/opravný postup, nikoli smazání bez stopy. Náhled nespotřebovává číslo.
3. Změna objednávky, ceny v katalogu nebo adresy osoby nesmí přepsat vydaný doklad. Oprava odkazuje na originál a přesné položky.
4. Jedna objednávka může mít více plateb, více částečných výdejů a více oprav. Součet oprav nesmí bez výslovného důvodu překročit původní množství/částku.
5. Platba, oprava dokladu, návrat zboží do skladu a uvolnění místa na kurzu jsou samostatné kroky s návazností. Poškozené vrácené oblečení se nevrací automaticky do prodejného skladu.
6. Potvrzený bankovní pohyb musí mít stabilní zdrojovou referenci. Opětovný import nebo pozdější potvrzení v jiné agendě jej nesmí započítat podruhé.
7. Rodinná úhrada je jeden příjem s více rozděleními. Roční platba proti dvanácti předpisům znamená jeden peněžní pohyb, nikoli dvanáct bankovních příjmů.
8. Přeplatek zákazníka je samostatný závazek/zůstatek. Odměna sportovce a náhradní lekce mají jinou evidenci i jednotky a nesmějí se automaticky směňovat za peníze nebo vzájemně započítávat.
9. Výdej při osobním prodeji potřebuje identifikaci vydávajícího, varianty, množství, cenu, způsob úhrady a doklad. Zachovat možnost prodeje veřejnosti bez povinného členského účtu.
10. U smíšené objednávky zboží a kurzu lze zboží vydat samostatně; služba má své období. Doprava a případná sleva se rozepíší dohledatelně, bez umělého skladového pohybu služby.
11. Testovací položky se v ostrém účetním balíku vyloučí jen podle explicitního schváleného označení; hledání slova TEST v názvu není dostatečná účetní kontrola.
12. Změny za uzavřené období se předávají jako dohledatelné doplnění/oprava s vazbou na předchozí balík. Nelze tiše přepsat již předané soubory.

## Balík pro účetní

Navržený měsíční balík `KIS_ucetni_YYYY-MM_revNN.zip` obsahuje `prehled.xlsx`, složku `doklady/` s PDF, soukromé přílohy přijatých výdajů podle dohody a `manifest.json` se seznamem souborů a SHA-256. Jde o zadání budoucího exportu; v etapě 0 se žádné ostré účetní soubory nevystavují ani neposílají.

Před generováním se volí období od/do a rozhodný čas. Export vytvoří konzistentní snímek a označí filtr, verzi formátu a revizi. Má oddělené filtry podle data účetního případu, vydání a skutečného pohybu peněz; uživatel musí vidět, který určuje zařazení daného listu. Základní režim je měsíční úplný snímek, nikoli anonymní změnový soubor. Opětovné stažení stejného balíku vrací archivovanou verzi. Nový snímek po změně dat má novou revizi.

### Listy XLSX

| List | Jeden řádek představuje | Povinné sloupce návrhu |
|---|---|---|
| Kontrola | Souhrnnou kontrolu nebo parametr | Verze formátu, ID/revize balíku, období, rozhodný čas, vytvořil, zdroj, počet řádků po listech, součty po měnách a druzích, stav kontroly, popis výjimky |
| Doklady | Jeden vydaný doklad nebo evidované storno | ID dokladu, číslo, typ, stav, zdrojové ID, číslo objednávky/předpisu, původní doklad, vystavovatel, odběratel a adresa, IČO/DIČ dle typu, vydáno, datum případu, splatnost, měna, částka, zálohy, doplatek při vydání, VS, režim DPH, cesta PDF, otisk |
| Polozky | Jednu položku dokladu | ID položky, ID dokladu, zdrojová položka, pořadí, druh zboží/služba/příspěvek/doprava, popis, SKU, varianta, množství, jednotka, jednotková cena, sleva, celkem, účetní kategorie, středisko/činnost, období služby, interní ID účastníka |
| Penize | Jeden potvrzený peněžní pohyb | ID pohybu, zdrojová reference, datum pohybu, datum zaevidování, příjem/výdej, důvod, částka se znaménkem, měna, bankovní účet/pokladna, metoda, VS, plátce/příjemce, protistrana dle potřeby, původní pohyb u vratky, kód členění pro jednoduché účetnictví nebo odkaz na jeho rozpis, stav ověření členění, potvrzující osoba, stav |
| Parovani | Jedno rozdělení peněz na závazek nebo jeho zrušení | ID rozdělení, ID pohybu, cílový typ a ID, ID dokladu pokud existuje, částka se znaménkem, měna, datum, důvod, odkaz na rušené rozdělení |
| Predpisy | Jeden členský nebo jiný provozní předpis | ID, typ, člen, plátce, účel, období, datum vzniku, splatnost, původní částka, opravy, aktuální částka, úhrady k rozhodnému času, zůstatek, stálý VS, účetní kategorie, související doklad, stav |
| Saldo | Jeden otevřený závazek nebo nepřiřazený zůstatek plátce | Typ a ID, osoba/plátce, doklad/předpis, splatnost, částka, přiřazené úhrady, zbývá, přeplatek odděleně, měna, stáří po splatnosti, rozhodný čas |
| Vydaje | Jeden přijatý výdajový doklad | Interní ID, číslo dodavatele, dodavatel, IČO dle podkladu, datum dokladu/případu/splatnosti, účel, středisko, akce, částka/měna originálu, kurz a datum/zdroj kurzu, částka CZK, kdo zaplatil, věřitel, uhrazeno a zbývající závazek k rozhodnému času, stav schválení/proplacení, vazba na pohyb, příloha a otisk |
| Sklad | Jeden skladový pohyb | ID, datum, sklad, varianta/SKU, typ příjem/výdej/vratka/inventura, množství se znaménkem, jednotka, pořizovací hodnota a metoda ocenění, návazný doklad/objednávka, autor, důvod, stav ověření ceny |

Názvy jsou stabilní, bez slučování buněk uvnitř dat. XLSX obsahuje tabulky, filtry a zamrzlé záhlaví. Součty mají vlastní řádky na listu Kontrola; nesmějí se tvářit jako další účetní záznamy. Čísla jsou číselná, data skutečná data Excelu, VS/IČO/ID/SKU text včetně úvodních nul. Uživatelský text začínající `=`, `+`, `-`, `@` se zapisuje jako text, nikoli jako vzorec. Peníze se počítají přesně v haléřích; množství a jednotkové ceny mohou mít větší desetinnou přesnost s výslovným zaokrouhlením řádku.

Má-li jedna platba více účetních členění, její rozpis uvede ID pohybu, kód členění a dílčí částku. Součet rozpisu musí odpovídat pohybu; samotný bankovní příjem zůstane na listu Penize jen jednou. Rozpis účetního členění není totéž jako přiřazení platby jednotlivým dětem či předpisům. U výdajů se odlišuje dluh vůči dodavateli od dluhu vůči pracovníkovi, který jej již zaplatil ze svého; stejný výdaj nesmí vytvářet oba závazky současně v plné výši.

Listy se nesčítají napříč: faktura, příjem a jeho rozdělení jsou tři pohledy na jednu událost. Export musí toto označit také v popisu listů. Předpis a z něj vydaný doklad odkazují na jediný ekonomický závazek; saldo jej nesmí zahrnout dvakrát.

Skladové ocenění není dnes kompletní. Dokud nebudou příjemky, pořizovací ceny a inventura, list Sklad musí uvést omezení a chybějící ocenění. Nesmí nahradit pořizovací cenu prodejní cenou ani vykazovat neznámou hodnotu jako nulu. Před ostrým nahrazením Shoptetu je skladový list nutné dokončit, pokud účetní na těchto datech staví skladovou evidenci.

### Kontrolní rovnice a výjimky

- Součet položek dokladu plus explicitní zaokrouhlení odpovídá částce dokladu; rozpis slev se sejde na haléř.
- Ke každému pohybu platí: původní příjem minus vrácená část a platná rozdělení = nepřiřazený zůstatek, při zohlednění případných přesunů a jejich auditní stopy.
- Pro každý závazek: původní částka plus opravy minus platné úhrady = zbývá. Záporný výsledek se vykáže jako přeplatek, nikoli zatajený záporný dluh.
- Pokladna: počáteční stav plus příjmy minus výdaje = konečný stav; fyzická hotovost má samostatné potvrzení inventury.
- Vypořádání brány odděluje hrubé platby, vratky, poplatky a čistý převod na banku. Čistý převod není dalším prodejem. Dokud se brána nepoužívá, tento tok nemusí být aktivní.
- Bankovní převody mezi vlastními účty a přesuny pokladna–banka nejsou prodej. Neidentifikovaná platba zůstává výjimkou, nesmí být automaticky přiřazena jen podle shodné částky.
- Každý vydaný doklad v balíku má PDF a platný otisk. Nezařazené položky, duplicitní reference, chybějící přílohy a nesedící součty jsou viditelné výjimky.
- Neshoda peněžních součtů, duplicitní doklad, chybějící PDF nebo chybějící povinná klasifikace blokují označení balíku jako připraveného k předání. Pracovní export lze stáhnout s výrazným stavem NEÚPLNÝ.

## Přístupy a předání

Hospodář vystavuje doklady, připravuje a uzavírá balík. Běžný trenér vidí jen jemu potřebné podklady; nemění vydané doklady. Účetní dostane omezený balík nebo čtecí přístup bez práv ke členství a zdravotním údajům. Žádné automatické rozesílání v první verzi. Záznam předání obsahuje kdo, kdy a kterou revizi předal; přijetí účetní a případné připomínky mají vlastní stav. Předání není totéž jako potvrzení zaúčtování.

Vystavené dokumenty a účetní snímky musí být v soukromém úložišti a záloze včetně možnosti obnovy. Kopie v e-mailu sama není archiv. Odstranění přístupu člena nesmí zničit oprávněně uchovávané účetní záznamy; dobu uchování potvrdí účetní podle typu dokladu.

## Přejímací scénáře pro budoucí implementaci

Všechny příklady jsou syntetické a mají se ověřovat v izolovaném prostředí.

| ID | Příklad | Očekávaný výsledek |
|---|---|---|
| A01 | Tričko 600 Kč, zaplaceno hotově při převzetí | Jeden prodejní doklad, jeden příjem 600 Kč, jeden výdej 1 ks, vazby mezi nimi; žádný druhý příjem z potvrzení. |
| A02 | Rodič pošle 2 500 Kč, předpisy dětí 1 000 + 1 200 Kč | Jeden příjem, dvě rozdělení, přeplatek 300 Kč; žádná automatická změna členství jiného dítěte. |
| A03 | Roční platba na 12 měsíčních předpisů | Jeden peněžní pohyb, 12 rozdělení; sleva a období podle schváleného plánu; původní VS beze změny. |
| A04 | Objednávka 2 trička po 600 Kč, vráceno jedno | Oprava −600 Kč, samostatná vratka 600 Kč po skutečném vrácení peněz, návrat 1 ks po kontrole stavu; druhý kus zůstává prodaný. |
| A05 | Zrušení nezaplacené objednávky | Uvolnění rezervace, žádný fiktivní příjem ani vratka; již vydaný doklad vyřešen opravou. |
| A06 | Kurz zaplacen v prosinci na leden až červen | Uchované datum platby i období služby; záloha/vypořádání dle metodiky účetní, bez přepsání platby na leden. |
| A07 | Zboží 600 Kč + kurz 4 100 Kč + doprava 150 Kč | Položkově 4 850 Kč, zboží lze vydat samostatně, kurz nevytváří sklad; odlišné účetní kategorie. |
| A08 | Změna adresy rodiče po vydání faktury | Původní PDF a otisk shodné; změna dokladu pouze evidovaným opravným postupem. |
| A09 | Dvojklik na vydání, opakované načtení bankovní platby a stažení exportu | Jeden doklad, jeden pohyb, při stažení stejné revize stejný archiv; žádné nové číslování. |
| A10 | Oprava říjnového dokladu po předání října | Opravný doklad a nový evidovaný balík/doplnění; původní předání dohledatelné. |
| A11 | Záloha pracovníkovi 2 000 Kč, uznané náklady 1 700 Kč, vráceno 300 Kč | Jeden výdej zálohy a jeden příjem jejího zbytku; vyúčtování nezdvojí účtenky, zůstatek zálohy 0. |
| A12 | Odměna sportovce 900 Kč, přeplatek rodiče 300 Kč, dvě náhrady lekce | Tři oddělené evidence; bez výslovného rozhodnutí nevznikne peněžní zůstatek 1 200 Kč ani automatický zápočet. |
| A13 | Platba kartou 1 000 Kč, poplatek 20 Kč, převod brány 980 Kč | Hrubá platba 1 000, náklad/poplatek 20, převod 980 a průkazné vypořádání; obrat se nezdvojnásobí. |
| A14 | VS `0000000013`, IČO s úvodní nulou, text začínající `=` | XLSX zachová textové identifikátory a nespustí vložený vzorec. |
| A15 | Cena pořízení ve skladu chybí | Výjimka ocenění; nevznikne falešná nulová skladová hodnota. |

## Implementační návaznosti

1. Potvrdit U01–U09, jeden skutečný vzor účetního předání a požadované číselné řady.
2. Navrhnout neměnný model dokladů a vazeb. Čerpat z existujících objednávek/předpisů a účetního zařazení; zachovat všechny VS.
3. Doplnit vystavení PDF, opravy a přístupová pravidla. Doplnit potřebné fakturační údaje zákazníka a firemního odběratele.
4. Sjednotit identitu peněžních pohybů a rozdělení, export a kontrolní rovnice. Stávající `payments`, rodinné příjmy a obecné klubové příjmy vyžadují explicitní mapování; prosté spojení tabulek nestačí.
5. Navázat položkové vratky, osobní prodej, pořizovací ceny a inventuru; teprve potom označit skladovou část za úplnou.
6. Účetní převezme syntetický vzor a následně schválený zkušební měsíc; součty musí souhlasit s bankou, pokladnou, doklady a skladem.

Vlastní účetní API, ISDOC a přímé účtování nejsou podmínkou první verze, pokud účetní akceptuje dohodnuté XLSX + PDF. Vzor reálně předávaného XLSX ani účetní program zatím nebyly potvrzeny; výše je nový navržený kontrakt, nikoli tvrzení o přesné kompatibilitě s jejím importem.

## Opora ve zdrojovém kódu

Kontrolovaný zdroj: `origin/main` na `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. Odkazy míří na tuto neměnnou revizi, nikoli na starší pracovní kopii.

- [Objednávky, platby, storno a refundace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php)
- [Účetní klasifikace a VS](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_number_series.php)
- [Rodinné příjmy a jejich rozdělení](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/member_payer_receipt.php)
- [Klubové finanční zůstatky](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_finance.php)
- [Existující evidence přijatých výdajů](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/uctenky/uloz.php)
