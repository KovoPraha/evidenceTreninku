# P11-ASSET — Bootstrap CSS načítaný právě jednou

Zahájení 9./10. 11. 2026, Europe/Prague. NASAZENO / TECHNICKÁ PŘEJÍMKA PASS. Vlastník vývoj/Codex.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md),
[důkazy](verification/bootstrap-assets-20261110/RESULTS.md).

## Zadání a výběr

Aktuální pokyn vlastníka: další optimalizace s největším doloženým přínosem,
shoda výsledků, před/po; při plném PASS commit/merge/protected deploy.
WIP kis_rosters_admin.php v původním htdocs SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Pracovní větev codex/athlete-targeted-probe-20261109, následně vlastní PR425
codex/athlete-targeted-read-optimization-20261109; izolovaný worktree mimo htdocs.
Baseline ff182971; výchozí produkce byla17110a7f/deploy42679050552.
Finální runtime9dbd9eaf/deploy42727693090 SUCCESS, viz release protokol.

[Předchozí RIX diagnóza](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md) vyřadila
SQL/index varianty kvůli regresím nebo nedostatečnému zisku na obou DB.
Původní reader je obnoven přesně z ff182971. Nepřidávat spekulativní index.
Další skutečný nález původní analýzy bod7: sportovec_treninky.php,
moje_treninky.php a sprava_treninku.php načítají Bootstrap5.3.3 z CDN i lokálně.
Canonical Git lokální CSS je232803bytes a SHA384
QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH,
přesně stejný jako SRI duplicitního CDN linku. appUiAssets už poskytuje lokální
CSS na všech3cestách; profiler/body layout se nemění odstraněním první kopie.
Ověřit také skutečný lokální/CDN payload přes HTTPS a skutečný Chrome.

## Rozsah a akceptace

1. Odstranit pouze3 známé duplicitní CDN stylesheet linky, zachovat lokální
   appUiAssets a veškeré další HTML/PHP/JS/CSS. Žádné DB/SQL/writery/role/session/
   tokeny/CSRF/cache konfigurace. Po odpočtení těchto3konkrétních tagů musí být
   canonical zdroje identické s ff182971; reader byte-shodný celý.
2. Celé HTTP před/po na stejném syntetickém serveru/schema oběMariaDB10.3/11.4,
   původní business kontroly, canonical checksumy, 1k/10k/30k. Parent HTML po
   odstranění POUZE známého CDN tagu při současné právě1lokální kopii identické.
   Žádné obecné ignorování link/script atributů; negativní test normalizátoru.
   Uložit i raw bytes/hash. Oprávnění/revokace/bearer/CSRF staré security PASS.
3. Skutečný Chrome, žádné request mocky ani umělé zpomalování CDN. Přímé měření
   v1kfixture (řádově blízká produkčnímu odhadu1667tréninků),3stránky desktop/
   390px a samostatný read-only bearer profil.17střídavých párů po4warmupech
   cold/warm; reálný Resource Timing/CDP, FCP, load a hotový obsah, konzole.
   Bootstrap stylesheet2→1 a redundantní232803decoded bytes odstraněné v cold
   scénáři; žádný CDN Bootstrap request po změně. CSS payload přesně stejný.
   Časové úspory zveřejnit skutečné, neslibovat rychlejší SQL/server. Cold/warm
   FCP a hotový obsah nesmějí významně regresovat (po<před*1.2+20ms).
   Tento frontendový gate nahrazuje neúspěšný SQL20%gate: nový cíl je odstranění
   poloviny Bootstrap CSS požadavků/bajtů, nikoli vymyšlené zrychlení DB.
4. Shodný viditelný obsah, computed styles a desktop/mobil screenshoty; dropdown/
   navbar/tab/pagination fungují, Bootstrap JS se nemění. Dále skutečný Chrome
   default/uložené Měření velkého profilu jako kontrola starých UI toků.
5. Všechny aktuální CI SUCCESS, exact source/tree identity, pak squash PR425
   a protected deploy z main. Úplná záloha/migrace/VS/config/smoke a veřejné
   čtecí před/po; import/UATfalse, lidské produkční UAT samostatně NOT RUN.
   Finální důkazy docs PR bez druhého deploye. Nic nepovolovat podle HTTP200.

## Historie přípravy a nepřijatých běhů

Následující záznamy jsou historické; aktuální vydání je potvrzené nahoře a v release protokolu.

Další krok: přesný tag removal, úzký testovací normalizátor a nativní HTTP/
Chrome profil. Implementace/CI/merge/deploy optimalizace dosud NOT RUN.


První příprava42718340757/f9 selhala správným302 trenéra na hlavni-only stránce.
Přejímka3stránek poběží pod existujícím sports_lead, veřejný bearer samostatně;
žádná změna oprávnění. Model3/562 a normalizátor1/6 oběDB PASS, cleanup PASS.
Časová měření a app merge/deploy pořád NOT RUN. CLI bloky34vzorků zachovají
17střídavých párů/4warmupy každého cold/warm/viewport/route.

Druhý42718778899 zastaven na správném403 vedoucího sportu u trenérských Moje.
Oprava měřicího scénáře respektuje samostatné pracovní pozice: coach profil/
Moje, sports_lead Správa, samostatný public bearer; žádné slučování rolí.
Negativní302/403 se nově ověřují před/po. Stále žádné app merge/deploy.


### Aktualizace 10. 11. — přejímka před merge
Pátý běh42720302527/ce znovu dokončil shodu celéhoHTML všech3stránek,
ale selhal chybný testovací předpoklad: sports_lead může po inicializaci
kontextu dostat Moje200 již před změnou. Starší302/403 zde neprokazují
univerzální zákaz. Nově se pro tento cross-context vyžaduje přesná shoda
statusu/Location před/po, bez předem vymyšleného zákazu. Coach Správa a oba
anonymní vstupy musejí zůstat odmítnuté a shodné. Role/guardy se nemění.

PR CI b8 odhalilo statický SharedUiShell test požadující vlastníCSS i tam,
kde je již zaručeně emituje hlavicka. Výjimka je úzce pro2měřené stránky;
zároveň se ověřuje canonical Bootstrap v helperu a jeho volání v hlavičce.
CodeQL našel nepřesný hostname substring v testu: nahrazen přesným URL.hostname.
PHPStan PASS. PHPUnit má navíc4chyby/2selhání ShopDocumentAutomation mimo
CSS změnu: konfigurace pravidla používá Europe/Prague, SQLite CURRENT_TIMESTAMP
UTC. Běh23:23UTC překročil českou půlnoc. Nutné zvlášť ověřit baseline;
produkční účetní kód se v rámci optimalizace nemění. Nic se neslučuje ani
nenasazuje při červených kontrolách. Model3/562, normalizátor1/6 a cleanup
pátého běhu PASS oběDB; kompletní Chrome a výkon dosud nejsou přijaty.


