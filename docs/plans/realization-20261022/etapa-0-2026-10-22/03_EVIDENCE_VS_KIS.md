# Porovnání dat původní Evidence a KIS

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Živé porovnání 22. 10. 2026. Zdrojová instalace: [původní Evidence](https://data.kovopraha.cz/evidence/index.php). Cílová instalace: [KIS](https://kis.kovopraha.cz/pracovni_pozice.php). Označení KIS zde znamená novou aplikaci na této doméně; nejde o samostatný nový audit původního externího členského systému, z něhož se dříve importovaly osoby.

**Instalace mají odlišný obsah. KIS má novější členskou evidenci a soupisky, původní Evidence drží podstatnou tréninkovou historii, závody a odměny sportovců. Obnova celé staré databáze přes KIS by proto byla chybný postup.**

## Metodika a hranice důkazu

- Přihlášené administrátorské přehledy byly čteny bez ukládání obchodních dat. U osob byly vypnuté filtry a zvoleno Všichni; z datové tabulky bylo načteno všech 10 095 a 1 504 řádků. Technické tabulky klávesových zkratek nebyly započteny.
- Párování se provedlo nad údaji z těchto tabulek v paměti prohlížečového nástroje. Do výsledných dokumentů se ukládají agregace, nikoli seznamy osob, data narození nebo kontakty.
- Klíč kandidáta: příjmení + jméno + datum narození; ořez mezer, sjednocení vícenásobných mezer, Unicode NFC a velikosti písmen. Diakritika se nesmazávala. Datum musí mít formát YYYY-MM-DD a nesmí být 1900-01-01. To nezaručuje, že je datum věcně správné.
- Jednoznačný kandidát znamená právě jeden takový řádek na každé straně. Nejde o potvrzení identity, příbuznosti nebo práva k účtu. Číselná ID instalací nebyla použita jako důkaz totožnosti.
- Tréninky a závody byly kontrolovány bez filtrů skupiny, trenéra a kategorie. Počty platí pro administrátorské přehledy, nikoli pro všechny fyzické řádky DB. Například aktuální zdroj tréninkového přehledu používá spojení na trenéra, takže osiřelé záznamy mohou zůstat mimo přehled.
- Šlo o průběžně používané systémy, nikoli atomický databázový snímek. Před skutečnou migrací se musí porovnání zopakovat na konzistentních exportech a doplnit nepřístupné či skryté vazby.

## Osoby a kvalita údajů

| Metrika z tabulky osob | Evidence | KIS | Význam |
|---|---:|---:|---|
| Záznamy osob | 10 095 | 1 504 | Rozdíl 8 591 není počet chybějících členů; zdroj zahrnuje mnohem širší záznamy. |
| Osoby s alespoň jednou vazbou na trénink | 405 | 1 | Přenos sportovní historie není hotový. |
| Součet zobrazených počtů tréninkových vazeb osob | 2 884 | 1 | Počet vazeb, nikoli počet lekcí; duplicitní osoby mohou představovat stejného člověka. |
| Datum narození 1900-01-01 | 8 568 | 0 | Zástupná hodnota se nesmí stát platným narozením ani podkladem věkové kategorie. |
| Nevyplněné datum narození | 0 | 163 | KIS správně může držet neznámou hodnotu; neznámé datum nedoplňovat odhadem. |
| Neprázdné číselné UCI ID | 8 749 | 0 | UCI není vhodné jako jediný společný párovací klíč; chybí v cílovém přehledu. |
| Osoby s alespoň jednou původní skupinou v tomto přehledu | 508 | 0 | Platí pro původní skupiny, nikoli nové KIS soupisky. |
| Zobrazený stav Čekající | 10 095 | 0 | Výchozí legacy stav sám neprokazuje, že nikdo není členem. |
| Zobrazený stav Aktivní ručně | 0 | 1 462 | Stav osoby není důkaz platby, účasti nebo aktivovaného účtu. |
| Zobrazený stav Neaktivní ručně / Archiv ručně | 0 / 0 | 41 / 1 | Zachovat cílová rozhodnutí o stavu. |
| Příznak KIS aktivní | 0 | 1 462 | Legacy pole není náhradou dnešního členství v konkrétní soupisce. |

Na první stránce Evidence jsou i zjevně neúplné názvy osob, například fragmenty v hranatých závorkách a jednopísmenné hodnoty, některé s navázanými tréninky. Vyžadují prověření vazeb; nesmějí být automaticky smazány ani převzaty jako noví aktivní členové. Kombinace velkého počtu UCI ID a zástupných narození naznačuje katalogové/importované záznamy; konkrétní původ každé osoby musí potvrdit zdrojový export.

### Výsledek párování

| Kategorie cílových osob KIS | Počet | Další postup |
|---|---:|---|
| Jednoznačný kandidát podle jména a nezástupného data na obou stranách | 1 242 | Připravit převodní mapu; ověřit zdrojové identifikátory a konfliktní atributy. |
| Vyplněné nezástupné datum, žádný přesný kandidát v Evidenci | 88 | Mohou být noví členové, jiné zápisy jména nebo chybné údaje; zachovat KIS a prověřit. |
| Vyplněné nezástupné datum, ale shoda není jednoznačná | 11 | Ruční rozhodnutí. |
| Datum v KIS chybí | 163 | Hledat doložené vazby/kontakty; nezakládat datum narození odhadem. |
| Celkem | 1 504 | 1 242 + 88 + 11 + 163. |

Ve zdroji je 13 duplicitních klíčů s nezástupným datem, které zahrnují 27 řádků. V KIS jsou 3 takové klíče zahrnující 6 řádků. Jde o kandidátní duplicity podle omezené sady polí, nikoli o schválené sloučení.

Ze 405 zdrojových osob s tréninky má 324 jednoznačného kandidáta. **81 zbývá individuálně vyřešit**, přičemž 12 zdrojových osob s tréninky má datum 1900-01-01. Tyto skupiny se překrývají; nelze je sčítat. Ruční řešení má přednost u osob s finančními zůstatky, odměnami nebo tréninkovou historií.

Zdroje: [Evidence osoby](https://data.kovopraha.cz/evidence/sprava_sportovcu.php), [KIS osoby](https://kis.kovopraha.cz/sprava_sportovcu.php).

## Tréninky a závody

| Metrika | Evidence | KIS |
|---|---:|---:|
| Tréninky ve správě tréninků | 1 651 | 1 |
| Součet hodin uvedený přehledem | 2 245,5 h | 1,0 h |
| Rozsah dat tréninků | 24. 5. 2025 až 2. 11. 2026 | 28. 10. 2026 |
| Tréninky za rok 2025 / 2026 | 615 / 1 036 | 0 / 1 |
| Tréninky s datem po dni kontroly | 2 | 1 |
| Závody ve správě závodů | 50 | 0 |
| Rozsah dat zobrazených závodů | 18. 7. 2025 až 24. 8. 2026 | — |
| Součet zobrazených účastníků závodů | 293 | 0 |

Záznamy s budoucím datem nejsou důkazem skutečné účasti. Před převodem rozlišit plán a skutečnost; přehled je dnes ukazuje společně. Součet hodin se nepovažuje za součet hodin všech trenérů ani za mzdový podklad bez další kontroly.

Převést je potřeba nejen datum a popis, ale vazby na trenéry a sportovce, skupiny platné v době tréninku, docházku, podrobnosti cvičení/měření a přílohy. U závodů navíc disciplínu, účastníky, výsledky, importy a fotografie. Přesný počet detailů, souborů a osiřelých vazeb zatím není porovnán.

Zdroje: [Evidence tréninky](https://data.kovopraha.cz/evidence/sprava_vsech_treninku.php), [KIS tréninky](https://kis.kovopraha.cz/sprava_vsech_treninku.php), [Evidence závody](https://data.kovopraha.cz/evidence/sprava_zavodu.php), [KIS závody](https://kis.kovopraha.cz/sprava_zavodu.php).

## Soupisky mají jiný význam než původní skupiny

KIS už obsahuje v přehledu historických sezon 76 soupisek:

| Sezona v přehledu KIS | Soupisky | Aktivní členství | Historická členství |
|---|---:|---:|---:|
| Školní rok 2026/27 | 41 | 435 | 0 |
| Starý KIS 2025/26 | 33 | 0 | 560 |
| Kroužky 2026/2027 | 1 | 0 | 0 |
| TEST UAT období | 1 | 0 | 1 |

Členství jsou součty řádků napříč soupiskami, nikoli unikátní lidé. Jedna osoba může patřit do více soupisek. Z toho plyne, že nula ve sloupci původních skupin u KIS osob neznamená prázdné nové soupisky. Testovací sezonu je nutné při finančním a provozním přejímání posuzovat odděleně.

Pět původních skupin je viditelných ve filtrech obou instalací. Přenos historické vazby do původní skupiny nesmí automaticky vytvořit současné placené členství v novém kroužku. Zdroj: [KIS sezony a soupisky](https://kis.kovopraha.cz/kis_rosters_admin.php).

## Odměny a provozní finance

| Oblast | Evidence | KIS | Závěr |
|---|---|---|---|
| Aktivní kreditní období sportovců | 27 | 0 | Chybí převzetí období a sazeb. |
| Průběžně vypočtené odměny | 20 910 Kč | 0 Kč | Převzít podklady výpočtu; průběžná částka není schválený bankovní přeplatek ani automaticky splatný závazek. |
| Čeká na výplatu podle přehledu | 0 Kč | 0 Kč | Shodná nula neřeší chybějící aktivní období. |
| Vyplaceno za posledních 12 měsíců | 720 Kč | 0 Kč | Zachovat historii vyplacení a dohledat doklady; nevyplácet znovu. |
| Přijaté účtenky | 5, celkem 1 237 Kč | 5, celkem 1 237 Kč | Částky a data odpovídají. Totožnost souborů ani skutečné účetní schválení tím nejsou doloženy. |
| Provozní události | 3 | 3 | Dvě otevřené a jedna uzavřená v obou přehledech. |
| Záloha Soustředění Harrachov, 9.–11. 8. 2025 | 0 Kč | 2 000 Kč | Konkrétní konflikt. Hospodář musí ověřit proti pokladně/bance; automatický přepis je zakázaný návrhem migrace. |

U části provozních položek jsou názvy připomínající testovací záznamy. Bez potvrzení vlastníka je nelze označit za neúčetní ani odstranit. Shodné ID dokladu v obou instalacích je pouze pomůcka; shodu obsahu a původu je potřeba doložit.

Odměny sportovce se vedou odděleně od finančních přeplatků a od náhradních lekcí. Obnova tréninků může změnit průběžný výpočet odměn; uzavřená období musí zachovat původní schválený snímek a informaci o vyplacení.

Zdroje: [Evidence kredity](https://data.kovopraha.cz/evidence/prehled_kreditu.php), [KIS kredity](https://kis.kovopraha.cz/prehled_kreditu.php), [Evidence účtenky](https://data.kovopraha.cz/evidence/uctenky/seznam.php), [KIS účtenky](https://kis.kovopraha.cz/uctenky/seznam.php), [Evidence události](https://data.kovopraha.cz/evidence/udalosti/seznam.php), [KIS události](https://kis.kovopraha.cz/udalosti/seznam.php).

## Rozdělení autority dat

Jde o návrh pravidel pro jednotlivá pole; žádný systém nemá automaticky přednost ve všem.

| Data | Navržené pravidlo |
|---|---|
| Aktuální osoby, rodinné vazby, účty, stav členství | Zachovat KIS; prokazatelné opravy ze zdroje posoudit po polích. Kontakt rodiče není schválené přístupové oprávnění. |
| Tréninková a závodní historie | Evidence je zdroj chybějící historie; importovat přes schválené mapy osob, trenérů a skupin, bez kolize s novými záznamy KIS. |
| Současné soupisky a programy | Zachovat KIS; stará skupina přenáší historický kontext, ne automatický dnešní nárok. |
| Doklady, uhrazené částky, zálohy, odměny a přeplatky | Prvotní doklad a ověřený peněžní pohyb rozhodují; rozpory předat hospodáři. |
| UCI licence a sportovní údaje | Doplnit po ověření správné osoby a aktuálnosti; nepřenášet katalogové záznamy hromadně mezi aktivní členy. |
| Zástupné a poškozené hodnoty | Uchovat původ ve zdrojovém archivu; nevydávat je za ověřený údaj. |
| Přílohy | Zachovat originální obsah, ověřit otisk, privátní přístup a vazbu na cílový záznam. |

## Konkrétní zadání převodu

### Datový kontrakt

Každá převáděná entita musí mít `source_system`, `source_entity_type`, `source_id`, `target_id`, otisk zdrojového obsahu, čas snímku, stav rozhodnutí a odůvodnění. Převodní mapa osoby navíc uchová metodu párování, kandidáty, případný konflikt a kdo rozhodnutí ověřil. Obsah s osobními údaji patří do chráněného datového balíku, nikoli do veřejné dokumentace nebo Gitu.

Mapy připravit pro osoby, trenéry/uživatelské účty, skupiny/podskupiny, sportoviště, tréninky, závody, kreditní období a finanční/provozní záznamy. Číselná ID ze zdroje se nemají dosazovat přímo do cizích klíčů KIS. Shodné uživatelské jméno trenéra není samo důkaz totožnosti; ve zdroji jsou i vícečetná podobná jména.

### Pořadí budoucí práce

| Krok | Úkol | Podmínka přijetí |
|---|---|---|
| D01 | Získat konzistentní podklady obou instalací včetně souborů | Manifest tabulek/souborů, časy, počty, součty a otisky. Podklady čitelné a obnovitelné. |
| D02 | Inventarizovat všechny vazby a odlišnosti schématu | Matice zdrojová tabulka/pole → cílová tabulka/pole; samostatně osiřelé vazby a nové kanonické struktury. |
| D03 | Vyřešit mapu osob a trenérů | Žádná nevyřešená osoba/role v převáděné historii; sporné položky ponechat v evidované frontě, nevyrobit nového člena odhadem. |
| D04 | Připravit aditivní import historie do izolované kopie | Zachová současné KIS osoby, objednávky, účty a soupisky; zdrojový identifikátor zajišťuje opakovatelnost bez duplikátů. |
| D05 | Přenést tréninky a navazující detaily | Shodné počty po letech, součty přesných délek, účasti, trenéři, přílohy; vysvětlené případné deduplikace. |
| D06 | Přenést závody a odměny | Všechny výsledky/vazby; uzavřené odměny a výplaty zachované, žádná duplicitní výplata. |
| D07 | Vypořádat provozní a finanční rozdíly | Rozhodnutí k Harrachovu a všem dalším rozdílům doložené prvotním dokladem; shodné účtenky nezakládat znovu. |
| D08 | Porovnat soubory a dosud neověřené moduly | Každý soubor dohledatelný; rezervace, lekce, výkazy, měření, plány, oznámení a vozidla vyřešeny podle inventury. |
| D09 | Uživatelské převzetí a pozdější přepnutí provozu | Trenér ověří historii, registrář identity, hospodář peníze; stanovený okamžik ukončení zápisů do staré Evidence a návratový postup. |

**Existující nástroj úplného importu osob není vhodný pro doplnění této historie.** Jeho [dokumentace v kontrolované revizi](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/kis-full-person-import.md) výslovně popisuje nahrazení osob a odstranění související historie v rámci jiného, dříve schvalovaného převodu. Pro tento úkol navrhujeme samostatný aditivní postup. Z existence nástroje nelze dovozovat, že vysvětluje všechny aktuální rozdíly nebo že je nyní povoleno jej použít.

### Přejímací pravidla

- Každá zdrojová položka v dohodnutém rozsahu skončí jako importovaná, přiřazená k existující, vědomě archivovaná nebo výslovně odložená s důvodem. Žádná se tiše neztratí.
- Při opakování stejného balíku nevzniknou nové osoby, tréninky, platby ani soubory. Rozdílný obsah se stejným zdrojovým ID vytvoří konflikt, nikoli tiché přepsání.
- Aktuální doklady, objednávky, VS, soupisky a rodinné přístupy KIS zůstanou zachované.
- Historické přiřazení do skupiny neaktivuje nový placený kurz; import účasti nerozešle pozvánky ani připomínky.
- Nula osiřelých vazeb v převzatých datech; vyřešené duplicity mají zaznamenáno, jak ovlivnily počty. Součty před/po mají být vysvětlitelné, ne slepě stejné po deduplikaci.
- Průběžné odměny se porovnají po osobách a období; uzavřené a vyplacené odměny zachovají původní částky a stav.
- Před produkčním krokem se zopakuje soupis změn od prvního snímku, ověří obnova zálohy včetně souborů/klíčů a získá samostatné schválení produkčního převodu.

## Co ještě není datově porovnáno

Porovnání zatím neprokazuje úplnost adres a kontaktů, rodičovských vztahů, jednotlivých souhlasů, všech měření a segmentů, obsahu příloh, rezervací, individuálních lekcí, plánovaných tréninků, ostatních činností trenérů, rozesílek ani detailů jízd a servisů. Tyto oblasti patří do D01/D02/D08; současné přehledy a zdrojový kód potvrzují jejich existenci, nikoli úplnou datovou shodu.

Shoptet faktury, bankovní výpis a účetnictví nebyly v tomto srovnání spárovány po položkách. Zůstatky `KIS dluh = 0` v přehledu osob nelze vykládat jako potvrzení, že klub nemá žádné otevřené pohledávky.

## Dopad na další etapy

Doklady a export lze navrhovat nyní. Nahrazení původní Evidence ale musí čekat na převzetí historie a odměn, vyřešení identity 81 trénujících zdrojových záznamů a doložení finančních rozporů. Nový KIS už obsahuje vlastní aktuální data, takže migrační práce je slučování podle pravidel, nikoli prosté přenesení celé staré databáze.
