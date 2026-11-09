# P11-RHMS — celé párové HTTP a Chrome měření

Baseline main `0b4592fa63ee49a733b8d4a882c177ac73541e99` (runtime nasazeného 5cc9cf83).
Vše syntetické: Linux/PHP 8.2, dvě varianty stejné plně migrované DB, cache OFF, společné obnovené statistiky.
1k/10k/30k tréninků; 9 scénářů × 17 střídavých párů po 4 warmupech. Výběr osoby přes parent před AJAX je mimo časování.
918 časovaných AJAX na DB, 1836 celkem. Samostatná SQL diagnostika se do těchto časů nepočítá.

| MariaDB | Tréninky | Scénář | Před ms | Po ms | Změna |
| --- | ---: | --- | ---: | ---: | ---: |
| 10.3 | 1000 | primary | 14.961 | 12.333 | -17.6 % |
| 10.3 | 10000 | primary | 111.860 | 80.260 | -28.2 % |
| 10.3 | 30000 | primary | 400.914 | 253.962 | -36.7 % |
| 10.3 | 30000 | exact_case | 385.229 | 249.719 | -35.2 % |
| 10.3 | 30000 | exact_space | 412.270 | 258.222 | -37.4 % |
| 10.3 | 30000 | page2 | 412.294 | 253.258 | -38.6 % |
| 10.3 | 30000 | last | 587.955 | 489.160 | -16.8 % |
| 10.3 | 30000 | absent | 560.832 | 427.257 | -23.8 % |
| 10.3 | 30000 | small | 311.850 | 297.108 | -4.7 % |
| 10.3 | 30000 | old | 306.782 | 310.550 | +1.2 % |
| 10.3 | 30000 | empty | 295.615 | 295.443 | -0.1 % |
| 11.4 | 1000 | primary | 24.441 | 17.326 | -29.1 % |
| 11.4 | 10000 | primary | 226.237 | 135.027 | -40.3 % |
| 11.4 | 30000 | primary | 672.882 | 393.144 | -41.6 % |
| 11.4 | 30000 | exact_case | 673.250 | 395.134 | -41.3 % |
| 11.4 | 30000 | exact_space | 674.989 | 395.304 | -41.4 % |
| 11.4 | 30000 | page2 | 676.773 | 397.003 | -41.3 % |
| 11.4 | 30000 | last | 1033.482 | 747.292 | -27.7 % |
| 11.4 | 30000 | absent | 980.232 | 704.230 | -28.2 % |
| 11.4 | 30000 | small | 592.295 | 603.420 | +1.9 % |
| 11.4 | 30000 | old | 599.937 | 605.397 | +0.9 % |
| 11.4 | 30000 | empty | 590.000 | 593.625 | +0.6 % |

## Samostatná SQL diagnóza

| MariaDB | Varianta | SQL | Vrácené řádky | Souhrn ms | Detail ms |
| --- | --- | ---: | ---: | ---: | ---: |
| 10.3 | before | 17 | 48 | 341.509 | 3.770 |
| 10.3 | after | 17 | 48 | 228.153 | 3.557 |
| 11.4 | before | 17 | 48 | 667.687 | 5.354 |
| 11.4 | after | 17 | 48 | 395.236 | 4.864 |

PHP peak diagnostiky obsahuje profiler; není to paměť prostého requestu, RSS ani paměť DB. EXPLAIN raw JSON se uchovává celý (10.3 může opakovat klíče).

## Skutečný Chrome

52 měřených navigací plus warmupy/interakce. Dokument TTFB, AJAX fetch→DOM+2frames a celá navigace odděleně; žádné request stubs.

| Záložka | Cache | Metrika | Před ms | Po ms | Změna |
| --- | --- | --- | ---: | ---: | ---: |
| default | cold | document_ttfb_ms | 347.10 | 349.50 | +0.7 % |
| default | cold | ajax_pair_render_ready_ms | 411.60 | 410.20 | -0.3 % |
| default | cold | navigation_to_both_ready_ms | 796.80 | 796.50 | -0.0 % |
| default | warm | document_ttfb_ms | 348.70 | 353.85 | +1.5 % |
| default | warm | ajax_pair_render_ready_ms | 400.95 | 404.25 | +0.8 % |
| default | warm | navigation_to_both_ready_ms | 782.05 | 789.75 | +1.0 % |
| measurements | cold | document_ttfb_ms | 350.40 | 350.60 | +0.1 % |
| measurements | cold | ajax_pair_render_ready_ms | 1083.50 | 811.80 | -25.1 % |
| measurements | cold | navigation_to_both_ready_ms | 1476.20 | 1201.70 | -18.6 % |
| measurements | cold | measurement_fetch_to_render_ready_ms | 738.50 | 451.90 | -38.8 % |
| measurements | warm | document_ttfb_ms | 351.30 | 349.40 | -0.5 % |
| measurements | warm | ajax_pair_render_ready_ms | 853.50 | 562.45 | -34.1 % |
| measurements | warm | navigation_to_both_ready_ms | 1243.60 | 943.50 | -24.1 % |
| measurements | warm | measurement_fetch_to_render_ready_ms | 841.50 | 549.70 | -34.7 % |

## Shoda a hranice

Na každé MariaDB 6 testů / 634 assertions. Úplné návratové objekty a PHP typy, pořadí i počty; native/emulated PDO, stringify, NULL a zero/partial-zero data, nullable/negative/signed64 pořadí a ID, přesné case/space/Unicode/numerické/NULL typy, 124 typů, duplicity/orphans/foreign/unlinked a čerstvé změny.
Celé fragmenty/parent DOM, 12 staff rolí a 4 povolené kontexty, bearer/retarget/revoke/expire/scope/inactive, CSRF/logout, oba parent fallbacky a 11 doménových checksumů PASS. Cleanup a zdroje po běhu beze změny.
Snímky vyhodnotit vizuálně zvlášť. Přihlášené produkční UAT, fyzický mobil, produkční výkon a obnova konkrétní produkční zálohy NOT RUN. Syntetické časy nejsou SLA ani LCP. Merge/deploy a všechna aktuální CI se dokládají samostatně.


Desktop i 390px snímek před/po jsou byte-identické; oba aktuální snímky po
změně vizuálně prohlédnuté. Pager je v šířce viewportu. Stávající úzká tabulka
omezuje dlouhé syntetické poznámky; nejde o nově opravené mobilní UX ani
fyzické mobilní UAT. [Otisky a omezení](visual-verification.json).
