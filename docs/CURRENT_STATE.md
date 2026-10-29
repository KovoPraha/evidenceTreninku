# Aktuální stav projektu

Aktualizováno 29. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `7000ec60007015d0ca0044190de7db68f6e25a46`,
  [PR #229](https://github.com/KovoPraha/evidenceTreninku/pull/229),
  [deploy 40951722439](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951722439), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-w72-release-20261029/RESULTS.md):
  před/po 26/26 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [Current documentation 40951317765](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951317765), [Push on main 40951316661](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951316661), [PHP quality 40951317779](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951317779), [Testy 40951317762](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951317762).
  1814 testů / 36118 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.2 cíleně načítá jednotlivé rodinné profily. U 20 osob
  celá stránka 2706 → 2326 SQL, 2953 → 2193 načtených řádků; shodná data/HTML.
  Čerstvá autorizace zachována, bez cache či změny W7.1b writerů.
  [Plán a měření](REFACTORING_W72_2026-10-29.md). Časové závěry mají meze profilu.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 29. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-w72-release-20261029/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** W7.3 — rozpad zbylých nákladů R02 na sekce/metadata,
  následně R03–R04 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
