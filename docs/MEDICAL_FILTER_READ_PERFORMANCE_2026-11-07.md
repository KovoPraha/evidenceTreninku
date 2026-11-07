# P11-R13-F — hledání a datumové filtry zdravotního přehledu

7. 11. 2026, Europe/Prague. ROZPRACOVÁNO, plán před implementací.
Vlastník vývoj podle nového výslovného zadání Marka. Po přesné shodě, přínosu
a zelených kontrolách commit/merge/standardní deploy; zachovat WIP.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md),
[výsledky](verification/medical-filter-read-performance-20261107/RESULTS.md), [D15](DECISION_LOG.md#d2026-11-07-15).

## Výchozí stav a izolace

Baseline35fdad4483ec4326e5da9b6ffbb908f77a4df85f, nasazený runtime938e9c50.
[Uzavřený stavový řez #381](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md)
nechal hledání/datum původní cestou: scoped varianta opakovala hledání a JOIN lidí
uvnitř agregace a mírně regredovala. Tyto předchozí výsledky zůstávají historií.
Nová větev `codex/medical-filter-read-performance-20261107`, vlastní dřívější čistý
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Jeho staré bezpečné untracked výstupy zachovat. Původní checkout/WIP nedotknout;
`kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` znovu shodný.

## Navržený postup a varianty

1. Zmrazit kompletní aktuální list i stavovou projekci jako nezávislou referenci.
2. Pro platné hledání/datum bez přímé osoby a transakce nejprve omezená sonda
   nejvýše257ID kandidátů. Prázdný výsledek vrátit bez opakovaného hledání.
   Nejvýše256kandidátů vyhodnotit původní cestou s omezením na nalezená ID;
   původní predikát opět ověřit nad touto malou sadou, pořadí/počty přesně stejné.
3. Široký stavový výsledek: sdílená současná agregace historie bez opakovaných
   JOIN lidí/hledání uvnitř. Datum omezí i agregovaná sledování, bez opakování
   textového hledání. Budoucí explicitní náhrady, limity a stavové vyhodnocení
   kanonické. Široký seznam bez stavu ponechat původní stránkovací cestou;
   přijmout ji jen při doložené neutrální režii. Bez nové cache, rekurze nebo DDL.
4. Porovnat přesná pole/typy/pořadí/počty/stránky/filtry s baseline: všechny stavy,
   q prázdné/široké/vzácné/žádné, více slov, diakritika a doslovné !%_, data od/do
   samostatně i spolu, inkluzivní hranice, invalidní rozsah, kombinace a přehnaná
   stránka. Osoba/transakce původní SQL; legacy řetězce a fallbacky zachované.
5. Nativní MariaDB10.3/11.4 na vlastním syntetickém schématu, query cache OFF,
   stejné seedy, střídavé párové časy/helper SQL/fetched rows a checksumy.
   Celé HTTP/Chrome na stejném migrovaném10k/100k fixture před/po, úplné HTML,
   samostatná diagnostika a nulové doménové DML/DDL. Zapsat reálné indexy CI/EXPLAIN;
   bez tvrzení o produkčních indexech nebo skutečných rows examined z odhadů.
6. Archivovat bezpečné výsledky a průběh v repu; po doloženém přínosu a zelených
   kontrolách merge a standardní deploy přesného testovaného stromu, import/UAT false.
   Čtecí produkční kontrola před/po, výsledkové předání do main. WIP hash znovu.

Globální agregace bez sondy zamítnutá pro vzácné dotazy; opakovaná scoped agregace
s JOIN lidí má předchozí doloženou režii. Nová sonda nesmí stát plné opakované
hledání u prázdných výsledků. Výchozí stavový seznam bez doplňkových filtrů neměnit.

## Akceptace a meze

Implementace, testy, měření, PR/merge/deploy zatím NOT RUN. Implementovat až po
uložení tohoto plánu. Přínos ověřit zvlášť pro široké/vzácné/prázdné dotazy a datum;
při regresi upravit variantu nebo zachovat konkrétní původní cestu a důvod zapsat.
Práva, writer, soukromé dokumenty, zdravotní rozhodnutí a integrační flags neměnit.
DDL není součástí návrhu; před případnou další změnou indexů ověřit skutečný
produkční inventář. Přihlášené produkční UAT/mobil/souběh a ostatní K0.2 samostatné.
