# Aktuální stav projektu

Aktualizováno 28. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `331bd97474b676bf4e22f529508a9db434dc64c6`,
  [PR #211](https://github.com/KovoPraha/evidenceTreninku/pull/211),
  [deploy 40752209335](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40752209335),
  28. 10. 2026, SUCCESS; živé SHA a run ID ověřeny následnou čtecí kontrolou.
  [Protokol vydání a důkazy](verification/refactoring-wave2-release-20261028/RESULTS.md):
  před i po nasazení 22/22 anonymních HTTP kontrol PASS, schéma current / pending [], VS zachovány.
  Záloha 336 tabulek / 2 triggery vytvořena a ověřena; obnova v tomto vydání neopakována.
- **CI nasazeného main:**
  [Testy 40751905929](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751905929),
  [PHP quality 40751906121](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751906121),
  [CodeQL 40751905422](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751905422)
  a [dokumentace 40751906128](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751906128), PASS.
  1 764 testů / 35 753 assertions; MariaDB 10.3/11.4, PHPStan a sweep PASS.
- **Změna:** druhá vlna sdílí metadata, nabídky a prodejnost programů uvnitř
  jediné čtecí operace e-shopu; checkout ověřuje čerstvý stav pod zámkem.
  Žádná nová migrace ani změna členských cen. [Plán a backlog](REFACTORING_WAVE2_2026-10-28.md).
  Produkční medián katalogu 7,95 → 3,32 s, tří detailů 5,2–5,9 → 0,67–1,03 s;
  tři vzorky na cestu před/po, orientační měření HTTP včetně sítě, nikoli zátěžový test.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 28. 10. / bez nové aktivace:** Fio import a automatické potvrzování,
  SumUp, Stripe a Velocota vypnuté; Packeta flag není definovaný. KIS canonical writes false.
  [Živá verze a čtecí kontrola](verification/refactoring-wave2-release-20261028/release-comparison.json).
  Konfigurace, flags a ledger 141 záznamů shodné v čerstvé kontrole před/po vydání.
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání má souhlas vlastníka; UAT flag zůstává false.
  Historické přejímky se na toto vydání automaticky nepřenášejí.
- **Další krok:** dokončit lidské/účetní převzetí a doplnit ověřená provozní data;
  další výkonový kandidát je dávkové čtení prezentačních informací programů v katalogu.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
