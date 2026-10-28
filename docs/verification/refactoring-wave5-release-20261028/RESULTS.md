# Pátá vlna refaktoringu — produkční vydání

28. 10. 2026 (Europe/Prague), [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #217](https://github.com/KovoPraha/evidenceTreninku/pull/217) sloučen jako
`a14d1026800822a09ee713a9e6924651be052913`. [Deploy 40777286733](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40777286733)
SUCCESS; následná čtecí kontrola potvrzuje stejné živé SHA a run ID.
Vlastník výslovně schválil autonomní implementaci, sloučení i nasazení po ověření.

## Rozsah a ověření

Dávkové načtení nabídek a vazeb fyzických týmů do kontextu jedné veřejné operace.
Pro 50 programů SQL 218 → 120, nabídky 50 → 1, vazby týmů 50 → 1. Kanonické kapacity, checkout,
zámky, členské ceny a oprávnění beze změny; bez nové migrace či závislosti.
[Implementace, SQL, HTTP shoda a limity](../refactoring-wave5-20261028/RESULTS.md).
Všech 12 výstupních hashů a 6 lokálních HTTP profilů shodných. MariaDB 10.3/11.4
potvrdila plné řádky, sdílenou kapacitu, legacy duplicity a čerstvé zámky; hranice dávky 401 variant PASS.

CI přesného nasazeného main: [Push on main 40776671215](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776671215), [Current documentation 40776670646](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776670646), [PHP quality 40776670614](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776670614), [Testy 40776670809](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40776670809). [Metadata](github-ci-main.json).
Deploy zopakoval PHPUnit: 1782 testů / 35866 assertions PASS.
Aplikační soubory main odpovídají ověřenému finálnímu PR headu `c64358d9`.

## Záloha a provozní stav

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Vytvořena a ověřena záloha 336 tabulek / 2 triggerů,
6063461 komprimovaných bajtů, `evidence_2026-10-28_220740_b3a44c80.sql.gz`.
SHA-256 `32337a1d96cc77f431e5696ae5746b53f8a5275ad96bf7d90f4772c120efe16e`. Obnova v tomto vydání neopakována.
Obě migrační kontroly current / pending []; katalog 140 migrací, ledger 141.
Dosavadní variabilní symboly zachovány. Dočasná kopie konfigurace odstraněna.
Soukromé přílohy: 0 kandidátů, 2 historicky chybějící, 0 chyb.
[Vybrané bezpečné výstupy deploye](deployment.json).

Čtecí kontrola [před 40775746797](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40775746797)
a [po 40777887462](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40777887462)
PASS v read-only session bez doménových mutací.
[Před](pre-release.json), [po](post-release.json), [porovnání](release-comparison.json).
Konfigurace, flags, ledger, KIS canonical writes a stav příloh shodné.
Fio import/automatické potvrzování, SumUp, Stripe a Velocota vypnuté,
Packeta flag nedefinovaný, KIS canonical writes false.
Historická pole old_source/new_source/previous_release diagnostiky nejsou
inventurou tohoto vydání; identitu dokládají deployment metadata a workflow.
Nový soupis hashů všech runtime souborů nebyl pořizován.

## HTTP a produkční odezvy

[Skript](http-smoke.py), [před 22/22 PASS](pre-http.json), [po 22/22 PASS](post-http.json).
Pouze anonymní GET bez cookies a POST: homepage, katalog, přihlášení, tři detaily,
neexistující detail 404, dvě chráněné agendy přesměrované na přihlášení
a pět neveřejných cest 403/404. Žádná objednávka, registrace ani platba.

Stejný klient a ID, tři pozorování každé cesty před/po. [Vzorky](latency-comparison.json).

| Cesta | Medián před | Medián po |
| --- | ---: | ---: |
| /booking/eshop.php | 0,683 s | 0,270 s |
| /booking/produkt.php?id=247 | 1,032 s | 0,677 s |
| /booking/produkt.php?id=218 | 0,791 s | 0,534 s |
| /booking/produkt.php?id=221 | 0,535 s | 0,468 s |

Čas celé HTTP odpovědi včetně sítě, nikoli vykreslení. Orientační pozorování,
nikoli p95, zátěžový test či izolovaný kauzální benchmark; produkční zátěž
a cache nebyly řízeny. Silnějším důkazem optimalizace je shoda výstupů a SQL rozpočet.

## Otevřené body

Plné UAT a lidské/účetní převzetí D12–D14, inventura/ocenění, ověřené rodinné
vazby a trenérské rozsahy, dohody/částky příspěvků a dvě přílohy zůstávají otevřené.
Importy ani integrace nebyly aktivovány. Zbývající čtení obsazenosti a rezervací
je kandidátem dalšího měření, nikoli součástí této vlny.
