# P11-R13-F — hledání a datumové filtry zdravotního přehledu

8. 11. 2026, Europe/Prague. **TECHNICKY HOTOVO / NASAZENO**. Původní plán7.11. před implementací; níže průběh a zamítnuté varianty.
Vlastník vývoj podle nového výslovného zadání Marka. Po přesné shodě, přínosu
a zelených kontrolách commit/merge/standardní deploy; zachovat WIP.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md),
[výsledky](verification/medical-filter-read-performance-20261107/RESULTS.md), [D15](DECISION_LOG.md#d2026-11-07-15).

## Výchozí stav a izolace

Baseline35fdad4483ec4326e5da9b6ffbb908f77a4df85f, nasazený runtime938e9c50.
[Uzavřený stavový řez #381](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md)
nechal hledání/datum původní cestou: scoped varianta opakovala hledání a JOIN lidí
uvnitř agregace a mírně regredovala. Tyto předchozí výsledky zůstávají historií.
Nová větev `codex/medical-filter-read-performance-20261107`, vlastní dřívější čistý
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Jeho staré bezpečné untracked výstupy zachovat. Původní checkout/WIP nedotknout;
`kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` znovu shodný.

## Navržený postup a varianty

1. Zmrazit kompletní aktuální list i stavovou projekci jako nezávislou referenci.
2. Pro platné hledání/datum bez přímé osoby a transakce nejprve omezená sonda
   nejvýše257ID kandidátů. Prázdný výsledek vrátit bez opakovaného hledání.
   Nejvýše256kandidátů vyhodnotit původní cestou s omezením na nalezená ID;
   původní predikát opět ověřit nad touto malou sadou, pořadí/počty přesně stejné.
3. Široký stavový výsledek: sdílená současná agregace historie bez opakovaných
   JOIN lidí/hledání uvnitř. Datum omezí i agregovaná sledování, bez opakování
   textového hledání. Budoucí explicitní náhrady, limity a stavové vyhodnocení
   kanonické. Široký seznam bez stavu ponechat původní stránkovací cestou;
   přijmout ji jen při doložené neutrální režii. Bez nové cache nebo rekurze. Původní omezení bez DDL nahrazuje níže V5.
4. Porovnat přesná pole/typy/pořadí/počty/stránky/filtry s baseline: všechny stavy,
   q prázdné/široké/vzácné/žádné, více slov, diakritika a doslovné !%_, data od/do
   samostatně i spolu, inkluzivní hranice, invalidní rozsah, kombinace a přehnaná
   stránka. Osoba/transakce původní SQL; legacy řetězce a fallbacky zachované.
5. Nativní MariaDB10.3/11.4 na vlastním syntetickém schématu, query cache OFF,
   stejné seedy, střídavé párové časy/helper SQL/fetched rows a checksumy.
   Celé HTTP/Chrome na stejném migrovaném10k/100k fixture před/po, úplné HTML,
   samostatná diagnostika a nulové doménové DML/DDL. Zapsat reálné indexy CI/EXPLAIN;
   bez tvrzení o produkčních indexech nebo skutečných rows examined z odhadů.
6. Archivovat bezpečné výsledky a průběh v repu; po doloženém přínosu a zelených
   kontrolách merge a standardní deploy přesného testovaného stromu, import/UAT false.
   Čtecí produkční kontrola před/po, výsledkové předání do main. WIP hash znovu.

Globální agregace bez sondy zamítnutá pro vzácné dotazy; opakovaná scoped agregace
s JOIN lidí má předchozí doloženou režii. Nová sonda nesmí stát plné opakované
hledání u prázdných výsledků. Výchozí stavový seznam bez doplňkových filtrů neměnit.

## Akceptace a meze

První implementace/parita a měření hotové, PR #385 otevřené; merge/deploy zatím NOT RUN.
Původní plán uložený před implementací v7ae100b2. Přínos ověřit zvlášť pro široké/vzácné/prázdné dotazy a datum;
při regresi upravit variantu nebo zachovat konkrétní původní cestu a důvod zapsat.
Práva, writer, soukromé dokumenty, zdravotní rozhodnutí a integrační flags neměnit.
Původní návrh byl bez DDL; po zamítnutí V1–V4 jej nahrazuje níže V5 s krycím indexem.
Před migrací ověřit skutečný produkční indexový inventář. Přihlášené produkční UAT/mobil/souběh a ostatní K0.2 samostatné.

## Průběh implementace

První implementace hotová lokálně, přesný současný baseline35fdad44 včetně jeho stavového readeru zmrazený nezávisle. Nová sada7/454 a společná regrese25/801 PASS, styl/lint PASS. Přímá osoba a transakce zachovávají SQL/bindingy; předchozí test požadující původní SQL i u q/data nahrazen novým kontraktem výsledků podle D15. Nativní profiler14případů×4objemy100/1k/10k/100k,17střídavých párů a4zahřátí s query cache OFF. Samostatné nativní joby nezvětšují rozpočet staré integrační sady. HTTP benchmark porovnává přesné dva původní aplikační soubory, všech30k/300k záznamů a rozložená data revizí na jednom seedu;15kladných/12záporných případů a6Chrome scénářů. Výkon zatím NOT RUN; implementace je kandidát k měření, nikoli k deployi.

## Pokračování8.11.2026

cc2a825e má15CI PASS a celý GET100k hledání907,81→462,51ms. Smíšené nativní datum_day a date_to regredovaly, proto zatím nenasazovat. Bezpečný archiv v protokolu. Další varianta pouze pro široké datumové stavy použije korelované nejnovější verified/poslední ID přes existující watch_id/id index; původní pure status/future/fallback beze změny. Hledání a kandidátní sonda zachované. Znovu přesná parita a cache-off oba DB/HTTP/Chrome páry před release. WIP zachovaný.

Datumová varianta2 implementovaná: jeden korelovaný nejnovější verified přes watch_id/id; poslední libovolný záznam jen když verified chybí. Datum je next_review_on sledování, historie ani explicitní řetězec nejsou ořezané podle issued_on/valid_until. Vše v původním počtu SQL, bez N+1 síťových dotazů a bez DDL. První nativní7/466 na každé DB PASS; aktuální lokální a nové nativní/HTTP ověření V2 ještě běží.

V2 korelované datumové lookupy měly na nativním11.4 ještě vyšší režii (date_to502,30→748,19ms), zamítnuté. V3 použije jedinou agregaci kandidátů/head ID s původním WHERE a MAX(next_review_on), poté jen head records; vnější people/watch filtry se neopakují. Nejde o další korelovaný lookup ani o změnu indexů. Nativní i migrované CI indexy jsou zaznamenané; produkční indexový inventář NOT RUN. Nová parita/přínos před release.

V3 doplnění: filtrované okno třídí pouze ID sledování a dvou head záznamů; metadata se připojí až vně okna pro vybranou stránku v témže SQL. Výchozí stavový seznam bez doplňkových filtrů zůstává původní. Menší pracovní sada je hypotéza k novému měření, nikoli tvrzení o skutečných rows examined nebo diskových tempech. Lokálně18/751 PASS, styl/lint PASS. Nová nativní/celá HTTP měření čekají; bez nasazení.

V3 datum_day na11.4 253,72→267,98ms mírně regresní; V4 anti-join latest verified pro datum a HAVING pro stavy bez verified s explicitními budoucími výjimkami. Další2pending hledací případy v benchmarku a datumové legacy grafy v paritě. Bez DDL, nové indexy se nezavádějí; skutečné produkční indexy se tím neprohlašují za prověřené. Měření/testy V4 před release.

V4 lokálně26/813 PASS včetně nových12datumových legacy kontrol; nativní profiler nyní16případů×4objemy×17párů/DB, celkem2176párů. HTTP17kladných/12záporných případů a7Chrome scénářů. Indexově závislá korelovaná V2 zamítnutá; do deploye jde až vítězná ověřená cesta bez DDL. Produkční indexový inventář zůstává NOT RUN.

V4 SQL-only datum/poloviční pending stále regredovaly. V5 měří neunikátní krycí index (watch_id,id,state,issued_on,valid_until,replaces_id): zvlášť starý kód/staré indexy, starý kód/nový index a nový kód/nový index. D15 bezDDL tímto podloženě nahrazeno. Nativní dvě data-shodná schémata/query cache OFF, HTTP stejný fixture a index mezi fázemi. Produkční read-only inventura před migrací, ověřená záloha, idempotentní online ADD INDEX a kontrola před aktivací. Žádné klinické datové změny. Vše nejprve CI, nasadit jen po prokázaném přínosu.

V5 lokálně44 testů /1328 assertions PASS (medical + deploy kontrakt). Nativní profiler má512MB buffer pool jen vlastního CI serveru, dvě data-shodná schémata,16případů×4objemy×17rotujících trojic na každé DB; index-only odděluje přínos indexu od kódu. HTTP porovnává původní kód/staré indexy proti novému kódu/indexu,17kladných/12záporných případů,7Chrome scénářů. Před každou fází se mění pouze index syntetické DB při vypnutém PHP serveru. Produkční diagnostika čte pouze metadata v READ ONLY transakci, před zálohou a po migraci před aktivací. Nativní/HTTP přínos V5 zatím NOT RUN.

V5/7c083f0a měření dokončené: nativní parita12/495 na každé DB, všechny64případy a3checksumy PASS; index-only odděleně. Na100k11.4 široké hledání985→518ms, datum od715→469ms, do674→454ms. Celé HTTP100k hledání1183→573ms, datum od883→509ms, do882→326ms;2040časovanýchHTTP vzorků/48zápornýchGET/364Chrome pozorování, úplné HTML/checksumy/úklid PASS. Malé sady mají režii sondy dojednotekms, původní široké seznamy bez stavu neutrální. Důkazy v iteration5. Současná aplikační/migrační SHA256 přesně shodná s měřením; fa902ba6 navíc přebírá dokumentační #387/Q01 bez ztráty obsahu, pracovní eventID posunuty za již přijaté E01. Aktuální CI běží; merge/deploy zatím NOT RUN.

8.11.2026: PR #385 sloučený jako519ff098961943f1aa58a70e40fed3ffba37cc96 po všech15CI na2362f3a4. Stromy přesně shodné, měřené zdroje SHA256 shodné. Produkční workflow42477866185 spuštěné s import/UAT false; zatím příprava runneru, nasazení neprohlášeno za dokončené. Čerstvé před-GET5×200 a zdravotní anonym403. Finální provozní důkazy připravuje větev codex/medical-filter-release-proof-20261108.

## Uzavření 8. 11. 2026

PR #385 /519ff098 sloučený po15CI PASS; chráněný deploy42477866185 SUCCESS. Přesný strom a měřené zdroje shodné. Ověřená záloha389tabulek/2triggery, produkční inventář potvrdil nový krycí index po migraci před aktivací; VS zachované, migrace current. Veřejný DOM5/5 a anonymní zdravotní403 před/po PASS. [Provozní důkazy](verification/medical-filter-release-20261108/RESULTS.md). Celé syntetické100k GET hledání1214→586ms, datum od920→519ms, do915→344ms; malé sady/režie a variabilita kontrol explicitně v protokolu. Přihlášené produkční UAT NOT RUN, samostatná zbývající přejímka; technický optimalizační řez uzavřený. WIP zachovaný.
