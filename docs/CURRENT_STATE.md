# Aktuální stav projektu

Aktualizováno 28. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `20ab863701bfb1b90e3a7f5a7c1357cceda3c30b`,
  [PR #209](https://github.com/KovoPraha/evidenceTreninku/pull/209),
  [deploy 40701923049](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701923049),
  28. 10. 2026, SUCCESS; živé SHA a run ID ověřeny následnou čtecí kontrolou.
  [Protokol vydání a důkazy](verification/refactoring-release-20261028/RESULTS.md):
  14/14 anonymních HTTP kontrol PASS, schéma current / pending [], dosavadní VS zachovány.
  Záloha 336 tabulek / 2 triggery vytvořena a ověřena; obnova v tomto vydání neopakována.
- **CI nasazeného main:**
  [Testy 40701687558](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701687558),
  [PHP quality 40701687766](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701687766),
  [CodeQL 40701687067](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701687067)
  a [dokumentace 40701688066](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701688066), PASS.
  1 757 testů / 35 705 assertions; MariaDB 10.3/11.4, PHPStan a sweep PASS.
- **Změna:** cílené čtení detailu a lehká navigace e-shopu, sdílená strukturální
  kontrola prodejnosti, PhpSpreadsheet 5.10.0. Žádná nová migrace ani doménový stav.
  [Plán, měření a navazující backlog](REFACTORING_WAVE1_2026-10-28.md).
  Produkční katalog/detail v jednom HTTP průchodu 6,8–15,6 s; výkon celé stránky
  vyžaduje další měření, syntetické SQL zlepšení není produkční benchmark.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 28. 10. / bez nové aktivace:** Fio import a automatické potvrzování,
  SumUp, Stripe a Velocota vypnuté; Packeta flag není definovaný. KIS canonical writes false.
  [Živá verze a čtecí kontrola](verification/refactoring-release-20261028/release-verification.json).
  Konfigurace a integrační flags shodné s doloženým stavem z 26. 10.; ledger 141 záznamů.
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání má výslovný souhlas vlastníka; UAT flag zůstává false.
  Historické přejímky se na toto vydání automaticky nepřenášejí.
- **Další krok:** dokončit lidské/účetní převzetí, doplnit ověřená provozní data
  a samostatně změřit zbývající náklady programových nabídek a členských cen.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
