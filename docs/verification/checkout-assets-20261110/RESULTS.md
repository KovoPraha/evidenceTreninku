# P11-ASSET6 — ověření checkout formulářů

NASAZENO / cílové formuláře technicky PASS, controlfoto LIMIT. Implementace, native/Chrome,13CI a živé HTML/targetpixely PASS.
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset6--rychlý-nákup-a-přihláška-10-11-2026), [PR #445](https://github.com/KovoPraha/evidenceTreninku/pull/445).

## Rozsah a shoda

Odstraněné právě dva známé CDN Bootstrap CSS tagy, po jednom v rychly_nakup.php
a rychla_prihlaska.php. Source kontrakt proti58848900: všechny PHP tokeny a
literal HTML/JS mimo tag stejné, nula vložených guard závorek. Povinné PSR12/
200znakové formátování šablon používá nulové PHP hranice bez výstupu. CSS/JS
payloady, verze, TTL, SQL a všechny writery stejné. Test rodičovského průchodu
akceptuje pouze PHP whitespace; nadále vykresluje skutečné hidden názvy/verze,
kontroluje stale/refresh a atomický rollback. Lokálně21 testů/412 assertions PASS.

[Native42878204242](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42878204242)
SUCCESS na obou DB. [Doklad](native-acceptance.json), [10.3](native-10.3/whole-http.json.gz),
[11.4](native-11.4/whole-http.json.gz): deset celých HTTP scénářů/DB,
17střídavých párů po4warmupech =340měřených+80warmup GET/DB. Celé HTML, status
i Location přesně shodné, maskovaný pouze konkrétní CDNtag a request nonce/CSRF.
Zachované ceny, data, skripty a hidden term/program_term verze. NULL/zero stock404,
neplatná varianta404, klubový redirect303, nezveřejněné program terms404,
goods/program guard404, ověřený account303, produkt control200. Samostatný
chybějící povinný athlete notice409 a invalidCSRF obou formulářů beze zápisu.
Před checksumy skutečný syntetický veřejný login přesně302. Všech350 tabulek/DB
beze změny; cleanup schema/user/config/portů a bridge účtu PASS. Soukromé
konfigurace, cookies, hesla, raw HTML a logy nejsou součástí exportu.

První běhy42876038779/d9c62776 a42876348307/fb952c69 FAIL na chybně očekávaném
login303; zdroj používá neměněné302. Běh42876825180/c3a48460 FAIL ještě na before
fixture NULLstock: existující storefront správně404, stejně nepublikované terms.
Očekávání opravena přesně podle současných guardů; samostatný notice409 přidán.
Původní FAIL zůstává FAIL, žádná aplikativní ochrana se neuvolnila.

## Skutečný Apache/Chrome před a po

Linux, MariaDB11.4, Chrome156.0.8078.79; dvě fyzické kopie stejného aktuálního
PHP kontextu, pouze dvě cílové before šablony z58848900. Plně migrovaná izolovaná
syntetická DB,162migrací/350tabulek, bez mocků nebo umělé latence. Desktop1036×703,
mobile390×844, cold/warm;17střídavých párů po4warmupech ve12kombinacích:
408měřených+96warmup navigací. [Všech12 souhrnů](native-11.4/browser-summary.json),
raw bezpečné vzorky browser-*.json.gz ve stejném adresáři. ResourceTiming/CDP
ověřují skutečné přenosy a cache, CSS SHA/SRI, computedstyles/geometrii.
24funkčních záznamů: navigace, quantity/identity, guardian/self synchronizace,
age warning, ack/CSC a všechny verze. Šest dekódovaných pixelových párů shodných.

| Cold scénář | FCP před → po, ms | Ready před → po, ms | Encoded Bootstrap před → po, B |
| --- | --- | --- | --- |
| guest desktop | 72 → 56 | 93.1 → 76.7 | 64454 → 31248 |
| guest mobile | 68 → 52 | 93.6 → 76.8 | 64454 → 31248 |
| program desktop | 96 → 76 | 123.7 → 109.4 | 64454 → 31248 |
| program mobile | 88 → 76 | 125.2 → 109.3 | 64454 → 31248 |
| control desktop | 68 → 68 | 92.9 → 92.6 | 31248 → 31248 |
| control mobile | 68 → 68 | 93.2 → 92.9 | 31248 → 31248 |

Cílové CSS2→1, decoded465606→232803 B (−50 %); cold encoded64454→31248 B
(−33206 B,−51,5 % v tomto CI/CDN transportu). Control1→1/31248 B, FCP68→68 ms.
Warm network0→0 B, takže nepřipisujeme další síťový zisk teplé cache; stále jedna
kopie místo dvou ke zpracování. Gate FCP/ready20%+20ms prošel. HTTP backend
mediány10.3: goods11,262→11,106ms/program22,763→22,989ms/control18,094→18,019ms;
11.4: goods13,431→13,671ms/program27,591→27,655ms/control20,866→20,614ms.
Změna necílí na SQL/TTFB a časové odchylky backendu nepovažujeme za zrychlení.
Chrome mediány nejsou produkční SLA ani důkaz stejného zisku každému návštěvníkovi.

Reprodukce: .github/workflows/checkout-assets-performance.yml a odpovídající
tests/Support/CheckoutAssets*; explicitní SYNTHETIC-ONLY, pin58848900 a oba DB
matrix joby. [Source identity](native-11.4/source-identity.json),
[společný kontext](native-11.4/shared-context.json), [cleanup](native-11.4/cleanup.json).
Odložený EVENTREAD helper není součástí runtime; jeho SQLite potenciál nebyl
prohlášen za native/produkčně přijatý. Historické tři ASSET4 katalogové hashy
zůstávají LIMIT pro chybějící before HTML. Původní WIP7a7afd75 zachovaný.


## Produkční release a přejímka

PR #445 sloučen jako `c9e5501a4911f724ae5e6ed253941cb4cb8e7830`; celý tree `97fa16dcd690b2b78025b9aa531a52a7a5b6fee1` je totožný s testovaným d6af0a09.
[Všech13 aktuálních kontrol](release/app-checks.json) SUCCESS, přísná branch
protection zachovaná. [Deploy42881039034](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42881039034)
SUCCESS na stejném SHA; znovu2454 testů/56238 assertions.
Záloha389 tabulek/2 triggerů ověřená, SHA256
`7a01b1d1220d992401719fe2c0e5939539809d6ba2ee7c08c2c2dbae1bc79453`. Konfigurace zachovaná bez doplnění, archivní klíč/záloha/
webcopy PASS, pending migrace [], dosavadní VS a definice/inventory zdravotního
indexu stejné. Přímý runner HTTP0 (síťová nedostupnost); předepsaný serverový
curl fallback200 a oba úklidy PASS. Import přeskočený, oba flagsfalse.
[Receipt](release/release-controls.json), [workflow](release/deploy-summary.json),
[indexy](release/index-preflight/medical-index-before.json).

Čerstvé produkční [before](release/live-chrome/before.json) z2026-11-10T19:01:02.734Z
a [after](release/live-chrome/after.json) z2026-11-10T19:11:20.501Z: šest celých HTML
otisků shodných, včetně dat, skriptů a všech pěti asset verzí. Zachované privacy
hlavičky/no-store a CSP. Maskují se jen konkrétní dva známé tagy a nonce/CSRF;
žádné obecné whitespace, script nebo asset-version normalizace.
Cíle CSS2→1, control1→1. Živé cold encoded53957→31248 B (−22709 B /−42,1 %
Bootstrap CSS), decoded465606→232803 B; control31248→31248 B.
Pět ze šesti [GD pixelových párů](release/live-chrome/parity-and-pixels.json)
přesně shodných, včetně všech čtyř upravených formulářů; desktop/mobile bez
přetékání a JS/network chyb. Chrome158 Windows,
jednorázové produkční capture nejsou časový benchmark. [Pět UI payloadů +CDN](release/static-after.json)
shodných s canonical Git58848900 před i po včetně cache politik; ETag může
odrážet mtime. Encoded rozdíl proti CI je skutečný odlišný CDN transport,
rozbalený CSS payload je stále přesně totožný.

**LIMIT kontrolní mobilní fotografie:** strict pixel test není PASS. Rozdíl
32509pixelů je omezený na obdélník x107–281/y532–761 fotografie neupraveného
produkt.php; vše mimo fotografii přesně stejné. Max RGB kanálová odchylka4/255,
průměr na odlišných pixelech0,647, jen246pixelů má kanálovou odchylku větší2.
Šest celých HTML/hlaviček/CSP, všechny assety i control desktop přesně stejné.
Čtyři opakované GET dávají stejný JPEG272969 B/SHA794eec11…ea50f a stejné after
pixely; before samotný JPEG nebyl samostatně hashován. Rozdíl může odpovídat
zaokrouhlení při dekódování/zmenšení, ale přesná příčina není prokázaná a není
zpětně označená PASS. Žádná obrazová maska ani tolerance nenahradila strict test.
[Diagnostika](release/live-chrome/control-mobile-photo-diagnostic.json), všechny
původní i opakované PNG zachované. Runtime kontrolního produktu nebyl změněn;
native6/6pixelpairs a všechny4 produkční targetpairs jsou přesně shodné.

Přihlášené lidské produkční UAT, fyzický telefon a obnova této konkrétní zálohy
NOT RUN. Žádná testovací produkční objednávka, platba ani změna rolí nevznikla.
Původní fio/d1221f35 a WIP SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` zachované.
Finální doklady: codex/checkout-assets-release-docs-20261110,
[PR #446](https://github.com/KovoPraha/evidenceTreninku/pull/446); sloučením PR #446 jsou součástí main; druhý deploy se neprovádí.
