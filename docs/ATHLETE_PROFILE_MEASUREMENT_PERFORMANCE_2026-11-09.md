# P11-RHM — načítání měření profilu sportovce

9. 11. 2026. Vlastník vývoj/Codex. Stav RHM: HOTOVO / NASAZENO. Návazný P11-RHMS: ROZPRACOVÁNO (souhrn).
Aktuální uzavření: #418/5cc9cf83, deploy42654922651 SUCCESS;
HTTP30k −28.2/32.1 %, Chrome uloženéMěření warm−10.6/cold−22.4 %.
[Produkční důkazy a meze](verification/athlete-profile-measurement-release-20261109/RESULTS.md).
[Aktuální návaznost P11-RHMS](#p11-rhms--souhrn-měření-nové-zadání-9-11-2026).
Níže je původní plán a průběh pokusů; současný stav/flags vlastní CURRENT.

[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_MEASUREMENT_2026-11-09.md),
[důkazy](verification/athlete-profile-measurement-performance-20261109/RESULTS.md).

## Rozsah a oprávnění

Aktuální pokyn vlastníka výslovně povoluje optimalizaci měření a po shodě/přínosu
commit/merge/protected deploy. Import/UATfalse; žádné role, platby nebo osobní zápisy.
Baseline main5ff9fa2a65123aa1eef58be3678943cb4bfc2b53, runtime c3d34b87,
deploy42625502461 SUCCESS. Předchozí RHI/RAH/RAP úspory se novému řezu nepřičítají.
Větev codex/athlete-measurement-performance-20261109, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel/codex/fio-auto-reconciliation-recovery
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Stage pouze vlastní cesty; bez nových DDL/indexů/cache nebo změny DB nastavení.

## Diagnóza a varianty

Aktuální souhrn počítá ROW_NUMBER pro všechny vazby a znovu připojuje všechna
měření, aby dostal binární typy a první výskyt. Předchozí RHA odmítlo prosté
přenesení všech typů do PHP kvůli regresi; tuto variantu neopakovat.
Kandidát: binární GROUP BY s COUNT a jedním prvním klíčem přes MariaDB
GROUP_CONCAT(JSON_ARRAY(training_id,poradi,measurement_id) ORDER BY ... LIMIT1).
Pak připojit pouze první reprezentanty skupin a řadit jejich původní date/id/
nullableporadi/measurementid, bez číslování celé historie či hashových klíčů.
NULLtyp se počítá do all, nikoli do mapy typů, jako dřív; duplicity vazeb se počítají.

MariaDB LIMIT uvnitř GROUP_CONCAT podporuje od10.3.3. Před fastpath ověřit
PDO server verzi a aktuální SESSION group_concat_max_len alespoň128bytů;
klíč obsahuje pouze3integer/null hodnoty a vejde se do limitu. Při menším
limitu nebo jiném backendu použít původní okénkový souhrn, bez změny nastavení.
Produkční read-only indexový protokol posledního release uvádí MariaDB10.3.39.
[Oficiální syntaxe a truncation](https://mariadb.com/docs/server/reference/sql-functions/aggregate-functions/group_concat),
[verzová podpora](https://mariadb.com/docs/release-notes/community-server/old-releases/10.3/10.3.3).
Alternativa: ponechat souhrn a optimalizovat jen stránku klíčů, pokud agregační
kandidát nezrychlí celou odpověď. Při nedoloženém přínosu kandidáta neuvolnit.

## Akceptace

1. Plán commitnout před implementací; zmrazený reader+parent+endpoint z5ff9fa2a.
2. Přesná parita úplného návratového objektu a PHP typů proti zmrazené metodě:
   všechny stránky/filtry, case/mezery/diakritika/numerické a neznámé typy,
   NULL/empty typy, nulové/null datum, poradiNULL/0/negativní/extremy,
   remízy/duplicate vazby/měření ve více trénincích/orphans/foreign/unlinked,
   čerstvé změny. Native/emulated prepares a stringify, limity128 a4 pro fallback.
3. SQLite a skutečná MariaDB10.3/11.4; cacheOFF,1k/10k/30k tréninků a až270k
   vlastních vazeb.4warmups+17střídavých before/after párů, typ vše/exactcase/
   trailing space/neexistující/page2/last, velký/malý/prázdný profil.
   Wholefragment byte-parita, parentHTML/JS pouze nonce/CSRF normalizace.
4. Samostatná SQL/řádková/PHPpeak diagnostika mimo prosté HTTP časy; cílově
   alespoň20% úspory měření30k bez podstatné regrese kontrol či paměti.
5. Skutečný Chrome/PlaywrightCLI: default a uložené Měření,3cold+10warm/scénář/
   variantu, fetch→DOM+2frames/ResourceTiming/celá navigace odděleně,
   filtry/pagery/záložky/390px/chyby0. Bez stubů nebo změny UI kvůli benchmarku.
6.12staffrolí/bearer303/retarget/revokace/expirace/scope/CSRF/logout,
   2fallbacky parentu,11doménovýchchecksumů a vlastní úplný cleanup PASS.
7. Celé aktuálníCI, measured-source Gitbloby a whole tested/merged tree,
   protected deploy, backup/migrace/VS/config/index/smoke, veřejnéGET/DOM před/po.
   Produkční výkon/přihlášenéUAT/obnova této zálohy samostatně NOT RUN.
8. Průběžně plán/handoff/registr/deníky; finální bezpečné důkazy přes PR do main,
   dokumentace bez druhého deploye. Historie/header/writery/session/HTML nezměněné.

Další krok: implementovat kandidáta a native přesné testy, pak celé HTTP/Chrome.

## Lokální kandidát

Implementována MariaDB agregace s jedním JSON klíčem na binární typ a COUNT,
připojení reprezentantů až po agregaci. Typové filtry i stránka zůstávají
původní. Podmíněná verze>=10.3.3 a SESSIONlimit>=128; bez nastavování DB.
Původní window souhrn zachovaný pro SQLite/MySQL/krátký limit. Metadata dotaz
přidává1SQL na MariaDB, případný přínos se musí projevit v celémHTTP.

Lokálně SQLite10testů/1139assertions PASS (původníprofil/historie/header + nové
3testy/247), PSR12 všech6dotčenýchPHP, PythonAST a composer validate --strict PASS.
Jde o portable/fallback paritu včetně64bitklíčů; skutečný novýSQLfastpath a
limity128/4 musí prokázat nativní CI na MariaDB10.3/11.4. Zatím NOT RUN.
Žádná změna readeru mimo measurements()/nový helper, parent/endpoint/UI/
security/session/writery byte-nezměněné. Baseline3soubory zmrazené z5ff9fa2a.
Vlastní runner připraven pro1k/10k/30k,8scénářů/3profily,4warmups+17párů,
oddělenou SQL/PHP diagnostiku a52Chrome navigací obou režimů.

## Pokus 1 — JSON agregace odmítnuta

PR #418, commit3b7fbfba, [native run42632034781](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42632034781).
Nativní přesná parita obou DB3testy/499assertions PASS; celé HTTP/role/Chrome
provedeny, výkonnostní akceptace FAIL. Mediány30k celé odpovědi:

MariaDB10.3: 756.473 → 1215.773 ms (+60.7%).
MariaDB11.4: 798.339 → 839.414 ms (+5.1%).

První bezpečné vzorky, diagnostika, testy, security a cleanup zachované v
[rejected-json](verification/athlete-profile-measurement-performance-20261109/rejected-json/failed-run-identity.json).
Souhrnný report nevznikl, protože gate před ním správně zastavila běh.
Identita prvního pokusu odvozena z Gitblobs a označena jako odvozená;
nový runner ukládá skutečné source-identity hned při startu. Zatím nic nasazeno.

## Pokus 2 — integer klíč a omezení prvních stránek

JSON serializaci/parsování nahrazuje přesný integer tuple s oddělovačem `:`
a tokenem `N` pro NULL pozici. Dekódování DECIMAL(65,0), původní verze/limit
fallback zachovány. Tréninky pro stránky1–32 se nejdřív omezí na prvních
page*25 s EXISTS vlastní vazby a přesně stejného typu. Každý takový trénink
přispívá alespoň jednou vazbou; proto nemůže chybět žádná potřebná položka.
Vlastník/typ se znovu ověří v detailním výběru. Pozdější stránky zůstávají původní.
Test nově pokrývá80vazeb jednoho tréninku, přechod32/33 a prázdné/typové filtry.
Lokálně nové4testy/271assertions PASS. Nativní přínos druhého pokusu čeká;
release pouze po nové shodě, měření a celém CI.

Převzat main5977897f/#416 (oprava rychlé přihlášky), runtime profilu beze změny.
Konflikty registrů sjednoceny; přijatý QR01 event12 zachován, vlastní RHM
události přeznačeny13/14. Nový celý CI a native běh na merge head.

## Pokus 2 odmítnut, pokus 3 — pouze bounded selector

[Run42634946860](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42634946860),
head3b127f98, MariaDB10.3 native4/523 PASS, sourcehashes přesné, HTTPparita PASS,
výkon FAIL:30k primary519.448→914.333ms (+76.0%).
Samostatná diagnostika: původní souhrn410.259→748.548ms, ale detail164.894→4.190ms.
Druhá agregace je stále pomalejší; zamítnuta. První/nové vzorky se nenahrazují.
[Zdrojová identita a vzorky](verification/athlete-profile-measurement-performance-20261109/rejected-integer/native-10.3/source-identity.json).
MariaDB11.4 tohoto pokusu dosud běží, bezpečné výsledky budou doplněny.

Třetí kandidát vrací původní numeric-window souhrn byte-přesně, odstraňuje
nový helper/verzový probe/metadata SQL a ponechává jen bounded training selector
pro stránky1–32. Získané typy/součty ani jejich pořadí se nepočítají jinak.
Čerstvé GROUP_CONCAT limity128/4/1048576 nadále pokryté paritou a nezměněnou
SESSION hodnotou; původní souhrn se o limit neopírá. Detailní scope/type recheck
zachovaný. Nové celé native/HTTP/Chrome měření a CI před případným release.

## Pokus 3 — HTTP přínos zatím nedoložen, diagnóza celého bootstrapu

[Run42636506112](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42636506112),
headd34e5427. MariaDB10.3 native4/523 a shoda PASS, HTTP30k712.779→708.897ms
(−0.5%),20%gate FAIL. Diagnostika detail217.575→5.160ms se do celéhoHTTP
nepromítla. Tento kandidát se zatím neslučuje/nenasazuje.
Samostatný instrument původně načetl db.php před profilovacím PDO, proto
neviděl SQL bootstrapu. SQL úspory jsou dílčí diagnóza, ne přínos celé aplikace.

Doplněn instrument pouze v izolovaných diagnostických kopiích db.php/readeru/
endpointu. PDO se profiluje od samotného vytvoření ve stejném pořadí bootstrapu,
bez přednačtení db.php; vypíše i SHA256 skutečně načtené třídy. Aplikace ani
prosté časovací workers se tím nemění. Ruční diagnostic_only workflow používá
jen syntetickou30kDB a přesné testy; nenahrazuje úplný HTTP/Chrome gate.
Další krok: vysvětlit rozdíl, až poté případná další optimalizace a celý CI.
Druhý pokus na11.4 také výkon FAIL; jeho původní raw/Chrome důkazy doplněné.

Převzat main e3f29903/#419, aktuální produkce dle Actions
deploy42637712723 SUCCESS na e3f29903. Profilové runtime soubory proti
baseline beze změny. Přijaté QR01 event13/14 zachované, vlastní RHM
přeznačené15–18; katalogy sjednocené. Nová CI po vyřešení konfliktu.

## Přesná diagnóza spojení a pokus 4 — pevné pořadí joinů

Ruční diagnostic_only run42638743078 nejprve FAIL kvůli chybě testovacího
bootstrapu (chybějící profilovací třída při loginu), aplikace se neměnila.
Bezpečné source/test/cleanup důkazy v diagnostic-first-failed/ zachované.
Opravený [run42639130649](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42639130649)
PASS oběDB, f0f451e5:17SQL od vytvoření PDO, skutečné readerSHA shodné.
Metadata/autorizace jsou pod1ms; brzda zůstává SQL měření. Na10.3 detail
287.696→259.713ms, na11.4 365.562→953.808ms. Původní4–5ms diagnostika
nebyla reprezentativní pro spojení po běžném bootstrapu. Plné rawdůkazy
v full-bootstrap/, nejde o PASS výkonnostní akceptace.

Pracovní hypotéza: volné pořadí joinů může načíst všechny vlastní vazby před
omezeným klíčovým seznamem. Nový kandidát začíná bounded eligible tréninky,
na MySQL/MariaDB spojuje pomocí STRAIGHT_JOIN t→tm→mz v tomto pořadí.
SQLite používá běžné JOIN; původní souhrn i pozdější stránky beze změny.
[Oficiální řízení pořadí](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/index-hints-how-to-force-query-plans).
Nová diagnostika ukládá EXPLAIN aktuálního i odmítnutého volného selectoru
nad stejnými parametry (parametry neukládá), až po renderu v samostatných
workers. Hypotézu musí potvrdit plán a nové celé párové HTTP/Chrome měření;
20%gate se nesnižuje. Žádný RHM merge/deploy, cizí WIP zachovaný.

Diagnostika pořadí run42639932929 PASS (nejde o performancegate):
10.3detail214→189ms,11.4 352→703ms. EXPLAIN ukazuje ALLscan tm
a na11.4 materializaci všech300k vazeb i uvnitř EXISTS. Samotný join order
neřeší vše. Doplněno SHOW COLUMNS/SHOW INDEX pro3syntetické tabulky,
PDOrežim a rawEXPLAIN (10.3 může opakovat JSONklíče; parsovaná forma
sama není úplný plán). Žádný RHM deploy,20%gate beze změny.

## Příčina potvrzena — zastaralé statistiky syntetické DB

[Run42640885861](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42640885861),
head4d9d7edd, metadata obouDB PASS. Trenink_mereni má správný kompozitní
PRIMARY(trenink_id,mereni_id), stejné INTtypy; u300křádků ovšem10.3 ukazuje
cardinality2/2,11.4 jen1/1. Mereni_zaznamy PK1–2, tréninky2/1755 při30křádcích.
Stará minimální fixture po velkém INSERT neobnovila persistentní statistiky;
EXPLAIN proto volí ALL/BNL i u omezeného seznamu. Data i první výsledky
zachované v stale-statistics/, nebyly změněny na PASS.

Pouze vlastní syntetická příprava nyní provede ANALYZE těchto3tabulek před
oběma variantami, zaznamená společné indexy, počty a stav analýzy. Žádný
index/DDL/přepínač se nepřidává a aplikační request nic takového neprovádí.
Původní i nový kód používají přesně stejná data a nově stejné obnovené
statistiky; žádná změna20%gate. Nová nativní diagnostika/HTTP/Chrome/CI
musí potvrdit přínos. Přihlášené produkční UAT a produkční výkon stále NOT RUN.

Převzat main620cc773/#420 s již dokončeným QR01release, runtimeprofilu
beze změny. Přijatý event15 zachovaný, vlastní RHM přeznačené16–20;
statistiky event21. Neaktuální f0f451e5 a4d9d7edd PR běhy nahrazené
novým head (vybrané běžící byly zrušené kvůli kapacitě); nejsou PASS
výkonnostní akceptace. Hotové původní FAIL a diagnózy zůstávají v repu.

## Ochrana řídkých filtrů a malých profilů

Bounded selector používat pouze při filtrovaném total>800 a stránce<=32.
Při malém/prázdném/vzácném filtru by hledání prvních25vlastních tréninků
mohlo procházet celý klub, zatímco přímý původní selector je vhodnější.
Jejich SQL zůstává původní, včetně pozorování detailů po souhrnu. Nevkládá
se předčasný prázdný return ani další SQL. Vysoké počty/typy stále zrychluje
bounded strategie; velké pozdní stránky zůstávají původní.

Paritní fixture zvýšené tak, aby nový guard skutečně procvičily:101tréninků
s null/zero/date/position/typovými okraji,900vazeb jednoho tréninku a velký
exact trailing-space typ v native/emulated+stringify režimech,32/33stránka,
64bitklíče v profilu nad800vazbami. Nové přesné lokální/nativní a celéHTTP
kontroly musí projít na novém head. Rozsah bez změny; gate20% zachovaná.

Ruční statsdiagnóza42641457242 PASS:10.3detail293.279→7.067ms,
11.4 319.689→20.611ms; EXPLAIN ref/eq_ref místo ALL/BNL, cardinality
32k/75k místo1–2. Jde o diagnózu, nikoli celéHTTPgate. Lokální rozšířená
parita4/307 PASS, očekávanou nativní4/619 musí potvrdit CI. Diagnostický
PHPpeak nově zachycen před EXPLAIN/schema, obsahuje SQLprofiler; prosté
request RAM neměřeno. Bez další změny aplikačního či DB chování.

## Zpřesnění malého měřicího profilu

Kontrola rawsignature ukázala, že SID1012 měl12tréninků, ale0měření;
dřívější small/empty fragmenty byly oba225bytů a shodné. Dosavadní přínos
primary zůstává platný pro dané běhy, small nebylo ověření12měření.
Vlastní příprava nyní vloží12skutečných měření k existujícím12tréninkům
SID1012 (kolo/KOLO/kolo-space), prázdnýSID1020 zůstává0. Před každým
měřením se společné profile_counts explicitně ověří12/0 a uloží do statistiky.
Stejná data pro before/after, bez změny aplikace. Nový kompletní native/
HTTP/Chrome běh na tomto head je rozhodující; předchozí vzorky zachované.

## Finální data — první native10.3 PASS, souhrnný CodeQL check k obnově

[Run42644225032](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42644225032),
head345938af: MariaDB10.3 native4/619 PASS, celé fragmenty/role/checksumy/
cleanup a20%gate PASS.30k primary458.363→324.314ms (−29.2%), exactcase−29.9%,
space−30.2%, page2−29.1%. Last−0.4%, absent+0.1%, skutečný malý12−0.3%,
empty+0.2%. Počty270000/12/0 povinně potvrzené. První úplné důkazy v
first-validated/native-10.3/,11.4/Chrome ještě čekají. SQLite11/1199 PASS,
sourceoutside measurements + původní souhrn byte-přesné.

Všechny3CodeQLanalýzy345938af SUCCESS a API má správnýcommit/ref/config,
error/warning prázdné; souhrnný check přesto NEUTRAL se zprávou chybějícíPY
konfigurace. První metadata zachovaná v codeql-first-*.json. GitHub defaultsetup
neumožnil job/run retry, checkrerequest404. Nový dokumentační head bez změny
runtime obnoví kontrolu; nesmí se vydávat jako20SUCCESS, dokud nejsou skutečně
zelené všechny kontroly aktuálního head. Produkce e3f29903, žádný RHMdeploy.

## Pokus 5 — omezený globální prefix a původní fallback

Závěrečná kontrola složitosti odhalila, že hledání prvních vlastních tréninků
přes EXISTS může u dávno neaktivního, ale velkého profilu projít celý klub.
Před release proto nahradit tento selector pevným prefixem64globálně posledních
tréninků. Připojit vazby v daném pořadí, filtrovat přesný typ/vlastníka a provést
původní LIMIT/OFFSET. Plnou25řádkovou stránku lze přijmout: globální prefix
zaručuje, že žádné vynechané starší měření nemůže předcházet vráceným řádkům.
Pokud stránka není plná, provést původní neomezený selector. Žádné zkrácené
výsledky ani odhadované počty. Malé/vzácné/prázdné filtry a pozdní stránky
zůstávají původní. Typový souhrn je byte-původní. Bez DDL/cache/settings.

Doplnit oracle test starého velkého profilu za128novějšími cizími tréninky
na všech relevantních stránkách. HTTP příprava dostaneSID1013 s900měřeními
v roce1990 mimo současný globální prefix; úplná parita i výkon se ověří také
na tomto9.scénáři. Nová akceptace918časovanýchAJAX/DB,1836celkem +52Chrome,
20%gate pro hlavní scénář a kontrola starého profilu bez podstatné regrese.
Předchozí PASS přísluší předchozímu selectoru; nic z něj se zatím nenasazuje.

Předchozí3ba1b041 dosáhl20CI SUCCESS, uložené superseded-owned-selector/
(nejde o aktuální prefix64). Nebyl sloučen ani nasazen; devátý historický
profil a omezená práce se přidávají před uvolněním. Lokální nováparita5/312
PASS, celá nativní akceptace nového prefixu64 teprve čeká.

## Další pouze KONCEPT

Po zrychlení detailu je dominantní původní numeric-window souhrn typů:
cca446/911ms proti detailu4/7ms v samostatné diagnostice. Další agregační
variantu případně měřit se společnými obnovenými statistikami; první odmítnuté
GROUP_CONCAT pokusy proběhly se zastaralou statistikou a nejsou univerzálním
závěrem pro jiné SQL varianty. Browser souběh historie/Měření zkoumat odděleně;
ResourceTiming sám nedokazuje příčinu čekání. Žádná další implementace/cache/
změna práv tímto konceptem nevzniká. Aktuální technický rozsah je dokončený.

## Uzavření 9. 11. 2026

#418/5cc9cf83, tested 54a2b1c1, všech20CI SUCCESS; celý testovaný/sloučený strom a5měřených runtime Gitblobů shodné. Přijaté #416/#419/#420 zachované.
Deploy42654922651 SUCCESS 2026-11-09T16:31:28Z, testy2417/55578, úplná ověřená záloha389/2, migrace/VS/config/index/smoke PASS, import=false/UAT=false. Veřejné8/8HTTP a normalizovanéDOM shodné.

Celé HTTP měření30k/270k: 590.973→424.092ms (-28.2%,10.3); 1348.186→915.044ms (-32.1%,11.4).
Chrome warm uloženéMěření: celá navigace 2191.60→1959.90ms (-10.6%), fetch→DOM+2frames 1441.80→988.35ms. Default/historie a cold/TTFB samostatně v tabulce výsledků; nepřičítat jim úsporu Měření.

Změna pouze measurement selector: total>800/page<=32, nejprve prefix64globálních posledních tréninků a pak vazby; neplná stránka vždy přejde na původní selector. Přesný typ/vlastník ověřen znovu. Původní souhrn, malé/prázdné/vzácné filtry i pozdní stránky zůstávají původní. Žádné aplikační DDL/indexy/ANALYZE/cache/settings/session/UI/writer změny.
Původní dvě agregace, nevyhovující plány/statistiky i nahrazené kandidáty jsou zachované v původních vzorcích. Jen syntetická příprava obnoví společné statistiky před OBOU variantami;20%gate zachovaná. EXPLAIN+metadata dokládají indexové lookupy.

Nativní5/624 na každéDB, SQLite5/312, místní12/1204 PASS.1836časovaných AJAX a52měřených Chrome navigací (+warmupy/interakce). Úplný návratový objekt/PHPtypy, pořadí, počty, přesné typy, PDOrežimy,900vazeb jednoho tréninku,32/33stránka a velký starý profil, signed64 a čerstvé změny PASS. Celé fragmenty/parentDOM/12staffrolí/bearer/freshrevokace/CSRF/logout/2fallbacky/11checksumů a cleanup PASS. PNG ověřené zvlášť; žádná nová UI změna. Diagnostický PHPpeak zahrnuje SQLprofiler, není RAM prostého requestu ani RSS/DBpaměť.

WIP kis_rosters_admin.php SHA2567a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 a původní checkout/větev/untracked soubory zachované.
Přihlášené produkční UAT, produkční výkon, fyzický mobil, nový zápis poznámky a obnova této konkrétní zálohy NOT RUN. Syntetické časy nejsou SLA/LCP.
Finální důkazy přes codex/athlete-measurement-release-proof-20261109 a samostatný dokumentační PR do main bez druhého deploye. Rozsah technicky dokončen.

[Provozní důkazy](verification/athlete-profile-measurement-release-20261109/RESULTS.md).

## P11-RHMS — souhrn měření (nové zadání 9. 11. 2026)

Stav: ROZPRACOVÁNO. Vlastník: vývoj/Codex. Navazuje na dokončené RHM;
jeho původní výsledky ani zamítnuté pokusy se nepřepisují.
Aktuální pokyn vlastníka povoluje implementaci souhrnu, ověření shody,
párové měření a pouze při PASS commit, merge a chráněné nasazení.
Bez importů, změny rolí, plateb nebo osobních produkčních zápisů.

Baseline: main `0b4592fa63ee49a733b8d4a882c177ac73541e99`, runtime nasazeného
`5cc9cf83193ca547d641e39afb0df41e4f8ee457`; rozdíl pouze dokumentace.
Větev `codex/athlete-measurement-summary-performance-20261109`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Původní checkout/větev a WIP zůstávají zachované; kontrolní SHA256 souboru
`kis_rosters_admin.php`: `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Diagnóza: dominantní souhrn čísluje celý seřazený proud vazeb a znovu
připojuje měření. Zkusit přesné binární počty bez okna a první řadicí klíč
pro každý typ; alternativou je omezený agregační první klíč. Rozhodne
skutečný EXPLAIN a celá HTTP odezva, nikoli izolovaný SQL odhad.
Nepřenášet staré výsledky GROUP_CONCAT se zastaralou statistikou.

Postup a akceptace:
1. Zmrazit reader z aktuální baseline jako nezávislou referenci. Zapsat plán
   a handoff před implementací; stage jen vlastní pojmenované soubory.
2. Změnit pouze souhrn. Zachovat původní výběr stránek, veřejný návratový
   objekt, přesné typy, pořadí filtrů, duplicity a započítání NULL typu.
   Bez aplikačního DDL/ANALYZE, změny SQL/session nastavení nebo cache.
3. Ověřit úplnou shodu na SQLite a MariaDB 10.3/11.4, native/emulated PDO,
   stringify, NULL/zero datum, shodné časy, nullable/negative poradi,
   signed64 klíče, orphan/foreign/unlinked a čerstvé změny. Rozšířit hranice
   prvního výskytu typů a velkého počtu typů.
4. Nejdřív samostatná plná bootstrap diagnóza. Potom před/po na stejném
   migrovaném syntetickém datasetu, obnovené společné statistiky, cache OFF:
   1k/10k/30k tréninků, 9 scénářů, 17 střídavých párů po 4 warmupech.
   Nejméně 20 % celé hlavní HTTP odezvy na obou DB, bez významné regrese
   malého/prázdného/starého profilu; zachovat surové vzorky i neúspěchy.
5. Ověřit celé HTML/JS, role/token/revokaci/CSRF, canonical checksums,
   skutečný Chrome default/uložené Měření, desktop/úzký viewport a konzoli.
6. Zelené aktuální CI, přesný testovaný/sloučený strom a runtime identity;
   potom protected deploy z main s import/UAT false. Ověřená záloha,
   migrace/VS/config/smoke a veřejné čtecí před/po. Žádné druhé nasazení
   kvůli závěrečné dokumentaci. Přihlášené UAT evidovat samostatně.

Důkazy: [průběžný protokol](verification/athlete-profile-measurement-summary-20261109/RESULTS.md).
Další krok: implementovat kandidáta, lokální parita a nativní diagnóza.
Merge/nasazení tohoto řezu: NOT RUN.
