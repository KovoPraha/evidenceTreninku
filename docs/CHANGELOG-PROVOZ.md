# Provozní historie

Archiv původních stavových zápisů z `3b164e91`, řazený od nejnovějších.
Text uvnitř bloků je přesunut beze změny; shodné zápisy obou zdrojů zůstávají
kvůli úplnosti zachované. Hodnoty a oprávnění jsou historické, nikoli aktuální.
Aktuální stav je pouze v [CURRENT_STATE.md](CURRENT_STATE.md).

## 29. 10. 2026 — W7.1a cílený rodinný přístup nasazen

PR #225: cílené ověření rodinného přístupu, charakterizace a migrovaný profil.
Release `81544542bb40cb96e7141791ea22e4b382531d5e`, [deploy 40930536923](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930536923) SUCCESS.
1804 testů / 36058 assertions; před i po 26/26 HTTP PASS.
Konfigurace, flags a ledger shodné, VS zachovány, UAT false.
[Protokol](verification/refactoring-w71-release-20261029/RESULTS.md). Předchozí stav celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-29-w63", "part": 0, "sha256": "7e9b54bf28a5f8f6e691dbf73e06a372e1b8e59ddea09a1c1782a0b7b9395987"} -->
# Aktuální stav projektu

Aktualizováno 29. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `3a168a68050be1d44648086bdb190df7c556617e`,
  [PR #223](https://github.com/KovoPraha/evidenceTreninku/pull/223),
  [deploy 40914561899](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914561899), SUCCESS.
  Živé SHA/run ID ověřeny novou čtecí kontrolou.
  [Protokol a důkazy](verification/refactoring-w63-release-20261029/RESULTS.md):
  před/po 22/22 HTTP PASS, schéma current / pending [], VS zachovány.
  Ověřená záloha 336 tabulek / 2 triggery; obnova v tomto vydání neopakována.
- **CI přesného main:** [Current documentation 40914037135](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037135), [Push on main 40914036628](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914036628), [PHP quality 40914037212](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037212), [Testy 40914037413](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037413).
  1799 testů / 35956 assertions; MariaDB 10.3/11.4,
  POSIX bez skipů, PHPStan, styl, anonymous sweep a JS PASS.
- **Změna:** W6.3 zpracovává rezervované položky postupně; přírůstek špičky
  paměti při 10 000 položkách klesl o 66,6 % na migrovaném syntetickém schématu.
  SQL, stavy a zámky shodné; bez migrace nebo nového indexu. [Plán](REFACTORING_W63_2026-10-29.md).
  K0/W6.1 přidal mapu 16 cest, charakterizaci a měřicí nástroje.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 29. 10.:** Fio import/auto-confirm, SumUp, Stripe a Velocota
  vypnuté; Packeta flag nedefinovaný. KIS canonical writes false.
  Konfigurace, flags, ledger 141 a stav příloh před/po shodné.
  [Čtecí porovnání](verification/refactoring-w63-release-20261029/release-comparison.json).
- **Blokery ostrého převzetí:** lidská přejímka a pilot, účetní D12–D14,
  skutečná inventura/ocenění, ověřené rodinné vazby a trenérské rozsahy,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání autorizované; UAT false, importy/integrace neaktivovány.
- **Další krok:** K0.2/W7 — profil osob, rodin a oprávnění;
  další agendy průběžně podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
  Lidské/účetní převzetí a ověřená provozní data zůstávají samostatné.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)

<!-- /archive -->

## 29. 10. 2026 — W6.3 paměť kapacity sloučena a nasazena

PR #223: postupné zpracování rezervovaných řádků a profil migrovaného schématu.
Release `3a168a68050be1d44648086bdb190df7c556617e`, [deploy 40914561899](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914561899) SUCCESS.
1799 testů / 35956 assertions; před i po 22/22 HTTP PASS.
Konfigurace, flags a ledger shodné, VS zachovány, UAT false.
[Protokol](verification/refactoring-w63-release-20261029/RESULTS.md). Předchozí stav celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-29-wave6", "part": 0, "sha256": "2a75a20fdee220ebbb2bc9103e422a2954bbf4e31543f71f55078deb05a8c819"} -->
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

<!-- /archive -->

## 29. 10. 2026 — K0/W6.1 sloučeno a W6.2 nasazeno

PR #219 plán, #220 charakterizace a měření, #221 čistý výpočet kapacity.
Release `330f8bde7a02984e21dc59309a7d8e6fb85bac62`, [deploy 40877039442](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40877039442) SUCCESS.
1798 testů / 35952 assertions; před i po 22/22 HTTP PASS.
Konfigurace, flags a ledger shodné, VS zachovány, UAT false.
[Protokol](verification/refactoring-wave6-release-20261029/RESULTS.md). Předchozí stav celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-28-wave5", "part": 0, "sha256": "8f4b02c32f684249e5cccc1d00e4d33af4f7dbbbe72a6311629af2fc49e998b9"} -->
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

<!-- /archive -->

## 28. 10. 2026 — pátá vlna refaktoringu sloučena a nasazena

PR #217 → `a14d1026800822a09ee713a9e6924651be052913`, [deploy 40777286733](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40777286733) SUCCESS.
1 782 testů / 35 866 assertions, před i po 22/22 HTTP PASS. SQL pro 50 programů 218 → 120.
Konfigurace, vypnuté integrace a ledger shodné, VS zachovány.
[Protokol a limity](verification/refactoring-wave5-release-20261028/RESULTS.md).
Bez importu kroužků a bez plného UAT. Předchozí přehled zachován celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-28-wave4", "part": 0, "sha256": "67f2e38d1dc13dba099624e988b5e32f6a7bea9c6baf00db06db4ac5164cba2d"} -->
# Aktuální stav projektu

Aktualizováno 28. 10. 2026 (Europe/Prague); další vydání musí tento přehled nahradit.

