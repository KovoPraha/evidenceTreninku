# P11-R13-S — parita a měření zdravotního přehledu

7. 11. 2026: FINÁLNÍ PARITA A PŘÍNOS PASS, [PR #381](https://github.com/KovoPraha/evidenceTreninku/pull/381) sloučené.
[Plán](../../MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [provozní stav](../medical-state-release-20261107/RESULTS.md), [předání](../../SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md).

## Finální a1d7a70d / V4

Reference6b1860d5 → `a1d7a70d95123734939046456478d19caa276f35`, main938e9c50 má přesně stejný celý strom.
[Testy42404266532](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42404266532),
[celé HTTP/Chrome42404266401](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42404266401),
[všech12 kontrol](../medical-state-release-20261107/checks-final.json) SUCCESS.
PHPUnit2377/47777, cílená nativní sada7/201 na každé MariaDB10.3/11.4,
lokální cílená18/356, PHPStan5/styl/CodeQL/docs PASS. SQL a bindingy hledání,
data, osoby i transakce přesně původní. Zdravotní statusy včetně explicitních
budoucích řetězců, nekanonických dat a fallback limitů shodné s původní funkcí.

Vlastní Linux CI/PHP8.2, bez produkčního configu nebo zdravotních dokumentů.
Pomocný profil21případů/DB, velikosti100/1k/10k všech6případů a100k valid/pending/person;
4rozehřátí a17střídavých párových časů (celkem714párů), read-only session,
3kontrolní součty stejné. SESSION SQL query cache vypnutá a ověřená.
Smíšených12vzorů historie; nejde o homogenizovaná300k data celého HTTP.
[Nativní10.3](final/native-10.3.json.gz), [nativní11.4](final/native-11.4.json.gz).

Celé HTTP na stejném vlastním fixture v každém páru:10k/100k sledování,
30k/300k záznamů,5budoucích řetězců, všechny stavy přítomné. Standardní login
před časováním; celý přihlášený GET včetně bootstrapu, kontroly session a navigace.
18kladných×30časů×2varianty×2objemy =2160vzorků po5rozehřátích,
12záporných×2×2 =48kontrol. Úplné normalizované HTML/status/ID/pořadí/počty
všech30případů shodné,13doménových checksumů stejné před/po obou variantách.
Oddělená instrumentovaná diagnostika: žádné doménové DML/DDL. Globální SQL
result cache vlastního MariaDB11.4 serveru OFF; časy zahrnují práci DB.
[Souhrn a zdrojové hashe](final/integrated-summary.json),
[surové HTTP, diagnostika a Chrome](final/), [úklid](final/runner-cleanup.json) PASS.
Vlastní schema, účet/bridge účet, config a server uklizené; soukromé výstupy nejsou v artefaktu.

| Celý GET / medián ms | Před | Po |
| --- | ---: | ---: |
| 10k / Platné | 94.53 | 50.37 |
| 100k / Platné | 1062.80 | 519.94 |
| 100k / Čekající | 1047.26 | 473.69 |
| 100k / Platnost skončila | 1054.99 | 442.21 |
| 100k / hledání Platných (původní SQL) | 1074.05 | 1088.80 |
| 100k / bez stavu (původní algoritmus) | 200.11 | 201.30 |

100k Platné −51,1%, Čekající −54,8%. Chrome registrar4případy, viewport1036×703,
3cold+10warm navigací/případ/varianta/objem =208pozorování, bez console/request chyb.
100k warm load medián Platné1128,45→595,55ms, Čekající1129,10→548,20ms;
bez stavu256,85→260,95ms neutrální. Všechny Chrome JSON jsou v final/.

| Pomocný filtr /100k / medián ms | Před | Po |
| --- | ---: | ---: |
| Platné /MariaDB10.3 | 471.00 | 313.91 |
| Čekající /MariaDB10.3 | 462.75 | 376.17 |
| Platné /MariaDB11.4 | 922.91 | 580.86 |
| Čekající /MariaDB11.4 | 920.16 | 746.92 |
| Řádky přenesené do PHP /obě DB | 216691 | 74 |
| SQL pomocného filtru s8budoucími řetězci | 2 | 4 |

Bez budoucích řetězců nejvýše50řádků (25metadata+25plných), ověřeno zvlášť.
74řádků není počet zkoumaných řádků DB ani čítač celého HTTP. Starý stream už měl
omezenou PHP paměť; zrychlení nesmí být prezentované jako její zmenšení.
100k celý GET Platné má SQL20→22 a PHPpeak616168→711080B,
alokovaný peak stále2MiB. Malá sada100 má pevnou režii navíc:
Platné10.3 0,89→1,41ms,11.4 1,70→3,03ms. Hledání/osoba zůstávají neutrální,
včetně stejných SQL a bindingů. Nový mechanismus odstraňuje přenos celé historie
u běžného stavového filtru; přesná agregace v DB nadále roste s objemem.

Syntetické CI časy nejsou produkční SLA ani důkaz aktuálních produkčních objemů.
Skutečné rows examined, souběh, fyzický mobil a přihlášené produkční UAT NOT RUN.
Práva, writer, integrační flags a soukromé soubory se nemění. Žádné nové schéma
ani trvalá cache; příliš mnoho/složitých budoucích řetězců má původní streaming cestu.

## Historie iterací (stav v době jednotlivých měření)

7. 11. 2026, [plán](../../MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [PR #381](https://github.com/KovoPraha/evidenceTreninku/pull/381).

## Prototyp a7b1bee9 — zamítnuto pro nasazení

[Celý HTTP/Chrome42399868489](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399868489) PASS pro paritu,
ale **FAIL pro časový přínos**. Jeden fixture10k sledování/30k záznamů +5 budoucích řetězců,
prosté celé GET30 časů po5 zahřátích. [Souhrn](prototype/integrated-summary.json),
[úklid](prototype/runner-cleanup.json). Celé normalizované HTML a13 checksumů stejné,
žádné DML/DDL v čtecí diagnostice; Chrome bez chyb. Provozní data beze změny.

| Celý GET, medián ms | Před | Prototyp |
| --- | ---: | ---: |
| Platné | 106,05 | 197,30 |
| Čekající | 104,89 | 221,31 |
| Odvolané | 105,69 | 193,74 |
| Platnost skončila | 104,82 | 215,09 |

Prototyp přenáší méně řádků do PHP, ale opakuje agregaci pro sondu, COUNT a stránku.
Samotná úspora přenosu nestačí. Další varianta: cílená sonda jen budoucích verified
s kontrolou novějšího verified; hlavní agregace jednou, COUNT/ROW_NUMBER OVER pro
přesný počet a ořez stránky v jednom dotazu. Před jakýmkoliv merge/deploy znovu
referenční parita a obě nativní DB i celé HTTP/Chrome. Finální varianta zatím NOT RUN.

## V2/64137a5b — odstraněna regrese, Platné neutrální

[HTTP/Chrome42401069703](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42401069703) PASS. [Souhrn](iteration2/integrated-summary.json), úklid a13 checksumů PASS. Platné64,85→64,80ms, Čekající63,65→58,61ms, Odvolané65,09→59,59ms, Expired63,91→58,35ms; hledání Platných66,13→67,32ms. Neporovnávat absolutní časy s jiným runnerem prvního prototypu. V2 výraznou regresi odstranila, ale běžný filtr Platné zatím nemá jasný přínos. Pro běžný seznam bez dalších filtrů následuje přímá agregace records bez vnitřních opakovaných JOIN lidí/sledování, odvozená z odděleného EXPLAIN. Jeho odhady nejsou rows examined. Nová varianta bude znovu změřena.

Nativní V2 kontrakt: šest funkčních testů prošlo; selhal nový test typu PDO hodnoty (`0` není bool`false`, 7/186 s jedním assertion selháním). Opravený test ověřuje skutečně vypnutý režim a pak přísnou shodu původní hodnoty včetně typu po úspěchu i výjimce. Žádný zásah do runtime. Nativní kontrakt/měření musí být znovu zelené.

## V3/c6f7576e — objemový přínos, finální zúžení rozsahu

[HTTP/Chrome42403127385](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42403127385) PASS na10k/100k, úplné HTML a13checksumů stejné, bez doménového DML/DDL a browser chyb. [Souhrn](iteration3/integrated-summary.json), bezpečné surové JSON/gzip a oba nativní profily v iteration3. Celý GET Platné při100k690,82→321,14ms, Čekající680,90→284,76ms; při10k Platné60,56→31,02ms. Běžný seznam bez stavu nemá změnu algoritmu, proměnlivé časy nejsou jeho optimalizace.

Nativní11.4 pomocný filtr Platné100k714,91→442,56ms, Čekající708,68→559,00ms; přenos216691→74 řádků. Hledání10k67,36→73,18ms a malá sada100 Platné1,25→1,96ms. Finální V4 proto pro všechny doplňkové filtry (hledání/datum/osoba) zachová původní SQL i bindingy, ověřené novými12assertions; hlavní stavové zrychlení zůstává. Malá nefiltrovaná sada má pevnou režii navíc, uvést otevřeně.

10.3 v tomto profilu vykazuje extrémně nízké opakované časy nové cesty; stav SQL query cache nebyl explicitně řízený. Tyto poměry nepoužít jako kauzální zrychlení. Finální pomocný profil vypne a ověří SESSION query_cache_type=OFF na obou DB; HTTP runner ověří vypnutou globální cache vlastního11.4 serveru. Buffer pool zůstává rozehřátý stejnou metodou. V4 lokálně18/356 PASS, styl beze změn; finální nativní/HTTP měření následuje. Produkce stále beze změny.
