# Etapa 6 — migrace dat, pilotní provoz a přechod ze starých systémů

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Zadání k 22. 10. 2026. Pouze příprava. Neprovádí se import, obnova, nasazení, změna produkčních dat, aktivace automatizací, přesměrování webů ani vypnutí služby.

**Přechod je řízené sloučení dat do již používaného KIS, nikoli obnova celé staré Evidence přes nový systém. Máme dílčí importéry, kontrolní reporty, databázové zálohování a chráněné nasazení. Chybí společný převodní kontrakt pro všechny agendy, úplná zkouška obnovy, opakovatelná migrace historie, provozní pilot a ověřený postup přepnutí i návratu.**

Cílem je, aby každá agenda měla po přechodu jedno určené místo pro nové zápisy, dostupnou historii, odpovědnou obsluhu a doloženou kontrolu úplnosti. Staré systémy se odstaví až po převzetí jejich skutečně používaných funkcí a dat.

## Podklady a hranice

Navazuje na připravené etapy [0](../etapa-0-2026-10-22/00_ETAPA_0.md), [1](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md), [2](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md), [3](../etapa-3-2026-10-22/00_ETAPA_3_PLAN.md), [4](../etapa-4-2026-10-22/00_ETAPA_4_PLAN.md) a [5](../etapa-5-2026-10-22/00_ETAPA_5_PLAN.md). Jejich dokumentace neznamená, že jsou všechny požadavky již implementované nebo převzaté.

„Máme“ znamená funkci nalezenou ve zdrojové revizi `origin/main` **68b22bd2b3b666dbb4fb571a2731eae5b7f056d9**, případně výslovně datované zjištění etapy 0. Aktuální produkční verze, konfigurace, zůstatky a úplnost exportů se nyní znovu neověřovaly.

Samostatné přílohy:

- [Migrační pravidla, pilot, postup přepnutí a návratu](01_MIGRACE_PILOT_A_PRECHOD.md).
- [40 přejímacích scénářů, výstupní brány a zdrojové podklady](02_PRIJEMKA_A_ZDROJE.md).

Potvrzené zdroje jsou Shoptet, původní Evidence, dříve používaný externí KIS a papír/Excel. Nový KIS již obsahuje vlastní osoby, soupisky, účty a další současná data, která je nutné zachovat. Úplný seznam dalších nahrazovaných webů/služeb se musí uzavřít v E6-01; jejich názvy a obsah nelze domyslet. Půjčování vybavení zůstává mimo rozsah.

## Co již máme a co doplnit

| Oblast | Existující základ | Zbývající práce |
|---|---|---|
| Porovnání dat | Etapa 0: agregace Evidence/KIS, kandidáti identity, známé finanční rozpory | Konzistentní snímky, fyzické řádky a vazby, přílohy a všechny používané moduly |
| Import KIS | Kontrola stabilních ID, náhledy, otisky, parity, oddělený sandbox a přenos předpisů s omezením | Ověřit vhodnost po entitách; nejde o univerzální produkční migraci celého oddílu |
| Úplný import osob | Samostatný transakční nástroj pro nahrazení osob a jejich závislostí | Pro tento přechod jej nepoužít: cílová aktuální data se mají zachovat |
| Shoptet katalog | CSV/XML parser, staging, kontrola, jednorázová synchronizace, obrázky a publikace | Ochrana současných změn, otevřené objednávky, doklady, skutečný sklad a účty zákazníků |
| Zálohy | SQL, otisk, manifest, vlastnictví tabulek, triggery a test obnovy DB | Úplná aplikace včetně souborů a klíčů, ověřené konkrétní zálohy obou instalací a změřená obnova |
| Nasazení | Chráněný workflow, předběžná záloha, migrace schématu a aktivace release | Oddělená autorizace datového převodu, provozní uzávěra a kompatibilní plán návratu |
| Kontroly | Readiness a invarianty, dílčí testy, existující UAT podklady | Přizpůsobit aktuálnímu modelu etap 1–5; prokázat úplnost dat i běžnou práci lidí |
| Pilot | Rodinné, trenérské, prodejní a administrativní cesty v aplikaci | Konkrétní rozsah, vlastník zápisu, obsluha, podpora a kritéria přijetí |
| Přechod webů | Nový KIS má veřejné i pracovní části | Ověřený seznam obsahu, adres, odkazů, rezervací, formulářů a potřebných integrací |
| Odstavení | Původní systémy jsou zdrojem historie a otevřených případů | Archiv, dokončení starých obchodů, pozdní platby/vratky a samostatné rozhodnutí o ukončení služeb |