- **Poslední doložené nasazení:** `e40df7d3c532ed933541013f833b8f64e5951c47`,
  [PR #215](https://github.com/KovoPraha/evidenceTreninku/pull/215),
  [deploy 40771509403](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771509403),
  28. 10. 2026, SUCCESS; živé SHA a run ID ověřeny následnou čtecí kontrolou.
  [Protokol vydání a důkazy](verification/refactoring-wave4-release-20261028/RESULTS.md):
  před i po nasazení 22/22 anonymních HTTP kontrol PASS, schéma current / pending [], VS zachovány.
  Záloha 336 tabulek / 2 triggery vytvořena a ověřena; obnova v tomto vydání neopakována.
- **CI nasazeného main:**
  [Testy 40771201596](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771201596),
  [PHP quality 40771201654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771201654),
  [CodeQL 40771202202](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771202202)
  a [dokumentace 40771201612](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771201612), PASS.
  1 776 testů / 35 830 assertions; MariaDB 10.3/11.4, PHPStan a sweep PASS.
- **Změna:** čtvrtá vlna dávkově načítá zveřejněné podmínky programů.
  Pro 50 programů SQL 416 → 218, podmínky 200 → 2; výstupy shodné.
  Bez nové migrace, změny kapacity, členských cen či checkoutu. [Plán a důkazy](REFACTORING_WAVE4_2026-10-28.md).
  Produkční medián katalogu 0,949 → 0,571 s; tři vzorky před/po,
  orientační měření HTTP včetně sítě, nikoli zátěžový test.
- **Zapnuté:** společná Evidence/KIS, veřejný katalog, rodinné a sportovní účty,
  klubová, finanční, účetní a skladová implementace; bankovní/ruční platební postup.
- **Flags ověřené 28. 10. / bez nové aktivace:** Fio import a automatické potvrzování,
  SumUp, Stripe a Velocota vypnuté; Packeta flag není definovaný. KIS canonical writes false.
  [Živá verze a čtecí kontrola](verification/refactoring-wave4-release-20261028/release-comparison.json).
  Konfigurace, flags a ledger 141 záznamů shodné v čerstvé kontrole před/po vydání.
- **Blokery ostrého převzetí:** lidská přejímka a pilot, převzetí účetní D12–D14,
  skutečná inventura a ocenění, ověřené rodinné vazby a rozsahy trenérů,
  dohody/částky příspěvků a dvě chybějící přílohy účtenek.
  [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické vydání má souhlas vlastníka; UAT flag zůstává false.
  Historické přejímky se na toto vydání automaticky nepřenášejí.
- **Další krok:** dokončit lidské/účetní převzetí a doplnit ověřená provozní data;
  další výkonový kandidát je zbývající kanonické čtení nabídky a kapacity programů.
  Před každým vydáním ověřit společný stav schválených změn, CI a konkrétní souhlas
  vlastníka; po vydání aktualizovat tento přehled včetně SHA a důkazů.

[Postup převzetí stanice](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)

<!-- /archive -->

## 28. 10. 2026 — čtvrtá vlna refaktoringu sloučena a nasazena

PR #215 → `e40df7d3c532ed933541013f833b8f64e5951c47`, [deploy 40771509403](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771509403) SUCCESS.
1 776 testů / 35 830 assertions, před i po 22/22 HTTP PASS. SQL pro 50 programů 416 → 218.
Konfigurace, vypnuté integrace a ledger shodné, VS zachovány.
[Protokol a limity](verification/refactoring-wave4-release-20261028/RESULTS.md).
Bez importu kroužků a bez plného UAT. Předchozí přehled zachován celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-28-wave3", "part": 0, "sha256": "901664147fdcaade3219a80b15fca1948cd7a05339aff9b56b0670d61efba7f2"} -->
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

<!-- /archive -->

## 28. 10. 2026 — třetí vlna refaktoringu sloučena a nasazena

PR #213 → `09510ac6e44cfd01f571b91f5e52bc2d9d6eb9df`, [deploy 40761965370](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761965370) SUCCESS.
1 769 testů / 35 789 assertions, před i po vydání 22/22 HTTP PASS.
Konfigurace, vypnuté integrace a ledger shodné, dosavadní VS zachovány.
Katalog medián 2,92 → 0,32 s (tři pozorování každé cesty).
[Protokol a limity](verification/refactoring-wave3-release-20261028/RESULTS.md).
Bez importu kroužků a bez plného UAT. Předchozí přehled zachován celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-28-wave2", "part": 0, "sha256": "3180f960ddf727eb576995e7072dc0b3bb8be7fb2b672b90705e58fd1dfa6764"} -->
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

<!-- /archive -->

## 28. 10. 2026 — druhá vlna refaktoringu sloučena a nasazena

PR #211 byl sloučen jako `331bd97474b676bf4e22f529508a9db434dc64c6`.
[Deploy 40752209335](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40752209335)
skončil SUCCESS; 1 764 testů / 35 753 assertions, před i po nasazení 22/22 HTTP PASS.
Čtecí kontroly bezprostředně před a po vydání potvrdily nové živé SHA/run ID,
shodnou konfiguraci, integrační flags a migrační ledger. Medián katalogu
7,95 → 3,32 s, tří detailů 5,2–5,9 → 0,67–1,03 s (jen tři vzorky na cestu).
[Protokol, důkazy a limity](verification/refactoring-wave2-release-20261028/RESULTS.md).
Bez importu kroužků a bez plného UAT. Předchozí přehled je zachován celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-28-wave1", "part": 0, "sha256": "a4b5b2db031729ed2d4c7a65669bcbeafec6305dacb5a085bb99fa396eeba36d"} -->
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
  [Živá verze a čtecí kontrola](verification/refactoring-release-20261028/release-comparison.json).
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

<!-- /archive -->

## 28. 10. 2026 — první vlna refaktoringu sloučena a nasazena

Po výslovném pokynu vlastníka „Takze sloučit a nasadit“ byl sloučen PR #209
jako `20ab863701bfb1b90e3a7f5a7c1357cceda3c30b` a nasazen na kis.kovopraha.cz.
[Deploy 40701923049](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701923049),
SUCCESS. 1 757 testů / 35 705 assertions; následně 14/14 HTTP kontrol PASS
a čtecí ověření živého SHA, run ID, konfigurace a vypnutých integrací.
[Protokol vydání a limity](verification/refactoring-release-20261028/RESULTS.md).
Bez importu kroužků a bez plného UAT. Předchozí přehled je zachován celý:

<!-- archive {"source": "docs/CURRENT_STATE.md@2026-10-26", "part": 0, "sha256": "85a5bb0c913394c073de21f935d8eb74fc9f62fbea227fe0643fb80a48832c8e"} -->
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

<!-- /archive -->

## 26. 10. 2026 — opravy dozoru F1–F3 sloučeny a nasazeny

Po výslovném souhlasu vlastníka byly postupně sloučeny PR #196, #199, #197,
#198 a #200. Nasazen `fc0d3779306e70362e013f088f30974725bbf8ff`,
[deploy 40330758548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330758548), SUCCESS.
Předchozí živé vydání bylo `d12e7c792ce796260f3fc5d07b25a395c310fcca`
([deploy 40317875183](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317875183)).
Finální obsah main je totožný s prověřeným společným headem `65a61b5b`.
Linux 1 748 testů / 35 593 assertions, MariaDB 10.3/11.4, PHPStan, sweep,
CodeQL a dokumentace PASS. Produkce: 24 HTTP, 16 databázových, 14 účetních
a 20 skladových kontrol PASS, 8 runtime hashů shodných; záloha obnovena
v izolaci (336 tabulek / 2 triggery). Konfigurace, integrační flags,
dosavadní VS a 141 záznamů ledgeru beze změny. Žádný import nebo aktivace;
UAT false, dvě historicky chybějící přílohy nadále otevřené.
[Protokol a hranice ověření](verification/dozor-release-20261026/RESULTS.md).

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 0, "sha256": "fb3e50b08bb8825e6fbb0a4e756a147782dcb4aebb5b00874dc41d1c9b772996"} -->
# Aktuální stav projektu


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 1, "sha256": "5c95262037a15907203a7babec271bc6d28cb7f17e5f770644aed65938c9379f"} -->
**25. 10. 2026 — dokončené opravy sloučené, nasazené a automatizovaně ověřené.**
Produkce: `a23e393f64eeffafd75df834e6e5ba8c78213b72` (PR #194,
[deploy 40198413027](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198413027), SUCCESS).
[Souhrnný protokol a otevřené provozní body](verification/release-acceptance-20261025/RESULTS.md).
Linux: 1 718 testů / 34 748 assertions; MariaDB 10.3/11.4 a CodeQL PASS.
Izolovaně: 26 HTTP sad / 805 kontrol, 6 sad souběhů / 78 kontrol a 17 migračních
kontrol PASS. Produkce pouze čtením: 16 databázových + 14 účetních + 20 skladových
kontrol PASS; záloha obnovena v izolaci (330 tabulek, 2 triggery).
Živý trenérský přehled už odkazuje na nejstarší nezapsanou lekci z 30. 5. 2026.
Konfigurace, dosavadní VS a 139 migračních záznamů zachovány; nové integrace,
importy ani automatické schvalování vazeb nebyly zapnuté. Bez testovacích
obchodních zápisů v produkci. D15 je splněno pro toto vydání kódu.
Technická přejímka nenahrazuje lidské převzetí ani doplnění provozních dat:
36 trenérů bez aktuálního rozsahového oprávnění, 0 schválených rodinných vazeb
(poslední čtecí UI kontrola), 799 zveřejněných variant bez počátečního řízeného
skladu, příspěvkový plán bez dohod/rozhodnutí částek a 2 již dříve chybějící
přílohy účtenek. Přesné hranice a kroky uvádí protokol; nic se nedoplňuje odhadem.
Původní checkout `C:\xampp\htdocs\evidencePavel` zůstává starou větví s WIP
`kis_rosters_admin.php`; není zdrojem release. Aktuální důkazy jsou v main a
checkoutu `C:\xampp\htdocs\evidencePavel-browser-acceptance-20261025`.
Následující datované zápisy jsou historie, nikoli aktuální stav nasazení.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 2, "sha256": "30741ce8d60ae2f603436d4b5c3afc15c90d3ab9a149d7abb8eb65380dd0820a"} -->
**25. 10. 2026 — integrační vydání sloučeno a nasazeno.**
Produkce běží na `115a617d3660bc9e3cba48891737b56fc6ab068f` (PR #191 + #192,
deploy `40189517120`). Uživatel výslovně povolil tento merge a deploy včetně
nutných migrací. Nové integrace nebyly zapnuté, skutečná data nebyla importovaná
a nevznikaly testovací obchodní záznamy na produkci. D15 je splněno pro toto
vydání; souhlas účetní, podklady pilotu a další aktivace zůstávají samostatné.
[Protokol nasazení a čtecí kontroly](verification/production-integration-20261025/RESULTS.md)
a [ověřená příprava návratu](verification/production-integration-20261025/ROLLBACK.md).
Následující datované zápisy popisují historický stav před tímto nasazením.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 3, "sha256": "65e74377fdf9ef1c53b84c4f2bb23fe6bfbf05d3592251575618a655c2a1ddeb"} -->
**25. 10. 2026 — společná integrační větev ověřena, NENASAZENO.**
Větev `codex/integration-20261025` v samostatném checkoutu
`C:\xampp\htdocs\evidencePavel-integration-20261025` sjednocuje aktuální
`origin/main` (`6bf57d6f`, bezpečnostní PR #190) a dokončené klubové balíky
z `420e78a0`. Ověřený technický commit `075d92ac` prošel Linuxem
(1 717 testů / 34 744 assertions), MariaDB 10.3/11.4, 26 místními HTTP sadami,
souběhy, migracemi a obnovou. [Výsledky, rozsah a výjimky](verification/integration-20261025/RESULTS.md).
Inventura 249 výchozích odkazů neodhalila další chybějící dokončenou implementaci.
Žádný merge do main, nasazení, aktivace služeb ani převod skutečných dat neproběhl.
Následující datované záznamy jsou historické; jejich údaje o otevřeném PR #190,
produkčním SHA a počtech testů nejsou stavem dnešní integrační větve.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 0, "sha256": "c25549fb35378366c46b9e40ef411e93208f4c3b0350b80872334694df17602e"} -->
# Aktuální předání vývoje na další stanici


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 1, "sha256": "5c95262037a15907203a7babec271bc6d28cb7f17e5f770644aed65938c9379f"} -->
**25. 10. 2026 — dokončené opravy sloučené, nasazené a automatizovaně ověřené.**
Produkce: `a23e393f64eeffafd75df834e6e5ba8c78213b72` (PR #194,
[deploy 40198413027](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198413027), SUCCESS).
[Souhrnný protokol a otevřené provozní body](verification/release-acceptance-20261025/RESULTS.md).
Linux: 1 718 testů / 34 748 assertions; MariaDB 10.3/11.4 a CodeQL PASS.
Izolovaně: 26 HTTP sad / 805 kontrol, 6 sad souběhů / 78 kontrol a 17 migračních
kontrol PASS. Produkce pouze čtením: 16 databázových + 14 účetních + 20 skladových
kontrol PASS; záloha obnovena v izolaci (330 tabulek, 2 triggery).
Živý trenérský přehled už odkazuje na nejstarší nezapsanou lekci z 30. 5. 2026.
Konfigurace, dosavadní VS a 139 migračních záznamů zachovány; nové integrace,
importy ani automatické schvalování vazeb nebyly zapnuté. Bez testovacích
obchodních zápisů v produkci. D15 je splněno pro toto vydání kódu.
Technická přejímka nenahrazuje lidské převzetí ani doplnění provozních dat:
36 trenérů bez aktuálního rozsahového oprávnění, 0 schválených rodinných vazeb
(poslední čtecí UI kontrola), 799 zveřejněných variant bez počátečního řízeného
skladu, příspěvkový plán bez dohod/rozhodnutí částek a 2 již dříve chybějící
přílohy účtenek. Přesné hranice a kroky uvádí protokol; nic se nedoplňuje odhadem.
Původní checkout `C:\xampp\htdocs\evidencePavel` zůstává starou větví s WIP
`kis_rosters_admin.php`; není zdrojem release. Aktuální důkazy jsou v main a
checkoutu `C:\xampp\htdocs\evidencePavel-browser-acceptance-20261025`.
Následující datované zápisy jsou historie, nikoli aktuální stav nasazení.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 2, "sha256": "30741ce8d60ae2f603436d4b5c3afc15c90d3ab9a149d7abb8eb65380dd0820a"} -->
**25. 10. 2026 — integrační vydání sloučeno a nasazeno.**
Produkce běží na `115a617d3660bc9e3cba48891737b56fc6ab068f` (PR #191 + #192,
deploy `40189517120`). Uživatel výslovně povolil tento merge a deploy včetně
nutných migrací. Nové integrace nebyly zapnuté, skutečná data nebyla importovaná
a nevznikaly testovací obchodní záznamy na produkci. D15 je splněno pro toto
vydání; souhlas účetní, podklady pilotu a další aktivace zůstávají samostatné.
[Protokol nasazení a čtecí kontroly](verification/production-integration-20261025/RESULTS.md)
a [ověřená příprava návratu](verification/production-integration-20261025/ROLLBACK.md).
Následující datované zápisy popisují historický stav před tímto nasazením.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 3, "sha256": "65e74377fdf9ef1c53b84c4f2bb23fe6bfbf05d3592251575618a655c2a1ddeb"} -->
**25. 10. 2026 — společná integrační větev ověřena, NENASAZENO.**
Větev `codex/integration-20261025` v samostatném checkoutu
`C:\xampp\htdocs\evidencePavel-integration-20261025` sjednocuje aktuální
`origin/main` (`6bf57d6f`, bezpečnostní PR #190) a dokončené klubové balíky
z `420e78a0`. Ověřený technický commit `075d92ac` prošel Linuxem
(1 717 testů / 34 744 assertions), MariaDB 10.3/11.4, 26 místními HTTP sadami,
souběhy, migracemi a obnovou. [Výsledky, rozsah a výjimky](verification/integration-20261025/RESULTS.md).
Inventura 249 výchozích odkazů neodhalila další chybějící dokončenou implementaci.
Žádný merge do main, nasazení, aktivace služeb ani převod skutečných dat neproběhl.
Následující datované záznamy jsou historické; jejich údaje o otevřeném PR #190,
produkčním SHA a počtech testů nejsou stavem dnešní integrační větve.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 4, "sha256": "76749ba6f9d791d243a1e0982824b7a4d78fcfd53b8bc4adc28b026a83b7479f"} -->
**24. 10. 2026 — čtyři bezpečnostní nálezy opraveny, PR #190 / Linux CI PASS, NENASAZENO.**
[Opravy a regresní důkazy](verification/security-fixes-20261024/RESULTS.md).
Větev `codex/security-audit-fixes-20261024`: aktuální práva před akcí na 27 cestách,
textové buňky UCI, bezpečné JSON účastníků a pracovní vlastnictví `list.php`.
38 cílených testů / 2017 assertions PASS, syntaxe 1088 PHP/PHP3 PASS.
Plná Windows sada 1685 / 33580: pouze známý failure POSIX scanneru a 3 skips.
Větev odeslána, [PR #190](https://github.com/KovoPraha/evidenceTreninku/pull/190) otevřen.
Linux CI40130990758: 1685 / 33629 PASS, MariaDB10.3/11.4 a CodeQL PASS.
Bez konfliktů s main; bez migrací, merge, produkčních změn a deploye.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 5, "sha256": "a67de4e0732cde671bac4fe577229e4b4ffb078a446db42df6cba84522b02c8d"} -->
**24. 10. 2026 — balík C „Akce a klubová administrativa“ lokálně implementován, NENASAZEN.**
[Protokol, přejímka a hranice](verification/event-administration-20261024/RESULTS.md).
Větev `codex/event-administration-20261024`, navazuje na B (`f521a10d`).
Přehled akce s účastníky a odkazy na skutečnost, dopravu a vyúčtování;
pojmenované finanční výběry, tabulka položek, soukromá účtenka s návratem do
případu, detaily/historie/salda výdajů a záloh, filtry vypořádání odměn.
79 testů / 2110 assertions; HTTP C 49, výdaje 47, odměny 29, regrese A 32 PASS.
Otevřené D20 (samoobslužné předkládání výdajů), D04/D06 (sazby a metodika),
D05 (schvalovatelé a výjimky); předchozí D17–D19 se tím nepotvrzují.
Bez migrací, změn e-shopu, produkčních zásahů a aktivace transportů.
Další nezávislý krok je balík D; reálné převody a komunikace vyžadují podklady.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 6, "sha256": "900aafd04c942f41271a4f7154df9cfe6f10eee86d76da90a7af18e92527c0f4"} -->
**24. 10. 2026 — balík B „Život osoby a podklady“ lokálně implementován v rozsahu bez otevřených rozhodnutí, NENASAZEN.**
[Protokol a odložený rozsah](verification/person-life-documents-20261024/RESULTS.md).
Větev `codex/person-life-documents-20261024` navazuje na A (`1925f88c`).
Vyřízení osoby s odpovědností a historií, dohledatelná rozhodnutí o členství,
upozornění na neověřené znění papírového podkladu, zdravotní stav skupiny/lekce
k datu a úkoly rodiny. Role, soukromé dokumenty a oddělení členství od závazků zachovány.
155 testů / 2563 assertions; HTTP B 30 + regrese A 32 scénářů PASS v syntetickém B1.
D17 datovaná účinnost členství, D18 zdravotní pravidla a D19 online změny/přílohy
rodiny zůstávají odložené. Bez migrací, změn e-shopu, produkčních zásahů a aktivace zpráv.
Další nezávislý balík C lze realizovat bez schvalování těchto odložených bodů.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 7, "sha256": "2c0bad5d892467fa8059af0a114829ddc0b137b6a6d39fe0b216585ac440ec7c"} -->
**24. 10. 2026 — balík A „Každodenní práce trenéra a registráře“ implementován lokálně, NENASAZEN.**
[Protokol a rozsah](verification/daily-coach-registrar-20261024/RESULTS.md).
Větev `codex/daily-coach-registrar-20261024` v izolovaném B1 checkoutu.
Případy vedoucího sportu; propojení lekce/omluvy/zápisu/uzavření;
pojmenovaný výběr a opravy sportovní účasti; hledání, stav a stránky přehledů;
registrátorský přehled kroků u osoby. Opravené chybějící delegace trenérských tras
pro vedoucího sportu; souběhy a rozsah přístupu zachované.
135 testů / 2433 assertions, nový HTTP průchod 32 scénářů PASS.
Bez produkčních zásahů, migrací a změn e-shopu. Navazují balíky B–D;
společná přejímka včetně e-shopu až E. Živý release zůstává uveden níže.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 8, "sha256": "4d42ac1f96fb9c6734f6ab59b5ef3626468c9654bb654ecde5f5b743080c1679"} -->
**24. 10. 2026 — skladové opravy PR #187 nasazeny, čtecí produkční kontrola dokončena.**
[Protokol a důkazy](verification/stock-production-20261024/RESULTS.md).
Release `065ed2a6fb797e21928d0eb5d1d5b84af0c04d93`, deploy **40085679569 SUCCESS**:
záloha330 tabulek, pending=[], VS zachovány, HTTP200; 8/8 živých otisků souhlasí.
16 základních + 20 skladových + 14 účetních kontrol PASS. Produkce: 8 objednávek,
skladové/účetní tabulky zatím prázdné; **855 katalogových variant zboží bez řízeného
skladového stavu**. Není to počet kusů ani závěr o aktivní nabídce. Skutečné
počátky vyžadují inventuru, nebyly doplněny odhadem. Celý průchod prokázán izolovaně.
Diagnostika PR #188: 1677 testů / 33411 assertions, MariaDB10.3/11.4 a CodeQL PASS.
Bez prohlížeče a bez testovacích doménových zápisů do produkce. Lidská přejímka otevřená.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 9, "sha256": "3ec7c9116ffae242457bd79a6b53e0c7459adf041493f8442d210b7c4e04d15e"} -->
**24. 10. 2026 — přejímka skladů, výdejů a vratek bez prohlížeče; oprava zatím nenasazena.**
[Protokol](verification/stock-followup-20261024/RESULTS.md), PR #187.
Opraveno přehlédnutí úplné vratky z příjmů propojených jen příděly a chybné
blokování výdeje kvůli vratce jiné objednávky ze společného příjmu.
80 cílených testů / 834 assertions; CI 1676 / 33403, MariaDB10.3/11.4 a CodeQL PASS.
Ověřen celý průchod výdej → blokovaná vratka → posouzení → opravný doklad
→ skutečná vratka peněz → skladové PDF v účetním ZIP; kurzy obou dětí zachovány.
Souběhy skladu/výdejů/vratek a konzistentní export PASS. Produkce v tomto kole
nebyla měněna ani čtena, testovací zápisy pouze v izolovaných datech.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 4, "sha256": "7de8371b8b64c2842b502fbd889129b3861645fd1af219fea87a33a2424a330b"} -->
**24. 10. 2026 — opravy SEC-01 až SEC-04 v PR #190, Linux CI PASS, výslovně NENASAZOVAT.**
[Protokol a testy](verification/security-fixes-20261024/RESULTS.md).
Větev `codex/security-audit-fixes-20261024` vychází z `f2626b86`; checkout
`C:\Users\Marek\Documents\Codex\evidencePavel-security-fixes-20261024`.
27 pořadí bootstrap/autorizace, UCI literal text, JSON v HTML, `list.php` sports_lead.
Nových 8 regresí; 38 cílených testů PASS. Plná Windows sada 1685 / 33580:
stejný známý POSIX failure, 3 symlink skips, bez nových selhání. Lint 1088 PASS.
Implementační commit `f7d58d8d` odeslán; [PR #190](https://github.com/KovoPraha/evidenceTreninku/pull/190) je otevřený do main.
Linux CI40130990758: 1685 / 33629 PASS, MariaDB10.3/11.4 a CodeQL PASS.
Původní checkout s rozpracovaným `kis_rosters_admin.php` zachován. Bez migrací,
merge a nasazení; další krok je rozhodnutí o sloučení PR, deploy samostatně.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 5, "sha256": "a67de4e0732cde671bac4fe577229e4b4ffb078a446db42df6cba84522b02c8d"} -->
**24. 10. 2026 — balík C „Akce a klubová administrativa“ lokálně implementován, NENASAZEN.**
[Protokol, přejímka a hranice](verification/event-administration-20261024/RESULTS.md).
Větev `codex/event-administration-20261024`, navazuje na B (`f521a10d`).
Přehled akce s účastníky a odkazy na skutečnost, dopravu a vyúčtování;
pojmenované finanční výběry, tabulka položek, soukromá účtenka s návratem do
případu, detaily/historie/salda výdajů a záloh, filtry vypořádání odměn.
79 testů / 2110 assertions; HTTP C 49, výdaje 47, odměny 29, regrese A 32 PASS.
Otevřené D20 (samoobslužné předkládání výdajů), D04/D06 (sazby a metodika),
D05 (schvalovatelé a výjimky); předchozí D17–D19 se tím nepotvrzují.
Bez migrací, změn e-shopu, produkčních zásahů a aktivace transportů.
Další nezávislý krok je balík D; reálné převody a komunikace vyžadují podklady.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 6, "sha256": "900aafd04c942f41271a4f7154df9cfe6f10eee86d76da90a7af18e92527c0f4"} -->
**24. 10. 2026 — balík B „Život osoby a podklady“ lokálně implementován v rozsahu bez otevřených rozhodnutí, NENASAZEN.**
[Protokol a odložený rozsah](verification/person-life-documents-20261024/RESULTS.md).
Větev `codex/person-life-documents-20261024` navazuje na A (`1925f88c`).
Vyřízení osoby s odpovědností a historií, dohledatelná rozhodnutí o členství,
upozornění na neověřené znění papírového podkladu, zdravotní stav skupiny/lekce
k datu a úkoly rodiny. Role, soukromé dokumenty a oddělení členství od závazků zachovány.
155 testů / 2563 assertions; HTTP B 30 + regrese A 32 scénářů PASS v syntetickém B1.
D17 datovaná účinnost členství, D18 zdravotní pravidla a D19 online změny/přílohy
rodiny zůstávají odložené. Bez migrací, změn e-shopu, produkčních zásahů a aktivace zpráv.
Další nezávislý balík C lze realizovat bez schvalování těchto odložených bodů.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 7, "sha256": "2c0bad5d892467fa8059af0a114829ddc0b137b6a6d39fe0b216585ac440ec7c"} -->
**24. 10. 2026 — balík A „Každodenní práce trenéra a registráře“ implementován lokálně, NENASAZEN.**
[Protokol a rozsah](verification/daily-coach-registrar-20261024/RESULTS.md).
Větev `codex/daily-coach-registrar-20261024` v izolovaném B1 checkoutu.
Případy vedoucího sportu; propojení lekce/omluvy/zápisu/uzavření;
pojmenovaný výběr a opravy sportovní účasti; hledání, stav a stránky přehledů;
registrátorský přehled kroků u osoby. Opravené chybějící delegace trenérských tras
pro vedoucího sportu; souběhy a rozsah přístupu zachované.
135 testů / 2433 assertions, nový HTTP průchod 32 scénářů PASS.
Bez produkčních zásahů, migrací a změn e-shopu. Navazují balíky B–D;
společná přejímka včetně e-shopu až E. Živý release zůstává uveden níže.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 8, "sha256": "512df1a4070634f87b43831053647b71a5984fcb04cb8ae88ea376eb494949ae"} -->
**24. 10. 2026 — PR #187 nasazen, produkční skladový/účetní audit PASS.**
[Protokol a souhrnné důkazy](verification/stock-production-20261024/RESULTS.md).
Živý release `065ed2a6fb797e21928d0eb5d1d5b84af0c04d93`, deploy40085679569 SUCCESS.
Záloha330 tabulek, pending=[], VS zachovány, HTTP200; ověřeny hashe8 souborů.
Drill40085999249: 16 kontrol PASS; audit40086062724: 20 skladových + 14 účetních PASS.
Produkce: 8 objednávek, 0 skladových/účetních záznamů a souborů, 855 katalogových
variant zboží bez řízeného skladového stavu. Bez skutečné inventury nelze doplnit
počátky; nulové tabulky nejsou dokladem reálného průchodu. Testovací zápisy jen izolovaně.
Opakovatelná diagnostika PR #188 (main283c46db) není další aplikační deploy:
1677 testů / 33411 assertions, MariaDB10.3/11.4, CodeQL a negativní kontroly PASS.
Použít `production-drills.yml`, `overit-ucetni-vazby`, `PROVEST`; žádný aplikační
bootstrap, konzistentní READ ONLY, dočasné skripty mimo webroot se odstraní.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 9, "sha256": "876d48f5ba5cb909f36aae88b23da27a447eca7c016d3a96c8cc63babfca8fe4"} -->
**24. 10. 2026 — skladové návaznosti ověřeny, PR #187 zatím bez nasazení.**
[Protokol a souběžné kontroly](verification/stock-followup-20261024/RESULTS.md).
Výdej respektuje úplné účetní vratky z historie přídělů; při sdíleném příjmu
má přednost výslovný původní doklad. Obě chyby reprodukovány před opravou.
1676 testů / 33403 assertions, MariaDB10.3/11.4, CodeQL PASS; fyzická vratka
trička prošla až do skladového PDF v účetním exportu a nezměnila kurzy dětí.
Produkce nedotčena; testovací data jen izolovaná. Další nasazení nebylo provedeno.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 10, "sha256": "0f98d95e1c70d3a14252d46dfbc1c88ccf6c2654c27f04aac4fb9ddae9cadd6c"} -->
**23. 10. 2026 — účetní opravy nasazeny, přejímka bez prohlížeče dokončena v čtecím rozsahu.**
[Protokol a důkazy](verification/accounting-production-20261023/RESULTS.md).
Release `ebc3081cbee3a1b38f75bcc2989e4bf6800b1cb9`, deploy **40025423618 SUCCESS**:
1669 testů, záloha 330 tabulek, žádné pending migrace, dosavadní VS beze změny.
Produkce: 16 databázových kontrol PASS; čtecí účetní audit 40026086361 PASS,
otisky tří nasazených účetních souborů přesně souhlasí s ověřeným commitem.
Produkce má **8 objednávek a 0 účetních dokladů/pohybů/exportů**: PDF/ZIP průchod
je prokázán izolovaně, nikoli nad neexistujícími produkčními podklady.
Nová opakovatelná diagnostika PR #185: 1674 testů / 33371 assertions,
MariaDB 10.3/11.4 a CodeQL PASS včetně detekce záměrně poškozených testovacích souborů.
Žádné testovací doménové zápisy do produkce, žádné aktivace integrací.
Lidská přejímka, metodika a převzetí účetní zůstávají otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 11, "sha256": "5e174db621ef31ad06e3e385f84de55eb9ae45539ac1ecff92bbde60127d664d"} -->
**23. 10. 2026 — účetní návaznosti objednávek opravené, zatím nenasazeno.**
[Protokol](verification/accounting-followup-20261023/RESULTS.md).
XLSX nově zachová účastníka, období, referenci případu, VS a splatnost z dokladu.
Opraveny kontroly dvojí vratky přes dílčí příjmy a dokončení úplné vratky z více příjmů.
73 cílených testů / 456 assertions PASS; nativní průchod PDF → příjmy → XLSX/ZIP
→ opravný doklad a vratka → neměnná revize exportu. Ukázka pouze syntetická.
Bez migrace a bez produkční změny. Účetní převzetí i browser UAT zůstávají otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 12, "sha256": "88dc32d3cb9738ec924c23f04d407dcfdf3464830ce136e5ef4208a102f3bbc3"} -->
**23. 10. 2026 — rozšířená technická přejímka rodinného nákupu, bez nasazení.**
[Protokol](verification/family-followup-20261023/RESULTS.md), PR #183.
38 cílených testů / 960 assertions a nativní MariaDB smoke PASS: dva sourozenci,
zboží, různé rozsahy kupónu, přesné finanční podíly, rollback posledního místa,
účetní řádky, dílčí příjmy, přeplatek a idempotentní výdej. Pouze testy a dokumentace.
Prohlížeč se odpojil; mobilní přejímka, doplnění B15 přes UI a nový export odloženy.
B15 zůstává PARTIAL, lidské převzetí i účetní otevřené. Produkce se v tomto kole nemění.

