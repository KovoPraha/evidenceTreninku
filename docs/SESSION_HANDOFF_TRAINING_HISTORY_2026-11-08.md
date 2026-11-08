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