### Kořen příčiny role kontroly (šestý běh42721621744/731 FAIL)
Předchozí vysvětlení o inicializovaném kontextu bylo nepřesné. staffCurrentRoute
porovnává realpath(SCRIPT_FILENAME) s db.php __DIR__. Baseline root měl db.php
symlinkovaný do druhého rootu; pro zkopírované3rodiče tak workspace guard
vracel route=null. Žádná změna produkčního oprávnění: chyba izolace A/B harness.
Baseline nově dostane fyzickou, byte-shodnou kopii db.php z ff182971;
main db.php musí být zároveň identický. Oba kontexty coach/Správa a lead/Moje
opět musejí být odmítnuté se stejným statusem/Location, plus anonymní Moje/Správa.
Šestý ani předchozí asset běhy nejsou platnou kompletní přejímkou. Jejich
model/CSS/source/cleanup důkazy zůstávají, timing se musí zopakovat s opravou.
RIX pokusy s odděleným symlink baseline jsou rovněž pouze zamítnutá diagnóza,
nikoli důkaz ke sloučení. Čerstvý main PHPUnit42721710594 má stejné účetní
selhání během české půlnoci jako PR. Čeká ověření přesného logu a fixture času.


### Ověření půlnočního selhání a korekce fixture
Main ff182971 run42721710594 reprodukoval přesně stejné6účetní případy:
2420testů/55582assert/4errors/2failures. Není to regrese CSS. SQLite fixture
nově definuje vlastní CURRENT_TIMESTAMP v Europe/Prague shodně s testovaným
obchodním mandátem; žádný globální ani produkční čas/kód/DB se nemění.
Stejný cílený test během stejného půlnočního intervalu nyní12/233 PASS.
SharedUiShell+normalizátor16/98 PASS lokálně. [Záznam](verification/bootstrap-assets-20261110/midnight-test-isolation.json).

Sedmý run42722027988 byl zrušen během přípravy runneru před profilem: k fyzické
baseline kopii db.php doplněn symlink POUZE na vlastní syntetický config.php.
Osmý42722121950/6db běží se správným kořenem/guardy/configem; app zdroje pořád
přesně3smazané linky. Bez celého CI/browser/gate nic neslučovat ani nenasadit.


### HTTP10.3 PASS, další izolované testovací opravy
Osmý42722121950/6db: MariaDB10.3 komplet PASS, whole HTML3parent/16AJAX
scénářů ×3velikosti, shodné role odmítnutí, security i checksumy, cleanup.
11.4 skončil na CLI callback syntax (trailingsemicolon u výrazu), opraveno.
Devátý42722418721/d9 došel reálným Chrome prvním blokem, ale skončil na
console404. Doplněna URL do každé chyby a úzké oddělení pouze automatického
Chromium favicon.ico404 (soubor není v repu ani uveden v HTML). Ostatní chyby
se nezakrývají, žádný síťový mock ani změna app. Kompletní Chrome nepřijat.
PHPStan/style d9 PASS. PHPUnit2424/55907 má1selhání: PHP UTC retry versus
Prague SQLite clock. Fixture teď nastaví i PHPtimezone a vždy obnoví původní;
při explicitním -d date.timezone=UTC cíleně12/233 PASS. Produkční kód beze změny.
Osmé/deváté safe artefakty zachované. Staré queued/in-progress PR běhy6db/9e
zrušené jako překonané po d9, nepočítají se jako PASS. Nový aktuální head musí
prokázat celé CI i Chrome; merge/deploy dosud NOT RUN.


### Poslední přejímka — nový fyzický baseline root
Tenth42723243006/0915 a eleventh42723878203/c181: profil desktop/mobil
cold/warm136vzorků PASS, CSS2→1 a computed styles shodné; první screenshoty
pixelově identické. 404 bylo potvrzené pouze automatické favicon.ico Chromium,
explicitně evidované mimo chyby aplikace, nikoli obecně ignorované404.
Diagnostika c181 opravila původní domněnku o timeoutu navigace: Moje AJAX500
kvůli neúplné syntetické fixture (legacy treninky.mereni/tagy). Použit stávající
CoachHistoryHttpProfile prepare a nově celý mine_ajax fragment v HTTP shodě.
Test navigace navíc správně sleduje data-bs-toggle offcanvas/collapse a skutečný
target místo předpokladu navbar-collapse; obě akce musí otevřít a zavřít panel.

Dvanáctý42724386359 a třináctý42724572553 nahrazené/zrušené; nejsou PASS.
Baseline fyzicky kopíruje kořenové/includes PHP, nikoli jejich symlinky: jinak
hlavicka přes __DIR__ znovu spouštěla bootstrap druhého rootu. db.php i hlavicka
musí být byte-shodné s ff182971, assets zůstávají sdílené kvůli shodným verzím
filemtime. To odstraňuje i umělé náklady druhého DB bootstrapu v A/B. Čerstvá
platná přejímka je až42724887655/12092dd1 (obě DB), která nyní běží.
Aplikační diff stále jen3odstraněné CDN tagy; SQL/header/auth/role beze změny.
PHPUnit0915 plná sada PASS; finální aktuální CI následuje, staré překonané běhy
zrušené k uvolnění runnerů. Merge/deploy pořád NOT RUN. Žádný předchozí
nekompletní běh se nepočítá jako release přejímka.

## Aktuální přejímka a vydání

30CI/2424testů/55908assertions, oba čerstvé fullHTTP/Chrome běhy PASS,544nav
+52regresních,8/8pixelpárů shodných. FCP cold7–35%lepší, CSS2→1/−30829gzipbytes.
Main425/9dbd9eaf tree-shodné s6162; deploy42727693090 SUCCESS, import/UATfalse.
Záloha389/2, konfigurace/migrace/VS/indexy/smoke a živé8GET/DOM PASS.
Metadata CLI hosting403 stejně jako migrate; native PHP guard404/0.
Finální důkazy sdílí docs426 bez druhého deploye. Lidské produkční UAT/restore nové zálohy NOT RUN.
[Finální metriky](verification/bootstrap-assets-20261110/RESULTS.md),
[release](verification/bootstrap-assets-20261110/release/RESULTS.md).


## P11-ASSET2 — pracovní rozcestník, nový řez 10. 11. 2026

ROZPRACOVÁNO před implementací. Vlastník povolil další nejpřínosnější optimalizaci,
shodu a před/po měření, commit/merge/deploy poPASS; WIP zachovat.
Mainfaed76c0ee94d210c99f67a20a92604da8f61206, runtimef682cc46/deploy42774356221.
Větev codex/workspace-bootstrap-assets-20261110, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.

Čerstvý reálný Chrome/Apache v HJS2run42783645170 ukázal2CSS requests na
pracovni_pozice.php: /assets/vendor/bootstrap/bootstrap.min.css a totožný?v=...
Oba200/gzip, canonical232803 decodedB každý, BootstrapAPItrue, žádnéJSchyby.
Přímý zdroj82 má ručnítag,83 appUiAssets() stejnouCSSvkládá znovu; společná
hlavička na92 jej volá idempotentně. Nativní rootHTML tento skutečný CSSrequest
nepoznalo, protože původní guard počítal pouze verzované URL. Celý jeden CSS
přenos má větší doložený potenciál než3.3–3.6kB HTMLgzip v nevybraném HJS2.
Původní ASSET3stránky i JSZ zůstávají dokončené; HJS2/PR429/prototyp zachované,
bez jejich PHP/JS/JSON/JSZhelper změn v tomto řezu.

