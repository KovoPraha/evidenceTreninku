# P11-ASSET2 — vydání pracovního rozcestníku

10. 11. 2026 · NASAZENO / TECHNICKÉ OVĚŘENÍ PASS.
[PR #430](https://github.com/KovoPraha/evidenceTreninku/pull/430) sloučen jako
`706a8d429197c779c36ffc333751b77ee1f9ba14`. Celý tree je identický s testovaným
`156a9597ccdc183ae6e0bf1cf8598612f1285a88`; všech [13 CI SUCCESS](app-checks.json),
PHPUnit 2 425 testů / 55 914 assertions, obě MariaDB integrační i read-profile matice,
PHPStan/style, CodeQL a docs PASS. [Source a tree proof](tree-identity.json).

## Změna a shoda

Jediný změněný runtime soubor je pracovni_pozice.php. Jeho jedinou změnou
emitovaného HTML je smazaný redundantní Bootstrap link. Povinné PSR-12/line200
formátování zůstalo uvnitř PHP segmentů; závorky přidané jedinému přesně
identifikovanému dosavadnímu if/continue guardu. Tokenový kontrakt vyžaduje
všechny další významové PHP tokeny a literal HTML beze změny. Nezavádí obecnou
výjimku pro závorky/HTML nebo slabší style gate. Header/JS/CSS/SQL/práva/session,
cache a aktivace funkcí beze změny. HJS/HJS2 prototypy zachované bez merge/deploy.

## Skutečný nový Apache/Chrome pár

[MariaDB 10.3](../native-10.3/integrated-summary.json) a
[11.4](../native-11.4/integrated-summary.json) SUCCESS na aktuálním zdroji.
Každá DB: 1k/10k/30k, 17 střídavých párů po 4 zahřívacích, čtyři celé HTTP
odpovědi, strict HTML/kontexty, role odmítnutí, profile bearer/revokace/logout,
12 doménových checksumů a úklid. To je 408 měřených +96 zahřívacích parent
HTTP odpovědí na každou DB, mimo login/security/fixture kroky.
Reálný Apache gzip před vlastními PHP workers; stejná topologie oběma,
žádné network mocks, throttling nebo přidaná latence.

Chrome: 544 měřených +128 zahřívacích navigací, coach rozcestník/Moje,
lead Správa a public profil, desktop/mobile cold/warm. Všech 8 párů pixelově
identických, computed styles a geometrie shodné, 24 funkčních scénářů včetně
navigace, toast/shortcut/confirm modalů a form/bfcache kontraktu PASS; JS a
network chyby 0. Pracovní rozcestník CSS 2→1, tři nezměněné kontroly 1→1.
Všechny skutečně přijaté CSS payloady mají identický canonical hash/SRI.
[Chrome data](../native-11.4/workspace-browser-coach.json.gz),
[mediány](../native-11.4/workspace-browser-coach-summary.json),
[pixelové receipts](../native-11.4/workspace-pixels-coach.json) a
[snímky rozcestníku](../native-11.4/screenshots/workspace-assets-coach-workspace-desktop-after.png).

| Rozcestník | Před | Po | Rozdíl |
| --- | ---: | ---: | ---: |
| Cold Bootstrap CSS gzip payload | 62 496 B | 31 248 B | −31 248 B / −50 % |
| Bootstrap CSS decoded | 465 606 B | 232 803 B | jedna kopie místo dvou |
| HTML decoded | 45 094 B | 45 016 B | −78 B, pouze link |
| Cold HTML + Bootstrap CSS | 73 524 B | 42 272 B | −31 252 B; není to celý waterfall |
| Cold desktop FCP | 60 ms | 52 ms | medián −13,3 % |
| Cold mobile FCP | 56 ms | 52 ms | medián −7,1 % |
| Warm desktop/mobile FCP | 44 / 40 ms | 44 / 40 ms | beze změny |
| Warm CSS network payload | 0 B | 0 B | obě cache hit |

Cold ready desktop 76,7→75,6 ms a mobile 76,7→76,8 ms; výrazný ready-time zisk
neprokázaný. Jde o reálný Chrome nad syntetickými daty a loopback sítí, nikoli
produkční SLA. Předchozí neúplné běhy se nepočítají jako přijaté měření.

## Produkční release a nezávislé čtení

[Deploy 42790212078](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42790212078)
SUCCESS na přesném merged SHA; skutečný checkout SHA i rsync cílového souboru
ověřené. [Stav workflow](deploy-summary.json) a [receipt](release-controls.json):
importovat_krouzky=false, uat_schvaleno=false, import přeskočený, testy znovu
2425/55914 PASS. Úplná ověřená záloha 389 tabulek /2 triggerů; konfigurace
zachovaná bez doplnění, private key záloha/webcopy PASS, čekající migrace [],
původní VS beze změny, covering index a definice před/po shodné. Runner přímým
HTTP nezískal spojení (0); předepsaný serverový fallback provedl HTTP GET 200.
Private key HTTP403, oba úklidy SUCCESS. Nejde o vydávání síťového 0 za HTTP200.

[Osm veřejných GET před](../public-before.json) a [po](../public-after.json) má
všech osm statusů i normalizovaných DOM hashů shodných, nezávisle z Windows.
[CSS kontrola před](../production-css-before.json) a [po](../production-css-after.json)
potvrdila přesné canonical232803 decodedB/SRI, gzip31248B, max-age604800 a
stejný výstup verzované i bezverzové URL; anonymní rozcestník nadále odmítnutý.
Nejde o přihlášený produkční waterfall. [Reprodukční GET probe](../live-css-probe.py.txt)
nepoužívá cookies, redirecty, POST ani TLS výjimky a neuchovává raw HTML.

Přihlášené produkční UAT / fyzický mobil / obnova právě této zálohy: NOT RUN.
Připojený browser inventory dvakrát timeout/reset, žádné UI formuláře nebo role
se neměnily. Produkční CSS2→1 odpovídá testovanému/deploynutému zdroji;
časová čísla výše patří výhradně syntetickému Chrome páru.

Původní checkout codex/fio-auto-reconciliation-recovery a dirty kis_rosters_admin.php
zachované se stejným SHA2567a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5
po release. Žádný reset/stash/clean ani přimíchání sousedního WIP.
[Plán](../../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset2--pracovní-rozcestník-nový-řez-10-11-2026)
a [předání](../../../SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md) uzavřené
v docs větvi codex/workspace-assets-release-docs-20261110, bez druhého deploye.
Další optimalizaci vybírat podle nového aktuálního waterfallu, ne starých časů.
