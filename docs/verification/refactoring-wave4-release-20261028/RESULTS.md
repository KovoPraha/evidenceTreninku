# Čtvrtá vlna refaktoringu — produkční vydání

28. 10. 2026 (Europe/Prague), [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #215](https://github.com/KovoPraha/evidenceTreninku/pull/215) sloučen jako
`e40df7d3c532ed933541013f833b8f64e5951c47`. [Deploy 40771509403](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771509403)
SUCCESS; následná čtecí kontrola potvrzuje stejné živé SHA a run ID.
Vlastník výslovně schválil autonomní implementaci, sloučení i nasazení po ověření.

## Rozsah a ověření

Dávkové načtení posledních aktivních podmínek do kontextu jedné veřejné operace.
Pro 50 programů SQL 416 → 218, podmínky 200 → 2. Kanonické kapacity, checkout,
zámky, členské ceny a oprávnění beze změny; bez nové migrace či závislosti.
[Implementace, SQL, HTTP shoda a limity](../refactoring-wave4-20261028/RESULTS.md).
Všech 12 výstupních hashů a 6 lokálních HTTP profilů shodných. MariaDB 10.3/11.4
potvrdila kolace a čerstvé zamykající čtení; SQLite ověřila i hranici dávky.

CI přesného nasazeného main: [Current documentation 40771201612](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771201612), [Push on main 40771202202](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771202202), [Testy 40771201596](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771201596), [PHP quality 40771201654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40771201654). [Metadata](github-ci-main.json).
Deploy zopakoval PHPUnit: 1776 testů / 35830 assertions PASS.
Aplikační soubory main odpovídají ověřenému finálnímu PR headu `e83e2f57`.

## Záloha a provozní stav

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Vytvořena a ověřena záloha 336 tabulek / 2 triggerů,
6063441 komprimovaných bajtů, `evidence_2026-10-28_212539_45bebb06.sql.gz`.
SHA-256 `0c4f42855c8afdd6ab1f5db33c19c0128374c8a48b3e6b73d34817f2a3092645`. Obnova v tomto vydání neopakována.
Obě migrační kontroly current / pending []; katalog 140 migrací, ledger 141.
Dosavadní variabilní symboly zachovány. Dočasná kopie konfigurace odstraněna.
Soukromé přílohy: 0 kandidátů, 2 historicky chybějící, 0 chyb.
[Vybrané bezpečné výstupy deploye](deployment.json).

Čtecí kontrola [před 40770548691](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40770548691)
a [po 40772479517](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40772479517)
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
| /booking/eshop.php | 0,949 s | 0,571 s |
| /booking/produkt.php?id=247 | 1,231 s | 0,525 s |
| /booking/produkt.php?id=218 | 0,780 s | 0,458 s |
| /booking/produkt.php?id=221 | 0,981 s | 0,408 s |

Čas celé HTTP odpovědi včetně sítě, nikoli vykreslení. Orientační pozorování,
nikoli p95, zátěžový test či izolovaný kauzální benchmark; produkční zátěž
a cache nebyly řízeny. Silnějším důkazem optimalizace je shoda výstupů a SQL rozpočet.

## Otevřené body

Plné UAT a lidské/účetní převzetí D12–D14, inventura/ocenění, ověřené rodinné
vazby a trenérské rozsahy, dohody/částky příspěvků a dvě přílohy zůstávají otevřené.
Importy ani integrace nebyly aktivovány. Zbývající čtení nabídky a kapacity
je kandidátem dalšího měření, nikoli součástí této vlny.
