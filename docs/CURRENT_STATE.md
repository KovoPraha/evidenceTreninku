# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `7828758cfe832a99c11ddeafe916b8480e501feb`,
  [PR #247](https://github.com/KovoPraha/evidenceTreninku/pull/247),
  [deploy 41077903487](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41077903487), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w102-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [Current documentation 41076568762](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41076568762), [PHP quality 41076571091](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41076571091), [Testy 41076568814](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41076568814), [Push on main 41076568914](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41076568914).
  1848 testů / 37639 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** průběžný XLSX zápis zvládá 100 000 pohybů pod 256 MiB;
  shodné hodnoty, typy, formátování, textové VS a účetní snapshot.
  [W10.2](REFACTORING_W102_2026-10-30.md); v CI celý export 23 s / 190 MB.
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w102-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W10.3 / W10.4 a W11–W15 podle
  [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
