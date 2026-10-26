# Aktuální stav projektu

Aktualizováno 26. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `fc0d3779306e70362e013f088f30974725bbf8ff`,
  [deploy 40330758548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330758548),
  26. 10. 2026, SUCCESS; živé SHA a run ID ověřeny následnou čtecí kontrolou.
  [Protokol vydání a důkazy](verification/dozor-release-20261026/RESULTS.md):
  24 HTTP, 16 databázových, 14 účetních a 20 skladových kontrol PASS;
  záloha obnovena pouze v izolaci, 336 tabulek / 2 triggery.
- **CI nasazeného main:**
  [Testy 40330507391](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330507391),
  [PHP quality 40330507279](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330507279)
  a [CodeQL 40330508180](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330508180), PASS.
  1 748 testů / 35 593 assertions; MariaDB 10.3/11.4, PHPStan a sweep PASS.
- **Historická produkční přejímka:** `a23e393f64eeffafd75df834e6e5ba8c78213b72`,
  [deploy 40198413027](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198413027),
  25. 10. 2026. [Důkazy a jejich hranice](verification/release-acceptance-20261025/RESULTS.md)
  se automaticky nepřenášejí na další vydání.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
  Technické ověření samo nepotvrzuje připravenost obsluhy ani dodání provozních dat.
- **Flags ověřené 26. 10. / bez nové aktivace:** Fio import a automatické potvrzování,
  SumUp, Stripe a Velocota vypnuté; Packeta flag není definovaný. Další transporty,
  periodické rozesílky a skutečný cutover vyžadují vlastní schválení.
  [Konfigurace, flags a ledger beze změny](verification/dozor-release-20261026/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md),
  [pilot](CLUB_PILOT_D12_D14.md), [aktuální hranice ověření](verification/dozor-release-20261026/RESULTS.md#hranice-ověření).
  Technické vydání má výslovný souhlas vlastníka; UAT flag zůstává false.
- **Další krok:** dokončit lidské/účetní převzetí a doplnit ověřená provozní data.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.
- **PR dozoru dokončeny:** #196 → #199 → #197 → #198 → #200 jsou sloučené a nasazené;
  [opravy F1–F3 a negativní zkoušky](DOZOR_F1_F3_2026-10-26.md).
  Protokol dokládá vlastní technické ověření, nikoli novou nezávislou přejímku dozoru.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
