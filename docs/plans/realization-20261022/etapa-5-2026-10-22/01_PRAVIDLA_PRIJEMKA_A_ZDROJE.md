# Etapa 5 — pravidla, přejímka a zdrojové podklady

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 5](00_ETAPA_5_PLAN.md), 22. 10. 2026. Pravidla a scénáře určují budoucí chování. Při přípravě se neprováděly výplaty, importy ani zápisové testy.

## 1. Významy a rozdělení odpovědností

| Objekt | Význam | Nesmí být zaměněn za |
|---|---|---|
| Původní doklad | Co bylo nakoupeno/poskytnuto, komu a za kolik | Samotný důkaz úhrady klubem |
| Žádost o proplacení | Požadavek konkrétní osoby a její podklady | Automaticky schválený závazek |
| Uznaná částka | Výsledek věcného schválení konkrétní verze | Celou částku dokladu nebo skutečnou výplatu |
| Věřitel | Komu oddíl dluží z konkrétního důvodu | Osobu, která nahrála soubor |
| Skutečný plátce nákupu | Kdo zaplatil dodavateli | Vždy klubovou pokladnu jen proto, že doklad říká „hotově“ |
| Příjemce proplacení | Ověřená osoba/protistrana, které se nárok vypořádává | Automaticky prvního rodiče, autora či držitele účtu v seznamu |
| Záloha vedoucímu | Doložené předání prostředků ke konkrétnímu účelu | Již doloženou spotřebu celé částky |
| Dodavatelská záloha | Platba dodavateli před konečným vyúčtováním | Svěřenou hotovost trenérovi nebo druhou plnou úhradu konečné faktury |
| Záloha účastníka kurzu | Přijaté peníze od zákazníka podle etapy 2 | Stejný typ záznamu jako záloha poskytnutá pracovníkovi |
| Vyúčtování | Doklady, uznání a výpočet zůstatku konkrétního případu | Samo provedenou vratku/doplatek |
| Výkaz činnosti | Doložený rozsah práce/účasti a jeho schválenou verzi | Automatickou peněžní odměnu |
| Vypočtená odměna | Výsledek pravidla a doložených vstupů | Bez dalšího čistou částku k převodu |
| Schválená odměna | Přijatý nárok a podklad pro účetní/úhradu | Doklad, že již bylo zaplaceno |
| Platební seznam / příkaz | Přípravu úhrady | Provedený peněžní pohyb |
| Skutečná úhrada | Doložený pohyb z banky/pokladny a jeho příděly | Další nákup vedle původní účtenky |
| Účetní předání | Neměnnou verzi balíku a stav převzetí | Potvrzení úplného zaplacení všech závazků |

Etapa 5 vlastní žádosti, schválení a podklady. Etapa 2 vlastní společné peněžní pohyby, doklady, saldo a účetní balík. Jediný ekonomický případ může být zobrazen v obou modulech, ale nesmí tím vzniknout druhý závazek nebo druhá platba.

## 2. Minimální datové a provozní požadavky

### Výdaj a jeho rozdělení

Minimální záznam: stabilní ID případu; původní systém/ID; dodavatel; číslo/typ dokladu; data případu, vystavení, přijetí, splatnosti a zápisu; původní částka/měna; potvrzený přepočet, pokud je potřebný; účel; akce/středisko; skutečný plátce; věřitel; verze přílohy; stav kontroly a schválení. Osoba bez vlastního uživatelského účtu musí být evidovatelná bez založení fiktivního přihlášení.

Rozdělení zachytí uznanou/neuznanou část a jednotlivé účely. Rozdělení mezi akce není druhá platba. Nahrání stejného souboru nebo podobného dokladu vyvolá kontrolu duplicity; skutečně odlišné nákupy stejné částky zůstávají možné. Doklad a platba mají samostatnou identitu.

Každá podstatná změna částky, věřitele, účelu, přílohy či platebních údajů po schválení musí zneplatnit příslušné schválení nebo založit řízenou opravu. Schválení se vztahuje na konkrétní verzi, nikoli navždy na ID editovatelné účtenky.

### Stavy, které se nesmějí sloučit do jednoho přepínače