<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 13, "sha256": "c01e262cce41a508bb17939d29dc4e3963746807e682ec85ddb966915a2e43e4"} -->
**23. 10. 2026 21:12 — stejný kurz pro více dětí v jedné objednávce, nasazeno.**
[Protokol a důkazy](verification/multi-child-course-20261023/RESULTS.md).
Samostatné položky a souhlasy, přesná kapacita, neměnné finanční podíly,
jména v detailu objednávky. Browser B1 objednávka33/varianta55: Anna91 + Bára93,
300 Kč, účasti12/13 aktivní, podíly150+150Kč. Nativní MariaDB smoke včetně
postupné úhrady podílů PASS. Migrace137 zahrnuje zálohování finančních podílů.
Předchozí omezení na samostatné objednávky sourozenců je nahrazeno touto změnou.
PR #181, release `43a825e01235e66baa1601c3bc957365df011009`, chráněný deploy
**40009024140 SUCCESS**, záloha/migrace/zachování VS/HTTP smoke PASS.
Linux **1659 testů / 33138 assertions**, MariaDB10.3/11.4 a CodeQL PASS.
Živý browser potvrdil release, úložiště PASS a nový postup na produktu247.
B15 nadále PARTIAL; částečné storno uhrazené objednávky nepřidáno.
Produkční doménová data pouze čtena, žádné zkušební objednávky ani aktivace integrací.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 14, "sha256": "307bd1eccb5e2f48a1faf8b480c3778f1b8c6d753fdf864f2463d3b465499f3b"} -->
**23. 10. 2026 20:28 — přejímka rodinných a sportovních účtů, opravy nasazeny.**
[Protokol a přesné meze](verification/family-course-20261023/RESULTS.md).
B1 browser prošel žádost → schválení → nákup kurzu → simulovaná úhrada → soupiska,
odvolání přístupu a obnovení vazby. Produkce pouze čtení; návrat ke konkrétnímu
termínu a prázdné profily bez vazeb PASS. Opravy: kontrola aktuálního ověření účtu
při výběru příjemce, ochrana před tichým přepsáním dítěte v košíku a rozlišení
potvrzené docházky sportovce. Cílené testy 229/2596 + reset/návrat 8/78 PASS.
Browser sportovního účtu odložen: B1 nemá žádný existující sportovní účet.
PR #179, release `c6e3328b`, chráněný deploy **40002714298 SUCCESS**.
Linux **1656 testů / 33008 assertions**, MariaDB 10.3/11.4 a CodeQL PASS.
Živý browser potvrdil SHA, soukromé úložiště PASS a rodinnou stránku bez cizích údajů.
B15/B18 v hubu PARTIAL; stejné období pro sourozence vyžaduje samostatné objednávky.
Žádné syntetické produkční doménové zápisy ani aktivace integrací. Lidské přijetí otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 15, "sha256": "19b57e6c002671ed954b8c3283677c130427b874cc89ed16b8b4f9d73e2e917a"} -->
**23. 10. 2026 19:49 — přihlášená produkční přejímka osob a tréninků dokončena v čtecím rozsahu.**
[Protokol, opravy a meze](verification/people-training-production-20261023/RESULTS.md).
Opraveno HTTP 500 při hledání osoby nad rozdílnými kolacemi jmen, chybný cíl upozornění
na nezapsané lekce a HTML entity v náhledu oznámení. PR #177, release `313cd61b`,
deploy **39997113354 SUCCESS**. Linux **1653 testů / 32963 assertions**, MariaDB 10.3/11.4
a CodeQL PASS. Produkční browser potvrdil správné hledání osoby i auditní osy,
skutečný cílový týden nezapsané lekce, aktuální SHA a soukromé úložiště PASS.
Produkční osoby, vztahy a tréninky se neměnily. Zápis výsledků jen v izolovaném B1.
Předchozí překážka přihlášení je vyřešena; lidská přejímka, účetní převzetí a aktivace integrací zůstávají otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 16, "sha256": "88bc1b4f5ab4a11cb20777d98fed516fc278fbf69e55e4ef0820afcdd8ece98b"} -->
**23. 10. 2026 — detailní kontrola osob, tréninků a vazeb na e-shop.**
[Nový protokol a přesné meze](verification/people-training-20261023/RESULTS.md).
Opraven souběh editace osob, atomická poznámka/audit, rychlá přihláška kurzu,
aktuální oprávnění rodiny a historické soupisky. Tréninky: opraven JavaScript,
identita měření, zachování spolutrenérů, bezpečnost snímků, opakování formuláře
včetně návratu Zpět, rozepsaná docházka a viditelné odmítnutí změny uzavřeného záznamu.
Browser B1 potvrdil osobu92 a plán15/trénink31 s uzávěrkou v2; zkouška34 po opravě
nevytvořila při návratu Zpět duplicitu. Pouze syntetická místní data.
Cílená sada 232/2491 PASS, Node 8/8 PASS; plná Windows sada má známé POSIX selhání.
PR #175 nasazen jako `ee6cff62`, deploy **39987332095 SUCCESS**. Linux CI **1 650 testů / 32 947 assertions PASS**, MariaDB i CodeQL PASS. Produkční přihlášení při reloadu
vypršelo; přihlášená živá prohlídka zůstává odložená. Lidských 43 scénářů NEPROVEDENO,
účetní NEPŘEVZATO. Žádné aktivace integrací ani opravy historických vazeb odhadem.



<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 17, "sha256": "cb90e6c86a3175237d05d3cea69166c63e24e4f215046254896f8ace359965fd"} -->
**23. 10. 2026 — souvislá technická browser přejímka a opravy obsluhy.**
V izolovaném B1 prošel řetězec sklad → osobní prodej → úhrada → částečný výdej
→ doklady → vratky → stažený účetní export a řetězec papírová přihláška
→ kroužek → úhrada → soupiska → docházka → oddělené schválení odměny.
[Protokol, opravy a přesné meze](verification/browser-acceptance-20261023/RESULTS.md).
Všechny nové provozní záznamy jsou syntetické a pouze místní. Účetní export
má 20 ověřených technických kontrol, ale zůstává NEÚPLNÝ bez schváleného
členění a odsouhlasení. Lidských 43 scénářů je nadále NEPROVEDENO,
účetní převzetí NEPŘEVZATO. Opravy PR #173 jsou nasazeny jako `e78af565`,
chráněný deploy **39937236344 SUCCESS**, Linux **1 614 testů / 32 689 assertions PASS**.
Živá čtecí kontrola změněných obrazovek prošla; soukromé úložiště PASS.
Celková připravenost zůstává BLOKOVÁNO provozními předpoklady a rozhodnutími.



<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 18, "sha256": "93fb38fd310a7b28705f1f81e1f109dc25db3b5cd25ba44dd9cd34979a3e1bd7"} -->
**23. 10. 2026 — hostingový přístup k soukromému úložišti obnoven.**
Po výslovném schválení vlastníkem změněno pouze PHP `open_basedir` pro KIS
na režim Posunuto. Nový webový požadavek potvrzuje dostupný soukromý adresář
a oprávnění k zápisu; ostatní nastavení hostingu beze změny.
Nasazený diagnostický doplněk `fb2e00c7`, deploy 39926289201 SUCCESS.
Skutečná webová zkouška vytvoření/0600/zápisu/čtení/odstranění PASS;
1 584 testů / 32 313 assertions a obě MariaDB integrační sady v Linux CI PASS.
[Navazující důkazy a stav skutečné souborové zkoušky](verification/private-storage-20261023/RESULTS.md).
Původní P0 nález níže je uzavřený. Plná provozní přejímka zůstává otevřená.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 19, "sha256": "7a5b875f7c7ad0f4624fbe3e5fca747f1d94bbc34d1b100ed2ce673c0d9e35b7"} -->
**23. 10. 2026 — společné vydání R01–R44 nasazené, provozní přejímka má otevřený P0 blok.**
Živé SHA `0b03b564dfa6b20817478848fa8721fea09ca082`; [deploy SUCCESS](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39922198281).
[Produkční důkazy a zbývající práce](verification/production-club-20261023/RESULTS.md):
1 572 testů / 31 989 assertions PASS; 64 čtecích browser pozorování / 59 cest.
Opravený vstup do položkových výdejů, ochrana pracovních pozic a vyprodaná varianta přejaté prohlížečem.
**P0: webové PHP `open_basedir` nepovoluje připravené soukromé úložiště.**
[Konkrétní zásah hostingu a následné ověření](CLUB_STORAGE_HOSTING_BLOCKER.md).
Do vyřešení není vystavování soukromých PDF/archivů provozně převzaté.
Doložit granty 36 trenérů, počáteční inventuru 799 variant a dohodu/částku jednoho plánu příspěvků.
Nic z toho nebylo doplněno odhadem. Shoptet/Evidence běží dál, žádné finanční testovací zápisy ani aktivace integrací.
43 scénářů lidské přejímky nadále NEPROVEDENO, účetní převzetí NEPŘEVZATO.

Následující záznamy popisují historický stav před autorizovaným nasazením:



<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 20, "sha256": "db36f60c5a58db6326c12acf089b54a64e609cacf755ee433a17dc4ac70700ce"} -->
**23. 10. 2026 — připravená lidská přejímka a ukázkové předání účetní.**
Větev `codex/club-acceptance-package-20261023`, aplikační základ `abbb4be9`.
[Předání balíčku](acceptance-20261023/README.md): 43 scénářů / 188 kroků,
18 účetních kontrol, přenosný HTML průvodce, PDF a nezměněný nativní export.
Veškerá lidská přejímka **NEPROVEDENO**, účetní převzetí **NEPŘEVZATO**.
Nově jen dokumenty/nástroje balíčku a syntetické katalogové podklady přes místní UI;
aplikační kód ani produkce se neměnily. D01–D16 a omezený testovací účet odložené.
Žádný push, merge, deploy ani odeslání účetní.

Následující technické výsledky pocházejí z předchozího balíku:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 21, "sha256": "3719d3c6cd0e9c419d4e21a6085d32732a2f76a0c701dc23e8e1ffde8f080953"} -->
**23. 10. 2026 — navazující společná kontrola R01–R44 dokončena lokálně.**
Větev `codex/club-followup-review-20261023`, základ `7aff7884`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
[Předání oprav](CLUB_AUTONOMOUS_FOLLOWUP.md),
[aktuální důkazy](verification/autonomous-followup/RESULTS.md).
**1 545 testů / 31 583 assertions PASS; obnova 326 tabulek a 175 souborů PASS.**
136 migrací current, kontrakt `2026-10-23.3`. Opravena i nalezená závada místní
databáze: původní datadir zůstal zachovaný; nejistota historie jediné nečitelné
tabulky je uvedena v protokolu. Produkce, skutečný pilot a převzetí účetní
zůstávají beze změny, D01–D16 odložené. Žádný push, merge ani deploy.

Následuje historické předání před touto navazující kontrolou:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 22, "sha256": "73889ba39f9f1ba89850692d6a41802b7d578e3b43054e933848851d764a64d6"} -->
Lokální vývoj 23. 10. 2026: **dokončená technická příprava R38–R44** a
závěrečná finanční regrese ve větvi `codex/migration-cutover-r38-r44-20261023`.
Pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
[Společné předání R01–R44](CLUB_IMPLEMENTATION_HANDOFF.md),
[místní prohlídka](CLUB_LOCAL_REVIEW.md),
[závěrečné ověření](verification/r38-r44/RESULTS.md).
**1 526 testů, 132 HTTP kontrol a obnova 326 tabulek / 157 souborů PASS.**
136 migrací, zálohovací kontrakt `2026-10-23.3`.
Bez push, merge, nasazení a produkčních změn. Skutečné zdroje, metodika,
pilot, účetní převzetí a přepnutí zůstávají odložené podle D01–D16.

Následuje historické místní předání; jeho stav „pokračuje“ je překryt výše:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 23, "sha256": "31c71f95ba67d47223474fb8f806a31cfb1f42c14bb95edea2a8ed64b07ef1a3"} -->
Lokální vývoj 23. 10. 2026: **R31–R37 — výdaje, zálohy, odměny a účetní předání**,
větev `codex/club-administration-r31-r37-20261023`, izolovaná pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ `4033bf18`.
[Implementace](CLUB_ADMINISTRATION_R31_R37_IMPLEMENTATION.md),
[přejímka](verification/r31-r37/RESULTS.md),
[odložená rozhodnutí](CLUB_COMPLETION_DECISIONS.md).
**Bez nasazení a produkčních změn.** Lokálně ověřeno 1 479 testů,
93 skutečných HTTP kontrol, 36 souběžných kontrol a souvislý syntetický scénář.
Uživatelská přejímka, skutečné účetní předání a ostrý přechod zůstávají oddělené.
Pokračuje technická příprava migrace a přechodu R38–R44.

