# Aktuální stav projektu

Aktualizováno 28. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `a14d1026800822a09ee713a9e6924651be052913`,
  [PR #217](https://github.com/KovoPraha/evidenceTreninku/pull/217),
  [deploy 40777286733](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40777286733),
  28. 10. 2026, SUCCESS; živé SHA a run ID ověřeny následnou čtecí kontrolou.
  [Protokol vydání a důkazy](verification/refactoring-wave5-release-20261028/RESULTS.md):
  před i po nasazení 22/22 anonymních HTTP kontrol PASS, schéma current / pending [], VS zachovány.
  Záloha 336 tabulek / 2 triggery vytvořena a ověřena; obnova v tomto vydání neopakována.
- **CI nasazeného main:**
  [Testy 40776670809](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776670809),
  [PHP quality 40776670614](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776670614),
  [CodeQL 40776671215](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776671215)
  a [dokumentace 40776670646](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776670646), PASS.
  1 782 testů / 35 866 assertions; MariaDB 10.3/11.4, PHPStan a sweep PASS.
- **Změna:** pátá vlna dávkově načítá nabídky a vazby fyzických týmů.
  Pro 50 programů SQL 218 → 120, nabídky 50 → 1, vazby týmů 50 → 1; výstupy shodné.
  Bez nové migrace, změny kapacity, členských cen či checkoutu. [Plán a důkazy](REFACTORING_WAVE5_2026-10-28.md).
  Produkční medián katalogu 0,683 → 0,270 s; tři vzorky před/po,
  orientační měření HTTP včetně sítě, nikoli zátěžový test.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 28. 10. / bez nové aktivace:** Fio import a automatické potvrzování,
  SumUp, Stripe a Velocota vypnuté; Packeta flag není definovaný. KIS canonical writes false.
  [Živá verze a čtecí kontrola](verification/refactoring-wave5-release-20261028/release-comparison.json).
  Konfigurace, flags a ledger 141 záznamů shodné v čerstvé kontrole před/po vydání.
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání má souhlas vlastníka; UAT flag zůstává false.
  Historické přejímky se na toto vydání automaticky nepřenášejí.
- **Další krok:** dokončit lidské/účetní převzetí a doplnit ověřená provozní data;
  další výkonový kandidát je zbývající kanonické čtení obsazenosti a rezervací.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