### Konkrétní závazky z etapy 0

Přehledy 22. 10. 2026 ukázaly 1 651 tréninků v Evidenci proti 1 v KIS a 50 závodů proti 0. Ze 405 zdrojových osob s tréninky mělo 324 jednoznačného kandidáta, 81 vyžadovalo individuální posouzení. Kandidát není potvrzená identita. Počty zahrnovaly i budoucí záznamy; nejsou automaticky počtem skutečně absolvovaných aktivit.

Rozdíl zálohy Harrachova 0/2 000 Kč musí rozhodnout hospodář podle dokladů. Obdobně je potřeba ověřit 27 aktivních odměnových období, zachovat uzavřené a vyplacené odměny a neduplikovat pět shodně zobrazených účtenek za 1 237 Kč. Toto jsou datované výchozí poznatky, nikoli pevné očekávané počty pro budoucí převod. [Z1]

## Priority a návaznosti

**P0** = podmínka převodu nebo spuštění dotčené agendy. **P1** = navazující práce; před úplným odstavením příslušného starého systému musí být dokončena, nebo musí být schválen přesný rozsah archivního řešení. Časový harmonogram se stanoví až podle výsledku zkoušky a dostupnosti obsluhy.

| Úkol | Priorita | Závislosti | Přejímá |
|---|---|---|---|
| E6-01 Rozsah a odpovědnosti přechodu | P0 | Etapy 0–5 jako zadání | Vedení + správci agend |
| E6-02 Verze systému a izolované prostředí | P0 | E6-01; E1-01 | Technický správce |
| E6-03 Inventura a konzistentní exporty | P0 | E6-01; E0-04 | Správce dat + správci zdrojů |
| E6-04 Úplná záloha a zkouška obnovy | P0 | E6-02, E6-03; E1-09 | Technický správce + druhý kontrolující |
| E6-05 Osoby, rodiny, role a soupisky | P0 | E6-03; E1-02 až E1-06; E4-01, E4-02 | Registrář + hlavní trenér |
| E6-06 Sportovní historie a programy | P0 pro převzetí Evidence | E6-05; E4-03 až E4-13 | Hlavní trenér + koordinátor |
| E6-07 Shoptet, prodej a počáteční sklad | P0 pro přechod e-shopu | E6-03, E6-05; E2-14; E3-03, E3-15 | Skladník + hospodář |
| E6-08 Peníze a otevřené závazky | P0 | E6-03, E6-05; E2-14, E2-15; E5-15 | Hospodář + účetní |
| E6-09 Přílohy, obsah a staré adresy | P0 pro potřebné podklady; ostatní P1 | E6-01, E6-03; E1-06; E4-14; E5-14 | Správce obsahu + správci agend |
| E6-10 Opakovatelný převod a změnové dávky | P0 | E6-02 až E6-09 pro převáděný rozsah | Technický správce + správce dat |
| E6-11 Zkušební migrace a porovnání | P0 | E6-04, E6-10 | Správci agend + účetní |
| E6-12 Provozní připravenost | P0 | E6-02; přejímky potřebných částí etap 1–5 | Provozní správce + obsluha |
| E6-13 Omezený pilot | P0 | E6-11, E6-12, E6-16; rozhodnutí o pilotu | Vedení + obsluha |
| E6-14 Příprava ostrého přepnutí | P0 | E6-13; aktuální kontroly E6-03 až E6-12 | Vedoucí přechodu |
| E6-15 Ostrý převod a otevření provozu | P0, budoucí realizační krok | E6-14, E6-16; výslovné schválení | Vedoucí přechodu + správci agend |
| E6-16 Návratový a havarijní postup | P0 již před pilotem | E6-04; E6-10; E1-09 | Vedení + technický správce + hospodář |
| E6-17 Stabilizace a účetní uzávěrka | P0 pro převzetí provozu | E6-15 | Hospodář + hlavní trenér + správce |
| E6-18 Archiv a odstavení starých systémů | P1; podmínka úplného nahrazení | E6-09, E6-17; rozhodnutí o odstavení | Vedení + vlastníci služeb |

