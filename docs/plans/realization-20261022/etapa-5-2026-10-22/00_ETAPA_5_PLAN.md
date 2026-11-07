# Etapa 5 — klubová administrativa, výdaje, zálohy a odměny

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Zadání k 22. 10. 2026. Pouze příprava; bez implementace, změny finančních záznamů, importu, výplat a produkce.

**Účtenky, provozní akce, jednoduché vyúčtování zálohy, výkazy trenérů i odměnová období sportovců již existují. Pro úplný provoz potřebujeme doplnit jejich schvalování, konkrétního věřitele a příjemce peněz, průkazné vypořádání, ochranu uzavřených podkladů a společné předání účetní.**

Cílový postup: požadavek nebo vznik výdaje → doložení → věcné schválení → závazek či vyúčtování zálohy → skutečná platba/vratka → kontrola zůstatku → účetní předání. U odměn předchází ověřená činnost, použité pravidlo a schválení částky. Přepis papíru a osobní vyřízení musí mít stejnou průkaznost jako online postup.

## Rozsah a návaznosti

Navazuje na [etapu 0](../etapa-0-2026-10-22/00_ETAPA_0.md), [etapu 1](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md), [etapu 2](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md), [etapu 3](../etapa-3-2026-10-22/00_ETAPA_3_PLAN.md) a [etapu 4](../etapa-4-2026-10-22/00_ETAPA_4_PLAN.md). Podrobná pravidla, příklady, 40 přejímacích scénářů a zdroje jsou v [příloze](01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md).

„Máme“ znamená implementaci ověřenou ve zdrojové revizi `origin/main` **68b22bd2b3b666dbb4fb571a2731eae5b7f056d9**. Produkční konfigurace ani skutečné peněžní zůstatky nebyly v této etapě nově ověřovány. Datované srovnání Evidence/KIS přebíráme z etapy 0, nikoli jako nové měření.

- **Etapa 1:** identity, oprávnění, soukromé podklady, audit, úkoly k dořešení, notifikace a obnova.
- **Etapa 2:** jednotná evidence dokladů, peněz, závazků, pokladny/banky, oprav a účetního exportu. Etapa 5 na ni navazuje; nevytváří druhou pokladnu ani druhý registr peněz.
- **Etapa 3:** nákup zboží může navazovat na skladovou příjemku. Zaplacení dodavateli a fyzické naskladnění jsou dvě propojené události.
- **Etapa 4:** doložená docházka, činnost trenérů, osoby, soupisky a klubové akce. Etapa 5 přebírá podklady, schvaluje odměny a předává finanční důvod etapě 2.
- **Etapa 6:** ostrý převod, počáteční stavy a přepnutí provozu. Zde připravujeme jeho kontrakt a kontrolní podmínky.

