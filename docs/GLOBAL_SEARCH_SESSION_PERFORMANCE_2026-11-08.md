# P11-RGS — souběh globálního hledání a session

**NEVYBRÁNO:** UI input je vypnutý, neaktivovat. Realizovaný/nasazený [RAS](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md). Původní plán níže je historický.
8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
Vlastník: vývoj podle nového zadání Marka; commit/merge/deploy při shodě,
naměřeném přínosu a aktuálních kontrolách. [Předání](SESSION_HANDOFF_GLOBAL_SEARCH_2026-11-08.md),
[výsledky](verification/global-search-session-performance-20261108/RESULTS.md).

## Důvod a rozsah

[Analýza5/6](verification/performance-analysis-20261107/RESULTS.md): substringové
hledání a sdílený bootstrap/session. Ceny, katalog, docházka, plánovač, zdravotní
filtry a všechny hlavní historie jsou již opravené. Globální hledání má jen3SQL
a malé výstupy, ale při zamykajícím handleru drží session během všech LIKE čtení.
Nejdříve ověřit mechanismus skutečnými HTTP; počet SQL není důkaz rychlosti.
Prefix/fulltext by změnil výsledky, proto tento řez nemění hledací SQL ani indexy.

Basec548d1d3b49ef63645d8e9ff421f823ce55e1712; produkce82efa16d,
deploy42521982178 SUCCESS. Větev codex/global-search-session-performance-20261108,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní checkout codex/fio-auto-reconciliation-recovery/WIP zachovat;
kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
Převzít jen již přijatý main, neslučovat cizí otevřená PR.

## Akceptace a postup

1. Zmrazit celý ajax_global_search.php na base. Nejdříve vlastní Linux CI
   s nativními MariaDB10.3/11.4 a skutečným loginem, souborovým session handlerem
   a více PHP HTTP workery. Nezavádět sleep, SQL SLEEP, síťové stuby nebo ručně
   podstrčenou přihlášenou session do měřené aplikace.
2. Změřit dlouhý skutečný substringový dotaz nad rostoucí vlastní syntetickou
   historií/osobami. Souběžně krátký skutečný dotaz stejné session a kontrola
   jiné session téhož aktéra. Časování celé HTTP,4zahřátí +17párů; jednotlivý
   požadavek zvlášť. SQL result cache OFF. Diagnostiku SQL/zámku oddělit od časů.
   Pokud blokování nebo čistý přínos není doložený, tento kandidát neuvolnit.
3. Kandidátní změna: až po lifecycle a čerstvé DB revokaci/obnovení práv a
   potřebných session zápisech uzavřít session před čistou projekcí. Lokálně
   převzít vše potřebné před uzavřením. Žádná globální cache práv, žádný plošný
   read_and_close/bootstrap zásah, změna handleru, TTL, rotace nebo CSRF.
   Prověřit, že pozdější kód/CSP filtr session znovu neotevírá ani nezapisuje.
4. Parita celé JSON odpovědi a statusů: délka/trim/Unicode, jména oběma směry,
   podřetězce/wildcards, HTML a délka popisů, duplicity, pořadí, limity6/5/5,
   prázdno a druhý trenér. Nepřidávat filtrování osob/rolí nebo změny pořadí:
   nový perfslice zachovává dosavadní autorizační kontrakt endpointu.
5. Standardní login více rolí a dvou sessions stejného aktéra, anonym/odvolaný
   účet a následná čerstvá revokace. CSRF/identity/workspace/lifecycle a session
   zápisy musí přežít. Ověřit kanonické odhlášení souběžně s hledáním a že
   dokončení starého čtení neoživí session. Tyto syntetické writery evidovat
   odděleně od čtecích GET; žádné role nebo zápisy v produkci.
6. Chrome: skutečné pole globálního hledání, výsledky/kliknutí/nové hledání a
   navigace během hledání, desktop/390px; defaultní a zahřáté načtení. Nejprve
   stejné session HTTP, potom skutečné browser ověření. Žádný nový veřejný route.
7. Porovnat doménové checksumy, uložit bezpečné vzorky/zdrojové hashy a úklid
   pouze vlastního schématu, configu, účtu, browseru a všech workerů procesu.
   Žádné skutečné profily, credentials, cookies nebo raw soukromé HTML v Gitu.
8. Aktuální celé CI a measured-source/whole tested-merged tree identita před
   chráněným deployem import/UATfalse, backup/VS/migrace/smoke a čtecí kontrola
   produkce. Finální plán/handoff/důkazy sdílet v main, ne jen lokálně nebo v PR.

Produkční session handler a skutečný lock wait zatím NEOVĚŘENÉ; závěry o souběhu
budou výslovně pro ověřený syntetický handler. Žádná produkční SLA ani tvrzení
že každý požadavek musí zrychlit. Přihlášené produkční UAT je samostatný důkaz.
Další krok: fixture/baseline souběhu, potom úzká změna a párové ověření.

## Uzavření výběru kandidáta — NEVYBRÁNO

Aktuální c548d1d3 má v hlavicka.php řádek859 `if (false && $is_logged_in)`
a JS ukončí hledání bez tohoto inputu. Globální hledání není v běžné hlavičce
aktivní. Dílčí návrh byl vrácen pouze ve vlastní kopii; žádný runtime/produkční
zásah nebyl sloučený ani nasazený. Koncepty zůstávají verzované.
Mechanismus session se přesouvá na aktivní profilové AJAX v P11-RAS:
[plán](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md). Původní WIP beze změny.
