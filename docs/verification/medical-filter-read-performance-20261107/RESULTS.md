# P11-R13-F — parita a měření hledání a data

8. 11. 2026, Europe/Prague: první varianta parita PASS, smíšené datumové případy výkonově nevyhověly.
[Plán](../../MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [handoff](../../SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md), [PR #385](https://github.com/KovoPraha/evidenceTreninku/pull/385).

## cc2a825e — široké hledání PASS, datum je třeba doladit

Všech15CI SUCCESS, [nativní i celé HTTP42417479628](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42417479628).
Parita s35fdad44, query cache OFF, kontrolní součty a úklid PASS.
[Celý HTTP souhrn](prototype/integrated-summary.json), bezpečné surové native/XML/HTTP/Chrome a indexy v prototype/.
Při100k celý GET široké hledání907,81→462,51ms, datum od684,90→544,60ms,
datum do680,08→519,33ms, kombinace457,88→403,79ms. Prázdné hledání bez stavu318,40→178,00ms.

Ve smíšeném nativním100k fixture však denní filtr Platné:10.3 184,40→199,07ms,
11.4 145,73→166,45ms; filtr datum-do Čekající:10.3 491,30→582,71ms,
11.4 395,77→465,13ms. Časy mezi různými runnery nesrovnávat; rozhoduje každý vlastní pár.
Varianta se ještě nenasazuje. Scoped agregace opakuje rozsah sledování/historie;
další varianta pro datum hledá aktuální verified/poslední záznam korelovaným
indexovým dotazem podle watch_id a id, bez agregace mimo vybraná sledování.
Široké textové hledání a bounded kandidáti zůstanou jako v této ověřené variantě.
Všechny případy se přeměří; skutečné rows examined nejsou měřené z EXPLAIN odhadů.

Nová lokální sada7/454 a společná25/801 PASS. Dosavadní produkce938e9c50 nezměněná,
původní WIP SHA256 shodný. Nový merge/deploy NOT RUN.

První varianta měla nativně7testů/466assertions PASS na každé DB. V2 mění pouze širokou datumovou stavovou projekci na korelovaný head lookup; aktuální měření čeká. Úplná původní historie a datum next_review_on se nesmějí zaměnit za ořez záznamů podle platnosti/vystavení.

## V2/1c6ef34f — korelované datumové lookupy zamítnuté

Nativní11.4 parita/cache-off/checksumy PASS, ale100k date_day184,02→240,49ms,
date_from529,84→577,45ms, date_to502,30→748,19ms, kombinace389,47→457,43ms.
[Bezpečný profil](iteration2/11.4-native.json.gz). Tato cesta se nenasazuje.
CI/migrovaný model má watch_id index a PK id, takže samotná existence indexu
nestačí; režie korelovaných lookupů zde převážila. Žádné produkční indexy ani DDL tím nejsou ověřené/provedené.

V3 pro široké datumové filtry vytvoří hlavy jednou: původní kandidátní FROM/WHERE
včetně textu/data, JOIN historie, GROUP BY id a MAX(next_review_on), potom
připojí pouze vypočtené head záznamy. Žádný druhý JOIN lidí/sledování nebo druhé
vyhodnocení téhož filtru mimo agregaci. Čistá stavová funkce/řetězce/limity stejné.
V3 se musí nově změřit na obou DB i celém HTTP/Chrome; dosavadní produkce beze změny.

V3 navíc ponechá v počítaném/řazeném okně jen watch/head ID; původní metadata doplní po ořezu stránky v témže SQL. Složité stavy a full watch hydratace zůstávají kanonické, žádná celá historie běžných vybraných sledování se nenačítá do PHP. Lokálně18/751 PASS. Nový přínos se teprve měří.

## V3/a88cabc8 — většina režie odstraněná, datum_day ještě mírně regresní

Nativní parita/cache-off/checksumy PASS.100k11.4 date_day253,72→267,98ms,
date_from720,35→669,06ms, date_to691,34→694,10ms;10.3 date_day243,51→244,32ms,
date_from662,88→545,55ms, date_to662,99→655,51ms. Široké hledání11.4 1000,15→633,12ms.
Bezpečné profily/XML v iteration3. Neporovnávat časy s jinými runnery.
V3 ještě nejde do produkce. Zastavení případných nedokončených CI zamítnutých variant
není výsledek PASS pro jejich nedokončené HTTP; dokončené nativní důkazy zůstávají.

V4: datumové platnostní stavy vyberou latest verified pomocí anti-join na novější
verified, takže nepotřebují seskupit/watch head znovu dohledávat. U neplatnostních
stavů zůstane jeden kandidátní průchod, ale HAVING vyřadí skupiny s verified,
které nemohou požadovaný stav vytvořit. Výjimky budoucích explicitních řetězců
se zachovají podle kanonicky vypočtených matching ID. Tenké okno a stránka stejné.
Doplní se datumové rozbité/cizí/cyklické/revokované předchůdce a pending široké/poloviční hledání
v nativním i HTTP měření. Znovu ověření před jakýmkoli merge/deploy; bez DDL.

V4 lokální úplná cílená sada26/813 PASS, PSR-12/lint PASS. Datumové legacy výjimky mají12novýchpřesných porovnání. Benchmark rozšířený na16případů/4objemy/DB a17kladnýchHTTP/7Chrome scénářů; výkon V4 zatím NOT RUN.

## V4/feb2e522 — SQL-only datum/poloviční pending stále regresní

Nativní parita a checksumy PASS,8/478 na každé DB.100k11.4 datum_day251,28→307,06ms,
datum_from716,32→766,70ms, poloviční pending537,79→621,95ms.10.3 datum_day239,26→272,58ms,
poloviční pending498,14→551,88ms. Bezpečné native/XML v iteration4; k nasazení nevhodné.
EXPLAIN ukazuje owner/id index pro mr/mv/mn, který nekryje state a další metadata;
mv/mn mají další čtení řádku pro stav a platnost. Odhady nejsou skutečné rows examined.

## V5 plán — index a kód měřit odděleně i spolu

Původní předpoklad bez DDL v D15 je po měření nahrazen experimentem s neunikátním
krycím indexem (watch_id,id,state,issued_on,valid_until,replaces_id). Zachová pořadí
owner/id a nezavádí zdravotní datový přechod. Nejprve pouze vlastní CI.
Nativní srovnání tří variant: baseline/staré indexy, baseline/krycí index,
nový reader/krycí index; dvě stejně naplněná vlastní schémata a stejné3checksumy,
query cache OFF,4zahřátí a17střídavých trojic. HTTP na stejných datech před/po
přidání indexu mezi variantami, nikoli během GET. Celé HTML a doménové součty stejné.
Pokud vyhraje, nasazení před migrací přečte skutečné produkční indexy (bez dat),
po záloze aplikuje idempotentní online ADD INDEX a před aktivací ověří výsledek.
Žádné nové klinické hodnoty, role nebo přílohy. Nový přínos zatím NOT RUN.

## V5 / 7c083f0a — nativní přínos, HTTP a aktuální CI ještě běží

Bezpečné profily a XML v `iteration5/`, run42458893492. Obě DB: přesná parita všech64případů, párové3checksumy stejné před/po a mezi schématy, query cache OFF. Dvě data-shodná schémata a512MB buffer pool,4zahřátí a17rotujících trojic na případ. Třetí sloupec odděluje samotný index od nového kódu. Časy mezi různými runnery neporovnávat.

| 100k sledování / medián ms | DB | Původní | Pouze index | Kód + index |
| --- | --- | ---: | ---: | ---: |
| broad_search | 10.3 | 679.39 | 596.46 | 394.86 |
| half_search | 10.3 | 386.44 | 343.65 | 311.51 |
| broad_pending | 10.3 | 680.33 | 595.68 | 523.83 |
| half_pending | 10.3 | 375.37 | 335.38 | 354.70 |
| rare_search | 10.3 | 115.26 | 121.22 | 118.00 |
| empty_search | 10.3 | 89.34 | 94.01 | 92.57 |
| date_day | 10.3 | 196.92 | 155.60 | 152.30 |
| date_from | 10.3 | 512.76 | 426.88 | 324.42 |
| date_to | 10.3 | 503.38 | 434.95 | 359.55 |
| date_empty | 10.3 | 12.36 | 12.35 | 12.33 |
| combined | 10.3 | 392.26 | 320.20 | 290.87 |
| plain_search | 10.3 | 114.54 | 115.23 | 114.73 |
| plain_date | 10.3 | 74.98 | 74.05 | 74.61 |
| plain_rare | 10.3 | 273.18 | 274.51 | 113.72 |
| plain_empty | 10.3 | 222.27 | 227.35 | 88.00 |
| control_state | 10.3 | 436.37 | 381.11 | 379.02 |
| broad_search | 11.4 | 984.81 | 853.37 | 517.80 |
| half_search | 11.4 | 534.98 | 472.77 | 434.74 |
| broad_pending | 11.4 | 984.04 | 851.89 | 660.71 |
| half_pending | 11.4 | 535.79 | 473.14 | 496.56 |
| rare_search | 11.4 | 211.96 | 211.79 | 212.14 |
| empty_search | 11.4 | 161.81 | 161.28 | 159.78 |
| date_day | 11.4 | 249.36 | 208.53 | 196.76 |
| date_from | 11.4 | 715.08 | 595.11 | 469.25 |
| date_to | 11.4 | 674.43 | 571.05 | 454.11 |
| date_empty | 11.4 | 14.84 | 14.86 | 14.81 |
| combined | 11.4 | 521.14 | 439.85 | 399.22 |
| plain_search | 11.4 | 157.30 | 157.44 | 159.62 |
| plain_date | 11.4 | 82.94 | 83.31 | 83.70 |
| plain_rare | 11.4 | 436.38 | 438.22 | 211.88 |
| plain_empty | 11.4 | 339.21 | 339.37 | 160.44 |
| control_state | 11.4 | 584.11 | 462.79 | 461.36 |

Na10k široké/poloviční stavové hledání i všechny neprázdné datumové stavové tvary zlepšené na obou DB. Malé100/1k sady mají kvůli kandidátní sondě režii: typicky0,3–0,9ms, u1k polovičního pending do2,2ms. Na100k původní široké seznamy bez stavu neutrální (+0–2,3ms), vzácné/prázdné se stavem neutrální (na10.3 do+3,2ms), bez stavu přínos51–60%. Index-only je u polovičního pending mírně rychlejší než nová projekce; kombinovaná cesta přesto zlepšuje původní čas5–7%, široký pending23–33%. Žádný příslib stejného zrychlení skutečné produkční DB. Index přidává údržbu při zápisu a místo; klinické hodnoty beze změny.

EXPLAIN10.3 pro mr/mf/mn a datumové mv používá nový krycí index s `Using index`; jde o plán/odhady, nikoli skutečné rows examined. Úplná aktuální PHPUnit sada na727e1d53 **2389 testů /48273 assertions PASS**, PHPStan/styl PASS; původní dvě testovací chyby ze7c opravené. HTTP/Chrome a ostatní současné CI ještě běží, merge/deploy NOT RUN.

### V5 celé HTTP a Chrome PASS — run42458893492

2040 časovaných kladných HTTP vzorků (17případů×30×2objemy×2varianty),48záporných GET; všechna normalizovaná HTML/statusy přesně shodná. Třináct doménových tabulek/checksumů stejných před/po, GET bez doménového DML/DDL. Indexový přechod proběhl mezi fázemi při vypnutém PHP serveru. Chrome156:7scénářů×(3cold+10warm)×2objemy×2varianty=364pozorování; skutečné navigace, práva/status a DOM ověřené. Úklid vlastní DB, serveru, bridge účtu i obnova obou PHP souborů PASS. Bezpečné výsledky v `iteration5/integrated/`; zdrojové SHA256 veškerých měřených aplikačních/migračních souborů odpovídají fa902ba6.

| Celý HTTP GET, medián ms | Objem | Před | Po |
| --- | ---: | ---: | ---: |
| medical-registrar-all | 10000-watches | 27.09 | 27.30 |
| medical-registrar-person | 10000-watches | 14.19 | 15.22 |
| medical-filter-broad-search | 10000-watches | 105.90 | 56.79 |
| medical-filter-broad-pending | 10000-watches | 107.32 | 51.16 |
| medical-filter-half-pending | 10000-watches | 62.37 | 35.70 |
| medical-filter-half-search | 10000-watches | 61.46 | 44.25 |
| medical-filter-rare-search | 10000-watches | 29.08 | 28.61 |
| medical-filter-empty-search | 10000-watches | 29.18 | 29.00 |
| medical-filter-date-day | 10000-watches | 37.80 | 30.93 |
| medical-filter-date-from | 10000-watches | 81.80 | 54.13 |
| medical-filter-date-to | 10000-watches | 80.51 | 41.06 |
| medical-filter-date-empty | 10000-watches | 15.37 | 15.27 |
| medical-filter-combined | 10000-watches | 63.56 | 44.47 |
| medical-filter-plain-search | 10000-watches | 28.99 | 29.66 |
| medical-filter-plain-date | 10000-watches | 24.09 | 24.15 |
| medical-filter-plain-empty | 10000-watches | 46.11 | 29.15 |
| medical-filter-control-state | 10000-watches | 55.96 | 56.08 |
| medical-registrar-all | 100000-watches | 213.89 | 214.32 |
| medical-registrar-person | 100000-watches | 83.95 | 83.78 |
| medical-filter-broad-search | 100000-watches | 1182.86 | 572.95 |
| medical-filter-broad-pending | 100000-watches | 1179.53 | 436.50 |
| medical-filter-half-pending | 100000-watches | 587.51 | 273.63 |
| medical-filter-half-search | 100000-watches | 588.06 | 388.91 |
| medical-filter-rare-search | 100000-watches | 231.84 | 229.21 |
| medical-filter-empty-search | 100000-watches | 235.24 | 232.70 |
| medical-filter-date-day | 100000-watches | 335.27 | 237.68 |
| medical-filter-date-from | 100000-watches | 882.55 | 508.70 |
| medical-filter-date-to | 100000-watches | 882.43 | 325.78 |
| medical-filter-date-empty | 100000-watches | 96.65 | 96.36 |
| medical-filter-combined | 100000-watches | 599.25 | 391.72 |
| medical-filter-plain-search | 100000-watches | 233.75 | 235.13 |
| medical-filter-plain-date | 100000-watches | 172.70 | 174.43 |
| medical-filter-plain-empty | 100000-watches | 412.63 | 232.68 |
| medical-filter-control-state | 100000-watches | 574.73 | 574.40 |

| Chrome100k, warm medián DCL ms | Před | Po |
| --- | ---: | ---: |
| medical-filter-broad-search | 1265.35 | 632.00 |
| medical-filter-half-pending | 646.00 | 331.00 |
| medical-filter-rare-search | 284.45 | 283.85 |
| medical-filter-empty-search | 289.85 | 285.20 |
| medical-filter-date-day | 393.35 | 295.10 |
| medical-filter-combined | 655.00 | 452.95 |
| medical-filter-plain-empty | 474.50 | 290.25 |

Interpretace: hlavní hledací/datumové tvary na10k i100k zrychlené; kontrolní přímá osoba, celkový seznam, stav bez q/data a široké seznamy bez stavu neutrální v řádu0–2ms. Jde o vlastní syntetický Linux fixture, nikoli měření pacientských dat nebo záruku produkční latence. HTTP podporuje přijetí V5, zbývá zelené CI nad větví po převzetí dokumentačního #387 a standardní release.

Společný historický SyntheticReadPages benchmark nafa902ba6 správně odmítl odlišný počet migrací (předchůdci bez nové indexové migrace). V63682e84 do všech tří izolovaných historických worktrees přidána stejná migrace/helper a skutečná aplikace jako naHEAD, aplikační kód předchůdců zmrazený. Kontroly ledgeru ani parity neoslabené; tento společný historický benchmark porovnává kód na shodných indexech, příspěvek indexu řeší samostatné V5 trojice. Nové CI před release.

## Finální opakování na551b46d3 — všech15CI PASS

Run42460613528 má obě nativní sady i celé HTTP/Chrome SUCCESS, Testy42460613422 a stavové HTTP42460613526 SUCCESS. Bezpečné výsledky v `final/`; zdroje5souborů nativně a všech aplikačních/migračních souborů HTTP přesně odpovídají HEAD. Znovu stejné výsledky/checksumy, nulové doménové DML/DDL a úklid PASS. Nativní100k10.3 hledání676,31→383,19ms, datum od505,32→316,56ms, do490,30→345,73ms;11.4 hledání533,72→289,70ms, datum od388,41→263,56ms, do379,74→257,83ms. Všechny hlavní tvary opakovaně zlepšené; malé/prázdné/stavové vzácné případy mají výše uvedenou malou režii/varianci, na10.3 do4,2ms.

| Finální100k celý GET, medián ms | Před | Po |
| --- | ---: | ---: |
| medical-registrar-all | 217.30 | 216.94 |
| medical-registrar-person | 83.41 | 83.07 |
| medical-filter-broad-search | 1213.91 | 586.46 |
| medical-filter-broad-pending | 1203.92 | 445.78 |
| medical-filter-half-pending | 602.03 | 279.47 |
| medical-filter-half-search | 611.38 | 394.40 |
| medical-filter-rare-search | 230.29 | 227.80 |
| medical-filter-empty-search | 234.19 | 231.54 |
| medical-filter-date-day | 349.44 | 244.22 |
| medical-filter-date-from | 919.93 | 518.62 |
| medical-filter-date-to | 914.99 | 344.27 |
| medical-filter-date-empty | 97.06 | 97.48 |
| medical-filter-combined | 610.01 | 397.07 |
| medical-filter-plain-search | 238.69 | 241.12 |
| medical-filter-plain-date | 174.51 | 176.82 |
| medical-filter-plain-empty | 414.98 | 231.82 |
| medical-filter-control-state | 579.75 | 599.43 |

Před merge nově přijatý #388 obsahuje pouze dokumentaci o rolích a blokovaném UAT. Převzatý bez ztráty, původní stavová tvrzení opravena podle jeho důkazů; veškerý kód/testy/workflow jsou stejné jako plně zelený551b46d3. Další CI dokumentačního merge před release.
