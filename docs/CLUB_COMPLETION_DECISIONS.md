# Rozhodnutí před ostrým provozem — schválený základ a otevřené podklady

## Aktuální povolení D15 — 25. 10. 2026

Uživatel zadal: „Slouč ověřenou integrační větev do main a nasaď ji na produkci.
Postupuj autonomně, ověř zálohu a možnost návratu. Nové integrace nezapínej,
skutečná data neimportuj a po nasazení proveď kontroly bez testovacích zápisů.“
Toto povolení nahrazuje dřívější zákaz nasazení pouze pro konkrétní integrační
vydání. Merge PR #191/#192 a nasazení `115a617d` včetně nutné dopředné migrace
jsou dokončené; [důkazy](verification/production-integration-20261025/RESULTS.md).
Nejde o potvrzení účetní, lidské přejímky, převodu skutečných dat, pilotních jmen
či termínů ani aktivace integrací. D12: konfigurace zachována bez nové aktivace;
D13: konkrétní pilot otevřený; D14: záloha a technický návrat ověřeny, provozní
RTO/RPO zatím negarantované. Ostatní konkrétní podklady zůstávají otevřené.

Níže uvedený souhlas z 24. 10. je historický základ plánu; jeho tehdejší zákaz
nasazení je v rozsahu výše nahrazen aktuálním povolením.

Pracovní registr k realizačnímu plánu R01–R44, založen 23. 10. 2026.
Aktualizace 24. 10. 2026: uživatel v tomto chatu schválil doporučené varianty
**jako základ plánu**, s výslovným ponecháním konkrétních údajů a potvrzení účetní
otevřených. Zadání: „Souhlasím s doporučenými variantami jako základem. Zapiš je
do plánu, konkrétní údaje a potvrzení účetní nech otevřené.“ Současně platí
„Zatím nic nenasazuj.“

Schválení se týká níže zapsané verze doporučení D01–D20. Nejde o úplné uzavření
jednotlivých rozhodnutí, účetní převzetí, přidělení skutečných oprávnění ani
povolení produkční změny. D15 bylo k 24. 10. **NEPOVOLENO**; aktuální omezené povolení je uvedeno výše. Nové funkce označené jako
budoucí nejsou tímto zápisem implementované. Původní záznamy níže jsou historické;
aktuální stav určuje tato část „Schválený základ“.

Potvrzené zadání: oddíl není plátcem DPH, vede jednoduché účetnictví a účetní
dostává XLSX a faktury. Používá všechny dříve vyjmenované papírové agendy.
Půjčované vybavení je mimo rozsah. Samotné tyto informace neurčují účetní
klasifikaci, pracovněprávní vztahy ani daňový režim jednotlivých odměn.

## Schválený základ — 24. 10. 2026

Společný postup: spouštět po agendách, zachovat osobní vyřizování a malý počet
odpovědných lidí. Každá převzatá agenda má jeden určený zapisující systém.
Nasazení kódu, převod dat a aktivace integrací jsou samostatné kroky.

Termíny: **NASAZENÍ** = před konkrétní produkční změnou; **AGENDA** = před prvním
ostrým použitím či převodem dané oblasti; **POZDĚJI** = neblokuje první nasazení
v doporučeném omezeném rozsahu. Odklad agendy vyžaduje skutečné omezení jejího
používání; samotný zápis v plánu nic nevypíná ani nepovoluje.

