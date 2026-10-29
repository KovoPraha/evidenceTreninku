# Aktuální stav projektu

Aktualizováno 29. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `3a168a68050be1d44648086bdb190df7c556617e`,
  [PR #223](https://github.com/KovoPraha/evidenceTreninku/pull/223),
  [deploy 40914561899](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914561899), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-w63-release-20261029/RESULTS.md):
  před/po 22/22 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [Current documentation 40914037135](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037135), [Push on main 40914036628](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914036628), [PHP quality 40914037212](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037212), [Testy 40914037413](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037413).
  1799 testů / 35956 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W6.3 zpracovává rezervované položky postupně; přírůstek špičky
  paměti při 10 000 položkách klesl o 66,6 % na migrovaném syntetickém schématu.
  SQL, stavy a zámky shodné; bez migrace nebo nového indexu. [Plán](REFACTORING_W63_2026-10-29.md).
  K0/W6.1 přidal mapu 16 cest, charakterizaci a měřicí nástroje.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 29. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-w63-release-20261029/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** K0.2/W7 — profil osob, rodin a oprávnění;
  další agendy průběžně podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