| Evidence | Cílové rozlišení |
|---|---|
| Podklady | Rozpracované, předložené, chybí podklad, připravené ke kontrole |
| Rozhodnutí | Čeká na posouzení, vráceno, schváleno, částečně schváleno, zamítnuto, nahrazeno opravou |
| Závazek | Otevřený, částečně vypořádaný, vypořádaný, zrušený/opravovaný s důvodem |
| Záloha | Požadována, schválena, skutečně předána, částečně vyúčtována, vyúčtování schváleno, finančně vypořádána |
| Výkaz/odměna | Rozpracováno, předloženo, schválená verze, předáno účetní, určeno k vypořádání |
| Platba | Připravená není provedená; skutečná platba má doloženou referenci a opravy |
| Předání účetní | Připraveno, předáno, vráceno k doplnění, převzato; každá revize samostatně |

Nejde o požadavek přidat všechny názvy do jedné databázové tabulky. Implementace musí zachovat tyto významy s využitím existujících modelů. Měsíční balík může být převzatý, i když obsahuje řádně popsané otevřené závazky. Vyúčtování akce může být schválené, i když zbývá skutečně vrátit peníze; taková akce ještě není finančně vypořádaná.

### Záloha a vyúčtování

Každé skutečné předání: vlastní ID, příjemce, předávající, účel/akce, částka a měna, datum, termín vyúčtování a reference peněžního pohybu. Jeden text u akce se nerozdělí automaticky na osoby bez ověření.

Každé čerpání: konkrétní doklad, uznaná částka, osoba, konkrétní záloha a datum. Součet přídělů nepřekročí uznanou část dokladu. Výdaj klubovou kartou se neodečte ze svěřené hotovosti. Více příjemců má oddělené saldo, i když jde o jednu akci.

Pro schválený případ lze provozní saldo příjemce vyjádřit:

**Předané zálohy + skutečné doplatky − uznané výdaje přiřazené k tomuto vypořádání − skutečně vrácené částky.**

Kladná hodnota znamená prostředky zbývající k vrácení/vyúčtování, záporná schválenou částku k doplacení. Neuznané či dosud neposouzené výdaje jsou zvlášť; vzorec je nesmí schovat. U samostatné žádosti o proplacení vlastních peněz mimo zálohu použít samostatný závazek. Stejný doklad nesmí být zároveň plně proplacen z obou cest.

Záloha dodavateli potřebuje vlastní vazbu na konečný doklad a jeho doplatek. Vyúčtování zaplacené zálohy nevytváří druhý peněžní výdaj v plné hodnotě konečné faktury. Konkrétní účetní členění a data se přebírají ze schválené metodiky etapy 2.

### Schválení činnosti a odměny

- Výkaz uvádí skutečnou činnost, osobu, datum, délku/jednotku, akci, původ a verzi. Připsání trenéra k lekci je vstup ke kontrole, nikoli automatický důkaz odpracování celé délky.
- Pravidlo odměny má účinnost a verzi: hodinová sazba, sazba za uznaný trénink, paušál nebo jiný schválený způsob. Souběh pravidel je výslovný.
- Schválený výpočet uchovává vstupy, podklady, sazbu, zaokrouhlení, částku a schvalovatele. U sportovců se využije stávající uložený počet/částka a doplní ochrana a podrobné vazby.
- Další změna docházky, vykázaného času nebo sazby založí rozdíl k posouzení. Nepřepíše minulou schválenou a předanou částku.
- Výplata navazuje na schválený nárok a účetní vypořádání. Smluvní povaha, případné odvody a skutečná částka příjemci vycházejí z potvrzených podkladů účetní. Neurčujeme zde sazby daní, odvody ani právní režim konkrétního pracovníka.
- Vyplacené historické odměny se nevyplácejí podruhé. Nejasný příznak staré výplaty je případ k ověření, nikoli pokyn k novému odeslání peněz.
- Automatické započtení sportovní odměny proti dluhu člena nebo převod na rodinný přeplatek se nezavádí. Případné budoucí započtení potřebuje samostatný schválený postup a odpovídající podklady.

## 3. Modelové případy pro obsluhu

Částky jsou syntetické příklady provozního chování. Neurčují účetní či daňové zařazení konkrétního výdaje.