| ID | Schválená varianta jako základ plánu | Co zůstává otevřené | Brána |
|---|---|---|---|
| D01 | Měsíční XLSX + PDF v jednom ZIP, zpočátku bez přímého napojení účetního programu. Nové doklady v jednoznačně odlišených řadách; historická čísla a VS zachovat. | Vystavovatel, účet, konkrétní řady a jejich návaznost, program účetní a její schválení vzoru. | AGENDA — před prvním skutečným dokladem. |
| D02 | Stručné členění příspěvků, kurzů, zboží, akcí, darů/dotací a výdajů; nezařazené položky zůstávají viditelné. | Účetní schválení konkrétních kategorií a zacházení s převody, zálohami, vratkami i daňovým členěním. | AGENDA — před finančními počátky a ostrou evidencí. |
| D03 | Výslovné přijetí určenou osobou podle stanov; nákup kurzu sám nevytváří členství. Plátce se určuje samostatně. Zachovat schválený sazebník; poměrné částky řešit doloženým jednotlivým rozhodnutím. | Konkrétní přijímající osoba, platná pravidla a sazebník, plátci a jednotlivé výjimky. | AGENDA — před přijetím členů a předpisy. |
| D04 | Zachovat doložené dohody a sazby. Trenérské odměny opřít o uzavřené skutečné aktivity; mimořádné odměny schvalovat jednotlivě. Historii automaticky nedopočítávat. | Skutečné sazby, jednotky, platnost a příjemci. | AGENDA — před výpočtem ostrých odměn. |
| D05 | Vlastní nárok schvaluje druhý člověk. Dva konkrétní schvalovatelé se zastupováním; bez obecné výjimky pro vlastní schválení. | Jména, rozsahy schválení a konkrétní zastupování. | AGENDA — před finančním schvalováním. |
| D06 | KIS připraví a schválí podklad; účetní určí výslednou částku k výplatě a potřebné doklady. Zpočátku bez vlastního automatického mzdového výpočtu. | Titul odměny, případné odvody, podklady a potvrzení účetní pro konkrétní příjemce. | AGENDA — před výplatou odměny. |
| D07 | Nejprve převést aktivní osoby s doloženými vazbami. Sporné identity oddělit; shoda jména nebo e-mailu sama nevytváří přístup k dítěti. | Registrář, skutečné mapy, sporné identity, rodinné vztahy a autoři. | AGENDA — před příslušným importem a rodinným přístupem. |
| D08 | Převzít ověřené počátky ke společnému datu. Sporné případy vést samostatně s vlastníkem a chybějícím podkladem; neodhadovat částku ani nulu. | Datum, doložená salda a jednotlivé spory včetně Harrachova 0 / 2 000 Kč. | AGENDA — před převzetím dotčených sald. |
| D09 | Jedna fyzická inventura podle variant. Staré objednávky pokud možno dokončit v původním systému, nové od určeného data v KIS. Každou objednávku dokončovat právě v jednom systému. | Skutečné kusy, rezervace, rozpracované objednávky, datum a konkrétní rozdělení dokončení. | AGENDA — před ostrým prodejem a výdejem z nového skladu. |
| D10 | Začít potvrzenými zdroji Shoptet, Evidence, KIS a papír/Excel; přesměrovávat postupně po ověření náhrady funkce. | Úplný seznam adres, formulářů, obsahu a vlastníků. | AGENDA — před přesměrováním či odstavením zdroje. |
| D11 | Tabulka druhu podkladu, účelu, přístupu, doby uchování a vyřazení. Žádná společná lhůta ani trvalý archiv všeho; automatické mazání až podle schválených pravidel. | Správce, konkrétní účely, oprávnění a lhůty včetně ověření příslušných požadavků. | AGENDA — pravidla před převodem nových soukromých podkladů; lhůty před automatickým vyřazováním. |
| D12 | Výslovně vymezit rozsah nových automatických úloh. Aktivovat postupně, nejprve nezbytné provozní zprávy; import banky a automatické potvrzování plateb schvalovat odděleně. | Správce a zástupce, seznam služeb pro první spuštění, kontrolní kontakt, běhy a pozdější souhlas s aktivací každé služby. | NASAZENÍ — rozsah; AGENDA — před jednotlivou aktivací. |
| D13 | Registrář, hospodář a vedoucí sportu se zástupci; role může zastávat tentýž člověk při zachování D05. Pilot přibližně dva týdny s jednou skupinou a několika rodinami, jeden zapisující systém pro převzatou agendu. | Jména, skupina a rodiny, termíny, skutečný rozsah pilotu a rozdělení zápisů. | NASAZENÍ. |
| D14 | Mimo tréninky/prodej, pozastavení dotčených zápisů a úplná záloha před převodem. Plánovací cíle: obnova do 4 hodin, nejvýše 1 hodina ztráty dat v běžném provozu; při řízeném přechodu zachovat všechny potvrzené zápisy. | Konkrétní okno a nejzazší návrat, rozhodující osoba; technické ověření dosažitelnosti cílů na hostingu. Cíle zatím nejsou provozní záruka. | NASAZENÍ. |
| D15 | Samostatně schvalovat konkrétní verzi, nasazení kódu, migrace, import skutečných dat a aktivaci služeb. | Nasazení vydání `115a617d` a nutná migrace povoleny a dokončeny 25. 10. Další importy, aktivace služeb a jiná vydání nejsou tímto automaticky povoleny. | NASAZENÍ. |
| D16 | Staré služby rušit až po úspěšném pilotu a převzetí prvního skutečného měsíce účetní; zachovat dostupnou historii a omezení zápisů podle D09/D13. | Skutečná přejímka, potvrzení účetní a výslovné povolení odstavení služeb. | POZDĚJI — před zrušením starých služeb. |
| D17 | Pro první provoz zapisovat doložená aktuální rozhodnutí o členství. Zpětné/budoucí účinnosti posuzovat jako výjimky; ukončení členství nemaže historii ani dluh. | Konkrétní pravidla, osoby a výjimky; případné rozšíření časové evidence vyžaduje další implementaci. | AGENDA — před ostrými změnami členství. |
| D18 | Převzít doložená platná pravidla konkrétní činnosti. Registrář ověřuje podklady; trenér vidí potřebný stav a relevantní omezení. Systém sám neurčuje zdravotní způsobilost. | Skutečné požadavky podle činnosti a odpovědná osoba pro nejasné případy. | AGENDA — před spoléháním na přehled při skutečné účasti. |
| D19 | Pro první spuštění odložit novou samoobsluhu změn kontaktů a dalších soukromých příloh. Rodina vyřídí změnu s registrářem; případné budoucí online žádosti nejprve ověří klub. | Budoucí rozsah, schvalování a případná implementace. Odklad první etapy je schválen jako základ. | POZDĚJI. |
| D20 | Pro první spuštění odložit novou finanční samoobsluhu trenérů. Trenér předá podklad hospodáři, který jej zapíše; schválení podle D05. | Budoucí potřeba, rozsah přístupů a případná implementace. Odklad první etapy je schválen jako základ. | POZDĚJI. |

