# PR #300 — sloučení, nasazení a technické ověření 5. 11. 2026

**PR #300 je sloučený a nasazený.** Oprava souběhu rodinného souhrnu s odhlášením
odběru nebo změnou rodinného oprávnění je v živém vydání
`97cf47929e09fae420b2171c443b98289fa8a1b5`.
CI i deploy **1997 testů / 38644 assertions, bez skipů**, HTTP před/po **42/42 PASS**,
ověření čerstvé zálohy skutečnou izolovanou obnovou **PASS**.
Přihlášené produkční UAT je **NOT RUN**: dostupný připojený Chrome neměl relaci.
Změna hesla, použitý obnovovací odkaz a T07 nadále **SKIPPED**.

## Kontrola PR a současného základu

[PR #300](https://github.com/KovoPraha/evidenceTreninku/pull/300) původně vycházel ze
stavu před TV programy #301. Ty mezitím byly samostatně sloučené a nasazené
[během 41977082925](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41977082925).
Aktuální main byl proto připojen do vlastní větve bez konfliktu. Nový ověřený head
`f2b4f9b5a8d4d8a6a56a866a99e4b0d801828b9f` prošel všemi **11 kontrolami**.
Squash `97cf47929e09fae420b2171c443b98289fa8a1b5` má přesně shodný Git strom.
[Review a porovnání](pr-review.json), [kontroly](pr-checks.json), [PR po sloučení](pr300.json).

Ruční kontrola ověřila svázání obsahu s otiskem před sestavením, zámek a opětovnou
kontrolu odběru/kontaktu i úplný rollback při změně podmínek. Stávající kontrola
před odesláním zůstává zachovaná. Blokující nález nebyl zjištěn.
Jediný runtime rozdíl proti již nasazenému main je `includes/family_weekly_delivery.php`.
Žádná nová migrace, rozesílka ani změna plánování.

- [CI aktualizovaného PR 41977992138](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41977992138):
  1997/38644 bez skipů, integrační smoke a čtecí profily obou MariaDB PASS.
- Stažené artefakty [MariaDB 10.3](mariadb-10.3.json) a [11.4](mariadb-11.4.json):
  oba potvrzují všech 49 kontrol včetně 28 kontrol změn během preview.
- Místní cílená sada na aktualizovaném headu: **34/365 PASS**.
- [CI sloučeného main 41978849474](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41978849474): SUCCESS.

## Nasazení a změny produkce

[Chráněný deploy 41978905942](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41978905942)
skončil SUCCESS nad přesným sloučeným SHA. Vstupy: NASADIT,
`uat_schvaleno=false`, `importovat_krouzky=false`.
[Strojový protokol](deployment.json), [běh](deploy-run.json).

Před aktivací proběhly syntaxe, Composer validace, platform requirements, čistý
audit závislostí a celá sada testů. Vznikla ověřená záloha, připravil se neveřejný
release, ověřil klíč archivu KIS a proběhla kontrola schématu bez čekajících migrací.
Log aktivace obsahuje dodání změněného `includes/family_weekly_delivery.php`.

[Výchozí audit 41978019697](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41978019697)
a [následný audit 41979427383](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41979427383)
proběhly ve čtecí DB transakci. Potvrdily přechod z TV vydání
`3a3893fd4d604da63e40659f07834510bc567879` na požadované nové SHA.
[Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json).

Shodné jsou hash konfigurace, integrační přepínače, stav soukromých příloh,
KIS canonical writes false a migrační ledger **150**. Fio import/auto-confirm,
SumUp, Stripe a Velocota zůstávají false; Packeta nedefinovaná.
Deploy samostatně potvrdil zachování dosavadních variabilních symbolů.
Fronta rodinných souhrnů neměla čekající položky před ani po vydání.
Dvě dosavadní chybějící přílohy zůstávají otevřené.

`old_source/new_source` v obecném auditu jsou historické pevné manifesty 44 souborů.
Nejde o hash inventuru tohoto vydání; důkaz zde tvoří přesné živé release metadata,
shodný Git strom a log aktivace změněného souboru.

## Čerstvá záloha a izolovaná obnova

Generace `evidence_2026-11-05_061452_d2e17b4b.sql.gz`, **347 tabulek, 2 triggery**,
6 255 274 komprimovaných bajtů.
SHA-256 `1f3a5ef83d62a57fce0f5e25a7e36dfc101c49591f2eea9587c64712295c513b`.

[Obnova 41979519304](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41979519304)
skončila SUCCESS: přesný název generace prošel kontrolním součtem a manifestem,
obnoveno všech 347 tabulek s ověřením počtů řádků a oba triggery v izolované
MariaDB 11.4 runneru. [Strojový důkaz](restore.json).
Produkční DB se neobnovovala. Dočasná obnovovací data workflow uklidilo.

## HTTP, browser a zbývající přejímka

[Před](pre-http.json) i [po](post-http.json) prošlo **42/42** anonymních GET kontrol.
Součástí jsou veřejný katalog a konkrétní produkty, odmítnutí rodinných/pracovních
stránek bez relace, neveřejné cesty a zachování ochrany TV administrace.
[Pomocník](http-smoke.py) nepoužívá cookies, POST ani automatická přesměrování.
Původní očekávání 403 pro TV administraci bylo opraveno na její skutečný login
redirect 302; nešlo o chybu aplikace. Konečná sada před/po používá totožné produkty
a stejné očekávané stavy.

Skutečný Chrome v připojeném uživatelském profilu při otevření pracovního
rozcestníku přesměroval na přihlášení. [Browser záznam](browser.json).
Žádné heslo nebylo čtené, zadávané ani měněné, session se nekopírovaly a jiné
uživatelské karty zůstaly nedotčené. Pomocná karta byla zavřená.
Přihlášené produkční UAT ani skutečné doručení souhrnu se netvrdí.

Původní [místní funkční protokol](../functional-uat-round9-20261104/RESULTS.md)
obsahuje červené/zelené důkazy obou chyb a skutečný místní browser průchod.
Produkční doménová data, vazby, oprávnění a odběry se v rámci přejímky neměnily.
E8, poslední synchronizace, lidská/účetní přejímka a heslo/T07 zůstávají oddělené.
Dokumentační následný PR nemění živé SHA a nevyžaduje další deploy.
