# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `46ffede1c368d33c25c524f967be02f2040cb1a9`,
  [PR #241](https://github.com/KovoPraha/evidenceTreninku/pull/241),
  [deploy 41063201796](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41063201796), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w84-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [PHP quality 41062190133](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062190133), [Push on main 41062189830](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062189830), [Current documentation 41062190230](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062190230), [Testy 41062190138](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41062190138).
  1842 testů / 36744 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W8.4 dávkuje přílohy povolených klubových akcí a vozidla;
  roční seznam správce 2913 → 9 SQL, veřejnost 521 → 6, rodina 2289 → 734.
  Shodná data, nižší paměť; autorizace a zápisové kontroly zachované.
  [W8.4](REFACTORING_W84_2026-10-30.md). Předchozí
  [W8.2/W8.3](verification/refactoring-w82-release-20261030/RESULTS.md) také nasazené.
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w84-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W8.5 sportovní kontrakty a W9–W15 podle
  [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