Plán: odstranit pouze redundantní ruční CSSlink na pracovním rozcestníku.
Ponechat společnou verzovanouCSS iJS, pořadí ostatníchCSS, inlinehlavičku,
CSP/CSRF/SQL/práva/session a všechny ostatnístránky. Neodstraňovat další nalezené
linky bez aktuálního důkazu duplicitní skutečné odpovědi.

Přejímka: sourcecontract jediná smazanálinka, canonicalCSSbytes/SRI shodné,
celý HTTP před/po po normalizaci jen této konkrétnílinky a nonce/CSRF. Vlastní
čerstvé MariaDB10.3/11.4, 1k/10k/30k,17párů/4warmupy, role odmítnutí,
profile revokace/bearer/logout a doménovéchecksums/cleanup. Reálný Apache/gzip
před4vlastníPHPworkers oběma, cachepolitikastejná; žádnýmock/latence.
Chrome coachworkspace+Moje, leadSpráva, publicprofil desktop/mobilecold/warm,
17střídavýchpárů/4warmupy; workspaceCSS2→1, ostatní1→1, payloadhash všech
CSSstejný, warm skutečnězměřit,8pixelpairs/computedstyle/nav/confirm/form PASS.
ColdCSSpayload nižší o jeden celýgzip stylesheet; FCP/ready bezregrese přes
20%+20ms, publiccontrol bezezměny. Časovýzisk netvrdit, pokud není prokázaný.
CeléaktuálníCI aidentickýtested/mergedtree před protecteddeploymain s oběma
flagsfalse, backup/config/migrace/VS/index/smoke a nezávislé GET/DOM/CSS před/po.
FinálnídocsPR bezdruhéhonasazení; původní WIP7a7afd75 vždyshodný.

Nové výsledky/implementace/PR/release zatím NOT RUN.
[Důkazy ASSET2](verification/workspace-bootstrap-assets-20261110/RESULTS.md).


ASSET2 průběžně: repository style vyžaduje opravu starých dlouhých řádků
celého dotčeného souboru. Formátování proto pouze uvnitř PHP, bez změny emitted
HTML; explicitní závorky dosavadního if/continue guardu. WorkspaceAssetsSourceContract
ověří všechny PHP tokeny a ostatní literalHTML, nativní wholeHTML gate beze změny.
První201e CI nepřijatý (fixture tuple/style), nový gate následuje; žádný release.


### P11-ASSET2 — finální stav

NASAZENO / TECHNICKÉ OVĚŘENÍ PASS: PR430 /706a8d429197c779c36ffc333751b77ee1f9ba14,
celý tree shodný s156a9597 /13CI SUCCESS; PHPUnit2425/55914. Deploy42790212078
SUCCESS, import/UATfalse, backup389/2, config/VS/migrace/index/smoke PASS.
Syntetický Apache/Chrome2→1BootstrapCSS, cold−31248gzipB, FCPdesktop60→52ms,
mobile56→52ms; warm FCP/payload bezezměny, výrazný ready-time zisk neprokázaný.
Strict source/wholeHTML, oběMariaDB, 544+128Chrome,8pixelpairs/24UIscénářů PASS.
[Finální release a meze](verification/workspace-bootstrap-assets-20261110/release/RESULTS.md).
Produkční8GET/DOM a CSSbytes/SRI/cache shodné; authenticatedproductionUAT NOTRUN
kvůli2timeoutům browserAPI bezUIzměn. WIP7a7afd75 zachovaný, HJSprototypybezmerge.
Tento řez je technicky hotový; finální docsbez druhéhonasazení. Dalšíkandidát
musí znovu doložit aktuální waterfall a respektovat nejslabší cold/control gate.


## P11-ASSET3 — veřejné vstupy k účtu, 10. 11. 2026

ROZPRACOVÁNO před implementací. Vlastník po opravě omylem položené otázky
výslovně pokračuje v optimalizaci; platí ověřit shodu, změřit před/po a po PASS
commit/merge/deploy. Vlastník práce Vývoj/Codex. Base main
01b2ca386a221f34ed6300148192c9af2cf4e7d9; dosavadní runtime706a8d42,
deploy42790212078 SUCCESS. Větev codex/public-account-assets-20261110,
izolovaný worktree C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní kis_rosters_admin.php/WIP7a7afd75 zachovat; HJS/RIX prototypy nepřebírat.

Rozsah: booking/prihlaseni.php, registrace.php, sportovec_prihlaseni.php,
zapomenute_heslo.php. Každá má explicitní Bootstrap5.3.3 CDN CSS se SRI a zároveň
appUiAssets() s verzovanou lokální kopií. Obnova zahrnuje i ?pro=trener.
Odstranit jen přesně tuto duplicitu, ponechat ostatní pořadí stylů a skriptů.
Při povinném formátování zachovat všechny významové PHP tokeny a emitted HTML;
nutné legacy guard závorky uvést jednotlivě v source contractu.

Důvod: další současné veřejné duplicity z bodu7 původní analýzy; čtyři vstupy
mají větší zásah než jednotlivá interní error stránka, menší riziko než defer
společného JS s inline závislostmi. Kalendář/velodrom zatím mimo tento řez.
Ověření skutečného produkčního waterfallu a canonical CSS/SRI je první gate.

Akceptace: jediná známá smazaná CSS značka, jinak identické source tokeny a
literal HTML; stejný wholeHTTP po normalizaci pouze té značky, nonce/CSRF.
Vlastní izolovaná MariaDB10.3/11.4, skutečné PHP a Apache/gzip; opakované
střídavé páry GET/variant účelů a chybějící CSRF bez doménových zápisů.
Chrome desktop/mobile cold/warm,17párů/4warmupy, skutečné CDN bez mocků či
umělých zpoždění, lokální payload stejný jako CDN/SRI. CSS2→1 vs control1→1,
nižší cold přenos, stejné computed styles/geometrie/pixely a navigace/formuláře.
FCP/ready bez regrese přes20%+20ms; neprokázané zrychlení netvrdit.
Celé CI, identický tested/merged tree, protected deploy pouze main, flags
importovat_krouzky=false / uat_schvaleno=false; backup/config/migrace/VS/index/smoke
a nezávislé live GET/CSS/Chrome před/po. Žádné přihlašování ani odesílání
produkčních formulářů. Lidská přejímka odděleně; finální docs bez druhého deploye.

Závislosti: stávající syntetická FullHttp fixture, Apache front, skutečné CDN,
Chrome CLI, GitHub CI a chráněné nasazení. Další krok: zachytit živý before,
implementovat přesné odstranění, uložit výsledky do
[ověření ASSET3](verification/public-account-assets-20261110/RESULTS.md).
Implementace, nové testy a nový deploy zatím NOT RUN.

Průběžně PR432: první nativní10.3 PASS (340 měřených +80warmup GET,10CSRF odpovědí,350tabulek shodných). První de6c CI nepřijato: style nebyl po zalomení šablon idempotentní a jedno zalomení rozdělilo literal minlength ve stávajícím testu. Opraveny pouze hranice formátování; testy a source/wholeHTML gate nezměněné. Nově místně exact source4/4, style dry-run6/6, password policy10/35 a další8/88 kontrol PASS. Nový celý CI následuje, release NOT RUN.


### P11-ASSET3 — měření přijato, nasazování

