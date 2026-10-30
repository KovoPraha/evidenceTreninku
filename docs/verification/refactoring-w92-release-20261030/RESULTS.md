# W9.2 / W10.1 — ověřené společné vydání

30. 10. 2026: [PR #245](https://github.com/KovoPraha/evidenceTreninku/pull/245)
a [PR #246](https://github.com/KovoPraha/evidenceTreninku/pull/246), release
`360355fd30238c11fc7bdd608b7c30f5a0140c0c`,
[deploy 41074968191](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41074968191) SUCCESS.

Programový košík 50 nabídek: členský 1862 → 275 SQL, nečlenský 1762 → 175;
podmínky 400 → 2. Kapacitní a write čtení zůstávají čerstvá. Skladové race
jsou v CI obou MariaDB. W10.1 indexuje finanční projekce při zachování
snapshotu a XLSX. Syntetický profil 1000 řádků: 150 → 0,83 ms.
Podrobnosti a omezení v [W9.2](../refactoring-w92-20261030/RESULTS.md)
a [W10.1](../refactoring-w10-20261030/RESULTS.md).

- [CI přesného main](github-ci-main.json): Testy 41073944651,
  PHP quality 41073944540, Current documentation 41073944566 a CodeQL
  Push on main 41073944352 SUCCESS. Linux 1847 testů / 37281 assertions,
  obě MariaDB, PHPStan, styl, JS a anonymous sweep PASS.
- Původní dynamický PR CodeQL Actions 41071442134 skončil při uploadu
  bez uvedeného nálezu; GitHub odmítl jeho opakování. Před deployem byly
  úspěšné všechny tři CodeQL analýzy přesného sloučeného main.
- [Před](pre-verification.json) 41073606465 a [po](post-verification.json)
  41075843201: read-only bez doménových mutací; živé SHA/run ID souhlasí.
  Konfigurace, flags, ledger 141, canonical writes false a stav příloh shodné;
  [strojové porovnání](release-comparison.json).
- [Záloha a deploy](deployment.json): 336 tabulek / 2 triggery, ověřený SHA-256,
  migrace current / pending [], VS zachovány, dočasná konfigurace odstraněna.
- Přímý runner HTTP 200; nezávislé GET [před](pre-http.json) i [po](post-http.json)
  26/26 nad stejnými produkty 247, 218 a 221.
- UAT false, importy false, integrace nebyly aktivovány. Jde o technický release.

W10.2 má samostatný PR #247 s opravou paměti XLSX; toto vydání jej neobsahuje.
Pokračuje W10.3 a W11–W15. Lidská účetní přejímka zůstává samostatná.
