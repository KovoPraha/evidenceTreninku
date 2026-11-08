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
