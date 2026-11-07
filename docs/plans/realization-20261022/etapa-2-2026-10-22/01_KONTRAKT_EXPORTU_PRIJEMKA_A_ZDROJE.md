# Etapa 2 — kontrakt exportu a přejímka

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 2](00_ETAPA_2_PLAN.md), 22. 10. 2026. Níže je návrh pro budoucí implementaci a převzetí účetní. Nejde o hotový export ani o provedené testy.

## Rozhodnutí před implementací

| Rozhodnutí | Již víme | Je potřeba určit | Ovlivní |
|---|---|---|---|
| Účetní režim | Jednoduché účetnictví, neplátce DPH | Konkrétní členění příjmů/výdajů a metodiku záloh | E2-03/06/09 |
| Právní vystavovatel | Jde o činnost oddílu | Přesná právnická osoba, identifikační údaje, účty a odpovědní | E2-04/05 |
| Účetní předání | XLSX + faktury | Vzor současného předání, program, ruční/strojové zpracování | E2-11/15 |
| Okamžik vydání | Zboží, kurzy a členské příspěvky mají rozdílné postupy | Pravidlo vydání, záloha/konečný doklad, osobní prodej | E2-05/06 |
| Číslování | Dosavadní a stálé VS se zachovají | Formát dokladových řad, počátek a hranice Shoptet/KIS | E2-05/14 |
| Úplnost balíku | Potvrzeny i papírové výdaje, zálohy a odměny | Které další příjmy/výdaje existují a kdo je dodává | E2-02/09/12 |
| Peníze | Hotovost, převody, základ online plateb | Používané pokladny/účty/kanály, výpisy a počáteční stavy | E2-07/10/14 |
| Účetní období a opravy | Měsíční předávání je navržený základ | Termín předání, postup pozdních dokladů, uchování a převzetí | E2-12/13 |

## Oddělené evidence a vazby

Jedna ekonomická povinnost může mít předpis, objednávku i vydaný doklad, ale v saldu smí být jen jednou. Jeden skutečný peněžní pohyb může hradit více povinností. Jedno přidělení peněz může mít více účetních členění. Technický návrh musí zachovat tyto rozdíly:

| Objekt | Úloha | Co není |
|---|---|---|
| Obchodní případ / pohledávka | Co má kdo zaplatit a za co | Potvrzený příjem peněz |
| Vydaný doklad | Neměnný obsah případu v okamžiku vydání | Druhá kopie pohledávky |
| Skutečný peněžní pohyb | Co kdy skutečně přišlo/odešlo a přes jaký účet | Každý řádek platebního požadavku |
| Přidělení úhrady | Kolik z konkrétního pohybu kryje konkrétní povinnost | Nový bankovní příjem |
| Účetní členění pohybu | Rozpis částky podle metodiky účetní | Totožné rozdělení mezi děti |
| Skladový pohyb / plnění | Co a kdy bylo vydáno, vráceno či poskytnuto | Automatické přijetí/vrácení peněz |
| Exportní balík | Zmrazená revize předaných podkladů | Živý report přepočítávaný při každém stažení |

Příklad: rodič zaplatí 2 500 Kč za předpisy 1 000 Kč a 1 200 Kč. V Penize je jeden příjem 2 500 Kč, v Parovani dvě přidělení, nepřidělený zbytek je 300 Kč. Cleneni_penez rozepíše celý příjem podle schválené metodiky včetně zbytku; jeho nezařazená část se neztratí. Účetní posouzení přeplatku se neodvodí pouze z technického stavu.

## Mapa existujících zdrojů

