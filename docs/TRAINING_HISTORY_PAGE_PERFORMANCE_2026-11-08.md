# P11-RH — stránkované správní historie tréninků

8. 11. 2026. HOTOVO v technickém rozsahu / NASAZENO. Vlastník: vývoj podle nového výslovného zadání Marka;
commit/merge/deploy po shodě výsledků, přínosu a zelených kontrolách.
[Handoff](SESSION_HANDOFF_TRAINING_HISTORY_2026-11-08.md), [výsledky](verification/training-history-page-performance-20261108/RESULTS.md).

## Výběr a výchozí stav

[Analýza](verification/performance-analysis-20261107/RESULTS.md), pořadí4: neomezená
tréninková historie. Dřívější vyšší kandidáti (ceny/katalog/docházka) i R05/R13 již
nasazené. Nejvyšší zbývající doložený mechanismus: kompletní poznámky, obrázky,
účastníci a skryté detaily všech tréninků v počátečním GET. Časový přínos nového
řezu zatím NOT RUN; původní analýza jej nepředstírala.

Base `cc80556131f855cb29ec75c335873574930577af`, produkce519ff098.
Větev `codex/training-history-page-performance-20261108`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Původní checkout zůstává na cizí WIP větvi; SHA256 kis_rosters_admin.php
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`, shodný.
Staré výstupy/worktrees zachované.

## Postup před implementací

1. Cíl: sprava_treninku.php a sprava_vsech_treninku.php, společný čtecí helper.
   Nejprve tenká kandidátní projekce ID/data/kategorie/délky se stejnými JOIN,
   predikáty a seskupením. Hierarchie soupisek kanonicky ke dni tréninku PŘED stránkou.
2. Vybrat25ID, až potom původní detailní projekce, štítky a účastníci pouze stránky.
   Přesné celkové počty/hodiny/kategorie nad všemi filtrovanými kandidáty zachovat.
   Všechny tréninky dál dosažitelné stránkami; nadměrná stránka se ořízne na poslední.
   Jednoznačné pořadí datum DESC,id DESC upřesní dříve neurčené remízy data.
3. Zachovat formuláře/CSRF/zápisy/oprávnění a všechny dosavadní filtry. Vyhledávání
   správního seznamu se odesílá serveru pro celou historii; původní lokální skrývání
   DOM by po stránkování hledalo pouze na stránce, proto jej nahradit jasným popisem
   serverového hledání v náplni/poznámce. Filtry v odkazech zachovat, nový filtr začíná1.
4. Nezávislá reference původní kompletní projekce a příslušných vazeb; ověřit přesná
   pole/typy a souhrny i sjednocení všech stránek bez ztrát/duplicit. Testovat filtry,
   duplicity JOIN, prázdné/poslední stránky, remízy data, historickou hierarchii a změny.
5. Změřit celé čtení včetně účastníků před/po na stejném syntetickém seedu,
   SQLite a MariaDB10.3/11.4, alespoň100/1k/10k záznamů, cache SQL výsledků OFF,
   neinstrumentované střídavé časy, SQL/vrácené řádky zvlášť, shodné checksumy.
   Webové scénáře ověří stránkování, zachované formuláře a zamítnuté role.
6. Důkazy do repa; zelené kontroly, merge přesného ověřeného stromu, standardní
   deploy s import/UAT false, ověřená záloha a čtecí produkční kontrola. Přihlášené
   produkční UAT evidovat zvlášť. Nepřidávat DDL, cache práv, nové služby ani writery.

Akceptace: původní úplná datová množina a souhrny shodné se všemi stránkami,
plné detaily a účastníci jen25tréninků, materiální přínos na větších datech.
Další AJAX/profilové historie a ostatní K0.2 nejsou tímto řezem automaticky hotové.

První implementace obou správních cest lokálně hotová:25ID, přesné souhrny/hierarchie před stránkou, účastníci/štítky pouze stránky. Lokální parita3/922 a styl7souborů/0chyb PASS; SQLite10k celé čtení798→83ms,140000→10350řádků,22→4SQL. Profil před formátováním, finální nativní a celé HTTP/Chrome důkazy čekají. Žádné produkční změny; další AJAX/profilové historie neuzavřené.

PR #394 otevřené; implementace3/922 a styl PASS. Doplněný vlastněný HTTP/Chrome runner: původní oba route soubory zcc805561 proti stránkování na stejném plně migrovaném fixture1000nových tréninků. Přesné otisky řádků/akcí/CSRF markerů a celkové statistiky,12kladných a6záporných případů,17časů po5zahřátích;3Chrome scénáře×13pozorování/fázi a skutečný klik další stránky +zachování filtrů/nové hledání. Funkční shoda celých dat přes všechny stránky nativně; wholeHTML se záměrně liší novou paginací. Doménové checksumy beze změny, cleanup a obnova obouzdrojů povinné. Výsledky nativního/HTTP měření ještě NOT RUN. CI doposud ručně spuštěné Testy42483015467; čeká i nový synchronizační běh PR.

Před CI převzatý main755b20a2 (#390 mobilní plánovač a #393 přejímky). Zdravotní/role/picker skutečné čtecí meze zachované, naše E19 za již přijatými E14–18. Konflikt PR bránil pull_request workflow; po jeho vyřešení znovu nativní/HTTP a celé CI. Plánovač a jeho srovnávací harness jsou převzaté, nejsou druhou naší opravou.

První nativní CI66330773 našlo pouze nesprávné testové pořadí účastníků napříč různými tréninky při shodném jménu. Testový fixture přidá trenink_id jako poslední tie-break; pořadí jmen uvnitř každého tréninku a produkční SQL beze změny. HTTP fixture doplní chybějící legacy TEXT sloupce poznamka/obrazky (jen syntetické schéma). PHPStan našel pokles dvou starých pdo výjimek8→7; baseline snížená, nepotlačené chyby nepřibývají. Nová nativní/HTTP kontrola nutná, parita se neoslabuje.

Upřesnění nativního srovnání: produkční pořadí účastníků nadále přesně ORDER BY příjmení,jméno; žádný dodatečný sort key v měřeném SQL. Fixture používá i stejnou CONCAT projekci. Při porovnání mimo časový/paměťový interval se pouze seskupí řádky podle trenink_id, jejich vzájemné prokládání mezi tréninky se nevykresluje; vnitřní pořadí jmen a plné hodnoty zůstávají striktně shodné. Štítky jsou mapy porovnané podle klíče. Lokálně3testy/3254assertions před touto úpravou PASS; finální nativní a HTTP měření ověří aktuální zdroje.

První celé HTTP s doplněnými legacy sloupci ověřilo původní fázi i jednoduchou stránku. Bohatá statistika se musí porovnávat podle přesného páru štítek/hodnota (nikoli surová whitespace spojeného textu, po PSR-12 a změně remíz pořadí karet není datová hodnota). Nový kontrolní otisk má všechny původní počty/hodiny/kategorie jako striktní mapu, obsah řádků/akcí zůstává striktní. Případná další neshoda vypíše jen syntetické počty a statistiky, ne cookies/HTML.

Iterace1/a857fd18: native11.4 i celé HTTP/Chrome PASS, přesná data/statistiky,5checksumů a úklid. Na10k11.4 čtení1951→117ms,140000→10350řádků,22→4SQL. Bohatý skutečný GET1002tréninků313→145ms,8,4MB→253kB. Skutečné kliknutí další stránky/filtry/nové hledání PASS. Důkazy iteration1. Další lokální řez před implementací: bez filtru skupiny/podskupiny jsou LEFT JOIN legacy vazeb zbytečné, jejich násobnost již ruší GROUP BY/DISTINCT. Odstranit pouze JOIN aliasu nepoužitého ve WHERE, povinné coach/trainer JOIN zachovat; při filtraci konkrétní alias ponechat. Nová parita samostatných i společných legacy filtrů a nové měření/HTTP před release.

Iterace2 lokálně3testy/5006assertions PASS, včetně samostatného filtru legacy skupiny a podskupiny. Ponechané JOIN aliasy použité ve WHERE, ostatní LEFT JOIN odstraněné jako nadbytečná násobnost; data, filtry, souhrny a celá stránkovaná množina přesně shodné s referencí. Nová nativní a HTTP/Chrome měření se teprve spouštějí.


Finální varianta2a02e96f: nativní obě MariaDB3/5006, všechny stránky a hodnoty PASS; vlastní HTTP/Chrome408časů/12negative/78pozorování a interakce PASS. Čtení10k MariaDB10.3 2733→38ms,11.4 1850→39ms; bohatý GET1002 398→16ms,8,41MB→253kB. Zdrojové SHA a checksumy ověřené, cleanup PASS. Podrobnosti a malé režijní případy ve výsledcích. Celá regresní sada ještě dobíhá; merge/deploy zatím NOT RUN.


Všech14CI na2a02e96f PASS (hlavní sada2392testů/53289assertions). #394 sloučené jakoab49947d9f673636d717fbbb4acb04f608eff477; celý Git tree a SHA256 měřených zdrojů přesně stejné jako testovaný HEAD. Standardní deploy42486500282 spuštěný na tomto přesném main, import/UAT false; výsledek zatím čeká. Finální dokumentační větev codex/training-history-release-proof-20261108.


Finální vydání: #394/ab49947d, deploy42486500282 SUCCESS; záloha389/2,2392testů/53289assertions, migrace current, VS beze změny, aktivace/smoke PASS. Katalog5/5DOM a obě historie302/login před/po shodné. [Trvalý produkční protokol](verification/training-history-release-20261108/RESULTS.md). Přihlášené produkční UAT NOT RUN; další P11 řezy otevřené. Dokumentační předání vlastní release-proof větví bez druhého nasazení.

## P11-RHC2 — omezená agregace vzorů kopírování, 12. 11. 2026

ROZPRACOVÁNO, Vývoj/Codex na pokyn Marka pokračovat v měřených optimalizacích,
testech, merge a nasazení. Base 7d452b30, poslední runtime dbab9e6e.
Větev codex/training-copy-performance-20261112, samostatný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-training-copy-performance-20261112.
Původní fio WIP zůstává zachovaný.

Kopírovací seznam nyní násobí vazby skupin, podskupin a účastníků pro celé
období ještě před filtrem historické hierarchie a limitem 100. Změřit
kandidátní čtení ID a data, stávající kanonický filtr týmů a detailní
agregaci nejvýše 100 vybraných tréninků. Zachovat predikát pracovního
rozsahu, skupinový filtr v agregaci, období, návratové typy, počty,
štítky, ovládání a odkazy na předvyplnění. Parita zahrne remízy dat,
nulový a neexistující tým, starší záznamy bez vazby a prázdné výsledky.

Nezávislá reference vychází z přesného původního SQL. Přejímka na stejných
datech a runneru před/po porovná celé řádky a celé HTTP včetně hlaviček,
role a shodné kontrolní součty. Měření 100, 1 000 a 10 000 tréninků,
4 zahřívací a 17 střídavých měřených párů, MariaDB 10.3/11.4. Přínos
ověřit časem, pamětí a rozsahem detailů. Merge/deploy až po shodě,
prokázaném přínosu a zeleném CI. Produkční read-only kontrola po nasazení.
[Průběžné výsledky](verification/training-copy-20261112/RESULTS.md).

### Kontrola remíz P11-RHC2

První místní porovnání odhalilo rozdílné pořadí stejně datovaných tréninků.
Nová cesta kontroluje prvních 101 způsobilých kandidátů. Pokud se datum
opakuje, provede přesný původní dotaz včetně původního limitu a týmového
filtru. Shoda zahrne i nejednoznačnou hranici sté položky. Zisk se bude
vykazovat samostatně pro jednoznačné pořadí a režii při remízách.

### HTTP přejímka P11-RHC2

Na MariaDB 11.4 prošla nativní parita včetně remíz. HTTP kontrola ukázala,
že pracovní pozice vedoucího sportu má na této trase dosavadní 403.
Matice nyní zachovává toto odmítnutí, testuje registráře a přidává
pozitivní druhý trenérský účet s kontrolou nepřítomnosti cizího tréninku.

### Průběžný profil P11-RHC2

Diagnostický běh 43212500796 na 10.3 potvrdil paritu. Pro 10 000 jednoznačně
datovaných tréninků vyšel seznam 439,67 → 7,43 ms a týmový výběr
505,51 → 76,60 ms. Při remízách týmový výběr zdražil 494,86 → 543,61 ms.
Před dokončením proto rozpoznáváme remízy také před prvním vyhodnocením
hierarchie a u návratu k původnímu dotazu uvolňujeme kandidáty. Tyto hodnoty
patří průběžnému commitu acbcea30, poslední varianta čeká na nové měření.
