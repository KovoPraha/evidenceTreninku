# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `df09eab94ccc6545e57b71a41bc24aa0011850c0`,
  [PR #237](https://github.com/KovoPraha/evidenceTreninku/pull/237),
  [deploy 41055183388](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41055183388), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w81-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [Current documentation 41054548544](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548544), [PHP quality 41054548565](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548565), [Testy 41054548557](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548557), [Push on main 41054548082](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548082).
  1831 testů / 36291 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.6 / W8.1 načítá historické členství a hierarchii časové osy
  jednou za volání. Roční profil 4176 → 16 SQL, členské dotazy 2082 → 2.
  Přesně shodná data, nižší paměť; bez změny writerů a autorizace.
  [Plán a měření](REFACTORING_W76_W81_2026-10-30.md); nejde o měření produkční latence.
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w81-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W8.2 — historické filtry a kopírovací náhled,
  potom W8–W15 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
