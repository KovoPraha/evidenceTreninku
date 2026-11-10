# P11-ASSET — průběžné ověření

[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md).

## Technická přejímka a vydání 10. 11. 2026 — PASS

PR [#425](https://github.com/KovoPraha/evidenceTreninku/pull/425) sloučen jako
`9dbd9eaf1f78e364d1dacd1ae0e3969758b7a176`; celý tree je totožný s testovaným
`6162e6b7`. [30/30 CI](premerge-checks.json) SUCCESS, PHPUnit2424/55908.
Primární běh42725053241 i nezávislý42724887655 PASS na obouMariaDB10.3/11.4.
Oba fyzickéPHP roots mají stejný bootstrap, hlavičku, reader a assets; celý
HTML/16AJAXscénářů/4parent+coachAJAX ×1k/10k/30k, role/revokace/bearer/CSRF,
checksumy a úklid PASS. [Zdrojová shoda](source-proof.json), [native](native-metrics.json).

Skutečný Chrome544měřených navigací +128warmup,17střídavých párů každého
kontextu/viewport/cache; navíc52legacy default/saved-Měření navigací. Žádné
throttling/mocky. CSS2→1 na každé stránce, odstraněno232803decoded bytes,
při ověřeném živém gzip30829bytes a1externí request. Warm cache bez významné
regrese. [Všechny metriky a8/8pixelově shodných párů](browser-metrics.json);
computed styles shodné, skutečné mobilní offcanvas otevřeno/zavřeno, žádná
chyba aplikace. Pouze automatický nepoužitý favicon.ico404 evidován zvlášť.

Cold FCP, mediány ms, původní→nové:

| Stránka | Desktop1036×703 | Mobil390×844 |
| --- | --- | --- |
| Profil sportovce | 68→48 (−29.4%) | 68→44 (−35.3%) |
| Moje tréninky | 64→48 (−25.0%) | 60→44 (−26.7%) |
| Správa tréninků | 60→52 (−13.3%) | 56→52 (−7.1%) |
| Veřejný bearer profil | 48→44 (−8.3%) | 48→44 (−8.3%) |

Časy jsou párový syntetický Chrome benchmark, nikoli produkční SLA. Serverový
čas zůstává kontrolní metrikou; SQL se nemění. Variabilita plánů a hostů je
vidět mezi nezávislými běhy, nelze porovnávat absolutní čísla napříčVM.
WIP7a7afd75 zachovaný; žádná migrace/index/role/cache/aktivace/import změna.

Chráněný deploy [42727693090](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42727693090)
SUCCESS na přesném `9dbd9eaf`, import/UAT false. Úplná záloha 389/2,
konfigurace, migrace, VS, indexy a HTTP smoke PASS. [Osm GET před](public-before-final.json)
a [po](public-after.json) mají shodný status i normalizovaný DOM. CLI je
chráněné stávajícím hostingovým 403; canonical CSS/SRI živě shodné.
Konečný stav a skutečné meze ověření jsou v [release](release/RESULTS.md).
Přihlášené lidské produkční UAT, fyzický mobil a restore nové zálohy NOT RUN.

---

Následují historické pracovní záznamy včetně FAIL a nepřijatých měření.

Baselineff182971/runtime17110a7f. RIX SQL/index varianty jsou nevybrané,
reader obnoven přesně. Canonical Bootstrap232803bytes/SHA384 odpovídá SRI
na duplicitních CDN odkazech; živý payload/HTML/Chrome následují.
Nový app merge/deploy NOT RUN, WIP7a7afd75 zachovaný.


## Příprava — PASS

Pouze3 přesné odstraněné tagy; canonical LF všechny3zdroje proti ff182971
jinak identické, celý reader byte-shodný. Živý [lokální/CDN CSS payload](public-css-identity.json)
oba232803decoded bytes a stejný SHA384/SRI; lokální gzip31248bytes,CDN30829bytes.
Bez cookies, TLSověřené,2GET/200. Odstranění CDNkópie tedy šetří jeden
externí render-blocking request a jeho30829gzip bytes při cold načtení.
To je deterministický přenosový zisk, zatím ne naměřená úspora FCP.
Úzký normalizátor1test/6assertions PASS, odmítá jinou verzi/SRI i chybějící/
duplicitní/externí lokální kopii. Žádný app source edit nad rámec3tagů.
Nativní HTTP/Chrome a finální aktuální CI následují.


## První příprava f9 — FAIL testovací role, bez nasazení

42718340757 oběDB zastavené před timingem: trenér dostal na sprava_treninku
správnou302/login, tato legacy stránka vyžaduje hlavni. Zvolena existující
syntetická sports_lead identita pro třístránkový profil; role ani guardy
aplikace se nemění. [Důkazy](first-f9/native-10.3/runner-cleanup.json): cleanup
PASS oběDB, native model3/562 a normalizátor1/6 PASS. Přenosová úspora,
source edit a CSS identity ověřené; FCP/HTML kompletního běhu zatím NOT RUN.
Browser měření rozdělené do34vzorkových bloků kvůli omezení CLI;17párů a
4warmupy každého cold/warm viewport/route zůstávají. WebCrypto v reálném
browseru místo závislosti na Node require; žádné request mocky.


Druhý běh42718778899/3f také před timingem FAIL testovacího kontextu: sports_lead
správně403 na moje_treninky, kterou vlastní coach. Měření nyní používá přesné
per-route existující identity: coach profil+Moje, sports_lead Správa, veřejný
bearer samostatně. Doplněny negativní302/403 kontroly hranic pracovních pozic.
Žádná změna/grant rolí ani route guardů; oba cleanup PASS,3/562 a1/6 PASS.


Třetí42719230914/20 došel celým parent timingem, ale FAIL srovnání Moje:
shared header má stejný per-session CSRF také ve dvou push JSON objektech.
Původní normalizace formuláře/const je neobsáhla. Nová úzká normalizace vezme
jediný ověřený64hexCSRF z explicitního pole/const a nahradí jeho výskyty,
při více různýchCSRF v jednom parentu selže. Není to změna aplikace ani
obecné ignorováníJS. Další případný rozdíl uloží jen bezpečný redigovaný
snippet. OběDB model3/562/normalizátor1/6/cleanup PASS, app deploy NOT RUN.


Čtvrtý42719770933/b8 dokončil shodu celéhoHTML všech3stránek na obouDB,
ale FAIL na příliš konkrétním očekávání302/403 negativní workspace kontroly.
Stávající kontrakt přijímá302/303/401/403 podle již inicializovaného kontextu;
nový test přijme jen odmítnutí a vyžaduje naprosto stejný status i Location
před/po pro oba zakázané kontexty. Nikdy200. Staré cookiejary konkrétního
parent testu se před loginem znovu založí, stejně jako v ostatních harness.
Aplikace/role nezměněné; native model3/562, normalizátor1/6 a cleanup PASS.


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
SharedUiShell+normalizátor16/98 PASS lokálně. [Záznam](midnight-test-isolation.json).

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
