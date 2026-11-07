# R06 nabídka lekcí — parita a párové měření

Stav: implementace, lokální i nativní parita a měření PASS; celkové CI k dokončení.
[Plán](../../ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md),
[předání](../../SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md).

Reference je zmrazený helper z main `22e1b2cb`; dosavadní výběr kandidátů,
řazení vlastní/cizí lekce, SQL filtr/kolace, datum a limit 50 beze změny.
Dávka ověří aktuální existenci vybraných ID a pak znovu aktuální pracovní
oprávnění před návratem. Žádný stav se necachuje mezi voláními. Transakce
používá původní smyčku a všechny write-time kontroly jsou nezměněné.

Místní PHP 8.2.12 / SQLite: **19 testů / 993 assertions PASS**,
[JUnit](targeted-junit.xml). Pokryté různé role a superadmin, vlastní a cizí
lekce, prázdné/50/1000 kandidátů, datum a hledání včetně doslovných `%`/`_`.
Také odebrané role, deaktivace, jiný účet, změny mezi čteními a po kandidátním
SELECTu: smazané plány, odvolání role, deaktivace a nedostupná tabulka.
Původní i nový helper vracejí stejné celé řádky/pořadí nebo stejnou výjimku.
SQL/parametry/řádky transakční větve jsou stejné s původní cestou.

[SQLite párový report](sqlite-comparison.json): 5 zahřátí, 30 střídavých párů
na každém z 12 scénářů, prosté PDO, stejná data a proces. Samostatný zahřátý
QueryProfilePdo měří SQL a řádky. Medián průměr prostředních hodnot, p95 29. z 30;
špička je přírůstek použité paměti PHP, nikoli RSS.
50 nabídek trenéra: **153 → 6 SQL**; superadmin: **254 → 9 SQL**.
HTTP analýza jiného spojeného fixture vykazovala 203 SQL celé cesty;
nejde o totožný kontext ani tvrzení 203 → 6 SQL celé stránky.
Časy a všechny vzorky jsou v reportu, SQLite čísla nejsou produkční latence.

Nativní CI 10.3/11.4: stejný profiler s `--mariadb`, nativní prepared statements,
explicitně potvrzený vlastní localhost server a datadir, náhodné vlastní schéma
odstraněné ve finally. Bez configu, skutečných osob, lekcí nebo transportů.
Nativní výsledky PASS na obou verzích; viz tabulka níže. Writer / trainingDayPlan / potvrzení nezměněné.
Produkční provoz nové etapy zatím žádný; katalog #364 se nasazuje samostatně.

## Nativní párové výsledky

Runtime `b20715ec`, Testy `42370346225`. [Report 10.3](ci-10.3/attendance-lesson-comparison.json),
[report 11.4](ci-11.4/attendance-lesson-comparison.json), [kontrola otisků](verification.json).
720 měřených párů / 1440 volání; 6 otisků zdrojů shodných s Git blobem.
Všech 24 vlastních schémat (12 na DB) odstraněno přes finally.
Nativní prepared statements; žádný config, skutečná data ani produkční zápisy.

| MariaDB / scénář | SQL před → po | Medián ms před → po | p95 ms před → po | PHP KiB před → po |
| --- | --- | --- | --- | --- |
| 10.3 / prázdná DB | 3 → 3 | 0.842 → 0.843 | 0.869 → 0.903 | 59.38 → 59.38 |
| 10.3 / 1 lekce | 6 → 6 | 1.604 → 1.603 | 1.675 → 1.685 | 98.61 → 137.05 |
| 10.3 / 50 / trenér | 153 → 6 | 41.467 → 1.770 | 43.094 → 1.829 | 131.34 → 179.05 |
| 10.3 / 1000 / trenér (limit 50) | 153 → 6 | 41.174 → 1.750 | 46.672 → 2.340 | 131.34 → 180.59 |
| 10.3 / 50 / vedoucí sportu | 153 → 6 | 41.149 → 1.750 | 46.330 → 1.818 | 131.34 → 179.05 |
| 10.3 / 50 / jiný trenér | 153 → 6 | 41.212 → 1.772 | 42.114 → 1.864 | 131.34 → 179.05 |
| 10.3 / 50 / superadmin coach | 254 → 9 | 72.516 → 2.636 | 74.331 → 2.978 | 148.98 → 196.70 |
| 10.3 / 50 / superadmin sports_lead | 254 → 9 | 73.026 → 2.654 | 76.573 → 3.019 | 148.98 → 196.70 |
| 10.3 / doslovné wildcardy | 45 → 6 | 12.038 → 1.686 | 12.927 → 1.863 | 108.22 → 149.01 |
| 10.3 / Unicode + datum | 39 → 6 | 10.500 → 1.714 | 11.011 → 1.804 | 107.25 → 147.68 |
| 10.3 / hledání bez shody | 3 → 3 | 0.848 → 0.848 | 1.024 → 0.979 | 59.38 → 59.38 |
| 10.3 / neplatné datum | 3 → 3 | 0.840 → 0.836 | 0.915 → 0.880 | 59.38 → 59.38 |
| 11.4 / prázdná DB | 3 → 3 | 0.404 → 0.411 | 0.430 → 0.458 | 59.38 → 59.38 |
| 11.4 / 1 lekce | 6 → 6 | 0.790 → 0.800 | 0.830 → 0.869 | 98.61 → 137.05 |
| 11.4 / 50 / trenér | 153 → 6 | 19.989 → 0.973 | 20.617 → 1.043 | 131.34 → 179.05 |
| 11.4 / 1000 / trenér (limit 50) | 153 → 6 | 20.017 → 1.211 | 20.414 → 1.318 | 131.34 → 180.59 |
| 11.4 / 50 / vedoucí sportu | 153 → 6 | 19.783 → 0.955 | 20.615 → 1.035 | 131.34 → 179.05 |
| 11.4 / 50 / jiný trenér | 153 → 6 | 20.000 → 0.976 | 20.463 → 1.035 | 131.34 → 179.05 |
| 11.4 / 50 / superadmin coach | 254 → 9 | 33.520 → 1.424 | 34.898 → 1.543 | 148.98 → 196.70 |
| 11.4 / 50 / superadmin sports_lead | 254 → 9 | 33.407 → 1.399 | 38.478 → 1.628 | 148.98 → 196.70 |
| 11.4 / doslovné wildcardy | 45 → 6 | 5.917 → 0.880 | 6.230 → 0.997 | 108.22 → 149.01 |
| 11.4 / Unicode + datum | 39 → 6 | 5.026 → 0.911 | 5.285 → 0.989 | 107.25 → 147.68 |
| 11.4 / hledání bez shody | 3 → 3 | 0.451 → 0.454 | 0.491 → 0.485 | 59.38 → 59.38 |
| 11.4 / neplatné datum | 3 → 3 | 0.422 → 0.426 | 0.558 → 0.826 | 59.38 → 59.38 |

50 nabídek trenéra: 41,47 → 1,77 ms na 10.3 a 19,99 → 0,97 ms na 11.4;
153 → 6 SQL. Jde o serverový helper, nikoli celé HTTP, browser nebo produkční SLA.
PHP paměť může mírně narůst kvůli malé mapě ID (max. 50); cílem je odstranit
opakovanou DB práci. Vybírání všech 10k osob ani jiné domény tato změna neřeší.