| Případ | Požadovaný výsledek |
|---|---|
| Trenér zaplatil ze svého 1 200 Kč; uznáno 1 000 Kč; proplaceno 600 Kč | Jeden původní doklad, 200 Kč neuznáno, jeden nárok trenéra 1 000 Kč, jedna doložená úhrada 600 Kč a zůstatek 400 Kč. Žádný druhý neuhrazený dluh dodavateli za stejnou uhrazenou část. |
| Vedoucí dostal zálohu 5 000 Kč; uznané čerpání 4 200 Kč; vrátil 800 Kč | Doložené předání, přiřazené doklady a doložená vratka. Saldo nula až po skutečném vrácení. Zobrazený rozdíl 800 Kč bez dokladu o vrácení nestačí. |
| Vedoucí dostal 2 000 Kč; uznané výdaje v jeho vypořádání činí 2 300 Kč | Vyúčtování požaduje doplatek 300 Kč. Jeho schválení peníze nevyplatí; saldo se vypořádá po doloženém doplatku. Stejných 300 Kč se nevytvoří znovu v samostatném proplacení. |
| Vedoucí drží hotovostní zálohu 2 000 Kč a klub zaplatil kartou hotel 1 500 Kč | Hotel je nákladem případu, ale nespotřeboval vedoucího hotovost; jeho záloha zůstává 2 000 Kč před dalším čerpáním. |
| Dodavatelská záloha 3 000 Kč a konečná faktura 5 000 Kč | Vazba na již zaplacenou zálohu, zbývá 2 000 Kč. Konečná faktura se neoznačí celá za další úhradu 5 000 Kč. |
| Účetní podklad trenéra obsahuje 8 hodin při 250 Kč/h | Výpočet 2 000 Kč je schvalovaný podklad. Částku skutečně k převodu určí potvrzený režim a účetní vypořádání; systém ji neoznačí automaticky za čistou výplatu. |
| Sportovec má schválených 10 tréninků po 30 Kč; doložená výplata je 200 Kč | Schválený nárok 300 Kč a zůstatek 100 Kč. Jediný příznak ano/ne nestačí. Pozdější změna na 11 tréninků vytvoří rozdíl 30 Kč k posouzení. |
| Stejná odměna je znovu předána účetní nebo znovu načten bankovní výpis | Stejný zdrojový nárok ani bankovní pohyb se nevytvoří podruhé. Technické opakování není nové schválení nebo platba. |

Pohyby mezi klubovou pokladnou a svěřenými prostředky musí být v modelu peněz propojené. Pokud se vede samostatná evidence svěřené hotovosti, předání a její pozdější spotřeba nejsou dvě nezávislé platby dodavateli. Celkový pohled na peníze a jejich účetní členění stanoví E2-02/E2-07/E2-09; tato etapa dodává doklady a vypořádání, nikoli druhý peněžní deník.

## 4. Kontrakt předání do etapy 2

| Zdroj etapy 5 | Předávaná data | Cíl a kontrola |
|---|---|---|
| Schválený výdaj | ID/verze, dodavatel, věřitel, plátce, data, částky, účel, uznané rozdělení, přílohy | `Doklady`, `Vydaje`, případně položky; jeden závazek, žádná druhá kopie původní účtenky |
| Proplacení či výplata | ID pohybu, skutečné datum, částka/měna, účet/pokladna, příjemce, důkaz a příděly | `Penize`, `Parovani`, `Cleneni_penez`; součet přídělů nepřekročí pohyb ani otevřené nároky |
| Záloha vedoucímu | ID, příjemce, účel, předání, termín, vazba na peníze | Detail vypořádání a `Saldo`; neprezentovat celou zálohu jako doložené nákupy |
| Vyúčtování zálohy | ID/verze, uznané doklady, příděly, vratky/doplatky a zůstatek | `Vydaje`, `Saldo` a příloha; doklady nesčítat znovu jako nové peníze |
| Schválená odměna | ID/verze, osoba, podklad, období, pravidlo, částka a stav účetního posouzení | Příloha a příslušný nárok/závazek podle potvrzené metodiky; faktura i výkaz nezdvojí závazek |
| Oprava | Vazba na původní ID/verzi, důvod, autor, účinnost, rozdíl a přidružené doklady | Nová revize balíku E2-12; starý obsah zůstane dostupný |
| Otevírací stav | Původní zdroj a důkaz, protistrana, částka a rozhodné datum | Počáteční saldo, nikoli nový příjem/výdaj měsíce převodu |

