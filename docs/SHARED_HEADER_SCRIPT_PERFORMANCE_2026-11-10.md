# P11-HJS — sdílený JavaScript přihlášené hlavičky

10. 11. 2026, Europe/Prague. NEVYBRÁNO po ověření produkčního transportu.
Vlastník: vývoj/Codex podle aktuálního zadání Marka. Při plném PASS je povolený
commit, merge a chráněné nasazení; import a lidské UAT se tím nepovolují.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_SHARED_HEADER_SCRIPT_2026-11-10.md),
[důkazy](verification/shared-header-script-20261110/RESULTS.md).

## Výchozí stav a volba

Main `6dee3253b483522396cd9c7349dabac576e702b6`; runtime `9dbd9eaf`, deploy
42727693090 SUCCESS. Minulý P11-ASSET se neopakuje. RIX SQL/index varianty
nebyly přijaty; nejde je vydávat za další neopravený nález.
Navazuje na [původní analýzu, bod 7](verification/performance-analysis-20261107/RESULTS.md#7-frontend-sjednocení-sdílených-prostředků):
společný inline CSS/JS. Aktuální hlavicka.php má canonical 68 443 bytes;
poslední staff-only inline script 14 812 bytes, tělo 14 717 bytes včetně
9 PHP interpolací (6 různých hodnot). Stejný kód se přenáší v každé chráněné
HTML stránce. To je doložený náklad, nikoli dosud naměřené zrychlení.
CSS blok je menší (6 190 bytes), proto se tento řez soustředí na větší JS.

Větev `codex/shared-header-assets-performance-20261110`, izolovaný worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Původní checkout fio a kis_rosters_admin.php WIP zůstávají nedotčené, SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Cizí otevřené PR se do této práce neslučují.

## Rozsah a varianty

Vybraný kandidát: přesunout pouze poslední staff-only JS do verzovaného
same-origin assetu. Šest serverových hodnot předat v malém čerstvém JSON
kontextu: veřejný VAPID klíč, SW URL, app base, push endpoint, CSRF a search URL.
Žádná soukromá data do statického souboru. Zachovat stejné místo a synchronní
provedení, pořadí skriptů, podmínku trener login, globální funkce a callbacks.
Záměrně nepřidávat defer ani neaktivovat vypnuté globální hledání.
Alternativa podmíněného vynechání neaktivního hledání má menší dopad; lze se
k ní vrátit, pokud nový asset neprojde studeným načtením. Inline CSS zůstává.
Žádná změna SQL, čerstvé autorizace/revokace, rolí, session, writerů, cache
osobního HTML, konfigurace, migrací ani dlouhodobé cache práv.

## Akceptace a provedení

1. Zmrazit původní staff script a přesně zaznamenat zdrojové hashe. Prefix a
   suffix hlavičky musejí zůstat identické. Nový JS musí být mechanicky shodný
   po nahrazení jen 9 explicitních PHP míst kontextovými hodnotami; všechny
   hodnotové PHP výrazy zachovat. SQL počet VAPID zůstane jeden.
2. Celé HTTP a business parita na MariaDB 10.3 i 11.4 se stejnými daty;
   fyzické oddělené PHP roots (nikoli symlinkovaný bootstrap), baseline6dee.
   Úzký normalizátor smí ignorovat jen tento ověřený packaging rozdíl, nonce
   a stejný per-session CSRF. Zvlášť porovnat JSON kontext a fingerprint JS.
   Ověřit přihlášené role, hosta, revokaci, bearer, CSRF a doménové checksumy.
3. Přínos: alespoň 12 kB méně decoded HTML na přihlášené header stránce a
   při teplé cache žádný síťový payload sdíleného JS. Zaznamenat raw/gzip
   velikosti, requesty, cache a skutečné FCP/load/content-ready. Nezavádět
   umělé zpomalování ani síťové mocky do časovaného browseru.
4. Skutečný Chrome desktop/390px, cold/warm; 17 střídavých párů po 4 zahřívacích
   párech v každém scénáři. Studené i teplé FCP/content-ready nesmějí významně
   regresovat (po < před*1.2 +20ms). Časový přínos zveřejnit skutečný; transportní
   úspora není automaticky rychlejší SQL nebo produkční SLA.
5. Shodné computed styles/viditelný obsah a screenshoty. Funkční menu/offcanvas,
   toast, shortcuts, confirm cancel/accept, validace a odesílací zámek formuláře,
   návrat bfcache; push/search kontrakty cíleným srovnávacím JS testem. Žádné
   skutečné rozesílky, subscriptions ani platby. Anonymous nesmí dostat staff JS.
6. Celé aktuální CI, shoda testovaného/merged tree, pak protected deploy main
   s importovat_krouzky=false a uat_schvaleno=false. Ověřená úplná záloha,
   konfigurace/migrace/VS/smoke a anonymní GET před/po. Finální důkazy přes docs
   PR bez druhého deploye. Zachovat původní WIP a průběžně zapsat výsledky.

Další krok: úzká implementace a kontrakty mechanické shody, potom nativní
HTTP/Chrome před/po. Implementace, nový CI/merge/deploy: NOT RUN.


## Uzavření výběru — NEVYBRÁNO, prototyp zachovaný

Nové skutečné HTTPS GET obou existujících JS s Accept-Encoding:gzip prokázaly
identity přenos, nikoli gzip: Bootstrap80721 + app-ui8561 =89282bytes. Oba
mají Cache-Control:max-age=30. CSS má gzip/604800, tato politika se na JS
nevztahuje. [Bezpečná měření](verification/shared-header-script-20261110/production-js-transport.json).
Offline gzip6 odhad obou JS je26809bytes; není to ještě změřený nový HTTP zisk.

Přesun14.7kB inline JS do nového nekomprimovaného assetu může zvýšit cold
přenos, protože HTML už gzip má; krátká30s cache omezuje zisk navigací.
Proto se kandidát HJS nepřijímá do runtime. Mechanická shoda prefix/suffix,
JS a všech PHP hodnot PASS, srovnávací JS7případů a normalizátor1/8 PASS.
Native/browser výkon prototypu nebyl přijat ani nasazen. Vlastní prototyp
zůstane commitnutý pouze na codex/shared-header-assets-performance-20261110.

Přednost má menší a šířeji působící zásah: povolit gzip pro existující veřejné
JS pod assets, beze změny jejich dekódovaných bytes, HTML nebo počtu požadavků.
Zachovat30s cache, rootSW i ostatní typy a soukromé cesty. Nový řez P11-JSZ
bude mít samostatný plán, skutečný Apache/nativeHTTP/Chrome a release gate.
Zadání merge/deploy platí až pro plně přijatý JSZ; HJS se do produkce neslučuje.

Nástupnický [JSZ řez](JAVASCRIPT_COMPRESSION_PERFORMANCE_2026-11-10.md) nasazen v PR427; HJS prototyp4c6ffea0 nadále pouze ve vlastní větvi.


## P11-HJS2 — nové posouzení po nasazení gzip, 10. 11. 2026

ROZPRACOVÁNO. Nové zadání vlastníka: další nejpřínosnější řez, ověřit shodu,
měřit před/po, po plném PASS commit/merge/deploy. Zachovat původní i prototypové WIP.
Původní zamítnutí HJS výše platí pro tehdejší nekomprimovaný hosting; neoznačuje
se dodatečně za přijatý výsledek. P11-JSZ nyní skutečně gzipuje veřejné JS.
Base main faed76c0ee94d210c99f67a20a92604da8f61206; potvrzený runtimef682cc46,
deploy42774356221 SUCCESS. Vlastní větev codex/shared-header-script-gzip-20261110,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.

Kandidát bodu7 analýzy: opakovaný staff inline JS14812B, čisté tělo14717B;
přesunout totožný kód do veřejného assets/staff-header.js a ponechat6 čerstvých
serverových hodnot v soukromém JSON konkrétního HTML. Původní prototyp4c6ffea0
zůstává ve své větvi; soubory z něj posoudit jednotlivě, neslučovat celou větev.
Nezavádět cache práv, JSON ani HTML, neměnit cacheTTL30s, session, SQL, guardy,
pořadí, synchronnost nebo aktivaci globálního hledání/push.

Postup a přejímka před změnou:
1. Mechanická shoda JS/PHP výrazů, prefixu/suffixu hlavičky; srovnávací NodeVM,
   JSON/HTML normalizátor a PHP kontrakty. Žádné dynamic hodnoty v assetu.
2. Nový nativní celý HTTP pár na obou MariaDB, čerstvá syntetická DB, role,
   CSRF/revokace/bearer/DOM i doménové checksumy. Nenahrazovat serverovou shodu
   pouhým počtem řádků nebo starou přejímkou prototypu.
3. Reálný Apache před PHP workers: existující gzip .htaccess pro statický JS,
   HTMLgzip a30sJS cache obě varianty; žádné síťové mocky/latence. Stejné backend
   a proxy náklady oběma. Při /bin zachovat Apache403 před PHP404 guardem.
4. Skutečný Chrome, coach Moje/Pracovní pozice, lead Správa, public profil,
   desktop/mobile cold/warm,17 střídavých párů/4warmupy. Hash skutečně přijatého
   JS, computedstyles/pixely a navigace/form/confirm/toast/shortcuts. Warm cache
   vyhodnotit podle skutečných requestů, nedeklarovat cachehit po expiraci30s.
5. Přijmout jen při HTMLdecoded úspoře nejméně12kB na staff a prokázané warm
   gzipHTML úspoře; cold celkový payload HTML+novýJS nesmí regresovat více než
   5%+1024B, FCP/ready do20%+20ms vůči baseline, public kontrola beze změny.
   Pokud časový zisk není průkazný, tvrdit pouze přesně změřenou úsporu přenosu.
6. Všechno aktuální CI, shodný tested/merged tree, chráněný deploymain s oběma
   flagsfalse, backup/config/VS/migrace/index/smoke a nezávislé produkčníGET,
   decodedasset/hash, veřejnéDOM, zápis do plánu/registru/CURRENT/handoff.
   Finální doklady přes docsPR bez druhého deploye. WIP7a7afd75 zachovat.

Nové implementační/test/release výsledky zatím NOT RUN. Další krok: obnovit a
prověřit úzký prototyp proti aktuálnímu main, přidat skutečný kompresní transport.
[Důkazy HJS2](verification/shared-header-script-gzip-20261110/RESULTS.md).


### HJS2 uzavření výběru — NEVYBRÁNO, zachované WIP

Nová diagnostika42783645170 ukázala skutečnou dosud neodstraněnou duplicitu
na pracovni_pozice.php: CSSbezverze + totožnéCSS?v=..., oba200/gzip, BootstrapAPI
funkční. Browserový guard1CSS byl nesprávný předpoklad prototypu; není to
prokázaná regrese jehoJS. Přednost má jednodušší odstranění celé duplicitníCSS
odpovědi na centrálním rozcestníku všech pracovníků (P11-ASSET2). HJS2 tedy
není přijat ani nasazen. Nativní10.3shoda PASS, kompletní Chrome/cold/warm gate
nesplněný. CodeQL nová DOMhranice vyřešena validací a URLpathname; negativní
VM8/8 PASS, finální bezpečnostní/performance přejímka HJS2 se netvrdí.
Všechny implementační commity v codex/shared-header-script-gzip-20261110 /PR429
zachovat, neslučovat do vybraného řezu. Nové PHP/JS/bezpečnostní hranice ani
JSZhelper změny se nepřimíchají do ASSET2. Původní WIP7a7afd75 zachovaný.


## P11-HJS3 — společný skript po odstranění duplicitních stylů, 10. 11. 2026

ROZPRACOVÁNO. Vlastník Vývoj/Codex, zadavatel Marek. Nové přímé zadání:
další nejpřínosnější optimalizace podle analýzy, přesná shoda a měření před/po,
při všech PASS commit/merge/deploy, WIP zachovat, průběžný plán/handoff v repu.
Base5d6434dc6d4338d47e283d715a4b2c568950c986, runtimec29642f1,
deploy42905781826 SUCCESS. Větev codex/header-cache-performance-20261110,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-header-cache-performance-20261110.
Původní fio/d1221f35 a kis_rosters_admin.php SHA2567a7afd75 zachovat beze změny.

### Výběr a nové vymezení

Bod7 analýzy: pracovní hlavička stále opakuje přibližně14,8kB JS v každém
staff HTML. Čerstvá kontrola: celý hlavicka.php a původní scriptblock jsou
identické s HJS2 immutable referencí; žádné top-level globální deklarace v bloku.
Hlavní staff CSS duplicity už ASSET1/2 odstranily, ASSET1–7 jsou nasazené.
Zbylé CSS jsou menší množinou vstupů/odlišných autentizací; HJS3 zasahuje
společnou hlavičku všech pracovníků. Návštěvnost neznáme, absolutní souhrnný
produkční přínos netvrdíme. EVENTREAD při0veřejných kartách zůstává odložený,
RIX a oba starší HJS/HJS2 prototypy zachované a neslučují se jako celé větve.

Z HJS2 prověřit jednotlivé soubory a opakovat novou přejímku. Jeho staré
10.3PASS ani offlinegzip nejsou aktuální výkonový důkaz; HJS2 nevybrání dál platí.
Nový HJS3 obsahově adresovaný veřejný soubor staff-header.<SHA256>.js umožní
cache7d/immutable pouze této třídy souborů. Tím úspora nepřestává po původních
30s obecného JS cache. Ostatní JS/asset policy beze změny. Aktuálních6hodnot
včetně CSRF/push/URL zůstává v novém necacheovaném JSON konkrétního HTML.
Veřejný asset nesmí obsahovat osobní data, oprávnění, session nebo token.

### Akceptace zapsaná před implementací

1. Runtime jen přesun původního scriptu do souboru s obsahem v názvu + JSON
data. Stejná synchronnost, pořadí a veřejné JS API; bez defer/minifikace,
nové aktivace hledání/push, změny SQL/session/práv/flash/CSRF/writerů.
Prefix/suffix hlavičky stejné. TěloJS doslovně stejné mimo9explicitních
interpolací6PHPvýrazů a již prověřený fail-closed validační prefix/context
manifest URL konstrukci. VM včetně chybných dat, search escaping, confirm,
toast, shortcuts a push kontextu. Source SHA musí odpovídat názvu i SRI.
2. Cache pouze content-addressed staff soubor7d/immutable; všechny dřívější
JS zůstanou30s, CSS/rootSW/private cesty pod dosavadní politikou. Skutečný
Apache gzip/identity/q0/ETag/range/security, decodedbyte/hash/SRI i živý TLS
payload. Nepotlačovat CodeQL ani původní regresní testy; zachovat bezpečnost
nové DOMJSON hranice a ochranu proti externím/síťovým/neudaným URL cestám.
3. Nové native MariaDB10.3/11.4, plně migrovaná izolovaná syntetická DB;
celé HTTP všech rolí/revokace/CSRF a domainchecksumy, přesný normalizátor
pouze známého původního scriptu proti právě tomuto JSON+assetu. Žádné obecné
odstranění scriptů, textu, whitespace či verzí. Celý ostatní aktuální PHP kontext
stejný mezi fyzickými before/after kopiemi, nikoli symlink na změněný baseline.
4. Skutečný Chrome/Apache: coach Moje/Pracovní pozice, lead Správa a public
profilcontrol, desktop/mobile cold/warm,17střídavých párů po4warmupech, skutečné
requesty/payloady, FCP/ready, computedstyle/pixely a bezpečné klientské ovladače.
Přijmout staffHTMLdecoded úsporu≥12kB a prokázanou warmgzip celkovou úsporu;
cold HTML+novýJS nesmí regresovat více než5%+1024B, FCP/ready gate20%+20ms.
Publiccontrol beze změny. Časový zisk uvádět jen jako měření runneru, ne SLA.
5. Všechno aktuální CI, strict main a tested/merged tree shoda; až při PASS
chráněný deploymain s UAT/importfalse, backup/config/migrace/VS/index/smoke/
cleanup. Živé veřejné GET a novýJS/hash/SRI/gzip/cache před/po. Přihlášené
produkční UAT pouze s dostupnou schválenou relací, jinak výslovně NOT RUN.
Žádné reálné notifikace/push, formuláře, zápisy, objednávky/platby/změny rolí.
6. Průběžně [výsledky](verification/shared-header-cache-20261110/RESULTS.md),
registr/CURRENT/handoff/logy. Bezpečné výsledky přes PR, finální dokumentace
po všech CI sloučit bez druhého deploye. Původní i prototypový WIP zachovat,
stage jen vlastní cesty; historické ASSET4/6 LIMIT se nepřepisují na PASS.

Nové implementace/VM/native/browser/release zatím NOT RUN. Další krok:
selektivní obnova a kontrola prototypu, content adresování/cache kontrakty,
nová celá přejímka. Při neprokázané paritě/přínosu nevydávat. Flags vlastní CURRENT.

HJS3 checkpoint: source/8VM/1PHPUnit (16 assertions)/style/docs PASS; implementace
15 271 B s obsahovým názvem a SRI. Native/browser/přínos/release NOT RUN,
bez nasazení do jejich dokončení. Podrobnosti v průběžných výsledcích.

Implementace `7de1c569` pushnutá v [#449](https://github.com/KovoPraha/evidenceTreninku/pull/449); lokální PASS výše.
Aktuální native2DB/Chrome/ostatní CI probíhají; merge/deploy NOT RUN.
