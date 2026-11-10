# P11-ASSET5 — veřejný kalendář a velodrom

10. 11. 2026. NASAZENO / technická přejímka PASS; implementace, native/Chrome,13 CI a živé after PASS.
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset5--veřejný-kalendář-a-velodrom-10-11-2026).

## Rozsah a přesná shoda

Z booking/kalendar.php (individuální lekce) a booking/velodrom.php odstraněn
jediný duplicitní CDN Bootstrap5.3.3 tag. Společný lokální Bootstrap přes
appUiAssets() zůstává; booking/klubovy_kalendar.php je nezměněný control.
Payloady, manifest verzí, TTL, PHP/SQL logika, rezervace, role a kapacity stejné.

[Source kontrakt](native-11.4/exact-source-contract.json) proti immutable
37f1ba8f připouští pouze dvě přesná odstranění tagu a16 konkrétních vložených
závorek v kalendáři (8 párů). Ostatní PHP tokeny a celý literal HTML/JS text
jsou stejné. Povinné dlouhé řádky se rozdělily nulovými PHP hranicemi, které
nic nevypisují. Žádná obecná whitespace normalizace ani odstraňování skriptů.
[Sdílený kontext](native-11.4/shared-context.json) pinovaný na finální39f3f104:
všechny ostatní PHP soubory v obou variantách byte-for-byte stejné.

První native běhy42849413986 a42850619353 korektně selhaly: přesný HTTP diff
byly čtyři zdrojové mezery za inline SEO. PHP closing tag spotřebuje newline,
takže původní regex odstranil tag, ale ponechal jeho odsazení. Oprava odstraňuje
pouze přesné čtyři mezery + známý tag + jeho jeden newline, nebo samotný inline
tag. Negativní regresní test1/13 PASS: změna ceny, času, kapacity či skriptu
shodu poruší; jiný SRI/verze/počet kopií není maskovaný. Počáteční lokální1/12
a předběžný native42851391494 na38f6e9fb nejsou finální integrované měření.

## Finální native a skutečný Chrome

[Běh42853530222](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42853530222),
head39f3f1046bc495fc1981edf296c1d13a7134c8d1, společný základ zahrnuje
#438/f39f3157 i #436/4e3df207. Jejich schválené společné nasazení42852365591
SUCCESS bylo dokončené před naším samostatným release. Jejich věcné změny zachované.
[MariaDB10.3](native-10.3/source-identity.json) a [11.4](native-11.4/source-identity.json).

Na každé DB8 celých HTTP scénářů: kalendář, sportoviště101, prázdné999999,
prázdný měsíc2099-01-01, neplatný měsíc, velodrom, club control a registrace
control.17 střídavých párů po4 warmupech =272 měřených +64 warmup GET/DB.
[10.3 wholeHTTP](native-10.3/whole-http.json.gz),
[11.4 wholeHTTP](native-11.4/whole-http.json.gz): celé výstupy shodné mimo
přesný tag a request nonce/CSRF,350 business checksumů nezměněných na každé DB.
Tři anonymní velodrom POST akce (reserve/add_paid_to_cart/cancel) obou variant
vracejí303 login a nic nezapisují. Syntetická data jen ve vlastním disposable
schématu: sportoviště101/102, čtyři termíny900001–900004, lesson/public_velodrome,
placené i bezplatné. Žádné produkční rezervace ani přihlášení.

Apache + skutečný Chrome156.0.8078.79, desktop1036×703 a mobile390×844;
3 stránky ×2 viewporty ×cold/warm ×17 párů =408 měřených +96 warmup navigací.
24 funkčních variant: kalendářový modal/navigace měsíců, velodrom kapacita/login,
mobilní společná navigace. Šest dekódovaných pixelových párů, geometrie i
computed style shodné, žádná neočekávaná JS/network chyba; volitelná favicon
evidovaná zvlášť. Všechny načtené Bootstrap body mají přesný canonical SHA/SRI.
Žádný mock sítě ani umělá latence. [Souhrn](native-11.4/browser-summary.json),
raw vzorky a snímky ve stejném adresáři. Obě [10.3](native-10.3/cleanup.json) /
[11.4](native-11.4/cleanup.json) cleanup kontroly PASS: vlastní DB/user/config,
bridge účet i porty uklizené; zdroje nezměněné, soukromé artefakty vyloučené.

## Přínos před/po a jeho meze

Cold medians finálního Chrome běhu; bytes jsou encoded body pouze Bootstrap CSS.

