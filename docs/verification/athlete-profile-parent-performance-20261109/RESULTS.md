# P11-RAP — výsledky a meze

9. 11. 2026: plán před implementací. [Plán](../../ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md),
[předání](../../SESSION_HANDOFF_ATHLETE_PARENT_2026-11-09.md).
Base02904936 a produkce95cb33e5 čerstvě ověřené; WIP SHA2567a7afd75… shodný.
Předchozí2,7sTTFB je důvod diagnostiky, nikoli nové měření či příčina.
Diagnostika/parita/implementace/CI/merge/deploy zatím NOT RUN, produkce beze změny.

## Diagnostika beze změny aplikace — PASS

[Run42570580742](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42570580742),
head40183284, oba native joby SUCCESS. [10.3](baseline-diagnosis/native-10.3/integrated-summary.json),
[11.4](baseline-diagnosis/native-11.4/integrated-summary.json), samostatné SQL
[10.3](baseline-diagnosis/native-10.3/diagnostic-before.json)/[11.4](baseline-diagnosis/native-11.4/diagnostic-before.json).
Skutečný původní parent GET,4zahřátí+17vzorků na1k/10k/30k. Oba vlastní cleanupy PASS.
30k medián1226,977ms na10.3 a2978,714ms na11.4. Toto jsou nové baselines,
nikoli páry se starším RAS měřením; datum a prostředí ovlivňují roční výběr.
SQL snapshot sportsActivityMeasurements:94752řádků, execute+fetch649,16 /
1664,73ms. PHP diagnostická špička96629320 /96629352B (celý požadavek,
profilovaný PDO a přednačtený bootstrap; nesměšovat s prostým HTTP časem).
Ostatní nejdražší3SQL jsou roky/počet tréninků/počet měření:10.3zhruba196–220ms,
11.4404–460ms každý. Zvolen největší příkaz a materiálizace nevyužitých detailů.

Volba: nový SportsActivityOverviewRead pro3součty, stejní kanoničtí čtenáři
trainingClosedActivitiesForPerson a sportsEventCurrent. Plný sportsActivitySummary,
jeho export/hash a všechny writery zůstanou byte-identické. Nezavádět volbu,
která by vrátila ochuzený objekt s původním kanonickým contract tagem.
Nová projekce musí zachovat i JSON chyby, aktuální revize, PHP převody a pořadí
součtu; přímá parita proti plnému souhrnu na obou nativních DB i SQLite.
Implementace lokální, párová měření/role/Chrome/merge/deploy ještě NOT RUN.

Lokální nový paritní test2/54 PASS (SQLite), širší cílená sada49/324 PASS.
Příkaz: `php vendor/bin/phpunit --colors=never --filter "SportsActivityOverviewReadTest|SportsActivityTest|SportovecNote|SessionSecurityTest|PublicProfile"`.
Nová funkce používá kanonické current-revision readers a stejný součet/casty/řazení;
legacy sports_activity.php, training_closure.php a AJAX endpoint beze změny.
Po diagnostice zapnutá povinná comparison1 +Chrome na11.4, žádný release
bez >20% přínosu30k celého HTTP a <50% původní diagnostické PHP peak.