PR432/beead425, celý tree identický s58e97f55,13CI PASS; PHPUnit2425/55924. OběDB wholeHTTP/350checksumů/CSRF a cleanup PASS. Chrome816+192nav/12pixelpairs/48UIscénářů bez chyb; coldCI CSS64454→31248B (−51,52%), produkčníbefore53957B kvůli jiné CDNbr reprezentaci. Časový zisk omezený, viz [aktuální výsledky](verification/public-account-assets-20261110/RESULTS.md). Deploy42810948814 běží na mergedSHA, flagsfalse; WIP zachovaný. Další krok živé GET/CSS/Chrome a finální samostatný docsPR.


### P11-ASSET3 — finální stav

NASAZENO / CÍLOVÉ TECHNICKÉ OVĚŘENÍ PASS. PR432/beead425,13CI a deploy42810948814
SUCCESS, import/UATfalse; záloha389/2, config/VS/migrace/index/smoke200 PASS.
OběDB wholeHTTP/350checksums/CSRF, Chrome816+192nav/12pixelpairs/48UI PASS.
Produkční5cílových variant CSS53957→31248B (−42,09%); control1→1. Šest celých
HTMLhashů po přesném replay5známých asset verzí shodných,10old/newassetGET canonical
bytes/cache shodných. Cílové UI/zdroj neměněné mimo4CSSlinky a povinný PHPstyle.
[Release a meze](verification/public-account-assets-20261110/release/RESULTS.md).
Doplňkový8GETcatalog probe:8stavů shodných, DOM5/8;3cataloghashy odlišné a after
stabilní, příčina zpětně nedoložená. Neprohlašovat katalogovou paritu za PASS.
Auth/lidsképrodUAT NOTRUN, WIP7a7afd75 zachovaný; finální docs bez druhého deploye.
Další kandidáti: čerstvý waterfall veřejného kalendáře/velodromu a stabilní
assetverze místo filemtime (vlastní nový gate). Před dalším katalogovým řezem
nová strukturovaná baseline s dílčími otisky, nikoliv pouze starým agregovaným hashem.

