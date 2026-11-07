# R06 nabídka lekcí — parita a párové měření

Stav: implementace a SQLite parita PASS, nativní CI k ověření.
[Plán](../../ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md),
[předání](../../SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md).

Reference je zmrazený helper z main `22e1b2cb`; dosavadní výběr kandidátů,
řazení vlastní/cizí lekce, SQL filtr/kolace, datum a limit 50 beze změny.
Dávka ověří aktuální existenci vybraných ID a pak znovu aktuální pracovní
oprávnění před návratem. Žádný stav se necachuje mezi voláními. Transakce
používá původní smyčku a všechny write-time kontroly jsou nezměněné.

Místní PHP 8.2.12 / SQLite: **18 testů / 987 assertions PASS**,
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
Nativní výsledky zatím NOT RUN. Writer / trainingDayPlan / potvrzení nezměněné.
Produkční provoz nové etapy zatím žádný; katalog #364 se nasazuje samostatně.
