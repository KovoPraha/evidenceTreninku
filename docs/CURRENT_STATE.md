# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `4628845c9e0a7b61e2f2e24b4a6362c27fa70e55`,
  [PR #231](https://github.com/KovoPraha/evidenceTreninku/pull/231),
  [deploy 41029345885](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41029345885), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-w73-release-20261030/RESULTS.md):
  před/po 26/26 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [Current documentation 41028951282](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951282), [Push on main 41028951254](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951254), [PHP quality 41028951333](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951333), [Testy 41028951249](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951249).
  1821 testů / 36135 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.3 sdílí metadata jen během GET rodinného přehledu. U 20 osob
  celá stránka 2244 → 752 SQL, metadata 1590 → 98; shodná data/HTML.
  Čerstvá autorizace zachována, bez změny W7.1b writerů; POST bez cache.
  [Plán a měření](REFACTORING_W73_2026-10-30.md). Časové závěry mají meze profilu.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 30. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-w73-release-20261030/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** W7.4 — opakované čtení členských polí v R02,
  následně R03–R04 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