Následuje předchozí místní balík:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 24, "sha256": "641fb6a76c062fd4647b64ccb0cc29163b3a8f1059294c8f611ba57878807a09"} -->
Lokální vývoj 23. 10. 2026: **R25–R30 — členská a sportovní agenda**,
větev `codex/membership-sports-r25-r30-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ R18–R24
`12f28b611a8781968ca6b73821c0df3e2e4075dc`.
[Rozsah a pracovní postupy](MEMBERSHIP_SPORTS_R25_R30_IMPLEMENTATION.md),
[výsledky přejímky](verification/r25-r30/RESULTS.md).
**Výhradně izolovaná syntetická data; bez push, merge, nasazení a produkčních změn.**
Skutečná klubová pravidla, ostré historické převody a aktivace rozesílání nejsou
tímto balíkem schváleny. Navazují výdaje, zálohy a odměny R31–R34;
úplné účetní předání dokončuje R36.

Následuje historické předání předchozích lokálních balíků:


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 10, "sha256": "557c1b483cbd8588b5e6f25e2ba429a76ce3fe219a4690edfcd8ef4be6bcb6ae"} -->
**23. 10. 2026 — účetní opravy PR #184 nasazeny; další kontroly bez prohlížeče hotové.**
[Protokol a důkazy](verification/accounting-production-20261023/RESULTS.md).
Produkční release `ebc3081cbee3a1b38f75bcc2989e4bf6800b1cb9`, deploy40025423618 SUCCESS.
Záloha330 tabulek, pending=[], VS zachovány; 16 čtecích databázových kontrol PASS.
Účetní audit40026086361 PASS, živé hashe souborů odpovídají commitu.
**8 objednávek, účetní tabulky zatím prázdné**: nevydávat testovací doklady/exporty
v produkci; produkční PDF/ZIP ani lidské převzetí zatím nelze označit za prokázané.
Diagnostika PR #185 (main59725050) není další aplikační deploy. Běží přes
`production-drills.yml`, operace `overit-ucetni-vazby`, potvrzení `PROVEST`, READ ONLY;
dočasné skripty mimo webroot se uklidí. 1674 testů / 33371 assertions,
MariaDB10.3/11.4 + CodeQL PASS, detekce poškození ověřena izolovaně.
Žádné nové produkční účetní/doménové zápisy ani aktivace integrací.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 11, "sha256": "5e174db621ef31ad06e3e385f84de55eb9ae45539ac1ecff92bbde60127d664d"} -->
**23. 10. 2026 — účetní návaznosti objednávek opravené, zatím nenasazeno.**
[Protokol](verification/accounting-followup-20261023/RESULTS.md).
XLSX nově zachová účastníka, období, referenci případu, VS a splatnost z dokladu.
Opraveny kontroly dvojí vratky přes dílčí příjmy a dokončení úplné vratky z více příjmů.
73 cílených testů / 456 assertions PASS; nativní průchod PDF → příjmy → XLSX/ZIP
→ opravný doklad a vratka → neměnná revize exportu. Ukázka pouze syntetická.
Bez migrace a bez produkční změny. Účetní převzetí i browser UAT zůstávají otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 12, "sha256": "88dc32d3cb9738ec924c23f04d407dcfdf3464830ce136e5ef4208a102f3bbc3"} -->
**23. 10. 2026 — rozšířená technická přejímka rodinného nákupu, bez nasazení.**
[Protokol](verification/family-followup-20261023/RESULTS.md), PR #183.
38 cílených testů / 960 assertions a nativní MariaDB smoke PASS: dva sourozenci,
zboží, různé rozsahy kupónu, přesné finanční podíly, rollback posledního místa,
účetní řádky, dílčí příjmy, přeplatek a idempotentní výdej. Pouze testy a dokumentace.
Prohlížeč se odpojil; mobilní přejímka, doplnění B15 přes UI a nový export odloženy.
B15 zůstává PARTIAL, lidské převzetí i účetní otevřené. Produkce se v tomto kole nemění.

<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 13, "sha256": "c01e262cce41a508bb17939d29dc4e3963746807e682ec85ddb966915a2e43e4"} -->
**23. 10. 2026 21:12 — stejný kurz pro více dětí v jedné objednávce, nasazeno.**
[Protokol a důkazy](verification/multi-child-course-20261023/RESULTS.md).
Samostatné položky a souhlasy, přesná kapacita, neměnné finanční podíly,
jména v detailu objednávky. Browser B1 objednávka33/varianta55: Anna91 + Bára93,
300 Kč, účasti12/13 aktivní, podíly150+150Kč. Nativní MariaDB smoke včetně
postupné úhrady podílů PASS. Migrace137 zahrnuje zálohování finančních podílů.
Předchozí omezení na samostatné objednávky sourozenců je nahrazeno touto změnou.
PR #181, release `43a825e01235e66baa1601c3bc957365df011009`, chráněný deploy
**40009024140 SUCCESS**, záloha/migrace/zachování VS/HTTP smoke PASS.
Linux **1659 testů / 33138 assertions**, MariaDB10.3/11.4 a CodeQL PASS.
Živý browser potvrdil release, úložiště PASS a nový postup na produktu247.
B15 nadále PARTIAL; částečné storno uhrazené objednávky nepřidáno.
Produkční doménová data pouze čtena, žádné zkušební objednávky ani aktivace integrací.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 14, "sha256": "62160ff2d0f02d6906c93e8c2c49361a598176a38db49483ffbbe244417d5a2a"} -->
**23. 10. 2026 20:28 — rodinné účty a nákup kurzů, PR #179 nasazen.**
[Nový protokol](verification/family-course-20261023/RESULTS.md) obsahuje tři opravy,
browser B1 objednávku32 / osobu91 / vazbu86, čtecí produkční kontrolu a odložený
sportovní login. Cílené testy 229/2596 + 8/78 PASS; plná Windows sada má pouze
známé selhání POSIX scanneru. Linux **1656/33008**, MariaDB 10.3/11.4 a CodeQL PASS.
Produkční release `c6e3328be0567a1ea4b242f18eb6d66c4e1e9202`, chráněný deploy
**40002714298 SUCCESS**, dokončen 20:28:10 SELČ. Browser SHA a private storage PASS;
celková UAT brána zůstává BLOKOVÁNO. Moje kroužky načteny bez cizích údajů.
Další browser krok vyžaduje existující izolovaný sportovní účet; nevytvářet heslo za uživatele.
Nenahrazovat tím lidské přijetí, nevytvářet syntetické zápisy v produkci.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 15, "sha256": "925671a9fab12e8d5435aa7f3ff933e461e7af9f0cfd01bf69fbe73f3d32366c"} -->
**23. 10. 2026 19:49 — produkční prohlídka osob a tréninků po obnoveném přihlášení.**
Začít [novým protokolem](verification/people-training-production-20261023/RESULTS.md).
Aktuální produkční kód `313cd61ba5111f0d88e158b5107bb1160cb0d374` (PR #177), chráněný
deploy **39997113354 SUCCESS**, dokončen 19:46:45 SELČ. Opravené hledání osoby nad
různými kolacemi, odkaz na skutečný nejstarší nezapsaný plán a české entity v oznámeních.
Browser na produkci ověřil obě opravy, auditní hledání a soukromé úložiště; bez doménových zápisů.
CI 1653/32963, MariaDB 10.3/11.4 a CodeQL PASS. B1 protokol B05/B26 doplněn, stále PARTIAL.
Přihlášení již není překážkou této kontroly. Plná lidská/účetní přejímka zůstává otevřená;
neprovádět historické opravy vazeb, přidělování přístupů ani zapnutí integrací odhadem.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 16, "sha256": "88bc1b4f5ab4a11cb20777d98fed516fc278fbf69e55e4ef0820afcdd8ece98b"} -->
**23. 10. 2026 — detailní kontrola osob, tréninků a vazeb na e-shop.**
[Nový protokol a přesné meze](verification/people-training-20261023/RESULTS.md).
Opraven souběh editace osob, atomická poznámka/audit, rychlá přihláška kurzu,
aktuální oprávnění rodiny a historické soupisky. Tréninky: opraven JavaScript,
identita měření, zachování spolutrenérů, bezpečnost snímků, opakování formuláře
včetně návratu Zpět, rozepsaná docházka a viditelné odmítnutí změny uzavřeného záznamu.
Browser B1 potvrdil osobu92 a plán15/trénink31 s uzávěrkou v2; zkouška34 po opravě
nevytvořila při návratu Zpět duplicitu. Pouze syntetická místní data.
Cílená sada 232/2491 PASS, Node 8/8 PASS; plná Windows sada má známé POSIX selhání.
PR #175 nasazen jako `ee6cff62`, deploy **39987332095 SUCCESS**. Linux CI **1 650 testů / 32 947 assertions PASS**, MariaDB i CodeQL PASS. Produkční přihlášení při reloadu
vypršelo; přihlášená živá prohlídka zůstává odložená. Lidských 43 scénářů NEPROVEDENO,
účetní NEPŘEVZATO. Žádné aktivace integrací ani opravy historických vazeb odhadem.



<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 17, "sha256": "05a720b7387150d277b68165691f44c4bf7f1cf338ac5b4c133d872ce41fdbb8"} -->
**23. 10. 2026 — technická browser přejímka dvou propojených toků.**
Začít [aktuálním protokolem](verification/browser-acceptance-20261023/RESULTS.md),
který obsahuje reprodukované chyby, opravy, identifikátory syntetických záznamů,
ověření skutečně staženého exportu a stav vydání. PR #173 sloučen a nasazen
jako `e78af565`, chráněný deploy **39937236344 SUCCESS**. Linux CI
**1 614 testů / 32 689 assertions PASS**; produkční čtecí ověření prošlo.
Větev oprav `codex/browser-acceptance-fixes-20261023`, dokumentační doplnění
`codex/browser-acceptance-release-proof-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Původní pracovní kopie obsahuje cizí rozpracované změny; nepřepisovat.
Technické ověření agenta není lidské převzetí ani schválení účetní metodiky.
A01–A10 ani 43 scénářů lidské přejímky tím nejsou uzavřeny.



<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 18, "sha256": "93fb38fd310a7b28705f1f81e1f109dc25db3b5cd25ba44dd9cd34979a3e1bd7"} -->
**23. 10. 2026 — hostingový přístup k soukromému úložišti obnoven.**
Po výslovném schválení vlastníkem změněno pouze PHP `open_basedir` pro KIS
na režim Posunuto. Nový webový požadavek potvrzuje dostupný soukromý adresář
a oprávnění k zápisu; ostatní nastavení hostingu beze změny.
Nasazený diagnostický doplněk `fb2e00c7`, deploy 39926289201 SUCCESS.
Skutečná webová zkouška vytvoření/0600/zápisu/čtení/odstranění PASS;
1 584 testů / 32 313 assertions a obě MariaDB integrační sady v Linux CI PASS.
[Navazující důkazy a stav skutečné souborové zkoušky](verification/private-storage-20261023/RESULTS.md).
Původní P0 nález níže je uzavřený. Plná provozní přejímka zůstává otevřená.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 19, "sha256": "7a5b875f7c7ad0f4624fbe3e5fca747f1d94bbc34d1b100ed2ce673c0d9e35b7"} -->
**23. 10. 2026 — společné vydání R01–R44 nasazené, provozní přejímka má otevřený P0 blok.**
Živé SHA `0b03b564dfa6b20817478848fa8721fea09ca082`; [deploy SUCCESS](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39922198281).
[Produkční důkazy a zbývající práce](verification/production-club-20261023/RESULTS.md):
1 572 testů / 31 989 assertions PASS; 64 čtecích browser pozorování / 59 cest.
Opravený vstup do položkových výdejů, ochrana pracovních pozic a vyprodaná varianta přejaté prohlížečem.
**P0: webové PHP `open_basedir` nepovoluje připravené soukromé úložiště.**
[Konkrétní zásah hostingu a následné ověření](CLUB_STORAGE_HOSTING_BLOCKER.md).
Do vyřešení není vystavování soukromých PDF/archivů provozně převzaté.
Doložit granty 36 trenérů, počáteční inventuru 799 variant a dohodu/částku jednoho plánu příspěvků.
Nic z toho nebylo doplněno odhadem. Shoptet/Evidence běží dál, žádné finanční testovací zápisy ani aktivace integrací.
43 scénářů lidské přejímky nadále NEPROVEDENO, účetní převzetí NEPŘEVZATO.

Následující záznamy popisují historický stav před autorizovaným nasazením:



<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 20, "sha256": "3393c7676a2c8284e2409536e8e627a5367b1624546752441a571a01049df0ee"} -->
**23. 10. 2026 — balíček pro skutečnou uživatelskou přejímku je připraven.**
Začít [návodem a mezemi přejímky](acceptance-20261023/README.md),
[inventářem podkladů](acceptance-20261023/fixtures.md)
a [ověřením artefaktů](acceptance-20261023/verification.md).
Větev `codex/club-acceptance-package-20261023`, základ `abbb4be9`.
ZIP je v `output/KOVO-prejimka-a-ucetni-20261023.zip`; není verzovaný v Gitu.
Nezaměnit technické kontroly za výsledky člověka: 43 scénářů je NEPROVEDENO,
účetní ukázka NEPŘEVZATO. Podklady v isolated B1 přibyly jen přes UI (produkty
44/45, varianty 50–52); žádný skladový pohyb ani peníze. Přístupy zůstávají soukromé.
Omezený trenér, rozhodnutí D01–D16 a produkční přechod jsou odložené.
Bez změny aplikačního kódu, push, merge, nasazení a odesílání podkladů.

Následuje předání před přípravou tohoto balíčku:


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 21, "sha256": "ee79a781e15af45187168c75c4709242e9d39be97816e9efc121cbde50f84efa"} -->
**23. 10. 2026 — společné navazující opravy a místní přejímka dokončeny.**
Větev `codex/club-followup-review-20261023`, základ `7aff7884`, izolovaná kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Začít [předáním oprav](CLUB_AUTONOMOUS_FOLLOWUP.md),
[finálními důkazy](verification/autonomous-followup/RESULTS.md)
a [návodem k prohlídce](CLUB_LOCAL_REVIEW.md).
1 545 testů / 31 583 assertions PASS; záloha a nezávislá obnova 326 tabulek,
2 triggerů a 175 soukromých souborů PASS. 136 migrací current.
Nový runtime DB vznikl po izolované obnově jediné nečitelné tabulky;
původní datadir je zachovaný, nejistota její historie je zdokumentovaná.
Nepouštět souběžně starý archivovaný datadir a nemažte jej jako odpad.
Žádný push, merge, deploy ani produkční změna. D01–D16 nadále odložené;
technické ověření není skutečný pilot nebo převzetí účetní.

Následuje historické předání před společnou kontrolou:


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 22, "sha256": "21886804551510092532fedd28cd8ddc2da493a990b8693aa26ca42e303ff669"} -->
**23. 10. 2026 — místní implementace a technická příprava R38–R44 dokončena.**
Větev `codex/migration-cutover-r38-r44-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ `67121f0b`.
Nejprve číst [společné předání R01–R44](CLUB_IMPLEMENTATION_HANDOFF.md)
a [výsledky](verification/r38-r44/RESULTS.md). Pro uživatele je připravený
[návod k místní kontrole](CLUB_LOCAL_REVIEW.md) a dva izolované účty.
Finální regrese: 1 526 testů / 31 327 assertions, 132 HTTP kontrol,
326 obnovených tabulek a 157 soukromých souborů. Katalog 136 migrací current;
kontrakt záloh `2026-10-23.3`.
**Žádný push, merge, deploy, reálný import ani produkční zápis.**
Produkční brána zůstává zavřená; skutečný pilot, převzetí účetní a přepnutí
neproběhly. Rozhodnutí D01–D16 jsou odložená, nikoli automaticky schválená.
Původní checkout `C:\xampp\htdocs\evidencePavel` má cizí WIP a neupravoval se.

Následuje historické předání předchozího balíku:

Lokální R31–R37 dokončené v pracovní kopii
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/club-administration-r31-r37-20261023`, základ `4033bf18`.
[Rozsah](CLUB_ADMINISTRATION_R31_R37_IMPLEMENTATION.md),
[výsledky](verification/r31-r37/RESULTS.md).
133 migrací tohoto vydání; zálohovací kontrakt `2026-10-23.2`.
Pouze syntetický localhost, žádný push ani produkční změna.
Uživatel požaduje autonomně pokračovat: navazují R38–R44 technická příprava
migrace a přechodu. Reálná rozhodnutí jsou v
[registru k pozdějšímu potvrzení](CLUB_COMPLETION_DECISIONS.md).
Pilot, účetní převzetí a nasazení nejsou místními testy nahrazeny.

