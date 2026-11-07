# Etapa 1 — společný základ systému oddílu

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Zadání k 22. 10. 2026. Pouze návrh práce; bez implementace, importu, aktivace služeb a změn v produkci.

**Cílem etapy 1 je spolehlivá společná evidence osob, rodinné a pracovní přístupy, dohledatelné změny, doručování důležitých zpráv a obnovitelný provoz. Většinu stavebních částí již máme. Hlavní práce spočívá v jejich sjednocení, doplnění osobního vyřizování a prokázání funkčnosti celých postupů.**

Etapa 1 sama nenahrazuje Shoptet ani původní Evidenci. Doklady a účetní export patří do etapy 2, kompletní skladové postupy do etapy 3, zbývající sportovní a členská agenda do etapy 4, výdaje/zálohy/odměny do etapy 5 a přenos historie a odstavení starých systémů do etapy 6.

## Podklady a význam hodnocení

- Navazuje na [etapu 0](../etapa-0-2026-10-22/00_ETAPA_0.md), včetně potvrzených papírových agend a jednoduchého účetnictví neplátce DPH.
- Zdrojový kód byl posouzen na čerstvě ověřeném `origin/main`, revize `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. Místní rozpracovaný checkout se od něj liší; při tomto zadání nebyl měněn.
- „Máme“ znamená existující implementaci v této revizi, pokud není výslovně uvedeno měření etapy 0. Neznamená automaticky produkční zapnutí ani úspěšnou uživatelskou přejímku.
- Provozní nastavení, aktuální běh plánovačů, doručení do schránky a obnovu produkčních dat tato příprava znovu neověřovala. Jde o samostatné přejímací výstupy.
- „Doplnit“ označuje požadovaný výsledek. U bodů označených **ověřit** nejprve vznikne důkaz; kód se upravuje pouze při prokázané mezeře. Nebudeme stavět druhou správu osob, rolí, souhlasů ani další samostatný rodinný portál.

## Priority a pořadí

**P0:** podmínka bezpečného pilotu společného systému. **P1:** podmínka uzavření etapy 1; lze dokončovat po zprovoznění základů v odděleném testovacím prostředí. Volitelné automatické týdenní souhrny nejsou podmínkou dokončení.

Vlastníci jsou navržené odpovědné role, nikoli již přidělené osoby. Jeden člověk může zastávat více rolí.

| ID | Priorita | Úkol | Povaha práce | Předchůdci | Odpovědnost |
|---|---|---|---|---|---|
| E1-01 | P0 | Potvrdit základ a izolované ověřovací prostředí | Ujasnit a ověřit | Výstupy etapy 0 | Správce systému + vedení |
| E1-02 | P0 | Jedna osoba a bezpečné řešení shod | Sjednotit a doplnit | E1-01; pro skutečné identity E0-04/05 | Registrář + vývoj |
| E1-03 | P0 | Oddělit členství, účast, účet a placení | Ujasnit, propojit, ověřit | E1-02 | Registrář + vedoucí sportu |
| E1-04 | P0 | Dokončit rodinné přístupy a osobní vyřízení | Propojit a ověřit | E1-02, E1-03; doručení E1-07 | Registrář + vývoj |
| E1-05 | P0 | Pracovní oprávnění a odchod pracovníka | Ověřit a případně opravit | E1-01, pravidla E1-02/03 | Vedení + správce systému |
| E1-06 | P1 | Papírové přihlášky, souhlasy a soukromé přílohy | Doplnit a sjednotit | E1-02, E1-04, E1-05, E0-03 | Registrář + vedení |
| E1-07 | P0 | Spolehlivé provozní zprávy včetně starších rezervací | Doplnit a ověřit | E1-01; pravidla příjemců E1-02/05 | Správce systému + vývoj |
| E1-08 | P0 | Přehled plánovaných úloh a dohled nad chybami | Doplnit a ověřit | E1-01, E1-07 | Správce systému |
| E1-09 | P0 | Úplná záloha, obnova a postup při výpadku | Ověřit a doplnit | E1-01 | Správce systému + vedení |
| E1-10 | P1 | Audit a práce s nevyřešenými případy | Rozšířit existující přehledy | E1-02 až E1-08 | Registrář + správce systému |
| E1-11 | P1 | Srozumitelný společný přehled pro rodinu a obsluhu | Propojit existující obrazovky | E1-03, E1-04, E1-06, E1-10 | Registrář + uživatelský garant |
| E1-12 | P0 | Přejímka a rozhodnutí o připravenosti pilotu | Ověřit a předat | Všechny předchozí úkoly | Vedení + správci agend |

E1-04 potřebuje doručování z E1-07, ale jeho návrh lze připravovat souběžně. E1-07 nepotřebuje dokončené rodinné UI. U E1-05 stačí nejprve schválená pravidla identity a stavů, nemusí čekat na hotové obrazovky.

**Doporučené pracovní vlny:**

1. **A — základ a pravidla:** E1-01; následně návrh E1-02/03/05 a ověření E1-09. Současně inventura e-mailových cest a plánovačů pro E1-07/08.
2. **B — dokončení hlavních postupů:** E1-02 až E1-05, E1-07/08 a zjištěné nedostatky E1-09. Až po pravidlech přístupů E1-06.
3. **C — obsluha a přejímka:** E1-10/11, souvislé scénáře E1-12 a protokol otevřených bodů. Produkční zapnutí je následný samostatný krok, nikoli součást tohoto zadání.

## Konkrétní úkoly

### E1-01 — Potvrdit základ a izolované ověřovací prostředí

**Máme:** jednu aplikaci Evidence/e-shop/KIS, společnou databázi, migrace, automatické testy, místní ověřovací postupy a dokumentaci nasazení. Máme také podrobné zadání etapy 0. [Z1, Z12]

**Potřeba doplnit / ověřit:**

- Zapsat výchozí revizi kódu, stav schématu a skutečně zapnuté funkce zvlášť pro testovací prostředí a produkci. Rozpory starší dokumentace vyřešit podle aktuálních důkazů.
- Určit jmenovitého vlastníka každé agendy a zástupce: registrář, sport, obchod/sklad, hospodář, technická správa. Jde o přiřazení k existujícím pracovním pozicím.
- Připravit oddělenou databázi, soukromé úložiště a místní zachytávání zpráv se syntetickými rodinami. Ověřit, že žádná testovací objednávka, notifikace ani plánovač nepoužije ostrá data či příjemce.
- Založit seznam rozhodnutí a otevřených závad; oddělit „implementováno“, „ověřeno lokálně“, „nasazeno“ a „převzato obsluhou“.

**Hotovo, když:** existuje reprodukovatelný výchozí stav, seznam vlastníků a důkaz oddělení testu od produkce. Chybějící účetní program neblokuje tuto práci.

### E1-02 — Jedna osoba a bezpečné řešení shod

**Máme:** kanonické osoby `sportovci`, editaci profilu a zdrojových údajů, účty oddělené od osob, vazby účtu na osobu, posouzení registrační žádosti a rozhodnutí „spojit s existující osobou / založit novou“. Existují importní zdrojové vazby a přehledy konfliktů. [Z2, Z3]

**Potřeba doplnit / ověřit:**

- Sepsat závaznou identifikaci: interní ID osoby je stabilní; e-mail, UCI, VS ani jméno jej nenahrazují. Účet zákazníka nemusí znamenat členství a nákup oblečení nesmí sám zakládat sportovce.
- Určit autoritativní zdroj jednotlivých údajů a pravidla opravy: aktuální kontakty, narození, historická identita, členské zařazení. Neodvozovat datum narození odhadem; zástupné datum se nesmí tvářit jako ověřené.
- Sjednotit kontrolu možné duplicity ve všech používaných cestách založení, včetně osobního vyřízení. Registrář má vidět kandidáty a důvod shody; rozhodnutí má obsahovat původ, autora a odůvodnění.
- Připravit převodní mapu `zdrojový systém + zdrojové ID → cílové ID + stav ověření + důkaz rozhodnutí`. Zde její kontrakt a omezený ověřený pilotní vzorek; celý přenos historie až v etapě 6.
- Nejasné případy odložit do řešení. Obecné sloučení dvou osob se všemi finančními a sportovními vazbami není v této přípravě prokázané; případnou potřebu posoudit jednotlivě. Hromadné slučování není podmínkou etapy 1.

**Hotovo, když:** opakovaná přihláška nevytvoří bez upozornění druhou osobu, shodný e-mail nedá přístup k cizímu dítěti a nejasná shoda zůstane nevyřešená. U pilotních osob je známé cílové ID a původ rozhodnutí.

**Důležitá hranice:** existující úplný import osob je nahrazovací postup, nikoli bezpečný nástroj pro doplnění historie. Nepoužívat jej jako obecný převod Evidence → KIS.

### E1-03 — Oddělit členství, účast, účet a placení

**Máme:** stav osoby, aktivitu a ověření účtu, platné vztahy účtu k osobě, sezony a soupisky, přihlášky a účast v programech, přehled úrovní zapojení i samostatné platební předpisy. [Z2, Z4, Z5]

**Potřeba doplnit / ověřit:**

- Schválit jednoduchou tabulku významů: účet aktivní/neaktivní; vztah rodiče čeká/schválen/ukončen; členství podle rozhodnutí oddílu; zařazení v konkrétní sezoně; účast v kurzu; úhrada konkrétní povinnosti. Žádný jediný příznak „aktivní“ nesmí zastupovat všechny tyto významy.
- Určit pro každý přechod kdo, na základě čeho a od kdy jej může provést. Ukončení soupisky nesmí smazat historii, odebrání přístupu není storno nákupu a zaplacení zboží není přijetí do oddílu.
- Prověřit, zda názvy, filtry a odvozené přehledy používají tato pravidla shodně. Opravit pouze zjištěné rozpory.
- V kontrolním seznamu oddělit chybějící údaj od neplatného údaje a od údaje, který daný typ osoby vůbec nepotřebuje. U historicky importovaných stavů zobrazit nejistotu místo automatického uznání členství.

**Hotovo, když:** stejná pilotní osoba má ve všech přehledech vysvětlitelné stavy; nákup, přihláška, členství a přístup se navzájem nemění nechtěným vedlejším účinkem.

### E1-04 — Rodinné přístupy a osobní vyřízení

**Máme:** rodiče s více dětmi, schvalované a odvolatelné vztahy `self/guardian`, žádosti o propojení, schválení či zamítnutí registrace, samostatné sportovní účty, ověření e-mailu a jednorázové tokeny. Rodinný přehled vybírá jen schválené a aktuálně platné vztahy aktivního ověřeného účtu. [Z2, Z3, Z5, Z6]

**Potřeba doplnit / ověřit:**

- Projít bez přerušení cestu nový rodič → ověření → dítě / žádost o propojení → rozhodnutí registráře → dostupný rodinný program. Zahrnout druhého rodiče, více dětí, samostatného dospělého a zákazníka bez členství.
- Popsat a doplnit chybějící kroky osobního vyřízení: pracovník zaznamená žádost a doložení vztahu; nevytváří souhlas ani ověření e-mailu jménem rodiče. Kontaktní osoba z importu se nestává automaticky oprávněným rodičem.
- Ověřit odvolání vztahu na profilu, docházce, omluvách, soukromých souborech, odkazech i již připravených zprávách. Práva k vlastní objednávce posuzovat zvlášť podle kupujícího a platebního vztahu; neodvozovat je automaticky z rodičovství.
- Ověřit opakované poslání ověřovacího/resetovacího odkazu, starý a expirovaný odkaz, zablokovaný účet a změnu e-mailu. Postup při ztrátě přístupu musí mít ověření obsluhou a audit.
- Jeden sdílený e-mail nesmí vytvořit dvě nerozlišitelné identity. Nabídnout srozumitelný postup pro rodinu; druhého samostatného rodiče vést pod jeho vlastním účtem.

**Hotovo, když:** rodina zvládne celý postup, registrář vyřeší osobní případ a po odebrání vztahu nepokračuje přístup k chráněným údajům dítěte. Všechny zásadní kroky jsou dohledatelné.

### E1-05 — Pracovní oprávnění a odchod pracovníka

**Máme:** registr pracovních pozic a jejich obrazovek, přepínání pracovního kontextu, kontroly session, změny verze session a auditovanou aktivaci/deaktivaci pracovních účtů. Deaktivace již existuje; starší dokumentace může tvrdit opak. [Z7]

**Potřeba doplnit / ověřit:**

- Zpracovat matici role × akce × rozsah dat. Rozlišit např. trenér své skupiny, vedoucí sportu více skupin, registrář identity a vztahy, skladník výdej, hospodář platby, správce technický provoz.
- U citlivých akcí testovat serverové oprávnění i přímý odkaz/požadavek: přístup k jiné skupině, osobě, platbě, exportu a příloze. Skrytá položka menu není důkaz ochrany.
- Prověřit účty s více pozicemi a osobu, která je současně rodičem i trenérem. Ztráta pracovního oprávnění má zachovat případná oprávněná soukromá práva, ale nikoli pracovní přístup.
- Připravit odchod pracovníka: odebrání pozice či deaktivace, zneplatnění rozpracovaných přístupů, předání nevyřešených případů a potvrzení vlastníka agendy.

**Hotovo, když:** podepsaná matice odpovídá skutečnému chování v testu a změna práv se projeví i v již otevřené relaci při dalším požadavku. Nevytváří se druhý systém rolí.

### E1-06 — Papírové přihlášky, souhlasy a soukromé přílohy

**Máme:** verze registračních textů, koncept/publikaci, snapshoty odpovědí při nákupu kroužku a vlastní povinné/volitelné souhlasy. Vlastní souhlasy pro kroužky nejsou automaticky součástí obecné registrace osoby. Existuje ochrana citlivých údajů, audit jejich zobrazení a operace s privátními soubory. [Z8]

**Potřeba doplnit / ověřit:**

- Rozdělit formuláře podle účelu: členství, kurz/kroužek a jednotlivá akce. Určit, které potvrzení kam patří; nekopírovat všechny souhlasy na všechny formuláře.
- Přidat jednotný postup evidence osobně předané přihlášky/souhlasu tam, kde dnes chybí: osoba, podepisující a jeho vztah, dokument a verze, datum skutečného podpisu, datum zaevidování, pracovník a odkaz na důkaz nebo umístění originálu.
- U starého papíru bez identifikovatelné verze zachovat „verze neověřena“. Zpětným přepisem z něj nevytvářet dnešní online souhlas. Oprava či odvolání vytváří novou auditovanou událost.
- Určit přístup k přílohám a dobu uchování podle účelu; zapojit existující soukromé úložiště, zabránit přímému veřejnému stažení a zahrnout soubory do obnovy E1-09.
- Pro zdravotní prohlídky nyní vymezit jen přístupová práva, zdroj a princip evidence platnosti. Samotný úplný zdravotní workflow patří do etapy 4; diagnózy nemají být součástí běžné členské karty.

**Hotovo, když:** u online i papírového případu lze určit kdo, co a kdy potvrdil a kde je důkaz. Nová verze nepřepíše historii; běžný pracovník nestáhne nepovolenou přílohu. Seznam skutečných papírů a jejich správců doplní E0-03.

### E1-07 — Spolehlivé provozní zprávy

**Máme:** fronty pro ověřovací/resetovací zprávy a klubové události, deduplikaci, pokusy/opakování, diagnostiku, oznámení rozhodnutí o propojení a další doménové zprávy. Existuje i komunikace se soupiskami a volitelný týdenní souhrn. [Z6, Z9]

**Prokázaná mezera:** `booking/rezervovat.php` a `booking/waiting_list.php` obsahují přímé `@mail(...)`. Tyto konkrétní cesty potřebují trvalou evidenci požadavku a řízené zpracování. [Z9]

**Potřeba doplnit / ověřit:**

- Sestavit katalog zpráv: spouštěcí událost, příjemce, účel, jedinečný identifikátor události, priorita, podmínky platnosti a odpovědnost při chybě. Zahrnout rezervaci/storno/čekací listinu, změnu či zrušení lekce, rozhodnutí o přihlášce, ověření/reset a dostupné objednávkové zprávy.
- Doplnit zbývající přímé odesílání do trvalé fronty se stavem, historií pokusů a odůvodněným opakováním. Jednotné chování nevyžaduje násilné sloučení všech existujících front do jedné tabulky.
- Před skutečným odesláním znovu ověřit platnost tokenu, události a oprávnění příjemce. Zpráva připravená před zrušením přihlášky nebo odvoláním rodičovského vztahu nesmí poslat zastaralé chráněné údaje.
- Odlišit vložení do fronty, převzetí transportem a skutečné doručení. Stav „odesláno“ nesmí slibovat přečtení ani doručení do schránky.
- Pro účet bez dostupného e-mailu ukázat obsluze nutnost osobního kontaktu a možnost zaznamenat jeho výsledek. Nepřidávat povinný SMS kanál.

**Hotovo, když:** chyba transportu neztratí událost; opakované zpracování stejné události nevytvoří druhý obchodní úkon ani duplicitní frontovou položku. Nejasný výsledek odeslání má dohledatelný stav a řízené řešení. Zkouška doručení do určené testovací schránky je zvláštní důkaz po autorizaci daného prostředí.

**Mimo povinné minimum:** zapnutí týdenního souhrnu a další hromadné rozesílky. Z existence jejich kódu neplyne oprávnění je aktivovat.

### E1-08 — Plánované úlohy a provozní dohled

**Máme:** pracovní procesy pro zprávy, skripty připomínek a souhrnů, plánované workflow pro vybrané agendy a konfigurační přepínače. Diagnostika transakčních zpráv umí ukázat stav front, ale sama vrací `scheduler_verified=false` a `mailbox_delivery_verified=false`. [Z9, Z10]

**Potřeba doplnit / ověřit:**

- Vytvořit provozní seznam všech úloh bez ohledu na to, zda běží na hostingu nebo v GitHub Actions: účel, interval, vlastník, přepínač, poslední začátek/úspěch, počet zpracovaných a chybných položek, délka běhu a další očekávaný běh.
- Rozlišit „záměrně vypnuto“, „běží v pořádku“, „čeká na konfiguraci“ a „selhává / neběží včas“. Absence aktivace není totéž co incident.
- Ověřit souběžné spuštění, opakování stejné dávky a obnovu po přerušení pro úlohy potřebné k pilotu. Limity a způsob opakování určit podle konkrétní agendy.
- Do existujícího provozního přehledu přidat chybějící technické signály a instrukci, kdo je řeší. Určit toleranci zpoždění a způsob upozornění provozního správce.
- Samostatně evidovat obchodně významné automatizace, například bankovní import, párování, připomínky nebo generování termínů. Tento úkol je automaticky nezapíná.

**Hotovo, když:** správce pozná nefunkční plánovač i při dostupném webu, najde poslední výsledek a umí bezpečně pokračovat po chybě. Každá pro pilot potřebná úloha má ověřený běh, nebo výslovně přijatý ruční postup s vlastníkem a četností.

### E1-09 — Záloha, obnova a výpadek

**Máme:** databázový zálohovací skript s vlastnictvím tabulek, manifestem a kontrolami a test skutečné obnovy na MariaDB. To je silný základ; není to samo o sobě důkaz obnovy celé ostré instalace včetně souborů a šifrovacích klíčů. [Z11]

**Potřeba doplnit / ověřit:**

- Sestavit úplný seznam pro obnovu: databáze, soukromé přílohy, potřebné nahrané soubory, konfigurace a bezpečně dostupné šifrovací klíče. Klíče se nezapisují do tohoto plánu ani běžného reportu.
- Dohodnout nejvyšší přijatelnou ztrátu dat a dobu obnovení. Návrh pro malý oddíl: obnova provozu do jednoho pracovního dne; přípustná ztráta objednávek, hotovosti a skladových pohybů musí být stanovena zvlášť před finančním pilotem. Samotná denní záloha nemusí těmto agendám stačit.
- Ověřit plán, úspěšnost, oddělené uložení a dostupnost kopií; vyzkoušet obnovu do izolovaného prostředí. U soukromých souborů ověřit obsah i přístupová práva a schopnost přečíst zašifrované údaje.
- Připravit postup výpadku: číslovaná dočasná evidence osobního vyřízení, plateb a výdejů; určená osoba pro následné doplnění a kontrolu duplicit. Nepovažovat výpadek za možnost opravovat stav z paměti.

**Hotovo, když:** existuje datovaný protokol obnovy s naměřeným časem, rozsahem a kontrolami a vedení přijalo provozní limity. Skutečnou obnovu nelze nahradit důkazem, že soubor zálohy existuje.

### E1-10 — Audit a nevyřešené případy

**Máme:** auditní osu osoby a doménové historie vztahů, registrací, pracovních účtů i citlivých údajů. Provozní přehled už počítá čekající žádosti, problémy plateb a kapacit, chyby zpráv a další výjimky. [Z7, Z8, Z10]

**Potřeba doplnit / ověřit:**

- Zkontrolovat pokrytí rozhodnutí E1-02 až E1-08: kdo, kdy, co, důvod, vazba na případ a nezbytné před/po. Chybějící události přidat do stávajících auditů.
- U nevyřešeného případu určit vlastníka, další krok, datum kontroly a způsob uzavření. Začít žádostmi o osobu/rodiče, možnými duplicitami, neověřeným papírovým dokladem a selhanou důležitou zprávou.
- Rozšířit stávající přehled a odkazy na konkrétní rozhodovací obrazovky; nevytvářet paralelní registr plateb či osob a obecné tlačítko, které obchází doménová pravidla.
- Zajistit přiměřený obsah logů: žádné resetovací tokeny, hesla či zbytečné zdravotní a osobní údaje. Důkaz k citlivému případu má být přístupný jen příslušné roli.

**Hotovo, když:** náhradník obsluhy z přehledu pozná, co čeká, kdo to řeší a na základě čeho se rozhodlo. Po dokončení se případ uzavře bez ztráty historie.

### E1-11 — Společný přehled pro rodinu a obsluhu

**Máme:** rodinný přehled oprávněných osob, jejich soupisek, akcí, tréninků a předpisů; rodinný program a existující pracovní rozcestníky. [Z5, Z7]

**Potřeba doplnit / ověřit:**

- Propojit existující stránky do srozumitelné cesty „moje rodina → co má kdo naplánováno → co je potřeba vyřídit“. U více dětí musí být jednoznačně vidět, koho se konkrétní akce týká.
- Vysvětlit čekající schválení, neověřený účet a prázdnou historii. Chybějící převod historie z Evidence nesmí být prezentován jako tvrzení, že sportovec nikdy netrénoval.
- Částky přebírat z autoritativních platebních záznamů. Odlišit předpis, objednávku, skutečnou úhradu, odměnu a náhradní lekci; netvořit společný domnělý „kredit“.
- Registráři zpřístupnit kontrolní seznam pro osobní vyřízení a odkaz na nevyřešené případy. Ověřit běžné úkoly na telefonu bez širšího redesignu celého webu.

**Hotovo, když:** rodič i pracovník najdou další nutný krok bez znalosti interního členění Evidence/KIS/e-shop. Portál neslibuje dosud nepřevedenou historii nebo budoucí účetní doklady.

### E1-12 — Přejímka a předání

**Máme:** automatické testy vztahů osob, session, rodinného portálu, pracovních účtů, auditní osy, zpráv a databázové obnovy. Jejich přítomnost v repozitáři není důkazem nové přejímky všech postupů. [Z12]

**Potřeba doplnit / ověřit:**

- Projít [konkrétní přejímací scénáře](01_PRIJEMKA_A_ZDROJE.md) na pevné revizi v izolovaném prostředí. Použít existující testy; přidávat jen kontroly skutečně doplněného chování nebo prokázaného rizika.
- U každého výsledku zaznamenat revizi, prostředí, datum, zkoušejícího, výsledek a odkaz na důkaz bez osobních údajů. Oddělit ověření zdrojového kódu od běhu na cílovém prostředí.
- Předat obsluze krátké postupy: nová rodina, osobní přihláška, nejasná shoda, odebrání přístupu, nedoručená zpráva a výpadek.
- Uzavřít P0 i povinné P1. Otevřené body musí mít dopad, vlastníka a cílovou etapu; nepřesouvat nefunkční ochranu dat nebo ztrácení základních zpráv do neurčitého „později“.

**Hotovo, když:** registrář, správce systému a uživatelský garant převezmou souvislé postupy a vedení dostane konkrétní seznam připravených funkcí i omezení. Příprava protokolu neznamená provedení pilotu ani autorizaci produkčních zápisů.

## Návaznost na etapu 0 a další etapy

| Otevřený bod | Dopad na etapu 1 |
|---|---|
| E0-01/02: účetní program, metodika a vzor exportu | Neblokují identity, přístupy a provoz; blokují definitivní podobu účetního exportu v etapě 2. |
| E0-03: vlastníci a konkrétní podoba papírových agend | Nutné pro úplné E1-06 a postup osobního vyřízení. Rozsah základních agend už uživatel potvrdil. |
| E0-04/05: úplná inventura a ověření identit | Nutné pro práci se skutečnými převodními vazbami. Návrh a syntetické ověření E1 mohou probíhat nyní; ostrý pilot vyžaduje ověřené osoby svého rozsahu. |
| E0-06: Harrachov a finanční zůstatky | Nerozhodovat v etapě 1. Sporné částky nesmějí být prezentované jako ověřené počáteční zůstatky. |
| E0-07: migrační kontrakt | Podmínka pozdějšího převodu a vypnutí Evidence, nikoli celé přípravy etapy 1. |

Měření etapy 0 našlo 1 242 jednoznačných kandidátních shod osob a 81 zdrojových osob s tréninky bez jednoznačné shody použitou metodou. **Kandidát není ověřená identita.** Vyřešení celé historické populace není nutné před dokončením syntetické přejímky etapy 1; všechny osoby zahrnuté do skutečného pilotu však musí mít bezpečně vyřešenou identitu a oprávnění. Převod zbývající historie se řídí etapou 6.

## Co musí etapa 1 předat dál

- Etapě 2: stabilní ID osoby, kupující a plátce jako rozlišitelné vztahy, oprávnění hospodáře, audit a provozní podmínky; zachované dosavadní VS. Nevytváří nové faktury ani účetní export.
- Etapě 3: oprávnění skladníka/výdeje, dohledatelného pracovníka osobního předání a postup výpadku. Neřeší ještě plnou inventuru a skladové oceňování.
- Etapám 4/5: spolehlivou identitu, sezonní zařazení, evidenci původu dokumentu a pracovní odpovědnosti. Nedělá nový modul zdravotních prohlídek, výdajů, odměn ani půjčování vybavení.
- Etapě 6: pravidla mapování, audit, ověřenou obnovu a postup přejímky. Neprovádí hromadný import a nevypíná stávající služby.

**Doporučený první budoucí pracovní balík:** E1-01, pravidla E1-02/03, matice E1-05 a inventura obnovy E1-09. Teprve nad tím doplnit prokázané mezery, zejména starší přímé e-maily a osobní evidenci přihlášek/souhlasů. Odhad pracnosti má smysl po oddělení těchto skutečných úprav od funkcí, u kterých stačí přejímka.