| Zdroj v systému | Použít pro | Povinná kontrola při propojení |
|---|---|---|
| `shop_orders` a položkové tabulky | Prodejní případ, ceny, varianty, služby, zákazník a historie | Nezaměnit objednávku za vydanou fakturu; doplnit potřebné fakturační údaje |
| `shop_order_accounting_items` a účetní číselníky | Historické obchodní zařazení položek | Doprava/sleva/částečné plnění; účetní metodika navíc |
| `club_member_charges` | Členské a jiné předpisy | Jedna pohledávka i po vystavení potvrzení/dokladu; zachovat VS |
| `payments` | Platební požadavek, kanonický stav a reference | Rozlišit přímou platbu, stav odvozený z přídělů a historický import; `paid` samo není samostatný pohyb |
| `fio_account_movements` | Původní bankovní reference, částka, datum a stav párování | Již použitý pohyb neduplikovat; nespárované skutečné peníze nevynechat; celý výpis může obsahovat další agendy |
| `club_finance_receipts` + `club_finance_allocations` | Přijaté částky sportovce a příděly na předpis/objednávku | Prověřit druh a původ každého příjmu; nepočítat současně odvozené `payments` jako další peníze |
| `member_payer_receipts` + `member_payer_receipt_allocations` | Rodinná bankovní platba a rozdělení | Jeden příjem pro více předpisů, samostatný nepřidělený zbytek |
| Údaje vratky a historie objednávky | Podklad výdeje peněz a opravy | Datum potvrzení není nutně datum výdeje; zachovat původní příjem; částečné vratky potřebují rozšíření |
| `ucto_uctenky` a soukromé přílohy | Přijaté výdaje a prvotní doklady | Kdo skutečně zaplatil, komu zbývá dluh, datum peněz a kompletnost dokladu |
| Shoptet / starší systémy / externí podklady | Původní doklady, počátky a další agendy | Zdrojové ID, úplnost, hranice období a zabránění dvojímu vykázání |

Mapování má zahrnout všechny skutečně používané zapisovací cesty včetně ručního potvrzení, automatického potvrzení poskytovatelem, přídělů, zrušení, importovaných záznamů a zpětných oprav. Nestačí spojit tabulky podle částky a data.

## Návrh účetního balíku

`KIS_ucetni_YYYY-MM_revNN.zip` obsahuje `prehled.xlsx`, `doklady/`, dohodnuté `prilohy/` a `manifest.json`. Název i listy jsou návrhem k převzetí účetní. Původních devět listů etapy 0 zůstává; desátý **Cleneni_penez** konkretizuje už požadovaný samostatný účetní rozpis pohybu.

| List | Jednotka řádku a minimum obsahu | Pravidlo období |
|---|---|---|
| Kontrola | ID/revize balíku, formát, období, rozhodný čas, zdroje, počty, součty, výjimky a stav | Popis celého balíku a rozdílů proti předchozí revizi |
| Doklady | Vydaný/původní externí doklad: ID, číslo, typ, stav, vazby, strany, datum vydání/případu/splatnosti, měna, částky, zálohy, VS, PDF a otisk | Podle schváleného data případu/vydání; explicitně označit dodatečné doklady a odkazy na starší originály |
| Polozky | Položka dokladu: stabilní ID, původní položka, popis, množství, jednotka, cena, sleva, částka, kategorie, středisko a období služby | Navazuje na zahrnutý doklad; změna katalogu se sem zpětně nepromítá |
| Penize | Jeden skutečný pohyb: stabilní ID/reference, skutečné datum, evidence, účet/pokladna, směr, typ, měna, částka se znaménkem, protistrana a ověření | Skutečný pohyb v období; počátky jsou označené zůstatky jinde, nikoli nové příjmy |
| Parovani | Jedno přidělení nebo jeho oprava: ID, ID pohybu, typ/ID povinnosti, částka, měna, účinnost, evidence a odkaz na rušený příděl | Události a stav potřebný pro rekonciliaci období; příděl k platbě z minulého měsíce je povolený |
| Cleneni_penez | Část pohybu: ID pohybu, kód účetního členění, činnost/středisko, částka se znaménkem, verze pravidla, stav potvrzení a případný odkaz na příděl/položku | Rozpis zahrnutých peněz a výslovně označené pozdější opravy klasifikace; není novým příjmem |
| Predpisy | Předpis: ID, osoba/plátce, účel, období, částka, splatnost, VS, stav, vazba na jednu pohledávku a případný doklad | Počáteční otevřené, nové, změněné či vypořádané v období a konečné otevřené |
| Saldo | Jedna identifikovaná pohledávka/závazek či nepřidělený zůstatek: druh, protistrana, počátek, změny, úhrady a konec | Stav k hranicím období, ne současný stav v okamžiku stažení; žádné skryté započtení dluhu dítěte proti cizímu přeplatku |
| Vydaje | Přijatý doklad / vyúčtování: zdroj, dodavatel, věřitel, skutečný plátce, částka, splatnost, úhrady, zbývá, členění a příloha | Doklady a otevřené závazky potřebné k období; platba odkazuje do Penize, nesčítá se podruhé |
| Sklad | Položka/varianta: počátek, příjmy, výdeje, vratky, inventurní rozdíly, konec a doložené ocenění | Obraty a stavy daného období; do dokončení etapy 3 výslovně omezené nebo externě doplněné údaje |