Následuje předchozí předání:


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 23, "sha256": "83b8dba04bea43d63ced63669568ef5acea6fdc81e34acc8c5ccb3b700246812"} -->
Lokální vývoj 23. 10. 2026: **R25–R30 — členská a sportovní agenda**,
větev `codex/membership-sports-r25-r30-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ R18–R24
`12f28b611a8781968ca6b73821c0df3e2e4075dc`.
Nejprve číst [předání R25–R30](MEMBERSHIP_SPORTS_R25_R30_IMPLEMENTATION.md)
a [ověření](verification/r25-r30/RESULTS.md).
**Bez push, merge, nasazení a produkčních změn; pouze izolované syntetické prostředí.**
Migrace 130 položek, zálohovací kontrakt `2026-10-23.1`.
Osobní přijetí podporuje doloženého plátce bez účtu. Sportovní skutečnost je
verzovaná; zdravotní skeny čte jen registrář. Reálné politiky a údaje nebyly
odhadnuty ani importovány, automatické úlohy nebyly aktivovány.
Navazují R31–R34; úplný účetní export R36 a ostrá migrace/pilot později.

Následuje historické předání předchozích lokálních balíků:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 25, "sha256": "d64df785229347b90675bff11097fefb2d40c0dc7f529b5680faab52bb0201c6"} -->
Lokální vývoj 22. 10. 2026: **R18–R24 — sklady, prodej a vratky**, větev
`codex/inventory-r18-r24-20261022`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ R13–R17
`fd402b6d9e12a86d932f376c8eaff76877d9a029`.
Předání: [implementace R18–R24](INVENTORY_R18_R24_IMPLEMENTATION.md),
[důkazy přejímky](verification/r18-r24/RESULTS.md).
**Pouze izolované syntetické prostředí; bez push, merge, nasazení a produkčních změn.**
Fyzické počátky, FIFO a účetní údaje pro skutečný provoz se nepřebíraly.
Úplnost účetního předání dokončí R36; navazuje R25–R30.

Následuje historické předání předchozího lokálního balíku:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 26, "sha256": "55d969a4be5e868cca54b89602aedf819944f0557d9a3608a14f9a5791be939a"} -->
Lokální vývoj 22. 10. 2026: na B1 a R07–R12 navazuje **R13–R17** ve větvi
`codex/finance-r13-r17-20261022`. Peněžní evidence, doklady/PDF, účetní
členění, vratky a první export jsou popsány v
[předání finančního základu](FINANCE_R13_R17_IMPLEMENTATION.md).
Výsledky ověření jsou v [protokolu R13–R17](verification/r13-r17/RESULTS.md).
**Pouze izolovaný lokální vývoj; bez push, merge, nasazení a produkčních změn.**
Export je pracovní rozsah R13–R17; úplné účetní předání včetně skladu,
výdajů a odměn dokončí R36. Metodiku a reálnou konfiguraci schvaluje účetní.

Následuje předchozí dokončený lokální balík:


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 27, "sha256": "451d390a5ffc5f726ba376d8f067c630dd77af14b489f999691f8e2808dcb2f5"} -->
Lokální vývoj 22. 10. 2026: na B1 navazuje balík **R07–R12** ve větvi
`codex/club-foundation-r07-r12-20261022`. Identita, oddělené členství, trenérské
rozsahy, papírové podklady, bezpečné fronty a provozní případy jsou popsány v
[předání R07–R12](CLUB_FOUNDATION_R07_R12.md). Lokální implementace a přejímka jsou
dokončené: 1 294 testů, 110 HTTP scénářů, souběhy a obnova syntetické DB.
**Bez push, merge, nasazení nebo aktivace produkčních služeb.**
Výsledky společné přejímky jsou v [protokolu](verification/r07-r12/RESULTS.md).

Následující produkční údaje jsou dříve zaznamenaný stav, v tomto lokálním úkolu
se znovu neověřovaly:

Vydání 21. 10. 2026: číselné řady RR N KK XXXXX a účetní kategorie

Historický produkční záznam převzatý z origin/main při přípravě vydání:

22. 10. 2026: kontrola účetního zařazení před zveřejněním produktu je na
produkci z [PR #164](https://github.com/KovoPraha/evidenceTreninku/pull/164),
SHA `cc9c9280f137465eb59f20523d827b5502416e20`,
[deploy 39779478133](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779478133)
úspěšný. PR i aplikační main: 1 214 testů / 26 960 kontrol, MariaDB 10.3/11.4
a CodeQL zelené. Záloha 246 tabulek / 2 triggerů, migrace aktuální,
`pending=[]`, HTTP 200. Osm původních VS nezměněno (`unchanged=true`), obě
tabulky stálých VS zůstaly prázdné. Živé GET ověření ukázalo blokaci
Nezařazeno bez publikačního formuláře u #274 a #243; data, ceny, sklady
a dosavadní stav zveřejnění všech tří odložených produktů jsou shodné.
Bez migrace a automatické datové opravy, false/false. Podrobnosti v
[produkčním reportu](SHOP_PUBLICATION_ACCOUNTING_RELEASE_2026-10-22.md).
Následující časové záznamy zachycují starší vydání.

Nejnovější vydání 21. 10. 2026: číselné řady RR N KK XXXXX a účetní kategorie
z [PR #160](https://github.com/KovoPraha/evidenceTreninku/pull/160) jsou na produkci.
Aplikační SHA `1441683ae1ae2bb9da16d8be3af62ce40f35385c`,
[deploy 39609254832](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39609254832)
je zelený: záloha 238 tabulek / 2 triggerů, 111 migrací aktuálních, porovnání
dosavadních VS `unchanged=true`, aktivace a HTTP smoke 200. Osm starých plateb
zachováno, obě tabulky stálých příspěvkových VS zatím prázdné. CI výsledného
main, MariaDB 10.3/11.4 i CodeQL prošly; 1 193 testů / 26 739 kontrol.
Hospodář má správu řad, kategorií, mapování a čítačů; smíšené košíky mají 9/00,
nezařazené 8/00. Živá prohlídka bez ukládání prošla, původní VS objednávky
`0000000013` nezměněn. Fakturace a účetní export nejsou součástí vydání,
`uat_schvaleno=false`, `importovat_krouzky=false`. Podrobnosti a hranice jsou
v [produkčním reportu](SHOP_NUMBER_SERIES_RELEASE_2026-10-21.md).
Následující záznamy jsou historická vydání.

Nejnovější vydání 21. 10. 2026: [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156)
opravil výdejové akce Zásilkovny a oddělil skutečné systémové stavy od vlastních
zákaznických upřesnění. Správa stavů nyní obsahuje editor šesti existujících
systémových e-mailů, syntetický náhled, historii a zmrazený obsah/verzi ve frontě.
Nasazeno SHA `574328421fff748b48f7d744a3ce454b9b50dd1e`,
[deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
prošel zálohou 236 tabulek / 2 triggerů, 110 migracemi bez čekajících kroků a
HTTP 200. PHPUnit 1 178/26 530, MariaDB 10.3/11.4 a CodeQL zelené. Živě ověřen
přehled stavů, editor/náhled bez zápisu a fronta; starší zpráva nezměněna,
3 pending / 0 pokusů / 0 sent. Simulace Zásilkovny vypnuta; ostrá doprava
neaktivní do zadání ceny, veřejného klíče a povolení. Fakturace je odložená,
`uat_schvaleno=false`, `importovat_krouzky=false`. Podrobnosti a hranice v
[produkčním reportu](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md).
Následující záznamy jsou historická vydání.

Navazující produkční ověření 21. 10. 2026: **PASS** pro celý TEST cyklus
převod → hotovost → příjem → storno → vratka. Nová TEST objednávka
`KP2610218B7C69C006` za 1 Kč je Stornováno/Vráceno, TEST produkt je stažený
a sklad se vrátil na původní 1 kus. Skutečné peníze ani objednávky se neměnily.
[Protokol a důkazy](ORDER_CASH_REFUND_PRODUCTION_2026-10-21.md) zachycují
audit, přesný rozsah, úklid a vztah k souběžnému navazujícímu deployi.
Scénář je uzavřený, žádná TEST vratka již nečeká na dokončení. Dokumentace
testu je v [PR #157](https://github.com/KovoPraha/evidenceTreninku/pull/157),
commit `a0e2bac1`, s úspěšnými PHPUnit, MariaDB 10.3/11.4 a CodeQL.
[Provozní postup příjmu a vratky](ORDER_PAYMENTS_2026-10-21.md) je dostupný
také z [rozcestníku dokumentace](README.md). Plné UAT tím není schválené.

Nejnovější doplnění: 21. 10. 2026. [PR #152](https://github.com/KovoPraha/evidenceTreninku/pull/152)
přidal možnost změnit **název registračního dokumentu**, i bez změny jeho těla.
Koncept a zveřejněná verze uchovávají název spolu s textem; přihlášky a historie
zobrazují název příslušné verze. Registrář má nové pole přímo v editoru.
Nasazený aplikační SHA `f5d6276838239ace6426aa8c326c01a213730a36` má úspěšný
[deploy 39593524611](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39593524611),
zálohu 234 tabulek / dvou triggerů, 108 migrací bez čekajících kroků a živé
ověření nového pole. PHPUnit, MariaDB 10.3/11.4 i CodeQL prošly; produkční
texty se při ověření neměnily. Podrobnosti jsou v
[reportu názvů](REGISTRATION_TEXT_TITLES_2026-10-21.md). Plné UAT zůstává otevřené.
Následující release jsou historické body.

Nejnovější produkční doplnění: 21. 10. 2026, hotovostní úhrady objednávek z
[PR #150](https://github.com/KovoPraha/evidenceTreninku/pull/150).
Aplikační SHA `f813264b16ffd49224bd41a908adc33d9687af88` má úspěšný
[deploy 39590481900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590481900):
ověřená záloha, 107 aktuálních migrací, aktivace a HTTP 200. Hospodář může
společně změnit převod na hotovost a zaevidovat příjem celé částky; součástí
je audit, navazující aktivace, zobrazení způsobu úhrady a ruční vratka.
CI: 1 155 testů / 26 040 kontrol, MariaDB 10.3/11.4 a CodeQL.
[Postup a ověřovací hranice](ORDER_PAYMENTS_2026-10-21.md) obsahují dokončené
přihlášené ověření formuláře z 21. 10.: přechod od objednávky, volbu hotovosti,
povinná pole, CSRF, vizuální kontrolu a konzoli bez chyb. Formulář platby nebyl
odeslán a následná kontrola potvrdila nezměněnou objednávku. Kontrola proběhla
na navazujícím release `f5d6276`. Plné UAT aplikace zůstává samostatné.
Následující produkční záznamy jsou historické.

Nejnovější doplnění: 21. 10. 2026. PR [#148](https://github.com/KovoPraha/evidenceTreninku/pull/148)
přidal **Registrační texty a souhlasy** pod pozici **Registrář členů a KIS**.
Samostatně se ukládá neměnný koncept a zveřejňuje jeho přesné znění; historie
obsahuje předchozí verze, autora a důvod. Nasazený aplikační commit je
`3c64dc530fa8025854f2cc8be07e74bdc1a51aab`,
[deploy 39588673226](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39588673226)
prošel zálohou 233 tabulek / dvou triggerů, 107 migracemi bez čekajících kroků
a HTTP smoke. CI ověřilo PHPUnit i MariaDB 10.3/11.4; živý browser potvrdil
menu a všech pět typů textů. Dosavadní schválená znění a staré přihláškové
snapshoty se neměnily. Návod a přesné důkazy jsou v
[reportu registračních textů](REGISTRATION_TEXTS_ADMIN_2026-10-21.md).
Plné UAT zůstává otevřené. Následující release jsou historické body.

Nejnovější produkční oprava: 21. 10. 2026. PR
[#146](https://github.com/KovoPraha/evidenceTreninku/pull/146) opravil chybějící
načtení bankovního nastavení v rychlé přihlášce a rychlém nákupu bez účtu.
Nasazený aplikační SHA je `e935950ed5be7181687dadba4b1fc2daf8dd5095`;
[deploy 39585582515](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585582515)
dokončil ověřenou zálohu 233 tabulek, kontrolu 106 migrací bez čekajících kroků,
aktivaci a HTTP smoke. [CI main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585403375)
ověřilo PHPUnit a MariaDB 10.3/11.4, CodeQL také prošel. Celá testovací sada
včetně dvou nových regresních testů: 1 140 testů / 25 706 kontrol.
Po nasazení byla rychlá přihláška Aligátoři (`product_id=247&variant_id=813`)
ověřena v prohlížeči; přihláška, detail produktu, e-shop a homepage vracejí
HTTP 200 bez PHP fatální chyby. Ověření nevytvářelo produkční přihlášku ani
objednávku. Nasazení má `uat_schvaleno=false` a `importovat_krouzky=false`.
Podrobnosti jsou v [aktuálním předání](HANDOFF_CURRENT.md); následující
produkční záznamy zachycují historické body.

Nejnovější doplnění administrace: 20. 10. 2026. PR [#144](https://github.com/KovoPraha/evidenceTreninku/pull/144) sjednotil zbývající starší formuláře, ukládací tlačítka, navazující karty a opravil vnořené akce hlavičky i ztrátu vyplněného tréninku při chybějící soupisce. Release `246dee2a4df5dc3152c1a769a9af128b87a91092` má úspěšný [deploy 39496342888](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496342888) a nezávislé potvrzení SHA v produkčním prohlížeči. Lokálně prošlo 1 138 testů / 25 708 kontrol, 105 adres v osmi rolích a konkrétní syntetické zápisy; produkčně osm formulářových stránek a přepnutí role z podadresáře. Přesný rozsah a omezení popisuje [report formulářů](REMAINING_ADMIN_FORMS_2026-10-20.md). Plné UAT zůstává otevřené. Následující release jsou historické body.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 24, "sha256": "991e1252249b9832cf0f2a3e01fb0b6922331ebeaabe0018411da0a0673a952c"} -->
Lokální vývoj 22. 10. 2026: **R18–R24 — sklady, prodej a vratky**, větev
`codex/inventory-r18-r24-20261022`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ R13–R17
`fd402b6d9e12a86d932f376c8eaff76877d9a029`.
Předání: [implementace R18–R24](INVENTORY_R18_R24_IMPLEMENTATION.md),
[důkazy přejímky](verification/r18-r24/RESULTS.md).
**Pouze izolované syntetické prostředí; bez push, merge, nasazení a produkčních změn.**
Fyzické počátky, FIFO a účetní údaje pro skutečný provoz se nepřebíraly.
Úplnost účetního předání dokončí R36; navazuje R25–R30.

Následuje historické předání předchozího lokálního balíku:

Aktuální úkol 22. 10. 2026: **R13–R17**, izolovaná pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/finance-r13-r17-20261022`, základ `0e23e9e766079ce658bbc7687fed7f19b53779de`.
Implementace a ověření se subagenty autorizovány, produkční změny a nasazení
výslovně zakázány. Žádný push/PR/merge/deploy tohoto balíku.
Nejprve přečíst [předání R13–R17](FINANCE_R13_R17_IMPLEMENTATION.md)
a [výsledky přejímky](verification/r13-r17/RESULTS.md). Dále podle plánu
R18–R24 (sklady/prodej); úplné měsíční předání uzavírá R36.
Syntetické účetní nastavení není schválením skutečných údajů a číselných řad.

Následuje předchozí lokální úkol a dále historický produkční kontext:

