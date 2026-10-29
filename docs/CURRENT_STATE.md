# Aktuální stav projektu

Aktualizováno 29. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `330f8bde7a02984e21dc59309a7d8e6fb85bac62`,
  [PR #221](https://github.com/KovoPraha/evidenceTreninku/pull/221),
  [deploy 40877039442](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40877039442), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-wave6-release-20261029/RESULTS.md):
  před/po 22/22 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [PHP quality 40876631658](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631658), [Push on main 40876631075](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631075), [Testy 40876631205](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631205), [Current documentation 40876631269](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631269).
  1798 testů / 35952 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W6.2 odděluje čistý výpočet kapacity od databázového čtení.
  Shodné výsledky, SQL, parametry a řádky; zámky a checkout zachovány.
  Bez migrace, indexu nebo deklarovaného zrychlení. [Plán](REFACTORING_WAVE6_2026-10-29.md).
  K0/W6.1 přidal mapu 16 cest, charakterizaci a měřicí nástroje.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 29. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-wave6-release-20261029/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** W6.3 profil migrovaného schématu před dávkovou optimalizací;
  K0.2 dalších agend průběžně podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
