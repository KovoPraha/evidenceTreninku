# P11-HJS3 — průběžné ověření společného pracovního skriptu

NASAZENO / technická přejímka PASS; přihlášené produkční UAT NOT RUN. [Plán](../../SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs3--společný-skript-po-odstranění-duplicitních-stylů-10-11-2026).
Čerstvý main5d6434dc: původní hlavička byte-shodná s referencí HJS2, žádné top-level JS deklarace.

## Lokální kontrola implementace

- PASS: prefix/suffix hlavičky, původní JS mimo vyjmenovaný validační prefix,
  same-origin manifest a 9 interpolací; všech 6 PHP výrazů tokenově shodných.
- Veřejný soubor 15 271 B, SHA256
  `222b64217870ab726e3ac50483b00fd54e4e0d79251917dfb5162b5de275c8ee`;
  obsahový název a SHA384 SRI kontrolované mechanicky. Původní blok 14 812 B.
- PASS: 8 Node VM porovnání (push subscribe/unsubscribe, fresh CSRF, chybné
  cesty/context, hledání a escaping, fallback confirm). Žádná reálná oznámení.
- PASS: porovnávací PHPUnit 1 test / 16 assertions, včetně odmítnutí jiné URL,
  hashe, SRI, nonce, dalšího skriptu/CSS/whitespace a nesouhlasného CSRF formuláře.
- PASS: PHP style 4 soubory / 0 oprav / 0 chyb; Python syntax, JS syntax,
  aktuální dokumentace 80 řádků / 5 317 odkazů / 0 chyb.
- Native/browser/úspora celého gzip přenosu/aktuální CI/merge/deploy: NOT RUN.
  Dokud neprojdou, žádné nasazení. Původní WIP a staré prototypy zachované.

Porovnání celých stránek používá pouze nonce z CSP a ověřený session CSRF;
neodstraňuje CSS, další skripty, verze ani whitespace. Obě větve fixture mají
fyzicky stejný aktuální PHP kontext mimo hlavičku; celé syntetické schéma se
kontroluje checksumy. Apache před variantou používá původní assets/.htaccess;
nových 7 dní platí jen pro obsahově adresovaný staff soubor. Ostatní JS 30 s.

