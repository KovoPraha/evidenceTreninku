# Produkční vydání druhé funkční vlny — 2. 11. 2026

Vlastník výslovně povolil sloučení a nasazení dokončené vlny PR #274.
**Sloučení i chráněné nasazení SUCCESS.** Změna hesla a T07 jsou nadále SKIPPED.
Workflow běžel 11:28–11:32 UTC, tedy 12:28–12:32 Europe/Prague.

## Rozsah a kontrola před sloučením

[PR #274](https://github.com/KovoPraha/evidenceTreninku/pull/274), přesný head
`b76e8c8c9cd5ecb94b45bdbc630caf867158a49f`, měl všech **11 kontrol SUCCESS**.
[Linux CI](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41425068180):
**1905 testů / 38263 assertions**, bez skipů, obě MariaDB 10.3/11.4 smoke
a objemové profily PASS; PHPStan/styl, dokumentace a CodeQL rovněž PASS.
Při novém ověření zůstal head i base/main beze změny, ochrana main strict
a vynucení pravidel pro administrátory zachovány.

Oprava odděluje zboží od programu v přípravě localhost dema. Starší postižené
demo dostane samostatné syntetické zboží, původní program a historie zůstávají.
Skutečnou funkční regresi, opakované seedy i 35 dalších kontrol s browserem
dokládá [místní protokol](../functional-uat-round2-20261102/RESULTS.md).
Těchto 35 PASS jsou syntetické místní kontroly, nikoli produkční přejímka.

Aplikační rozdíl proti předchozí produkci tvoří `bin/seed-local-demo.php`
a `includes/localhost_demo_catalog.php`; ostatní změny jsou testy a dokumentace.
Formátování seedu má samostatný parserový sémantický diff. Bez nové migrace,
změny veřejných formulářů, účetního runtime nebo produkčních oprávnění.

Squash merge **`51796fcafa16823f6c84208406a5c75682023409`**; sloučený strom
je přesně shodný s otestovaným headem. [Metadata PR](pr274.json).

[CI sloučeného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41437040472)
mělo při prvním pokusu síťový timeout Packagistu v `composer audit` (curl 28),
nikoli hlášenou zranitelnost či pád testu. Databázové joby prošly; selhaný job
byl zopakován se stejnými kontrolami. Aktuální výsledek určuje tento CI běh.

## Nasazení a provozní stav

[Deploy 41437064975](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41437064975)
SUCCESS ze zdroje `51796fcafa16823f6c84208406a5c75682023409`.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Deploy zopakoval **1905/38263**, provedl preflight, ověřenou zálohu, kontrolu
schématu, zachování VS, aktivaci a HTTP přejímku. Dočasný config odstraněn.

- Nová ověřená záloha **337 tabulek / 2 triggery**, SHA-256
  `25e7202d52707bffc6c659ac08b1da334bd930e5ee8cd20006c7f7f1abcabf59`.
  Tato generace se v tomto běhu izolovaně neobnovovala.
- Schéma current / pending `[]`, katalog 142, ledger 143; dosavadní variabilní
  symboly beze změny. Soukromé úložiště i existující konfigurace zachovány.
- Před/po shodný hash konfigurace, integrační flags, kandidáti soukromých příloh,
  zákaz kanonických KIS zápisů a počet ledgeru. Fio import/auto-confirm, SumUp,
  Stripe a Velocota false; Packeta nedefinovaná. UAT/import false.

[Čtecí audit před 41436805053](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41436805053)
a [čtecí audit po 41437714419](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41437714419)
SUCCESS. Po nasazení audit potvrdil živé SHA a deploy run. Jejich
`deployment_ready=false` odkazuje na otevřenou lidskou/účetní přejímku;
starý manifest F1–F3 není úplnou inventurou tohoto vydání.
[Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json),
[souhrn nasazení](deployment.json), [kroky workflow](deploy-run.json).

Log aktivace potvrzuje rsync obou změněných aplikačních souborů.
[Manifest](release-manifest.json) obsahuje očekávané Git hashe, nikoli přímo
změřené PHP hashe produkce. Důkazem dodání je chráněný workflow, úspěšná
aktivace a živá release metadata; nejde o úplnou inventuru hashů runtime.

## Ověření po aktivaci

Anonymní GET-only HTTP **před 26/26, po 26/26 PASS**: veřejné stránky,
stejné známé produkty, chráněné cesty a zákaz přístupu k soukromým souborům.
[Před](pre-http.json), [po](post-http.json), [helper](http-smoke.py).

[Doplňkové webové guardy 3/3 PASS](web-guards.json): seed 403, nový pomocný
soubor v includes 403, localhost testovací rozcestník 404. [Helper](web-guards.py).
**Localhost seed se v produkci nespouštěl**, žádná demo data/účty se tam nepřipravovala.

Skutečný Chrome po aktivaci ověřil anonymní katalog Knihy se dvěma výsledky,
řazení 250 Kč → 450 Kč a hledání Zábavně s jediným odpovídajícím výsledkem.
[Browser výsledek](browser.json), [produkční snímek](production-shop.png).
Původní uživatelské karty ponechány, vlastní karta zavřena, viewport neměněn.

Heslo a T07 vynechány. Žádná nová objednávka, trénink, rezervace, zpráva,
aktivace integrací ani nové uživatelské produkční záznamy v tomto běhu.
Standardní zálohovací a ověřovací kroky autorizovaného deploye proběhly.
Přihlášené produkční UAT se neopakovalo. Lidská/účetní přejímka a dříve
chybějící dvě soukromé přílohy zůstávají otevřené; technická vlna je uzavřena.

Dokumentace zachycuje toto vydání samostatným PR. Její sloučení nevyžaduje
další produkční deploy; živým aplikačním zdrojem zůstává výše uvedený merge.
Kontrola dokumentace: 60 řádků CURRENT_STATE, 498 místních odkazů,
94 neporušených archivních bloků; PHPUnit kontrakt 2/14, Composer validate
a lokální online audit závislostí bez advisory, git diff --check PASS.