Aktuální úkol 22. 10. 2026: balík **R07–R12** v izolované pracovní kopii
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/club-foundation-r07-r12-20261022`, základ B1 `2ccd30e27fa209302db4d1e17d4eb411a4c87cca`.
Vlastník schválil implementaci a ověření se subagenty; výslovně **zakázal změny
produkce a nasazení**. Žádný push/PR/merge/deploy tohoto balíku není proveden.
Před pokračováním přečíst [předání balíku](CLUB_FOUNDATION_R07_R12.md)
a [výsledky přejímky](verification/r07-r12/RESULTS.md). Další realizační balík:
R13–R17. Reálné trenérské rozsahy ani historické identity se nemigrovaly.

Následující odstavce jsou kontext předchozího úkolu; nejsou autorizací k nasazení
R07–R12:

Předchozí autorizace 22. 10. 2026: vlastník výslovně schválil push kontroly
účetního zařazení před publikací, PR, merge po zelených kontrolách a chráněné
produkční nasazení. Ověření blokace pouze čtením; produkty #274, #243, #245
a existující/stálé VS zachovat. Nasazení tohoto řezu se teprve připravuje.

22. 10. 2026: schválené účetní mapování uloženo v produkční administraci
(38 auditovaných změn); produkty #274, #243, #245 a kontrolované původní VS
zachovány. Následná nová kontrola účetního zařazení před zveřejněním produktu
je implementována lokálně v `evidencePavel-publication-accounting-20261022`
na větvi `codex/shop-publication-accounting-guard-20261022`; zatím není nasazená.
Ověření: 1 214 testů / 26 960 kontrol, PHP lint, `git diff --check` a lokální
MariaDB smoke číslování včetně nové publikační kontroly prošly.
Podrobnosti, rozsah ověření a omezení jsou v
[plánu kontroly publikace](SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md).

Historický produkční záznam převzatý z origin/main při přípravě vydání:

22. 10. 2026 nasazena kontrola účetního zařazení před zveřejněním produktu:
[PR #164](https://github.com/KovoPraha/evidenceTreninku/pull/164), aplikační SHA
`cc9c9280f137465eb59f20523d827b5502416e20`, úspěšný
[deploy 39779478133](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779478133).
PR i aplikační main mají zelené PHPUnit (1 214 testů / 26 960 kontrol),
MariaDB 10.3/11.4 a CodeQL. Záloha 246 tabulek / 2 triggerů, migrace aktuální,
`pending=[]`, HTTP 200, `uat_schvaleno=false`, `importovat_krouzky=false`.
Automatické porovnání VS `unchanged=true`: osm původních plateb nezměněno,
obě tabulky stálých VS zůstaly prázdné. Živě pouze čtením ověřena blokace
Nezařazeno u #274 a #243 a shoda katalogových i hlavních údajů, cen a skladů
#274, #243 a #245 před/po. Žádný produkt ani platba se při kontrole neuložily.
Předchozí schválené účetní mapování (38 auditovaných změn) zůstává zachováno.
Neprovádět automatickou opravu ani deaktivaci tří odložených produktů,
nepřepisovat existující VS. [Produkční report](SHOP_PUBLICATION_ACCOUNTING_RELEASE_2026-10-22.md)
a [implementace](SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md) popisují
důkazy i omezení. Dokumentační dokončení je na větvi
`codex/shop-publication-release-proof-20261022`, bez dalšího deploye.
Následující datované záznamy popisují starší řezy.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 25, "sha256": "be28b96b59c37be787d7e5bb9b2642ba456f416d6b2f1634137d2f5dc4c7570c"} -->
## Autorizované vydání Zásilkovny — 22. 10. 2026

Vlastník schválil commit, push a nasazení připravených úprav. Dopravu i
simulaci ponechat vypnutou, neodesílat e-maily ani zásilky, fakturace odložená.
[Aktuální release checklist](PACKETA_RELEASE_2026-10-22.md) má přednost před
staršími zákazy vzdálených kroků a historickými průběžnými výsledky níže.
PR #162 sloučen na `b6b4251548fc6a99a4c654dc00a70589cc8d2386`;
CI 39776280900 (PHPUnit, MariaDB 10.3/11.4) a CodeQL 39776275878 zelené.
Deploy 39776493246 úspěšně nasadil tento SHA, oba volitelné příznaky false.
Lokálně 1 198/26 816 PASS, záloha/obnova 198 tabulek PASS, 112 migrací.
Záloha 246 tabulek / 2 triggerů, 112 aktuálních migrací, původní VS
nezměněny (osm plateb), HTTP 200. Produkčně přes UI ověřeny cena **150 Kč**,
**doprava vypnutá, simulace vypnutá, klíč prázdný**. Produkt #296 zůstává
Stažený a má nový checkbox Packety; PPL je samostatné. Objednávky i stavy
se načítají. Fronta před/po shodná: **3 pending / 0 pokusů / 0 sent**,
shodný otisk celé tabulky. Žádné objednání, podání, opakování zprávy ani
worker; původní pozice Finance obnovena. Veřejný klíč zůstal jen lokálně.
Packeta objednávky na produkci nejsou, expediční akce tedy ověřeny testy,
nikoli skutečným podáním. Aktuální produkční protokol je v release checklistu.
Dokumentační větev nyní
`codex/packeta-release-proof-20261022`, žádná změna aplikace v tomto řezu.
Začleněn main `886c07b2` včetně číselných řad a ochrany původních VS;
společný zálohovací kontrakt `2026-10-22.1` zachovává obě nové datové smlouvy.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 26, "sha256": "15fc0ca57971d16e4e49ff04fca14687ebaf3a5ca3361ab41899e49b83d6b92a"} -->
## Veřejný klíč a skutečná mapa — lokální PASS 22. 10. 2026

Po přihlášení vlastníka převzat jen veřejný klíč widgetu do lokálního KIS,
uložen přes UI (audit #3); API heslo se nepřenášelo. In-app browser načetl
skutečnou mapu CZ Z-BOXů a callback vybral **22312, Nad Kapličkou 3324/15
(TJ Kovo Praha)** do guest checkoutu 5/5; součet 125 + 150 = 275 Kč.
Serverová aktuální validace stejného boxu PASS, pobočka 39209 a syntetické
ID 999000001 správně odmítnuty. Po zkoušce UI dopravu vypnulo (audit #4).
Konečné lokální nastavení **enabled=0, cena=15000, délka klíče=16,
SHOP_PACKETA_LOCAL_DEMO=false**. Fronta nezměněna: 5 pending / 0 pokusů,
2 objednávky, auth/roster fronty a email_log 0. Nic objednáno, odesláno,
commitnuto, pushnuto ani nasazeno. První mapa se načítala několik minut;
starý nepoužívaný celý v5 feed timeout 12 s, bodová validace prošla.
[Výsledek a důkazy](PACKETA_MODULE_AUDIT_2026-10-21.md#propojení-veřejného-klíče-a-skutečná-mapa--dokončeno-22-10-2026).
Zbývá ověřit obchodní připravenost účtu, skutečný štítek/podání/doručení
a budoucí schválené nasazení/aktivaci. Fakturace nadále odložena.

## Živý účet Packeta — nastavení po přihlášení vlastníka

Účet již existuje. Uložen kontakt Marek Mixa (+420724116963,
`mixa@kovopraha.cz`), firemní adresa IČO 00541753 / Nad Kapličkou 3324/15,
100 00 Praha 10 (#718887) a po výslovném potvrzení VOP odesílatel Kovo Praha
(`#577459`, označení `kovopraha`). Povoleny jen CZ Z-BOXy, ostatní země,
pobočky a externí místa vypnuté. Veřejný klíč nyní pouze v lokální DB;
API heslo nebylo kopírováno.
**Vratkové místo dokončeno:** podle výslovné volby vlastníka uložena
Praha 10, Strašnice, Počernická 3492/1a **#39209** místo automatického depa
Olomouc #9936. Úspěšné uložení i přetrvání po novém otevření ověřeny;
důkaz `var/local-status-mail-review/packeta-account-return-39209.png`.
Veřejný klíč a mapa již lokálně ověřeny podle aktuálního výsledku výše.
Žádná zásilka nebyla vytvořena/podána a KIS nebyl nasazen ani zapnut.
[Detaily a důkazy v plánu](PACKETA_MODULE_AUDIT_2026-10-21.md).

## Lokálně připraven jednoduchý ruční provoz Zásilkovny