Pro reference na starší pohyby/doklady použít dohledatelné referenční údaje a označení „mimo obrat období“, případně příslušný archiv. Pomocné řádky nesmějí znovu navyšovat měsíční součty. Všechny vazby musí být pro účetní čitelné i bez přístupu do provozní databáze.

Časové hranice se definují v Europe/Prague, včetně převodu technických časů. Dnešní přepárování nesmí bez stopy přepsat stav již předaného minulého měsíce. Rozhodný okamžik exportu a datum účinnosti změny jsou oddělené.

### Technická pravidla souborů

- VS, IČO, čísla dokladů a externí ID ukládat jako text, včetně úvodních nul. Částky jako číselné hodnoty s měnou; kontrolní výpočty pracují v nejmenších měnových jednotkách.
- Uživatelský text začínající `=`, `+`, `-` nebo `@` nevkládat jako vzorec. Legitimní záporná číselná částka zůstává číslem.
- Identifikátory a formát mají verzi, sloupce mají popis; hlavička obsahuje jednotky a význam. Neznámá hodnota není nula.
- Sešit obsahuje jen potřebné osobní údaje. Interní ID účastníka lze použít pro vazbu; rodné číslo, zdravotní záznam ani přístupový token do něj nepatří.
- PDF a přílohy jsou pojmenované jednoznačně, bez kolizí, s relativní cestou v balíku. Manifest uvádí velikost a SHA-256; všechny uvedené soubory musí existovat.
- Balík je neměnný snímek. Nová revize má vlastní ID/otisk, odkaz na předchůdce a seznam změn. První verze může být vždy úplná náhrada za stejné období, nikoli neoznačený mix náhrad a přírůstků.

## Kontroly před označením připraveno k předání

| Kontrola | Požadovaný výsledek |
|---|---|
| Doklad | Součet konečných položkových částek + explicitní zaokrouhlení = celkem. Sleva i doprava jsou v částkách započtené právě jednou. |
| Pohledávka | Počáteční zůstatek + nové povinnosti + podepsané opravy − čisté platné úhrady = konečný zůstatek. Předpis a jeho faktura nejsou dvě povinnosti. |
| Příjem a přidělení | Původní příjem − skutečně vrácená část − čistá platná přidělení = nepřidělený zůstatek. Při vratce se odpovídající původní příděl musí uvolnit/opravit, aby se neodečetl dvakrát. |
| Členění | Součet účetních částí každého pohybu odpovídá jeho částce se znaménkem. Není dovolena neviditelná nezařazená část. |
| Účet/pokladna | Počáteční stav + skutečné příjmy − skutečné výdaje = konečný stav podle výpisu/fyzické kontroly, s doloženými rozdíly. Platí i pro neobchodní pohyby. |
| Převod mezi vlastními místy peněz | Provázaná výdejová a příjmová strana; případný časový přesah je doložený převod na cestě, nikoli tržba. |
| Poskytovatel platby | Počáteční nevypořádaný zůstatek + hrubé platby − vratky − poplatky − výplaty ± doložené další úpravy = konečný zůstatek. |
| Výdaj zaplacený pracovníkem | Tentýž původní doklad není současně plným neuhrazeným závazkem dodavateli i pracovníkovi; proplacení nemnoží náklad. |
| Archiv | Každý povinný soubor existuje a otisk souhlasí; stažený starší balík se shoduje s původním předáním. |
| Úplnost | Zdroje a pokrytí jsou popsané, zjištěné skutečné peníze nejsou vynechané, rozdíly nejsou zamaskované filtrem na „zaplaceno“. |

