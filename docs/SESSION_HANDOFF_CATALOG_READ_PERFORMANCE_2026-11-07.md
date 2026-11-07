# Předání P11 katalog — 7. 11. 2026

HOTOVO pro implementaci, striktní paritu a měření. [PR #364 k review](https://github.com/KovoPraha/evidenceTreninku/pull/364), bez merge/deploye.
[Plán a akceptace](CATALOG_READ_PERFORMANCE_2026-11-07.md),
[výsledky, surové reporty a otisky](verification/catalog-read-performance-20261107/RESULTS.md).

#358 sloučený `37d0ad68`; 11 kontrol PASS, žádný náš deploy.
Větev `codex/catalog-read-performance-20261107`, vlastní worktree viz plán.
Katalog: lehká projekce celku, plné karty pro stránku; do 64 aktivních produktů
původní cesta po sondě max. 65 ID. Transakce původní. Checkout beze změny.
Cíleně 31/2080, úplný Linux 2350/45220, PHPStan/styl/dokumentace PASS.
Runtime `623dcaa5` v `0252ba58`: 480 párů před/po na obou MariaDB,
shodné celé výsledky a všech 9 otisků zdrojů. 5000 zboží 202/340 → 87/199 ms;
PHP špička 71,6 → 16,8 MiB. U malých katalogů zůstává malá režie sondy;
u programů dominuje kapacita. Více malých SQL, méně těžkých řádků.

Převzaté #363 HTTP/browser i #362 provozní předání; tato práce je neopakuje.
Poslední dokumentační head a úplné CI sledovat u PR. Reporty dokazují runtime,
nikoli produkční latenci. Nový katalog HTTP/browser/produkční UAT NOT RUN.
Další krok: review #364, pozdější integrace podle rozhodnutí vlastníka.
Původní WIP i jeho SHA256 zachované; původní checkout se nepřepínal.
Bez nového zadání nenasazovat, neaktivovat ani neuklízet cizí rozpracovanou práci.
