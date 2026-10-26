# Aktuální stav projektu

Aktualizováno 26. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `d12e7c792ce796260f3fc5d07b25a395c310fcca`,
  [deploy 40317875183](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317875183),
  26. 10. 2026, SUCCESS podle GitHub Actions. Toto vlákno provedlo pouze čtení
  výsledku existujícího běhu, nikoli deploy nebo novou produkční přejímku.
- **Poslední CI main:** `d12e7c792ce796260f3fc5d07b25a395c310fcca`,
  [Testy 40317788236](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317788236)
  a [CodeQL 40317788561](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317788561), SUCCESS.
  1 743 testů / 35 152 assertions; MariaDB 10.3/11.4 PASS.
- **Historická produkční přejímka:** `a23e393f64eeffafd75df834e6e5ba8c78213b72`,
  [deploy 40198413027](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198413027),
  25. 10. 2026. [Důkazy a jejich hranice](verification/release-acceptance-20261025/RESULTS.md)
  se automaticky nepřenášejí na další vydání.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
  Technické ověření samo nepotvrzuje připravenost obsluhy ani dodání provozních dat.
- **Stav flags doložený přejímkou z 25. 10. / bez oprávnění nové aktivace:** Fio import a automatické potvrzování, SumUp,
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
- **Otevřené PR dozoru:** řetězec #196 → #199 → #197 → #198 → #200 nyní zahrnuje
  uvedený main; [opravy F1–F3 a jejich ověření](DOZOR_F1_F3_2026-10-26.md).
  Tyto PR nejsou sloučené ani nasazené. Předchozí přejímka starého headu není
  nezávislou kontrolou nových oprav.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