## Krátký seznam podkladů od uživatele pro D12–D15

Konkrétní [návrh pilotu D12–D14](CLUB_PILOT_D12_D14.md) rozepisuje první
skupinu, pořadí agend a integrací, přípravu, přechod a návrat při problému.
Jména a termín zůstávají k doplnění. Jde o navržené provedení schváleného
základu, nikoli schválení nového detailního rozsahu nebo povolení D15.

1. **D12 — integrace:** jméno správce a zástupce, kontrolní e-mail a seznam
   služeb požadovaných při prvním spuštění. Pro zvolené služby určit požadovaný
   režim/četnost, případně nechat připravit návrh. Nové neschválené automatické
   úlohy nejsou součástí aktivace; existující produkční nastavení se nyní nemění.
   Hesla ani API klíče nejsou součástí tohoto seznamu.
2. **D13 — pilot:** jména registráře, hospodáře, vedoucího sportu a zástupců;
   vybraná skupina a několik rodin; navržený začátek přibližně dvoutýdenního
   pilotu a agendy, které převezme KIS. U každé určit, kde se budou zapisovat
   skutečná data a dokončovat rozpracované případy.
3. **D14 — okno změny:** vhodný den a čas, maximální délka odstávky / nejzazší
   čas návratu a člověk oprávněný rozhodnout o návratu. Dosažitelnost schválených
   plánovacích cílů 4 h / 1 h musí doložit technická příprava; není třeba je
   znovu schvalovat, pokud se nezmění nebo se neprokáže jejich nedosažitelnost.
4. **D15 — konkrétní vydání povoleno:** nasazení `115a617d` je dokončené.
   Pro budoucí importy skutečných dat a aktivace služeb zůstává nutné samostatné
   povolení; nynější nasazení jej nenahrazuje.

Konkrétní údaje a potvrzení účetní zůstávají otevřené. Souhlas s variantami
není potvrzení skutečných počátečních stavů, fyzické inventury, jmen, sazeb,
podkladů, retence, zdravotních požadavků ani lidské přejímky.

## Původní registr a historie balíků — stav před schválením základu

