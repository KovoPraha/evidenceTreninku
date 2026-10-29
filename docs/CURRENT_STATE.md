# Aktuální stav projektu

Aktualizováno 29. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `81544542bb40cb96e7141791ea22e4b382531d5e`,
  [PR #225](https://github.com/KovoPraha/evidenceTreninku/pull/225),
  [deploy 40930536923](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930536923), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-w71-release-20261029/RESULTS.md):
  před/po 26/26 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [PHP quality 40930090645](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930090645), [Current documentation 40930090635](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930090635), [Testy 40930090693](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930090693), [Push on main 40930089391](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930089391).
  1804 testů / 36058 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.1a ověřuje rodinný přístup cíleně: u 20 osob 24 → 4 SQL,
  nejvýše jeden datový řádek. Stejná autorizační pravidla, bez cache oprávnění
  a změny transakcí nebo schématu. [Plán](REFACTORING_W71_2026-10-29.md).
  K0/W6.1 přidal mapu 16 cest, charakterizaci a měřicí nástroje.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 29. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-w71-release-20261029/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** W7.1b — race test revokace u RSVP/náhrad a případná oprava;
  dále úplné profily R02–R04 podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
