# P11-R13-S — parita a měření zdravotního přehledu

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
