# P11-RHMS — celé párové HTTP a Chrome měření

Baseline main `0b4592fa63ee49a733b8d4a882c177ac73541e99` (runtime nasazeného 5cc9cf83).
Vše syntetické: Linux/PHP 8.2, dvě varianty stejné plně migrované DB, cache OFF, společné obnovené statistiky.
1k/10k/30k tréninků; 9 scénářů × 17 střídavých párů po 4 warmupech. Výběr osoby přes parent před AJAX je mimo časování.
918 časovaných AJAX na DB, 1836 celkem. Samostatná SQL diagnostika se do těchto časů nepočítá.

| MariaDB | Tréninky | Scénář | Před ms | Po ms | Změna |
| --- | ---: | --- | ---: | ---: | ---: |
| 10.3 | 1000 | primary | 20.645 | 16.448 | -20.3 % |
| 10.3 | 10000 | primary | 162.145 | 111.203 | -31.4 % |
| 10.3 | 30000 | primary | 535.915 | 342.364 | -36.1 % |
| 10.3 | 30000 | exact_case | 539.619 | 346.775 | -35.7 % |
| 10.3 | 30000 | exact_space | 542.276 | 345.188 | -36.3 % |
| 10.3 | 30000 | page2 | 534.215 | 346.434 | -35.2 % |
| 10.3 | 30000 | last | 817.079 | 623.651 | -23.7 % |
| 10.3 | 30000 | absent | 766.921 | 565.895 | -26.2 % |
| 10.3 | 30000 | small | 404.075 | 430.518 | +6.5 % |
| 10.3 | 30000 | old | 414.881 | 424.914 | +2.4 % |
| 10.3 | 30000 | empty | 412.071 | 407.902 | -1.0 % |
| 11.4 | 1000 | primary | 32.701 | 24.553 | -24.9 % |
| 11.4 | 10000 | primary | 292.113 | 184.493 | -36.8 % |
| 11.4 | 30000 | primary | 875.862 | 546.464 | -37.6 % |
| 11.4 | 30000 | exact_case | 874.564 | 547.674 | -37.4 % |
| 11.4 | 30000 | exact_space | 874.496 | 547.101 | -37.4 % |
| 11.4 | 30000 | page2 | 875.518 | 547.125 | -37.5 % |
| 11.4 | 30000 | last | 1322.356 | 992.598 | -24.9 % |
| 11.4 | 30000 | absent | 1314.226 | 984.818 | -25.1 % |
| 11.4 | 30000 | small | 760.164 | 774.533 | +1.9 % |
| 11.4 | 30000 | old | 770.159 | 775.639 | +0.7 % |
| 11.4 | 30000 | empty | 760.120 | 761.636 | +0.2 % |

## Samostatná SQL diagnóza

| MariaDB | Varianta | SQL | Vrácené řádky | Souhrn ms | Detail ms |
| --- | --- | ---: | ---: | ---: | ---: |
| 10.3 | before | 17 | 48 | 481.853 | 5.004 |
| 10.3 | after | 17 | 48 | 324.690 | 4.907 |
| 11.4 | before | 17 | 48 | 863.065 | 6.247 |
| 11.4 | after | 17 | 48 | 539.863 | 6.239 |

PHP peak diagnostiky obsahuje profiler; není to paměť prostého requestu, RSS ani paměť DB. EXPLAIN raw JSON se uchovává celý (10.3 může opakovat klíče).

## Skutečný Chrome

52 měřených navigací plus warmupy/interakce. Dokument TTFB, AJAX fetch→DOM+2frames a celá navigace odděleně; žádné request stubs.

| Záložka | Cache | Metrika | Před ms | Po ms | Změna |
| --- | --- | --- | ---: | ---: | ---: |
| default | cold | document_ttfb_ms | 450.80 | 449.40 | -0.3 % |
| default | cold | ajax_pair_render_ready_ms | 514.50 | 522.60 | +1.6 % |
| default | cold | navigation_to_both_ready_ms | 1015.60 | 1015.90 | +0.0 % |
| default | warm | document_ttfb_ms | 449.30 | 450.50 | +0.3 % |
| default | warm | ajax_pair_render_ready_ms | 519.50 | 519.25 | -0.0 % |
| default | warm | navigation_to_both_ready_ms | 1015.00 | 1009.60 | -0.5 % |
| measurements | cold | document_ttfb_ms | 450.50 | 450.00 | -0.1 % |
| measurements | cold | ajax_pair_render_ready_ms | 1070.60 | 749.10 | -30.0 % |
| measurements | cold | navigation_to_both_ready_ms | 1569.30 | 1241.50 | -20.9 % |
| measurements | cold | measurement_fetch_to_render_ready_ms | 1034.70 | 715.30 | -30.9 % |
| measurements | warm | document_ttfb_ms | 449.15 | 453.75 | +1.0 % |
| measurements | warm | ajax_pair_render_ready_ms | 1052.10 | 711.85 | -32.3 % |
| measurements | warm | navigation_to_both_ready_ms | 1542.55 | 1209.30 | -21.6 % |
| measurements | warm | measurement_fetch_to_render_ready_ms | 1042.45 | 696.15 | -33.2 % |

## Shoda a hranice

Na každé MariaDB 6 testů / 634 assertions. Úplné návratové objekty a PHP typy, pořadí i počty; native/emulated PDO, stringify, NULL a zero/partial-zero data, nullable/negative/signed64 pořadí a ID, přesné case/space/Unicode/numerické/NULL typy, 124 typů, duplicity/orphans/foreign/unlinked a čerstvé změny.
Celé fragmenty/parent DOM, 12 staff rolí a 4 povolené kontexty, bearer/retarget/revoke/expire/scope/inactive, CSRF/logout, oba parent fallbacky a 11 doménových checksumů PASS. Cleanup a zdroje po běhu beze změny.
Desktop i 390px snímek před/po byte-identické a oba aktuální snímky po změně vizuálně ověřené. Stávající clipping dlouhých poznámek v úzké tabulce beze změny; pager uvnitř viewportu. [Vizuální důkaz](visual-verification.json). Přihlášené produkční UAT, fyzický mobil, produkční výkon a obnova konkrétní produkční zálohy NOT RUN. Syntetické časy nejsou SLA ani LCP. Merge/deploy a všechna aktuální CI se dokládají samostatně.


Aktuální testovaný head730de3d6, běh42676475155: oběDB SUCCESS a navazující
všech20CI SUCCESS. Předchozí celé d8měření je samostatně zachované v
sousedním `validated-d8/`, runtime obou běhů byte-shodný. U malého profilu
je zde +26.443/+14.369ms (+6.5/+1.9 %); starý +10.033/+5.480ms
(+2.4/+0.7 %), prázdný −1.0/+0.2 %. Nepřipisovat hlavní úsporu všem
profilům/stránkám. Primary20%gate a historický no-regression gate PASS.
