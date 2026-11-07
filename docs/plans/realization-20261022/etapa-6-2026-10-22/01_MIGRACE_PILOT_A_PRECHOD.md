# Etapa 6 — migrační pravidla, pilot a postup přechodu

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 6](00_ETAPA_6_PLAN.md). Konkrétní provozní zadání pro budoucí realizaci; žádný zde popsaný zásah nebyl tímto dokumentem proveden.

## 1. Rozhodování o původu a cíli dat

Žádný zdroj nemá přednost ve všem. Před převodem se pro každý typ dat schválí zdroj, cílové místo, konflikt a správce. Aktuální KIS je živý cíl, nikoli prázdná databáze.

| Data | Výchozí pravidlo | Co vyžaduje individuální rozhodnutí |
|---|---|---|
| Osoba a současné členské rozhodnutí | Zachovat ověřenou cílovou osobu a její ruční rozhodnutí; doplňovat po polích | Duplicita, změněné narození/jméno, nesoulad zdrojových ID |
| Kontakt, účet a rodinné právo | Kontakt se může převést jako kontakt; schválená cílová práva mají vlastní původ | Sdílený e-mail, vazba rodič–dítě bez důkazu, změna oprávněné osoby |
| Trenér a historický autor | Převodní mapa osoby/autorů; zachovat historické autorství | Starý účet bez dnešního oprávnění, shodná jména |
| Aktuální soupisky/programy | Zachovat cílové stálé identity a datované vazby | Staré texty skupin, kolize současného a historického členství |
| Přihlášky, termíny a rezervace | Převzít skutečné otevřené případy pod schválenou cílovou nabídkou | Nejasný účastník, neplatná nabídka, překročená kapacita, již zrušený termín |
| Tréninky, závody, měření | Evidence je zdrojem chybějící historie; doplnit podle map | Budoucí „skutečnost“, chybějící jednotka, osiřelé vazby, dvojí záznam |
| Omluvy a náhrady | Uchovat platné nároky a použitá pravidla, bez automatického nového generování | Již využitý nárok, střet zdrojové a cílové docházky |
| Katalog | Porovnat Shoptet s aktuálním cílem a chránit ruční změny KIS | Kolize SKU, upravená cena/typ, chybějící položka ve zdrojovém exportu |
| Objednávky a výdeje | Jeden vlastník dokončení, zachované položky a původní identifikátory | Částečná platba/výdej, vrácené zboží, chybějící položka či zákazník |
| Sklad | Fyzická inventura + doložené rezervace a blokace; jeden hlavní stav | Rozpor proti evidenci, zboží u trenéra, vrácené/poškozené kusy |
| Doklady a peníze | Původní doklad a ověřený peněžní pohyb; zachovat VS a čísla | Nejasná úhrada, nezařazený pohyb, duplicitní reference, Harrachov |
| Výkazy a odměny | Zachovat schválené období, sazby, částky a doložené výplaty | Starý živý výpočet bez souhrnu, chybějící důkaz výplaty |
| Soubory a souhlasy | Původní obsah, verze, datum, vazba a odpovídající přístup | Chybějící soubor nebo znění souhlasu; neověřený papírový přepis |
| Webový obsah | Převzít schválený potřebný obsah na konkrétní cílovou adresu | Nepoužívaný web, neaktuální text, soukromý obsah, nepokrytý formulář |
| Staré uzavřené případy | Aktivní převod nebo úplný čitelný archiv podle schválené potřeby | Historie potřebná pro pozdější vratku, kontrolu, výkaz či opravu dokladu |

Rozhodnutí „archivovat“ není povolení zahodit přílohy nebo odstavit službu, na níž zůstává jediná čitelná kopie. Otevřený případ nelze přesunout do pasivního archivu bez možnosti jeho vypořádání.

## 2. Kontrakt migrační dávky

### Povinný manifest

| Položka | Obsah |
|---|---|
| Identita dávky | Jedinečné ID, zdrojová instalace/typ exportu, čas a časová zóna, rozsah agend |
| Obsah | Seznam souborů, velikosti a SHA-256, počty entit/vazeb, částkové součty po měnách a druhových skupinách |
| Cílový základ | Identita prostředí, verze schématu a aplikace, kontrolní otisk relevantních cílových dat |
| Pravidla | Verze převodníku, map osob a dalších entit, pravidel konfliktů a klasifikace |
| Rozhodný okamžik | Hranice předchozího snímku a této dávky, metoda zachycení změn/výmazů a konečné uzávěry |
| Náhled | Zamýšlené vytvoření, propojení, opravy, přesuny do archivu, odmítnutí a dopad na závislosti |
| Schválení | Kdo schválil konkrétní obsah/otisk a rozsah; změna vstupu nebo relevantního cíle schválení zneplatní |
| Protokol | Skutečný výsledek, převodní mapy, chyby, kontrolní součty, okamžiky a stav každé dílčí dávky |
| Obnova | Odkaz na ověřenou zálohovací sadu a postup návratu pro příslušnou fázi |