E6-16 se připravuje před pilotem a ověřuje při generální zkoušce. Číslování úkolů není pokyn provést vše v jediném lineárním běhu. Zápisové kroky tabulky jsou budoucí práce, nikoli nyní provedené akce.

### E6-01 — Uzavřít rozsah a určit odpovědné osoby

**Máme:** potvrzené zdroje, papírové agendy a rozdělení etap. Nemáme úplný potvrzený seznam všech dalších webů a používaných externích služeb.

**Doplnit:** registr „agenda → zdroj → budoucí hlavní evidence → vlastník → historie → podmínka vypnutí“. Rozlišit Shoptet, původní Evidenci, externí členský KIS a nynější KIS. U každé části rozhodnout aktivní převod, pouze čitelný archiv, nebo nepoužívanou funkci. Archiv je přijatelný jen tam, kde běžná práce nepotřebuje aktivní data.

Vedení jmenuje vedoucího přechodu a zástupce, registráře, hospodáře, skladníka, hlavního trenéra a technického správce. Tyto role nemusí znamenat různé zaměstnance; důležitá je jasná odpovědnost a kontrola finančních/obnovovacích kroků.

**Hotovo, když:** žádný používaný web, formulář, rezervace nebo papírová agenda nezůstane bez rozhodnutí. P01.

### E6-02 — Stanovit ověřovanou verzi a oddělit zkušební prostředí

**Máme:** verzovaný kód, číslované migrace, testy a oddělení deploye od běžného merge. Historické dokumenty uvádějí starší provozní verze. [Z2]

**Doplnit:** pro každou zkoušku zaznamenat přesný commit, migrace schématu, verzi převodníku a pravidel. Před pilotem ověřit skutečnou cílovou produkční verzi, nikoli jen větev `main`. Pracovní rozpracované změny nejsou schválený release.

Izolované prostředí má vlastní DB, soubory, adresu, přístupové účty a blokované ostré e-maily, platby, webhooky a plánovače. Obnova nesmí převzít aktivní transporty a tajemství tak, aby se z kopie stal druhý produkční systém. Syntetická zkouška a chráněná zkouška skutečného exportu jsou odlišné režimy.

**Hotovo, když:** správce prokáže oddělené cíle a neaktivní vnější účinky; výsledek lze zopakovat na stejné verzi. P02–P03.

### E6-03 — Získat úplnou inventuru a konzistentní snímky

**Máme:** porovnání administrátorských přehledů, katalogové exportní kontrakty a seznam dosud neporovnaných dat. Přehled není úplný databázový export. [Z1, Z5]

**Doplnit:** pro oba vlastní weby inventuru tabulek, schémat, vazeb, souborů a konfigurace; pro Shoptet a externí KIS ověřit skutečný rozsah dostupných exportů včetně příloh a historie. Zaznamenat čas a metodu snímku, kontrolní počty/součty, manifest a otisky. Názvem „úplný export“ nenahradit kontrolu obsahu.

Zachytit i osiřelé vazby, interní stavy, zrušené položky, rezervace, individuální lekce, další činnosti trenérů, souhlasy, jízdy a servis v používaném rozsahu. Určit, jak mezi snímkem a přepnutím zachytíme změny a výmazy. Pokud zdroj nemá spolehlivé změnové značky, porovnat nové úplné snímky podle stabilních ID a otisků.

