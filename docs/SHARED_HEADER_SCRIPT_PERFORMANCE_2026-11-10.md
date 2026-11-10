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
