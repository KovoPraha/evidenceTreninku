# W7.6 / W8.1 — osobní čtení a historická časová osa

Základ `c6a260acef86218bb3e429c912d12746f712b75f`, 30. 10. 2026.
[Plán před implementací](../../REFACTORING_W76_W81_2026-10-30.md).
Čistě syntetická data v náhodné lokální DB, celý migrační katalog a aktivní FK.
Žádná změna schématu, writerů, route guardů či integračních přepínačů.

## Prokázaný problém a změna

[RED](red-budget.txt): původní timeline pro 52 týdnů / 10 týmů provádí
1040 datových dotazů na hierarchii a členství. Nová projekce načte relevantní
historické členství jedné osoby a hrany za rok ve dvou dotazech. Zpětný
průchod pro jednotlivá data zachovává historické potomky, cykly a více cest.
Stavy porovnává nadále SQL se skutečnou kolací/NULL sémantikou. Instance
žije jen během jednoho volání; není cache oprávnění ani podklad writeru.

Původní materializace plánů/skutečností zachována kvůli kompatibilitě aktivních
PDO kurzorů. Výstup, pořadí, RSVP, zrušené plány, historické odstranění,
inkluzivní platnost, přímá účast a uzavřená skutečnost mají přesnou shodu
proti zmrazenému původnímu čtení. Snížená SQLite/MariaDB fixture navíc
obsahuje cyklus a neplatné/NULL stavy, které reálné FK/NOT NULL nedovolují.

## Migrovaný profil

[JSON](timeline-profile.json), MariaDB 10.4.32, 20 týmů. Fixture obsahuje
jednu výchozí rodinnou aktivitu i při nule přidaných týdnů. Skutečný bridge
má oddělené řádky pro plán/skutečnost a jejich CHECK zůstává zapnutý.
Paměť/čas měřeny bez instrumentace, jeden warmup a pět střídavých vzorků.

| Přidané týdny | SQL před → po | Členské SQL | Načtené řádky | Medián ms před → po |
| --- | --- | --- | --- | --- |
| 0 | 16 → 16 | 2 → 2 | 14 → 19 | 18,10 → 15,53 |
| 1 | 96 → 16 | 42 → 2 | 119 → 39 | 38,63 → 15,60 |
| 12 | 976 → 16 | 482 → 2 | 1782 → 459 | 262,88 → 22,02 |
| 52 | 4176 → 16 | 2082 → 2 | 6441 → 1999 | 1207,68 → 40,76 |

Roční špička paměti 1 327 720 → 1 236 432 B. Pro nulový přírůstek je
načteno o pět historických řádků více; nejde o slib úspory v každém malém
scénáři. Brány: nejvýše dva členské dotazy, bez nárůstu celkového SQL,
shodné celé PHP pole/hash, paměť nejvýše původní +64 KiB. Čas je důkaz
lokálního měření, nikoli naměřená produkční latence či tvrdý CI práh.

## R03/R04 rozhodnutí

[Osobní čtení](person-read-assessment.json): 0/100/1000 historických žádostí
má 7 SQL a 6/106/1006 řádků. Cizí účet se nepropíše, revokace a neověřený
e-mail se projeví v příštím volání. Zobrazovanou historii neomezujeme;
pevné čtení pěti verzí podmínek není N+1. `accountPersonCanManage` má jen
definici a testy, žádný produkční callsite, proto nepřepisováno.

Editor R04: 22 SQL / 17 řádků v migrovaném základním scénáři. Zdrojové
dotazy cílí na sportovce, základní karta na ID a limity 5/6/8/80. Úplná
historie editoru/financí a salda jsou předána W10; tento malý profil jejich
škálování nedokazuje. Zbývající W8–W15 zůstávají otevřené.

## Ověření a reprodukce

`EVIDENCE_MARIADB_SMOKE_PORT=33426` pro vlastní lokální server, v CI 3306.
`php tests/Support/AthleteTimelineProfile.php` a
`php tests/Support/PersonReadAssessment.php` automaticky uklidí vlastní DB.
`EVIDENCE_TIMELINE_MARIADB=1 php vendor/bin/phpunit tests/Integration/AthleteTimelineReadOptimizationTest.php`:
[6 testů / 66 assertions](native-contracts.txt). Zahrnuje různé PDO/osoby/roky,
čerstvé změny, chybějící volitelné tabulky a uzavřenou vs evidovanou účast.
CI opakuje oba profily i kontrakty na MariaDB 10.3/11.4.

[PHPStan](phpstan.txt) bez chyb, baseline beze změny, styl PASS.
[Plná lokální sada](phpunit-windows.txt): 1831 testů / 36242 assertions,
3 POSIX skips. Linux 1831 testů / 36291 assertions; oba MariaDB profily PASS:
[10.3](ci-10.3-timeline-profile.json), [11.4](ci-11.4-timeline-profile.json).
Roční medián 1390,42 → 10,58 ms (10.3), 1059,23 → 14,09 ms (11.4),
paměť 1 364 536 → 1 269 960 B. První běh odmítl odsazení YAML; opravený
head `98c12a2bc68a0ac3223452702d1c953cc6c32b00` prošel všemi kontrolami.
PR #237 sloučen a nasazen. [Release](../refactoring-w81-release-20261030/RESULTS.md).
Lidské UAT, reálné finanční převody a aktivace integrací zůstávají oddělené.
