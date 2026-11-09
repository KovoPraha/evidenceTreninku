# Předání P11-RHM — 9. 11. 2026

[Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md),
[výsledky](verification/athlete-profile-measurement-performance-20261109/RESULTS.md).
ROZPRACOVÁNO, baseline5ff9fa2a, produkce c3d34b87/deploy42625502461 SUCCESS.
Uživatel povolil podmíněný commit/merge/deploy po shodě a přínosu.
Větev codex/athlete-measurement-performance-20261109, WIP zachovaný.
Další krok: kandidát + nativní parita/HTTP/Chrome. Zatím žádný nový deploy.

## Lokální kandidát

Implementována MariaDB agregace s jedním JSON klíčem na binární typ a COUNT,
připojení reprezentantů až po agregaci. Typové filtry i stránka zůstávají
původní. Podmíněná verze>=10.3.3 a SESSIONlimit>=128; bez nastavování DB.
Původní window souhrn zachovaný pro SQLite/MySQL/krátký limit. Metadata dotaz
přidává1SQL na MariaDB, případný přínos se musí projevit v celémHTTP.

Lokálně SQLite10testů/1139assertions PASS (původníprofil/historie/header + nové
3testy/247), PSR12 všech6dotčenýchPHP, PythonAST a composer validate --strict PASS.
Jde o portable/fallback paritu včetně64bitklíčů; skutečný novýSQLfastpath a
limity128/4 musí prokázat nativní CI na MariaDB10.3/11.4. Zatím NOT RUN.
Žádná změna readeru mimo measurements()/nový helper, parent/endpoint/UI/
security/session/writery byte-nezměněné. Baseline3soubory zmrazené z5ff9fa2a.
Vlastní runner připraven pro1k/10k/30k,8scénářů/3profily,4warmups+17párů,
oddělenou SQL/PHP diagnostiku a52Chrome navigací obou režimů.