**Hotovo, když:** každá položka rozsahu má zdroj, počet, způsob přístupu a pravidlo změn; potřebný nepřístupný export je konkrétní blokátor. P04–P05.

### E6-04 — Prokázat obnovu celé potřebné instalace

**Máme:** `db-backup.php` s manifestem, otiskem, vlastnictvím tabulek a triggery; integrační zkoušku obnovy do jiné MariaDB. Záloha patří jen explicitně vlastněným tabulkám, ne automaticky celé sdílené databázi či všem souborům. [Z3]

**Doplnit:** samostatné ověřené sady pro zdroj i cíl, potřebné přílohy a uploady, konfiguraci a bezpečně dostupné šifrovací klíče. Export a záloha jsou různé artefakty. Ověřit pokrytí starých tabulek mimo aktuální seznam vlastnictví a souborů mimo webový kořen. Cizí aplikace se při obnově nesmějí poškodit.

Obnovit konkrétní sadu do izolované kopie, ověřit čitelnost citlivých dat, oprávnění příloh, kontrolní součty a běžné obrazovky. Změřit trvání. Potvrdit nejdelší přípustnou odstávku a způsob zachování všech potvrzených peněžních, skladových a obchodních operací. Denní záloha sama neřeší záznamy po jejím pořízení.

**Hotovo, když:** existuje datovaný protokol úplné obnovy a dosažitelný postup s určeným správcem, nikoli pouze soubor s platným SHA. P06–P07.

### E6-05 — Schválit mapu osob, vztahů a soupisek

**Máme:** kanonické osoby, rodinné role, datované soupisky, importní aliasy a náhledy. Kontrakt `kis-import-field-v1` kontroluje stabilní externí ID; jiné importní formáty mají jinou identitu. [Z4]

**Doplnit:** mapu `(zdroj, typ entity, původní ID) → cílové ID`, pravidla pro jednotlivá pole a rozhodnutí u konfliktů. Zachovat aktuální KIS osoby, ruční členské stavy, platné přístupy a kontakty. Shoda jména, e-mailu nebo číselného ID napříč instalacemi není důkaz totožnosti či rodičovského oprávnění.

Přednostně vyřešit osoby s otevřenými penězi, účastí a odměnami, včetně 81 případů z etapy 0. Neověřený historický člověk může zůstat v označeném archivu k dořešení; nesmí se z něj odhadem stát nový aktivní člen. Neznámé narození a zástupné datum zůstanou neověřené. Historická skupina nezaloží automaticky placenou účast ani dnešní pracovní oprávnění.

**Hotovo, když:** v aktivně převáděných případech není nejasná osoba či vazba; sporná historie má vlastníka a neslouží k platbě nebo aktivaci. P08–P11.

### E6-06 — Připravit převod sportovní historie a živých programů

**Máme:** cílové tréninky, docházku, měření, závody, programy a kontrakt E4-13. Nemáme ověřený společný převod všech těchto zdrojů. [Z6]

**Doplnit:** mapy trenérů, událostí, plánů/skutečnosti, účastí, měření, výsledků a souborů. Uchovat původ, jednotky, neznámé hodnoty a dobový význam soupisek. Záznam s budoucím datem neprohlásit za skutečný absolvovaný trénink.

Zvlášť převzít aktivní přihlášky, kapacity, rezervace lekcí, omluvy a existující náhradní nároky. Import historie sám nerozesílá zprávy, nevytváří nové náhrady, neaktivuje nabídku a nepřepočítává vyplacené odměny. Převod historické činnosti a navazujících peněz se kontrolují společně s E6-08.

**Hotovo, když:** sedí jednotlivé aktivity, vazby a vysvětlitelné souhrny; rodina i trenér dohledají správnou historii a budoucí termíny. P12–P15.

