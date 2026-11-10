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

ROZPRACOVÁNO před implementací. Vlastník výslovně žádá prověřit tři neobjasněné
katalogové rozdíly, pokračovat nejpřínosnější optimalizací, ověřit shodu a před/po;
po všech PASS commit/merge/deploy. WIP zachovat, plán a handoff průběžně v repu.
Vlastník Vývoj/Codex. Main50ce79b2e290b926a70e46f8a729682f12a7b864,
runtimebeead425/deploy42810948814 SUCCESS, import/UATfalse.
Větev codex/catalog-audit-next-performance-20261110; izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní kis_rosters_admin.php7a7afd75 a původní větev zachovat; HJS/RIX nepřebírat.

Etapa A: pouze čtecí analýza catalog/price/page2 proti uloženým před/po hashům,
konkrétnímu normalizátoru a oběma runtime Git stromům. Aktuální anonymní GET
zaznamenat bezpečně po částech (produktová pole, pořadí, formuláře, struktura,
asset URL a dynamické bezpečnostní značky), bez cookies, rawHTML či tokenů v Gitu.
Odlišit změnu dat, technického formátu a chybu sondy. Příčinu nededukovat jen
z HTTP200 ani nerozšiřovat maskování, dokud ji nepotvrdí konkrétní reprodukce.
Staré beforeHTML chybí; případnou mez historického důkazu výslovně zachovat.

Etapa B: podle čerstvého waterfallu vybrat a zapsat nejpřínosnější bezpečný řez.
Kandidáti k posouzení: zbývající duplicitní veřejná BootstrapCSS a stabilní verze
společných assetů místo filemtime (při minulém deployi se změnilo5URL bez změny
obsahu). Defer společného JS vyžaduje samostatnou inventuru inline závislostí;
neaktivovat globální hledání ani nepřebírat nevyhovující SQL/index prototypy.
Konkrétní implementace dosud NEVYBRÁNA, uvedené varianty jsou kandidáti.

Akceptace: prokázaná příčina nebo přesně vymezená historická mez tří rozdílů,
nová reprodukovatelná bezpečná baseline; vybraná změna má shodné datové výsledky,
celé odpovědi s úzkou konkrétní allowancí, práva/CSRF a kanonické payloady.
Reálný PHP/Apache/Chrome, cold/warm desktop/mobile a kontrola;17střídavých párů
po4warmupech, bez mocků/umělé latence; doložené přenosy a FCP/ready, nezobecňovat
neprokázaný časový zisk. Při změně načítání DB ověřit MariaDB10.3/11.4 a checksumy;
při změně assetů i invalidaci při skutečně změněném obsahu a nezměněné cacheTTL.
Celé aktuální CI, identický testovaný/merged tree, protecteddeploy přes main
jen po PASS, oba flagsfalse, backup/config/VS/migrace/index/smoke a živé čtecí
před/po. Lidské UAT odděleně; finální docsPR bez dalšího deploye.

Závislosti: GitHub CI/protected release, existující syntetické fixture a ChromeCLI.
Další krok: strukturovaná diagnóza katalogu a čerstvé srovnání kandidátů.
[Důkazy ASSET4](verification/catalog-audit-assets-20261110/RESULTS.md).
Nová aplikace/testy/merge/deploy zatím NOT RUN.


### ASSET4 — diagnóza a výběr před implementací

Čerstvý veřejný Chrome: katalogdesktop resourcepayload1417429B, z toho obrázky
1208278B; mobile283580B/74429B. Pět společnýchCSS/JS tvoří78508B (CSS51890B,
JS26618B), samostatný ikonovýfont130396B. Kalendář má navíc duplicitníCDN CSS22709B.
Čtyři největší fotografie: losslessWebP zachoval pixely, ale1132857→2004806B;
zamítnuto, do aplikace nejde. Změna rozměrů/kvality má vlastní vizuální přejímku.
Vybrán obsahový manifest pěti společných assetů místo filemtime: bez změny
payloadů a TTL ušetří znovunačtení CSS51890B při dalších deployích bez změnyobsahu;
v rámci JS30sTTL potenciálněcelých78508B. Nejprve skutečná dvourelease fixture
v jednom originu, žádné prodmtime zápisy a žádné prodnasazení jen kvůli benchmarku.

Katalog: starý page2 parametr stranka je ignorován, skutečný je strana; původní
tři rozdíly jsou pouze dva odlišné pohledy. Default/page2hash se lišil už před
vydáním,09:29→11:38, price zůstal stejný. Aktuální hashy přesně odpovídají after
11:56 a12:03. Samotná náhrada libovolné podmnožiny12fotografií známým placeholderem
staré hashy nevysvětlí (boundedreplay bez zásahu do dat). Aktuální veřejné obrázky
se načetly, ale12důvěryhodných inlinefallback handlerů je blokovaných: zdrojová
nonce značka obsahuje emisní whitespace, a proto nesplní exactnonce guard.
Opravit pouze zdrojovou značku, bezpečnostní kontrolu ponechat.

Přihlášený browser inventory timeout20s, žádné UIzměny/role/formuláře.
Proto samostatný úzký CLI audit pevného okna10.11., START TRANSACTION READ ONLY
+ROLLBACK, jen veřejná auditní pole a agregace skladové aktivity. Bez důvodů,
aktérů, zákazníků, cen nákladů či tokenů. Nejprve CI/PR/main; potom stávající
chráněné SSH/prodprostředí zkopíruje pouze dočasný čtecí nástroj mimo webroot a
uklidí ho. Jde o diagnózu, nikoliv nasazení nové webové aplikace.
