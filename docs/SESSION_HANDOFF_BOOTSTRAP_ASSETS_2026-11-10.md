# Předání P11-ASSET — 10. 11. 2026

NASAZENO / TECHNICKÁ PŘEJÍMKA PASS.
[Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md),
[výsledky](verification/bootstrap-assets-20261110/RESULTS.md),
[release a skutečné meze](verification/bootstrap-assets-20261110/release/RESULTS.md).

PR #425 → 9dbd9eaf, celý tree shodný s testovaným 6162e6b7.
30 CI SUCCESS, 2 424 testů / 55 908 kontrol. Oba čerstvé běhy obou DB PASS:
42725053241 a 42724887655, celé HTML/business/role/revokace/bearer/CSRF/checksumy.
544 Chrome nav + 128 zahřátí, 52 legacy Měření nav, 8/8 pixelových párů shodných.
Cold FCP profil 68→48/44 ms, Moje 64→48/60→44, Správa 60→52/56→52;
CSS 2→1, jeden požadavek a 30 829 gzip bytes méně. Benchmark není produkční SLA.

Deploy 42727693090 SUCCESS na 9dbd9eaf. Záloha 389 tabulek / 2 triggerů,
konfigurace/migrace/VS/indexy/smoke PASS. Runner HTTP 0, serverový fallback 200;
nezávislých 8 veřejných GET před/po má shodné statusy i normalizovaný DOM.
CSS/SRI živě shodné. /bin chrání hostingový 403, nativní PHP guard má 404/0.
Runtime 9dbd9eaf; uat_schvaleno=false, importovat_krouzky=false.

Aplikační diff pouze 3 smazané CDN linky; reader/db/header a další runtime
shodné s ff182971. RIX uzavřen bez přijaté SQL/index varianty, žádná nová aktivace.
WIP původní fio větve / kis_rosters_admin7a7afd75 zachovaný bez stash/clean/reset.
Doklady sdílí [PR #426](https://github.com/KovoPraha/evidenceTreninku/pull/426),
jen docs: po sloučení se runtime znovu nenasazuje. Technická práce uzavřená;
lidské přihlášené produkční UAT, fyzický mobil a obnova této zálohy NOT RUN.


## P11-ASSET2 — nasazeno a technicky ověřeno

10. 11. 2026. PR430 /706a8d42, celý tree shodný s156a9597,13CI SUCCESS.
Deploy42790212078 SUCCESS, flags vlastní CURRENT: import/UATfalse, backup389/2,
config/migrace/VS/index/smoke PASS. Syntetický Chrome544+128 a8pixelpairs/24UI
scénářů, oběMariaDBwholeHTML/source/security/checksum/cleanup PASS.
ColdBootstrapCSS62496→31248gzipB; FCPdesktop60→52/mobile56→52ms, warmbezezměny;
není to produkčníSLA ani výrazný ready-time zisk.
[Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset2--pracovní-rozcestník-nový-řez-10-11-2026),
[release a meze](verification/workspace-bootstrap-assets-20261110/release/RESULTS.md).
8anonymníchprodukčníchstatus/DOM i canonicalCSS/SRI/cache shodné. Přihlášené
produkčníUAT NOTRUN:2timeouty připojeného browserAPI, žádné UIzápisy nebo role.
PůvodníWIP7a7afd75 po deployi shodný. HJS/HJS2 větve/PR429 zachovanébezmerge.
Finálnídocs větev codex/workspace-assets-release-docs-20261110, žádný druhý deploy.
Dalšíkrok: nový waterfall aktivní stránky a další měřitelný řez; ASSET2hotovo.


## Aktuální předání P11-ASSET3



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


## Aktuální pokračování P11-ASSET4

ROZPRACOVÁNO; [plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset4--katalogová-diagnóza-a-další-měřený-řez-10-11-2026). Nová větev codex/catalog-audit-next-performance-20261110 z50ce79b2. Nejprve čtecí diagnóza3cataloghashů a nová strukturovaná baseline, pak výběr podle waterfallu. Implementace dosud nevybraná; po PASS autorizovaný release, flagsfalse. WIP7a7afd75 zachovat.


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


### ASSET4 — průběžný stav implementace

Čtecí audit [#434](https://github.com/KovoPraha/evidenceTreninku/pull/434) sloučen,
11 CI PASS; běh 42826637716 SUCCESS včetně rollbacku, shody konfigurace a úklidu.
49 změn fotografií nedokazuje úplnou příčinu historických rozdílů. Původní HTML
chybí, chybný parametr stránky 2 opakoval default. Historická shoda otevřená;
[výsledky a meze](verification/catalog-audit-assets-20261110/RESULTS.md).
Připraven offline manifest pěti společných assetů a úzká oprava nonce značky.
Současná větev codex/stable-ui-assets-performance-20261110; samostatný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-ui-cache-performance-20261110.
Obsah souborů, cache TTL a CSP kontrola zachované. Zdrojový kontrakt PASS;
nové celé HTTP, dvourelease Chrome a skutečná invalidace NOT RUN, release nepřijat.
Runtime zůstává beead425 / deploy42810948814, oba flags false. Další krok:
nový izolovaný profil, potom vyhodnotit shodu a naměřený přínos; teprve při
splnění přejímky commit/merge/deploy. Původní WIP zachovaný, nic nestashovat.