Finální důkazy předány v [PR433](https://github.com/KovoPraha/evidenceTreninku/pull/433); docs větev codex/public-account-assets-release-docs-20261110. Merge až po celémCI; bez druhého nasazení.


## P11-ASSET4 — katalogová diagnóza a další měřený řez, 10. 11. 2026

**NASAZENO / nová technická přejímka PASS; historická důkazní mez LIMIT.**
Vlastník Vývoj/Codex, zadavatel Marek; pověření: prověřit tři rozdíly, vybrat
nejpřínosnější řez, měřit/paritu ověřit a po PASS commit/merge/deploy. Původní WIP
zachovat. Výchozí main `50ce79b2`, běžící aplikace beead425; vlastní implementační
větev codex/stable-ui-assets-performance-20261110 a izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-ui-cache-performance-20261110.

### Rozhodnutí a rozsah

Zachovaný obsah i TTL pěti společných CSS/JS, offline SHA-256 manifest místo
filemtime; žádné hashování při GET. Kontrola generovaného manifestu v PHPUnit
i stávajících deploy testech. LF pravidlo pouze pěti payloadů zajišťuje shodu
canonical bytů na Windows/Linux. Spuštění po skutečné změně souboru:
`php tools/build-ui-asset-versions.php --write` (ověření --check).
Opraven jen whitespace zdrojové nonce značky katalogového fallbacku; CSP guard
zůstává beze změny. Žádný nový SQL/index, změna fotografie ani datová cache.

Největší cold položkou jsou fotografie (desktop 1208278 B). Zvažovaná lossless
WebP varianta čtyř fotek zachovala pixely, ale 1132857→2004806 B: zamítnuta.
Vybraný společný manifest působí napříč stránkami při dalších vydáních beze
změny obsahu; přísná shoda pixelů i dat je zachovaná. Alternativní kalendářová
duplicita má menší a lokální přínos, zůstává dalším kandidátem.

### Audit a průběh přejímky

Čtecí audit #434 v main `fb593ad4`, 11 CI PASS; chráněný běh 42826637716 SUCCESS
včetně read-only transakce, rollbacku, shody konfigurace i úklidu. Zjištěno
49 veřejných událostí fotografií a šest zdrojů shodných s canonical main.
Starý parametr stranka=2 opakoval první stránku; nový probe už používá strana=2.
Původní before HTML není uchované. Časová shoda fotografií ani bounded replay
jejich podmnožin neposkytly úplnou příčinu obou odlišných pohledů; **původní tři
hashové rozdíly se neoznačují za vysvětlené či PASS**. Nová baseline/per-card
otisky, opakování a následné vydání jsou naopak přísně shodné ve všech 5 pohledech.

První CI opravilo chyby kontrolních nástrojů: metadata manifestu zaměněná za
JS include a vývojový dotfile ve společné fixture. Výstup pěti URL právě jednou
má skutečný subprocess test. Kontrola mutace CSS respektuje běžnou revalidaci
PHP mimo benchmark a navíc ověřuje hash skutečně staženého nového obsahu.
Žádné oslabení CSP/SQL/role/parity gate ani umělá síťová latence.

### Výsledek a release

[Kompletní měření a release](verification/catalog-audit-assets-20261110/RESULTS.md):
MariaDB 10.3/11.4, celé HTTP 9 pohledů, 345 business checksumů shodných; 408
měřených Chrome navigací a 4 pixelové páry PASS. Metadata-only release 78508→0 B
v rámci JS TTL; po skutečném 31s vypršení 78508→26618 B (CSS úspora 51890 B).
Cold 78508→78508 B a warm 0→0 B, původní TTL CSS 604800 s / JS 30 s. Skutečná změna CSS
invalidovala právě jednu URL se správným obsahem, fallback opravil 12 vadných
syntetických obrázků. Native run 42833762133 SUCCESS; všech 19 CI na d439f5db.

PR #435 sloučen jako `94c10708`, celý testovaný/sloučený tree `0aa9d163` shodný.
Deploy 42837011604 SUCCESS, znovu 2432/55977 testů/kontrol, záloha 389/2, config,
migrace, VS, indexy, HTTP 200 i úklidy PASS. Živé katalogové 5/5 celé HTML/karty
a opakování shodné; Chrome desktop/mobile 4/4 decoded pixel pairs shodné,
pět payloadů/cache headerů canonical. Při místní libcurl chybě použit explicitní
system-curl transport bez změny obsahové kontroly. Přihlášené lidské UAT,
fyzický mobil a obnova této zálohy NOT RUN; flags a runtime vlastní CURRENT.

Finální evidence ve větvi codex/stable-ui-assets-release-docs-20261110;
[PR #437](https://github.com/KovoPraha/evidenceTreninku/pull/437). Žádný druhý deploy kvůli dokladům/test helperu.
WIP původní fio větve / kis_rosters_admin.php SHA-256 `7a7afd75` zachovaný.
Další kandidát KONCEPT: duplicitní Bootstrap CSS veřejného kalendáře a velodromu,
samostatná nativní/pixelová přejímka; fotografie se bez přijaté shody nemění.


## P11-ASSET5 — veřejný kalendář a velodrom, 10. 11. 2026

**NASAZENO / technická přejímka PASS.** Výchozí plán a checkpointy níže jsou historické. Vlastník Vývoj/Codex, zadavatel Marek. Zadání: odstranit duplicitu
CSS, ověřit shodu a přínos před/po, při PASS commit/merge/deploy; zachovat WIP.
Base main37f1ba8f, runtime94c10708 / deploy42837011604; flags vlastní CURRENT.
Větev codex/public-calendar-assets-performance-20261110, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-calendar-assets-performance-20261110.
Původní fio větev a kis_rosters_admin.php SHA2567a7afd75 ponechat bez zásahu.

Nález: booking/kalendar.php (individuální lekce) a booking/velodrom.php obsahují
CDN Bootstrap5.3.3 i místní identický Bootstrap přes appUiAssets(). Klubový
booking/klubovy_kalendar.php duplicitu nemá, bude nezměněným control pohledem.
Vybrána pouze dvě odstranění CDN tagu. Payloady, manifest obsahových verzí,
TTL, PHP/SQL logika, rezervace, role, kapacity a zabezpečení se nemění.

Postup: veřejná GET/Chrome baseline; přesný source kontrakt dvou odstraněných
tagů a jen povinného formátování/závorek; izolované MariaDB10.3/11.4 celé HTTP,
business checksumy, odmítnutí anonymních POST; skutečný Apache/Chrome desktop
a mobile, cold/warm, 17 střídavých párů po 4 warmupech. Porovnat celý výstup
mimo přesný CDN tag a request nonce/CSRF, skutečné CSS SHA/SRI a snímky pixelů.
Ověřit kalendářové filtry/navigaci a velodrom bez vytváření ostrých rezervací.
Žádné síťové mocky či umělá latence; časový gate 20 % +20 ms. Po všech PASS
ověřit tested/merged tree, chráněný deploy, zálohu/config/migrace/VS/indexy,
živé GET/Chrome před/po a WIP. Doklady zapsat samostatně bez dalšího deploye.
Akceptace zatím NOT RUN; další krok baseline a úzká implementace.

ASSET5 checkpoint: source contract a1/12 negativní unit PASS; živý before6/6, cíle CSS2/53957 B a control1/31248 B. [Průběžné výsledky](verification/public-calendar-assets-20261110/RESULTS.md). Native dvojí DB/Chrome a přijetí ještě čekají.


ASSET5 integrace main: během práce sloučen #438 / f39f3157 (objednávky a
registrátor). Společná dokumentace zachována včetně TESTER-20261110. Nové CI
se zopakuje na integrovaném head; dva vybrané kalendářové zdroje z37f1ba8f
jsou stále přesným oraclem, ostatní sdílené PHP kopie jsou v páru shodné.
Produkční plán TESTER váže #438 na společné vydání s #436; před vlastním deploy
ověřit splnění této již zapsané podmínky a aktuální run/SHA. #436 neslučovat
v rámci tohoto řezu. Původní WIP zůstává bez zásahu.


ASSET5 checkpoint / PR #439: první přísný HTTP diff byl4 mezery za inline SEO.
PHP uzavírající značka spotřebuje zdrojový newline; porovnávač proto nyní
odstraňuje přesně4 zdrojové mezery a jeden newline patřící jedinému známému
CDNtagu, bez obecné whitespace normalizace. Regresní unit1/13 PASS. Nový živý
before používá stejný přesný postup; skripty/data/verze assetů zůstávají celé.
Native42851391494 na38f6e9fb prošlo na obou DB včetně Chrome. Následně do main
přibylo povolené #436 /4e3df207; zachované oba souběžné plány a nové ověření
se zopakuje na tomto společném základu. Společný produkční běh jiného řezu
42852365591 běží na4e3df207; neslučovat další cizí větev ani ho nepřehrávat.
Dosavadní aplikační diff tohoto PR proti main jsou jen dva zvolené kalendáře.


### ASSET5 — finální výsledek a předání

Finální integrovaný head39f3f104 (zahrnuje schválené #438/#436), všech13 CI
SUCCESS včetně2443/56108. Native42853530222 naMariaDB10.3/11.4: celé8HTTP,
350 checksumů/DB, anonymní POST denials, source/cleanup PASS. Skutečný Chrome:
408 měřených +96 warmup navigací,24 funkčních variant,6 pixelových párů PASS.
Cold CSS64454→31248 B v CI; FCP60→56/56→52 ms kalendář desktop/mobile,
52→48/48→48 ms velodrom. Control také o4 ms lepší; časový zisk neprohlašujeme
za produkční SLA. Warm network0→0 B; decoded465606→232803 B.

PR #439 sloučen jako8182489e, celý treee40cdc71 shodný s testovaným39f3f104.
Deploy42855987339 SUCCESS, záloha389/2, config/migrace/VS/index/smoke/cleanup
PASS. Čerstvá produkční baseline po4e3df207:6 celých HTML otisků, všech5 asset
verzí a6 pixelových párů shodných po8182489e; CSS53957→31248 B, control stejný.
[Finální měření, metoda a meze](verification/public-calendar-assets-20261110/RESULTS.md),
[receipt](verification/public-calendar-assets-20261110/release/release-controls.json).
Lidské přihlášené UAT, fyzický telefon a obnova této zálohy NOT RUN. WIP7a7afd75
zachovaný; runtime/flags vlastní CURRENT. Finální doklady ve větvi
codex/public-calendar-assets-release-docs-20261110, [PR #442](https://github.com/KovoPraha/evidenceTreninku/pull/442).
Další krok: po úplném CI sloučit doklady bez druhého deploye. Žádný další
implementační řez není v tomto zadání rozpracovaný.


Finální docs integrace: souběžný #441 sloučen do main jako d0f5fee7. Převzaté
všechny jeho věcné záznamy TESTER/COACH včetně intro CURRENT/HANDOFF; jediný
konflikt CURRENT vyřešen zachováním obou dokončených prací a vlastního ASSET5
měření. Pouze docs, aplikace8182489e se nemění. Všech11 kontrol finálního
dokumentačního head se zopakuje před sloučením #442; druhý deploy se neprovádí.


ASSET5 docs checkpoint: souběžný #440 byl mezitím sloučen jako9ef52268.
GitHub protected main vyžaduje strict aktuální základ; pokus o merge #442
po11 SUCCESS na staršímcfbafd47 vrátil405 (3 required checks expected).
Žádné sloučení ani nasazení tím nevzniklo. Převzatý již schválený main včetně
BAR, bez konfliktu a se zachováním jeho věcných dokladů; proti novému main
zůstává náš PR pouze docs. Všech11 CI se zopakuje na společném finálním head.
Naše produkční přejímka se týká8182489e/deploy42855987339, nikoli dodatečně
kódu BAR. Kvůli těmto dokladům se žádný další deploy nespouští.


## P11-ASSET6 — rychlý nákup a přihláška, 10. 11. 2026

**NASAZENO / cílové formuláře technicky PASS; controlfoto LIMIT.** Výchozí plán a checkpointy níže jsou historické. Vlastník Vývoj/Codex, zadavatel Marek. Přímé zadání: další
nejpřínosnější optimalizace podle analýzy; shoda/před-po, při všech PASS
commit/merge/deploy, zachovat WIP a průběžný plán/handoff v repozitáři.
Base main588489003aff40963c3a64959d566406c687d08f; poslední ověřený deploy42863352343/9ef52268 SUCCESS.
Větev codex/checkout-assets-performance-20261110, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261110.
Původní fio větev/d1221f35 a kis_rosters_admin.php SHA2567a7afd75 beze změny.
CURRENT vlastní runtime/flags; pro vlastní vydání UAT/importfalse.

### Čerstvé posouzení kandidátů

[P11-EVENTREAD](CLUB_EVENT_PUBLIC_READ_PERFORMANCE_2026-11-10.md) prototyp má
SQLite strict paritu a při1000syntetických akcích6506→17SQL. Produkční GET ale
vrací0veřejných karet; prázdná projekce jen6→4SQL. Native/Chrome přejímka NOT RUN,
prototyp nevybraný/odložený pouze na d8274970 ve vlastní větvi, bez view změny
a deploye. Podmínka návratu v jeho plánu, žádný jeho PHP helper se nepřebírá.
RIX/HJS2 zůstávají nevybrané, původní důvody a WIP platí.

Podle bodu7 [analýzy](verification/performance-analysis-20261107/RESULTS.md)
vybrány další dvě ověřené duplicity CDN Bootstrap5.3.3 + appUiAssets():
booking/rychly_nakup.php a booking/rychla_prihlaska.php. Produkční veřejné
formuláře218/784 a298/952 oba200 a2CSS; katalog/produkt control200 a1CSS.
Změna má okamžitý přenosový přínos na dostupných formulářích. Bez údajů o
návštěvnosti netvrdíme absolutní celkový produkční zisk. Samostatné měření níže.

### Rozsah a akceptace před implementací

1. Odstranit právě jeden známý CDNtag na každém ze dvou formulářů; společný
lokální CSS payload/SRI, manifest verzí, TTL a všechny jiné assety beze změny.
Přesný source kontrakt proti immutablebase: celé PHP tokeny a literal HTML/JS
mimo konkrétní tag a vyjmenované povinné guard závorky/formátování stejné.
2. Žádná změna SQL, účtů, identit, stock/dopravy, cen, term/version/consent,
CSRF, rate limit, idempotence, transakcí, plateb, rezervací nebo importu.
Zachovat guard přesměrování klubového produktu, stale verze a atomický rollback.
3. Native MariaDB10.3/11.4 celé HTTP s plně migrovaným syntetickým schématem:
validní goods/program formulář, varianty/neplatná či nedostupná nabídka,
chybějící podmínky, přihlášený redirect, invalid CSRF a control produkt.
Přesná shoda celé odpovědi mimo právě jeden tag a request nonce/CSRF; všechna
data, skripty, ceny i term_version/program_term_version zachované. Negativní
mutace porovnávače. Domain checksumy, žádný testovací produkční POST.
4. Skutečný Apache/Chrome desktop/mobile cold/warm,17 střídavých párů po4warmupech,
ResourceTiming/CDP, skutečné decoded CSS SHA/SRI,6pixelových párů, computedstyle,
form/select/guardian-self/terms a mobilní navigace. Žádné mocky či umělá latence.
Cíle CSS2→1 a decoded465606→232803 B, control1→1; měřit skutečný encoded přenos,
FCP/ready gate20%+20ms. Časové zrychlení nepřipisovat změně, pokud control kolísá.
5. Všechno aktuální CI, shodný tested/merged tree a chráněný deploy po PASS;
backup/config/migrace/VS/indexy/smoke/cleanup a čerstvá veřejná produkční GET/
Chrome baseline před nasazením i after. Bez reálné objednávky/platby/pošty nebo
změny role. Lidské přihlášené UAT/fyzický telefon/obnova zálohy označit NOT RUN.
6. Výsledky průběžně do [protokolu](verification/checkout-assets-20261110/RESULTS.md), registru/CURRENT/handoff/logů.
Finální doklady sloučit po úplném CI bez druhého deploye. Cizí WIP nestashovat,
neresetovat ani neuklízet; stage jen vlastní soubory, žádné secrets/raw HTML Git.

Nová implementace/native/browser/release zatím NOT RUN. Další krok: živá
baseline a přesná dvě odstranění, nové kontrakty/fixture a měřená přejímka.


### ASSET6 checkpoint před novým CI

Obě přesná odstranění implementovaná. Source kontrakt proti58848900 PASS:
celé PHP tokeny a veškerý literal HTML/JS mimo2známé tagy stejné;0vložených
guard závorek. Povinné formátování dlouhých šablonových řádků používá nulové
PHP hranice, které nic nevypisují. Payloady/manifest/TTL i všechny služby stejné.
Lokální21 testů/412 assertions PASS, včetně stale/refresh/atomického rollbacku
rychlé přihlášky a17negativních assertion porovnávače. Style/line200/lint PASS.

První lokální běh odhalil křehký zdrojový regex starého testu rodičovského
průchodu: vyžadoval přesné mezery PHP foreach značek. Nyní přijímá pouze PHP
whitespace; skutečný fragment stále vykresluje, HTML/atributy nijak nenormalizuje,
dekóduje odeslané názvy/verze a zachovává všechny stale/rollback assertion.
Nejde o zásah do registračního/platebního guardu nebo jeho vypnutí.

Nový izolovaný native/Apache/Chrome runner:10HTTP scénářů včetně validních
formulářů, empty-stock404, variant404, club303, neúplných terms409, customer303,
product control; invalidCSRF obou formulářů a checksumy plné migrované DB.
Chrome goods quantity, guardian/self synchronizace, věková poznámka, ack/CSC,
přesné hidden verze a mobilní navigace,6pixelpairs. Nová native přejímka zatím
NOT RUN, včetně backend/schema kalibrace fixture. Žádné produkční zápisy.


ASSET6 / [PR #445](https://github.com/KovoPraha/evidenceTreninku/pull/445).
Souběžný main06ad54e4 /#444 převzatý bez konfliktu se zachováním dokončeného
BARu a všech jeho věcných záznamů. Žádná aplikativní změna proti58848900;
source oracle dvou formulářů zůstává58848900, celý ostatní PHP kontext je v A/B
páru vždy čerstvý a byte-shodný. Nové CI ověřuje společný head před release.


ASSET6 native kalibrace: první42876038779/d9c62776 obě DB skončily před
HTTP maticí na očekávání customer login303. Skutečný neměněný veřejný login
prihlaseni.php používá header Location bez status argumentu, tedy302; login
byl úspěšný. Test nyní očekává přesně302, nikoli obecnou libovolnou3xx.
Následný ověřený-account guard rychlé přihlášky zůstává přesně303 a celý
Location/body kontrakt je dál kontrolovaný. Source/aplikace se nemění.
Cleanup vlastního schema/user/config a workerů proběhl, žádná produkční akce.
Nová plná native2DB/browser přejímka se opakuje, neoznačovat první FAIL za PASS.


ASSET6 druhá kalibrace42876825180/c3a48460: login302 už prošel. Původní
varianta ještě před změnou správně vracela404 pro NULL sklad, test ji chybně
nazval unlimited. Současný shopStorefrontReadRows při dostupném inventory
schématu NULL zásobu vylučuje; nyní explicitní unknown-stock404 i zero-stock404.
Totéž existující veřejný filtr skrývá program bez účinných program terms (404).
Očekávání jsou přesná podle neměněných guardů, žádná tolerance dalších stavů.
Samostatně doplněn409 pro chybějící povinné athlete notice: jen vlastní fixture
dočasně změní text a ve finally obnoví přesný obsah; checksumy se dál ověřují.
Nové plné native2DB/Chrome měření se opakuje, předchozí FAIL zůstává v historii.

První živý Chrome158 before6/6 GET200, desktop/mobile bez JS/network chyby
a přetékání. Target Bootstrap53957 B (22709CDN+31248local), control31248 B.
Pět obsahových URL a celé HTML zůstávají v nové baseline; před nasazením se
obnoví čerstvý before. Produkce beze zápisu; nejde o produkční časový benchmark.


### ASSET6 — checkpoint přijatého headu a release

D6af0a09:13aktuálních CI SUCCESS, native42878204242/oběDB a skutečný Chrome
PASS; úplné exporty v [protokolu](verification/checkout-assets-20261110/RESULTS.md).
PR #445 sloučen jako c9e5501a, celý tree97fa16dc shodný s testovaným headem.
Deploy42881039034 spuštěný jednou na c9e5501a, UAT/importfalse; zatím běží.
Živá čerstvá before6GET/6canonicalpayloads z19:01UTC, after/receipt NOT RUN.
WIP7a7afd75 zachovaný, dokumentační větev codex/checkout-assets-release-docs-20261110.


### ASSET6 — finální výsledek a předání

Native42878204242/d6af0a09 naMariaDB10.3/11.4 PASS: deset celých HTTP scénářů,
350checksumů/DB, source token/literal kontrakt s0závorkami, CSRF/notice denials
a cleanup. Skutečný Apache/Chrome408měřených+96warmup,24funkčních záznamů,
6pixelových párů PASS. Cold CSS64454→31248 B v CI, decoded465606→232803 B,
warm network0→0 B. FCP goods72→56/68→52 ms, program96→76/88→76 ms,
control68→68 ms; backend bez prokázaného zrychlení. Nejde o produkční SLA.

PR #445 →c9e5501a, všech13CI SUCCESS, celý tree97fa16dc shodný s testovaným
d6af0a09. Deploy42881039034 SUCCESS, 2454/56238, backup389/2,
config/migrace/VS/index/smoke/cleanup PASS, import/UATfalse. Živých šest celých
HTML, všech pět asset verzí, privacy hlavičky/CSP shodné;5/6pixelpárů přesně,
všechny4 upravené formuláře PASS. Mobilní control fotografie má pouze RGB≤4
rozdíl v obrázku, přesná příčina neprokázaná (LIMIT, nikoli PASS); diagnostika
a původní PNG zachované v protokolu, žádná maska/tolerance strict testu.
Produkční cold CSS53957→31248 B, control stejný; všech6staticpayloadů canonical
Git před/po shodných. [Metoda, důkazy a meze](verification/checkout-assets-20261110/RESULTS.md),
[receipt](verification/checkout-assets-20261110/release/release-controls.json).
EVENTREAD odložený nevybraný prototyp, žádný jeho runtime helper se nepřebírá;
historické3 ASSET4 hashy dál LIMIT. WIP7a7afd75 zachovaný. Lidské UAT,
fyzický telefon a obnova nové zálohy NOT RUN. Finální doklady ve větvi
codex/checkout-assets-release-docs-20261110, [PR #446](https://github.com/KovoPraha/evidenceTreninku/pull/446).
Doklady jsou předané přes PR #446; jeho sloučením jsou součástí main.
Další produkční krok není potřeba, kvůli dokladům neopakovat deploy.


## P11-ASSET7 — stránky rodinného účtu, 10. 11. 2026

**NASAZENO / technická přejímka PASS; přihlášené produkční UAT NOT RUN.** Výchozí plán/checkpointy níže historické. Vlastník Vývoj/Codex, zadavatel Marek. Přímé nové zadání:
nejpřínosnější další optimalizace podle analýzy; shoda výsledků a měření před/po,
při všech PASS commit/merge/deploy, zachování WIP a průběžný plán/handoff v repu.
Base4d1e99412d83c7bb70da77694f32e31b93ff8284; poslední ověřený runtimec9e5501a,
deploy42881039034 SUCCESS. Vlastní větev codex/family-assets-performance-20261110,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-family-assets-performance-20261110.
Původní fio/d1221f35 a kis_rosters_admin.php SHA2567a7afd75 zachovat beze změny.

### Výběr a hranice

Nová inventura bodu7 analýzy: čtyři stránky stejného rodinného účtu mají
explicitní CDN Bootstrap CSS i appUiAssets(): moje_osoby.php, moje_objednavky.php,
moje_programy.php a moje_rezervace.php. Cíl odebrat jednu celou identickou CSS
odpověď při první návštěvě každé stránky, bez změny dat a zápisových cest.
Objemová úspora bude přijata až po skutečném native/Chrome baseline, ne pouze
podle textového výskytu. Bez návštěvnosti netvrdíme absolutní globální pořadí.
Můj sport má odlišnou athlete autentizaci a PHP echo tag: samostatně nevybraný,
do tohoto rodinného řezu nepatří. HJS2 zůstává nevybraný WIP: menší inlineJS
úspora, nový transport/DOM bezpečnostní kontrakt. EVENTREAD má nyní0veřejných
karet a zůstává odložený; jeho runtime helper nepřebírat. RIX WIP zachovat.

### Akceptace zapsaná před implementací

1. Přesně čtyři známé jednorázové CDN tagy pryč; celé PHP tokeny a literal
HTML/JS jinak shodné mimo vyjmenované povinné PSR12 guard závorky. Dlouhé HTML
řádky dělit nulovými PHP hranicemi bez výstupu. Žádné jiné runtime úpravy.
2. Beze změny SQL, identit/vazeb, schvalování osob, objednávek, cen, plateb,
programů/účastí, rezervací/storna, verzí souhlasů, CSRF, flash a writerů.
Shared CSS/JS payloady/verze/TTL stejné. Neprovádět skutečný produkční POST.
3. Native MariaDB10.3/11.4, plně migrovaná izolovaná syntetická DB: skutečný
customer login, všechny čtyři celé GET včetně neprázdných doménových dat a
neověřeného/prázdného účtu, anonymní přesné denial redirecty, neplatný CSRF,
revokace účtu/vazby dle původního kontraktu. Všechny checksumy po fixture a
loginu stejné. Celé status/Location/HTML shodné; pouze konkrétní tag a request
nonce/CSRF, žádná obecná whitespace/script/asset-version normalizace.
4. Skutečný Apache/Chrome desktop1036×703/mobile390×844, cold/warm,
17střídavých párů po4warmupech; target2→1CSS, control1→1. ResourceTiming/CDP,
decoded SHA/SRI, computedstyle/geometrie, přesné pixely a bezpečné klientské
ovladače/form draft bez skutečného odeslání. Zachovat příslušné domain kontroly.
Měřit encoded/decoded bytes, FCP/ready; gate čas20%+20ms, bez umělé latence.
Warm network hlásit podle skutečnosti. Časový zisk nepřenášet na produkční SLA.
5. Všechno aktuální CI, strict main, tested/merged tree shoda; až při PASS
chráněný deploymain s UAT/importfalse, backup/config/migrace/VS/index/smoke/
cleanup. Čerstvé produkční anonymní redirecty a veřejný control před i po,
canonical payloady. Přihlášené živé UAT jen při dostupné schválené session;
jinak výslovně NOT RUN, nikoli tvrzená náhrada nativní fixture za produkci.
6. Průběžně [výsledky](verification/family-assets-20261110/RESULTS.md), registr,
CURRENT/handoff/logy; finální bezpečné doklady přes PR po celém CI bez druhého
deploye. Původní WIP nestashovat/neresetovat/neuklízet, stage jen vlastní cesty.
Historický ASSET4 LIMIT i ASSET6 controlfoto LIMIT zůstávají zachované.

Native/browser/implementace/release zatím NOT RUN. Další krok: baseline fixture,
přesná odstranění a nové párové přejímky; při neprokázané paritě nebo přínosu
nevydávat. Runtime/flags vlastní CURRENT.


ASSET7 checkpoint: implementovaná přesně čtyři odstranění. Source kontrakt
proti4d1e9941 PASS: všechny PHP tokeny/literal HTML/JS zachované mimo konkrétní
tagy a4přesně pinované guard závorky v moje_programy.php (PSR12). Nulové PHP
hranice dělí dlouhé HTML řádky bez výstupu; whitespace není obecně maskovaný.
Lokálních19testů/188assertions včetně negativních mutací porovnávače a account
claims/role/program overview PASS; style/line200/lint/diff PASS. Nový runner:
17celýchHTTP scénářů (main/empty/unverified/anonymous/control), CSRF a čerstvá
revokace vazby/účtu,350checksumů a20Chrome kombinací. Native/baseline/Chrome
přínos a release zatím NOT RUN; další krok nová přejímka, ne hotový výsledek.


ASSET7 / [PR #447](https://github.com/KovoPraha/evidenceTreninku/pull/447), větev codex/family-assets-performance-20261110; native/browser běh připravený.


ASSET7 native kalibrace:42900569649/964df9a6 a42900622889/5926e376 obě DB
FAIL před HTTP maticí na chybně očekávaném login302 neověřeného účtu.
Neměněné prihlaseni.php výslovně odmítá email_overeno=0:200 a „Email není
ověřen.“, účet vůbec nepřihlásí. Fixture nyní přesně kontroluje toto odmítnutí
apak302 denials čtyř soukromých stránek. Revokace aktivního přihlášeného účtu
se proto ověřuje na prázdném ověřeném účtu12: podle db.php přesně303 na
session=expired. Žádné rozšíření tolerovaných stavů ani změna autentizace;
první FAIL zůstává v historii, nová plná native/Chrome přejímka se opakuje.
Produkční anonymní before:4redirecty302 a login200,5canonicalUIpayloadů PASS;
přihlášené živé UAT NOT RUN. Žádné produkční POST, WIP zachovaný.


ASSET7 druhý native checkpoint42901157327/ee01c130: loginové denials už podle
původního kontraktu prošly; obě DB FAIL na neprázdném main-orders ještě při
before. Kapacitní základ fixture vytváří objednávky bez kanonických payments,
zatímco skutečné shopOrderListForAccount používá INNER JOIN payments.
Doplněné pouze syntetické payment řádky bez reálného IBAN/platby, aby byl
skutečný neprázdný stav vůbec vykreslený; query/writer/ochrana beze změny.
Revokační fixture navíc ve finally obnovuje i původní updated_at vazby
(ON UPDATE v migrovaném schématu), checksum kontrakt se nesnižuje. Nový celý
native2DB/Chrome běh se opakuje, dosavadní FAIL zůstávají FAIL.


ASSET7 úplný PHPUnit na f111d30e: jediný FAIL LoggedInUxWiringTest vyžaduje
přesný jednořádkový zápis athleteRegistrationTermsReady(athleteRegistrationCurrentTerms($pdo)).
Formátování jej rozdělilo; výraz nyní opět na jednom řádku pod200znaků.
Původní test ani registrační guard se neupravují, source token/literal kontrakt
stále PASS. Nová lokální unit sada824testů/28734assertions,3platformní skip;
bez selhání, style/diff PASS. Native42901718648 na předchozím f111d30e:
10.3celáHTTP/350checksumů/CSRF/revokace PASS,11.4Chrome ještě běží; kvůli
novému finálnímu SHA se aktuální CI/native2DB/browser zopakují celé.


### ASSET7 — přijatý head a nasazování

B65137a0 má13aktuálníchCI SUCCESS, native42903018197/oběDB a skutečnýChrome
680měřených+160warmup/40funkčních/10pixelpárů PASS. [Plné exporty](verification/family-assets-20261110/RESULTS.md).
PR #447 →c29642f1, celý tested/merged tree shodný. Deploy42905781826 spuštěný
jednou na stejnémSHA, UAT/importfalse; zatím běží, receipt/after NOT RUN.
Přihlášená uživatelská Chrome relace nedostupná (login), živé UAT NOT RUN.
WIP7a7afd75 a ostatní worktrees zachované; doklady codex/family-assets-release-docs-20261110.


### ASSET7 — finální výsledek a předání

Přesně čtyři rodinné CSS duplicity odstraněné, žádná změna SQL/writerů/ochran.
Source/literal kontrakt PASS,4pinované PSR12 závorky, původní wiringtest a guard
beze změny. Native42903018197/b65137a0 oběDB PASS:17celýchHTTP scénářů,
350checksumů/DB, CSRF/revokace/cleanup. Chrome680měřených+160warmup navigací,
40funkčních záznamů/10přesnýchpixelpárů PASS. Cold CSS[64454]→31248 B,
decoded465606→232803 B, warm network0→0 B; FCP8–24ms zisk
v targetkombinacích, mediány nejsou produkční SLA. Control CSS/payload beze změny; jeho FCPdesktop72→68/mobile68→68ms, tedy časový šum zahrnutý v měření.
PR #447/c29642f1,13CI a tree1acec2cf shoda. [Deploy42905781826](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42905781826) SUCCESS nac29642f1,
2455testů/56279assertions, backup389tabulek/2triggerů,
config/migrace/VS/index/HTTP200/cleanup PASS. Přímý runnerHTTP0,
předepsaný serverový curl fallback200; UAT/importfalse a import přeskočený.

Pět živých anonymních status/Location/HTML/header párů a5canonical assetpayloadů
shodných. Uživatelský Chrome na loginu, přihlášené produkční UAT/privátní live
waterfall/fyzický telefon/obnova nové zálohy NOT RUN. [Výsledky a meze](verification/family-assets-20261110/RESULTS.md).
Historické ASSET4/6 LIMIT, EVENTREAD/HJS2/RIX WIP i původní7a7afd75 zachované.
Doklady [PR #448](https://github.com/KovoPraha/evidenceTreninku/pull/448):
jeho sloučením v main, **neopakovat deploy**. Žádný další implementační řez
není v tomto přesně čtyřstránkovém zadání rozpracovaný; runtime/flags vlastní CURRENT.
