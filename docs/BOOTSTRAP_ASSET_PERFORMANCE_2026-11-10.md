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
