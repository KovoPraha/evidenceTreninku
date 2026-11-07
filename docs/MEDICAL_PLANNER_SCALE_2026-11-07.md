# P11-SCALE — větší zdravotní přehled a plánovač

7. 11. 2026, vlastník vývoj / zadavatel Marek. Stav ROZPRACOVÁNO.
Přímé zadání: měřit větší syntetická data, produkci neměnit, zachovat WIP.
Base `69f490332b0027232d7a58bffb578c28b2715ffa`, větev
`codex/medical-planner-scale-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107`.
Původní WIP kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Postup a akceptace před měřením

1. Nový vlastní localhost MariaDB datadir, port 33479, HTTP 18115.
   Vlastní náhodné schéma a přístup, žádný produkční config/dump/transport.
2. Stejný migrovaný syntetický základ jako předchozí celé HTTP.
   Zdravotní objemy 10 000 / 50 000 / 100 000 sledování, tři záznamy na sledování.
   Stavové filtry Platné a Odvoláno, běžný seznam, osoba, hledání, stránky.
   Plánovač 52 / 260 / 520 týdnů, 14 plánů týdně; vedle historie samostatně
   ověřit 20 / 200 / 1000 členství a širší hierarchii. Průběžně uložit přesné počty.
3. Měřit aktuální zmrazenou aplikaci, nikoli optimalizovat. Celé běžné HTTP
   přes skutečný standardní login, pět zahřátí a 30 vzorků/případ.
   Oddělená SQL/PHP diagnostika, kontrola úplného HTML a kontrolní součty.
   Samostatné čtecí projekce pro počet vrácených řádků a skutečné DB ANALYZE;
   EXPLAIN odhad nevydávat za skutečné rows examined. Časy diagnostiky nemíchat s HTTP.
4. Explicitně ověřit počty, osoby, stav, stránkování a plán/filtry vůči známému seedu;
   READ ONLY pro CLI projekce a web DB účet po loginu omezit na SELECT.
   Příliš drahý případ lze ukončit až s uvedeným limitem a neúplným stavem;
   nesmí zmizet ze závěrečného reportu. Bez zápisového UAT či změny aplikace.
5. Úklid vlastního schématu/účtu/configu/cookies a zastavení vlastních procesů.
   Bez rekurzivního mazání. Výsledky, metoda, omezení a další kroky do verzovaných souborů.
6. Kontrola dokumentace, cílená kontrola měřiče, PR a CI; produkční nasazení NOT RUN.

## Souběh a hranice

Jiný chat `01a1b4e4-bb47-70e0-8d10-c24f6ae5f569` mezitím samostatně
optimalizuje hierarchický filtr a má vlastní pověření k deployi. Tato studie
zmrazí současnou aplikaci před touto změnou; nepřebírá jeho WIP ani nasazení.
Změny main při předání převezme pouze do dokumentace s jasným rozlišením měřeného SHA.
Výkony na nevyhrazené Windows stanici nejsou produkční SLA. Nový browser,
fyzický mobil, síť/CPU omezení a souběžná zátěž nejsou tímto zadáním uzavřené.

Další krok: implementovat pouze měřicí podporu a bezpečně založit izolované prostředí.
Navazuje na [R13](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md),
[R05](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md) a
[celé HTTP](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md).

## Průběh přípravy

Plán zaznamenaný před měřičem v commitu `d2182268`, sdílená vlastní větev.
Nový datadir `C:/xampp/tmp/kis-medical-planner-scale-20261107-ee3c4eee/data`,
MariaDB 10.4.32 / PHP 8.2.12, 350 tabulek / 161 migrací, schema pouze syntetické.
Standardní login všech 12 rolí, potom web DB účet omezený na SELECT.
Zdravotní seeder zachovává stejná tři stavová metadata na sledování, velké objemy
vkládá dávkově mimo časování. Nové soupisky 10000–10049 neodstraňují původní
migrované ukázkové soupisky navázané na nabídky. Přesné počty v každém seedu.
Před časováním základních 18 doménových/DOM kontrol PASS; stejné úplné výsledky
jako zmrazený zdravotní algoritmus a kanonické RSVP. Samostatné projekce READ ONLY.
Předchozí neplatné přípravy (staré ID registráře a FK nabídky) jsou kalibrace,
nejsou časové vzorky. První pomalý per-row seed se před časováním nahradil
stejným dávkovým postupem jako ostatní objemy; jeho protokol zůstal oddělený.


Průběžné sdílení [PR #375](https://github.com/KovoPraha/evidenceTreninku/pull/375),
head měřiče `2feee67e`, draft během sběru. První CI dokumentace a PHP quality PASS;
nejde o finální dokončení ani merge. Základní sada hotová: 18×30 časů, 4 negativa,
18 úplných doménových/DOM parit, samostatné projekce a 22 diagnostických GET.
Všech 21 součtů shodných. Stavové platné/odvolané čtení: 130558 Rows_examined,
30065 serverových výstupních řádků, přesto nejvýše 25 zobrazených sledování.
Slow log navíc eviduje protokolové Quit (0/0 řádků), proto jeho počet položek
není totéž jako počet SQL Query/Execute z general logu. Řádkové čítače se tím nemění.
Sada 50k je založená a její ověření běží; sada 100k a větší plánovač zatím NOT RUN.

## Obnovení100k fáze

Sady10k a50k hotové, všechny konečné časy zachované. První100k časování
přerušilo HTTP500 kvůli chybějícímu lokálnímu TEMP .MAI souboru. Vlastní web
a DB se bezpečně zastavily; všechny21checksumy před/po restartu a obnovení
12standardních loginů shodné. Pouze vlastní my.ini nyní má vlastní tmpdir.
Celá100k sada se opakuje, neúplná fáze je oddělená mimo finální vzorky.
[Kalibrace](verification/medical-planner-scale-20261107/CALIBRATION.md),
[průběžný výsledek](verification/medical-planner-scale-20261107/RESULTS.md).
