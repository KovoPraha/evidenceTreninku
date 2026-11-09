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

## První přímé páry na61e84220 — průběžný native10.3 PASS

[Run42571649460](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42571649460),
[summary](first-comparison/native-10.3/integrated-summary.json),
[kanonická parita](first-comparison/native-10.3/projection-tests.xml),
[role/DOM/fragmenty/CSRF](first-comparison/native-10.3/security.json).
Čtyři zahřátí a17střídavých párů/objem a case; celý HTTP parent je bez SQL
instrumentace.1k48,014→36,478ms,10k563,319→253,522ms,
30k1981,074→887,535ms (−55,2%). Původní baseline1227ms z jiného CI runneru
není kontrolní skupina tohoto páru a časy se mezi běhy nekombinují.
Celý normalizovaný HTML/JS včetně všech záložek přesný; normalizované pouze
nonce a hodnota CSRF_TOKEN, žádné odstranění obsahových částí.12staff rolí,
pravý bearer303, expiry/revokace/scope, invalid/valid CSRF logout a následné
zamítnutí,12byte-shodných AJAX variant,11doménových checksumů a cleanup PASS.
SQL diagnostika samostatně22→17SQL a94771→15vrácených řádků; PHP peak
96629320→1262688B (celý profilovaný požadavek, nikoli DB serverová paměť).
Malý profil1012 na stejné30kglobální DB796,098→677,206ms; prázdný scoped
profil1020791,975→689,881ms. Nejde o malou celou DB; globální tabulky jsou stále
velké a původní joiny i pro prázdnou osobu mají náklady.
11.4/Chrome a aktuální finální CI ještě čekají. Doplněné CI cesty pro změny
kanonických sports_activity/training_closure zajistí nativní paritu při budoucím
vývoji. Aplikační zdroje se tímto dokumentačním/CI doplněním nemění.

## První přímé páry61e84220 — obaDB/Chrome PASS

[Native11.4](first-comparison/native-11.4/integrated-summary.json),
[kanonická parita2/54](first-comparison/native-11.4/projection-tests.xml),
[celé role/DOM/fragmenty/CSRF](first-comparison/native-11.4/security.json).
Native10.3 i11.4: nový paritní test2/54, včetně úplných původních snapshotů/hashů,
JSON chyb a změny aktuálních revizí PASS. Obě native fixture i veškerý cleanup PASS.
11.4 celý parent1k61,447→49,478ms;10k921,388→437,638ms;
30k3069,856→1353,246ms (−55,9%). SQL22→17 a94771→15řádků,
profilovaná PHP peak96629304→1262728B. Malé/prázdné kontroly jsou v raw samples.
Počet měření i samotné lazy AJAX dotazy zůstávají stejné, mění se pouze jejich
zbytečné načtení při počátečním panelu. Žádná nová agregace SQL či cache práv.

[Chrome souhrn](first-comparison/browser-summary.json),
[raw před](first-comparison/native-11.4/browser-coach-before.json.gz),
[raw po](first-comparison/native-11.4/browser-coach-after.json.gz).
52skutečných navigací:default +savedMěření, každé3cold+10warm/variantu.
Warm default TTFB3060,3→1380,2ms, load3116,9→1435,6ms;
celá navigace do DOM+dvou animačních smyček4130,75→2422,80ms (−41,3%).
Warm savedMěření celá navigace4644,05→2943,85ms (−36,6%), přitom samotná
AJAX část1533,85→1529,70ms podobná. Cold default4151,5→2421,3ms,
saved5507,3→3807,9ms. Neukazovat cold výsledek jako warm ani tvrdit zrychlení AJAX.
Žádné console/transport chyby, filtry/roky/typy/stránky/záložky/390px PASS.
[Desktop](first-comparison/native-11.4/athlete-parent-after-desktop.png) a
[390px pager](first-comparison/native-11.4/athlete-parent-after-mobile.png) vizuálně
zkontrolované; obě velikosti screenshotu před/po jsou byte-identické.
Úzký dlouhý syntetický text na mobilu není nová UX úprava ani fyzický mobilní UAT.

