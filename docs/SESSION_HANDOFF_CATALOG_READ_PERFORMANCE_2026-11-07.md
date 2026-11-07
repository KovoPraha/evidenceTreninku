# Předání P11 katalog — 7. 11. 2026

[Plán a akceptace](CATALOG_READ_PERFORMANCE_2026-11-07.md),
[výsledky](verification/catalog-read-performance-20261107/RESULTS.md),
[PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364), aktuálně draft.

#358 sloučený `37d0ad68`; 11 kontrol PASS, žádný náš deploy. Vlastní větev
`codex/catalog-read-performance-20261107`, worktree a původní WIP jsou v plánu.
Katalog implementovaný: lehká projekce celku, plné karty pro stránku;
do 64 aktivních produktů původní cesta podle sondy max. 65 ID. Transakce původní.
Cíleně 31 testů / 2080 assertions PASS. Finální runtime `623dcaa5`, integrovaný
s #363 v `0252ba58`; nativní párové měření a úplná CI právě běží.
První nativní reporty před malým fallbackem nejsou finálními čísly.

Další krok: převzít finální artefakty 10.3/11.4, ověřit zdrojové otisky,
doplnit výsledky, všechny přehledy a PR; po zelené CI označit k review.
Samostatné HTTP/browser #363 na `feb8fe8c` zachované; nový katalog nepřeměřují.
Katalogový merge, deploy/aktivace a produkční UAT NOT RUN. Bez nového oprávnění
nenasazovat. Rozpracované změny ani místní výstupy původního checkoutu neuklízet.
