# Aktuální stav projektu

Aktualizováno 29. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `66521929f7e9ce55f7a03d669cb7821f49382f6c`,
  [PR #227](https://github.com/KovoPraha/evidenceTreninku/pull/227),
  [deploy 40939479761](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939479761), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-w71b-release-20261029/RESULTS.md):
  před/po 26/26 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [Current documentation 40939142743](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939142743), [Push on main 40939144235](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939144235), [PHP quality 40939142856](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939142856), [Testy 40939142879](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939142879).
  1808 testů / 36095 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W7.1b zastaví RSVP, rezervaci i storno náhrady po revokaci
  oprávnění během čekání na zámek. Aktuální zamykající kontrola před zápisem;
  32/32 souběhových scénářů na MariaDB 10.3/11.4 PASS.
  [Plán a hranice](REFACTORING_W71B_2026-10-29.md). Schéma a čtecí helpery beze změny.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 29. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-w71b-release-20261029/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** W7.2 — úplný profil R02 (rodinný přehled), následně R03–R04
  podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