Osobní údaje, exportní adresy s přístupovým hashem, klíče a tokeny patří do chráněných balíků, nikoli do Gitu, veřejných reportů nebo běžných logů. Přehled pro vedení může obsahovat agregace a neprůhledné reference.

### Pravidla pro každý řádek

1. Identita je složená ze zdroje, typu entity a zdrojového ID. Stejné číselné ID na dvou webech neznamená stejný objekt. Zdroj bez stabilního ID potřebuje zvlášť schválené pravidlo mapování; nelze ho potichu nahradit e-mailem.
2. Každý vstupní řádek skončí v jedné doložené kategorii: převzato, propojeno s existujícím, archivováno, odloženo, odmítnuto. Podřazené přílohy a vazby mají vlastní kontrolu úplnosti.
3. Stejná identita a obsah při opakování nezakládají nový záznam. Změna stejného ID je porovnání vůči předchozí dávce a dnešnímu cíli.
4. Dvě zdrojové položky spojené do jedné osoby musejí mít schválenou mapu a vyčíslený dopad na počty. Celkový počet pak nemusí být shodný, ale každá odchylka musí být vysvětlitelná.
5. Chybění v neúplném či změnovém exportu není smazání. Výmazy a zneplatnění zpracovat pouze z průkazného zdroje a po kontrole historie/závazků.
6. Nově změněný cílový záznam se nepřepisuje starším snímkem. Porovná se základ snímku, změna zdroje a změna cíle; konflikt rozhodne vlastník dat.
7. Import není běžný nový obchodní úkon: nevystavuje znovu staré faktury, nerozesílá upomínky, nepotvrzuje nové rodičovské role, negeneruje náhrady ani výplaty.
8. U většího převodu se použijí závislé kontrolované dávky s ověřeným pokračováním. Neexistuje předpoklad, že všechny databáze, soubory a externí služby lze zahrnout do jedné transakce.

### Kontroly úplnosti

- **Identity a vazby:** každý aktivní případ má potvrzenou osobu a všechny potřebné návaznosti; žádné nevysvětlené osiřelé vazby v převzatém rozsahu.
- **Sport:** po letech a vybraných osobách sedí skutečné tréninky, délky, účasti, závody a měření; plány se nezapočítávají jako druhá skutečnost.
- **Peníze:** po jednotlivých účtech, pokladnách, měnách a protistranách sedí pohyby a salda. Nula v celkovém součtu nesmí zakrýt dva opačné rozdíly různých lidí.
- **Sklad:** fyzický stav, rezervace, blokace a dostupnost mají samostatné hodnoty; každá otevřená objednávka je započtena právě jednou.
- **Doklady a soubory:** každý požadovaný originál je čitelný, má shodný otisk nebo doloženou transformaci a zůstává dostupný pouze oprávněným osobám.
- **Původ a stav:** staré zaplacení zůstává historické zaplacení, otevřený závazek je otevřený, neznámá hodnota zůstává neznámá. Import nemění původní finanční význam.

## 3. Předběžný návrh pilotu

Nejprve zkouška na syntetických datech a izolovaný převod schváleného exportu. Živý pilot se potom týká reálných potřebných operací vybraných účastníků; není důvodem vytvářet umělé produkční nákupy jen pro test.

| Okruh | Navržený rozsah | Co musí ukázat |
|---|---|---|
| Členové a rodiny | Několik rodin, více dětí, druhý oprávněný rodič a osobně obsluhovaný člověk bez účtu | Ověřené identity, správné vztahy, jednoduché osobní vyřízení a funkční přístup |
| Sport | Jedna závodní soupiska a jeden používaný kroužek | Rozvrh, skutečná docházka, omluva, používaná náhrada a oprava |
| Prodej | Několik variant s ověřeným skladem a osobním výdejem | Rezervace, platba, výdej, vratka/výměna v převzatém rozsahu |
| Klubová administrativa | Vedoucí akce, trenér a hospodář | Vlastní výdaj, záloha, vyúčtování, schválený výkaz a odměna |
| Účetní | Jeden dohodnutý předávaný balík | Doklady, peníze, otevřené zůstatky a oprava bez dvojího započtení |
| Provoz | Obsluha a zástupce | Běžná práce, výpadek, nedoručení zprávy a dohledání nevyřešeného případu |

Navržené minimum jsou dva úplné tréninkové týdny a jedno účetní předání. Neznamená to záruku dokončení do dvou týdnů; podle četnosti akcí a připravenosti etap může být pilot delší. Jednotlivé okruhy lze spustit postupně, pokud neohrozí sdílené osoby, kapacitu, peníze a sklad.