### E6-07 — Převzít Shoptet, rozpracovaný prodej a skutečný sklad

**Máme:** parser katalogu, staging, kontrolu, synchronizaci a publikaci; nejde už jen o připravený CSV import. Jednorázová synchronizace umí měnit sklad a vyřazovat chybějící importované položky, takže opakování starého exportu není neutrální operace. [Z5]

**Doplnit:** porovnat již existující katalog KIS s čerstvým Shoptetem, zachovat nové ruční změny a schválené výjimky. Oddělit katalogový obsah od převodu objednávek, dokladů, zákazníků a skladu. U každé otevřené objednávky rozhodnout, který systém dokončí platbu, výdej, storno či vratku; historie faktur zůstává pod původní identitou.

Sklad převzít po fyzické inventuře: fyzické kusy, rezervace z obou systémů, blokované kusy a dostupnost. Aktuální `stock_quantity_decimal` se snižuje už rezervací, proto jej nelze bez převodu ztotožnit s fyzickým počtem. Pokud část starých objednávek doběhne v Shoptetu, jejich rezervace musí být zohledněné v jednom hlavním skladu. Nepoužívat dva nezávislé sklady pro stejné poslední kusy.

**Hotovo, když:** každá rozpracovaná objednávka má vlastníka dokončení a každý kus je započten právě jednou; veřejná nabídka odpovídá převzaté kapacitě/skladu a účetnímu zařazení. P16–P19.

### E6-08 — Převzít finanční historii a otevírací salda

**Máme:** návrh účetního kontraktu, stálé VS, finanční modely a inventuru známých rozporů. Místní přenos předpisů má kontroly a omezení; jeho existence není oprávněním k produkčnímu importu. [Z4, Z7]

**Doplnit:** po osobách/protistranách a měnách potvrdit pohledávky, závazky, přeplatky, zákaznické i poskytnuté zálohy, pokladny, banky, vratky, vypořádání plateb a odměny. Zachovat čísla původních dokladů, jejich obsah, VS a čitelné návaznosti. Převzetí zůstatku nevytváří nový pohyb peněz.

Harrachov a jiné rozpory rozhodne hospodář podle důkazů. U pozdních plateb starých objednávek musí existovat vazba i po ukončení nového prodeje ve starém systému. Účetní předání přes rozhodný okamžik označí původ každého případu a vyloučí dvojí příjem/výdaj ze dvou exportů. Odměna, přeplatek a náhradní lekce zůstávají oddělené.

**Hotovo, když:** účetní a hospodář přijmou počáteční/konečné saldo a každý původní doklad i neuzavřený případ je dohledatelný. P20–P23.

### E6-09 — Převzít soubory, obsah a návaznosti starých webů

**Máme:** soukromé ukládání/stahování příloh, katalogové obrázky a veřejné části nové aplikace. Úplná shoda souborů a seznam všech nahrazovaných adres dosud potvrzeny nejsou. [Z8]

**Doplnit:** manifest originálů s velikostí, otiskem, vlastníkem záznamu a přístupovým režimem. Ověřit po převodu otevření souboru a oprávnění, nejen jeho existenci. Soukromé zdravotní, účetní a smluvní soubory nesmí skončit ve veřejné cestě.

Sepsat stránky, dokumenty, kontakty, formuláře, rezervace a důležité staré adresy, jejich novou cílovou stránku nebo archiv. Pro veřejné adresy naplánovat vhodná trvalá přesměrování až po přejímce cíle; soukromé odkazy a platební/webhookové adresy řešit samostatně. Změna domény nesmí poškodit e-mail, důležité externí odkazy ani pozdní platby. Odhlášení a bezpečnost přístupových tokenů se nesmí obejít přesměrováním.

**Hotovo, když:** obsah potřebný k nahrazení potvrzených webů má převzaté místo a citlivé přílohy zachovaly ochranu. P24–P25.

### E6-10 — Doplnit opakovatelný migrační nástroj

