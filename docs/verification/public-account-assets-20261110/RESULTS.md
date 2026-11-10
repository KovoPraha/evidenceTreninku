# P11-ASSET3 — veřejné vstupy k účtu

10. 11. 2026. NASAZENO / CÍLOVÉ TECHNICKÉ OVĚŘENÍ PASS.
[PR432](https://github.com/KovoPraha/evidenceTreninku/pull/432) sloučen jako
beead42591c1f84c66f42c411ce6f1681c02a16a. Celý tree19f2e90e je identický s
testovaným58e97f557e691fad436325d6d7c80a6ea2fae163; všech[13CI SUCCESS](app-checks.json),
PHPUnit2425/55924, PHPStan/style, CodeQL a obě standardní DB matice PASS.
[Tree proof](tree-identity.json), [plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset3--veřejné-vstupy-k-účtu-10-11-2026).

## Změna a přesná shoda

Runtime pouze booking/prihlaseni.php, registrace.php, sportovec_prihlaseni.php,
zapomenute_heslo.php: odstraněn jeden explicitní CDN Bootstrap CSSlink na každé
stránce, verzovaná lokální CSS z appUiAssets() zůstává. Obnova i ?pro=trener.
Povinné PSR12/line200 formátování pouze uvnitř PHP segmentů; významové tokeny a
ostatní literal HTML přesně shodné. Jen4 přesně uvedené legacy if guardy registrace
mají doplněné závorky. Bez změny SQL, autentizace, CSRF, CSP, JS, CSS či cache.
[Přesný kontrakt](native-11.4/exact-source-contract.json) a
[kanonické source hashes](native-11.4/source-identity.json) obou DB ověřené proti Git blobům58e97f55.

## Nativní HTTP a skutečný browser

[MariaDB10.3](native-10.3/native-summary.json) a[11.4](native-11.4/native-summary.json)
v run42808731461 SUCCESS. Každá má vlastní ověřený schema/user/config, skutečné PHP
workers a Apache/gzip. Before fyzická kopie booking/ i includes/, čtyři soubory
obnovené z přesného base01b2ca38; žádný symlink na optimalizovanou booking/.
Vše mimo vybrané runtime soubory zůstává stejné. Bez produkční konfigurace či dat.

10 GET variant (včetně návratu/expirace, tří účelů registrace, obou obnov a control),
17 střídavých párů po4warmupech:340měřených+80zahřívacích odpovědí na každou DB.
Celý HTML po normalizaci jen přesně známého CDNtagu, nonce a CSRF shodný;
10 dalších POST odpovědí s neplatným CSRF také shodných. Všech350 tabulkových
checksumů beze změny; oba úklidy schema/user/config/ports a zdrojů PASS.
[Celé HTTP10.3](native-10.3/whole-http.json.gz), [11.4](native-11.4/whole-http.json.gz).
GET stránky neprovádějí SQL datové agregace; škálování sportovní historie1k/30k
z předchozích řezů zde není výkonovým parametrem. Standardní read-profile CI přesto prošlo.

Chrome156.0.8078.79, šest variant včetně nezměněné control,1036×703 a390×844,
cold/warm,17párů/4warmupy:816měřených+192zahřívacích navigací. Cold znamená
vypnutou/vyprázdněnou resource cache; spojení/DNS se mohou znovu použít.
Skutečné CDN bez mocků, throttlingu či přidané latence. FCP skutečný PaintTiming,
ready po load a dvou rAF, nikoliv uměle započtená networkidle pauza.
Bootstrap API, všechny přijaté CSS payload hashes proti canonical CSS/SRI,
computed styles/geometrie mezi variantami i cachemódy PASS. Všech12pixelových
párů přesně stejných;48 funkčních scénářů včetně mobilní navigace, účelů a
data narození registrace, obou password toggles a client validity prázdných
formulářů PASS. Žádné JS/network chyby; jen automatická volitelná favicon404
odděleně. Skutečná registrace/obnova/přihlášení se těmito browser scénáři neodesílá.
[Snímky mobilu](native-11.4/account-assets-registration-mobile-after.png),
[kompletní mediány](native-11.4/browser-summary.json); detailní24 gzipJSON vedle.

## Přínos před / po

| Metrika | Před | Po | Rozdíl |
| --- | ---: | ---: | ---: |
| Změněné stránky: Bootstrap requests | 2 | 1 | −1 externí požadavek |
| Canonical decoded Bootstrap CSS | 465606 B | 232803 B | jedna kopie místo dvou |
| Finální CI cold Bootstrap encoded payload | 64454 B | 31248 B | −33206 B / −51,52 % |
| Finální CI warm CSS síťový payload | 0 B | 0 B | obě cache hit |
| Nezměněná control cold Bootstrap | 31248 B | 31248 B | bez změny |

Přenos je pouze Bootstrap CSS, nikoliv celý waterfall. V produkčním Windows
Chrome before bylo[53957B](live-before.json):22709B CDN(br)+31248B local(gzip),
shodný decoded payload/SRI. Finální CI dostalo33206B CDN(br) se stejným
canonical decoded hash. První diagnostický browser run42807965443 dostal také
22709B. Velikosti CDN reprezentací se tedy mezi běhy lišily; jednotlivé páry
se neslévají a nastavení sítě/komprese jsme neměnili. Živý after31248B: produkční−22709B/−42,09%; viz [release a meze](release/RESULTS.md).

| Stránka / viewport | Cold FCP před→po | Cold ready před→po | Warm FCP před→po |
| --- | ---: | ---: | ---: |
| Přihlášení / desktop | 52.0→52.0 ms | 74.9→75.1 ms | 36.0→32.0 ms |
| Přihlášení / mobile | 52.0→44.0 ms | 75.1→74.9 ms | 32.0→32.0 ms |
| Registrace / desktop | 56.0→52.0 ms | 81.0→75.3 ms | 36.0→36.0 ms |
| Registrace / mobile | 52.0→48.0 ms | 85.9→75.6 ms | 36.0→36.0 ms |
| Sportovec / desktop | 52.0→52.0 ms | 75.3→59.0 ms | 36.0→32.0 ms |
| Sportovec / mobile | 48.0→48.0 ms | 75.6→59.2 ms | 32.0→32.0 ms |
| Obnova hesla / desktop | 52.0→52.0 ms | 75.1→58.7 ms | 36.0→32.0 ms |
| Obnova hesla / mobile | 48.0→48.0 ms | 75.0→59.0 ms | 32.0→32.0 ms |
| Obnova trenéra / desktop | 52.0→52.0 ms | 75.3→59.3 ms | 36.0→32.0 ms |
| Obnova trenéra / mobile | 48.0→48.0 ms | 75.5→59.3 ms | 36.0→44.0 ms |
| Ověřovací odkaz (control) / desktop | 52.0→52.0 ms | 73.2→72.0 ms | 48.0→48.0 ms |
| Ověřovací odkaz (control) / mobile | 48.0→48.0 ms | 59.0→59.2 ms | 44.0→36.0 ms |

Všechny předem dané20%+20ms nonregression gates PASS. Výrazné celkové FCP
zrychlení není prokázané; většina cold stránek FCP stejné, login mobile−8ms,
registrace−4ms. Warm trenérská obnova mobile36→44ms, současně control44→36ms;
neprohlašujeme každý čas za zlepšení. Ready část scénářů o jeden rámec nižší,
login stabilní. Syntetické časy nejsou produkční SLA ani test mobilní sítě.

## Průběh a release

První de6c CI nepřijato: formátování šablon nebylo idempotentní a zalomení
rozdělilo literal minlength ve stávajícím password testu. Opraveny pouze hranice
formátování; testy a jejich pravidla nezměněné. Přijatý je výhradně nový58e97f55
se všemi13 SUCCESS a novými oběma nativními/browsovými výsledky.

[Deploy42810948814](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42810948814)
SUCCESS na přesném merged SHA; import/UATfalse. [Cílové live HTML/CSS PASS a omezení doplňkového katalogového probe](release/RESULTS.md).
Přihlášené produkční/lidské UAT a obnova právě nové zálohy: NOT RUN.
Původní WIP kis_rosters_admin.php7a7afd75 a jeho větev zachované;
HJS/RIX prototypy nepřevzaté. Finální docs mají vlastní větev/PR bez druhého deploye.