V každém objemu3scoped profily (1001hlavní,1012malá vlastní historie,1020prázdný),
4warmup+17střídavýchbefore/after párů:306časovanýchHTTP/DB,612na obou DB.
Další bezpečnostní/diagnostické požadavky nejsou v těchto počtech.
Povolené4role/konteksty (3staff+bearer) mají navíc přesný parent DOM;12staff
rolí má stejné parent/API statusy.12variantAJAX a3bearer varianty byte-shodné.
Čerstvá expirace/odvolání/scope/inactive303, validní i nevalidní CSRF logout,
11doménových checksumů a vlastní kompletní cleanup PASS. Soukromé configy,
cookies, tokeny, serverlog a browserlog vyloučené z artefaktů.

Finální95305c2a doplňuje pouze CI trigger a důkazy; aplikační soubory identické.
Přesto čeká na své aktuální celé CI a opakovaný native/HTTP/Chrome protokol.
Produkce a původní WIP stále beze změny. Syntetická měření nejsou produkční SLA.

## Finální95305c2a — aktuální celé CI a opakování PASS

[Run42572561068](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42572561068),
[10.3](checked-head/native-10.3/integrated-summary.json)/[11.4](checked-head/native-11.4/integrated-summary.json).
Na přesném headu všech16CI SUCCESS, všechny4sourceSHA256 obou matrix jobů
shodné s Git bloby. Celý tested/merged strom shodný; [release](../athlete-profile-parent-release-20261109/RESULTS.md).

| Přidané tréninky / vlastní měření | Parent HTTP10.3 před→po ms | Parent HTTP11.4 před→po ms |
| --- | --- | --- |
|1000 /9000|48.723→37.038 (−24.0%)|61.135→49.806 (−18.5%)|
|10000 /90000|562.685→256.055 (−54.5%)|910.765→434.322 (−52.3%)|
|30000 /270000|1889.846→863.909 (−54.3%)|3033.538→1355.507 (−55.3%)|

Současné kontrolní profily1012/1020 a všechny stabilní normalizované wholebody
hashy jsou v raw gzip. Jejich DB zůstává globálně velká; neoznačovat za malou DB.
Počáteční měření/diagnóza a první páry ponechané zvlášť; nevybírat si lepší
hodnoty z jiného běhu. Poslední dvojice30k1889,846→863,909ms (−54,3%) a
3033,538→1355,507ms (−55,3%). Špička PHP96629320→1262688 /
96629304→1262728B (−98,7%), SQL22→17 a94771→15vrácených řádků.
SQL instrumentace je oddělená; jde o PHPmanaged peak, ne RSS či paměť DB serveru.

[Finální Chrome souhrn](checked-head/browser-summary.json).
Warm default:TTFB3080,85→1371,60ms, load3139,80→1434,25ms,
celá navigace do připravené historie4126,30→2432,60ms (−41,0%).
Warm savedMěření:TTFB3067,70→1359,95ms, celá navigace5073,95→2944,15ms (−42,0%).
Default AJAX1019,25→1016,30ms beze změny. Měření AJAX1989,00→1555,05ms je
v tomto běhu proměnlivá souběžná komponenta; předchozí běh1533,85→1529,70ms
ukázal neutrální výsledek. Nevyvozovat změnu SQL AJAX nebo jeho vlastní garantovanou úsporu.
Oba DOMy a oba screenshoty před/po byte-identické po normalizaci pouze
bezpečnostních hodnot uHTML; PNG soubory jsou přímo byte-identické.
Vizuální kontrola prvního i stejného finálního desktop/390px PASS.

Native2/54 na obouDB,SQLite2/54, širší místní49/324; exact counts/fullsnapshot/
hash/CSV, freshrevize/JSON chyby a absence measurement dotazu v projekci PASS.
612časovanýchHTTP +52Chrome,12staff rolí,4povolené kontexty včetněbearer,
15byte-shodných fragmentových variant/DB,11doménovýchchecksumů a cleanup PASS.
Přihlášené produkční UAT/produkční výkon/fyzický mobil NOT RUN, syntetická čísla
nejsou produkční SLA/LCP. Úplný souhrn/exporty/writery/rights/session beze změny.
