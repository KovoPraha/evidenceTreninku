# Aktuální stav projektu

Ověřeno 25. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Produkce:** `a23e393f64eeffafd75df834e6e5ba8c78213b72`,
  [deploy 40198413027](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198413027),
  25. 10. 2026, SUCCESS. [Přejímka a hranice důkazů](verification/release-acceptance-20261025/RESULTS.md).
- **Poslední CI main:** `ba2c9eef96d6bcb4e5a7f1dd2686362c6b63bd0a`,
  [Testy 40217568068](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40217568068)
  a [CodeQL 40217568282](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40217568282), SUCCESS.
  1 718 testů / 34 750 assertions; MariaDB 10.3/11.4 PASS.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
  Technické ověření samo nepotvrzuje připravenost obsluhy ani dodání provozních dat.
- **Vypnuté / bez nové aktivace:** Fio import a automatické potvrzování, SumUp,
  Stripe, Velocota; Packeta integrační flag není definovaný. Další transporty,
  periodické rozesílky a skutečný cutover vyžadují vlastní schválení.
  [Zachování konfigurace a flags při vydání](verification/release-acceptance-20261025/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md),
  [pilot](CLUB_PILOT_D12_D14.md), [provozní zjištění](verification/release-acceptance-20261025/RESULTS.md).
  D15 platí pro uvedené vydání, UAT flag zůstává false; další vydání mají vlastní souhlas.
- **Další krok:** dokončit lidské/účetní převzetí a doplnit ověřená provozní data.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
