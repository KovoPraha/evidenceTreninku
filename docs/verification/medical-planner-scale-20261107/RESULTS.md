# Zdravotní přehled a plánovač — větší syntetická data, 7. 11. 2026

Místní HTTP/SQL měření HOTOVO; aplikace ani produkce se touto studií neměnily.
[Plán](../../MEDICAL_PLANNER_SCALE_2026-11-07.md), [předání](../../SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md).

## Praktický výsledek

- Zdravotní filtr **Platné**: server při 10k / 50k / 100k sledování prozkoumal **130558 / 650558 / 1300558 řádků**, pokaždé při stejných 20 SQL. Přesný počet vyžaduje průchod celou kandidátní historií.
- Při 100k trvalo Platné orientačně **4.50 s**, PHP peak zůstal 2.16 MiB a HTML 49,932 B. Běžný seznam měl 0.56 s a 500,683 Rows_examined. Malá stránka a omezený počet načtených řádků neznamenají omezenou práci databáze.
- Plánovač vedoucího s filtrem kořene při 52 / 260 / 520 týdnech: historický helper načetl **1450 / 7274 / 14554 řádků**, PHP peak celého requestu **3.78 / 6.67 / 10.33 MiB**. Jde o verzi před souběžnou opravou #374; časy v úplné tabulce nejsou důkazem zrychlení při větším objemu.
- Výsledky filtrů, konkrétní položky, počty odpovědí a přístupové hranice souhlasí. Tato studie nemění aplikaci ani produkci. Další priority vede předání.

## Rozsah a metoda

Zmrazená aplikace 69f49033, PHP 8.2.12 / MariaDB 10.4.32 na nevyhrazeném Windows PC.
Nový vlastní datadir/porty, 350 tabulek / 161 migrací, žádný produkční config nebo dump.
Jde o minimální legacy testovací schéma doplněné celým migračním katalogem, nikoli kopii produkčního schématu.
[Definice a indexy devíti tabulek](schema-metadata.json) zachycují podmínky studie; vedle PK začíná index plánů veřejností, další legacy indexy produkce se nekopírují.
Dvanáct rolí standardně přihlášených formulářem; po restartu vlastního serveru znovu všech 12. Web DB účet při měření SELECT only.
72 kladných případů × pět zahřátí + 30 časů = **2160 HTTP vzorků**, 28 záporných kontrol.
Celý prostý HTTP request včetně bootstrapu, aktuálního oprávnění a navigace; prostředky prohlížeče se nenačítají.
P95 je 29. z 30 seřazených hodnot. Všechny vzorky finálních úplných běhů zachované; žádné odlehlé hodnoty vyřazené.
První neúplná 100k fáze skončila chybou sdílené dočasné složky. Po ověření 21 součtů dostal vlastní server vlastní tmpdir a celých 100k se zopakovalo.
[Neúplný běh](incomplete-run.json.gz) je oddělený od 2160 vzorků; [kontrolní body](restart-checkpoints.json) dokládají nezměněná data.
Původní 10k/50k a zopakované 100k mají odlišnou cestu tmpdir na stejném disku; časové poměry nejsou kontrolovaný experiment.
Studie měří růst objemů stejné aplikace. Nejde o pár starého/nového kódu ani o produkční SLA.

Oddělená diagnostika ověřuje úplné HTML (jen CRLF/LF a CSP nonce normalizované), PHP peak a SQL.
Skutečné rows examined pocházejí z **mysql.slow_log.rows_examined** vlastního serveru; nejsou to odhady EXPLAIN.
Jde o serverový čítač, nikoli počet všech fyzických čtení úložiště. Slow log může navíc evidovat Quit s nulovými řádky.
Rows sent jsou serverové výstupní řádky včetně metadat. Samostatné CLI projekce měří skutečně fetched rows
jen daného helperu (seznam + picker nebo historický souhrn), ne celé HTTP stránky. Tyto počty nezaměňovat.

## Nezávislé objemy

| Sada | Sledování / záznamy | Týdny / plány | Členství / hrany / soupisky celkem |
| --- | ---: | ---: | ---: |
| baseline | 10000 / 30000 | 52 / 728 | 20 / 1 / 3 |
| medical50k | 50000 / 150000 | 52 / 728 | 20 / 1 / 3 |
| medical100k | 100000 / 300000 | 52 / 728 | 20 / 1 / 3 |
| planner260 | 10000 / 30000 | 260 / 3640 | 20 / 1 / 3 |
| planner520 | 10000 / 30000 | 520 / 7280 | 20 / 1 / 3 |
| roster200 | 10000 / 30000 | 52 / 728 | 200 / 10 / 12 |
| roster1000 | 10000 / 30000 | 52 / 728 | 1000 / 50 / 52 |

