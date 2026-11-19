# P11-CATSORT — průběžné ověření

[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catsort--řazení-katalogu-19-11-2026).
Lokální SQLite/PHP8.2, celý Select,2warmup+7střídavých párů,24profilů,
canonical DTO s permutovanými ASCII/českými názvy a cenami. Celý stav/hash
shodný. [Data](local-probe.json), [sonda](local-probe.php.txt), [prototyp](local-prototype.txt).
Reprodukce: soubory sondy/prototypu do output/catalog-sort-probe.php.txt
a output/catalog-sort-block.txt v izolovaném checkoutu base4f43f793.
Tohle není MariaDB/HTTP přejímka ani produkční výkonový profil.

| Produkty/uzly | Názvy/režim | Před ms | Po ms | Zisk |
| --- | --- | ---: | ---: | ---: |
| 12/6 | ascii/order | 0.19 | 0.15 | 22.7% |
| 12/6 | ascii/name | 0.21 | 0.15 | 28.5% |
| 12/6 | ascii/price | 0.19 | 0.15 | 18.2% |
| 12/6 | czech/order | 0.25 | 0.17 | 33.8% |
| 12/6 | czech/name | 0.28 | 0.16 | 41.5% |
| 12/6 | czech/price | 0.25 | 0.17 | 29.7% |
| 1000/100 | ascii/order | 26.53 | 6.13 | 76.9% |
| 1000/100 | ascii/name | 32.00 | 10.01 | 68.7% |
| 1000/100 | ascii/price | 48.74 | 12.79 | 73.8% |
| 1000/100 | czech/order | 52.05 | 10.08 | 80.6% |
| 1000/100 | czech/name | 49.62 | 10.30 | 79.2% |
| 1000/100 | czech/price | 77.67 | 17.69 | 77.2% |
| 5000/500 | ascii/order | 145.55 | 41.09 | 71.8% |
| 5000/500 | ascii/name | 206.98 | 51.58 | 75.1% |
| 5000/500 | ascii/price | 146.10 | 37.87 | 74.1% |
| 5000/500 | czech/order | 444.25 | 63.17 | 85.8% |
| 5000/500 | czech/name | 227.18 | 45.26 | 80.1% |
| 5000/500 | czech/price | 234.33 | 44.89 | 80.8% |
| 10000/1000 | ascii/order | 352.99 | 86.29 | 75.6% |
| 10000/1000 | ascii/name | 453.77 | 132.96 | 70.7% |
| 10000/1000 | ascii/price | 605.17 | 205.81 | 66.0% |
| 10000/1000 | czech/order | 1217.87 | 307.67 | 74.7% |
| 10000/1000 | czech/name | 937.90 | 100.89 | 89.2% |
| 10000/1000 | czech/price | 586.70 | 125.02 | 78.7% |

Nativní přejímka používá původní krátké ASCII názvy/DTO fixture, žádný
umělý dlouhý text. Před výsledkem pevné cíle5000/500 a10000/1000 tree,
overview/root order/name celý Select i celé HTTP anonym/zákazník≥20%
na obou MariaDB. Price také parita/měření, ostatní≤20%+5ms/+20msHTTP,
ChromeFCP/ready≤20%+20ms. 30native profilů,82HTTP scénářů/DB,
355checksumů/8Chrome případů/176+48navigací/4pixelpáry. Fixture ověří
skutečné PageRead aktivní/filtered produkty, uzly i potomky před časováním.
Původní sort callback pro nekanonické typy a0/1produkty, přesné typy,
stabilní ties/duplicity/numeric-string/pořadí/warnings/exceptions.
Source contract byte-lock celého zbytku PageRead/shop_category.php.
SQL3outside/6insideTX a původní transakční sekvence stejné.
Native/HTTP/Chrome/CI/merge/deploy NOT RUN; přihlášené produkční UAT NOT RUN.

Lokální implementace:5testů/621kontrol, širší katalog38/4815, plný PHPStan a styl8PHP/0chyb, SourceContract a Composer/syntaxe PASS. [Doklad](local-checks.json). Chrome jmenný přehled/cenový filtr, native2DB/82HTTP/CI/merge/deploy ještě čekají.
