# W9.1 — ověřené vydání skladového přehledu

30. 10. 2026, [PR #244](https://github.com/KovoPraha/evidenceTreninku/pull/244),
release `a9906d6552e56f20471016a23ff98cc6535ed254`, [deploy 41070277597](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41070277597) SUCCESS.

Výpis 10000 variant: 20008 → 34 SQL, finanční role 20009 → 35.
Celé HTML a data shodné. CI 10.3 katalog 3420,68 → 97,32 ms,
11.4 6125,83 → 111,60 ms; jde o syntetický profil, nikoli produkční SLA.
Úzký filtr omezuje paměť, nemusí být vždy rychlejší.
[Měření](../refactoring-w91-20261030/RESULTS.md),
[CI 10.3](../refactoring-w91-20261030/ci-mariadb-10.3.json),
[CI 11.4](../refactoring-w91-20261030/ci-mariadb-11.4.json).

Přesný main: [Current documentation 41068909919](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909919), [PHP quality 41068909820](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909820), [Testy 41068909842](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909842), [Push on main 41068909367](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909367). Deploy Linux **1844 testů /
36778 assertions**, bez skipů. PHPStan, styl, JS,
anonymous sweep a obě MariaDB prošly.

- [Před](pre-verification.json) 41067877177,
  [po](post-verification.json) 41070978218: nové čtecí kontroly,
  živé SHA/run ID shodné s nasazením, žádné doménové mutace.
- [Porovnání](release-comparison.json): konfigurace, flags, ledger 141,
  canonical writes, VS repair a stav příloh beze změny.
- [Deploy](deployment.json): záloha `evidence_2026-10-30_141754_79ebac2e.sql.gz`,
  SHA256 `516b6bccf47afee740a71c397902e77a79456c45b64cb1e76a3e64fe6c6984d9`, 336 tabulek / 2 triggery,
  6063580 B. Migrace current / pending [], VS zachované,
  dočasná kopie konfigurace odstraněna.
- Přímý runner HTTP 200; nezávislé [GET před](pre-http.json) a
  [po](post-http.json) 26/26 nad stejnými produkty.
- UAT false, importy false, žádné aktivace integrací ani skutečné obchodní zápisy.

Součástí vydání je i již sloučená W8.5: testovací/CI sportovní kontrakty,
bez samostatné změny produkčního chování. Pokračuje W9.2 a W10–W15;
lidská účetní přejímka zůstává oddělená.
