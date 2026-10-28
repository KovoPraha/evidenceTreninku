# Třetí vlna refaktoringu — produkční vydání

28. 10. 2026 (Europe/Prague), [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #213](https://github.com/KovoPraha/evidenceTreninku/pull/213) sloučen jako
`09510ac6e44cfd01f571b91f5e52bc2d9d6eb9df`. [Deploy 40761965370](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761965370)
SUCCESS; následná čtecí kontrola potvrzuje stejné živé SHA a run ID.
Vydání navazuje na schválené autonomní pokračování refaktoringu a nasazování.

## Rozsah a kontroly

Karty programů v katalogu používají již načtené identity, kategorie a nabídky
ve stejném čtecím kontextu jako ověření prodejnosti. Členské ceny, oprávnění,
checkout a zamykání beze změny; žádná nová migrace ani závislost.
[Implementace, SQL, browser a limity](../refactoring-wave3-20261028/RESULTS.md).
Pro 50 programů SQL 1 366 → 416, metadata 711 → 11, výstupy shodné na MariaDB 10.3/11.4.
Většinu diffu katalogové stránky tvoří povinné formátování, ověřené PHP AST
a skutečným výstupem; pět wiring kontrol nově toleruje whitespace.

CI přesného nasazeného main: [Current documentation 40761603643](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603643), [Push on main 40761603485](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603485), [PHP quality 40761603769](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603769), [Testy 40761603743](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40761603743). [Metadata](github-ci-main.json).
Deploy zopakoval PHPUnit: 1769 testů / 35789 assertions PASS.
Připravené aplikační soubory odpovídají schválenému finálnímu PR headu `948c786f`.

## Záloha a provozní stav

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Vytvořena a ověřena záloha 336 tabulek / 2 triggerů,
6063441 komprimovaných bajtů, `evidence_2026-10-28_201953_56e8ea71.sql.gz`.
SHA-256 `e9b60a5fad2a2577b1b969601dad62c226e3f8f4a97d8a9a2fc28dce569bc9c3`. Obnova nebyla v tomto vydání opakována.
Obě migrační kontroly current / pending []; katalog 140 migrací, ledger 141.
Dosavadní variabilní symboly zachovány. Dočasná kopie konfigurace odstraněna.
Soukromé přílohy: 0 kandidátů, 2 historicky chybějící, 0 chyb.
[Vybrané bezpečné výstupy deploye](deployment.json).

Čtecí kontrola [před 40760366380](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40760366380)
a [po 40762533830](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40762533830)
prošly v read-only session bez doménových mutací.
[Před](pre-release.json), [po](post-release.json), [porovnání](release-comparison.json).
Hash konfigurace, integrační flags, ledger, KIS canonical writes a stav příloh shodné.
Fio import/automatické potvrzování, SumUp, Stripe a Velocota zůstávají vypnuté,
Packeta flag nedefinovaný, KIS canonical writes false.
Historická pole old_source/new_source/previous_release diagnostiky nejsou
inventurou tohoto vydání; identitu dokládají deployment metadata a úspěšný
workflow. Nový soupis hashů všech runtime souborů nebyl pořizován.

## HTTP a produkční odezvy

[Skript](http-smoke.py), [před 22/22 PASS](pre-http.json), [po 22/22 PASS](post-http.json).
Pouze anonymní GET, bez cookies a POST: homepage, katalog, přihlášení,
tři existující detaily, neexistující detail 404, dvě chráněné agendy přesměrované
na přihlášení a pět interních cest 403. Žádná objednávka, registrace ani platba.

Stejný klient a stejné ID; tři pozorování na cestu před/po. [Všechny vzorky](latency-comparison.json).

| Cesta | Medián před | Medián po |
| --- | ---: | ---: |
| /booking/eshop.php | 2,92 s | 0,32 s |
| /booking/produkt.php?id=247 | 1,12 s | 0,34 s |
| /booking/produkt.php?id=218 | 1,10 s | 0,26 s |
| /booking/produkt.php?id=221 | 0,70 s | 0,28 s |

Časy zahrnují síť a celé tělo odpovědi, nikoli vykreslení prohlížeče.
Jde o orientační pozorování, nikoli p95, zátěžový test ani izolovaný kauzální
benchmark; produkční zátěž a cache nebyly řízeny. Detaily nejsou cílem této vlny.

## Otevřené body

Plné UAT a lidské/účetní převzetí D12–D14, inventura/ocenění, ověřené rodinné
vazby a trenérské rozsahy, dohody/částky příspěvků a dvě přílohy zůstávají otevřené.
Žádné importy či integrace nebyly aktivovány. Další výkonový kandidát vyžaduje
měření zbývajících kanonických dotazů na kapacitu a podmínky; není součástí vydání.
