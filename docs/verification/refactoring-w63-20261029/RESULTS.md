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
