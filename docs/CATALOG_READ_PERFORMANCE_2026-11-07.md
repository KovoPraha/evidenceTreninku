# P11 — omezené načítání katalogu, 7. 11. 2026

ID P11-CATALOG; vlastník zadání Marek, realizace Codex. Stav HOTOVO: implementace, parita, měření a schválené nasazení #364.
[PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364),
[výsledky a metoda](verification/catalog-read-performance-20261107/RESULTS.md),
[session handoff](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md),
[rozhodnutí D2026-11-07-07](DECISION_LOG.md#d2026-11-07-07).

## Zadání, oprávnění a pracovní kopie

Vlastník zadal sloučit #358 do main a pokračovat katalogem podle
[analýzy](verification/performance-analysis-20261107/RESULTS.md), zachovat WIP,
ověřit shodu, změřit před/po a průběžně aktualizovat plán i handoff v repu.
Bez nasazení, aktivace nebo změn živých dat. Katalogový PR předat k review.

#358 sloučený `37d0ad687211aa912a90e8ad105842847956890d`, head `02ad8016`,
11 kontrol PASS; [cenový plán](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md).
Větev `codex/catalog-read-performance-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-catalog-performance-20261107`.
Původní checkout a WIP zůstávají nedotčené; otisk je v protokolu.
Převzaté main `75374564` zahrnuje produkční předání #362 bez opakování operací.
#363 přidává samostatné HTTP/browser měření
na `feb8fe8c`; zachované výsledky nejsou přeměření nového katalogu.

## Cíl, varianty a zvolený postup

Dříve vznikly obrázky, popisy, JSON a prezentace atributů celého katalogu
před výběrem 12 karet. Nový reader zachová globální lehké údaje pro prodejnost,
řazení skutečnou členskou cenou, kategorie a jejich počty; plné karty sestaví
jen pro vybranou stránku. Hledání čte popisy po 100 produktech v PHP se stejnou
Unicode/podřetězcovou sémantikou. Kategorie se čtou po 500 ID.

SQL LIMIT před prodejností/cenami by měnil výsledek, trvalá cache oprávnění
potřebuje invalidaci. Zvolena projekce pro jednu operaci a následná dávka
karet. První nativní měření prokázalo režii malého katalogu (~2 ms), proto
sonda nejvýše 65 ID aktivních produktů volí do 64 produktů původní cestu.
Sonda nevybírá obsah ani počty. Tento kompromis se měří v obou větvích.
Transakce vždy použije původní úplnou cestu bez sondy. Ostatní volání API
bez seznamu ID a checkout zůstávají kanonické. Žádná migrace ani sdílená cache.

## Etapy a ověřitelné přijetí

| Etapa | Stav a důkaz |
| --- | --- |
| Plán před implementací | `b44e7c53`; původní pracovní ID rozhodnutí 05 a události 13 po převzetí #363 přesunuta na 07/18; přijatá main ID zachovaná |
| Merge cen bez deploye | HOTOVO #358 `37d0ad68`; finální head 11 kontrol PASS |
| Reference a implementace | HOTOVO; zmrazený řadič z přesného `37d0ad68`, pouze obal/normalizace formátu; reader + omezené API, implicitní sort_order=0 zachovaný |
| Striktní shoda výsledků | PASS 31 testů / 2080 assertions; kompletní vybrané karty/typy, ceny, pořadí, menu, počty a filtry |
| Objemové párové měření | PASS nad runtime `623dcaa5`, integrovaným v `0252ba58`; 480 párů na MariaDB 10.3/11.4, všech 9 zdrojových otisků shodných |
| Regrese a předání | PASS cíleně 31/2080 a Linux 2350/45220, kvalita/dokumentace PASS; #364 k review, stav kompletních jobů u PR |
| Deploy / čtecí provozní kontrola | PASS release `c70939d4`, workflow 42372002993. Veřejná pole 5/5 shodná; původní DOM baseline neporovnatelný kvůli nonce, viz protokol. Přihlášené UI NOT RUN |

Shoda zahrnuje anonym/člen/neověřený účet/odvolání, více variant, veřejné i
členské ceny, kategorické potomky, všechny druhy řazení, doslovné `%`/`_`,
Unicode hledání včetně přechodu názvu do popisu, prázdný výsledek a krajní
stránku. Také sklad 0/NULL, importy 157–159, tarify s oběma přepínači,
programovou prodejnost/podmínky/kapacitu, změny mezi čteními a transakční SQL.

Měření: 5 zahřátí a 30 střídavých párů na stejných datech a PDO, samostatná
instrumentace SQL/řádků/EXPLAIN. Ověřit medián/p95, PHP špičku a těžké řádky;
nevydávat čísla izolované operace za celé HTTP nebo produkční latenci.
Syntetická schémata, vlastní ověřený localhost kontejner; bez configu/dumpů.
Nad 64 produkty nejvýše 12 plných produktů; více variant na produkt je přípustných.

## Další krok a hranice

Reporty, otisky a vyhodnocení včetně malé režie jsou v protokolu.
5000 produktů: MariaDB 10.3 202,18 → 87,13 ms, 11.4 340,46 → 198,57 ms;
PHP špička 71,59 → 16,82 MiB. Počet malých SQL roste; obsah a cena zůstávají shodné.
Další krok: review #364; při pozdější integraci ověřit aktuální main a WIP.
Nasazení a HTTP/browser nové verze čekají na samostatné zadání.
Globální lehký průchod a programový kontext zůstávají O(katalog).
Navazující HTTP/browser této nové verze a další priority #363 zůstávají oddělené.

Před předáním převzatý také main `3b37e053` / #361 (mobilní karty přístupů).
Všech 9 měřených katalogových zdrojů je stále shodných s `0252ba58`.
Jeho samostatné provozní zadání se v tomto úkolu nevykonává. Poslední CI
převzetí sleduje PR; katalogová parita a měření zůstávají doložené.

## Pokračování a nové oprávnění vlastníka

Vlastník nyní výslovně zadal vhodné změny nasadit, určitě commitnout a pokračovat
implementací dalších optimalizací. Původní zákaz deploye je historický; pro
ověřený katalog je nyní schválen merge a standardní chráněný deploy bez importu.
Nasazení má smysl podle doložené parity a přínosu. Před ním převzít main #360,
dokončit celý CI včetně zrušené MariaDB 11.4, zachytit anonymní veřejný katalog
před/po a ověřit shodné karty/ceny/menu. Neměnit produkční data ani přepínače.
Aktuální produkční workflow už nasadilo `3b37e053` (42365946802) v jiném úkolu;
neopakovat jeho aktivace ani vizuální role UAT. Nyní nasadit pouze nový katalog.
Další etapa: P11-R06-LESSONS, dávkový výběr nabízených lekcí podle #363.

CI oprava před deployem: integrační MariaDB job měl původní limit 10 minut;
po přidání úplných párových katalogových profilů 11.4 opakovaně skončila
zrušením u této hranice (42366360313). Rozpočet jobu zvýšen na 20 minut,
všechny kontroly zachované. Timeout není vydáván za PASS.

## Skutečný výsledek nově schváleného deploye

#364 sloučený a nasazený `c70939d4`, deploy 42372002993 SUCCESS.
[Produkční protokol včetně meze CSP sondy](verification/catalog-release-20261107/RESULTS.md).
Produkty/pořadí/navigace/počet PASS; úplný DOM před/po karet nelze z původních
nonce hashů zpětně prokázat. Dva opravené průchody po nasazení 5/5 stabilní.
Pokračování je [R06 nabídka lekcí](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md).


## P11-ATTR — objevování parametrů variant (17. 11. 2026)

**NASAZENO / technická přejímka PASS, přihlášené produkční UAT NOT RUN.** Vlastník vývoj/Codex. Pokyn vlastníka pokračovat
v optimalizaci stejným postupem: doložit shodu a přínos, po PASS commit/merge/
chráněné nasazení. Base f8f1c17511a6826411b309d24910f37042dd5bf9,
větev codex/attribute-discovery-performance-20261117, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261114.
Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
ASSET14 dokončené včetně dokladů #487/f8f1c175, runtime 9a0bc834 se nemění
pouhým zahájením této diagnózy.

Nový nález navazuje na původní princip analýzy P11: neudržovat celý drahý
výsledek, pokud stačí agregát. shopAttributeDiscoveredKeys vybírá pouze JSON
sloupec, ale fetchAll drží celé pole a znovu dekóduje každou variantu.
Jediným aplikačním voláním je eshop_attributes_admin.php. Definice, editory,
povolené hodnoty, ceny, viditelnost i zápisy se tímto měnit nemají.

Nejdřív izolovaný syntetický profil variant: původní chování versus postupné
fetch řádků a omezená paměť přesně stejných surových JSON hodnot jen v rámci
jednoho volání. Kandidát nejvýše 128 položek, každá nejvýše 8192 B;
žádná perzistentní cache ani změna PDO nastavení. Porovnat i jedinečné hodnoty,
vyčerpání/evikci, velké dokumenty, null, neplatné UTF-8/JSON, skaláry,
číselné klíče, pole, hloubku, Unicode a změnu dat mezi dvěma voláními.
SQL DISTINCT podle textové kolace není vybráno: mohlo by sloučit významově
odlišné surové hodnoty. SQL a celá množina variant zůstávají stejné.

Implementovat pouze při doloženém přínosu. Pak přesná parita proti zmrazené
funkci a vnějšími funkcemi nezměněné PHP tokeny (jen povinný styl a připnuté
závorky). Zachovat existující integrační testy. MariaDB 10.3/11.4, různé objemy
opakovaných i jedinečných JSON, čas/paměť/SQL a checksumy. Ověřit celé HTTP,
HTML/role/CSRF a skutečný Chrome bez změny UI. Požadovat významný zisk na
opakovaných datech, nezamlčet režii jedinečných dat; při regresi kandidát
nepřijmout. Celé aktuální CI a přesný testovaný/sloučený strom před vydáním.

Záloha/config/migrace/VS/index/HTTP/úklid chráněného deploye, UAT/import false,
neprovádět skutečné produkty, platby, účasti ani změny rolí. Lidské produkční
UAT samostatně. Finální protokol a handoff v main, bez dalšího deploye dokladů.
Další krok: izolovaný profil před rozhodnutím o produkční implementaci.


### ATTR — izolovaný výběr kandidáta

SQLite 3 / PHP 8.2.12, vlastní in-memory data, 7 vzorků na variantu/objem,
1000/10000/30000 řádků, 16 klíčů a opakované i jedinečné hodnoty. Shodný
seznam klíčů ve všech párech. Na 30000 opakovaných řádcích medián funkce
230,04 → 75,15 ms; u jedinečných 253,85 → 271,75 ms (+7,05 %).
Přírůstek PHP peak u fetchAll 46,62 MB, kandidát 20,99 kB opakovaných /
222,14 kB jedinečných. [Surové lokální vzorky](verification/attribute-discovery-20261117/local-probe.json).
Jde o diagnózu SQLite, nikoli produkční nebo MariaDB výsledek.

Kandidát vybraný pro implementaci a nativní přejímku; paměť zůstává pouze
v rámci každého volání, přesné surové řetězce a limit 128×8192 B. PDO buffering
se nemění a výsledná množina klíčů není uměle omezovaná. Měřit skutečné
MariaDB náklady včetně bufferu; místní PHP peak není celá paměť serveru.


### ATTR — místní implementace a přejímka

Source contract potvrzuje jedinou funkční změnu discovery; ostatní tokeny
shodné kromě 40 připnutých guard závorek povinného stylu. Lokálně 25 testů /
124 kontrol, PHPStan/styl/syntaxe PASS. [Protokol](verification/attribute-discovery-20261117/RESULTS.md).
Nativní akceptace zpřesněna: repeated funkce ≥20 % rychlejší, unique režie
nejvýše 20 % +5 ms; celé HTTP repeated30k cíl ≥20 % rychlejší a ostatní
případy bez regrese >20 % +20 ms. Vždy přesná shoda, stejná 2 SQL a checksumy.
Měřit buffer PDO a nezamlčet menší/žádné paměťové zlepšení MariaDB.
Native/Chrome/širší CI zatím NOT RUN; přijetí/vydání až po úplném PASS.


### ATTR — kontrola režie a nevybrané varianty

Předběžná MariaDB10.3 přejímka na 559795b1 PASS: repeated30k funkce
121,85→62,96 ms, celé HTTP přehled129,41→74,86 / detail126,92→74,37 ms.
PHP peak repeated91,81→45,22 MB; unique94,79→48,40 MB. Unique čas funkce
115,96→132,10 ms (+13,92 %) je skutečná režie, pod předem stanovenou
20 % +5ms bránou. Není to tvrzení o rychlejším každém katalogu; získává
se přibližně poloviční PHP peak také na jedinečných datech.

Další lokální sondy nebyly vybrané. Adaptivní omezení cache po128missech
s1024 vynechanými řádky měřilo repeated232,19→72,51 / unique229,46→253,53ms
(+10,49 %); neprokázalo výhodu navíc oproti jednodušší variantě.
fetchColumn místo fetch(PDO::FETCH_NUM) měřil repeated195,51→61,23 /
unique231,05→306,90ms (+32,83 %) a nesplnil toleranci. Obě sondy jsou
SQLite a samostatné časové běhy, nikoli nativní zlepšení hlavního headu.
[Sonda backoff](verification/attribute-discovery-20261117/adaptive-probe.json),
[sonda fetchColumn](verification/attribute-discovery-20261117/column-probe.json).
Produkční kandidát zůstává beze změny, včetně bezpečného čtení celého řádku.
Finální přijetí stále čeká na11.4/Chrome a celé CI; režii zveřejnit v protokolu.


### ATTR — merge a chráněné nasazení

Všech 13 kontrol aktuálního 559795b1 SUCCESS. [PR #488](https://github.com/KovoPraha/evidenceTreninku/pull/488)
sloučen jako 4270d0ccdf97499891a059d70e28df3169444247. Celý testovaný/sloučený
strom 2426a2ec7629b445717eee536061d87ec0d2a49d shodný. Jediný vlastní dispatch
[44131137781](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44131137781)
spuštěn z tohoto main, potvrzení NASADIT, UAT/import false. Nasazení v tomto
checkpointu běží; výsledek se potvrdí z celého workflow, ne ze samotného startu.


### ATTR — dokončení

**P11-ATTR NASAZENO:** [plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-attr--objevování-parametrů-variant-17-11-2026), [výsledky](verification/attribute-discovery-20261117/RESULTS.md). #488/4270d0cc, [deploy 44131137781](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44131137781) SUCCESS, 13 CI. Repeated30k celé HTTP129→75 /234→118ms; PHP peak přibližně poloviční. Unique HTTP má 6–12 % režii. Parita obou DB/8pixelpárů PASS, přihlášené produkční UAT NOT RUN.

Párový běh 44128919479 na 559795b1 SUCCESS na obou MariaDB. Sedm nativních
distribucí včetně historických hranic, 2 SQL/volání a checksumy beze změny;
51 celých HTTP scénářů na DB (39 v matici a čtyři sady po třech), každá fáze
355 nezměněných tabulek. Chrome: 16 případů, 352 měřených a 96 zahřívacích
navigací, osm identických pixelových
párů. Celé HTML/hlavičky/role/CSRF/typy/řazení stejné. CSS a UI bez změny.

Repeated 30k: nativní funkce 121,85→62,96 / 187,40→85,50 ms; celé HTTP přehled
129,41→74,86 / 233,53→117,91 ms, detail 126,92→74,37 / 216,39→107,46 ms.
Nativní přírůstek PHP peak 91,81→45,22 MB repeated a 94,79→48,40 MB unique
včetně bufferu mysqlnd, nikoli paměť celého OS/DB. Unique funkce
115,96→132,10 / 189,94→228,00 ms (cca +14–20 %), celé unique HTTP cca +6–12 %.
Přijata úspora
paměti a opakovaného dekódování s touto zveřejněnou režií; není to rychlejší
každý katalog. Všechny předem stanovené brány prošly beze změny tolerance.
Úplné mediány/vzorky a nevybrané lokální sondy v protokolu. Produkční SLA
nebo produkční výkonové měření se tím neprokazuje.

Testovaný/sloučený strom 2426a2ec7629b445717eee536061d87ec0d2a49d, všech 13 CI SUCCESS.
Chráněný deploy 44131137781: 2491 testů / 57194 kontrol,
záloha 394 tabulek / 2 triggerů. Konfigurace
zachovaná, pending migrace [], původní VS/index shodné; aktivace, HTTP 200
a úklid PASS. UAT/import false, import přeskočený. Nezávislé anonymní GET a CSS před/po
shodné. Skutečné produkty/definice/objednávky/role neměněné tímto ověřením.
Původní fio WIP a jeho hash zachovaný; jiné PR/prototypy nepřebírané.
Doklady samostatným docs PR bez dalšího nasazení; lidské UAT samostatně.
Další výkonový řez až po novém měření konkrétního kandidáta.


## P11-REVIEW — agregace kontroly importovaného katalogu (17. 11. 2026)

**NEVYBRÁNO / původní aplikační kód zachovaný.** Vlastník vývoj/Codex. Aktuální pokyn vlastníka:
další optimalizace podle analýzy, přesná shoda a měření před/po; po úplném
PASS commit/merge/chráněné nasazení. Base832048619c3b5c4977e614a5c746979e03a30815,
větev codex/next-performance-20261117c; vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261114.
Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
P11-ATTR včetně předání #489 dokončen; runtime4270d0cc/44131137781 ověřený.

Nález navazuje na původní analýzu výběru katalogu před drahou prezentací:
shopCatalogReviewDetail pro až500 produktů opakuje čtyři korelované agregace
variant. Nejprve změřit sloučení COUNT/MIN/MAX částky do jedné agregace
omezené na předem vybraných500 ID, ve stejném SQL statementu. Výběr, filtry,
CASE řazení, limit500 a události50 zachovat přesně. MIN měny prozatím ponechat
původním skalárním poddotazem kvůli možným kolacím a reprezentaci shodných měn.
Seznam importů ani writery/review/promote/platby se tímto měnit nemají.

Varianty: ponechat původní dotaz; agregovat celou tabulku (nevybráno kvůli
filtrovaným malým výsledkům); nebo agregovat jen předem omezený výřez.
Preferovaný kandidát drží stále3 SQL na detail a jedno konzistentní čtení
produktů/statistik v jediném statementu, bez cache, transakčních změn nebo DDL.
Nejdřív místní SQLite profil se skutečnými migracemi a indexem odpovídajícím
MariaDB; není to důkaz produkčního přínosu. Nativně použít skutečné migrované
indexy na MariaDB10.3/11.4, malá i velká data a selektivní filtry. Měřit čas,
paměť/SQL a pokud dostupné handler reads, zveřejnit i nulové nebo opačné změny.

Přesná diferenciální shoda proti frozen funkci z baseline: run/produtky/události,
typy polí, pořadí a limity; 0 variant, NULL/0/záporné částky, měny a kolace,
všechny review/offer stavy, neplatné filtry, hledání názvu/klíče/SKU s Unicode
či wildcardy, více importů a hranice500/50. Práva, CSRF a odmítnuté POST beze
změny. Celé ostatní tokeny zdroje (jen povinný styl) stejné, ostatní stránky
byte-shodné. Ověřit celé HTTP/HTML/hlavičky a reálný Chrome desktop/mobil;
UI/CSS se nemění. Velké HTTP profily oddělit od menší vizuální fixture.

Akceptace před výsledkem: významný zisk ≥20 % na velkém nativním i celém HTTP
cíli na obou MariaDB; malé/selektivní případy bez regrese >20 % +5ms funkce /
20ms HTTP. Když kandidát nedává zisk nebo mění výsledky, nevydávat ho.
Celé aktuální CI a přesný testovaný/sloučený strom před chráněným deployem;
záloha/config/migrace/VS/index/HTTP/úklid, UAT/import false. Žádné skutečné
importy, review, publikace, produkty, platby ani změny rolí tímto ověřením.
Přihlášené produkční UAT a výkonový profil samostatně NOT RUN. Výsledky,
plán a handoff sdílet v main; doklady bez dalšího nasazení.
Další krok: místní profil a rozhodnutí o minimálním SQL řezu.


### REVIEW — místní diagnóza a omezení strategie

Původní neomezeně použitá skupinová varianta není přijatá. SQLite se skutečnými
migračními tabulkami a explicitním indexem variant odpovídajícím MariaDB,
9 střídavých párů po dvou zahřívacích. Při1200 produktech×100 variantách:
nefiltrovaný detail426,57→259,20ms (−39,2 %), ale pending filtr124,36→161,00ms
(regrese nad bránou); při500×50 byl nefiltrovaný137,43→139,50ms.
[Úplné lokální vzorky](verification/catalog-review-read-20261117/local-v1.json).
To není nativní produkční výsledek a tolerance se nezvyšuje.

Ještě před implementací zúžená strategie: skupinový výpočet pouze pokud
status/type/search jsou prázdné; původní SQL pro všechny ostatní případy
(včetně neplatných ne-prázdných filtrů). Trim hledání se zachová před volbou
strategie, jak již původně probíhal. Tím se zachovává stará výkonnost selektivních
cest a lze měřit přínos omezené agregace běžného nefiltrovaného přehledu.
Měna zůstává původním skalárním MIN, nic dalšího se nemění. Nový kandidát
vyžaduje nové místní a pak nativní porovnání; zatím žádná produkční změna.


### REVIEW — nevybráno pro vydání této etapy

Konzervativní SQLite varianta s původním SQL pro všechny filtry vrací stejné
výsledky, ale opakovaný velký profil1200×100 ukázal471,29→422,91ms (−10,3 %),
nikoli požadovaných20 %. Filtrované cesty mají stejné SQL; jejich čas v tomto
běhu kolísal, například pending156,97→176,16ms. Nová tolerance se nezavádí.
[Selektivní sonda](verification/catalog-review-read-20261117/local-selective.json),
[první kandidát](verification/catalog-review-read-20261117/candidate-v1.php.txt),
[konzervativní kandidát](verification/catalog-review-read-20261117/candidate-selective.php.txt),
[metoda](verification/catalog-review-read-20261117/local-probe.php.txt).

P11-REVIEW **NEVYBRÁNO**, bez úpravy produkčního kódu, merge či deploye.
MariaDB/HTTP/Chrome tohoto SQL řezu NOT RUN. Případný návrat vyžaduje novou
nativní diagnózu nad skutečnými migrovanými indexy, nikoli předpoklad zisku.
Původní shop_catalog_review.php zůstává byte-shodný s base83204861.
Další výběr navazuje níže jako P11-CATMENU, bez přebírání SQL prototypu.

## P11-CATMENU — strom kategorií veřejného katalogu (17. 11. 2026)

**NASAZENO / technická přejímka PASS, přihlášené produkční UAT NOT RUN.** Vlastník vývoj/Codex; stejné aktuální oprávnění
ověřit další optimalizaci, po shodě a přínosu commit/merge/chráněný deploy.
Base832048619c3b5c4977e614a5c746979e03a30815, větev
codex/catalog-category-menu-performance-20261117, worktree
C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261114.
Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.

Nález přímo v cestě veřejného katalogu: shopStorefrontCategoryMenu pro každý
viditelný uzel volá shopCategoryDescendants. To při každém kroku BFS znovu
projde všechny uzly, potom se sjednocují produkty. Preferovaný kandidát
vytvoří index přímých potomků jednou v rámci volání menu; projde stejnou BFS
se stejným pořadím a striktními typy cest. Žádný persistentní stav nebo změna
SQL, kategorií, vstupních produktů, cen, pravidel prodejnosti či počtů.
Samostatná shopCategoryDescendants ani filtrování/řazení katalogu se nemění.

Nejdřív lokální profil plochého, větveného a hlubokého stromu. Potom přesná
diferenciální parita celé položky menu proti frozen funkci: descendant_paths
včetně pořadí/typů, product_count, menu_label, metadata a pořadí menu.
Ověřit skryté rodiče/potomky, sirotky/cykly, nenavázané a duplicitní produkty,
číselné cesty/klíče, prázdné/neplatné kategorie, Unicode a natural sort.
Index musí rozlišit string/int rodiče stejně jako původní ===, bez opravy
historické sémantiky pod záminkou výkonu. Výstup nesmí mít umělý limit.

Source contract připne jedinou funkční změnu a povinné stylové závorky mimo
menu; ostatní logika a stránky byte/token-shodné. MariaDB10.3/11.4, skutečné
menu i celé veřejné HTTP nad stejnou syntetickou fixture, čerstvost dvou
volání/rollback, statusy/hlavičky/CSRF/DOM a checksumy. Reálný Chrome desktop/
mobil, stejné formuláře/geometrie/styly/pixely. Přijmout ≥20 % časového zisku
na velkých cílech obou DB; malé případy bez regrese >20 % +5ms funkce /
20ms HTTP. Zveřejnit také režii paměti indexu a všechny časové směry.

Při PASS celé CI, přesný testovaný/sloučený strom a chráněný deploy se
zálohou/config/migracemi/VS/indexem/HTTP/úklidem, UAT/import false. Žádné
skutečné produkty, review/importy, objednávky, platby, role ani rozesílky.
Přihlášené produkční UAT a produkční výkonový profil samostatně NOT RUN.
Průběžné výsledky, plán a handoff v repu; finální doklady bez redeploye.
Další krok: izolovaný profil nového indexu proti kanonickému menu.


### CATMENU — výběr kandidáta podle místního profilu

SQLite/PHP8.2.12, 9 střídavých párů po2 zahřívacích, přesná shoda celé položky
menu včetně BFS pořadí/typů, popisků a počtů. Plochých30 kategorií1,79→1,86ms;
plochých500124,66→48,70ms; větvených500468,01→57,94ms; řetěz120
399,88→70,35ms. Přírůstek PHP peak: flat5001,164→1,165MB,
tree5001,288→1,309MB, chain1200,730→0,770MB. [Vzorky](verification/category-menu-20261117/local-probe.json).
Tohle je pouze lokální diagnóza, nikoli MariaDB nebo produkční profil.

Kandidát zvolen pro implementaci a nativní přejímku. Index rozlišuje string/int
rodiče, BFS zachovává pořadí původního foreach a striktní navštívení; obecná
shopCategoryDescendants zůstává původní. Výsledek ani produkty nejsou omezené,
SQL/cache/objednávky a ceny se nemění. Další krok hraniční parita a celé HTTP.

### CATMENU — lokální implementace a nativní přejímka

Index aplikovaný pouze uvnitř menu. [Průběžný protokol a přesná metoda](verification/category-menu-20261117/RESULTS.md).
Číselné cesty mají již v původním sestavení uzlů TypeError (int klíč do explode);
výjimka se zachovává, není to nově podporovaný případ. Ostatní smíšené grafy,
prázdné a chybné vstupy, skryté rodiče/potomci a rollback proti frozen funkci.
Nativní MariaDB/Apache/Chrome běží z vlastní syntetické fixture v CI,
žádný produkční SQL experiment. Merge/nasazení čekají na celé brány.

PR #490, první CI44148895506: HTTP baseline pod booking symlinkem načítala
nový includes kvůli PHP __DIR__. Výsledky HTTP nepoužitelné pro před/po,
zjištěno gain bránou; fixture opravena fyzickou kopií a realpath asercí.
Aplikační algoritmus/tolerance beze změny. Celé CI/profily znovu před mergem.

Druhý běh44149428209: nativní/HTTP10.3 PASS, Chrome zastavil CSP obrázků na
nesprávném portu. Vlastní APP_BASE_URL env pro každý worker; normalizace jen
očekávaného loopback originu při HTML/hlavičkovém srovnání. CSP/payloady/cesty
beze změny, žádné ignorované chyby. Celé ověření znovu.

Třetí běh44150359799 zastavila přesná CSP shoda, protože inline onerror
obsahuje origin i jeho schválený hash. Porovnání ověří raw CSP hash skutečného
handleru, potom přeloží hash stejného handleru s normalizovaným originem;
další CSP beze změny, unit kontroluje i chybějící raw hash. Pět lokálních
testů menu/porovnání,45 kontrol PASS. Algoritmus/brány stejné, celé CI znovu.


### CATMENU — dokončení a další diagnóza

**P11-CATMENU NASAZENO:** [plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catmenu--strom-kategorií-veřejného-katalogu-17-11-2026), [výsledky](verification/category-menu-20261117/RESULTS.md). #490/a3796752, [deploy 44152881039](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44152881039) SUCCESS, 13 CI. Tree1000 celé HTTP 94.0→25.2ms / 76.3→19.2ms; parita/32scénářů naDB/4pixelpáry PASS. Paměťová režie indexu v protokolu. Přihlášené produkční UAT NOT RUN; WIP zachovaný.

Párový běh44151159980 na 8a545bb8 prošel obě MariaDB. 9 nativních profilů/DB,
celé menu/typy/BFS/pořadí/popisky/počty přesně stejné, 3SQL a checksumy stejné.
32 celých HTTP scénářů/DB, 355 tabulek nezměněných v každé fázi i po Chrome.
Chrome při tree1000:8 případů,176 měřených+48 zahřívacích navigací a4 identické
pixelové páry, stejné formuláře/geometrie/styly/JS/CSS. Flat1000 celé HTTP
35.0→23.9ms / 26.8→18.2ms; tree1000 94.0→25.2ms / 76.3→19.2ms. Přírůstek paměti indexu je zveřejněný v úplných
tabulkách; malé případy a všechny časové směry také. Žádná tolerance se neměnila.
První nepoužitelné HTTP měření odhalilo baseline symlink __DIR__ chybu;
fyzická kopie a realpath aserce opravily fixture před finální přejímkou.

Testovaný/sloučený strom 4931882cb8228ab2c56641685e0b78de23c9eb59,13 CI SUCCESS. Chráněný deploy44152881039:
2496 testů/57239 kontrol, záloha 394 tabulek/2 triggerů.
Config zachovaný, migrace current/pending[], VS/index stejné;
aktivace/HTTP200/úklid PASS. UAT/import false, import přeskočený. Živé anonymní
GET katalogu (celé normalizované HTML/hlavičky), redirect administrace a CSS
payload/hash před/po stejné. Produkční výkonový profil a přihlášené UAT NOT RUN.
Původní fio WIP/HEAD/hash zachované, jiné PR nebrané. Doklady samostatným docs
PR bez redeploye.

Další KONCEPT k nové diagnóze: shopCatalogPageSelect volá shopCategoryNodes
a následně menu znovu načte stejné uzly (celkem6 dotazů). Ověřit přínos
jednoho lokálního read contextu na reálném HTTP před změnou veřejné signatury;
nedělat persistentní cache. Zbylé náklady menu_label a sjednocování produktů
v hlubokém stromu také vyžadují vlastní profil. P11-REVIEW zatím nevybráno.


## P11-CATLABEL — skládání popisků kategorií (17. 11. 2026)

**NASAZENO / technická přejímka PASS, přihlášené produkční UAT NOT RUN.** Vlastník vývoj/Codex. Nový pokyn vlastníka:
další optimalizace s největším přínosem, přesná shoda a měření před/po;
po úplném PASS commit, merge a chráněný deploy. Base30386ddb29ceed99190a18ed9e792ff02111b139,
větev codex/catalog-category-label-performance-20261117, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261114.
Původní fio/d1221f35, tracked WIP kis_rosters_admin.php a jeho SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
P11-CATMENU dokončen #490/#491, runtimea3796752/deploy44152881039 ověřený.

Porovnat dva následné kandidáty ze závěru předchozího řezu: (A) jedno lokální
načtení uzlů místo dvou ve shopCatalogPageSelect, (B) skládání menu_label.
shopCategoryMenuLabel při každém rodiči volá array_unshift, tedy znovu přesune
celé dosud složené pole. Navržená varianta přidává na konec a jednou obrátí
pořadí před implode. Stejná posloupnost navštívení, ukončení cyklu/sirotka,
typy, mezery, Unicode, prázdné a neobvyklé názvy. Žádná změna ceny, SQL,
produktů, oprávnění, HTML, cache nebo writerů; žádný limit výsledku.

Nejdřív lokální diferenciální profil celého menu a společného načtení uzlů,
ploché/malé/větvené/hluboké grafy. Podle důkazu zvolit minimální funkční řez.
Frozen oracle z aktuálního base; případná změna jen uvnitř MenuLabel,
všechny ostatní zdrojové tokeny musí zůstat přesně stejné.
Ověřit přímo popisky i celé menu, cykly/sirotky/skryté rodiče, typy klíčů,
chybějící/neplatné vstupy, čerstvost/rollback. MariaDB10.3/11.4 a reálné
celé Apache/PHP HTTP pro anonymního i přihlášeného zákazníka, filtry,
hledání, řazení, prázdné výsledky a neplatný CSRF, checksumy celé fixture.
Skutečný Chrome desktop/mobil, formuláře, styly/geometrie/pixely a CSS.

Akceptace před měřením: hluboké cíle chain250/500 ≥20% zisku v nativním
menu i celém HTTP na obou DB; ostatní tvary nejvýše20%+5ms funkce/+20msHTTP,
bez zhoršení přesné shody. Zveřejnit všechny směry a paměť. Při nesplnění
nezavádět kandidát, nezměkčovat tolerance. Potom celé CI, testovaný/sloučený
strom, chráněný deploy se zálohou/config/migracemi/VS/indexem/HTTP/úklidem,
UAT/importfalse. Produkční profil a přihlášené/lidské UAT NOT RUN.
Doklady a handoff v main přes vlastní PR, bez dalšího deploye dokumentace.
Další krok: místní porovnání obou kandidátů, žádná aplikovaná změna.


### CATLABEL — výběr podle místního profilu

SQLite/PHP8.2.12, část callsite shopCategoryNodes + celé menu, 2 zahřívací
+11 střídavých trojic. Přesná shoda všech celých menu. Flat30:1,49→1,53ms
popisky /1,04ms sdílené uzly; flat1000:68,59→64,72 /54,01ms;
tree1000:69,67→68,59 /47,45ms; chain250:181,02→106,24 /182,22ms;
chain500:1247,80→692,39 /1490,94ms. [Vzorky a prototyp](verification/category-label-20261117/local-probe.json).
Časy sdílených uzlů v hlubokém stromu mají i opačný směr; nejde o nativní
ani produkční profil. Zvoleno skládání popisků pro nejvyšší doložený hluboký
zisk a minimální rozsah bez změny signatury. Sdílené čtení zůstává KONCEPT.

Lokální aplikovaná změna: jen MenuLabel, append a array_reverse na konci,
žádný cache či jiné volání. Nativní/HTTP/Chrome přejímka čeká. Nový aktuální
CI profil navazuje na baseline30386ddb a zamyká celý prefix/suffix mimo
MenuLabel. Starý profil CATMENU měl zamknutý i tento helper; jeho historická
workflow zůstává ručně přehratelná na původním testovaném8a545bb8. Aktuální
PR brána je nahrazena dvěma CATLABEL joby se stejnými kontrolami + novými
chain250/500 cíli; všeobecné CI se nemění. Žádné vynechání bezpečnosti/CSP,
parity či výkonnostních tolerancí. Tento postup musí být součástí review.


### CATLABEL — nativní přejímka, CI a merge

**P11-CATLABEL SLOUČENO / DEPLOY BĚŽÍ:** [plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catlabel--skládání-popisků-kategorií-17-11-2026), [měření](verification/category-label-20261117/RESULTS.md). #492/8347ff50,13 CI PASS; [deploy44189981302](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44189981302) běží. Chain500 HTTP81,1→32,3 /110,7→43,3ms, shoda obou DB/4pixelpáry PASS. WIP zachovaný; aktivace a živé ověření čekají.

Head28496cca, párový běh44188289970 SUCCESS; chain250 HTTP22,52→15,55 /
31,15→21,39ms, chain50081,05→32,30 /110,71→43,28ms. Všech9 nativních
profilů/36celýchHTTP scénářů naDB,355 checksumů,4pixelpáry a source lock PASS.
PHP peak chain500 má +12312B. Testovaný/sloučený strom
ac82cc6b006259281efe2a6b595cfe787e5a10d3 přesně shodný. Chráněný deploy
spuštěný jednou s UAT/importfalse; produkční aktivace/živý GET ještě čekají.

### CATLABEL — další koncept k měření

KONCEPT, zatím bez implementace a bez doloženého přínosu: topCategories
v shopCatalogPageSelect pro každou položku menu prochází všechny produkty
a jejich kategorie. Porovnat přesné počty jedním průchodem (jednou za záznam
produktu/prefix) při mnoha produktech a kategoriích. Zachovat doslovnou
sémantiku path/prefix, duplicity záznamů, řazení/typy/počty a celý výsledek;
nejprve lokální i celé HTTP důkazy. Duplicitní načtení uzlů také zůstává
otevřený koncept k samostatnému profilu, bez persistentní cache.


### CATLABEL — dokončení (17. 11. 2026)

**P11-CATLABEL NASAZENO:** [plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catlabel--skládání-popisků-kategorií-17-11-2026), [výsledky](verification/category-label-20261117/RESULTS.md). #492/8347ff50, [deploy 44189981302](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44189981302) SUCCESS,13 CI. Chain500 celé HTTP 81.1→32.3ms / 110.7→43.3ms; parita/36scénářů naDB/4pixelpáry PASS. Přihlášené produkční UAT NOT RUN; WIP zachovaný.

Párový běh44188289970 na 28496cca SUCCESS na obou MariaDB.
9 nativních profilů/DB, přesný starý popisek + celé menu,3SQL/volání a stejné
checksumy. 36 celých HTTP scénářů/DB,355 tabulek nezměněných v každé fázi i po
Chrome. Chain250 celé HTTP 22.5→15.5ms / 31.2→21.4ms; chain500 81.1→32.3ms / 110.7→43.3ms. Menší/ploché/větvené
případy a paměť i opačné časové směry v úplném protokolu. Brány se nezměkčovaly.
Chrome8 případů,176 měřených+48 zahřívacích navigací,4 identické pixelové páry,
stejné formuláře/geometrie/styly/CSS a žádné síťové/console chyby. Desktop/
mobil snímky také vizuálně ověřené. Produkční profil a přihlášené UAT NOT RUN.

Jediná funkční změna: append/reverse uvnitř MenuLabel. Celý prefix/suffix
modulu byteově stejný; žádný cache, změna SQL/signatury/produktů/cen/writerů.
Sdílené načítání uzlů zůstává koncept. Historická CATMENU lane je zachovaná
pro ruční replay na8a545bb8, aktuální dva CATLABEL joby s baseline30386ddb
přebírají přísnou paritu/HTTP/CSP/Chrome/source lock, všeobecné CI stejné.

Testovaný/sloučený strom ac82cc6b006259281efe2a6b595cfe787e5a10d3,13 CI SUCCESS. Chráněný deploy44189981302:
2500 testů/57277 kontrol, záloha 394 tabulek/2 triggerů.
Config zachovaný, pending migrace[], VS/index stejné, aktivace/HTTP200/úklid
PASS. UAT/importfalse, import přeskočený. Živý anonymní katalog (celé
normalizované HTML/hlavičky), administrace redirect a CSS payload/hash/cache
před/po stejné. Doklady samostatným docs PR bez redeploye; původní fio
WIP/HEAD/hash zachované a jiné PR nepřebírané.

Další krok je [koncept souhrnu topCategories](#catlabel--další-koncept-k-měření),
případně samostatný profil sdíleného čtení uzlů. Nejdřív důkazy, bez tvrzení
přínosu před měřením a bez persistentní cache.
