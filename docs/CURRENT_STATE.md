# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `a9906d6552e56f20471016a23ff98cc6535ed254`,
  [PR #244](https://github.com/KovoPraha/evidenceTreninku/pull/244),
  [deploy 41070277597](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41070277597), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w91-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [Current documentation 41068909919](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909919), [PHP quality 41068909820](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909820), [Testy 41068909842](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909842), [Push on main 41068909367](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41068909367).
  1844 testů / 36778 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W9.1 dávkuje zůstatky skladu, 10000 variant 20008 → 34 SQL;
  stejné HTML, data a role. Filtrované čtení omezuje PHP paměť.
  [W9](REFACTORING_W9_2026-10-30.md); W8.5 přidává sportovní souběhy do CI.
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w91-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W9.2 a W10–W15 podle
  [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
