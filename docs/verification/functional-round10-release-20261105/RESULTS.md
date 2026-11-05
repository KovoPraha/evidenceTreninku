# PR #305 — sloučení, nasazení a ověření 5. 11. 2026

**PR #305 je sloučený a nasazený v produkci.** Vratky zachovávají objednávku,
odmítají nesouhlasný zdroj před doménovým úkonem a úplná historie je stránkovaná.
Živé vydání je `bc627ab12c22ba16feb0ca6cba3340fb87de31a9`.
Linux CI i deploy prošly **2009 testy / 38764 assertions bez skipů**,
anonymní HTTP před/po **48/48 PASS** a čerstvá záloha má skutečnou izolovanou obnovu PASS.
Přihlášený čtecí Chrome prošel v níže uvedeném rozsahu. Heslo, obnovovací odkaz a T07 SKIPPED.

## Review a přesné vydání

[PR #305](https://github.com/KovoPraha/evidenceTreninku/pull/305) byl zkontrolován nad
headem `e2570eeed1c8da2f19bc2259d747dd80c4d08497`, všech **11 kontrol SUCCESS**.
Aktuální main `bf899521` se od předchozího připojení nezměnil. Ruční kontrola
ověřila vstupní objednávku GET/POST, kontrolu cizího případu/výdeje před původní
doménovou službou, skrytá pole, odkazy, SQL rozsah před stránkováním a escapování.
Autorizace, CSRF a doménové služby zůstaly zachované. Blokující nález nebyl zjištěn.
Nové místní opakování souvisejících testů prošlo **47/617 bez skipů**.

Squash má přesně stejný Git strom jako ověřený head:
`78d1da25b883ad6eb4872bdab3fc4c42d7831622`.
[Review](pr-review.json), [kontroly PR](pr-checks.json), [sloučený PR](pr305.json),
[cílené testy](review-targeted.xml).

- [CI PR 41986189522](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41986189522):
  2009/38764 bez skipů, samostatné unixové hranice 3/45, MariaDB 10.3 i 11.4 PASS.
- [CI sloučeného main 41987967565](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41987967565):
  SUCCESS, [metadata](main-ci.json), [všechny kontroly](merged-checks.json).
- Původní [místní protokol](../functional-uat-round10-20261105/RESULTS.md) dokládá
  reprodukci chyby, 33 HTTP kontrol před/po, 38 nových kontrol, souběhy 19+6 a
  skutečný Chrome přes šest stránek. Tyto místní důkazy se nepřejmenovávají na produkční UAT.

## Chráněné nasazení a produkční stav

[Deploy 41987993980](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41987993980)
skončil SUCCESS nad přesným sloučeným SHA. Vstupy NASADIT,
uat_schvaleno=false, importovat_krouzky=false. [Protokol](deployment.json), [běh](deploy-run.json).
Proběhly syntaxe, Composer/platform validace, čistý audit závislostí, úplné testy,
ověřená záloha, neveřejná příprava release, kontrola schématu, aktivace a HTTP smoke.
Log aktivace potvrzuje aktualizaci shop_returns_admin.php a nový includes/shop_return_admin_read.php.
Oprava nepřidává migraci ani nemění vratkové, skladové a účetní doménové služby.
Aktuální main obsahuje také již samostatně sloučené provozní TV nástroje #303/#304;
v tomto vydání se nespouštěla TV příprava/cleanup ani import či datový převod.

[Výchozí audit 41987682391](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41987682391)
a [následný 41988846624](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41988846624)
proběhly ve čtecí DB transakci. Potvrdily přechod z vydání PR #300 na požadované
nové SHA a run. [Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json).
Hash konfigurace, flags, KIS canonical writes false, stav soukromých příloh a ledger
**150** jsou shodné. Fio import/auto-confirm, SumUp, Stripe i Velocota false;
Packeta nedefinovaná. Schéma current, pending [], dosavadní VS zachované.
Dvě dosavadní chybějící přílohy nadále zůstávají otevřené.

Historické old_source/new_source manifesty auditu nejsou inventurou tohoto vydání.
Důkazem je přesné živé release metadata, shodný Git strom a log aktivace; úplná
hash inventura všech živých souborů se netvrdí.

## Záloha a skutečná obnova

Generace `evidence_2026-11-05_073529_c66ea14d.sql.gz`, **347 tabulek, 2 triggery**,
6 257 659 komprimovaných bajtů. SHA-256
`3a45fc043fc25b8d9220fdc43a103dabb74431dce45d8143ee27e19f0cba3bbd`.
[Obnova 41988855944](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41988855944)
skončila SUCCESS. Přesný název generace prošel kontrolním součtem a manifestem,
všech 347 tabulek bylo obnoveno s kontrolou počtů řádků a ověřeny oba triggery
v izolované MariaDB 11.4 runneru. [Strojový důkaz](restore.json).
Produkční DB se neobnovovala; dočasná obnovovací data workflow uklidilo.

## Živé HTTP a přihlášený Chrome

[Před](pre-http.json) i [po](post-http.json) **48/48 PASS** se stejnými produkty.
[Pomocník](http-smoke.py) používá jen anonymní GET, bez cookies, POST a redirectů.
Vedle katalogu a chráněných souborů ověřuje anonymní odmítnutí vratek, vybrané
objednávky, neplatného ID, nadměrné stránky a položkového výdeje.

Skutečný připojený Chrome využil existující přihlášenou relaci vlastníka.
Z existující syntetické objednávky **14** (TEST Hotovost Vratka NEPLATIT) vedl
skutečný odkaz z položkového výdeje před nasazením na stránku bez označení objednávky.
Po aktivaci stejný přechod ukazuje správnou objednávku a vlastní zpětný odkaz.
Ověřen scoped/global prázdný stav, přítomnost kontextového pole ve formuláři,
prázdná nabídka původních výdejů, odmítnutí nuly a neexistující objednávky,
omezení neexistující stránky na 1/1, čtení správcem katalogu i hospodářem,
absence zakládacího formuláře u hospodáře a odmítnutí trenérského kontextu.
[Browser protokol](browser.json), [snímek opravy](production-order-context.png).

Browser vrací skryté hodnoty sanitizované; první přímé porovnání proto nebylo
použitelné jako důkaz hodnoty order_id. V browseru je potvrzena pouze přítomnost
pole. Jeho skutečnou hodnotu ověřují místní HTTP a regresní testy.
Jeden CDP snímek překročil timeout; čerstvý AX a dokumentované getScreenshot
úspěšně pořídily vizuálně ověřený snímek bez osobních údajů.

Produkce nemá vratkové případy, vybraná objednávka nemá položkový výdej.
Historii nad 100 ani pokus o zápis jiného případu tedy tato produkční přejímka
nepředstírá; pokrývají je místní data a Linux CI. Role jsou aktivní kontexty
stávajícího superadmina, nikoli nové oddělené omezené účty.
Nebyly odeslány doménové formuláře, vytvořené vratky ani skladové/peněžní pohyby.
Původní TV kontext byl obnoven a pomocná karta zavřená; ostatní karty nedotčené.
Žádná změna hesla, oprávnění, integrace, rozesílka ani T07.

Dokumentační následný PR mění pouze záznamy a nemění toto živé SHA; další deploy není potřebný.