U kontrol rozdělení jsou částky přídělů čisté po evidovaných zrušeních. Přijetí 1 200 Kč, oprava poloviny prodeje, uvolnění přídělu 600 Kč a skutečná vratka 600 Kč zanechá platný příděl 600 Kč a nulový nepřidělený zůstatek. Výpočet nesmí ponechat původní příděl 1 200 Kč a zároveň odečíst vratku.

Pracovní export s výjimkami má stav **NEÚPLNÝ**. Označení **PŘIPRAVENO K PŘEDÁNÍ** vyžaduje vyřešené blokátory v dohodnutém rozsahu. Při částečném rozsahu musí být přesně uvedeno, které agendy jsou dodané odděleně a jak se kontrolují; nejde o celý účetní měsíc oddílu bez jejich návaznosti.

## Budoucí přejímací scénáře

Všechny nové zápisy a vydávání testovacích dokumentů patří do izolovaného prostředí se syntetickými daty. Níže nic nebylo při přípravě provedeno. Výsledný protokol bude uvádět revizi, prostředí, datum, zkoušejícího, skutečný výsledek a důkaz bez citlivých dat.

| ID | Úkol | Příklad a přejímací podmínka |
|---|---|---|
| B01 | E2-04/05/07 | Tričko 600 Kč osobně hotově: jedna prodejní povinnost, prodejní doklad, jeden skutečný příjem 600 Kč a PPD; žádné dvojí tržby. |
| B02 | E2-02/11 | Rodič 2 500 Kč, předpisy dětí 1 000 + 1 200: jeden pohyb, dvě přidělení, zbytek 300. Uhrazené řádky `payments` nevytvoří další příjem. |
| B03 | E2-02/06 | Roční platba proti 12 předpisům: jeden příjem, 12 přidělení; dosavadní VS zachován, budoucí předpis nevyrobí další bankovní pohyb. |
| B04 | E2-06/11 | Kurz uhrazen v prosinci, probíhá leden–červen: peníze v prosinci, služba se svým obdobím, záloha/vypořádání podle schválené metodiky. |
| B05 | E2-02/07 | Hotovost přijata 31. 10., zapsána 2. 11.: skutečné datum i čas evidence jsou odlišné, říjnový pohyb se nezmění na listopadový. |
| B06 | E2-02/08 | Vratka celého prodeje v listopadu po říjnové platbě: říjnový příjem zůstane, listopad obsahuje skutečný výdej, ne pouze konečný stav `refunded`. |
| B07 | E2-05 | Dvojklik a dvě souběžná vydání: jeden doklad pro stejný požadavek, žádná kolize ani opakované použití čísla. |
| B08 | E2-05/13 | Chyba při zápisu PDF po rezervaci čísla: žádný falešně hotový dokument; řízené dokončení/opravný stav, audit a žádná druhá faktura za stejný požadavek. |
| B09 | E2-04/05 | Změna adresy a ceny po vydání: původní PDF, položky i otisk beze změny. |
| B10 | E2-03/06 | Zboží 600 + kurz 4 100 + doprava 150, následně sleva: celkem i rozpis se sejdou na haléř; dokladový model nezdvojí smíšený obchodní případ. Ověřit jen kombinaci povolenou checkoutem, jinak dvě provázané objednávky. |
| B11 | E2-03/11 | Částečná úhrada smíšeného případu: Parovani a Cleneni_penez mají různý účel, rozpis odpovídá schválené metodě a součet skutečné platbě. |
| B12 | E2-08 | Dvě trička po 600 Kč, vráceno jedno: opravný doklad −600, samostatná vratka 600, platný zbytek 600; skladový návrat až podle skutečného stavu kusu. |
| B13 | E2-08 | Storno neuhrazeného případu: žádný fiktivní příjem ani vratka, případný již vydaný dokument má správný opravný postup. |
| B14 | E2-08/12 | Přepárování omylem přidělené úhrady mezi předpisy: žádný nový příjem, dohledatelná oprava přídělu; minulý předaný balík zůstane zachovaný. |
| B15 | E2-07 | Převod 5 000 Kč z pokladny na banku přes konec měsíce: obě strany provázané, časový rozdíl vysvětlený, žádná nová tržba. |
| B16 | E2-09 | Pracovník zaplatí účtenku 800 Kč ze svého, oddíl proplatí později: správný věřitel a neuhrazený zůstatek; klubový pohyb vznikne až skutečným proplacením. |
| B17 | E2-09 | Záloha 2 000 Kč, uznané doklady 1 700, vráceno 300: výdej zálohy a příjem zbytku jsou samostatné pohyby; účtenky se nezapočítají znovu jako odchod peněz z klubu. |
| B18 | E2-10 | Brána: platba 1 000, poplatek 20, výplata 980 v dalším měsíci: souhlasí hrubá platba, poplatek, převod a oba měsíční zůstatky; žádný dvojí prodej. |
| B19 | E2-02/11 | Nespárovaný bankovní příjem a poplatek banky: oba viditelné a zahrnuté do rekonciliace; žádné automatické spárování podle stejné částky. |
| B20 | E2-11 | VS `0000000013`, identifikátor s úvodní nulou a text `=1+1`: identifikátory zůstanou text, text se nespustí jako vzorec. |
| B21 | E2-11/12 | Doklad/pohledávka z minulého měsíce, úhrada nyní: počáteční a konečné saldo souhlasí, reference na starší dokument dostupná bez nového započtení tržby. |
| B22 | E2-12 | Chybějící PDF, členění, příloha nebo neshoda banky: pracovní balík označen neúplný, nelze jej vydávat za připravený bez vyřešení příslušného blokátoru. |
| B23 | E2-12 | Dodatečný říjnový doklad po předání října: nová revize a změnový seznam; původní předání stále dostupné, jasný režim nahrazení pro účetní. |
| B24 | E2-13 | Běžný trenér nebo nepříslušný rodič zkusí účetní export či cizí PDF: odmítnutí i přes přímou adresu; účetní dostane jen určený rozsah. |
| B25 | E2-13 | Obnova dokladů a balíků ze zálohy: stejné soubory, otisky, vazby i historie; obnovená kopie nic automaticky neodesílá. |
| B26 | E2-14 | Shoptet faktura a pozdější vratka v KIS: původní číslo a zdroj zachované, nová oprava navázaná; nevznikne druhá faktura za původní prodej. |
| B27 | E2-09/11 | Schválená odměna 900, přeplatek 300, dvě náhradní lekce: oddělené evidence; žádná automatická hotovost 1 200 Kč ani finanční ocenění lekcí. |
| B28 | E2-11 | Chybějící pořizovací cena: skladový list ukáže neznámé ocenění a omezení; nevyrobí nulovou hodnotu ani hodnotu z prodejní ceny. |
| B29 | E2-15 | Účetní zpracuje vzorový měsíc a opravenou revizi: součty odpovídají dokladům, výpisům a pokladně; dohodnuté podklady jí stačí bez ruční rekonstrukce vztahů. |

