# W6.3 — postupné vyhodnocení rezervovaných položek

29. 10. 2026, základ `8a44d82cc522f89983ea73e66b9d5aa6f5319262`.
[Plán](../../REFACTORING_W63_2026-10-29.md).

## Změna a kontrakt

`clubProgramCapacityFromRows` přijímá `iterable` a projde jej právě jednou.
PDO čtení předává generátor s explicitním `fetch(PDO::FETCH_ASSOC)` místo
materializace všech rezervovaných řádků přes `fetchAll`. SQL, parametry,
FOR UPDATE, PDO buffering a pravidla výpočtu zůstávají. Čtení se dokončí uvnitř
stejné operace/transakce. Nezavádí se cache, migrace ani nový index.

Paměť stále roste s počtem aktivních osob a unikátních rezervovaných identit;
PDO MySQL nadále bufferuje výsledek. Odstraněna je druhá úplná reprezentace
rezervovaných řádků v PHP. Nejde o konstantní paměť ani omezení SQL výsledku.

## Reprodukce a důkaz před změnou

`EVIDENCE_MARIADB_SMOKE_PORT=33426 php tests/Support/ProgramCapacityMigratedProfile.php`
lokálně; CI používá standardní port 3306. Skript vynucuje localhost, vytvoří
náhodnou `w63_profile_*` DB a v `finally` ji odstraní. Minimální legacy bootstrap
odpovídá checkout smoke, potom aplikuje všech 140 migrací. FK zůstávají zapnuté.
Výhradně syntetické osoby, objednávky a programy; žádné produkční přihlašovací údaje.

[Výchozí profil](before.json) vznikl před změnou aplikace, MariaDB 10.4.32,
PHP 8.2.12. Hustý scénář: 10 000 položek, 1 000 aktivovaných, obsazenost 10 000,
přírůstek špičkové PHP paměti 5 480 168 bajtů. Test jednorázového generátoru před
změnou selhal s TypeError (argument vyžadoval array); po změně prošel.

Referencí je zmrazený reader `9da70c97` před čistou extrakcí W6.2. W6.2 už doložilo
shodu výsledků/SQL/parametrů/řádků. Zde se porovnávají stejné operace a čerstvé
read contexts, 3 warmup + 15 střídajících se vzorků každé varianty.
Časy a špička se měří přes běžné PDO; instrumentovaný průchod je samostatný.
Pět scénářů: prázdný, 10 položek, 10 000 různých osob, 10 000 řádků jedné identity,
1 000 položek se dvěma fyzickými týmy. Skript kontroluje stav i SQL/parametry/řádky
a ukládá EXPLAIN i DDL šestice dotčených tabulek.

## První porovnání

[První výsledek](after.json) běžel současně s PHPStan; neuzavírá latenci.
Přírůstek špičkové paměti: hustý scénář 5 480 168 → 1 831 240 B (-66,6 %),
opakovaná identita 4 537 600 → 515 024 B (-88,6 %), dva týmy
585 560 → 227 992 B (-61,1 %). Shoda všech stavů, SQL, parametrů a řádků PASS.

CI má pro oba scénáře s 10 000 položkami mez přírůstku špičky ≤ 4 MiB a zároveň
≤ 60 % materializované baseline. Finální profil po naplnění každého scénáře
obnovuje statistiky přes ANALYZE TABLE. Výchozí a první souběžné měření tuto
obnovu statistik ještě neměly; jejich surové výsledky jsou zachované.

Výsledky jsou syntetický benchmark, nikoli profil skutečné distribuce dat nebo
produkční zátěžový test. Počet SQL zůstává 8 (jeden tým), resp. 10 (dva týmy).
EXPLAIN uvádí odhady, ne skutečný počet prozkoumaných řádků. Paměťový přínos
je cílem této změny; zrychlení celé aplikace z ní nevyplývá.

## Finální Linux CI profil a ověření

[PR #223](https://github.com/KovoPraha/evidenceTreninku/pull/223), finální head
`3a693d36178aa7d49065ef686c4ba3899fd47914`, sloučen jako
`3a168a68050be1d44648086bdb190df7c556617e`.
[Testy 40913530264](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40913530264),
[PHP quality 40913530006](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40913530006)
a [dokumentace 40913529987](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40913529987) SUCCESS.
Linux: **1 799 testů / 35 956 assertions**, navíc POSIX **3 / 45 bez skipů**.
MariaDB 10.3/11.4 včetně checkout souběhu, charakterizace, SQL rozpočtů a nového
paměťového profilu PASS. PHPStan bez nové baseline, styl, anonymous sweep a JS PASS.
Lokálně 1 799 / 35 907, tři Windows POSIX skipy; cíleně 23 / 118.

Finální profily na běžném PDO, s ANALYZE TABLE před každým scénářem, PHP 8.2.35:
[MariaDB 10.3](ci-mariadb-10.3.json), [MariaDB 11.4](ci-mariadb-11.4.json).
Každý běží v samostatném CI jobu; ve stejném jobu současně neběží jiný test.

| Scénář | Špička přírůstku PHP, obě DB (B) | Čas 10.3 (ms) | Čas 11.4 (ms) |
| --- | ---: | ---: | ---: |
| Prázdný | 94 176 → 94 536 | 4,03 → 4,06 | 4,37 → 4,40 |
| 10 položek | 99 232 → 96 840 | 4,03 → 4,01 | 4,39 → 4,44 |
| 10 000 položek | 5 472 504 → 1 823 576 | 28,94 → 29,83 | 26,31 → 27,01 |
| 10 000 řádků jedné identity | 4 529 936 → 505 344 | 28,52 → 29,57 | 25,41 → 26,13 |
| Dva týmy, 1 000 položek | 577 896 → 220 328 | 5,58 → 5,75 | 11,09 → 11,29 |

Šipka znamená baseline → nová varianta, čísla jsou mediány 15 měření po třech
warmupech. Přijatý kompromis: výrazně nižší PHP paměť výměnou za malou režii
iterace (v hustém scénáři 0,70–0,89 ms, přibližně 3 %). Žádná úspora SQL práce
ani tvrzení o vyšší rychlosti. Obě paměťové meze prošly na obou verzích DB.
Obnovené statistiky a EXPLAIN jsou součástí profilů; není důvod měnit index.

Lokální první PHPStan překročil Composer limit 300 s; opakování s limitem
900 s prošlo bez chyb. Finální Linux PHP quality také prošlo bez výjimek.

Dávkové sdílení kapacit mezi nabídkami zůstává oddělený kandidát vyžadující
profil konkrétního katalogu. Tento řez zavádí doloženou paměťovou úsporu a
nepřidává cache s novými pravidly invalidace. Další plánovaný balík je K0.2/W7:
měření osob, rodin a oprávnění. [Produkční protokol](../refactoring-w63-release-20261029/RESULTS.md).

Klidové lokální opakování se stejnými finálními kontrolami také PASS:
[MariaDB 10.4.32, PHP 8.2.12](after-quiet.json). Původní souběžné měření
zůstává zachované. Windows klidový medián byl u hustého scénáře 134,23 →
156,83 ms (+17 %), u opakovaných identit 114,94 → 151,56 ms (+32 %), u dvou
týmů 61,74 → 70,09 ms. Režie iterace se tedy mezi prostředími liší; Linux
výsledek +3 % nelze zobecnit na Windows. Změna je přijata pro úsporu paměti,
nikoli pro čas. Produkční latence tímto benchmarkem doložena není.
