# P11-RH — stránkované správní historie tréninků

8. 11. 2026. ROZPRACOVÁNO. Vlastník: vývoj podle nového výslovného zadání Marka;
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
