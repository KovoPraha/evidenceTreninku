# P11-CATSEL — množina cest při výběru kategorie

18. 11. 2026 Europe/Prague. **NASAZENO #498/a752cd96d1a9cd2dee47498974178950cb156fac,13CI PASS.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catsel--výběr-katalogu-po-catread-18-11-2026),
[PR#498](https://github.com/KovoPraha/evidenceTreninku/pull/498),
[párový běh44401578117](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44401578117).
Testovaný head `972577ca6b70ed1bd3261173b72d9e84a3584737`, immutable baseline `4db60635f1d05f620fef9d9081be297c3bbe877b`.

## Změna a přesná shoda

Jen blok category filtru v shopCatalogPageSelect: pro pole string kategorií
jednorázová množina selectedPaths a membership průchod kategorií místo
opakovaného array_intersect. Žádná nová normalizace/trim/casefold, pořadí
produktů i duplicity zachované. Pro jiné typy kategorií úplně původní
array_filter/array_intersect callback se stejnými výjimkami/varováními.
Prázdné produkty původní cestou, validní prázdný výběr žádné produkty.
PHPStan odhalil nadbytečný is_array(product) u list<array> kontraktu;
odstraněna jen tato kontrola, nikoli kontrola kategorií/fallback nebo pravidlo
analýzy. Žádné potlačení, změna baseline ani rozšíření parametrů.

Celý prefix/suffix PageRead a celý shop_category.php byteově stejný;
source contract připíná přesný blok i frozen celý Select z aktuálního main.
Native před je strict frozen Select, celé HTTP fyzický původní modul.
Řazení/menu/CATTOP/prodejnost/ceny/writery/cache/limity beze změny,
outsideTX3SQL, insideTX původní6SQL. [Lokální kontroly](local-checks.json).
Testované doslovné cesty/mezery/Unicode/wildcards, numericky vypadající
cesty, duplicity ID/záznamů, všechny sorty/stránky/searchMatches, prázdné/
chybějící/neplatné kategorie, fallback null/string/Traversable/číslo/array/
Stringable, přesné kódy/texty/počty varování a třídy/messages výjimek,
čerstvé metadata, rollback, jiné spojení i celá transakční SQL sekvence.

## Místní volba kandidáta

SQLite/PHP8.2.12, celý Select,2warmup+7střídavých trojic na canonical DTO,
celé stavy/hash přesně shodné. Zvolen membership pro největší absolutní
zisk širokého filtrovaného stromu; řadicí klíče zůstávají samostatný koncept.
Nezměněné přehledy mají na stanici výrazné výkyvy a jsou také zveřejněné.
Toto není MariaDB přejímka ani produkční profil. [Data](local-probe.json),
[sonda](local-probe.php.txt), [prototyp](local-prototype.txt). Reprodukce:
kopie sondy do output/catalog-selection-probe.php.txt v odděleném checkoutu
base4db60635, nikoli aktuálního optimalizovaného main. Produkční guard/fallback
jsou navíc a ověřuje je finální nativní i HTTP přejímka, ne jen tato sonda.

| Produkty/uzly/režim | Původní ms | Membership ms | Řadicí klíče ms |
| --- | ---: | ---: | ---: |
| 12/6/přehled | 0.58 | 0.52 | 0.27 |
| 12/6/filtr | 0.59 | 0.60 | 0.34 |
| 1000/100/přehled | 113.09 | 66.39 | 14.68 |
| 1000/100/filtr | 114.62 | 116.79 | 76.24 |
| 2500/500/přehled | 391.94 | 357.86 | 76.61 |
| 2500/500/filtr | 744.99 | 243.35 | 557.90 |
| 5000/1000/přehled | 496.34 | 451.69 | 70.12 |
| 5000/1000/filtr | 2740.16 | 706.39 | 2341.34 |
| 10000/1/přehled | 1023.75 | 1087.70 | 129.15 |
| 10000/1/filtr | 1672.26 | 1179.24 | 149.81 |

## Nativní celý Select na MariaDB

9profilů/DB:12/6flat root,1000/100tree root,5000/500tree root,
10000/1000tree root,10000/1flat root,10000/1000tree overview,
1000/100tree leaf,1000/100tree missing a0/0empty. Skutečný kořen0 má všechny
potomky, selektivní list je jiný případ. Každý vzorek čerstvý PHP proces,
2warmup+11střídavých párů, oba algoritmy zahřáté včetně strict frozen
kompilace, querycacheOFF. Celý stav/typy/pořadí/serializedhash shodné,
3SQL před/po,3checksumy i úklid shodné. Paměť je přírůstek PHPpeak včetně
mysqlnd, nikoli DB/OS. Předem cíle5000/500 a10000/1000≥20%, ostatní≤20%+5ms,
beze změny tolerance. Všechny směry a paměť:

| MariaDB | Produkty/uzly/tvar/výběr | Celý Select ms před → po | PHPpeak MiB před → po |
| --- | --- | ---: | ---: |
| 10.3 | 12/6/flat/root | 1.10 → 1.09 | 0.041 → 0.041 |
| 10.3 | 1000/100/tree/root | 14.99 → 9.06 | 0.356 → 0.356 |
| 10.3 | 5000/500/tree/root | 255.27 → 54.87 | 1.712 → 1.712 |
| 10.3 | 10000/1000/tree/root | 1015.83 → 118.23 | 3.446 → 3.446 |
| 10.3 | 10000/1/flat/root | 103.11 → 104.98 | 1.138 → 1.138 |
| 10.3 | 10000/1000/tree/overview | 117.18 → 117.35 | 3.446 → 3.446 |
| 10.3 | 1000/100/tree/leaf | 3.83 → 3.81 | 0.356 → 0.356 |
| 10.3 | 1000/100/tree/missing | 3.71 → 3.64 | 0.356 → 0.356 |
| 10.3 | 0/0/flat/root | 1.02 → 1.00 | 0.037 → 0.037 |
| 11.4 | 12/6/flat/root | 0.49 → 0.50 | 0.041 → 0.041 |
| 11.4 | 1000/100/tree/root | 8.19 → 4.95 | 0.356 → 0.356 |
| 11.4 | 5000/500/tree/root | 135.91 → 30.88 | 1.712 → 1.712 |
| 11.4 | 10000/1000/tree/root | 523.55 → 66.97 | 3.446 → 3.446 |
| 11.4 | 10000/1/flat/root | 55.19 → 54.12 | 1.138 → 1.138 |
| 11.4 | 10000/1000/tree/overview | 64.26 → 65.33 | 3.446 → 3.446 |
| 11.4 | 1000/100/tree/leaf | 1.96 → 1.95 | 0.356 → 0.356 |
| 11.4 | 1000/100/tree/missing | 1.87 → 1.85 | 0.356 → 0.356 |
| 11.4 | 0/0/flat/root | 0.43 → 0.44 | 0.037 → 0.037 |

## Celé Apache/PHP HTTP

Vlastní úplně migrovaná DB355tabulek, skutečné publikované syntetické zboží/
varianty, fyzická baseline kopie.22matrix scénářů při12produktech/6uzlech:
anonym/zákazník,přehled/kořen/list/missing/invalid/hledání/prázdné/řazení/
stránka a odmítnutí nesprávného CSRF. Pak6scénářů (overview/root/leaf×obě
role) pro5000/500tree,10000/1000tree,10000/1flat a120/12tree. Celkem46/DB,
4warmup+17střídavých párů pozitivů/1pár negativů. Fixture před časováním
skutečně volá PageRead a ověřuje aktivní/filtered počet produktů, uzly a
potomky; tyto objemy jsou v HTTP datech. Není měřen jediný list místo stromu.
355checksumů po každé fázi i Chrome, neplatný POST nic nepřidá, objednávky0.
Celé HTML/bezpečnostní hlavičky/raw délky stejné. Normalizovány jen nonce,
64hexCSRF/32hexcheckout_key a explicitní loopback origin; odvozený CSP hash
nejprve ověřen proti skutečnému handleru. Žádné PHPchyby/ignorované CSPchyby.
Každý category5000/500 a10000/1000≥20%, ostatní≤20%+20ms, beze změny brány.

| MariaDB | Fáze/scénář | Celé HTTP ms před → po | Zrychlení |
| --- | --- | ---: | ---: |
| 10.3 | matrix/overview-anonymous | 8.30 → 7.87 | +5.2% |
| 10.3 | matrix/category-anonymous | 7.73 → 7.79 | -0.7% |
| 10.3 | matrix/leaf-anonymous | 7.79 → 7.80 | -0.1% |
| 10.3 | matrix/missing-anonymous | 8.04 → 8.09 | -0.6% |
| 10.3 | matrix/invalid-anonymous | 7.94 → 7.93 | +0.1% |
| 10.3 | matrix/search-anonymous | 8.02 → 7.95 | +0.8% |
| 10.3 | matrix/empty-anonymous | 7.67 → 7.51 | +2.0% |
| 10.3 | matrix/sort-price-anonymous | 7.93 → 7.98 | -0.6% |
| 10.3 | matrix/sort-name-anonymous | 8.85 → 9.10 | -2.9% |
| 10.3 | matrix/page-anonymous | 8.54 → 8.60 | -0.7% |
| 10.3 | matrix/overview-customer | 16.65 → 16.78 | -0.8% |
| 10.3 | matrix/category-customer | 15.45 → 15.53 | -0.5% |
| 10.3 | matrix/leaf-customer | 15.50 → 15.34 | +1.0% |
| 10.3 | matrix/missing-customer | 15.72 → 15.38 | +2.2% |
| 10.3 | matrix/invalid-customer | 15.41 → 15.36 | +0.3% |
| 10.3 | matrix/search-customer | 15.45 → 15.41 | +0.3% |
| 10.3 | matrix/empty-customer | 15.47 → 15.46 | +0.0% |
| 10.3 | matrix/sort-price-customer | 15.87 → 15.69 | +1.1% |
| 10.3 | matrix/sort-name-customer | 15.98 → 16.05 | -0.4% |
| 10.3 | matrix/page-customer | 16.08 → 15.90 | +1.1% |
| 10.3 | matrix/csrf-customer | 15.80 → 15.83 | -0.2% |
| 10.3 | large-5000/overview-anonymous | 201.86 → 201.01 | +0.4% |
| 10.3 | large-5000/category-anonymous | 396.41 → 201.58 | +49.1% |
| 10.3 | large-5000/leaf-anonymous | 146.91 → 148.66 | -1.2% |
| 10.3 | large-5000/overview-customer | 217.48 → 219.04 | -0.7% |
| 10.3 | large-5000/category-customer | 417.78 → 216.46 | +48.2% |
| 10.3 | large-5000/leaf-customer | 161.39 → 162.27 | -0.5% |
| 10.3 | large-10000/overview-anonymous | 419.40 → 419.56 | -0.0% |
| 10.3 | large-10000/category-anonymous | 1321.46 → 415.94 | +68.5% |
| 10.3 | large-10000/leaf-anonymous | 296.65 → 293.86 | +0.9% |
| 10.3 | large-10000/overview-customer | 450.68 → 451.11 | -0.1% |
| 10.3 | large-10000/category-customer | 1348.79 → 444.99 | +67.0% |
| 10.3 | large-10000/leaf-customer | 313.84 → 312.23 | +0.5% |
| 10.3 | single-10000/overview-anonymous | 373.00 → 371.51 | +0.4% |
| 10.3 | single-10000/category-anonymous | 373.99 → 373.33 | +0.2% |
| 10.3 | single-10000/leaf-anonymous | 366.27 → 368.73 | -0.7% |
| 10.3 | single-10000/overview-customer | 391.19 → 386.44 | +1.2% |
| 10.3 | single-10000/category-customer | 395.04 → 393.29 | +0.4% |
| 10.3 | single-10000/leaf-customer | 387.16 → 383.96 | +0.8% |
| 10.3 | browser/overview-anonymous | 14.34 → 14.27 | +0.5% |
| 10.3 | browser/category-anonymous | 14.24 → 14.37 | -0.9% |
| 10.3 | browser/leaf-anonymous | 13.57 → 13.63 | -0.5% |
| 10.3 | browser/overview-customer | 22.27 → 22.37 | -0.4% |
| 10.3 | browser/category-customer | 22.54 → 22.59 | -0.2% |
| 10.3 | browser/leaf-customer | 21.46 → 21.70 | -1.1% |
| 11.4 | matrix/overview-anonymous | 4.92 → 4.70 | +4.5% |
| 11.4 | matrix/category-anonymous | 4.97 → 4.65 | +6.4% |
| 11.4 | matrix/leaf-anonymous | 4.54 → 4.57 | -0.7% |
| 11.4 | matrix/missing-anonymous | 4.53 → 4.31 | +4.9% |
| 11.4 | matrix/invalid-anonymous | 4.33 → 4.37 | -0.8% |
| 11.4 | matrix/search-anonymous | 4.56 → 4.57 | -0.2% |
| 11.4 | matrix/empty-anonymous | 4.24 → 4.27 | -0.8% |
| 11.4 | matrix/sort-price-anonymous | 4.86 → 4.86 | -0.0% |
| 11.4 | matrix/sort-name-anonymous | 4.75 → 4.79 | -0.8% |
| 11.4 | matrix/page-anonymous | 4.75 → 4.79 | -0.8% |
| 11.4 | matrix/overview-customer | 9.84 → 9.70 | +1.4% |
| 11.4 | matrix/category-customer | 10.07 → 9.86 | +2.1% |
| 11.4 | matrix/leaf-customer | 9.17 → 9.12 | +0.5% |
| 11.4 | matrix/missing-customer | 9.30 → 9.38 | -0.9% |
| 11.4 | matrix/invalid-customer | 9.05 → 9.11 | -0.6% |
| 11.4 | matrix/search-customer | 9.31 → 9.30 | +0.2% |
| 11.4 | matrix/empty-customer | 9.33 → 9.43 | -1.1% |
| 11.4 | matrix/sort-price-customer | 9.77 → 9.82 | -0.4% |
| 11.4 | matrix/sort-name-customer | 9.74 → 9.70 | +0.4% |
| 11.4 | matrix/page-customer | 9.09 → 8.97 | +1.2% |
| 11.4 | matrix/csrf-customer | 9.55 → 9.78 | -2.4% |
| 11.4 | large-5000/overview-anonymous | 109.26 → 107.70 | +1.4% |
| 11.4 | large-5000/category-anonymous | 203.47 → 105.81 | +48.0% |
| 11.4 | large-5000/leaf-anonymous | 72.41 → 73.06 | -0.9% |
| 11.4 | large-5000/overview-customer | 116.14 → 118.94 | -2.4% |
| 11.4 | large-5000/category-customer | 211.59 → 115.83 | +45.3% |
| 11.4 | large-5000/leaf-customer | 84.77 → 82.38 | +2.8% |
| 11.4 | large-10000/overview-anonymous | 217.14 → 216.21 | +0.4% |
| 11.4 | large-10000/category-anonymous | 632.64 → 206.85 | +67.3% |
| 11.4 | large-10000/leaf-anonymous | 142.24 → 141.29 | +0.7% |
| 11.4 | large-10000/overview-customer | 234.13 → 233.66 | +0.2% |
| 11.4 | large-10000/category-customer | 667.06 → 232.09 | +65.2% |
| 11.4 | large-10000/leaf-customer | 164.85 → 162.93 | +1.2% |
| 11.4 | single-10000/overview-anonymous | 184.18 → 183.41 | +0.4% |
| 11.4 | single-10000/category-anonymous | 186.46 → 185.56 | +0.5% |
| 11.4 | single-10000/leaf-anonymous | 183.99 → 185.94 | -1.1% |
| 11.4 | single-10000/overview-customer | 206.06 → 208.64 | -1.3% |
| 11.4 | single-10000/category-customer | 213.15 → 209.47 | +1.7% |
| 11.4 | single-10000/leaf-customer | 205.32 → 205.76 | -0.2% |
| 11.4 | browser/overview-anonymous | 8.15 → 7.80 | +4.3% |
| 11.4 | browser/category-anonymous | 7.24 → 7.18 | +0.8% |
| 11.4 | browser/leaf-anonymous | 6.97 → 7.05 | -1.1% |
| 11.4 | browser/overview-customer | 11.95 → 12.30 | -3.0% |
| 11.4 | browser/category-customer | 12.53 → 12.46 | +0.5% |
| 11.4 | browser/leaf-customer | 12.83 → 12.90 | -0.5% |

## Skutečný Chrome

120produktů/12uzlůtree, přehled a filtr kořene0; desktop1036×703/mobil390×844,
cold/warm8případů,176měřených+48zahřívacích navigací,4shodné full-page
pixelové páry. Formuláře/styly/geometrie/overflow/BootstrapJS stejné,
bez console/request chyb. CDP skutečný CSSpayload/hash: cold232803decodedB,
stejný gzip přenos, warm0B. FCP/ready≤20%+20ms. Všechny směry:

| Cesta/profil | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop-overview/light | cold | 52 → 56 | 78.8 → 79.2 |
| eshop-overview/light | warm | 40 → 40 | 63.2 → 61.2 |
| eshop-overview/dark | cold | 52 → 56 | 78.1 → 79.4 |
| eshop-overview/dark | warm | 40 → 40 | 64.6 → 58.8 |
| eshop-category/light | cold | 56 → 56 | 79.5 → 82.1 |
| eshop-category/light | warm | 44 → 44 | 63.5 → 62.3 |
| eshop-category/dark | cold | 56 → 56 | 80.2 → 82.7 |
| eshop-category/dark | warm | 40 → 40 | 58.8 → 57.8 |

Light/dark jsou preference profilu; veřejný shell má vlastní světlý vzhled.
[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).
Historická CATREAD lane zachovaná pro replayfc4efb64; starý source lock
se neoslabuje. Aktuální2CATSEL joby přebírají přísnou paritu/HTTP/CSP/Chrome.
CATTOP/CATLABEL/CATMENU replay i všechny původní unit/obecné CI zachované.

## Vydání a WIP

[Chráněný deploy44404265875](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44404265875) SUCCESS,
2518testů/59580kontrol, záloha394tabulek/2triggerů.
Přesný testovaný/sloučený strom `08e81c9d597761b296a31093c7976f0806eaa936`. Config zachovaný bez doplnění,
pending migrace[], VS/zdravotní index shodné; aktivace/HTTP200/úklid PASS,
přímý runner HTTP200, UAT/importfalse a import přeskočený.
Živé anonymní GET katalogu i skutečného veřejného filtru: celé normalizované
HTML/hlavičky shodné, administrace guard a canonical CSS stejné.
[Před](production-before.json), [po](production-after.json), [receipt](release.json).
Produkční výkonový profil a přihlášené/lidské UAT NOT RUN. Žádné skutečné
produkty/objednávky/platby/role/rozesílky ověřením neměněné. Finální doklady
vlastním docs PR do main bez dalšího deploye.

Původní worktree/HEAD `d1221f3594a965096d98da5e86a27097f66cd380`, WIP
`kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`
zachované. Souběžná práce ani staré prototypy nepřebírané.
[Integrita](artifact-integrity.json): SHA-256 skutečných Git blobů.