Následující tabulky a zápisy zachovávají tehdejší otevřené otázky. Výroky o
neexistenci schválení v této historické části se vztahují k době jejich vzniku;
rozsah nového souhlasu je výhradně v aktuální části výše.

| ID | Rozhodnutí / podklad k doplnění | Odpovědná role | Nejpozději před | Bezpečný postup do té doby |
|---|---|---|---|---|
| D01 | Vystavovatel, dokladové řady, vzor a struktura účetního předání, účetní program | Hospodář + účetní | R36 / první reálné doklady | Pouze syntetický vystavovatel; žádné odhadnuté ostré řady |
| D02 | Klasifikace příjmů, výdajů, převodů, záloh a vratek | Účetní | R39 / počáteční stavy | Nezařazená položka zůstává viditelná k doplnění |
| D03 | Přijímání členů, plátce versus zástupce, příspěvky při nástupu uprostřed období | Registrář + vedení | Reálné přijetí a předpis | Výslovné doložené rozhodnutí pro konkrétní případ |
| D04 | Sazby, platnost a způsob výpočtu trenérských a sportovních odměn | Vedoucí sportu + účetní | Reálný nárok a výplata | Žádná výchozí klubová sazba ani dopočet historického chybějícího snímku |
| D05 | Kdo schvaluje vlastní výdaj, odměnu a opravu; pravidlo zastupování v malém oddílu | Vedení + hospodář | Ostré schvalování | Konzervativní oddělení podatele / příjemce a schvalovatele; výjimka vyžaduje další zadání |
| D06 | Právní a účetní titul odměny, případné odvody a částka k výplatě | Účetní | Výplata odměny | Systém eviduje podklad a výslovnou částku; nenahrazuje mzdový výpočet |
| D07 | Konkrétní sporné identity, rodinné vztahy, autoři a mapy skupin | Registrář + správce dat | R38 / import daného rozsahu | Kandidát shody bez potvrzení není sloučení ani oprávnění |
| D08 | Harrachov: rozpor 0 / 2 000 Kč; další nesrovnalosti, doklady a otevřená salda | Hospodář + vlastníci původních dokladů | R39 | Sporný případ zůstává v karanténě; částku nelze vybrat podle stáří webu |
| D09 | Fyzická inventura, rezervace, rozpracované objednávky a určení systému jejich dokončení | Skladník + hospodář | R39 / pilot prodeje | Syntetická inventura není potvrzení skutečného skladu |
| D10 | Úplný seznam nahrazovaných webů, formulářů a adres, vlastník obsahu | Vedení | R40 / přesměrování | Potvrzené zdroje jsou Shoptet, původní Evidence, původní KIS a papír/Excel; seznam není prohlášen za úplný |
| D11 | Retence souborů, souhlasů, zdravotních podkladů a archivů | Vedení + odpovědný správce | Automatické mazání / odstavení archivu | Žádné automatické mazání ani zveřejnění soukromého obsahu |
| D12 | Vlastníci integrací, skutečné kontakty, termíny úloh a povolené odesílání | Správce + vedení | Aktivace integrace | Místní prostředí blokuje vnější odesílání; produkční klíče se nepřenášejí |
| D13 | Jména obsluhy a zástupců, rozsah pilotu, jediný zapisující systém pro každou agendu | Vedení | R42–R43 | Technická zkouška není uživatelská přejímka ani živý pilot |
| D14 | Přijatelné okno odstávky, cíle obnovy, rozhodující osoba a mezní čas návratu | Vedení + správce | R43 | Nácvik změří postup; nepředpokládá přijatelnou ztrátu nových transakcí |
| D15 | Samostatné povolení nasazení, reálných importů a otevření provozu | Uživatel + garanti agend | Produkční změna | Žádný push, deploy ani produkční zápis v tomto zadání |
| D16 | Převzetí prvního skutečného měsíce účetní a povolení ukončit staré služby | Účetní + vedení | R44 | Staré služby se nevypínají; technický test není účetní převzetí |

## Doplnění pro balík B — 24. 10. 2026