### Pravidlo jediného zapisujícího systému

Před živým pilotem vznikne tabulka konkrétních agend a případů, například:

| Agenda/případ | V pilotu zapisuje | Ostatní evidence |
|---|---|---|
| Nová docházka vybrané skupiny od stanoveného data | KIS | Evidence slouží ke čtení historie; nové souběžné zápisy jsou uzavřeny |
| Nové objednávky vybraného rozsahu | KIS | Shoptet nemá nezávisle dostupné stejné rezervované poslední kusy |
| Stará rozpracovaná objednávka | Systém určený v registru doběhu | Druhý systém má odkaz a zohledněnou rezervaci, nepřijímá druhou úhradu/výdej |
| Doklady a úhrady přes rozhodný okamžik | Jedno určené vystavení/párování pro každý případ | Účetní dostane sjednocený balík s původem, ne dvě nekontrolované kopie |
| Náhradní záznam při výpadku | Číslovaný papír/řízená tabulka s vlastníkem | Po návratu jednorázový přepis s vazbou na původ a kontrolou duplicit |

Jde o vzor k doplnění konkrétními agendami a daty. Starý systém může u jiných skupin dočasně zůstat hlavním místem práce. Tato hranice musí být viditelná obsluze a nesmí vytvářet dvě pravdy pro jednu společnou kapacitu nebo zůstatek.

### Podmínky ukončení pilotu

Každá používaná agenda má úspěšné přejímací scénáře; nejsou nevyřešené P0, nevysvětlené peníze, chybné role ani dvojí rezervace. Obsluha zvládne práci bez zásahu vývojáře do databáze. Účetní přijala přesný balík a správce umí použít návratový postup. Přetrvávající drobná vada má vlastníka a termín, nepřekrývá chybějící základní funkci.

## 4. Postup ostrého přepnutí

Tabulka je budoucí pracovní protokol, nikoli příkaz ke spuštění. V realizaci se doplní konkrétní osoby, časy, odkazy na důkazy a stav každého kroku.

| Krok | Úkon a odpovědnost | Důkaz pro pokračování |
|---|---|---|
| C01 | Vedoucí přechodu sestaví balík k rozhodnutí: rozsah, release, mapy, vstupy, kontrolní výsledky, okno, návrat | Potvrzená přejímka pilotu a konkrétní rozhodnutí o převodu |
| C02 | Obsluha připraví seznam rozpracovaných obchodů/akcí, fyzický sklad a otevřené peníze | Každý případ má systém dokončení a vlastníka; nedochází k dvojí rezervaci |
| C03 | Správce a obsluha uzavřou nové zápisy do dotčených agend; ověří i importy, úlohy a přímé administrační cesty | Prokázaná účinná uzávěra, nestačí cedule na homepage; nouzová evidence připravena |
| C04 | Správce zachytí fronty, aktivní platby a probíhající požadavky; nastaví dohodnutý režim doběhu | Události jsou trvale dohledatelné; nic se neztratí pauzou ani nezpracuje dvakrát |
| C05 | Správce pořídí finální konzistentní exporty a zálohy; kontrolující ověří cíl a manifest | Úplné sady DB/souborů/klíčů, potvrzené otisky a reference ověřené obnovy |
| C06 | Převodník vytvoří náhled posledních změn proti zkoušce i současnému cíli | Stejné schválené mapy; nové konflikty vyřešeny nebo dotčený přechod odložen |
| C07 | Oprávněný správce provede přesně schválené dávky, sleduje dobu a stav | Protokol bez nevyřešeného selhání, žádný změněný vstup ani nečekaný vedlejší účinek |
| C08 | Registrář, trenér, skladník a hospodář porovnají výsledky v dotčeném rozsahu | Sedí vazby, souhrny, sklad, salda, potřebné soubory a přístupy |
| C09 | Vedoucí rozhodne otevřít / pokračovat v uzávěře / vrátit se podle E6-16 | Uvedený rozhodující, čas a důvod; vše před prvním novým zápisem |
| C10 | Správce otevře nové zápisy a povolené integrace v určeném pořadí | Jediný hlavní systém pro daný případ, správná konfigurace, záznam okamžiku otevření |
| C11 | Obsluha ověří první skutečné potřebné případy a pozdní události starého systému | Doložené platby, objednávky, rezervace, zprávy a jejich neduplicitní zpracování |
| C12 | Provozní tým přejde na zvýšený dohled; staré služby zůstávají podle registru doběhu/archivu | Otevřené problémy mají vlastníka; odstavení se řeší až po stabilizaci |