**Máme:** dílčí validace, otisky, staging, transakce, převodní protokoly a ochrany před duplicitou. Úplný import osob výslovně maže původní osoby, historii a dotčené objednávky; pro současné slučování je nevhodný. [Z4, Z9]

**Doplnit při budoucí implementaci:** aditivní převod podle schválených map, oddělený náhled a zápis, vazbu schválení na konkrétní balík/verzi/otisk a ochranu cílových změn od náhledu. Stejná dávka musí být opakovatelná bez nových řádků a pohybů. Jiný obsah stejného zdrojového ID je změna k posouzení, ne tiché přepsání.

Stanovit pořadí závislostí, atomické hranice dílčích dávek, stav rozpracování a bezpečné pokračování po přerušení. Evidence musí zahrnout všechny položky: převzato, propojeno, archivováno, odloženo či odmítnuto s důvodem. Vynechání ze změnového exportu není důkaz smazání; výmazy potřebují vlastní pravidlo a kontrolu návazností.

**Hotovo, když:** náhled popisuje konkrétní dopad, změna vstupu/cíle jej zneplatní a opakování nebo pád nevytvoří duplicity ani ztrátu aktuálního KIS. P26–P28.

### E6-11 — Projít zkušební převod a porovnání výsledku

**Máme:** dílčí testy, readiness a kontrolní invarianty. Některé kontroly odrážejí starší model, například celkovou cenu objednávky bez novější dopravy; nelze je bez revize použít jako úplnou bránu. [Z10]

**Doplnit:** na izolované kopii cíle spustit převod schváleného vzorku, pak plného rozsahu a změnové dávky. Ověřit počty, součty, řádkové mapy, přílohy, oprávnění a vzorky běžných cest. Celková shoda počtu osob není dostačující a po doložené deduplikaci nemusí být správně stejný počet řádků.

Rozšířit kontroly o konečný model etap 1–5: dopravu, vratky, částečné výdeje, náhrady, uzavřené odměny a staré doklady. Nevynechat chybějící tabulku jako zelený výsledek. Zopakovat import a simulovat přerušení, kolizi i obnovu. Změřit trvání pro plán odstávky.

**Hotovo, když:** každý rozdíl je vysvětlený; nevyřešené peníze, identita aktivních případů, osiřelé vazby a chybějící povinné podklady blokují dotčený přechod. P29–P30 a relevantní scénáře etap 1–5.

### E6-12 — Připravit provoz, obsluhu a integrační vazby

**Máme:** role, fronty zpráv, plánované úlohy, provozní přehledy a platební integrace. Existence kódu není důkaz aktivního plánu či doručení. [Z11]

**Doplnit:** přehled používaných úloh a integrací s vlastníkem, aktuálním stavem a důkazem funkčnosti: zprávy, bankovní evidence, platební odpovědi, rezervace, generování předpisů/nabídek a zálohy. Nová aktivace není vedlejším účinkem migrace. U potřebné neaktivní automatizace musí existovat přijatý ruční postup s četností a odpovědnou osobou.

Připravit role a zastupování, stručné pracovní návody, kontakt pro hlášení závady a postup výpadku s číslovanými dočasnými záznamy. Rodinný kontakt nepovýšit na ověřený účet; aktivaci/reset řešit cíleně po ověření identity. Zprávy uživatelům nyní pouze připravit, nikomu je neposílat.

**Hotovo, když:** obsluha umí běžnou práci i opravu a správce pozná zastavenou úlohu, i když web odpovídá. P31–P32.

### E6-13 — Uskutečnit omezený pilot

**Máme:** připravené přejímky etap 1–5 a aplikační cesty pro rodinu, trenéra, skladníka a hospodáře. Konkrétní společný pilot zatím převzatý není.