| ID | Odložené rozhodnutí | Co je připravené bez něj | Co se neprovádí |
|---|---|---|---|
| D17 | Pravidla účinnosti přijetí, přerušení, ukončení a návratu; kdo je potvrzuje, zda lze rozhodnutí zadat zpětně / do budoucna a jak opravovat překryvy | Průvodce vyřízením, současné členství, historie zaznamenaných rozhodnutí, odpovědný registrář a další krok | Nové intervaly členství, seznam členů k minulému datu, automatické odpojování vazeb nebo převod staré historie na účinnost |
| D18 | Které zdravotní podklady jsou požadované pro konkrétní činnost a kdo rozhodne o účasti při chybějícím podkladu; navazuje na D11 | Přehled podle skupiny/lekce a dne, současné schválené účely a lhůty upozornění, osobní předání registráři | Odhad nové zdravotní povinnosti, diagnózy v přehledu, automatický zákaz účasti nebo nové retenční pravidlo |
| D19 | Zda rodina smí podávat online změny kontaktů a nahrávat nové podklady, kdo je přebírá a schvaluje | Seznam vlastních úkolů, čekání na klub, existující žádost o propojení a osobní vyřízení | Nový veřejný formulář změn údajů / soukromých příloh ani udělení přístupu podle kontaktu |

Odesílání zpráv a jeho plánování zůstává pod D12; zapnutí odběru není potvrzení
transportu ani doručení. Pravidla v D17–D19 nejsou schválena implementací přehledu.
Balík B je dokončen v rozsahu nezávislém na těchto rozhodnutích.

## Doplnění pro balík C — 24. 10. 2026

| ID | Odložené rozhodnutí | Co je připravené bez něj | Co se neprovádí |
|---|---|---|---|
| D20 | Zda trenér sám předkládá výdaje a žádá zálohu; kdo požadavek přebírá a smí číst soukromé přílohy | Pojmenované výběry, položkový formulář a soukromá účtenka pro hospodáře, návaznost z akce, osobní předání | Nová samoobslužná finanční role nebo zpřístupnění dokladů trenérovi |

Skutečné sazby a metodika zůstávají pod D04/D06, jména schvalovatelů a výjimky
pod D05. Balík C je neodhaduje. Syntetická sazba v přejímce není klubové rozhodnutí.
Sporné vazby původních akcí a dokladů zůstávají pod D07/D08; shoda času a vozidla
nepřiřazuje skutečnou jízdu k akci. Žádná stará agenda se tím nevypíná.

## Doplnění pro balík D — 24. 10. 2026

Příprava období, přehled komunikace a verzovaná mapa starých postupů jsou
implementované a ověřené lokálně. [Výsledky a rozsah](verification/season-operations-20261024/RESULTS.md)
a [pracovní mapa](verification/season-operations-20261024/MAPA_PRECHODU.md)
nezakládají skutečné vlastnictví agendy ani termín přechodu.

D03/D17 stále určují pravidla pokračování členství. D07/D10/D11 vyžadují konkrétní
staré obrazovky, identity a rozsah historie. D12 vyžaduje vlastníka a zástupce
komunikace, četnost běhů, transport a povolený přejímací kontakt. D13–D16 vyžadují
jmenovanou obsluhu, jediný skutečný zapisující systém, datum přepnutí, pilot,
obnovu a samostatné povolení skutečného převodu či odstavení.

Tyto odpovědi nebyly odhadnuty. Žádné zprávy se nezapnuly, žádná skutečná mapa
nebyla automaticky založena a původní Evidence/KIS nebyly měněny.

Doklad rozhodnutí musí určit osobu, datum, rozsah, konkrétní verzi podkladu
a důvod. Změna vstupu nebo relevantního cíle vyžaduje nové posouzení.
Provozní záznam lze následně uložit v příslušné administraci; tento pracovní
registr sám nemění role, pravidla, platby, členství ani stav předání účetní.

## Doplnění pro balík E — 24. 10. 2026

[Společná technická přejímka](verification/final-system-20261024/RESULTS.md)
ověřila propojení agend včetně e-shopu, účetních podkladů, souběhů a obnovy.
Připraven je nový syntetický vzorek předání účetní s původní a opravenou revizí.
Žádné rozhodnutí D01–D20 tím není uzavřeno. Stav uživatelského převzetí i převzetí
účetní zůstává NEPROVEDENO / NEPŘEVZATO. Produkce a skutečné integrační transporty
nebyly v tomto balíku kontaktované, změněné ani aktivované.