Zdravotní růst zachovává 52 týdnů a 20 členství; historie zachovává 10k sledování a 20 členství.
Růst členství zachovává 52 týdnů a 10k sledování, současně rozšiřuje příslušnou hierarchii.
Poslední dvě sady proto neoddělují samotný vliv počtu členů od počtu soupisek.
Všechny plány míří na kořen 1, rozšířené podřízené soupisky mají vlastní ID 10000–10049.
Historie platí od roku 2010. V aktuálním týdnu je 14 plánů, odpovědi 50 % ano / 25 % ne / 25 % bez odpovědi; cizí odpověď se nepočítá.
Zdravotní fixture má tři záznamy/sledování, 90 % Platné a 10 % Odvoláno, ostatní stavový filtr má přesně nulu.

## Všechny měřené případy

| Sada / případ | HTTP medián / p95 ms | HTML B | SQL celé HTTP | Rows examined / sent | PHP peak MiB | Fetched rows helperu |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| baseline / medical-all | 354.4 / 616.6 | 49885 | 21 | 50683 / 141 | 2.09 | 129 |
| baseline / medical-valid | 1045.1 / 1271.0 | 49903 | 20 | 130558 / 30065 | 2.16 | 30053 |
| baseline / medical-revoked | 1138.1 / 2459.4 | 49958 | 20 | 130558 / 30065 | 2.16 | 30053 |
| baseline / medical-empty | 1261.6 / 2018.2 | 45974 | 19 | 130483 / 30040 | 2.12 | 30028 |
| baseline / medical-person | 258.4 / 432.0 | 46165 | 21 | 20491 / 45 | 2.06 | 33 |
| baseline / medical-page2 | 338.9 / 557.3 | 50047 | 21 | 50733 / 141 | 2.09 | 129 |
| baseline / medical-last | 438.8 / 707.8 | 49899 | 21 | 70633 / 141 | 2.09 | 129 |
| baseline / medical-search | 465.0 / 669.4 | 49921 | 21 | 50683 / 141 | 2.09 | 129 |
| baseline / medical-picker | 704.9 / 1151.8 | 50301 | 21 | 50708 / 141 | 2.09 | 129 |
| baseline / planner-coach-own | 1098.9 / 2107.4 | 116588 | 37 | 3299 / 222 | 3.21 | 1 |
| baseline / planner-coach-root | 1203.8 / 4242.5 | 116627 | 46 | 4047 / 971 | 3.48 | 726 |
| baseline / planner-coach-child | 1410.3 / 3582.4 | 116647 | 46 | 4047 / 971 | 3.58 | 726 |
| baseline / planner-coach-trainer | 981.0 / 3383.0 | 94863 | 37 | 3299 / 221 | 3.21 | 1 |
| baseline / planner-coach-group | 476.7 / 953.2 | 140424 | 37 | 3348 / 355 | 3.23 | 1 |
| baseline / planner-coach-empty | 349.1 / 628.9 | 71054 | 35 | 3761 / 817 | 3.55 | 726 |
| baseline / planner-lead-all | 438.2 / 1099.2 | 162615 | 37 | 3348 / 355 | 3.23 | 1 |
| baseline / planner-lead-root | 411.0 / 665.5 | 162654 | 47 | 4838 / 1849 | 3.78 | 1450 |
| baseline / planner-lead-own | 307.2 / 484.2 | 71549 | 25 | 2990 / 60 | 3.19 | 1 |
| medical50k / medical-all | 1172.3 / 1383.0 | 49887 | 21 | 250683 / 141 | 2.09 | 129 |
| medical50k / medical-valid | 5932.4 / 9621.4 | 49906 | 20 | 650558 / 150065 | 2.16 | 150053 |
| medical50k / medical-revoked | 4466.9 / 5775.3 | 49960 | 20 | 650558 / 150065 | 2.16 | 150053 |
| medical50k / medical-empty | 3296.1 / 5116.0 | 45975 | 19 | 650483 / 150040 | 2.12 | 150028 |
| medical50k / medical-person | 381.7 / 445.1 | 46166 | 21 | 100491 / 45 | 2.06 | 33 |
| medical50k / medical-page2 | 453.5 / 806.5 | 50049 | 21 | 250733 / 141 | 2.09 | 129 |
| medical50k / medical-last | 962.1 / 1374.5 | 49905 | 21 | 350633 / 141 | 2.09 | 129 |
| medical50k / medical-search | 578.7 / 880.3 | 49923 | 21 | 250683 / 141 | 2.09 | 129 |
| medical50k / medical-picker | 854.0 / 1345.6 | 50303 | 21 | 250708 / 141 | 2.09 | 129 |
| medical100k / medical-all | 558.5 / 892.9 | 49916 | 21 | 500683 / 141 | 2.09 | 129 |
| medical100k / medical-valid | 4501.6 / 6360.4 | 49932 | 20 | 1300558 / 300065 | 2.16 | 300053 |
| medical100k / medical-revoked | 3997.9 / 5797.2 | 49987 | 20 | 1300558 / 300065 | 2.16 | 300053 |
| medical100k / medical-empty | 3716.6 / 4885.6 | 45976 | 19 | 1300483 / 300040 | 2.12 | 300028 |
| medical100k / medical-person | 271.7 / 588.3 | 46168 | 21 | 200491 / 45 | 2.06 | 33 |
| medical100k / medical-page2 | 1030.7 / 1619.8 | 50074 | 21 | 500733 / 141 | 2.09 | 129 |
| medical100k / medical-last | 739.9 / 2164.3 | 49930 | 21 | 700633 / 141 | 2.09 | 129 |
| medical100k / medical-search | 842.9 / 1446.3 | 49952 | 21 | 500683 / 141 | 2.09 | 129 |
| medical100k / medical-picker | 641.0 / 1151.5 | 50332 | 21 | 500708 / 141 | 2.09 | 129 |
| planner260 / planner-coach-own | 96.1 / 154.7 | 116589 | 37 | 14947 / 222 | 3.21 | 1 |
| planner260 / planner-coach-root | 128.6 / 219.3 | 116628 | 49 | 29527 / 3883 | 5.31 | 3638 |
| planner260 / planner-coach-child | 226.2 / 414.9 | 116648 | 49 | 29527 / 3883 | 5.77 | 3638 |
| planner260 / planner-coach-trainer | 188.7 / 287.4 | 94864 | 37 | 14947 / 221 | 3.21 | 1 |
| planner260 / planner-coach-group | 132.2 / 198.3 | 140425 | 37 | 14996 / 355 | 3.23 | 1 |
| planner260 / planner-coach-empty | 112.4 / 142.2 | 71054 | 38 | 29241 / 3729 | 5.71 | 3638 |
| planner260 / planner-lead-all | 100.8 / 221.6 | 162616 | 37 | 14996 / 355 | 3.23 | 1 |
| planner260 / planner-lead-root | 331.8 / 482.2 | 162655 | 53 | 40646 / 7673 | 6.67 | 7274 |
| planner260 / planner-lead-own | 135.8 / 203.7 | 71549 | 25 | 14638 / 60 | 3.19 | 1 |
| planner520 / planner-coach-own | 105.1 / 141.4 | 116589 | 37 | 29507 / 222 | 3.21 | 1 |
| planner520 / planner-coach-root | 191.3 / 288.4 | 116628 | 53 | 80625 / 7523 | 7.63 | 7278 |
| planner520 / planner-coach-child | 240.4 / 447.7 | 116648 | 53 | 80625 / 7523 | 8.54 | 7278 |
| planner520 / planner-coach-trainer | 121.9 / 179.6 | 94864 | 37 | 29507 / 221 | 3.21 | 1 |
| planner520 / planner-coach-group | 109.0 / 177.5 | 140425 | 37 | 29556 / 355 | 3.23 | 1 |
| planner520 / planner-coach-empty | 149.8 / 204.4 | 71054 | 42 | 80339 / 7369 | 8.45 | 7278 |
| planner520 / planner-lead-all | 115.5 / 173.1 | 162616 | 37 | 29556 / 355 | 3.23 | 1 |
| planner520 / planner-lead-root | 200.2 / 427.6 | 162655 | 60 | 131786 / 14953 | 10.33 | 14554 |
| planner520 / planner-lead-own | 110.7 / 176.6 | 71549 | 25 | 29198 / 60 | 3.19 | 1 |
| roster200 / planner-coach-own | 122.8 / 180.1 | 117140 | 37 | 5576 / 1365 | 3.41 | 1 |
| roster200 / planner-coach-root | 118.2 / 172.6 | 117179 | 46 | 6351 / 2141 | 3.54 | 735 |
| roster200 / planner-coach-child | 142.6 / 225.3 | 117199 | 46 | 6351 / 2141 | 3.63 | 735 |
| roster200 / planner-coach-trainer | 101.4 / 146.1 | 95415 | 37 | 5576 / 1364 | 3.41 | 1 |
| roster200 / planner-coach-group | 102.7 / 235.3 | 140997 | 37 | 5625 / 2443 | 4.00 | 1 |
| roster200 / planner-coach-empty | 88.9 / 140.6 | 71585 | 35 | 3806 / 853 | 3.61 | 735 |
| roster200 / planner-lead-all | 90.5 / 130.6 | 163188 | 37 | 5625 / 2443 | 4.00 | 1 |
| roster200 / planner-lead-root | 137.0 / 198.9 | 163227 | 47 | 7142 / 3964 | 4.00 | 1459 |
| roster200 / planner-lead-own | 83.5 / 123.3 | 72080 | 25 | 3008 / 69 | 3.19 | 1 |
| roster1000 / planner-coach-own | 102.7 / 142.8 | 119554 | 37 | 15696 / 6445 | 5.89 | 1 |
| roster1000 / planner-coach-root | 120.6 / 216.6 | 119593 | 46 | 16591 / 7341 | 5.89 | 775 |
| roster1000 / planner-coach-child | 128.9 / 215.3 | 119613 | 46 | 16591 / 7341 | 5.89 | 775 |
| roster1000 / planner-coach-trainer | 98.7 / 123.0 | 97829 | 37 | 15696 / 6444 | 5.89 | 1 |
| roster1000 / planner-coach-group | 111.3 / 276.7 | 143425 | 37 | 15745 / 11723 | 8.57 | 1 |
| roster1000 / planner-coach-empty | 101.3 / 163.5 | 73985 | 35 | 4006 / 1013 | 3.97 | 775 |
| roster1000 / planner-lead-all | 149.1 / 223.2 | 165616 | 37 | 15745 / 11723 | 8.56 | 1 |
| roster1000 / planner-lead-root | 171.8 / 274.1 | 165655 | 47 | 17382 / 13364 | 8.56 | 1499 |
| roster1000 / planner-lead-own | 86.7 / 155.1 | 74480 | 25 | 3088 / 109 | 3.19 | 1 |