**Doplnit:** nejprve neprodukční generální zkoušku; následně samostatně schválený živý pilot s malou závodní skupinou, jedním kroužkem, několika rodinami a obsluhou osobního prodeje/administrativy. Začít omezeným souborem používaných funkcí. Zboží v pilotu buď vyhradit fyzicky oddělenou zásobou, nebo používat jeden centrální sklad; souběžný neřízený prodej stejných kusů je nepřípustný.

Pro každou agendu určit jediný zapisující systém. Druhý může sloužit k porovnání, nesmí nezávisle potvrzovat další výplatu, docházku či rezervaci stejného případu. Délku navrhuji alespoň dva úplné tréninkové týdny a jedno účetní předání; rozhoduje pokrytí potřebných situací, nikoli jen uplynutí dnů.

**Hotovo, když:** projde běžná cesta i storno/oprava, souhlasí peníze a sklad, obsluha nevede neřízenou paralelní evidenci a vedení přijme výsledky. P33–P34.

### E6-14 — Připravit přesný plán ostrého přepnutí

**Máme:** chráněné nasazení a návrh kontrol. Nemáme ještě konkrétní schválené okno, vstupní balíky a protokol přepnutí všech agend.

**Doplnit:** po pilotu zafixovat release, rozsah, přesná zdrojová/cílová prostředí, mapy, rozhodný okamžik, osoby a pořadí úkonů podle přílohy. Pro malý oddíl preferovat krátkou řízenou uzávěru dotčených agend před složitým obousměrným synchronizováním. Ostatní části KIS nemusí stát, pokud jsou prokazatelně oddělené.

Zachytit poslední změny od zkoušky, naplánovat uzávěru starého příjmu objednávek/přihlášek, fyzický sklad a peníze na cestě. Fronty a integrace inventarizovat: příchozí platební události nesmějí zmizet ani se zpracovat dvakrát. Připravit oznámení odstávky a přesměrování, ale neprovádět je tímto zadáním.

**Hotovo, když:** existuje kontrolovatelný balík k rozhodnutí „spustit / odložit“, včetně doby obnovy a konkrétních stop podmínek. P35.

### E6-15 — Provést převod a otevřít nové zápisy

**Máme:** ochrany pro nasazení kódu. Nasazení, migrace schématu, převod obchodních dat a aktivace integrace jsou odlišné zásahy. [Z2]

**Budoucí realizační úkol:** po výslovném schválení potvrdit uzávěru zápisů, zálohy a otisky; převzít poslední dávku; porovnat výsledek; nechat správce agend převzít jejich části. Zastavit při změně vstupu/cíle, nevysvětleném rozdílu nebo překročení časové hranice pro bezpečný návrat.

Nové zápisy otevřít až po kontrole aktivních případů, rolí, skladu, financí, souborů a nezbytných integrací. Staré systémy poté obsluhují jen výslovně určený doběh nebo čtení. Zaznamenat přesný okamžik a seznam případů; případné otevření po částech musí respektovat společné osoby, sklad, peníze a číselné řady.

**Hotovo, když:** protokol doloží správný cíl, verzi, dávku, přijetí agend a jedinou autoritu nových zápisů. P36–P37. Tento krok se nyní neprovádí.

### E6-16 — Připravit a nacvičit návrat při problému

**Máme:** databázovou obnovu jako řízený postup, nikoli automatickou součást deploye. Návrat starého kódu nevrací databázi. [Z3]

**Doplnit:** dvě varianty: před otevřením nových zápisů a po vzniku nových objednávek, peněz či docházky. Před otevřením lze použít ověřenou kompatibilní sadu, pokud prokazatelně nevznikly další účinky. Po otevření je nutné nejdřív zachovat nové záznamy a skutečné externí pohyby a rozhodnout o opravě vpřed nebo řízeném návratu s jejich převzetím.

Při incidentu neotevírat starý systém k nezávislému zápisu bez aktualizace stavu. Záloha nevrátí bankovní převod, odeslanou zásilku, e-mail nebo fyzicky vydané tričko. Uchovat stav po incidentu, uzamknout dotčené agendy, vést kontrolovanou náhradní evidenci a teprve podle konkrétního scénáře obnovovat. Části společné DB nelze vracet bez rozboru vazeb.

