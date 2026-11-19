# P11-CATSORT — jednorázové klíče řazení katalogu

19. 11. 2026 Europe/Prague. **NASAZENO #500/62c00649080ecdedd5bdd48b536b72d2dae84451,13CI PASS.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catsort--řazení-katalogu-19-11-2026),
[PR#500](https://github.com/KovoPraha/evidenceTreninku/pull/500),
[párový běh44472254109](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44472254109).
Testovaný head `8570d24f739cd489808053796fe18a2c6b61d8bc`, immutable baseline `4f43f793510fd7dd1bb136cea875475b258adab6`.

## Změna a přesná shoda

Pouze blok řazení v shopCatalogPageSelect. String názvy/int ID a příslušné
int cena/pořadí umožní jednou připravit samostatné sloupce s mb_strtolower.
array_multisort porovnává primární cenu/pořadí, názvy a ID pomocí SORT_REGULAR
pro každý sloupec; původní pozice je poslední klíč shod. Mapa původních klíčů
zachová také asociativní/neposloupné vstupy, bez deduplikace nebo porovnávání
produktového payloadu. Žádná nová normalizace/collation/float konverze intů.
Původní usort callback pro nekanonické typy, chybějící pole a0/1produkty,
včetně stejného počtu/pořadí konverzí, varování a výjimek. Numeric-string
názvy, whitespace/Unicode/neplatnéUTF8, duplicitní ID/záznamy i úplné ties,
všechny sorty/stránky/searchMatches/filtry a SQL/transakční sekvence pokryté.
Celý prefix/suffix PageRead a celý shop_category.php byteově shodný,
source contract připíná přesný blok i frozen celý Select z aktuálního main.
Native před je strict frozen Select, celé HTTP fyzický původní modul.
Filtry/menu/CATTOP/prodejnost/ceny/writery/cache/limity beze změny,
outsideTX3SQL, insideTX původní6SQL. [Lokální kontroly](local-checks.json):
6testů/675kontrol v širším katalogu39/4869, plný PHPStan/styl/Composer/syntaxe PASS.

## Místní diagnóza a odmítnutý kandidát

První uasort kandidát7d54ffb4 byl odmítnut na pevné20% celéHTTP bráně:
10.3 large5000 overview-customer147,209→120,995ms (17,807%),
11.4 large10000 overview-anonymous234,541→189,427ms (19,235%).
[Obě odmítnutí](rejected-uasort.json), [původní běh](rejected-uasort/run.json)
a všechny dostupné safe artefakty zůstávají ve složce rejected-uasort.
Žádné sloučení/nasazení této varianty a žádné změkčení bran. Druhý asort
kandidát0b8ed5a2 také odmítnut:10.3 large10000 overview-anonymous
274,934→225,040ms (18,1%) pod20%.11.4 včetně Chrome PASS, celková přejímka FAIL.
[Odmítnutí](rejected-asort.json), [běh](rejected-asort/run.json) a dostupné
safe artefakty obou DB uchované v rejected-asort. Finální změna používá
samostatné skalární sloupce místo vnořených tuple a array_multisort.
[PHP dokumentace](https://www.php.net/manual/en/function.array-multisort.php)
popisuje lexikografické normální porovnání bez změny typů. Rozhodující je
úplná app parita včetně velkých intů a numeric-string jmen. Statická analýza
odhalila nadbytečné list kontroly; nahrazené mapou původních klíčů bez změny
typů/baseline nebo potlačení pravidel, finální celý PHPStan PASS.

První místní profil je uasort. SQLite/PHP8.2.12, celý Select,
2warmup+7střídavých párů na canonical DTO,
permutované ASCII/české názvy a ceny.24profilů, celé stavy/hash shodné.
Toto není MariaDB přejímka ani produkční profil. [Data](local-probe.json),
[sonda](local-probe.php.txt), [prototyp](local-prototype.txt). Reprodukce:
kopie do output/catalog-sort-probe.php.txt a output/catalog-sort-block.txt
v odděleném checkoutu base4f43f793, nikoli optimalizovaného main.
Všechny směry a paměť v surových datech; nativní fixture používá původní
krátké ASCII názvy, žádný umělý dlouhý text pro výhodnější měření.

| Produkty/uzly | Názvy/režim | Před → po ms |
| --- | --- | ---: |
| 12/6 | ascii/order | 0.19 → 0.15 |
| 12/6 | ascii/name | 0.21 → 0.15 |
| 12/6 | ascii/price | 0.19 → 0.15 |
| 12/6 | czech/order | 0.25 → 0.17 |
| 12/6 | czech/name | 0.28 → 0.16 |
| 12/6 | czech/price | 0.25 → 0.17 |
| 1000/100 | ascii/order | 26.53 → 6.13 |
| 1000/100 | ascii/name | 32.00 → 10.01 |
| 1000/100 | ascii/price | 48.74 → 12.79 |
| 1000/100 | czech/order | 52.05 → 10.08 |
| 1000/100 | czech/name | 49.62 → 10.30 |
| 1000/100 | czech/price | 77.67 → 17.69 |
| 5000/500 | ascii/order | 145.55 → 41.09 |
| 5000/500 | ascii/name | 206.98 → 51.58 |
| 5000/500 | ascii/price | 146.10 → 37.87 |
| 5000/500 | czech/order | 444.25 → 63.17 |
| 5000/500 | czech/name | 227.18 → 45.26 |
| 5000/500 | czech/price | 234.33 → 44.89 |
| 10000/1000 | ascii/order | 352.99 → 86.29 |
| 10000/1000 | ascii/name | 453.77 → 132.96 |
| 10000/1000 | ascii/price | 605.17 → 205.81 |
| 10000/1000 | czech/order | 1217.87 → 307.67 |
| 10000/1000 | czech/name | 937.90 → 100.89 |
| 10000/1000 | czech/price | 586.70 → 125.02 |

Mezikrok asort je samostatný:12profilů5000/500 a10000/1000,
ASCII/čeština×3sorty, celý stav/hash shodné, stejná metoda párování.
[Data](local-asort-probe.json), [sonda](local-asort-probe.php.txt),
[prototyp](local-asort-prototype.txt), [frozen PageRead](local-asort-frozen-page.txt).
Reprodukce: kopie sondy/prototypu/frozen souboru do output pod názvy
catalog-sort-asort-probe.php.txt, catalog-sort-asort-block.txt a
catalog-sort-frozen-page.txt; závislosti fixture z base4f43f793.

Finální sloupce/mapa klíčů mají samostatných12profilů se stejnými objemy,
ASCII/češtinou, všemi3sorty a metodou, celý stav/hash shodné.
[Data](local-columns-probe.json), [sonda](local-columns-probe.php.txt),
[prototyp](local-columns-prototype.txt). Kopie do output pod názvy
catalog-sort-columns-final-probe.php.txt, catalog-sort-columns-block.txt,
stejný frozen PageRead jako výše. Tyto výsledky odpovídají finální variantě.

## Nativní celý Select na MariaDB

30profilů/DB:10objemů/výběrů×3sorty.12/6flat root,1000/100tree root,
5000/500tree root/overview,10000/1000tree root/overview,10000/1flat root,
1000/100tree leaf/missing a0/0empty.2warmup+11střídavých párů v čerstvých
PHP workerech. Vždy3SQL před/po, celý stav/typy/pořadí/serializedSHA stejné
a checksumy3tabulek stejné. Raw pole roots znamená počet uzlů, medians.json
jej výslovně pojmenovává nodes. Peak je přírůstek PHP managed memory nad
počátečním stavem workeru, nikoli systémové RSS; paměť klíčů je zveřejněná.
Před měřením pevné cíle5000/500 a10000/1000 tree overview/root order/name
celý Select i celé HTTP anonym/zákazník≥20% na obou DB. Price také měřený,
ostatní kontroly≤20%+5msnative/+20msHTTP. Brány se nezměnily podle výsledku.

| DB | Produkty/uzly/shape/výběr/sort | Před → po ms | Peak MiB před → po |
| --- | --- | ---: | ---: |
| 10.3 | 12/6/flat/root/order | 0.70 → 0.74 | 0.041 → 0.041 |
| 10.3 | 1000/100/tree/root/order | 6.81 → 3.25 | 0.356 → 0.547 |
| 10.3 | 5000/500/tree/root/order | 41.67 → 14.63 | 1.712 → 2.870 |
| 10.3 | 10000/1000/tree/root/order | 91.57 → 31.57 | 3.446 → 5.735 |
| 10.3 | 10000/1/flat/root/order | 79.54 → 19.61 | 1.138 → 3.591 |
| 10.3 | 10000/1000/tree/overview/order | 89.86 → 29.54 | 3.446 → 5.422 |
| 10.3 | 5000/500/tree/overview/order | 41.63 → 14.07 | 1.712 → 2.710 |
| 10.3 | 1000/100/tree/leaf/order | 2.73 → 2.73 | 0.356 → 0.356 |
| 10.3 | 1000/100/tree/missing/order | 2.61 → 2.61 | 0.356 → 0.356 |
| 10.3 | 0/0/flat/root/order | 0.60 → 0.65 | 0.037 → 0.037 |
| 10.3 | 12/6/flat/root/name | 0.71 → 0.74 | 0.041 → 0.041 |
| 10.3 | 1000/100/tree/root/name | 6.56 → 3.16 | 0.356 → 0.497 |
| 10.3 | 5000/500/tree/root/name | 39.61 → 13.89 | 1.712 → 2.588 |
| 10.3 | 10000/1000/tree/root/name | 86.79 → 29.46 | 3.446 → 5.176 |
| 10.3 | 10000/1/flat/root/name | 74.56 → 18.14 | 1.138 → 3.031 |
| 10.3 | 10000/1000/tree/overview/name | 85.24 → 28.04 | 3.446 → 4.863 |
| 10.3 | 5000/500/tree/overview/name | 38.90 → 13.42 | 1.712 → 2.428 |
| 10.3 | 1000/100/tree/leaf/name | 2.80 → 2.82 | 0.356 → 0.356 |
| 10.3 | 1000/100/tree/missing/name | 2.61 → 2.61 | 0.356 → 0.356 |
| 10.3 | 0/0/flat/root/name | 0.66 → 0.61 | 0.037 → 0.037 |
| 10.3 | 12/6/flat/root/price | 0.71 → 0.74 | 0.041 → 0.041 |
| 10.3 | 1000/100/tree/root/price | 6.65 → 3.19 | 0.356 → 0.547 |
| 10.3 | 5000/500/tree/root/price | 40.26 → 14.07 | 1.712 → 2.870 |
| 10.3 | 10000/1000/tree/root/price | 87.31 → 29.80 | 3.446 → 5.735 |
| 10.3 | 10000/1/flat/root/price | 75.65 → 18.42 | 1.138 → 3.591 |
| 10.3 | 10000/1000/tree/overview/price | 85.54 → 28.15 | 3.446 → 5.422 |
| 10.3 | 5000/500/tree/overview/price | 39.65 → 13.22 | 1.712 → 2.710 |
| 10.3 | 1000/100/tree/leaf/price | 2.72 → 2.72 | 0.356 → 0.356 |
| 10.3 | 1000/100/tree/missing/price | 2.59 → 2.60 | 0.356 → 0.356 |
| 10.3 | 0/0/flat/root/price | 0.63 → 0.65 | 0.037 → 0.037 |
| 11.4 | 12/6/flat/root/order | 0.40 → 0.43 | 0.041 → 0.041 |
| 11.4 | 1000/100/tree/root/order | 5.60 → 2.41 | 0.356 → 0.547 |
| 11.4 | 5000/500/tree/root/order | 33.89 → 11.40 | 1.712 → 2.870 |
| 11.4 | 10000/1000/tree/root/order | 72.66 → 22.01 | 3.446 → 5.735 |
| 11.4 | 10000/1/flat/root/order | 65.02 → 14.91 | 1.138 → 3.591 |
| 11.4 | 10000/1000/tree/overview/order | 71.91 → 21.05 | 3.446 → 5.422 |
| 11.4 | 5000/500/tree/overview/order | 33.64 → 10.78 | 1.712 → 2.710 |
| 11.4 | 1000/100/tree/leaf/order | 2.00 → 2.00 | 0.356 → 0.356 |
| 11.4 | 1000/100/tree/missing/order | 1.95 → 1.92 | 0.356 → 0.356 |
| 11.4 | 0/0/flat/root/order | 0.34 → 0.36 | 0.037 → 0.037 |
| 11.4 | 12/6/flat/root/name | 0.42 → 0.43 | 0.041 → 0.041 |
| 11.4 | 1000/100/tree/root/name | 5.62 → 2.35 | 0.356 → 0.497 |
| 11.4 | 5000/500/tree/root/name | 32.41 → 11.13 | 1.712 → 2.588 |
| 11.4 | 10000/1000/tree/root/name | 68.62 → 21.20 | 3.446 → 5.176 |
| 11.4 | 10000/1/flat/root/name | 60.94 → 13.63 | 1.138 → 3.031 |
| 11.4 | 10000/1000/tree/overview/name | 67.39 → 19.77 | 3.446 → 4.863 |
| 11.4 | 5000/500/tree/overview/name | 31.87 → 10.45 | 1.712 → 2.428 |
| 11.4 | 1000/100/tree/leaf/name | 2.00 → 2.02 | 0.356 → 0.356 |
| 11.4 | 1000/100/tree/missing/name | 1.92 → 1.90 | 0.356 → 0.356 |
| 11.4 | 0/0/flat/root/name | 0.36 → 0.36 | 0.037 → 0.037 |
| 11.4 | 12/6/flat/root/price | 0.44 → 0.41 | 0.041 → 0.041 |
| 11.4 | 1000/100/tree/root/price | 5.73 → 2.39 | 0.356 → 0.547 |
| 11.4 | 5000/500/tree/root/price | 33.05 → 11.11 | 1.712 → 2.870 |
| 11.4 | 10000/1000/tree/root/price | 70.71 → 21.46 | 3.446 → 5.735 |
| 11.4 | 10000/1/flat/root/price | 63.00 → 14.12 | 1.138 → 3.591 |
| 11.4 | 10000/1000/tree/overview/price | 69.50 → 20.30 | 3.446 → 5.422 |
| 11.4 | 5000/500/tree/overview/price | 32.49 → 10.36 | 1.712 → 2.710 |
| 11.4 | 1000/100/tree/leaf/price | 2.02 → 2.03 | 0.356 → 0.356 |
| 11.4 | 1000/100/tree/missing/price | 1.99 → 1.91 | 0.356 → 0.356 |
| 11.4 | 0/0/flat/root/price | 0.37 → 0.52 | 0.037 → 0.037 |

## Celé HTTP přes skutečné PHP/Apache

82scénářů/DB:26matrix12/6 a14na každý large5000/500,large10000/1000,
single10000/1 a browser120/12. Overview/category/leaf/name/price/category-name/
category-price×anonym/zákazník; matrix navíc search/empty/missing/invalid/page/
CSRF.4warmup+17střídavých párů pro200; negativní303 jeden pár. SQL result
cache OFF. PageRead před časováním asertuje skutečný aktivní i filtrovaný
objem, metadata uzly a potomky. Celé HTML/hlavičky přesně shodné po pouze
konkrétním nonce/CSRF/checkout_key/origin normalizování; CSP kontroluje skutečné
script/style/img source a hodnoty. Všech355tabulek checksum-shodných po každé
fázi i Chrome. Žádné maskování změn objednávek nebo skutečných finančních toků.
Všechna výsledná data v medians.json a kompletní safe HTTP ve složkách DB.

| DB | Případ | Před → po ms | Zisk |
| --- | --- | ---: | ---: |
| 10.3 | matrix/overview-anonymous | 7.09 → 6.78 | +4.5% |
| 10.3 | matrix/category-anonymous | 7.01 → 6.57 | +6.2% |
| 10.3 | matrix/category-name-anonymous | 6.79 → 6.43 | +5.3% |
| 10.3 | matrix/category-price-anonymous | 6.64 → 6.76 | -1.8% |
| 10.3 | matrix/leaf-anonymous | 6.59 → 6.81 | -3.4% |
| 10.3 | matrix/missing-anonymous | 6.34 → 6.37 | -0.5% |
| 10.3 | matrix/invalid-anonymous | 6.54 → 6.48 | +0.9% |
| 10.3 | matrix/search-anonymous | 6.51 → 6.58 | -1.0% |
| 10.3 | matrix/empty-anonymous | 6.64 → 6.68 | -0.7% |
| 10.3 | matrix/sort-price-anonymous | 6.69 → 6.71 | -0.3% |
| 10.3 | matrix/sort-name-anonymous | 6.68 → 6.65 | +0.4% |
| 10.3 | matrix/page-anonymous | 6.96 → 6.76 | +2.8% |
| 10.3 | matrix/overview-customer | 13.28 → 13.15 | +1.0% |
| 10.3 | matrix/category-customer | 12.78 → 12.71 | +0.6% |
| 10.3 | matrix/category-name-customer | 12.86 → 12.74 | +0.9% |
| 10.3 | matrix/category-price-customer | 13.01 → 12.81 | +1.5% |
| 10.3 | matrix/leaf-customer | 13.04 → 13.04 | -0.0% |
| 10.3 | matrix/missing-customer | 12.77 → 12.81 | -0.3% |
| 10.3 | matrix/invalid-customer | 12.79 → 12.75 | +0.3% |
| 10.3 | matrix/search-customer | 12.73 → 12.90 | -1.4% |
| 10.3 | matrix/empty-customer | 12.78 → 12.49 | +2.2% |
| 10.3 | matrix/sort-price-customer | 12.75 → 12.94 | -1.5% |
| 10.3 | matrix/sort-name-customer | 12.90 → 13.05 | -1.1% |
| 10.3 | matrix/page-customer | 12.88 → 12.93 | -0.4% |
| 10.3 | matrix/csrf-customer | 12.70 → 12.69 | +0.1% |
| 10.3 | large-5000/overview-anonymous | 163.29 → 125.53 | +23.1% |
| 10.3 | large-5000/category-anonymous | 162.66 → 124.19 | +23.6% |
| 10.3 | large-5000/category-name-anonymous | 161.72 → 123.57 | +23.6% |
| 10.3 | large-5000/category-price-anonymous | 160.40 → 122.20 | +23.8% |
| 10.3 | large-5000/leaf-anonymous | 118.86 → 117.92 | +0.8% |
| 10.3 | large-5000/sort-price-anonymous | 159.60 → 122.77 | +23.1% |
| 10.3 | large-5000/sort-name-anonymous | 156.67 → 120.91 | +22.8% |
| 10.3 | large-5000/overview-customer | 172.52 → 134.93 | +21.8% |
| 10.3 | large-5000/category-customer | 170.25 → 133.47 | +21.6% |
| 10.3 | large-5000/category-name-customer | 168.68 → 132.38 | +21.5% |
| 10.3 | large-5000/category-price-customer | 169.14 → 134.06 | +20.7% |
| 10.3 | large-5000/leaf-customer | 130.22 → 131.53 | -1.0% |
| 10.3 | large-5000/sort-price-customer | 172.91 → 136.33 | +21.2% |
| 10.3 | large-5000/sort-name-customer | 172.68 → 136.35 | +21.0% |
| 10.3 | large-10000/overview-anonymous | 318.77 → 237.34 | +25.5% |
| 10.3 | large-10000/category-anonymous | 316.81 → 232.06 | +26.8% |
| 10.3 | large-10000/category-name-anonymous | 314.48 → 231.28 | +26.5% |
| 10.3 | large-10000/category-price-anonymous | 310.64 → 228.58 | +26.4% |
| 10.3 | large-10000/leaf-anonymous | 218.93 → 218.84 | +0.0% |
| 10.3 | large-10000/sort-price-anonymous | 310.87 → 230.57 | +25.8% |
| 10.3 | large-10000/sort-name-anonymous | 311.96 → 230.62 | +26.1% |
| 10.3 | large-10000/overview-customer | 349.38 → 259.43 | +25.7% |
| 10.3 | large-10000/category-customer | 345.86 → 259.46 | +25.0% |
| 10.3 | large-10000/category-name-customer | 343.36 → 257.49 | +25.0% |
| 10.3 | large-10000/category-price-customer | 339.49 → 256.99 | +24.3% |
| 10.3 | large-10000/leaf-customer | 247.03 → 245.89 | +0.5% |
| 10.3 | large-10000/sort-price-customer | 341.92 → 261.99 | +23.4% |
| 10.3 | large-10000/sort-name-customer | 340.67 → 258.12 | +24.2% |
| 10.3 | single-10000/overview-anonymous | 285.35 → 201.44 | +29.4% |
| 10.3 | single-10000/category-anonymous | 284.06 → 198.03 | +30.3% |
| 10.3 | single-10000/category-name-anonymous | 281.49 → 198.12 | +29.6% |
| 10.3 | single-10000/category-price-anonymous | 280.12 → 198.27 | +29.2% |
| 10.3 | single-10000/leaf-anonymous | 283.92 → 196.77 | +30.7% |
| 10.3 | single-10000/sort-price-anonymous | 279.53 → 196.15 | +29.8% |
| 10.3 | single-10000/sort-name-anonymous | 279.35 → 196.71 | +29.6% |
| 10.3 | single-10000/overview-customer | 305.47 → 220.57 | +27.8% |
| 10.3 | single-10000/category-customer | 308.95 → 224.02 | +27.5% |
| 10.3 | single-10000/category-name-customer | 304.57 → 220.75 | +27.5% |
| 10.3 | single-10000/category-price-customer | 302.80 → 219.96 | +27.4% |
| 10.3 | single-10000/leaf-customer | 306.86 → 221.96 | +27.7% |
| 10.3 | single-10000/sort-price-customer | 303.13 → 221.67 | +26.9% |
| 10.3 | single-10000/sort-name-customer | 304.85 → 223.38 | +26.7% |
| 10.3 | browser/overview-anonymous | 11.10 → 10.93 | +1.6% |
| 10.3 | browser/category-anonymous | 11.01 → 10.87 | +1.3% |
| 10.3 | browser/category-name-anonymous | 11.01 → 10.61 | +3.6% |
| 10.3 | browser/category-price-anonymous | 11.08 → 10.77 | +2.8% |
| 10.3 | browser/leaf-anonymous | 10.75 → 10.71 | +0.4% |
| 10.3 | browser/sort-price-anonymous | 11.10 → 10.75 | +3.1% |
| 10.3 | browser/sort-name-anonymous | 11.50 → 11.02 | +4.2% |
| 10.3 | browser/overview-customer | 17.23 → 16.95 | +1.7% |
| 10.3 | browser/category-customer | 17.19 → 17.06 | +0.8% |
| 10.3 | browser/category-name-customer | 17.41 → 16.80 | +3.5% |
| 10.3 | browser/category-price-customer | 17.50 → 16.96 | +3.1% |
| 10.3 | browser/leaf-customer | 16.91 → 17.02 | -0.7% |
| 10.3 | browser/sort-price-customer | 17.90 → 17.75 | +0.9% |
| 10.3 | browser/sort-name-customer | 17.35 → 16.81 | +3.1% |
| 11.4 | matrix/overview-anonymous | 4.58 → 4.64 | -1.4% |
| 11.4 | matrix/category-anonymous | 4.32 → 4.03 | +6.7% |
| 11.4 | matrix/category-name-anonymous | 4.92 → 4.56 | +7.5% |
| 11.4 | matrix/category-price-anonymous | 4.11 → 3.96 | +3.9% |
| 11.4 | matrix/leaf-anonymous | 4.68 → 3.92 | +16.1% |
| 11.4 | matrix/missing-anonymous | 3.78 → 4.49 | -18.9% |
| 11.4 | matrix/invalid-anonymous | 3.88 → 4.21 | -8.5% |
| 11.4 | matrix/search-anonymous | 4.64 → 4.15 | +10.7% |
| 11.4 | matrix/empty-anonymous | 4.02 → 3.79 | +5.7% |
| 11.4 | matrix/sort-price-anonymous | 4.07 → 3.98 | +2.2% |
| 11.4 | matrix/sort-name-anonymous | 4.64 → 4.08 | +12.1% |
| 11.4 | matrix/page-anonymous | 4.05 → 4.04 | +0.3% |
| 11.4 | matrix/overview-customer | 8.52 → 7.91 | +7.2% |
| 11.4 | matrix/category-customer | 9.01 → 9.14 | -1.4% |
| 11.4 | matrix/category-name-customer | 8.32 → 8.88 | -6.8% |
| 11.4 | matrix/category-price-customer | 9.00 → 9.12 | -1.3% |
| 11.4 | matrix/leaf-customer | 7.97 → 9.12 | -14.5% |
| 11.4 | matrix/missing-customer | 7.69 → 9.25 | -20.3% |
| 11.4 | matrix/invalid-customer | 9.06 → 8.62 | +4.9% |
| 11.4 | matrix/search-customer | 7.71 → 7.86 | -1.9% |
| 11.4 | matrix/empty-customer | 7.76 → 9.25 | -19.2% |
| 11.4 | matrix/sort-price-customer | 8.03 → 9.17 | -14.2% |
| 11.4 | matrix/sort-name-customer | 9.14 → 8.98 | +1.7% |
| 11.4 | matrix/page-customer | 9.10 → 9.14 | -0.5% |
| 11.4 | matrix/csrf-customer | 9.24 → 9.06 | +1.9% |
| 11.4 | large-5000/overview-anonymous | 116.00 → 86.84 | +25.1% |
| 11.4 | large-5000/category-anonymous | 115.00 → 84.69 | +26.4% |
| 11.4 | large-5000/category-name-anonymous | 113.82 → 84.78 | +25.5% |
| 11.4 | large-5000/category-price-anonymous | 113.72 → 84.76 | +25.5% |
| 11.4 | large-5000/leaf-anonymous | 83.42 → 83.09 | +0.4% |
| 11.4 | large-5000/sort-price-anonymous | 115.86 → 86.17 | +25.6% |
| 11.4 | large-5000/sort-name-anonymous | 113.39 → 84.29 | +25.7% |
| 11.4 | large-5000/overview-customer | 123.02 → 92.80 | +24.6% |
| 11.4 | large-5000/category-customer | 122.36 → 92.44 | +24.5% |
| 11.4 | large-5000/category-name-customer | 120.20 → 92.85 | +22.8% |
| 11.4 | large-5000/category-price-customer | 121.23 → 91.88 | +24.2% |
| 11.4 | large-5000/leaf-customer | 88.05 → 89.93 | -2.1% |
| 11.4 | large-5000/sort-price-customer | 122.26 → 92.56 | +24.3% |
| 11.4 | large-5000/sort-name-customer | 121.77 → 92.64 | +23.9% |
| 11.4 | large-10000/overview-anonymous | 232.74 → 163.96 | +29.6% |
| 11.4 | large-10000/category-anonymous | 233.32 → 164.25 | +29.6% |
| 11.4 | large-10000/category-name-anonymous | 228.62 → 163.04 | +28.7% |
| 11.4 | large-10000/category-price-anonymous | 228.69 → 163.72 | +28.4% |
| 11.4 | large-10000/leaf-anonymous | 157.21 → 158.48 | -0.8% |
| 11.4 | large-10000/sort-price-anonymous | 230.45 → 164.53 | +28.6% |
| 11.4 | large-10000/sort-name-anonymous | 229.72 → 162.73 | +29.2% |
| 11.4 | large-10000/overview-customer | 247.51 → 179.11 | +27.6% |
| 11.4 | large-10000/category-customer | 246.30 → 177.35 | +28.0% |
| 11.4 | large-10000/category-name-customer | 243.31 → 176.53 | +27.4% |
| 11.4 | large-10000/category-price-customer | 243.72 → 176.44 | +27.6% |
| 11.4 | large-10000/leaf-customer | 169.60 → 170.76 | -0.7% |
| 11.4 | large-10000/sort-price-customer | 243.26 → 178.11 | +26.8% |
| 11.4 | large-10000/sort-name-customer | 243.08 → 176.66 | +27.3% |
| 11.4 | single-10000/overview-anonymous | 203.34 → 134.36 | +33.9% |
| 11.4 | single-10000/category-anonymous | 205.11 → 137.88 | +32.8% |
| 11.4 | single-10000/category-name-anonymous | 202.64 → 135.81 | +33.0% |
| 11.4 | single-10000/category-price-anonymous | 203.16 → 135.62 | +33.2% |
| 11.4 | single-10000/leaf-anonymous | 205.91 → 140.01 | +32.0% |
| 11.4 | single-10000/sort-price-anonymous | 201.85 → 135.56 | +32.8% |
| 11.4 | single-10000/sort-name-anonymous | 199.98 → 133.83 | +33.1% |
| 11.4 | single-10000/overview-customer | 220.34 → 148.93 | +32.4% |
| 11.4 | single-10000/category-customer | 220.85 → 150.53 | +31.8% |
| 11.4 | single-10000/category-name-customer | 214.74 → 149.69 | +30.3% |
| 11.4 | single-10000/category-price-customer | 216.58 → 150.58 | +30.5% |
| 11.4 | single-10000/leaf-customer | 215.67 → 148.09 | +31.3% |
| 11.4 | single-10000/sort-price-customer | 210.96 → 146.37 | +30.6% |
| 11.4 | single-10000/sort-name-customer | 211.12 → 143.86 | +31.9% |
| 11.4 | browser/overview-anonymous | 7.46 → 7.67 | -2.8% |
| 11.4 | browser/category-anonymous | 7.17 → 7.11 | +0.9% |
| 11.4 | browser/category-name-anonymous | 7.30 → 6.98 | +4.4% |
| 11.4 | browser/category-price-anonymous | 7.10 → 6.88 | +3.1% |
| 11.4 | browser/leaf-anonymous | 7.21 → 6.78 | +6.1% |
| 11.4 | browser/sort-price-anonymous | 7.08 → 6.88 | +2.8% |
| 11.4 | browser/sort-name-anonymous | 7.06 → 6.90 | +2.3% |
| 11.4 | browser/overview-customer | 11.31 → 11.03 | +2.4% |
| 11.4 | browser/category-customer | 11.38 → 11.05 | +2.9% |
| 11.4 | browser/category-name-customer | 11.52 → 11.04 | +4.2% |
| 11.4 | browser/category-price-customer | 11.26 → 11.83 | -5.1% |
| 11.4 | browser/leaf-customer | 11.21 → 11.04 | +1.5% |
| 11.4 | browser/sort-price-customer | 11.26 → 11.01 | +2.2% |
| 11.4 | browser/sort-name-customer | 11.27 → 11.07 | +1.8% |

## Skutečný Chrome

120produktů/12uzlůtree, jmenný přehled a cenový kořenový filtr;
desktop1036×703/mobil390×844, cold/warm8případů,176měřených+48zahřívacích
navigací,4shodné full-page pixelové páry. Formuláře/styly/geometrie/overflow/
BootstrapJS stejné, bez console/request chyb. CDP skutečný CSSpayload/hash:
cold232803decodedB, stejný gzip přenos, warm0B. FCP/ready≤20%+20ms.
Light/dark jsou preference profilu; veřejný shell má vlastní světlý vzhled.

| Cesta/profil | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop-overview/light | cold | 60 → 56 | 85.8 → 80.9 |
| eshop-overview/light | warm | 44 → 44 | 65.5 → 61.6 |
| eshop-overview/dark | cold | 56 → 56 | 79.6 → 83.0 |
| eshop-overview/dark | warm | 44 → 44 | 62.9 → 66.2 |
| eshop-category/light | cold | 60 → 56 | 86.2 → 82.2 |
| eshop-category/light | warm | 44 → 44 | 65.1 → 56.6 |
| eshop-category/dark | cold | 52 → 60 | 79.8 → 84.4 |
| eshop-category/dark | warm | 44 → 44 | 66.4 → 61.4 |

[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).
Historická CATSEL lane zachovaná jako replay972577ca; nová CATSORT přebírá
plnou přísnou přejímku. Všechny původní unit/obecné CI zachované.

## Vydání a WIP

[Chráněný deploy44473829467](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44473829467) SUCCESS,
2524testů/60255kontrol, záloha394tabulek/2triggerů.
Přesný testovaný/sloučený strom `3ff868f3efecb71f75a3320e23d0be37fbb8aa65`. Config zachovaný bez doplnění,
pending migrace[], VS/zdravotní index shodné; aktivace/HTTP200/úklid PASS,
přímý runner HTTP200, UAT/importfalse a import přeskočený.
Živé anonymní GET katalogu, name/price řazení a skutečného veřejného filtru:
celé normalizované HTML/hlavičky shodné, administrace guard a canonical CSS stejné.
[Před](production-before.json), [po](production-after.json), [receipt](release.json).
Produkční výkonový profil a přihlášené/lidské UAT NOT RUN. Žádné skutečné
produkty/objednávky/platby/role/rozesílky ověřením neměněné. Finální doklady
vlastním docs PR do main bez dalšího deploye.

Původní worktree/HEAD d1221f3594a965096d98da5e86a27097f66cd380, WIP
kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5
zachované. Souběžná práce ani staré prototypy nepřebírané.
[Integrita](artifact-integrity.json): SHA-256 skutečných Git blobů.