## Ověření a meze

72 úplných doménových/DOM porovnání PASS, 72 projekcí READ ONLY; všechna diagnostická HTML stejná jako prosté HTTP.
Zdravotní výsledek včetně všech stavových metadat se rovná zmrazenému původnímu algoritmu; stránka má maximálně 25 položek a výběr osob 25 + případný kontext.
Historický souhrn se rovná původnímu algoritmu, RSVP kanonickému readeru i nezávislým známým počtům seedu; ověřeny konkrétní ID karet a zdravotních řádků.
V každé sadě všech 21 doménových kontrolních součtů před/po časování, projekcích, paritě a diagnostice shodných; diagnostika 0 DML/DDL.
Časy jednotlivých objemů jsou sekvenční běhy na sdílené pracovní stanici; běžící činnosti Windows nejsou řízené. Časový poměr není kauzální odhad ani SLA.
Data neobsahují dlouhé komplikované řetězce náhrad ani proměnlivou historickou topologii; starší malé regrese je pokrývají samostatně.
Rozšíření počtu členství současně rozšiřuje hierarchii; další faktoriální matice může tyto vlivy oddělit.
Nový prohlížečový výkon, fyzický mobil, řízená síť/CPU a souběžní uživatelé **NOT RUN**. Databáze 10.3/11.4 tímto místním během nejsou přeměřené.
Úklid, porty a původní WIP PASS, viz [cleanup](cleanup.json). Bez deploye/aktivací/transportu.

[Souhrn](summary.json), [prostředí a otisky](environment.json), [manifest](artifact-sha256.json).
Každá ze sedmi sad má všech pět komprimovaných JSON podkladů: seed/http/diagnostics/verification/projections.
[Reprodukce](REPRODUCE.md), [kalibrace](CALIBRATION.md).

## Novější přijatý plánovač

Při předání převzatý main e36c9ba6 / #374. Jeho vlastní párový Linux test
obsahuje20728plánů a celý HTTP/Chrome. Zdejší primární R05 popisuje starší69f49033.
[Doplnění dvou největších sad na aktuálním kódu](current-planner/RESULTS.md) je dokončené
samostatně; primárních2160vzorků a jejich51hashů se nepřepisuje.