**Hotovo, když:** nacvičená obnova i návrat po nové transakci zachovají všechny potvrzené operace a správci znají rozhodovací pravomoc. P38.

### E6-17 — Stabilizovat provoz a převzít první účetní období

**Máme:** provozní přehledy a plánované kontrolní výstupy. Potřebujeme ověřit jejich používání po skutečném přechodu.

**Doplnit:** první pracovní dny denně zkontrolovat neúspěšné zprávy, nezpracované platby, rezervace, zápornou dostupnost, chybějící přílohy, otevřené zálohy a neuzavřenou docházku. Vést seznam závad s vlastníkem a rozsahem dopadu. Změny opravovat řízeně, ne ručními přepisy bez původu.

Přejmout první celé účetní předání podle etapy 2 a zkontrolovat doběh starých objednávek, opožděné platby a vratky. Počáteční stavy se nesmějí vykázat jako nové peníze. Zopakovat obnovu významně změněné sady, pokud se od generální zkoušky změnil rozsah dat/klíčů nebo technologie.

**Hotovo, když:** žádná otevřená závada P0, všechny použité agendy mají odpovědného uživatele a účetní přijala balík se shodnými saldy. P39.

### E6-18 — Archivovat a postupně odstavit staré systémy

**Máme:** staré systémy jako zdroj historie a možnost rozhodovat po agendách. Zastavení nových objednávek není totéž jako zrušení Shoptetu nebo vymazání Evidence.

**Doplnit:** dohledatelný chráněný archiv dat, původních dokladů, souborů, exportních manifestů a převodních map. Vyzkoušet přístup k historii bez závislosti na budoucím placení staré služby. Živý starý web s odemčeným zápisem není dlouhodobý archiv.

Před vypnutím vyřešit všechny doběhy, potřebné opravy starých dokladů, vratky a externí adresy. Potvrdit, kde se bude vyřizovat pozdější oprávněný požadavek ke starému nákupu. Ponechané domény a veřejná přesměrování spravovat i po ukončení aplikace. Přístupové údaje/integrace zrušit až po potvrzení, že už neslouží doběhu; data neodstraňovat automaticky s ukončením předplatného.

**Hotovo, když:** každá služba má samostatné rozhodnutí o odstavení, dostupný archiv a určenou odpovědnost. Teprve potom lze označit původní systémy za nahrazené. P40.

## Doporučené pořadí

1. **Rozsah a důkazy:** E6-01 až E6-04. Současně ověřit, které části etap 1–5 jsou skutečně hotové.
2. **Datové mapy a pravidla:** E6-05 až E6-09; sporné identity a peníze mají přednost před hromadným převodem historie.
3. **Nástroj a generální zkouška:** E6-10 až E6-12 a E6-16. Výstupem je ověřená doba převodu/obnovy a přijaté rozdíly.
4. **Omezený provoz a přepnutí:** E6-13 až E6-15 až po příslušném samostatném schválení. Před otevřením zkontrolovat poslední změny.
5. **Stabilizace a ukončení souběhu:** E6-17 a E6-18. Vypnutí starých služeb není součástí samotného nasazení.

## Rozhodnutí k uzavření před realizací

Vedení potvrdí seznam nahrazovaných služeb, vlastníky, rozsah historie a pilotu. Hospodář s účetní schválí finanční počátky a dobu souběhu předání. Skladník schválí inventuru a rezervace. Registrář a trenér schválí datové mapy. Technický správce doloží reálné časy převodu/obnovy a potřebné integrační vazby. Konkrétní termín odstávky se určí teprve z těchto podkladů.

Není potřeba znovu potvrzovat, že oddíl chce nahradit staré systémy. Před budoucím zápisovým zásahem se schvaluje konkrétní ověřený rozsah, cílová data a návratový postup. Toto zadání žádný z těchto zásahů neprovádí.
