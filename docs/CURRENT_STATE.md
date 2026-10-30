# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí přehled nahradit.

- **Poslední doložené nasazení:** `a3fae123cf6dcb2a4ce5ffe37ddd892874e436fe`,
  [PR #233](https://github.com/KovoPraha/evidenceTreninku/pull/233),
  [deploy 41047950067](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047950067), SUCCESS.
  Živé SHA/run ID ověřeny novou read-only kontrolou.
  [Protokol](verification/refactoring-w74-release-20261030/RESULTS.md): HTTP před/po 26/26,
  schéma current / pending [], VS zachovány, záloha 336 tabulek / 2 triggery.
- **CI přesného main:** [Current documentation 41047505501](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505501), [PHP quality 41047505574](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505574), [Push on main 41047505219](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505219), [Testy 41047505559](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505559).
  1824 testů / 36200 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.4 načítá členská pole v dávkách uvnitř jednoho čerstvě
  autorizovaného volání. Celý GET 20 osob 752 → 619 SQL, členství 160 → 27.
  Data, HTML, ostatní SQL i řádky zachovány. Bez cache osob a změny writerů.
  [Plán a měření](REFACTORING_W74_2026-10-30.md); nejde o měření produkční latence.
- **Provoz:** Evidence/KIS, katalog, rodinné/sportovní účty a klubové agendy,
  finanční/účetní/skladová implementace a bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta nedefinovaná, KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Porovnání](verification/refactoring-w74-release-20261030/release-comparison.json).
- **Otevřené provozní body:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, ověřené vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  UAT false; importy, brány ani další integrace nebyly aktivovány.
- **Další práce:** autonomně W7.5 — stránkování registráře a R03/R04,
  potom W8–W15 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Vlastník autorizoval pokračování včetně sloučení/nasazení po úspěšném ověření.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
