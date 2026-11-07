# P15 S1 — syntetické ověření KIS

7. 11. 2026. [Technické předání](../../CSC_SPORTSOFT_S1_KIS.md),
[plán](../../CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md).
Pouze lokální izolované SQLite/MariaDB databáze a syntetické S0 fixtures.
Nečte se aplikační config/DB, portály ani Riki. S1 v samostatné větvi
`codex/csc-sportsoft-s1-ingest-20261107`, závislost S0 #371.

| Kontrola | Stav / důkaz |
| --- | --- |
| První cílený průchod SQLite + UCI | PASS 12 testů / 131 assertions; průběžný výsledek před doplněním hraničních scénářů |
| Rozšířený SQLite/UCI průchod | RUNNING |
| MariaDB lokální ingest, transakce, revokace | RUNNING |
| Neprázdná obnova osmi tabulek skutečnou Evidence zálohou | NOT RUN |
| HTTP anonymní přístup a výchozí OFF | NOT RUN |
| Celá PHPUnit, PHPStan, style, syntax, composer validate | RUNNING |
| Dokumentace/odkazy a git diff --check | NOT RUN |
| Linux CI a MariaDB 10.3 / 11.4 | NOT RUN |
| Merge / deploy / zapnutí / skutečné portály / stanice | NOT RUN, mimo povolený rozsah |

Akceptace zahrnuje všechny čtyři kolekce, striktní pole, vedoucí nuly,
více licencí, chybějící a duplicitní identity, rozpory jmen/dat, změnu UCI
po publikaci, retry/hash/stránkování/lease, odvolané či cizí zařízení,
baseline/pokles/nulu a atomický rollback při simulované chybě DB.
Žádná shoda přes jméno, žádná změna kanonických sportovců ani plateb.

Příkazy: `php vendor/bin/phpunit tests/Unit/ExternalIdentityMatchTest.php tests/Integration/ExternalReadSyncTest.php`;
pro MariaDB pouze `EVIDENCE_EXTERNAL_SYNC_MARIADB=1` a test Integration.
Databáze se generuje jako `evidence_external_sync_test_<random>`, vytvoří a
uklidí samotným testem; aplikace/config.php se nepřebírá. Stejný test je
zařazen do obou existujících MariaDB CI variant. SQLite nepředstírá důkaz
souběžných MariaDB zámků; tento scénář spouští jen MariaDB.
