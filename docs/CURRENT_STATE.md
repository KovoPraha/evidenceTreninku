# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `c6a260acef86218bb3e429c912d12746f712b75f`,
  [PR #235](https://github.com/KovoPraha/evidenceTreninku/pull/235),
  [deploy 41051708252](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051708252), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w75-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [Current documentation 41051145037](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145037), [Push on main 41051145192](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145192), [PHP quality 41051145270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145270), [Testy 41051145060](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145060).
  1825 testů / 36217 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.5 načítá stránku registráře v SQL a zachovává celé statistiky.
  Pro 10 000 osob stránka 50: 10 003 → 53 řádků; CI stránka 100 paměť
  36,8 MB → 0,43 MB, shodné výsledky 50 scénářů. Bez změny writerů.
  [Plán a měření](REFACTORING_W74_2026-10-30.md); nejde o měření produkční latence.
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w75-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W7.6 / W8.1 — zbývající R03/R04 a časová osa,
  potom W8–W15 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