Konkrétní délka okna se odvodí ze změřené plné zkoušky plus rezervy na kontroly a návrat. Musí existovat poslední čas pro rozhodnutí o návratu; bez něj se odstávka může neřízeně prodlužovat. Změna kódu, schématu nebo map těsně před přepnutím vyžaduje zopakovat dotčené kontroly, nikoli jen přepsat číslo verze v protokolu.

### Události během uzávěry

- Už zahájená platba nebo bankovní převod může doběhnout, i když jsou nové objednávky zastavené. Událost musí přijmout ověřená trvalá cesta nebo se musí prokazatelně znovu načíst a spárovat podle reference. Nelze spoléhat na předpoklad, že zákazník v odstávce nezaplatí.
- Fronty zpráv se nevymažou ani hromadně nepřehrají. Určit položky k dokončení, zneplatněné staré zprávy a již provedené odeslání; zkontrolovat adresáta a platnost vazby při obnovení.
- Ruční výdej, hotovost nebo telefonická změna v uzávěře buď počká, nebo dostane číslovaný náhradní záznam s odpovědnou osobou. Jejich přepis patří do závěrečné kontroly.
- Přesměrování veřejné stránky není automaticky přesměrováním platební odpovědi, webhooku, přihlášení ani soukromé přílohy. Každá taková vazba má samostatně ověřený režim.

## 5. Stop podmínky a návrat

Přepnutí dotčené agendy se zastaví při neověřené záloze/klíči, změně vstupního otisku, nejasném cíli, nové konfliktní identitě aktivního případu, nevysvětleném peněžním nebo skladovém rozdílu, chybějícím povinném souboru, porušení přístupu, nezvládnutých příchozích událostech nebo nedostatku času na ověřený návrat.

| Okamžik problému | Postup | Co nelze předpokládat |
|---|---|---|
| Náhled před zápisem | Odložit, opravit vstupy/mapu, znovu předložit konkrétní náhled | Staré schválení platí i pro změněný balík |
| Pád uvnitř dílčí transakce | Ověřit skutečný stav transakce, protokol a soubory; bezpečně pokračovat nebo vrátit příslušnou dávku | Databáze automaticky vrátila i přesuny souborů či externí účinek |
| Po převodu, před otevřením zápisů | Uchovat stav incidentu, ověřit absenci nových obchodních účinků, použít nacvičenou kompatibilní obnovu nebo opravu | Starší kód je kompatibilní s novým schématem nebo rollback kódu vrací data |
| Po otevření a nových operacích | Zastavit dotčené zápisy, uchovat nové transakce a důkazy, vyčíslit rozdíl; rozhodnout opravu vpřed nebo řízený návrat s jejich převzetím | Prostá stará záloha zachová nové objednávky, platby a výdeje |
| Návrat ke starému systému | Nejprve převzít relevantní nové záznamy a rezervace, vyřešit probíhající platby; teprve pak otevřít zápisy | Starý systém je stále aktuální jen proto, že běžel na jiné adrese |

Vedoucí přechodu rozhoduje o zastavení/otevření podle důkazů správců. Hospodář potvrzuje zachování peněz, skladník fyzické výdeje a správce technickou obnovu. V jednom malém týmu může člověk zastávat více rolí; kontroly nesmějí zůstat beze jména.

Záměr je neztratit žádnou potvrzenou obchodní operaci. Záloha s nenulovým odstupem proto potřebuje prokazatelný záznam změn a ruční doplnění tam, kde automatické obnovení nestačí. Odstávku a nejvyšší přípustnou ztrátu ostatních dat vedení potvrdí před pilotem, až bude známý výsledek zkoušky.

## 6. Dokončení přechodu

První pracovní dny se denně kontrolují skutečné platby a vratky, kapacity, sklad, zprávy, docházka a soubory. Pak se přejde na dohodnutý běžný režim. Minimem pro převzetí je první účetní předání, uzavření kritických závad a kontrola všech rozpracovaných starých případů; samotný týden bez pádu webu nestačí.

Před ukončením staré služby musí být doloženo:

1. Nemá neřízené nové zápisy a všechny doběhy mají funkční místo vyřízení.
2. Historie a originály jsou čitelné i bez jejího předplatného; vzorek archivu byl skutečně otevřen.
3. Veřejné adresy a používané externí vazby mají schválený režim, včetně e-mailu a pozdních plateb.
4. Vedení a správci agend převzali rozsah; archiv má vlastníka, přístupová pravidla a schválené uchování.
5. Odpojení integrací, zrušení služby a případné odstranění dat jsou konkrétní samostatné kroky. Převod dat je neprovádí automaticky.

Výstupem celé etapy je převzatý provoz a dohledatelný archiv, nikoli pouze úspěšný importní log. V této přípravné fázi vzniká pouze zadání tohoto výsledku.