B10 není požadavek rozšířit všechny současné kombinace košíku; jde o účetní pokrytí skutečně povolených smíšených případů. B18 je povinný pro používaný kanál. B28 se plně uzavře po etapě 3 nebo pomocí doloženého externího skladového podkladu; omezení musí zůstat viditelné.

## Ověřené zdrojové podklady

Odkazy míří na neměnnou kontrolovanou revizi. Starší provozní reporty mohou popisovat jinou nasazenou verzi; zde nejsou vydávány za nové živé ověření.

| ID | Podklad | Co dokládá |
|---|---|---|
| Z1 | [Číselné řady a účetní klasifikace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_number_series.php), [popis řad](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/SHOP_NUMBER_SERIES_2026-10-21.md) | Existující kategorie, zařazení, čítače, VS a položkové snímky; fakturace a účetní export nejsou tímto vydáním hotové. |
| Z2 | [Kontrola účetního zařazení před publikací](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md) | Kontrola nové aktivace produktů; není úplnou účetní kontrolou historických prodejů ani dokladem daňového členění. |
| Z3 | [Objednávky a platby](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L375) | Snímky zákazníka a položek, životní cyklus objednávky, potvrzení a storno. |
| Z4 | [Čas potvrzení platby](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L875), [čas potvrzení vratky](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L1010) | Oba běžné toky zapisují aktuální čas; pro účetní datum je potřeba zdroj skutečného pohybu. |
| Z5 | [Hotovost a vratka](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/ORDER_PAYMENTS_2026-10-21.md) | Existující postup hospodáře; výslovně nejde o tisk dokladu ani samostatnou pokladní knihu. |
| Z6 | [Rodinné příjmy a příděly](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/member_payer_receipt.php#L46) | Jeden příjem rozdělený mezi předpisy; při plném pokrytí se mění také odvozený stav `payments`. |
| Z7 | [Klubové příjmy a saldo](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/club_finance.php#L229), [popis finančního salda](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/CLUB_FINANCE_IMPLEMENTATION.md) | Kontrola použití bankovní reference napříč zdroji, příděly a zbytek. Není hotovým účetním deníkem. |
| Z8 | [Členský předpis](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/member_charge.php), [Fio](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/fio_readonly_import.php) | Existující zdroje pro povinnost a bankovní pohyb; aktivace importu je samostatná provozní otázka. |
| Z9 | [Uložení účtenky](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/uctenky/uloz.php) | Částka, metoda, kategorie, akce/vozidlo, datum a privátní soubor s auditem. Je nutné doplnit pohled věřitele, úhrad a uzavřeného předání. |
| Z10 | [Existující XLSX export](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/export_xls.php) | Technická tvorba XLSX pomocí PhpSpreadsheet pro tréninky, nikoli hotový účetní export. |
| Z11 | [Soukromé úložiště](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/private_storage.php), [databázová záloha](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/db-backup.php) | Existující základ pro ochranu a obnovu; nové účetní soubory a snímky se musejí do kontraktu doplnit a ověřit. |
| Z12 | [Etapa 0 — účetní zadání](../etapa-0-2026-10-22/01_UCETNI_DOKLADY_A_EXPORT.md), [porovnání Evidence a KIS](../etapa-0-2026-10-22/03_EVIDENCE_VS_KIS.md) | Potvrzený režim, původní devítilistý návrh, scénáře a finanční rozpory k rozhodnutí. |

Účetní rámec byl při přípravě znovu ověřen na [oficiálním přehledu Finanční správy](https://financnisprava.gov.cz/cs/dane/dane/dan-z-prijmu/ucetnictvi/obecne-informace). Konkrétní daňové posouzení jednotlivých činností, metodika záloh a podoba předání jsou výstupem E2-01, nikoli automatickým závěrem z účetní kategorie produktu.

## Co tato příprava změnila

Vznikl pouze místní plán a tato příloha. Nebyl změněn aplikační kód, účetní mapování, existující VS, doklad, platba, databáze ani produkční nastavení. Testovací scénáře nebyly provedeny a účetní balík nebyl vygenerován ani odeslán.