Hospodář před předáním zkontroluje chybějící podklady, nevyřešené měny, neznámé příjemce, nepřiřazené platby, duplicity, zálohy po termínu a rozpory proti minulému období. Otevřená doložená položka je legitimní obsah salda; neznámá položka se musí přiznat a vyřešit nebo výslovně předat jako výjimka.

Zdrojem pravidel pro knihy jednoduchého účetnictví jsou metodika účetní a platné předpisy. Finanční správa uvádí peněžní deník, knihu pohledávek a závazků a pomocné knihy majetku; proto návrh odděluje peníze od neuhrazených nároků a jiných podkladů. [Finanční správa](https://financnisprava.gov.cz/cs/dane/dane/dan-z-prijmu/ucetnictvi/obecne-informace). Konkrétní účetní členění jednotlivých záloh a odměn zůstává k potvrzení, bez dosazování domnělého daňového režimu.

## 5. Přejímací scénáře

Budoucí zápisové ověření patří do izolovaného prostředí se syntetickými lidmi, bankovními referencemi a doklady. Ostré platby, e-maily, importy a plánovače zůstávají mimo tyto scénáře. Každý protokol uvede revizi, prostředí, zkoušejícího, očekávaný/skutečný výsledek, důkaz a případnou závadu. P01–P40 nejsou výsledky testů provedených při přípravě.

| ID | Úkol | Scénář | Očekávaný výsledek |
|---|---|---|---|
| P01 | E5-01 | Trenér předkládá vlastní proplacení, hospodář vlastní odměnu a zastupuje jiná osoba | Každý úkon má oprávněného vykonavatele; vlastní nárok schválí jiný pověřený člověk nebo předem definovaná doložená výjimka. |
| P02 | E5-01 | Dva schvalovatelé rozhodují o stejné verzi; po schválení se změní částka/příjemce | Žádný dvojí závazek; rozhodnutí je vázané na verzi a podstatná změna vyžádá nové posouzení. |
| P03 | E5-01/14 | Přímý POST na hromadné sazby, uzavření, označení výplaty a mazání bez role | Server odmítne akci, i když je uživatel přihlášený a zná URL. Ochrana není závislá na zobrazení menu. |
| P04 | E5-02 | Stejná akce v kalendáři a provozních financích; shodná ID jiné akce | Použije se schválená explicitní vazba; příjmy, náklady a účast se nespojí podle názvu nebo náhody ID. |
| P05 | E5-02 | Akce je zrušená nebo sportovně ukončená, ale má náklady a otevřenou zálohu | Finanční záznamy zůstanou; přehled ukazuje otevřené vypořádání a neoznačí je za hotové podle kalendáře. |
| P06 | E5-03 | Účtenka klubovou kartou, vlastní hotovostí a neuhrazená faktura | Každá má správného plátce/věřitele a samostatný stav platby; autor nahrání se nepovažuje automaticky za příjemce. |
| P07 | E5-03 | Stejná účtenka nahraná dvakrát, podobný legitimní nákup a nečitelný sken | Návrh kontroly duplicity bez automatického mazání; nečitelný doklad má úkol k doplnění. |
| P08 | E5-03 | Jeden doklad na dvě akce, osobní část a skladové oblečení | Součty částí souhlasí, osobní část neproplácena bez rozhodnutí; příjemka nezdvojí výdaj a úhrada nepředstírá příjem zboží. |
| P09 | E5-03 | Doklad v cizí měně, změna kurzu a rozdíl data případu/úhrady/zápisu | Původní měna a doložený přepočet zachovány; historická částka se dnešním kurzem nepřepočítává a data mají odlišný význam. |
| P10 | E5-04 | Žádost 1 200 Kč, uznáno 1 000 Kč, vrácení k doplnění či zamítnutí části | Rozhodnutí obsahuje důvod, verzi a uznanou část; zamítnutí nevytvoří platbu ani ztrátu původního dokladu. |
| P11 | E5-04/07 | Z uznaných 1 000 Kč vyplaceno 600 Kč, později 400 Kč | Zůstatek 400 Kč po první platbě, poté nula; skutečné dvě reference a žádné přeplacení opakováním požadavku. |
| P12 | E5-04/07 | Nákup již zaplatil pracovník; jeden bankovní převod hradí více jeho dokladů | Nezůstane duplicitní plný dluh dodavateli; součet přídělů souhlasí s jediným převodem a jednotlivými zůstatky. |
| P13 | E5-05 | Schválení zálohy bez předání; více skutečných předání dvěma vedoucím | Schválení nevytvoří hotovost; každé předání má příjemce a referenci, salda jsou oddělená. |
| P14 | E5-05 | Opakované uložení předání a změna osoby držící prostředky | Nevznikne druhá záloha; převod mezi osobami má vlastní doloženou návaznost, původní příjemce se nepřepíše. |
| P15 | E5-06 | Záloha 5 000 Kč, doklady 4 200 Kč a postupné vrácení 500 + 300 Kč | Po schválení zbývá vrátit 800 Kč, po první vratce 300 Kč, po druhé nula; uzavření čeká na doložení. |
| P16 | E5-06 | Záloha 2 000 Kč, uznáno 2 300 Kč, část dokladů neuznána | Schválený doplatek je přesně podle uznané části; neuznané položky zůstávají vysvětlené, nejsou skrytě započtené. |
| P17 | E5-06 | Klubová karta, hotovostní záloha a vlastní výdaj jiného trenéra na stejné akci | Každý výdaj spotřebuje správný zdroj; klubová karta ani cizí vlastní výdaj automaticky nesníží svěřenou hotovost. |
| P18 | E5-06/07 | Stejná účtenka ve dvou zálohách; dodavatelská záloha a konečná faktura | Příděly nepřekročí uznanou část; dodavateli se doplatí jen zbývající část bez druhé plné úhrady. |
| P19 | E5-06/14 | Uzavření s nevypořádaným rozdílem, poté pokus o editaci/smazání/přepojení účtenky | Kontrola nepustí finanční uzavření s nevysvětlenými penězi; po uzavření jen auditovaná opravná verze. |
| P20 | E5-07 | Připravený příkaz není proveden, bankovní platba selže nebo je vrácena | Stav není „vyplaceno“ jen podle přípravy; návrat peněz a příděly mají dohledatelnou opravu salda. |
| P21 | E5-07 | Hotovostní výplata, změna účtu příjemce a opětovný import téhož pohybu | Skutečné převzetí má důkaz, nový účet je ověřen, jedna reference nezpůsobí dvojí výdaj. |
| P22 | E5-08 | Výkaz s tréninky, další činností a pozdě přepsaným papírem | Rozlišena skutečná činnost a zápis; každá položka má původ, druh, délku a příslušné období. |
| P23 | E5-08 | Trénink znovu jako další činnost, dva legitimní trenéři a kratší záskok | Duplicita a souběh jsou k posouzení; práce dvou lidí se nesmaže jako duplicita, záskok nedostane automaticky plnou délku. |
| P24 | E5-08/14 | Výkaz je vrácen k opravě, schválen, následně někdo odstraní zdrojovou činnost | Oprava má novou verzi; odstranění přes starší vlastní výkaz nepoškodí schválený podklad ani jeho vazby. |
| P25 | E5-09 | Dvě sazby s různou účinností v jednom měsíci a souběh paušálu | Částka podle datovaného pravidla; souběh není automaticky násobený a minulá sazba se tiše nepřepíše. |
| P26 | E5-09 | Schválený výkaz dodavatele a jeho faktura za stejnou práci | Jeden závazek s vazbou na oba podklady; faktura nevytvoří druhou odměnu. |
| P27 | E5-09 | Účetní vrátí podklad a pak potvrdí vypořádání s odlišnou částkou příjemci | Původní schválení a účetní výsledek rozlišeny; hrubý podklad se nepovažuje automaticky za čistou výplatu. |
| P28 | E5-09/07 | Opakované předání odměny a částečná skutečná výplata | Stejné zdrojové ID nevytvoří druhý nárok; správný otevřený zůstatek a doložené pohyby. |
| P29 | E5-10 | Více soupisek, překryv odměnových období, budoucí/neuzavřený trénink | Stejná účast se nenásobí; překryv je odmítnut nebo řízeně vyřešen, neověřená skutečnost nevstupuje do schválené odměny. |
| P30 | E5-10 | Hromadná změna sazby uprostřed otevřeného období | Náhled a účinnost; již vzniklé schválené nebo historické částky se nepřecení bez výslovné opravy. |
| P31 | E5-10 | Historické uzavřené období bez uloženého souhrnu a doplnění staré docházky | Zůstane k ověření; živý přepočet se automaticky nezmění na nový nárok k výplatě. |
| P32 | E5-10/14 | Opakované uzavření, změna sazby nebo datumů již vyplaceného období | Původní schválená verze chráněna; každá cesta nabídne řízenou opravu, nikoli přepsání historie. |
| P33 | E5-10/07 | Nárok 300 Kč, vyplaceno 200 Kč a příjemcem je oprávněný zástupce sportovce | Sportovec, nárok, příjemce a převzetí rozlišeny; zbývá 100 Kč, bez automatického započtení proti příspěvku. |
| P34 | E5-11 | Otevřená jízda, chybný tachometr a dodatečný zápis z papíru | Validní průběh a skutečný čas; chyba zůstane k opravě, pozdní zápis nepředstírá dnešní cestu. |
| P35 | E5-11 | Palivo hrazené klubem a navržená náhrada stejného výdaje | Duplicitní požadavek je rozpoznán; případné jiné oprávněné složky cesty mají vlastní schválené pravidlo. |
| P36 | E5-12 | Zastupování hospodáře, záloha po termínu a neúspěšné upozornění | Náhradník vidí podklad, vlastníka a další krok; nedoručení vytváří dohledatelný úkol, ne tiché vyřízení. |
| P37 | E5-13 | Měsíční balík obsahuje otevřenou zálohu, nezaplacený závazek i skutečné výplaty | Správné počáteční/konečné saldo; otevřený případ není skryt, závazek a peněžní pohyb se nesčítají jako dva výdaje. |
| P38 | E5-13 | Pozdní účtenka, oprava klasifikace a opakované stažení předaného měsíce | Původní balík neměnný; nová revize uvádí rozdíly a návaznosti, účetní ví, co nahrazuje/přebírá. |
| P39 | E5-14 | Soukromá příloha cizího případu, odebraná role a obnova archivu v testu | Přístup odmítnut i přímým URL; obnova zachová originály, verze, schválení a vazby bez ostrého odesílání. |
| P40 | E5-15/16 | Modelový měsíc s otevíracími stavy, rozporem typu Harrachov, historickou odměnou a výpadkem | Rozpor nemigruje jako jistota; vyplacené nároky se nevyplatí znovu, papír se doplní jednou a obsluha s účetní vysvětlí celý měsíc. |

Migrační část P40 a ověření obnovy se uskuteční v příslušné samostatně autorizované realizaci. Při předání tohoto zadání se ověřuje jejich připravenost, nikoli předstíraný zápisový průchod.

## 6. Výstupní brány

1. **Připraveno k implementaci balíku:** odpovědnosti a pravidla potvrzené pro daný rozsah, známý zdroj dat, návaznosti a přejímka. Tento plán sám není autorizací implementace.
2. **Převzato v testu:** scénáře používané agendy prošly na konkrétní revizi. Existující ochrany se využijí; nová vrstva se nevydává za funkční pouze na základě názvu modulu.
3. **Připraveno k pilotu:** ověřené otevřené stavy, smluvní/metodické podklady, obsluha a její zástup, návaznost na etapu 2 a postup při výpadku.
4. **Provozně převzato:** po samostatně schváleném pilotu prokázán běžný měsíc, opravy a předání účetní. Bankovní automatizace nejsou podmínkou, doložení skutečných peněz ano.
5. **Papír/Evidence mohou přestat být hlavní evidencí:** etapa 6 potvrdila převod, zůstatky, archiv a okamžik změny. Originály a původní doklady se nelikvidují samotným přepisem.

## 7. Zdrojové podklady

Zdrojové odkazy míří na kontrolovanou revizi `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. Uvedené chybějící kontroly se týkají popsaných obsluh; nebyl prováděn pokus o produkční změnu ani nový úplný bezpečnostní audit.

| Značka | Podklad | Co dokládá / omezení |
|---|---|---|
| Z1 | [Formulář účtenky](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/uctenky/formular.php), [uložení](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/uctenky/uloz.php), [odstranění](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/uctenky/smazat.php) | Existují čtyři způsoby platby, kategorie, akce/vozidlo, soukromý soubor, transakční uložení a audit. Tyto cesty nemají úplný postup schválení/proplacení ani kontrolu účetního předání. |
| Z2 | [Uložení akce](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/udalosti/uloz.php), [vyúčtování](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/udalosti/vyuctovat.php), [uzavření](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/udalosti/uzavrit.php), [odstranění](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/udalosti/smazat.php) | Jedna zálohová částka a text osoby, rozdíl proti `hotove_tym`; uzavření mění stav bez ověření vratky/doplatku. Běžná editace může měnit stav i zálohu. |
| Z3 | [Klubový kalendář](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_calendar.php), [klubové události](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_event.php) | Sportovní události a jejich registrace používají vlastní model. Automatická identita s `ucto_udalosti` není doložena; propojení musí mít explicitní mapu. |
| Z4 | [Klubové finance](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_finance.php), [jejich popis](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/CLUB_FINANCE_IMPLEMENTATION.md), [zadání etapy 2](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md) | Příjmy, příděly a klubové finanční kontroly již existují. Neznamenají samy implementované výdajové úhrady všech agend. |
| Z5 | [Další činnost](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/nova_cinnost.php), [vlastní výkaz](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/vypis_vykazu.php), [souhrn výkazů](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/prehled_vsech_vykazu.php) | Tréninky a další činnost, validace zápisu, měsíční součty, XLSX. Export je živý přehled; vlastní mazací cesta další činnosti neřeší schválenou odměnovou verzi. |
| Z6 | [Přehled odměn](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/prehled_kreditu.php), [správa období](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/sprava_sportovec_obdobi.php) | Uzavření ukládá počet a částku; starší záznam může používat živý náhradní výpočet. Výplata je příznak `vyplaceno`; aktualizace nemají jednotnou ochranu uzavřených/vyplacených verzí. |
| Z7 | [Hromadné sazby](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/hromadne_odmeny.php), [pracovní pozice](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/staff_workspaces.php) | Sazby se mění v otevřeném období, výběr používá staré skupiny. V této obsluze není obdobná explicitní `canAccess()` kontrola jako v přehledu/správě období; požadavek na sjednocení oprávnění. |
| Z8 | [Jízdy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/jizdy/uloz.php), [vozidla](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/vozidla/formular.php) | Řidič, aktuální čas startu/konce, tachometr, místo a audit. Evidence jízdy není plný výpočet či schválení cestovní náhrady. |
| Z9 | [Provozní přehled](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/admin_operational_overview.php), [zadání společných postupů](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md) | Existující pracovní základ a E1-10; nezakládat samostatný obecný projektový nástroj kvůli několika finančním úkolům. |
| Z10 | [Kontrakt účetního exportu](../etapa-2-2026-10-22/01_KONTRAKT_EXPORTU_PRIJEMKA_A_ZDROJE.md), [sportovní podklady etapy 4](../etapa-4-2026-10-22/00_ETAPA_4_PLAN.md) | Význam dokladů, peněz, přídělů a salda; vlastnictví sportovních skutečností a předání ověřené verze činnosti. Jde o připravená zadání, nikoli implementaci těchto etap. |
| Z11 | [Soukromé úložiště](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/private_storage.php), [stahování příloh](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/private_download.php) | Soukromé klíče, povolené typy a kontrola finanční role při stažení účtenky. To nenahrazuje neměnný archiv schválených verzí ani samoobslužná práva k vlastnímu případu. |
| Z12 | [Živé porovnání etapy 0](../etapa-0-2026-10-22/03_EVIDENCE_VS_KIS.md), [papírové agendy](../etapa-0-2026-10-22/02_PAPIROVE_AGENDY.md) | Datované rozdíly Harrachova a odměn; shoda pěti účtenek podle přehledu bez ověření souborů. Původní kandidátní seznam agend není potvrzení používání všech jeho položek. |

## 8. Omezení ověření

- „Máme“ se opírá o aktuálně načtenou vzdálenou zdrojovou revizi. Neprokazuje produkční konfiguraci, účetní správnost konkrétního zápisu ani úspěch kompletního provozního scénáře.
- Odměnové souhrny už existují. Hlavní práce je jejich průkaznost, ochrana, schválení a skutečné vypořádání; nesmějí být v implementačním zadání označeny za zcela chybějící modul.
- Číselné rozdíly Evidence/KIS jsou měřením etapy 0 z administrátorských přehledů, nikoli konzistentním novým databázovým snímkem. Před převodem vyžadují prvotní doklady a ověření hospodářem.
- Při tomto úkolu vznikly pouze dva místní dokumenty etapy 5. Nebyl měněn aplikační kód, databáze, role, finanční záznam, účetní export ani produkční provoz.
