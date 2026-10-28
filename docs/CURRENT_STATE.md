# Aktuální stav projektu

Aktualizováno 28. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `09510ac6e44cfd01f571b91f5e52bc2d9d6eb9df`,
  [PR #213](https://github.com/KovoPraha/evidenceTreninku/pull/213),
  [deploy 40761965370](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761965370),
  28. 10. 2026, SUCCESS; živé SHA a run ID ověřeny následnou čtecí kontrolou.
  [Protokol vydání a důkazy](verification/refactoring-wave3-release-20261028/RESULTS.md):
  před i po nasazení 22/22 anonymních HTTP kontrol PASS, schéma current / pending [], VS zachovány.
  Záloha 336 tabulek / 2 triggery vytvořena a ověřena; obnova v tomto vydání neopakována.
- **CI nasazeného main:**
  [Testy 40761603743](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603743),
  [PHP quality 40761603769](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603769),
  [CodeQL 40761603485](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603485)
  a [dokumentace 40761603643](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603643), PASS.
  1 769 testů / 35 789 assertions; MariaDB 10.3/11.4, PHPStan a sweep PASS.
- **Změna:** třetí vlna používá již načtené identity, kategorie a nabídky pro
  karty programů v katalogu. Pro 50 programů SQL 1 366 → 416, metadata 711 → 11.
  Bez nové migrace, změny členských cen či checkoutu. [Plán a důkazy](REFACTORING_WAVE3_2026-10-28.md).
  Produkční medián katalogu 2,92 → 0,32 s; tři vzorky před/po,
  orientační měření HTTP včetně sítě, nikoli zátěžový test.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 28. 10. / bez nové aktivace:** Fio import a automatické potvrzování,
  SumUp, Stripe a Velocota vypnuté; Packeta flag není definovaný. KIS canonical writes false.
  [Živá verze a čtecí kontrola](verification/refactoring-wave3-release-20261028/release-comparison.json).
  Konfigurace, flags a ledger 141 záznamů shodné v čerstvé kontrole před/po vydání.
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání má souhlas vlastníka; UAT flag zůstává false.
  Historické přejímky se na toto vydání automaticky nepřenášejí.
- **Další krok:** dokončit lidské/účetní převzetí a doplnit ověřená provozní data;
  další výkonový kandidát je zbývající kanonické čtení kapacity a podmínek programů.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
