# P11-ASSET6 — ověření checkout formulářů

Implementace, native/Chrome a13CI PASS; PR #445 sloučen jako c9e5501a.
Chráněné nasazení42881039034 běží, živé after zatím NOT RUN.
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


Celý tested/merged tree97fa16dc shodný; čerstvá živá baseline6GET z19:01:02UTC,
šestcanonical staticpayloadů PASS. Finální doklady ve větvi
codex/checkout-assets-release-docs-20261110; PR bude doplněn při otevření.
Další krok dokončit deploy, ověřit receipt a živé after. Bez druhého deploye
kvůli samotným dokladům.