Oddíl podle potvrzení uživatele vede **jednoduché účetnictví, není plátcem DPH a účetní dostává XLSX + faktury**. Přesný program účetní a metodiku odměn dosud neznáme. KIS má dodávat úplné podklady; výpočet mezd, odvodů a právní posouzení druhu odměny se tímto nezadávají. Peněžní deník, pohledávky, závazky a majetkové podklady mají rozdílný význam i v jednoduchém účetnictví. [Finanční správa — obecné informace](https://financnisprava.gov.cz/cs/dane/dane/dan-z-prijmu/ucetnictvi/obecne-informace).

Potvrzené jsou výdaje a zálohy na akce i odměny trenérů a sportovců. Cestovní náhrady, obecný registr smluv a dotační agenda se zařazují jen v používaném rozsahu; jejich úplné vedení nebylo potvrzeno. Půjčování vybavení zůstává mimo rozsah.

## Co už máme a co doplnit

| Oblast | Existující základ | Zbývající práce |
|---|---|---|
| Účtenky | Částka, datum, kategorie, akce/vozidlo, osoba, soukromá příloha, audit | Dodavatel a číslo dokladu, skutečný plátce, věřitel, splatnost, schválení, duplicity, položkové rozdělení |
| Zdroj úhrady | Formulář rozlišuje klubovou kartu/hotovost a vlastní kartu/hotovost | Konkrétní osoba/účet, doložení platby a zůstatek k proplacení; samotný výběr metody není peněžní pohyb |
| Provozní akce | `ucto_udalosti`, otevření/uzavření, částka zálohy a text osoby | Vazba na klubový kalendář, odpovědný vedoucí, více záloh a příjemců, kontrolovaná uzávěrka |
| Vyúčtování | Záloha minus součet účtenek označených jako klubová hotovost | Skutečné příděly ze zálohy, uznané/neuznané položky, vratka/doplatek, zůstatek a schválení |
| Výkazy trenérů | Tréninky, další činnosti, vlastní a souhrnný měsíční přehled, XLSX | Předložení, kontrola, schválení verze, odměnové pravidlo a návaznost na účetní |
| Odměny sportovců | Sazba za trénink, období, živý výpočet; při uzavření uložený počet a částka | Verze sazeb, ochrana uzavření, konkrétní podklad a schválení, skutečná výplata místo pouhého příznaku |
| Jízdy a vozidla | Zahájení/ukončení, řidič, tachometr, polohy a audit | Účel/akce, doplnění papíru a případné schválené cestovní vyúčtování |
| Pracovní práva a přílohy | Pracovní pozice, kontroly přístupu, soukromé stahování účtenek | Práva podle úkonu a vlastníka případu, důsledná ochrana schválených podkladů ve všech starších cestách |
| Předání účetní | Jednotlivé exporty a připravený kontrakt etapy 2 | Úplný propojený balík včetně závazků, záloh, výplat, otevřených případů a opravných verzí |

## Priority a závislosti

**P0** znamená podmínku převzetí používané agendy do KIS. **P1** je navazující doplnění; pokud oddíl danou agendu reálně používá, musí být hotová před vypnutím její původní evidence. Role v tabulce jsou odpovědnosti, nikoli požadavek zaměstnat další lidi.

| Úkol | Priorita | Závislosti | Přejímá |
|---|---|---|---|
| E5-01 Odpovědnosti a schvalovací pravidla | P0 | E1-05, E1-10; E2-01 | Vedení + hospodář |
| E5-02 Jedna akce a její finanční přehled | P0 pro akce | E5-01; E4-08; E2-03 | Vedoucí akce + hospodář |
| E5-03 Výdaj a průkazný podklad | P0 | E5-01; E1-06; E2-09, E2-13 | Hospodář |
| E5-04 Schválení výdaje a proplacení | P0 | E5-03, E5-07 | Schvalovatel + hospodář |
| E5-05 Předání a evidence záloh | P0 | E5-02, E5-07 | Hospodář + příjemce |
| E5-06 Vyúčtování a uzavření zálohy | P0 | E5-04, E5-05 | Vedoucí akce + hospodář |
| E5-07 Vazba na skutečné peníze | P0 | E5-01; E2-02, E2-07 až E2-09 | Hospodář |
| E5-08 Předložení a schválení výkazu | P0 pro odměny trenérů | E5-01; E4-06, E4-12 | Trenér + vedoucí sportu |
| E5-09 Odměny trenérů a dalších pracovníků | P0 pro používané odměny | E5-08, E5-07; E2-09 | Vedoucí sportu + účetní |
| E5-10 Odměny sportovců | P0 pro používané odměny | E5-01, E5-07; E4-02, E4-06, E4-12 | Vedoucí sportu + hospodář |
| E5-11 Jízdy a cestovní podklady | P1, podmíněně | E5-02 až E5-04; E2-09 | Správce provozu + účetní |
| E5-12 Klubové úkoly, rozhodnutí a smluvní podklady | P0 pro finanční rozhodnutí; ostatní P1 | E5-01; E1-06 až E1-08, E1-10 | Vedení |
| E5-13 Měsíční předání a přehled závazků | P0 | E5-04, E5-06, E5-09, E5-10; E2-11, E2-12 | Hospodář + účetní |
| E5-14 Opravy, archiv a přístupy | P0, průběžně | E5-01; E1-05, E1-09, E1-10; E2-13 | Hospodář + správce |
| E5-15 Otevřené stavy a převod historie | P0 před přepnutím | E0-04 až E0-07; E5-03 až E5-10; E4-13 | Správce dat + hospodář |
| E5-16 Přejímka běžného měsíce | P0 | Používané agendy E5-01 až E5-15; E2-15 | Vedení a skutečná obsluha |

### E5-01 — Určit odpovědnosti a schvalovací pravidla

**Máme:** pracovní pozice, přístupová oprávnění a auditní nástroje. Výdajové stránky kontrolují oprávnění k účtenkám/událostem. Správa období a přehled odměn mají samostatné kontroly; v obsluze hromadné změny sazeb není obdobná explicitní kontrola `canAccess()`. [Z1, Z6, Z7]

**Doplnit:** matice úkonů: navrhnout, doložit, věcně schválit, připravit úhradu, potvrdit provedení, předat účetní a opravovat uzavřené. Určit schvalovatele, zastupování a případné limity podle skutečného rozhodnutí vedení. Nezavádět libovolně zvolené finanční limity.

Pro malý oddíl stačí krátký schvalovací postup. Vlastní odměnu nebo vlastní proplacení má schválit jiná pověřená osoba; potřebnou výjimku vedení výslovně určí a doloží. Samotná pracovní pozice nebo úspěšné přihlášení nesmí povolit libovolnou finanční změnu. Pravidla vynutit i přímým požadavkem mimo menu.

**Hotovo, když:** každý úkon má oprávněnou roli a dohledatelnou odpovědnost; změna schváleného podkladu vyvolá nové posouzení. P01–P03.

### E5-02 — Propojit klubovou akci s finančním případem

**Máme:** klubové události a registrace v `club_events` i samostatné provozní akce v `ucto_udalosti`, na něž odkazují účtenky. Jsou to rozdílné evidence; shodný název nebo číselné ID není jejich ověřená vazba. [Z2, Z3]

**Doplnit:** explicitní vazbu sportovní akce a jejího finančního případu, vedoucího, hospodáře, období, střediska/činnosti a termínu vyúčtování. Běžný výdaj může existovat i bez akce. Jednoduchý plán nákladů a schválený limit akce je užitečný P1; není podmínkou zadání každé účtenky.

Přehled oddělí plánované náklady, doložené doklady, uznanou část, skutečně zaplacené částky, otevřené závazky a zálohy. Platby účastníků se převezmou z kanonického zdroje etapy 2, nevytvoří se nový příjem přepisem do akce. Ukončení sportovní akce není finanční uzavření.

**Hotovo, když:** vedoucí dohledá všechny související doklady a zálohy jedné akce; její storno zachová skutečné výdaje i otevřené vypořádání. P04–P05.

### E5-03 — Dokončit evidenci výdajů a jejich podkladů

**Máme:** účtenku s částkou, datem, kategorií, akcí/vozidlem, nahrávající osobou, poznámkou a soukromým souborem; uložení je transakční a auditované. Formulář již rozlišuje klubovou a vlastní kartu/hotovost. [Z1]

**Doplnit v návaznosti na E2-09:** dodavatele, číslo a typ dokladu, datum případu/vystavení/splatnosti, původní měnu a částku, účel a účetní členění. U každého výdaje rozlišit nahrávajícího, skutečného plátce, věřitele a případného příjemce náhrady. Text „nahráno jménem“ není ověřený příjemce výplaty.

Přidat kontrolu možné duplicity podle dodavatele, čísla, data, částky a přílohy; podobnost má nabídnout prověření, nikoli automaticky smazat doklad. Umožnit rozdělit jeden doklad mezi více akcí/činností a oddělit osobní či neuznanou část. Součet rozdělení musí odpovídat celku. Nákup oblečení propojit s příjemkou etapy 3 bez dvojího peněžního výdaje.

Chybějící či nečitelný doklad vede na případ k doplnění nebo doložené rozhodnutí hospodáře/účetní; nevyrábí se náhradní údaj odhadem. U zahraničních dokladů zachovat původní měnu a doložený přepočet podle metodiky účetní, nikoli dnešní kurz při každém otevření.

**Hotovo, když:** lze doložit původ, celkovou částku, uznané rozdělení a komu skutečně zbývá zaplatit. P06–P09.

### E5-04 — Schvalovat výdaje a proplácet vlastní peníze pracovníků

**Máme:** základ výdaje a označení vlastních peněz. Kompletní stavový postup žádosti o proplacení, jeho schválení a vyrovnání nebyl v kontrolovaných výdajových obsluhách nalezen. [Z1]

**Doplnit:** rozpracováno → předloženo → vráceno k doplnění / zamítnuto / schváleno. Oddělit rozhodnutí o uznání od stavu úhrady. Schválení obsahuje částku, účel, osobu, datum, verzi podkladu a případný důvod rozdílu proti žádosti. U závazku evidovat splatnost a zůstatek.

Nezaplacená dodavatelská faktura vytváří jiný případ než doklad již uhrazený trenérem ze svého. Tentýž nákup nesmí zůstat zároveň plně dlužen dodavateli i trenérovi. Podporovat částečné proplacení a více dokladů hrazených jedním skutečným převodem. Schválení ani zařazení do seznamu k výplatě není důkaz zaplacení.

**Hotovo, když:** například nákup trenéra za 1 200 Kč, uznaný z 1 000 Kč a proplacený ze 600 Kč, vykáže 200 Kč neuznáno a 400 Kč k doplacení. P10–P12.

### E5-05 — Evidovat jednotlivá předání záloh

**Máme:** u provozní akce jednu částku `zalohova_castka` a text `zalohu_predal`. Formulář a přehled používají text osoby; z něj nelze průkazně odlišit předávajícího a příjemce ani více předání. [Z2]

**Doplnit:** samostatný záznam každé zálohy s příjemcem, předávajícím, akcí/účelem, datem, měnou, částkou, termínem vyúčtování a odkazem na skutečný bankovní/pokladní pohyb E5-07. Žádost a schválení zálohy oddělit od skutečného předání.

Umožnit více záloh na jednu akci a jednomu člověku, včetně postupného doplnění. Vždy ukázat, které peníze drží která osoba. Převod zálohy mezi vedoucími nebo na jinou akci musí být výslovně doložený; nepřepisovat příjemce původního předání. Záloha od zákazníka za kurz je odlišná agenda etapy 2.

**Hotovo, když:** u každé svěřené částky existuje příjemce, skutečné předání, účel a otevřený zůstatek; opakované uložení ji nezdvojí. P13–P14.

### E5-06 — Vyúčtovat zálohu a skutečně ji vypořádat

**Máme:** přehled všech účtenek akce a výpočet `záloha − účtenky hotove_tym`. Tlačítko uzavření nastaví stav akce na `uzavrena`; obsluha neověřuje doloženou vratku/doplatek. [Z2]

**Doplnit:** přiřazovat konkrétní uznané částky dokladů ke konkrétní záloze a příjemci. Klubová karta se neodečítá z hotovosti svěřené trenérovi. Vlastní výdaj jiné osoby se nestává automaticky spotřebou této zálohy. Neuznané položky zůstanou samostatně k vypořádání.

Vyúčtování obsahuje předané zálohy, uznané čerpání, již vrácené částky, doplatky a zbývající saldo. U kladného zůstatku je nutné doložit vrácení peněz, u přečerpání schválený doplatek. Rozlišit „vyúčtování schváleno“ a „peníze vypořádány“. Nevynulovat rozdíl pouhým přepnutím stavu.

Uzavřenou akci chránit také proti následnému přidání, přepojení, úpravě nebo smazání účtenky. Pozdní doklad vytvoří opravu nové verze s dopadem na zůstatek a účetní balík.

**Hotovo, když:** lze vysvětlit každý zůstatek podle osoby i akce; finanční uzavření nepřijme nevyřešené peníze. P15–P19.

### E5-07 — Propojit závazky a výplaty se skutečnými penězi

**Máme:** obecné platební a klubové finanční mechanismy; `club_finance_receipts` slouží zejména přijatým penězům a jejich rozdělení. Starší výdaje a odměny nejsou tím automaticky plnohodnotnými výdajovými platbami. [Z4]

**Doplnit prostřednictvím etapy 2:** proplacení dodavateli/pracovníkovi, předání zálohy, vratku a výplatu odměny navázat na jediný skutečný pohyb s datem, částkou, měnou, účtem/pokladnou, příjemcem a důkazem. Jeden pohyb může vyrovnat více závazků; jeden závazek může mít více úhrad. Hlídá se součet přídělů i zbývající částka.

Pro malý oddíl stačí seznam připravený k platbě a následné doložení bankovního provedení nebo hotovostního převzetí. Automatické odesílání plateb není součástí zadání. Neúspěšný příkaz, odvolaná platba, vratka či oprava přídělu zachovají historii a správný zůstatek. Změnu účtu příjemce před platbou ověří pověřený pracovník.

**Hotovo, když:** „vyplaceno“ znamená doložené vypořádání konkrétního závazku; příznak, platební příkaz nebo export nemohou vytvořit fiktivní výdaj. P11–P12, P20–P21, P28, P33.

### E5-08 — Předkládat a schvalovat výkazy trenérů

**Máme:** vlastní měsíční výkaz, souhrn pro oprávněnou obsluhu a XLSX. Tréninky se berou z vazby trenéra, další činnost z `dalsi_cinnosti`. Zápis další činnosti již validuje datum a kladnou délku; vlastní činnost lze odstranit. [Z5]

**Doplnit:** předložení výkazu za období, kontrolu vedoucím, vrácení k opravě a schválenou neměnnou verzi. Převzít ověřenou skutečnou činnost z E4-06/E4-12. Rozlišit trénování, administrativu a další používané druhy práce; přepis papíru má skutečné datum činnosti, datum zápisu a autora.

Kontrolovat duplicitní vykázání tréninku jako další činnosti, souběhy a neobvyklé délky. Více trenérů na jedné lekci může být oprávněné, proto nededuplikovat jejich práci napříč lidmi. Délka celé lekce se nesmí bez potvrzení vydávat za skutečně odpracovaný čas každého záskoku. Schválené podklady nelze smazat běžnou cestou přes výkaz.

**Hotovo, když:** schvalovatel dohledá každou hodinu a její stav; změna po schválení vytváří opravu, ne tichý přepočet již předané odměny. P22–P24.

### E5-09 — Dokončit odměny trenérů a dalších pracovníků

**Máme:** výkazy práce a zdrojové osoby; úplné stanovení odměny podle smluvního podkladu, její schválení a finanční vypořádání není součástí samotného výkazu. [Z5]

**Doplnit:** pro každého používaného příjemce odkaz na platný podklad/dohodu, druh odměny, období, schválenou sazbu nebo paušál a pravidlo výpočtu. Podklad a sazba mají verzi a účinnost. Změna sazby uprostřed měsíce nesmí zpětně přecenit celý měsíc. Paušál se automaticky nesčítá s hodinovou odměnou bez schváleného pravidla.

Schválený výkaz → výpočet → schválení částky → předání účetní → potvrzené částky k vypořádání → skutečné výplaty. Druh smluvního/daňového vztahu, případné odvody a rozdíl hrubé/čisté částky potvrdí účetní podle reálných podkladů. KIS je nesmí odvodit z názvu „trenér“ ani předstírat, že vypočtená odměna je vždy čistou částkou k převodu.

U fakturujícího dodavatele propojit výkaz a fakturu s jedním závazkem. U zpracování účetní přijmout její vypořádání pod stejnou referencí a evidovat potřebné související povinnosti v etapě 2. Nevyvíjet plný mzdový program.

**Hotovo, když:** každá vyplácená částka má schválenou verzi, příjemce, podklad a návaznost na účetní; opakované předání nevytvoří druhý závazek. P25–P28.

### E5-10 — Dokončit odměny sportovců

**Máme:** sazbu za trénink, otevřená/uzavřená období a živý výpočet. Při uzavření se již ukládá `pocet_treninku` a `castka_celkem`; není potřeba tento základ vytvářet znovu. U starších období bez těchto hodnot je náhradní živý výpočet. Označení výplaty dnes nastavuje `vyplaceno = 1`. [Z6]

**Doplnit:** pravidla uznatelných tréninků z E4-06, ochranu před překryvem období a verze sazeb. Hromadná změna nyní upravuje sazbu otevřeného období; nová účinnost musí být výslovná, aby nezměnila odměnu za minulou činnost. Výběr sportovců sjednotit s datovanými soupiskami E4-02, bez násobení kvůli více skupinám.

Rozšířit uložený souhrn o konkrétní podklady a schválení, ochránit ho proti opakovanému uzavření či úpravě již vyplaceného období. V existujících obsluhách aktualizace období neobsahují obecnou podmínku „jen otevřené a nevyplacené“; tuto ochranu je potřeba sjednotit. Historické období bez souhrnu označit k ověření, nedopočítat je automaticky novou importovanou docházkou jako nový nárok.

Skutečné výplaty evidovat přes E5-07, včetně částí a zůstatku. U nezletilého rozlišit sportovce, oprávněného příjemce a skutečného přebírajícího podle potvrzeného postupu oddílu. Účetní posoudí povahu odměny. Odměna není rodinný přeplatek ani nárok na náhradní lekci; automatické započítávání proti členským příspěvkům se nezavádí.

**Hotovo, když:** oprava docházky nebo sazby nezmění neviditelně schválenou odměnu; je doloženo, kolik bylo skutečně vyplaceno a kolik zbývá. P29–P33.

### E5-11 — Dokončit používané jízdy a cestovní podklady

**Máme:** vozidla, řidiče, začátek/konec jízdy, tachometr, místo, poznámku a audit. Účtenku lze navázat na vozidlo a akci. Jízda sama není hotové cestovní vyúčtování. [Z8]

**Doplnit podle potvrzeného používání:** účel, vazbu k akci, skutečná data cesty, přepis papírové jízdy a kontrolu uzavření. Pokud oddíl proplácí cesty, doplnit schválený nárok, podklady, druh dopravy a vyúčtování přes E5-04. Použité sazby a pravidla musí být verzované podle potvrzení účetní; nyní je neurčujeme.

Prověřit, aby klubová faktura/účtenka za pohonné hmoty a náhrada stejného výdaje nebyly vyplaceny dvakrát. Souběh řidičovy práce, cestovního výdaje a trenérské odměny posuzovat jako různé nároky podle pravidel. Evidence jízd nezavádí půjčování vybavení členům.

**Hotovo, když:** použitá jízda a její výdaje jsou doložené, nevypořádaná cesta je vidět a případná náhrada navazuje na jediný závazek. Pokud se náhrady nevedou, je to výslovně uvedeno. P34–P35.

### E5-12 — Doplnit přehled klubových úkolů a rozhodnutí

**Máme:** provozní přehledy, audit a společné zadání nevyřešených případů v E1-10. Samostatný rozsáhlý registr smluv a rozhodnutí není potvrzenou potřebou. [Z9]

**Doplnit minimum pro tuto etapu:** „co chybí“, odpovědná osoba, termín, stav, související akce/výdaj/výkaz a výsledek. Typické položky: dodat účtenku, vrátit zálohu, schválit výkaz, doložit účet příjemce, předat účetní a dořešit vrácený podklad. Navázat na existující úkoly, nikoli vytvořit další nesouvisející seznam.

U sazeb, schvalovacích pravidel a odměn evidovat odkaz na rozhodnutí či smluvní podklad, verzi a platnost. Obecné smlouvy, zápisy vedení, dotace, pojistné či jiné roční termíny přidat jako jednoduchý seznam teprve podle inventury skutečných povinností. Dotace nepotřebují univerzální nový modul: nejprve konkrétní požadované členění a výstupy.

**Hotovo, když:** zastupující hospodář zjistí, kdo co řeší a z čeho rozhodnutí vychází; upozornění má dohledatelný výsledek nebo úkol k osobnímu vyřízení. P36.

### E5-13 — Uzavřít měsíční předání účetní a provozní přehled

**Máme:** export výkazů a kontrakt deseti listů účetního balíku etapy 2. Samotný XLSX výkaz trenéra není celý účetní měsíc oddílu. [Z5, Z10]

**Doplnit:** z agend etapy 5 předávat doklady, uznané částky, otevřené závazky, skutečné úhrady, zálohy a jejich vypořádání, schválené odměnové podklady a opravy. Využít stejné identifikátory a listy `Doklady`, `Penize`, `Parovani`, `Cleneni_penez`, `Saldo` a `Vydaje`; detail výkazu nebo vyúčtování připojit jako dohledatelnou přílohu. Nerozmnožovat stejný pohyb v novém exportu.

Rozlišit „předáno účetní“, „vráceno k doplnění“, „převzato“ a skutečný stav platby. Konec měsíce může obsahovat otevřené zálohy a nezaplacené schválené závazky; musí být doložené v saldu, ne uměle označené za vyřízené. Pozdní doklad a změna klasifikace mají novou revizi podle E2-12.

**Hotovo, když:** účetní dokáže z balíku a příloh rozlišit podklad, závazek, peníze a zůstatek; hospodář vysvětlí počáteční i konečné otevřené položky. P37–P38.

### E5-14 — Chránit uzavřené podklady, opravy a přístupy

**Máme:** audit, soukromé ukládání a oprávněné stahování účtenek. Uložení účtenky umí nahradit soubor, odstranění maže databázový záznam a odkládá soubor; uzavření akce samo tyto cesty nezamyká. [Z1, Z2, Z11]

**Doplnit:** kontrolu stavu a závislostí ve všech cestách úpravy, mazání a opětovného uzavření: účtenka, akce, záloha, výkaz, sazba, odměnové období i příloha. Po schválení/předání uchovat původní verzi a opravu s důvodem, autorem a dopadem na závazek/export. Finanční údaje počítat přesným částkovým kontraktem E2-02, ne postupně násobenými desetinnými odhady.

Práva rozlišit podle role, vlastníka a úkonu. Pokud má trenér sám předkládat účtenku, nesmí kvůli tomu získat přístup ke všem klubovým dokladům; současný přístup ke stažení pro finanční roli je potřeba cíleně rozšířit pouze pro schválenou samoobsluhu. Smluvní údaje, odměny a účty příjemců nejsou veřejné soupisové údaje.

Záloha a obnova musí pokrýt vazby, originály, schválené verze a exportní balíky. Doby uchování a přístupy stanoví oddíl s účetní podle typu dokumentu; přepis papíru nepovoluje likvidaci originálu.

**Hotovo, když:** žádná starší cesta neobejde schválení ani nezmění historický balík a odebraná role nezíská podklad přímým odkazem. P03, P19, P24, P32, P39.

### E5-15 — Připravit převzetí otevřených stavů a historie

**Máme:** v etapě 0 bylo naměřeno pět účtenek v součtu 1 237 Kč v obou instalacích, ale bez potvrzení totožnosti souborů. U Harrachova byla záloha 0 Kč v Evidenci a 2 000 Kč v KIS. Evidence vykazovala 27 aktivních odměnových období a průběžný výpočet 20 910 Kč proti nule v KIS; historicky vyplacených 720 Kč za posledních 12 měsíců rovněž vyžaduje zachování a doložení. Jde o tehdejší přehledy, nikoli novou účetní inventuru. [Z12]

**Doplnit:** oddělené seznamy otevřených dodavatelských závazků, neproplacených vlastních výdajů, záloh podle příjemce, odměn k posouzení/k výplatě a doložených minulých úhrad. Každá položka má zdrojové ID, stav ověření, částku, osobu, rozhodné datum a podklad. Vyřešit Harrachov proti prvotnímu dokladu a pokladně/bance; ani jedna instalace nemá automaticky přednost.

Shodné účtenky neduplikovat. U odměn zachovat staré sazby a uzavřené souhrny; import docházky nesmí přepočítat již vyplacenou historii jako nové závazky. Neověřené položky nesmějí do seznamu k nové výplatě. Otevírací stavy nejsou nové příjmy ani výdaje přechodového měsíce.

**Hotovo, když:** hospodář schválí mapu a všechny otevírací položky nebo výslovně oddělené případy k dořešení. Zkušební i ostrý převod se provede až v autorizované etapě 6. P40.

### E5-16 — Přejmout celý běžný měsíc

**Máme:** zdrojové moduly a jednotlivé technické ochrany. Nejde o nový důkaz úplného průchodu agendy s účetní a obsluhou.

**Doplnit:** přejímku podle 40 scénářů v příloze, nejprve v izolovaném prostředí. Hospodář, trenér, vedoucí akce a účetní projdou jeden modelový měsíc: klubový nákup, vlastní výdaj, více záloh, vratku/doplatek, schválení výkazu, odměnu sportovce, částečnou výplatu, pozdní doklad a účetní předání. Přidat výpadek s papírem a zastupování hospodáře.

Přejímka zaznamená revizi, výsledek a důkaz; nevyřešené P0 se nezakryjí označením „hotovo“. Provozní pilot, import a nasazení vyžadují samostatně zadanou realizaci. Neaktivovat bankovní platby či automatizace jako vedlejší krok.

**Hotovo, když:** odpovědné osoby zvládnou běžný postup i opravy, peněžní a dokladové součty se vysvětlí a účetní převezme konkrétní balík. Scénáře migrace se dokončí v etapě 6; plán je nyní pouze připraven.

## Doporučené pořadí realizace

1. **Pravidla a společný finanční základ:** E5-01, E5-07 a průběžně E5-14, s převzetím příslušných částí etapy 2. Zde určit i skutečné smluvní a účetní režimy odměn.
2. **Výdaje a akce:** E5-02 až E5-06. Nejdříve doklad a konkrétní věřitel, potom záloha a její úplné vypořádání. Nejvyšší provozní priorita pro nahrazení papíru.
3. **Výkazy a odměny:** E5-08 až E5-10 po spolehlivém uzavírání sportovní činnosti z etapy 4. Zachovat existující výkazy a souhrny, doplnit schválení a výplaty.
4. **Administrativa a účetní předání:** E5-11 v používaném rozsahu, E5-12 a E5-13. E5-15 připravovat průběžně; E5-16 uzavře přejímku před samostatně schváleným pilotem.

## Rozhodnutí před implementací dotčené části

| Rozhodnutí | Navržený směr | Potvrdí |
|---|---|---|
| Schvalování a zastupování | Krátký postup, věcná kontrola a doložená úhrada; vlastní nárok posoudí jiná pověřená osoba | Vedení |
| Typické výdaje a členění | Použít společné členění etapy 2 a reálné příklady oddílu | Hospodář + účetní |
| Zálohy | Konkrétní osoba, skutečné předání, termín a doklady; žádné uzavření bez vysvětleného salda | Hospodář |
| Odměny trenérů | Skutečné smlouvy, druh odměny, sazby a předání účetní; žádné odhadované odvody | Vedení + účetní |
| Odměny sportovců | Uznatelné tréninky, účinnost sazby, schválení, příjemce a účetní povaha | Vedoucí sportu + účetní |
| Cesty, smlouvy a dotace | Doplnit jen skutečně používané postupy a povinné výstupy | Vedení + hospodář |
| Měsíční uzávěrka | Termín, kdo předává/přebírá, jak opravit pozdní doklad | Hospodář + účetní |
| Převzetí historie | Ověřené otevřené stavy, Harrachov, staré odměny a neduplicitní doklady | Hospodář + správce dat |

Používání základních papírových agend je již potvrzeno; není potřeba se na ně ptát znovu. Před implementací zbývá doplnit konkrétní správce, pravidla a vzorové anonymizované podklady.
