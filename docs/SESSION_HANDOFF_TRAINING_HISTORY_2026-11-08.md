# Předání P11-RH — správní historie tréninků

8.11.2026 ROZPRACOVÁNO, plán před implementací.
[Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md),
[výsledky](verification/training-history-page-performance-20261108/RESULTS.md).
Další nejvyšší zbývající kandidát původní analýzy: neomezené správní seznamy,
podrobnosti a účastníci celé historie. Po25ID, kanonický filtr hierarchie před stránkou,
celkové souhrny zachované. Celá historie dále dostupná stránkami.
Větev codex/training-history-page-performance-20261108, basecc805561,
vlastní stávající medical-state worktree; původní WIP hash shodný.
Další krok: zmrazit referenci a implementovat; parita/měření/merge/deploy NOT RUN.
Vlastník již povolil celý ověřený release; nepoptávat znovu schválení.

První implementace obou správních cest lokálně hotová:25ID, přesné souhrny/hierarchie před stránkou, účastníci/štítky pouze stránky. Lokální parita3/922 a styl7souborů/0chyb PASS; SQLite10k celé čtení798→83ms,140000→10350řádků,22→4SQL. Profil před formátováním, finální nativní a celé HTTP/Chrome důkazy čekají. Žádné produkční změny; další AJAX/profilové historie neuzavřené.

PR #394 otevřené; implementace3/922 a styl PASS. Doplněný vlastněný HTTP/Chrome runner: původní oba route soubory zcc805561 proti stránkování na stejném plně migrovaném fixture1000nových tréninků. Přesné otisky řádků/akcí/CSRF markerů a celkové statistiky,12kladných a6záporných případů,17časů po5zahřátích;3Chrome scénáře×13pozorování/fázi a skutečný klik další stránky +zachování filtrů/nové hledání. Funkční shoda celých dat přes všechny stránky nativně; wholeHTML se záměrně liší novou paginací. Doménové checksumy beze změny, cleanup a obnova obouzdrojů povinné. Výsledky nativního/HTTP měření ještě NOT RUN. CI doposud ručně spuštěné Testy42483015467; čeká i nový synchronizační běh PR.

Před CI převzatý main755b20a2 (#390 mobilní plánovač a #393 přejímky). Zdravotní/role/picker skutečné čtecí meze zachované, naše E19 za již přijatými E14–18. Konflikt PR bránil pull_request workflow; po jeho vyřešení znovu nativní/HTTP a celé CI. Plánovač a jeho srovnávací harness jsou převzaté, nejsou druhou naší opravou.

První nativní CI66330773 našlo pouze nesprávné testové pořadí účastníků napříč různými tréninky při shodném jménu. Testový fixture přidá trenink_id jako poslední tie-break; pořadí jmen uvnitř každého tréninku a produkční SQL beze změny. HTTP fixture doplní chybějící legacy TEXT sloupce poznamka/obrazky (jen syntetické schéma). PHPStan našel pokles dvou starých pdo výjimek8→7; baseline snížená, nepotlačené chyby nepřibývají. Nová nativní/HTTP kontrola nutná, parita se neoslabuje.

Upřesnění nativního srovnání: produkční pořadí účastníků nadále přesně ORDER BY příjmení,jméno; žádný dodatečný sort key v měřeném SQL. Fixture používá i stejnou CONCAT projekci. Při porovnání mimo časový/paměťový interval se pouze seskupí řádky podle trenink_id, jejich vzájemné prokládání mezi tréninky se nevykresluje; vnitřní pořadí jmen a plné hodnoty zůstávají striktně shodné. Štítky jsou mapy porovnané podle klíče. Lokálně3testy/3254assertions před touto úpravou PASS; finální nativní a HTTP měření ověří aktuální zdroje.

První celé HTTP s doplněnými legacy sloupci ověřilo původní fázi i jednoduchou stránku. Bohatá statistika se musí porovnávat podle přesného páru štítek/hodnota (nikoli surová whitespace spojeného textu, po PSR-12 a změně remíz pořadí karet není datová hodnota). Nový kontrolní otisk má všechny původní počty/hodiny/kategorie jako striktní mapu, obsah řádků/akcí zůstává striktní. Případná další neshoda vypíše jen syntetické počty a statistiky, ne cookies/HTML.

Iterace1/a857fd18: native11.4 i celé HTTP/Chrome PASS, přesná data/statistiky,5checksumů a úklid. Na10k11.4 čtení1951→117ms,140000→10350řádků,22→4SQL. Bohatý skutečný GET1002tréninků313→145ms,8,4MB→253kB. Skutečné kliknutí další stránky/filtry/nové hledání PASS. Důkazy iteration1. Další lokální řez před implementací: bez filtru skupiny/podskupiny jsou LEFT JOIN legacy vazeb zbytečné, jejich násobnost již ruší GROUP BY/DISTINCT. Odstranit pouze JOIN aliasu nepoužitého ve WHERE, povinné coach/trainer JOIN zachovat; při filtraci konkrétní alias ponechat. Nová parita samostatných i společných legacy filtrů a nové měření/HTTP před release.

Iterace2 lokálně3testy/5006assertions PASS, včetně samostatného filtru legacy skupiny a podskupiny. Ponechané JOIN aliasy použité ve WHERE, ostatní LEFT JOIN odstraněné jako nadbytečná násobnost; data, filtry, souhrny a celá stránkovaná množina přesně shodné s referencí. Nová nativní a HTTP/Chrome měření se teprve spouštějí.