| Pohled | Viewport | CSS bytes před → po | FCP ms před → po | Ready ms před → po |
| --- | --- | --- | --- | --- |
| Kalendář lekcí | desktop | 64454 →31248 | 60 →56 | 91,3 →91,1 |
| Kalendář lekcí | mobile | 64454 →31248 | 56 →52 | 91,8 →79,5 |
| Velodrom | desktop | 64454 →31248 | 52 →48 | 75,7 →73,8 |
| Velodrom | mobile | 64454 →31248 | 48 →48 | 75,6 →59,3 |
| Klubový control | desktop | 31248 →31248 | 56 →52 | 82,2 →75,9 |
| Klubový control | mobile | 31248 →31248 | 52 →48 | 75,6 →75,6 |

Robustní přínos: Bootstrap CSS2→1, decoded465606→232803 B; cold encoded
úspora33206 B v tomto CI běhu. Warm CSS network0→0 B, přesto odpadá duplicitní
dekódovaný stylesheet. FCP se zlepšilo i controlu, proto časový rozdíl
nepřipisujeme celý optimalizaci a neslibujeme procentní produkční zrychlení.
Regresní gate20 % +20 ms prošel. Serverové HTTP medians zůstaly ve stejném řádu,
např.11.4 calendar3,235→3,201 ms a velodrome2,567→2,549 ms; nejde o SQL optimalizaci.
[Všechna serverová měření](native-11.4/native-summary.json), včetně10.3 vedle.

Živý Chrome158/Windows baseline (čerstvě po předchozím vydání4e3df207,
2026-11-10T16:20:58.714Z) má jiné encoded CDN bytes:53957 B =22709 CDN +31248
lokální. Native má33206 CDN +31248 lokální. Prostředí nesměšujeme; dekódovaný
obsah/SRI je totožný. Produkční jednorázové captures nejsou časovým benchmarkem.

## CI, merge a další krok

[13 CI SUCCESS](release/app-checks.json) na přesném finálním39f3f104, včetně
2443 testů/56108 assertions, obou native DB a standardních smoke/read profilů,
PHPStan/stylu/docs/CodeQL. [PR #439](https://github.com/KovoPraha/evidenceTreninku/pull/439)
sloučen jako8182489e43d9f5013e02c58f2a8ccbc257b168ef;
[celý tested/merged tree](release/tree-identity.json) e40cdc717b5a3ee556ba92c69fb44e7aeae5943b
je přesně totožný. Release dokončený podle následujících důkazů; flags vlastní CURRENT.
Původní fio větev a WIP kis_rosters_admin.php SHA2567a7afd75 zachované.
Lidské přihlášené produkční UAT, fyzický telefon a obnova této zálohy NOT RUN.


## Produkční release a skutečná přejímka

[Deploy42855987339](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42855987339)
SUCCESS na8182489e; znovu2443 testů/56108 assertions. Záloha389 tabulek/2 triggerů
ověřená, SHA256 `c0bd1021501977e387f91bf64c47ee424d363085d91b83041bf2bde08663ac71`.
Konfigurace zachovaná bez doplnění, archivní klíč/záloha/webcopy PASS, pending
migrace [], dosavadní VS a definice/inventory zdravotního indexu shodné.
Runner se na síťové úrovni nedostal k webu (HTTP0); předepsaný serverový curl
fallback200 PASS. Nezávislý produkční Chrome6/6 GET200. Oba úklidy SUCCESS;
import přeskočený a oba flagsfalse.
[Receipt](release/release-controls.json), [celý workflow](release/deploy-summary.json),
[indexy](release/index-preflight/medical-index-before.json).

Živé [before](release/live-chrome/before.json) z4e3df207 a
[after](release/live-chrome/after.json) po8182489e: šest celých HTML otisků
shodných, skripty/data a všech pět obsahových asset verzí stejné. Maskuje se
pouze jeden přesný CDNtag a request nonce/CSRF; žádné obecné whitespace ani
odstranění skriptů. CSS2→1 na obou cílech, control1→1; cold encoded53957→31248 B
(úspora22709 B /42,1 % Bootstrap CSS), decoded465606→232803 B. Control31248 B
beze změny. Šest [GD pixelových párů](release/live-chrome/parity-and-pixels.json)
je přesně shodných, desktop/mobile bez přetékání, JS/network errors0. Chrome158
na Windows; šest jednorázových capture není produkční časový benchmark.
Reprodukce: [browser probe](release/live-browser-probe.js.txt), native příkazy
v .github/workflows/public-calendar-assets-performance.yml; finální artefakty
mají source-identity39f3f104. JSON/GZ obsahují bezpečné metriky/otisky, žádné
raw HTML, cookies, hesla, privátní konfigurace ani dumpy.

Přihlášené lidské produkční UAT, fyzický telefon a obnova této konkrétní zálohy
NOT RUN. Původní fio větev a WIP7a7afd75 zachované. Finální doklady: větev
codex/public-calendar-assets-release-docs-20261110, PR bude doplněn při otevření.
Dokumentační PR po všech CI sloučit; kvůli dokladům neopakovat deploy.
