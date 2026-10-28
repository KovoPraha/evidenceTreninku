# Druhá vlna refaktoringu — produkční vydání

28. 10. 2026 (Europe/Prague), [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #211](https://github.com/KovoPraha/evidenceTreninku/pull/211) sloučen jako
`331bd97474b676bf4e22f529508a9db434dc64c6`,
[deploy 40752209335](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40752209335)
SUCCESS. Živé deployment metadata následně potvrdila stejné SHA a run ID.
Vydání navazuje na předchozí pokyn vlastníka sloučit/nasadit a následné pokračování.

## Rozsah a ověření před vydáním

Sdílení metadat, nabídek, prodejnosti a podmínek v jedné čtecí operaci e-shopu.
Nová operace vytváří nový kontext; zamykající checkout starý kontext ignoruje.
Žádná nová migrace, závislost ani změna členských cen či oprávnění.
[Implementace, syntetické regrese a limity](../refactoring-wave2-20261028/RESULTS.md).
Aplikační soubory sloučeného commitu odpovídají finálnímu headu PR `d98c67f6`.

Na přesném nasazovaném main prošly [Testy 40751905929](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751905929),
[PHP quality 40751906121](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751906121),
[CodeQL 40751905422](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751905422)
a [dokumentace 40751906128](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751906128).
[Metadata CI](github-ci-main.json). Deploy zopakoval PHPUnit: 1 764 testů,
35 753 assertions, PASS na PHP 8.2.35.

## Nasazení a zachování provozního stavu

Ruční workflow bylo spuštěno s `potvrzeni=NASADIT`, `uat_schvaleno=false`
a `importovat_krouzky=false`. Produkční záloha vytvořena a ověřena:
336 tabulek, 2 triggery, 6 063 441 komprimovaných bajtů,
`evidence_2026-10-28_191504_f7445c3d.sql.gz`.
SHA-256 `9dec4469cf8a1d6b2f5ddf093222ff7860eb30c546794027928b03b6dd4026d8`.
Obnova zálohy v tomto vydání nebyla opakována.

Obě migrační kontroly: current, katalog 140 migrací, pending `[]`.
Otisky dosavadních variabilních symbolů shodné. Soukromé přílohy: 0 kandidátů,
2 historicky chybějící, 0 chyb. Dočasná kopie produkční konfigurace odstraněna.
Záznamy bezpečných výstupů workflow jsou v [deployment.json](deployment.json).

Čtecí kontrola [před vydáním 40751855325](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40751855325)
a [po vydání 40752964463](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40752964463)
prošly v read-only databázové session, bez doménových mutací.
[Před](pre-release.json), [po](post-release.json), [porovnání](release-comparison.json):

- Živé SHA `20ab8637` → `331bd974`; nový run `40752209335`.
- Hash konfigurace, integrační flags a ledger 141 záznamů shodné.
- Fio import/automatické potvrzování, SumUp, Stripe a Velocota zůstávají vypnuté;
  Packeta flag nedefinovaný, KIS canonical writes false.
- Dvě chybějící přílohy a příznak dřívější dopředné opravy VS beze změny.

Pole `old_source`, `new_source` a `previous_release` historického drillu nejsou
novou inventurou runtime souborů tohoto vydání, proto nejsou použita jako důkaz
shody aktuálních souborů. Identitu živé verze dokládají deployment metadata
a úspěšný deploy; nový soupis hashů všech nasazených souborů nebyl pořízen.

## HTTP a odezvy skutečné produkce

[Čtecí skript](http-smoke.py), [před 22/22 PASS](pre-http.json),
[po 22/22 PASS](post-http.json). Pouze anonymní GET, bez cookies, POST a ukládání HTML.
Kontrolovány homepage, katalog, přihlášení, tři existující detaily, neexistující
detail 404, přesměrování obou chráněných agend na přihlášení a pět interních cest 403.
Žádná objednávka, registrace ani platba nebyla vytvořena.

Stejný klient a stejné produktové ID před i po vydání, tři sekvenční průchody
každé měřené cesty. [Všechna měření a mediány](latency-comparison.json):

| Cesta | Medián před | Medián po |
| --- | ---: | ---: |
| Katalog | 7,945 s | 3,317 s |
| Detail 247 | 5,875 s | 1,030 s |
| Detail 218 | 5,208 s | 0,674 s |
| Detail 221 | 5,286 s | 0,802 s |

Jde o orientační pozorování celého HTTP přenosu včetně sítě, nikoli vykreslení
prohlížeče, izolovaný kauzální benchmark, p95 nebo zátěžový test. Produkční
zátěž a stav cache nebyly řízeny. Katalog má nadále měřitelnou zbývající režii.

## Otevřené body

Technické vydání nenahrazuje plné UAT ani lidské/účetní převzetí D12–D14,
inventuru a ocenění, ověřené rodinné vazby a trenérské rozsahy, dohody/částky
příspěvků a doplnění dvou příloh. UAT flag zůstává false. Integrace ani importy
nebyly aktivovány. Další výkonový kandidát je dávkové načítání prezentačních
informací programů v katalogové stránce; toto vydání jej neimplementuje.
