# P15 S1 — syntetické ověření KIS

7. 11. 2026. [Technické předání](../../CSC_SPORTSOFT_S1_KIS.md),
[plán](../../CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md).
Pouze lokální izolované SQLite/MariaDB databáze a syntetické S0 fixtures.
Nečte se aplikační config/DB, portály ani Riki. S1 v samostatné větvi
`codex/csc-sportsoft-s1-ingest-20261107`, závislost S0 #371.
Předáno jako [draft PR #376](https://github.com/KovoPraha/evidenceTreninku/pull/376),
Ověřený commit **2fe8aa93** (serverový runtime f61c365d + shodné schéma
historických profilů): **11/11 CI kontrol PASS**.
[Celé testy 42392402107](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42392402107),
[quality 42392402121](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42392402121),
[dokumentace 42392402155](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42392402155).
Následný commit pouze zapisuje tento výsledek do dokumentace; nemění runtime,
migraci, workflow ani testy. Merge/deploy/aktivace jsou NOT RUN.

| Kontrola | Stav / důkaz |
| --- | --- |
| První cílený průchod SQLite + UCI | PASS 12 testů / 131 assertions; průběžný výsledek před doplněním hraničních scénářů |
| Finální SQLite/UCI/HTTP průchod | PASS 19 testů / 207 assertions, 1 očekávaný skip MariaDB souběhu; hranice poklesu 20 % / 25 %, revokace registráře i původ řádku |
| MariaDB lokální ingest, transakce, revokace | NOT RUN: localhost:3306 odmítl připojení, služba nebyla zapínaná; ověření přesunuto do CI |
| Neprázdná obnova osmi tabulek skutečnou Evidence zálohou | PASS samostatný krok v obou MariaDB variantách CI 42392402107; porovnání obsahu všech osmi tabulek včetně revizí, lease a token hash |
| HTTP anonymní přístup a výchozí OFF | PASS 10 assertions; skutečný lokální HTTP, chybějící/false flag, cookie, Bearer a podvržený forwarded HTTPS |
| AnonymousSweep | PASS 36 public + 2 CLI + 261 chráněných cest, errors 0 |
| Composer validate / PHPStan / style | PASS, finální Linux [quality 42392402121](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42392402121) |
| Celá PHPUnit | PASS job v CI 42392402107: 2381 testů / 46650 assertions, 1 očekávaný skip MariaDB souběhu |
| Dokumentace/odkazy a git diff --check | PASS průběžně: CURRENT 80 řádků, 4640 odkazů, 108 archivních bloků, errors 0 |
| MariaDB 10.3 / 11.4 S1 ingest | PASS celé joby, každý 14 testů / 166 assertions; [CI 42392402107](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42392402107), včetně skutečného čekání na zámek a odmítnutí odvolaného zařízení |
| Ostatní regresní CI kroky | PASS obě read-profile varianty i všechny ostatní joby; žádný snížený limit nebo vypnutá kontrola |
| Merge / deploy / zapnutí / skutečné portály / stanice | NOT RUN, mimo povolený rozsah |

Akceptace zahrnuje všechny čtyři kolekce, striktní pole, vedoucí nuly,
více licencí, chybějící a duplicitní identity, rozpory jmen/dat, změnu UCI
po publikaci, retry/hash/stránkování/lease, odvolané či cizí zařízení,
baseline/pokles/nulu a atomický rollback při simulované chybě DB.
Žádná shoda přes jméno, žádná změna kanonických sportovců ani plateb.

První CI odhalilo neaktualizovaná očekávání ownership verze a katalogu migrací
a nadbytečnou podmínku validátoru; opraveno bez potlačení testů/PHPStan.
MariaDB 10.3 vyžadovala delší interval pozorování diagnostických tabulek
InnoDB (cache); test stále musí prokázat skutečný LOCK WAIT před revokací.
Nová migrace také musí být aplikovaná na obě strany historických profilů
R05/R06/R13: workflow ji kopíruje do tří testovacích worktrees stejně jako
dosavadní dokumentovou migraci. Zmrazený kód a porovnání počtu tabulek,
migračního ledgeru, doménových výsledků a HTML zůstávají beze změny.
Místní kompletní PHPUnit/PHPStan narazily na 300s Composer limit, nejsou
vydávané za PASS. Autoritativní kompletní důkaz je Linux CI; lokálně prošly
doménové a aktualizované migrační/backup kontrakty (36 testů / 810 assertions
před doplněním dvou kontrol souřadnic řádku).

Příkazy: `php vendor/bin/phpunit tests/Unit/ExternalIdentityMatchTest.php tests/Integration/ExternalReadSyncTest.php`;
pro MariaDB pouze `EVIDENCE_EXTERNAL_SYNC_MARIADB=1` a test Integration.
Databáze se generuje jako `evidence_external_sync_test_<random>`, vytvoří a
uklidí samotným testem; aplikace/config.php se nepřebírá. Stejný test je
zařazen do obou existujících MariaDB CI variant. SQLite nepředstírá důkaz
souběžných MariaDB zámků; tento scénář spouští jen MariaDB.

## Předání a zbývající rozsah

S1 implementace a syntetická přejímka HOTOVO. Zálohy ověřené na neprázdných
osmi tabulkách včetně historie a revizí; žádný zápis mimo integrační oblast
v příjmovém kódu. Celý projektový test má jeden očekávaný SQLite skip
souběhu; stejný scénář skutečně běžel a prošel v obou MariaDB variantách.

PR #376 zůstává **draft**, závislost S0 #371 otevřená. Není sloučený do main,
není nasazený a `KIS_EXTERNAL_SYNC_ENABLED` nebyl v žádném provozu zapnutý.
Žádná produkční migrace, device token, plánovač, portálový zápis ani přístup
k Rikimu. Další pracoviště převezme S2/S3 proti [protokolu](../../CSC_SPORTSOFT_S1_KIS.md);
před pilotem zbývá cílová inventura, scope, worker, UI, provisioning a retence.
