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
