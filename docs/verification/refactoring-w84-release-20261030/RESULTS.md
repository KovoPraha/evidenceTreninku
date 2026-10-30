# W8.4 — ověřené vydání klubového kalendáře

30. 10. 2026, [PR #241](https://github.com/KovoPraha/evidenceTreninku/pull/241),
release `46ffede1c368d33c25c524f967be02f2040cb1a9`, [deploy 41063201796](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41063201796) SUCCESS.
Autorizace vlastníka zahrnuje ověření, sloučení a nasazení dalších vln.

## Výsledek

Dávkové přílohy již povolených akcí a konflikty vozidel zachovávají celé
výstupy i oprávnění. Roční staff seznam 2913 → 9 SQL, veřejnost 521 → 6,
oprávněná rodina 2289 → 734. Zbývající rodinné kontroly autorizace zůstaly.
CI MariaDB 10.3 staff medián 884,82 → 10,85 ms, 11.4 1073,56 → 35,49 ms;
špička 5806808 → 4384616 B. Syntetický profil, nikoli produkční latence.
[Plán](../../REFACTORING_W84_2026-10-30.md), [měření](../refactoring-w84-20261030/RESULTS.md),
[CI 10.3](../refactoring-w84-20261030/ci-mariadb-10.3.json),
[CI 11.4](../refactoring-w84-20261030/ci-mariadb-11.4.json).

Přesný výsledný main: [PHP quality 41062190133](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062190133), [Push on main 41062189830](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062189830), [Current documentation 41062190230](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062190230), [Testy 41062190138](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062190138). Deploy Linux **1842 testů /
36744 assertions**, PHPStan, styl, anonymous sweep, JS
a obě MariaDB PASS; bez POSIX skipů. Testy zahrnují 401 akcí, více než 200
vozidel, NULL/duplicity, skutečnou SQL kolaci, skrytá pole a čerstvou revokaci.
Writer a jeho kontrola kolizí zůstaly zachované; formátování doloženo AST.

## Provozní důkazy

- [Před](pre-verification.json): čtecí kontrola 41062306586.
- [Po](post-verification.json): čtecí kontrola 41064078002,
  živé SHA/run ID odpovídají nasazení; bez doménových mutací.
- [Porovnání](release-comparison.json): konfigurace, flags, ledger
  141, canonical writes, VS repair a stav příloh shodné.
- [Deploy](deployment.json): záloha `evidence_2026-10-30_132753_2e1d64d5.sql.gz`, SHA256
  `8de71c327513505f7195d4d947f8698e5140f3c7880c2b2dafa61adb7572ba13`, 336 tabulek / 2 triggery,
  6063580 B. Migrace current / pending [], VS zachované,
  dočasná kopie konfigurace odstraněna.
- Přímý HTTP test z runneru 200; nezávislé [GET před](pre-http.json)
  a [po](post-http.json) 26/26 se stejnými produkty.
- `uat_schvaleno=false`, `importovat_krouzky=false`; vypnuté integrace
  beze změny, žádné skutečné registrace, převody či aktivace.

W8.4 je technicky dokončena a nasazena. Navazuje W8.5 (testovací/CI
sportovní kontrakty) a W9–W15. Browser a souvislá technická přejímka
zůstávají W13/W15; lidské/účetní UAT D12–D14 zůstává oddělené.
