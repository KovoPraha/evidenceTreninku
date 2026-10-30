# W10.2 — ověřené nasazení průběžného XLSX

30. 10. 2026, [PR #247](https://github.com/KovoPraha/evidenceTreninku/pull/247),
release `7828758cfe832a99c11ddeafe916b8480e501feb`,
[deploy 41077903487](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41077903487) SUCCESS.

Původní XLSX překročil 256 MiB již při 10 000 pohybech. Průběžný zápis
100 000 pohybů v Linux CI dokončil za 22 918 ms s celkovým peakem
189 608 296 B, navíc proti snapshotu 33 329 368 B. XLSX 15 106 137 B.
[CI artefakt](../refactoring-w102-20261030/ci-serialization.json),
[metoda a omezení](../refactoring-w102-20261030/RESULTS.md).
Jde o jednotlivé syntetické měření celé serializace XLSX, nikoli SLA
nebo důkaz libovolně velkého PDF / celého produkčního archivu.

- [CI přesného main](github-ci-main.json): Testy 41076568814, PHP quality
  41076571091, Current documentation 41076568762 a CodeQL 41076568914 SUCCESS.
  Linux 1848 testů / 37639 assertions, obě MariaDB, POSIX bez skipů,
  PHPStan, styl, JS a anonymous sweep PASS.
- [Před](pre-verification.json) 41077625152 a [po](post-verification.json)
  41078490265: read-only bez doménových mutací. Metadata živého vydání souhlasí.
  Konfigurace, flags, ledger 141, canonical writes false a stav příloh shodné;
  [porovnání](release-comparison.json).
- [Deploy](deployment.json): ověřená záloha 336 tabulek / 2 triggery,
  SHA-256 uložený v protokolu, migrace current / pending [], VS zachovány
  a dočasná konfigurace odstraněna.
- Přímý runner HTTP 200; nezávislé GET [před](pre-http.json) / [po](post-http.json)
  26/26 nad stejnými produkty. UAT a importy false; žádná aktivace integrací.

Pokračuje W10.3 / W10.4 a W11–W15. Lidské účetní převzetí zůstává oddělené.
