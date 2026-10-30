# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `50c542b057944c740e9424ad12f56d28e8183e98`,
  [PR #238](https://github.com/KovoPraha/evidenceTreninku/pull/238) a
  [PR #240](https://github.com/KovoPraha/evidenceTreninku/pull/240),
  [deploy 41060582108](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41060582108), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w82-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [Current documentation 41059610473](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610473), [Push on main 41059610417](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610417), [PHP quality 41059610479](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610479), [Testy 41059610469](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610469).
  1839 testů / 36699 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W8.2 historické filtry 1875 → 5 SQL, kopírovací náhled 3 → 1;
  W8.3 rodinný kalendář 4199 → 39 SQL, členství jen autorizovaných osob.
  Přesně shodná data, nižší paměť; bez změny writerů a autorizace.
  [W8.2](REFACTORING_W82_2026-10-30.md), [W8.3](REFACTORING_W83_2026-10-30.md).
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w82-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W8.4 klubový kalendář, W8.5 sportovní kontrakty,
  potom W9–W15 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