Implementace `7de1c569` pushnutá v [#449](https://github.com/KovoPraha/evidenceTreninku/pull/449); lokální PASS výše.
Aktuální native2DB/Chrome/ostatní CI probíhají; merge/deploy NOT RUN.

První native běh42916624735: FAIL při přípravě porovnávacího stromu, chyběla
skrytá vývojová `.php-cs-fixer.dist.php` v kopii, zatímco kontrola všech PHP ji
správně požadovala. Aplikace ani měření ještě neběžely. Oprava kopíruje i skryté
kořenové PHP soubory; porovnání všech ostatních PHP zůstává přesné. Obě izolované
DB/config/účty byly uklizené. Apache gzip/browser samostatný běh42916624824 PASS;
aktuální celek musí projít znovu na opraveném HEAD, nejde o release PASS.

Obsahově adresované JS mají v `.gitattributes` vynucené LF stejně jako již
verzované UI payloady. Checkout na Windows tak nezmění obsah proti SHA/SRI.

Běh42916928473: 10.3 HTTP 1k/10k/30k, 357 checksumů a security PASS.
11.4 se zastavilo v klientském ovladači: `Shift+/` posílal `/` místo `?`,
čekání na modal proto vypršelo už na původní before variantě. Oprava pouze
ovladače používá skutečné `?` a Bootstrap shown/hidden události podle již
ověřené WorkspaceAssetsBrowser kontroly; žádná změna appJS ani potlačení
funkční zkoušky. Neúplný běh není přijímací výkonový důkaz.

## Přijímací měření na konečném aplikačním HEAD

Technická přejímka, runtime merge i produkční deploy PASS. Konečné doklady vydání jsou níže; úvodní checkpointy zachycují historii jednotlivých bran.
Testovaný `dd44cff7a657d79c7bb4f2aa7e959c779f3be8d9` a main
`b753f6e765d83c402cc5e3b2c48ae448865749bb` mají přesně stejný celý Git tree;
[14/14 CI](release/app-checks.json), [strict brána](release/premerge-gate.json),
[merge #449](release/merge.json). Původní WIP 7a7afd75 zůstal beze změny.

[Native běh42917624902](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42917624902)
PASS na MariaDB10.3 i11.4. 350 migrovaných tabulek +7 kompatibilních fixture
tabulek: všech357 checksumů stejné po každém porovnání1k/10k/30k. Čtyři celé
HTTP případy, 17 střídavých párů po4warmupech pro každou velikost; shodné HTML,
bezpečnostní hlavičky, CSRF a role403/302. AJAX fragment beze změny. Přesná
source parita +8VM +porovnávací PHPUnit1/16 a projekční PHPUnit3/562 PASS.
[10.3 souhrn](native/mariadb-10.3/integrated-summary.json),
[11.4 souhrn](native/mariadb-11.4/integrated-summary.json),
[role/revokace/CSRF](native/mariadb-11.4/security.json),
[cleanup](native/mariadb-11.4/runner-cleanup.json).

Skutečný Apache a Chrome desktop1036×703/mobile390×844, cold/warm:
544 měřených navigací +128warmupů, 24 funkčních případů (navigace, toast,
klávesy, oba výsledky confirm, validace/zámek formuláře/pageshow/koncept).
8 pixelových párů přesně shodných, computedstyle/geometrie stejné, bez chyb
konzole/požadavků; pouze známé volitelné favicon404 odděleně. Veřejný profil
kontrola má shodné decoded HTML a nevkládá pracovní skript ani writer.
[Úplná přejímka a mediány](native-acceptance.json),
[4 coach pixelové páry](native/mariadb-11.4/header-pixels-coach.json),
[2 lead páry](native/mariadb-11.4/header-pixels-sports_lead.json),
[2 public páry](native/mariadb-11.4/header-pixels-public.json).

| Stránka (desktop warm) | Gzip před → po (B) | Úspora | První cold navíc (B) |
| --- | ---: | ---: | ---: |
| Pracovní pozice | 11 316 → 7 929 | 29.93% | +992 |
| Moje tréninky | 12 274 → 9 160 | 25.37% | +1 264 |
| Správa tréninků | 13 363 → 9 939 | 25.62% | +955 |

Měřena skutečná gzip těla HTML + pracovního JS; HTTP hlavičky a ostatní,
shodné assety se do této tabulky nepočítají. Nový JS15 271B/gzip4379B se při
warm načtení skutečně znovu nepřenáší. Každé staff HTML o13 899B menší;
warm úspora25,37–29,93%, cold navíc955–1264B (7,15–10,30%). Předem zapsaný
cold limit5%+1024B prošel; při druhé pracovní stránce už převažuje úspora.
Cache nového obsahového URL7d/immutable, ostatní JS30s, HTML/session/CSRF
pod původním privátním kontraktem. Žádná cache práv nebo osobních údajů.

FCP/ready prošly předem zapsanou branou20%+20ms ve všech16 skupinách;
drobný časový rozptyl runneru není důkaz produkční latence ani SLA. Např.
mobile warm Správa ready75,8→87,9ms uvádíme v úplných mediánech beze skrytí.
Přínos této změny je opakovaný přenos a sdílení cache, nikoli prokázané
plošné zrychlení serveru. Backend HTTP1k/10k/30k také splnil regresní bránu.

[Samostatný gzip/cache běh42917624810](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42917624810)
PASS: decoded payloady/hash, gzip/identity/q0/ETag/range/security a cache
včetně pouze obsahové výjimky7d. [Native doklad](compression/native.json),
[browser](compression/browser-summary.json), [cleanup](compression/cleanup.json).
Tento starší JSZ kontrakt porovnává zapnutý/vypnutý gzip; jeho úsporu
nepřičítáme k novému HJS3 přínosu. Skutečné před-po výše používá gzip v obou.

Staré supersedované Testy běhy42916624725/42916772377/42916928549/42917224573
zrušené pouze kvůli nahrazenému HEAD. Konečný HEAD má všech14 skutečných PASS;
zrušené a výše uvedené neúplné běhy nejsou přijímací důkaz.

## Vydání a produkční hranice

[Deploy42920002806](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42920002806)
spuštěn jednou na přesném b753f6e7, UAT/importfalse. Do SUCCESS zůstává poslední
prokázaný runtimec29642f1/deploy42905781826. Produkční after/receipt zatím čekají.
[Předchozí živé GET](release/live-before.json):4staff redirect302 +login200,
5 kanonických UI souborů shodných, nový staff soubor404. Žádné POST.
[Uživatelský Chrome](release/production-session.json) otevřel /login.php;
přihlášené produkční UAT NOT RUN. Fyzický mobil a reálné push oznámení NOT RUN.
ASSET4 tři historické hash LIMIT a ASSET6 mobilní kontrolní foto LIMIT zachované;
ASSET7 přihlášené produkční UAT zůstává NOT RUN. Žádné role/účty/WIP neměněny.

## Konečný produkční doklad — 11. 11. 2026

[Deploy42920002806](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42920002806) SUCCESS na b753f6e7; 2456 testů/56289 assertions, záloha389tabulek/2triggery, migrace/config/VS/index/HTTP200/cleanup PASS; UAT/importfalse.
[Úplná release kontrola](release/release-controls.json), [průběh](release/deploy-summary.json).
Záloha SHA256 `68b93d12fb82cad557596fd2edf1aba3224b0766ee4f968f48d7cb6b42587489`; nezávislý restore této konkrétní zálohy NOT RUN.
Původní konfigurace zachovaná, config se nedoplňoval; soukromé klíče a zálohy
ověřené. Migrace pending0, dosavadní variabilní symboly i definice/indexový
inventář zdravotního čtení stejné. Release kopie config a diagnostické indexové
soubory uklizené. Přímý runner HTTP0, předepsaný serverový curl200 PASS.
Import kroužků přeskočený. Žádné další přepnutí UAT nebo aktivace hledání/push.

[Živé TLS GET po](release/live-after.json) proti [před](release/live-before.json):
4staff redirect302 +login200 a celé normalizované tělo/bezpečnostní hlavičky
shodné. Pět dosavadních UI payloadů byte-shodných s kanonickým Git obsahem;
obě JS dál cache30s, tři CSS7d. Nový staffJS404→200, decoded15 271B přesně
SHA256/název i SHA384 SRI, gzip/identity/gzip;q=0 a 7d/immutable ověřené.
[Reprodukovatelná čtecí sonda](release/live-http-probe.py.txt) neprovádí POST,
neukládá formuláře a nenormalizuje nic kromě CSP nonce a známého form CSRF.
Anonymní redirect neověřuje soukromé šablony v produkci; jejich vizuální/funkční
shoda je doložená izolovaným native/Chrome během, živé přihlášené UAT NOT RUN.

[Dokumentační #450](https://github.com/KovoPraha/evidenceTreninku/pull/450)
obsahuje plán, handoff, aktuální stav, měření a bezpečné release doklady;
sloučení vyžaduje všech11 aktuálních CI a strict main. Tento PR nepřináší runtime
změnu ani druhé nasazení. Integrita bezpečných dokladů je v kanonickém
[Git manifestu](release/artifact-integrity.json); nezahrnuje sám sebe.

Původní fio/d1221f35, jediný tracked WIP kis_rosters_admin.php a jeho SHA256
7a7afd75…64733e5 zůstaly shodné. Žádný stash/reset/clean, cizí staging ani merge
starých HJS/HJS2 prototypů. ASSET4/6 LIMIT a ASSET7 UAT NOT RUN zachované.
Další krok je případná přihlášená produkční přejímka s dostupnou schválenou
relací, bez změn rolí nebo skutečných notifikací; není tvrzená jako hotová.
