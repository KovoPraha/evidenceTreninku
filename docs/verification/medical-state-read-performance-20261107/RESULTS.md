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