Rozhodnutí vlastníka a výsledky jsou v
[plánu modulu](PACKETA_MODULE_AUDIT_2026-10-21.md#schválené-zjednodušení-od-vlastníka--21-10-2026).
CZ Z-BOXy, podání pouze přes box, vše vyřizuje Kovo Praha; účet nastaven výše.
Výchozí dopravné 150 Kč měnitelné v administraci; nový samostatný checkbox
u fyzického produktu, kroužky blokované. Lokálně dále opraven widget a
aktuální validace, expediční přehled, oprava/duplicita štítku a potvrzení
storna v Klientu. Žádný API konektor pro založení nebo tracking.
PHPUnit 1 183 / 26 590 PASS; MariaDB backup/restore 190 tabulek PASS,
lokálních 111 migrací aktuálních. UI cena/checkbox uloženy a ověřeny.
Lokálně doprava vypnutá, cena 150 Kč, veřejný klíč uložen, simulace false,
fronta 5 pending / 0 pokusů. Žádný worker/skutečný e-mail či balík.
Změny jsou necommitnuté na `codex/packeta-module-audit-20261021` ve stejném
worktree. Neprovádět nové nasazení/aktivaci; fakturace odložená. Zbývá ověřit
obchodní připravenost účtu, skutečný štítek/podání a poštovní provoz před
pozdější schválenou aktivací.

## Historie auditu a předchozího nasazení

Navazující analýza Zásilkovny 21. 10. 2026 dokončena: plán a výsledky jsou v
[auditu modulu](PACKETA_MODULE_AUDIT_2026-10-21.md), větev
`codex/packeta-module-audit-20261021`, stejný vývojový worktree. Pouze analýza
a dokumentace; neaktivovat dopravu ani skutečné zásilky/e-maily. Výchozí
nasazený kód a doklady o vydání zůstávají níže. Zdrojově zjištěn nesoulad
widgetu `carrierId:czzbox` proti současnému `group:zbox`, chybějící aktuální
validace, expediční přehled Packety, limity balení a náprava štítku/storna.
Audit obsahuje úkoly mimo KIS, oddělení cílového boxu od místa podání,
bezpečný postup pro sdílenou poštovní frontu, API etapu a šest rozhodnutí
vlastníka. Ostrý účet/mapa, CRON ani doručení nebyly v analýze ověřeny.

Nasazeno 21. 10. 2026: číselné řady a účetní kategorie z [PR #160](https://github.com/KovoPraha/evidenceTreninku/pull/160).
Aplikační SHA `1441683ae1ae2bb9da16d8be3af62ce40f35385c`,
[deploy 39609254832](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39609254832)
prošel zálohou 238 tabulek / 2 triggerů, 111 aktuálními migracemi, aktivací a HTTP 200.
Kontrola VS před/po vrátila `unchanged=true`: osm plateb nezměněno; obě
tabulky stálých příspěvkových VS jsou prázdné a zůstaly prázdné. CI výsledného
main i CodeQL zelené; 1 193 testů / 26 739 kontrol a MariaDB 10.3/11.4.
Živě ověřeno finanční menu, sedm řad, náhledy, mapování, audit, ochrana formulářů
a odmítnutí jiné aktivní pozice. Původní VS objednávky `0000000013` zachován,
role Objednávky obnovena. Žádné nastavení, objednávka ani úhrada se při browser
kontrole nezapsaly; mapování z localhostu se nepřenášelo. `uat_schvaleno=false`,
`importovat_krouzky=false`. [Produkční report](SHOP_NUMBER_SERIES_RELEASE_2026-10-21.md)
a [návod](SHOP_NUMBER_SERIES_2026-10-21.md) jsou aktuální. Tento navazující
dokumentační řez nepřidává aplikační změny; následující lokální stavy jsou historie.

Aktuální autorizace 21. 10. 2026 pro číselné řady: vlastník výslovně schválil
push, PR, merge po zelených kontrolách a chráněné produkční nasazení. Starší
lokální omezení tohoto řezu níže je historické. Zachovat všechny existující
VS a příspěvkové rezervace; produkční ověření administrace pouze čtením.
Podrobný průběh vydání je v [plánu číselných řad](SHOP_NUMBER_SERIES_2026-10-21.md).


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 27, "sha256": "12e659d2b66473f008dea04a44cfb68247499b42458374ff296cb61b8c7b4537"} -->
Lokální práce 21. 10. 2026: číselné řady e-shopu RR N KK XXXXX a účetní
kategorie na větvi `codex/shop-number-series-20261021`, worktree
`C:\xampp\htdocs\evidencePavel-number-series-20261021`.
[Plán a stav](SHOP_NUMBER_SERIES_2026-10-21.md) je autoritou pro tento řez.
Vlastník schválil implementaci bez produkčního nasazení; staré VS a stálé VS
příspěvků zachovat. Následující autorizace jiných řezů se na tuto práci nevztahují.
Lokálně dokončeno: administrace v agendě Hospodář a platby, účetní číselník,
mapování s děděním, smíšená řada 9/00, nezařazené 8/00, všechny tři nákupní
cesty a neměnné zařazení položek. Závěrem 1 190 testů / 26 730 kontrol, nový
MariaDB souběh a původní checkout smoke prošly; záloha/obnova 198 tabulek.
Browser ověřil oprávnění, uložení čítače a mapování i audit bez konzolových chyb.
Bez push, merge a nasazení. Lokální náhled je `http://localhost:8921/`;
podrobnosti, limity a historické migrační varování jsou v odkazovaném plánu.

Aktuální autorizace 21. 10. 2026: vlastník schválil commit, push a produkční
nasazení stavů/Zásilkovny/systémových e-mailů. Starší zákaz nasazení níže je
historický. Platí zákaz skutečné pošty při ověřování a odložení fakturace.
Simulace v lokálním configu vypnuta. Začleněno `61ed7385`, kontrakt `.4`,
nové testy 1 178/26 530 a MariaDB záloha/obnova 190 tabulek prošly.
[Aktuální kroky vydání](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md#aktuální-autorizace-vydání-21-10-2026)
jsou nyní autoritou pro tento řez; pokračovat push/PR/CI a chráněným deployem.
Nasazeno 21. 10. 2026: stavy, oprava akcí Zásilkovny a verzovaný editor šesti
systémových e-mailů z [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156).
Aplikační SHA `574328421fff748b48f7d744a3ce454b9b50dd1e`,
[deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
je zelený: záloha 236 tabulek / 2 triggerů, 110 aktuálních migrací a HTTP 200.
PHPUnit 1 178/26 530, CI MariaDB 10.3/11.4 a CodeQL prošly. Živě ověřeny
systémové stavy, editor a syntetický náhled bez uložení a odeslání, seznam
objednávek a fronta. Starší zpráva zachovala obsah; konečná fronta 3 pending,
0 pokusů a 0 sent. Dvě další zprávy jsou z nezávislého testu před aktivací.
Simulace Zásilkovny vypnuta; ostrá doprava také zůstává neaktivní, protože
chybí cena a veřejný klíč. Žádné objednávky ani produkční šablony jsme při
této kontrole neměnili, worker nespouštěli. Fakturace je odložená.
[Plán](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md) a
[produkční report](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md) jsou aktuální
autoritou; starší zákazy push/deploy a průběžné počty níže jsou historie.
Dokumentační větev `codex/order-status-release-proof-20261021` nepřidává
aplikační změny a nevyžaduje nové nasazení; vývojový worktree zůstává stejný.

Navazující TEST ověření 21. 10. 2026: produkční cyklus změny převodu na
hotovost, příjmu, storna a samostatné vratky byl dokončen na nové objednávce
`KP2610218B7C69C006` za 1 Kč. Konečný stav Stornováno/Vráceno, způsob Hotově,
TEST reference a audit zachovány. Nový TEST produkt #296 / varianta #950
zůstává stažený, sklad 1 → 0 → 1. Skutečné objednávky ani peníze se neměnily;
původní pracovní pozice byla obnovena. [Přesný protokol](ORDER_CASH_REFUND_PRODUCTION_2026-10-21.md)
rozlišuje tuto simulaci od plného UAT a ověření skutečného převodu.

Platební scénář je uzavřený; nečeká na další potvrzení ani vratku. Protokol
byl začleněn v [PR #157](https://github.com/KovoPraha/evidenceTreninku/pull/157),
commit `a0e2bac12cf6818120f2dd5515499bfe5d7dc601`; PHPUnit, MariaDB 10.3/11.4
a CodeQL prošly. [Provozní návod](ORDER_PAYMENTS_2026-10-21.md) obsahuje příjem
i samostatnou vratku. TEST záznamy ponechat pro audit. Tato dokumentační
aktualizace nevyžaduje nový produkční deploy; plné UAT zůstává samostatné.



<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 28, "sha256": "8090b5bd27660a6c4588b746f7e9f1df687237f97b8f645a2525330f337b27f3"} -->
Lokální výsledek 21. 10. 2026: na větvi
`codex/order-status-system-mail-20261021` je připravena oprava Zásilkovny,
oddělené zobrazení systémového a zákaznického stavu a editor šesti existujících
systémových e-mailů s náhledem, historií a verzí uloženou ve frontě.
[Plán a výsledky](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md#úplný-lokální-browser-průchod-21-10-2026)
obsahují dokončený browser nákup/výdej osobního odběru i Z-BOXu, lokální B09
PASS a přesnou kontrolu pěti zpráv ve frontě (5 pending, 0 pokusů, 0 odesláno).
Sklad 10→9→7, oba koncové stavy, odstranění podacího kódu a zachování starší
zprávy po editaci šablony ověřeny. Nová úplná PHPUnit sada: 1 169/26 302, OK;
předchozí MariaDB souběh editorů, záloha/obnova 188 tabulek a 109 migrací platí.
Výběr boxu, štítek, platby a fyzické předání byly syntetické lokální simulace;
reálný dopravce ani doručení pošty tím nejsou ověřeny. Režim Z-BOX demo vyžaduje
výslovný příznak v ignorovaném configu a striktní loopback všech adres.
Pracovní složka: `C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Žádný push, merge, deploy ani skutečná rozesílka. Worker nespouštěn; čekající
testovací zprávy nepředávat skutečnému mail transportu. Fakturace je odložená.
Implementační commity jsou `55f43e68` a `690fefd8`. Plán eviduje i navazující
dokumentační PR #154 na `origin/main`, který zatím není do pracovní větve převzat.

## Pravidlo pro větší změny a pokračování po kompaktaci

Na výslovný pokyn vlastníka z 21. 10. 2026 ukládej před větší implementací
konkrétní plán do samostatného souboru v `docs/` a odkazuj na něj z tohoto
handoffu. Průběžně zapisuj rozsah autorizace, pracovní větev/složku,
dokončené kroky, výsledky testů, zbývající práci a přesný další krok.
Před kompaktací nebo předáním stav obnov; chat nesmí být jediným úložištěm
plánu. Po pokračování nejdřív přečti plán a aktuální stav souborů.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 29, "sha256": "33fd314bbafd95b71ba03b51033d20cd6a7dfbb55cebe55085af7673b5bb26d5"} -->
Lokální práce 21. 10. 2026: oprava Zásilkovny, přehled stavů a editor
systémových e-mailů se připravují na větvi
`codex/order-status-system-mail-20261021` v odděleném worktree
`C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Podrobný rozsah, průběžné úkoly a odložená rozhodnutí jsou v
[plánu stavů a e-mailů](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md).
Vlastník schválil pouze lokální přípravu a testování; žádný push, merge,
deploy ani skutečná rozesílka. Fakturace je výslovně odložená.



<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 30, "sha256": "e2da765cd113ea5336320473b1963bbc62a7723603e0312633f989d1181194de"} -->
Nejnovější předání: 21. 10. 2026, názvy registračních textů z
[PR #152](https://github.com/KovoPraha/evidenceTreninku/pull/152). Nasazený aplikační
SHA je `f5d6276838239ace6426aa8c326c01a213730a36`,
[deploy 39593524611](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39593524611)
prošel zálohou 234 tabulek / dvou triggerů, 108 aktuálními migracemi a HTTP smoke.
Registrář může upravit **Název souhlasu / informace** a zveřejnit i změnu samotného
názvu. Název se verzovaně ukládá s textem; nové nadpisy používají přihlášky
i historie. Původní potvrzení zachovávají vazbu na původní dokument a název.
Živě bylo ověřeno nové pole na `registration_texts_admin.php?purpose=member_data_notice`;
produkční názvy a texty se při kontrole neměnily. Podrobnosti a návod jsou v
[reportu názvů souhlasů](REGISTRATION_TEXT_TITLES_2026-10-21.md). Plné UAT zůstává otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 31, "sha256": "ff7b8b44374656e7ac2ec12bd542527b5a48aa5c324dff01e9404055b65848e7"} -->
Nejnovější předání: 21. 10. 2026, hotovostní platby objednávek z [PR #150](https://github.com/KovoPraha/evidenceTreninku/pull/150).
Nasazený aplikační SHA je `f813264b16ffd49224bd41a908adc33d9687af88`;
[deploy 39590481900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590481900)
prošel ověřenou zálohou, 107 aktuálními migracemi, aktivací a HTTP 200.
Hospodář může současně změnit převod na hotovost a přijmout celou platbu;
objednávka má audit, navazující aktivace a podporuje ruční vratku.
CI: 1 155 testů / 26 040 kontrol, MariaDB 10.3/11.4 a CodeQL.
Postup a hranice ověření jsou v [předání plateb](ORDER_PAYMENTS_2026-10-21.md).
Přihlášená kontrola formuláře byla 21. 10. dokončena: role, přechod na správnou
platbu, volba hotovosti, povinná pole, CSRF a čistá konzole. Platební formulář
nebyl odeslán; následná kontrola potvrdila nezměněný řádek objednávky.
Podrobné ověření proběhlo na navazujícím release `f5d6276`; původní pracovní
pozice byla obnovena. Skutečné ani testovací platby nebyly potvrzovány.
`uat_schvaleno=false`, `importovat_krouzky=false`. Následující předání jsou historická.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 32, "sha256": "afba18c5210d17eed7e9b010b125fcd0b9c15a1fb8824f0ef5d1207aec24c494"} -->
Nejnovější předání: 21. 10. 2026, správa registračních textů a souhlasů z PR
[#148](https://github.com/KovoPraha/evidenceTreninku/pull/148). Nasazený aplikační
SHA je `3c64dc530fa8025854f2cc8be07e74bdc1a51aab`,
[deploy 39588673226](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39588673226)
prošel zálohou, 107 aktuálními migracemi a HTTP smoke. Registrář má v běžném
menu **Členové → Registrační texty a souhlasy**: neměnné koncepty, samostatné
zveřejnění, historie a audit autora/času/důvodu. Dosavadní přihláškové snapshoty
i schválené veřejné texty zůstaly zachované. Živý prohlížeč načetl všech pět
typů textů; publikační zápisy se ověřily v odděleném localhostu. Podrobný
[report a návod](REGISTRATION_TEXTS_ADMIN_2026-10-21.md) obsahuje CI, produkční
důkazy a hranice ověření. Plné UAT aplikace zůstává otevřené.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 33, "sha256": "d74aef5730bbb798a30b92d8e0a651f4c37c4323d3eb198f6c1535a313c1089c"} -->
Nejnovější předání: 21. 10. 2026, oprava rychlé přihlášky z PR
[#146](https://github.com/KovoPraha/evidenceTreninku/pull/146). Nasazený aplikační
SHA je `e935950ed5be7181687dadba4b1fc2daf8dd5095`,
[deploy 39585582515](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585582515)
prošel. Formulář `booking/rychla_prihlaska.php` volal
`shopBankSettingsEffective()` bez načtení její definice a po odeslání zobrazil
obecnou chybu ukládání. Explicitní načtení `includes/shop_bank_settings.php`
je doplněno do rychlé přihlášky i rychlého nákupu bez účtu.
Oba nové testy samostatného načtení před opravou selhaly; po opravě prošlo
1 140 testů / 25 706 kontrol, MariaDB 10.3/11.4 a CodeQL.
[CI výsledného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585403375)
je zelené. Deploy ověřil zálohu 233 tabulek, 106 migrací bez čekajících kroků,
aktivaci a HTTP smoke. Po aktivaci byla přihláška Aligátoři
`product_id=247&variant_id=813` načtena v prohlížeči; přihláška, detail produktu,
e-shop a homepage vracejí HTTP 200 bez PHP fatální chyby. Produkční přihláška
ani objednávka nebyla v rámci ověření vytvořena. `uat_schvaleno=false`,
`importovat_krouzky=false`; další plné UAT zůstává samostatné.
Následující předání jsou historické body.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 28, "sha256": "62f009db0b3ae5243ebd40db75990fedc78952a0e9bdcc6af31ee78fc72892c9"} -->
Produkční doplnění administrace: 20. 10. 2026. Sjednocený vzhled a správa produktů, importovaných variant, cen a skladu z PR #140–142 jsou nasazené v release `175f92c7a59294acff7e15fe09cbed258707fa2e`; [deploy 39483597831](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483597831) prošel. Chrome ověřil přidání a editaci skryté varianty importovaného zboží, ceny, sklad 0 → 2 → 0 s auditem, archivaci konceptu, veřejné skrytí testovacích dat, mobilní navigaci i kontrast. CI: 1 137 testů / 25 679 kontrol, MariaDB 10.3/11.4 a CodeQL. Přesné důkazy a stav testovacích dat obsahuje [produkční report](UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md). Nejde o schválení plného UAT ani zapnutí plateb, Fio nebo rozesílek. Níže uvedené starší SHA jsou historické body svých částí.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 29, "sha256": "8ef26c7e788ab54484cd9cede4d08921db18a7b98af6a0d2e589880090edad33"} -->
Produkční doplnění: 20. 10. 2026, Europe/Prague, po nasazení hierarchie soupisek.
PR [#138](https://github.com/KovoPraha/evidenceTreninku/pull/138) byl sloučen jako
`4991c91f966eb65852876fd0084dd6ad041e3b69` a tento přesný SHA nasadil
[běh 39472709660](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472709660).
Povinné PHPUnit a MariaDB 10.3/11.4 kontroly nad `main` prošly v
[CI 39472026706](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026706),
CodeQL v [běhu 39472026146](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026146).
Deploy ověřil zálohu, 106 aktuálních migrací bez čekajících kroků, aktivaci
release, ochranu klíče archivu a HTTP 200. Samostatný živý test potvrdil HTTP
200 na homepage, HTTP 403 na soukromém klíči a načtení nové administrační
stránky v roli Registrář členů a KIS. První pokus o deploy
[39472178866](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472178866)
skončil po úspěšné aktivaci na síťovém timeoutu bezpečnostního HTTP testu;
opakovaný běh nad stejným SHA dokončil všechny kroky.

Nasazený kód zatím sám nepřiřadil soupisky k nadřazeným skupinám ani nezapnul
opakování nabídek či automatické párování plateb. Živá administrace ukázala
43 soupisek bez skupiny; read-only porovnání starých názvů ukázalo
0 přesných shod a 46 položek bez vazby. Jejich rozhodnutí a časově platné
zařazení je další datový krok. Plné produkční UAT tohoto releasu nebylo
schváleno (`uat_schvaleno=false`). Rozsah a předání popisuje
[implementační protokol](ROSTER_HIERARCHY_IMPLEMENTATION_HANDOFF_2026-10-20.md).

Níže uvedené dřívější produkční doplnění je historický stav před PR #138.


<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 30, "sha256": "0e02b3cd8c54fdf87833609dbc41d095848844dea56bf2d26a5038f2665501cf"} -->
Produkční doplnění: 20. 10. 2026, Europe/Prague

Rodičovská cesta z PR #91 je nasazená včetně ověření 94 migrací. Poslední
aplikační release po opravě příslušenství z PR #92 má SHA
`fd6ccc1584beb3a3266e8147497263cb8a38044f`:
[produkční běh 39402251446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402251446)
i [CI 39402250654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402250654)
prošly. Rozsah živého ověření a úplný seznam
52 nepropojených účastnických variant uvádí
[produkční report rodičovské cesty](PARENT_JOURNEY_PRODUCTION_2026-10-20.md).
Podrobnosti implementace a syntetického akceptačního testu zachycuje
[lokální předání](PARENT_JOURNEY_2026-10-20.md).

V prohlížeči prošlo přihlášení testovacího rodiče, zobrazení jeho rodiny
a stránka Moje kroužky. Novou žádost o dítě blokuje chybějící platná informace
o zpracování osobních údajů. Všech 52 kontrolovaných účastnických variant
správně brání nákupu bez návazností. Současně existuje 26 zveřejněných programů
a 74 aktivních nabídek se soupiskami: scházejí vazby importovaných variant,
platný souhlas se storno podmínkami všech 74 nabídek a potvrzení jejich
neomezené kapacity.
Pumptrack má rozporné ceny, Točňáci 1× týdně nevyřešený výběr dne
a Kunratice neúplný čas / rozdělení věkových skupin.

Úplný produkční průchod novou registrací, platbou a zařazením do soupisky
ani doručení e-mailů zatím nejsou ověřené. Fio, Stripe a SumUp se nezapínaly
a skutečné produktové vazby se neměnily. Lokální sada po opravě příslušenství
v PR #92: **1 019 testů / 23 532 kontrol**, bez selhání a chyb.
Po posledním deployi prohlížeč ověřil správné rozlišení Triček / Uskladnění
od kroužku Aligátoři. Veřejná prezentace TEST programu je koncept;
rodičovský katalog jej nenabízí. Pozitivní produkční nákup
nebyl dokončen.

Následující původní stavový přehled je **historický snapshot z 11. 10. 2026**,
včetně SHA, CI a produkčních výsledků. Nepopisuje aktuální změny ani
nepotvrzuje současný produkční stav.

Tento soubor je autoritativní stavový rozcestník. Historické audity, předávací
záznamy a implementační prompty zachycují stav v datu svého vzniku a nesmí se
používat jako důkaz aktuální verze. Před každou novou prací má přednost čerstvý
`git fetch`, větev `origin/main`, aktuální GitHub Actions a skutečný kód.

## GitHub a produkce

- Repozitář: <https://github.com/KovoPraha/evidenceTreninku>
- Výchozí větev: `main`
- Stav vzdálené větve `origin/main` při této kontrole:
  `16b7d4d60883f3f4f655c097339339c68c1be07d`
- Otevřené pull requesty při této kontrole: žádné
- Produkční aplikace: <https://kis.kovopraha.cz/>
- Poslední ověřené nasazení: GitHub Actions běh
  [`37886677875`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875)
  nasadil přesně commit `16b7d4d60883f3f4f655c097339339c68c1be07d`.
- Nasazení vytvořilo a ověřilo databázovou zálohu, připravilo úplný release,
  aplikovalo migrace, aktivovalo kód a dokončilo HTTP smoke test.
- Po nasazení byly z prohlížeče ověřeny domovská stránka, e-shop, veřejný
  rozvrh tréninků, klubový kalendář, velodrom a přihlášení. Žádná z těchto
  cest neobsahovala fatální chybu ani chybu či varování v konzoli.
- Veřejná domovská stránka a bezpečný vstup k veřejnému profilu po nasazení
  odpovídají HTTP 200 a posílají nonce CSP bez povolených inline skriptů.
  Soubor `var/deployment.json` není veřejný a správně odpovídá HTTP 403; důkaz
  release se proto čte z chráněného workflow, nikoli z veřejné URL.

Publikování tohoto dokumentu vytvoří novější dokumentační commit na `main`, ale
nemění nasazený aplikační commit. Přesné aktuální SHA `main` se proto vždy čte
pomocí `git fetch --prune origin` a `git rev-parse origin/main`, nikoli opisem z
dokumentu. Samotný push ani merge do `main` produkci nemění. Produkční nasazení
je vždy samostatný ručně spuštěný workflow podle
[`NASAZENI.md`](NASAZENI.md).

Bezpečnostní nastavení repozitáře bylo 8. 10. 2026 zpřísněno: je zapnuté
secret scanning, push protection, Dependabot alerts i automatické bezpečnostní
aktualizace. GitHub CodeQL default setup běží v rozšířeném režimu pro Actions a
JavaScript/TypeScript; první běh
[`37764312992`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992)
prošel bez otevřeného nálezu. PHP závislosti navíc kontroluje `composer audit`
v hlavním CI jobu.

## Poslední automatické ověření

GitHub Actions běh
[`37886667244`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244)
nad aktuálním `origin/main` a současně nasazeným commitem
`16b7d4d60883f3f4f655c097339339c68c1be07d` prošel v tomto rozsahu:

- PHPUnit na PHP 8.2: **877 testů / 11 995 kontrol**;
- integrační smoke na MariaDB 10.3: úspěch;
- integrační smoke na MariaDB 11.4: úspěch;
- kontrola Composer konfigurace: úspěch.

CodeQL běh
[`37886667406`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406)
nad stejným commitem prošel pro GitHub Actions i JavaScript/TypeScript.

Migrační katalog obsahuje 84 verzovaných PHP migrací včetně bezpečnostních
migrací připravených v pracovní větvi a zmrazený legacy baseline 2.20.2.
Aktuální zálohovací ownership kontrakt zdrojového kódu je `2026-10-08.1`.
Počet souborů není důkazem stavu konkrétní databáze; ten se na každé stanici
ověřuje pomocí `php bin/migrate.php --check --json` s nastaveným `APP_HOST`.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 34, "sha256": "fee6a0cf9da792c064be3b70afe9e45e484a17c8fa9d56cc46da0dc7280b1d57"} -->
Nejnovější předání: 20. 10. 2026, zbývající administrační formuláře z PR [#144](https://github.com/KovoPraha/evidenceTreninku/pull/144). Nasazený aplikační SHA je `246dee2a4df5dc3152c1a769a9af128b87a91092`, [deploy 39496342888](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496342888) i živé ověření prošly. Navazujte podle [reportu formulářů](REMAINING_ADMIN_FORMS_2026-10-20.md): společné styly starších editorů, zelené ukládání, funkční popisky, kořenové cesty hlavičky a zachování rozepsaného tréninku při chybějící soupisce. Lokálně 1 138 testů / 25 708 kontrol; 105 adres v osmi rolích, syntetické zápisy, mobil a tmavý režim. Produkčně osm stránek a přepnutí pracovní pozice z vnořeného formuláře. B30 zůstává PARTIAL, plné UAT není schválené. Následující předání jsou historické body.

Nejnovější doplnění: 20. 10. 2026, sjednocená administrace a správa e-shopu z PR #140–142. Release `175f92c7a59294acff7e15fe09cbed258707fa2e` má úspěšný [deploy 39483597831](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483597831) a produkční browser ověření variant, cen, skladu, archivace konceptu a společného vzhledu. Navazujte podle [produkčního reportu](UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md), který uvádí také ponechanou skrytou testovací variantu se skladem 0 a archivovaný testovací koncept. Plné UAT zůstává samostatně otevřené. Níže uvedené starší SHA jsou historické body svých částí.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 35, "sha256": "c28906879acf5920c3fafc8ba1557fd8f1142da6a1ff3246131f68f310767402"} -->
Produkční doplnění: 20. 10. 2026, Europe/Prague. Hierarchie skupin a soupisek,
časové členství, sjednocené cílení tréninků a akcí, opakované nabídky kroužků
a rozdělení rodinných bankovních plateb jsou nasazené z PR
[#138](https://github.com/KovoPraha/evidenceTreninku/pull/138). Přesný aplikační
SHA je `4991c91f966eb65852876fd0084dd6ad041e3b69`;
[deploy 39472709660](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472709660)
prošel zálohou, 106 migracemi bez čekajících kroků, aktivací, ochranou
soukromého klíče a HTTP smoke testem. [CI 39472026706](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026706)
ověřilo PHPUnit i MariaDB 10.3/11.4. Živá stránka správy hierarchie se načetla
v roli Registrář. První pokus o deploy skončil po aktivaci na síťovém timeoutu
runneru; druhý běh stejného SHA dokončil celý protokol.

**Další krok je datové zařazení, nikoli opakovaný import osob:** 43 živých
soupisek zatím nemá skupinu a read-only mapování starých skupin/podskupin
ukazuje 0 přesných shod a 46 položek bez vazby. V administraci je třeba
založit cílové skupiny, ověřit každé zařazení a použít náhled dopadu;
historické tréninky a přihlášky se nepřepisují. Generátor nových nabídek,
automatické bankovní párování a plné UAT zůstávají vypnuté.
Podrobný postup je v
[předání hierarchie](ROSTER_HIERARCHY_IMPLEMENTATION_HANDOFF_2026-10-20.md).

Následující doplnění je historický stav před PR #138.


<!-- /archive -->

<!-- archive {"source": "docs/HANDOFF_CURRENT.md", "part": 36, "sha256": "f8f08b4d60601002ca734c052d0ecec9003652c0d863230ebd460fb633f55261"} -->
Produkční doplnění: 20. 10. 2026, Europe/Prague

Aktuální důkazy nasazení a prohlížečového ověření shrnuje
[PARENT_JOURNEY_PRODUCTION_2026-10-20.md](PARENT_JOURNEY_PRODUCTION_2026-10-20.md).
Rodičovská cesta z PR #91 prošla nasazením a ověřením 94 migrací. Poslední
aplikační release včetně opravy příslušenství z PR #92 je
`fd6ccc1584beb3a3266e8147497263cb8a38044f`:
[deploy 39402251446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402251446)
a [CI 39402250654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402250654)
prošly. Lokální implementační a akceptační podrobnosti
zůstávají v [původním předání](PARENT_JOURNEY_2026-10-20.md).

Produkční prohlížeč potvrdil přihlášení testovacího rodiče, existující rodinu
a stránku Moje kroužky. **Úplná cesta nové registrace až do soupisky zatím
ověřena není.** Novou žádost o dítě blokuje chybějící platná informace
o zpracování osobních údajů. Všech 52 zkontrolovaných účastnických variant
je veřejně viditelných, ale čeká na propojení; přesné produktové identifikátory
jsou v novém reportu. Existujících 26 programů a 74 nabídek se soupiskami
není třeba plošně zakládat znovu: všech 74 nabídek hlásí chybějící platný
souhlas se storno podmínkami
a všude je nyní neomezená kapacita. Je třeba rozhodnout skutečné vazby
a nastavení, včetně rozdílných cen Pumptracku a termínů Točňáků / Kunratic.

Fio, Stripe a SumUp se nezapínaly. Skutečné produktové vazby nebyly měněny.
Lokální sada po opravě příslušenství v PR #92 prošla:
**1 019 testů / 23 532 kontrol**. Doručení e-mailu ani skutečná platba
se tím nepotvrzují. Po posledním deployi jsou Trička a Uskladnění správně
bez blokace na program; Aligátoři zůstávají blokováni kvůli chybějícím vazbám.
Syntetický testovací program má připravenou nabídku, ale veřejná prezentace
TEST programu je koncept; rodičovský katalog jej nenabízí. Pozitivní produkční nákup proto
nebyl dokončen.

Níže je **historický bod předání z 11. 10. 2026**. Jeho SHA, počty testů,
produkční UAT a stav nasazení nejsou aktuálním důkazem současného provozu.

Tento dokument je stabilní vstupní bod pro další počítač nebo vývojáře.
Autoritativní zdroj je vždy vzdálená větev `origin/main` repozitáře
<https://github.com/KovoPraha/evidenceTreninku>. SHA uvedené níže je kontrolní
snapshot z data aktualizace, nikoli náhrada za nový `git fetch`.

## Ověřený bod předání

| Položka | Hodnota |
|---|---|
| Větev | `main`; přesné aktuální SHA zjistí nový clone pomocí `git rev-parse origin/main` |
| `origin/main` při kontrole | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Nasazený aplikační commit | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Otevřené pull requesty | žádné při kontrole 11. 10. 2026 |
| CI aktuálního `main` | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CI nasazeného aplikačního commitu | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CodeQL nasazeného commitu | [běh 37886667406](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406), Actions a JavaScript/TypeScript úspěch |
| Produkční deploy | [běh 37886677875](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875), úspěch, `uat_schvaleno=true` |
| Obnovení UAT účtů a dat | [běh 38093332413](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093332413), úspěch, okno do 12. 10. 2026 20:00 |
| UAT připravenost | [běh 38093377833](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093377833), `ready=true`, 10/10 oblastí |
| Produkční invarianty | [běh 38093408270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093408270), 16/16 bez porušení |
| Obnova zálohy | [běh 37701691548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548), 195 tabulek a 2 triggery |
| Produkční fronta zpráv | [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch |
| CodeQL default setup | [běh 37764312992](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992), Actions a JavaScript/TypeScript bez otevřeného nálezu |
| Produkce | <https://kis.kovopraha.cz/> |

UX navigace, kalendářů, e-shopu a pracovních přehledů je popsána v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).
Nová stanice musí před další změnou ověřit aktuální SHA a poslední produkční
běh; stavové hodnoty v tomto dokumentu se po každém release aktualizují
samostatným dokumentačním commitem.

CI nad aplikačním commitem ověřilo 877 testů / 11 995 kontrol a MariaDB 10.3 i
11.4. Produkce běží na tomto commitu; dokumentace může být na `main` novější.
Po deployi prošla z prohlížeče domovská stránka, e-shop, veřejný rozvrh,
klubový kalendář, velodrom a přihlášení bez fatálních chyb a bez chyb či
varování v konzoli. Databázové invarianty jsou zelené. Poslední deploy má
`uat_schvaleno=true`; nové časově omezené UAT okno bylo schváleno a ověřeno do
12. 10. 2026 20:00 Europe/Prague. Po jeho skončení je nutné testovací účty a
data deaktivovat auditovaným workflow.

Automatický Fio import je dočasně vypnutý. Workflow zůstává jen ručně
spustitelné a produkční `FIO_IMPORT_ENABLED` má zůstat `false`, dokud vlastník
znovu neschválí token, účet a provozní dohled.

## Nová stanice: čisté převzetí

```powershell
cd C:\xampp\htdocs
git clone https://github.com/KovoPraha/evidenceTreninku.git evidencePavel
cd evidencePavel
git fetch --prune origin
git switch main
git pull --ff-only origin main
git rev-parse HEAD
git rev-parse origin/main
```

Poslední dva příkazy musí vrátit stejné SHA. Potom spusťte
`PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`. Skript připraví ignorovaný `config.php`,
oddělenou lokální MariaDB na portu 3308, migrace a syntetická demo data. Pro
další starty slouží `START_LOCALHOST_TESTOVANI.cmd`.

## Existující clone: bezpečná synchronizace

Nejdřív zjistěte, zda stanice neobsahuje rozpracovanou práci:

```powershell
git status --short --branch
git fetch --prune origin
git branch --show-current
git rev-list --left-right --count HEAD...origin/main
```

Pokud je pracovní strom čistý a lokální `main` nemá vlastní commity:

```powershell
git switch main
git pull --ff-only origin main
```

Pokud jsou přítomné lokální změny nebo vlastní commity, nepoužívejte force,
reset ani přepis souborů. Nejdřív práci uložte do samostatné větve a teprve
potom synchronizujte `main`. Cizí rozpracované změny se nesmí přidat do nového
commitu jen proto, že leží ve stejném adresáři.

## Zahájení nové práce

```powershell
git fetch --prune origin
git switch -c codex/kratky-popis origin/main
```

Před commitem zkontrolujte změněné soubory a spusťte přiměřené testy. Úplná
základní brána je:

```powershell
composer install
composer validate --strict
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
git diff --check
```

Nová migrace musí být nový neměnný soubor v `migrations/`. Již přijatá migrace
se neupravuje. Pokud přidává tabulku vlastněnou aplikací, musí se současně
aktualizovat zálohovací ownership kontrakt v `bin/db-backup.php` a jeho testy.

## GitHub pracovní postup

`main` má vynucené aktuální tři CI kontroly, strict aktualizaci větve,
zamítnutí zastaralých review a pravidla platí i pro administrátora. Povinný
počet schválení zůstává dočasně `0`, protože repozitář i organizace mají pouze
jediného člověka s přístupem (`mmmixamarek-source`); hodnota `1` by znemožnila
schválit vlastní pull request. Jakmile bude přidán druhý důvěryhodný reviewer,
zvyšte počet na `1` a zapněte stejného reviewera také pro prostředí
`production`.

1. Pushnout pouze vlastní pracovní větev.
2. Otevřít pull request do `main`.
3. Počkat na PHPUnit a MariaDB 10.3/11.4 kontroly.
4. Před merge znovu ověřit, zda se `origin/main` mezitím neposunul.
5. Sloučit až po zelených kontrolách.
6. Po merge provést `git fetch --prune origin` a ověřit výsledný SHA.

Push ani merge není produkční nasazení. Produkční workflow se spouští ručně a
jen po výslovném rozhodnutí podle [`NASAZENI.md`](NASAZENI.md).

## Co není v GitHubu

- `config.php` a jakákoli tajemství;
- lokální nebo produkční databáze a obnovovací dumpy;
- skutečné osobní údaje, importy a přílohy;
- lokální outboxy, session a cache;
- `vendor/`, který obnoví Composer podle `composer.lock`.

Tyto výjimky jsou bezpečnostní hranice, nikoli chybějící část synchronizace.

## Kde hledat aktuální informace

- stav GitHubu, produkce a posledních oprav: [`CURRENT_STATE.md`](CURRENT_STATE.md);
- dokumentační rozcestník: [`README.md`](README.md);
- lokální instalace: [`localhost-testovani.md`](localhost-testovani.md);
- produkční nasazení: [`NASAZENI.md`](NASAZENI.md);
- produkční UAT: [`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md);
- pravidla migrací: [`../migrations/README.md`](../migrations/README.md).

Datované dokumenty jako `HANDOFF_2026-08-29.md`, auditní zprávy a implementační
prompty jsou historické záznamy. Při rozporu nemají přednost před aktuálním
kódem, `origin/main`, CI ani tímto dokumentem.

<!-- /archive -->

<!-- archive {"source": "docs/CURRENT_STATE.md", "part": 31, "sha256": "b5d98be7780f85fa8ff4225899355842206608a36902dbf3f6594ef3cf1c78e3"} -->
## Funkční stav po změnách z 9. 10. 2026

Pull requesty
[#34](https://github.com/KovoPraha/evidenceTreninku/pull/34),
[#35](https://github.com/KovoPraha/evidenceTreninku/pull/35),
[#36](https://github.com/KovoPraha/evidenceTreninku/pull/36),
[#38](https://github.com/KovoPraha/evidenceTreninku/pull/38) a
[#39](https://github.com/KovoPraha/evidenceTreninku/pull/39),
[#53](https://github.com/KovoPraha/evidenceTreninku/pull/53) a
[#55](https://github.com/KovoPraha/evidenceTreninku/pull/55) jsou sloučené do
`main`. Aktuální release mimo jiné obsahuje:

- srozumitelnější registraci a práci s heslem;
- předletovou kontrolu klubové akce před otevřením přihlašování;
- dvoukrokové ruční založení sportovce;
- bezpečné opakování nebo návrat při nedostupné platební bráně;
- auditované odebírání pracovních pozic a aktivaci/deaktivaci účtů;
- povinnou vazbu plánovaného tréninku na soupisku, nebo výslovné označení
  interního tréninku bez účastníků;
- potvrzení účasti na tréninku rodičem či sportovcem a přehled odpovědí pro
  trenéra;
- zachování vazeb na soupisky při kopírování týdne;
- opravené produkční spouštění fronty zpráv soupiskám přes neveřejný PHP
  bootstrap;
- opravy druhé vlny testerových bodů a pravdivé zobrazení neomezené kapacity
  ve zrychlené přihlášce;
- centralizované a validované ukládání souborů mimo veřejný webroot;
- jednorázové časově omezené odkazy pro veřejný přístup k profilu místo
  přímého přenosu interního identifikátoru;
- limity těla Stripe a SumUp webhooků, fail-closed ověření SumUp odpovědi,
  transakční zápisy a odstranění runtime DDL z obsluhy požadavků;
- nonce CSP pro inline skripty a styly a zúžený přístup k servisním endpointům.
- opravy druhé iterace prohlížečového UAT: bezpečný přechod mezi účtem
  sportovce a rodiče, funkční našeptávač poplatků, QR fallback, lepší mobilní
  správa trenérů, filtrování a stránkování administrativních seznamů a
  idempotentní lokální demonstrační data.

Produkční běh fronty zpráv
[`37606107788`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788)
nad commitem `623e161` skončil úspěšně. Workflow je naplánovaný každých pět
minut a lze jej spustit také ručně.

Podrobný uživatelský dopad testerových oprav popisuje
[`UAT-OPRAVY-2026-10-07.md`](UAT-OPRAVY-2026-10-07.md).

Navazující UX release sjednocuje veřejné akce a kalendář, přidává skutečný
měsíční rozvrh tréninků, hierarchickou navigaci e-shopu, kratší anonymní
nákupní cestu a prioritní přehledy rodiče a trenéra. Rozsah a ověřovací hranice
jsou popsány v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).

## Důležitá provozní hranice

Poslední deploy má `uat_schvaleno=true`, což označuje schválení přesného
commitu pro řízené produkční UAT, nikoli neomezené povolení zápisů. Read-only
kontrola připravenosti
[`38093377833`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093377833)
potvrdila `ready=true` ve všech deseti oblastech: release, rodiče, děti,
správce, TEST data, technické vlastnictví, Stripe sandbox, banka, inbox i
úklidové okno. Časově omezené účty a data byly předtím idempotentně obnoveny
během
[`38093332413`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093332413).
Schválené okno končí 12. 10. 2026 ve 20:00 Europe/Prague. Reálné e-maily,
bankovní pohyby a provozní účty se používají jen v rozsahu schváleného scénáře
podle
[`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md).

Navazující read-only kontrola databázových invariantů
[`38093408270`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093408270)
skončila `ok=true`: 16/16 kontrol bez porušení. Deploy před aktivací vytvořil
ověřenou komprimovanou zálohu 196 tabulek a 2 triggerů mimo webroot.

Obnova poslední generace produkční zálohy v izolované MariaDB byla ověřena
během [`37701691548`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548):
195 tabulek a 2 databázové triggery.

Fio import je od 7. 10. 2026 záměrně provozně pozastavený. Automatický plán byl
odstraněn; kód zůstává připravený pro budoucí ruční obnovení po samostatném
schválení read-only tokenu a provozního postupu.

## Převzetí práce na jiné stanici

Aktuální postup je v [`HANDOFF_CURRENT.md`](HANDOFF_CURRENT.md). Základní pravidla:

1. vždy začít `git fetch --prune origin`;
2. ověřit čistý pracovní strom a shodu lokálního `main` s `origin/main`;
3. novou práci založit z čerstvého `origin/main` na větvi `codex/<téma>`;
4. nemíchat do commitu `config.php`, tajemství, produkční data, lokální importy
   ani cizí rozpracované změny;
5. změnu dostat do `main` přes pull request a zelené kontroly;
6. produkční deploy považovat za samostatné, výslovně schválené rozhodnutí.

## Autorita dokumentace

Pro pokračování ve vývoji platí toto pořadí:

1. skutečný `origin/main`, migrace, testy a workflow;
2. tento stavový soubor a [`HANDOFF_CURRENT.md`](HANDOFF_CURRENT.md);
3. provozní runbooky, zejména [`NASAZENI.md`](NASAZENI.md);
4. tematické dokumenty k jednotlivým funkcím;
5. datované audity, staré handoffy, roadmapy a implementační prompty pouze jako
   historický kontext.

Starší soubory nebyly mazány, protože zachovávají rozhodovací a auditní stopu.
Jejich stará SHA, počty testů a tvrzení typu „plánováno“ nejsou aktuálním
stavem, pokud jsou v rozporu s výše uvedenými autoritami.

<!-- /archive -->


Původní vývojový kontext: [CLAUDE_HISTORY.md](CLAUDE_HISTORY.md).
