# P11-CATLABEL — skládání popisků kategorií

17. 11. 2026 Europe/Prague. **NASAZENO #492/8347ff50e65ab33f18c9383bfbc462f2d10d5c99, 13 CI PASS.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catlabel--skládání-popisků-kategorií-17-11-2026),
[PR #492](https://github.com/KovoPraha/evidenceTreninku/pull/492).
Testovaný head `28496cca7f6ff97b37e91aac7f2587dd9516de1d`, immutable baseline `30386ddb29ceed99190a18ed9e792ff02111b139`,
[párový běh 44188289970](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44188289970).

## Změna a přesná shoda

Pouze shopCategoryMenuLabel: append místo array_unshift a jediný
array_reverse před implode. Stejná posloupnost rodičů, visited a ukončení,
včetně cyklů podle počátečního bodu a neřetězcového/prázdného rodiče.
Popisky, mezery/Unicode/oddělovač, typy/pořadí a celé menu přesně stejné.
Žádný limit výsledku, cache, změna signatury, cen, produktů, filtrů,
oprávnění či writerů. 3 SQL/volání menu zůstávají.

Source contract přebírá frozen popisek i menu z baseline30386ddb a ověřuje
byteově stejný celý prefix/suffix mimo MenuLabel. Povoluje přesně dvě
uvedené náhrady uvnitř funkce. Nativní before celé menu volá frozen starý
popisek, nikoli aktuální helper. Celé HTTP používá přímo původní modul.
Lokálně27 testů/204 kontrol PASS: chybějící/prázdné cesty, Unicode/mezery,
číselné klíče, jiné typy rodičů, cykly/sirotci/skrytí/virtuální uzly,
flat/tree/chain/mixed celé menu, izolace spojení/změn/rollbacku.
PHPStan/styl/source kontrakt/odkazy PASS.

## Volba kandidáta podle místní diagnózy

SQLite/PHP8.2.12, shopCategoryNodes+celé menu (callsite), 2 zahřívací+11
střídavých trojic. Porovnání s odstraněním duplicitního čtení uzlů:

| Tvar / kategorie | Původní ms | Popisky ms | Sdílené uzly ms |
| --- | ---: | ---: | ---: |
| flat /30 | 1,49 | 1,53 | 1,04 |
| flat /1000 | 68,59 | 64,72 | 54,01 |
| tree /1000 | 69,67 | 68,59 | 47,45 |
| chain /250 | 181,02 | 106,24 | 182,22 |
| chain /500 | 1247,80 | 692,39 | 1490,94 |

Zvoleny popisky: největší doložený hluboký zisk při minimálním rozsahu,
sdílení uzlů zůstává KONCEPT k vlastnímu nativnímu profilu. Časy obsahují
i opačné směry. [Vzorky](local-probe.json), [prototyp](local-probe.php.txt)
(kopie do output/category-label-probe.php.txt a CLI php v odděleném checkoutu
baseline30386ddb). Jde o původní sondu před aplikací změny; nad již
optimalizovaným modulem z aktuálního main se tato sonda nespouští.
Toto není MariaDB ani produkční profil.

## Nativní menu na MariaDB

9 tvarů/velikostí na každé DB, jeden přiřazený produkt ve velkém stromu.
Každý vzorek čerstvý PHP proces, oba algoritmy zahřáté včetně kompilace
frozen oracle, 2 warmup+11 střídavých párů, query cacheOFF. Celé menu/typy/
hash shodné, checksumy3 tabulek stejné, SQL budget3 a úklid schématu PASS.
Before používá frozen CLI callable; celé HTTP níže má přímo starý modul.
Paměť je přírůstek PHP peak včetně mysqlnd bufferu, nikoli DB/OS.
Předem určeno chain250/500 ≥20% zisku; ostatní ≤20%+5ms, beze změny brány.

| MariaDB | Tvar / kategorie | Medián ms před → po | PHP peak MiB před → po |
| --- | --- | ---: | ---: |
| 10.3 | flat / 30 | 0.67 → 0.49 | 0.052 → 0.052 |
| 10.3 | flat / 500 | 2.42 → 2.29 | 0.483 → 0.483 |
| 10.3 | flat / 1000 | 4.57 → 4.54 | 0.961 → 0.961 |
| 10.3 | tree / 30 | 0.75 → 0.69 | 0.054 → 0.054 |
| 10.3 | tree / 500 | 2.58 → 2.57 | 0.583 → 0.583 |
| 10.3 | tree / 1000 | 4.41 → 4.51 | 1.158 → 1.158 |
| 10.3 | chain / 250 | 15.39 → 7.79 | 2.992 → 2.999 |
| 10.3 | chain / 500 | 77.18 → 27.98 | 9.786 → 9.798 |
| 10.3 | mixed / 80 | 0.83 → 0.88 | 0.145 → 0.145 |
| 11.4 | flat / 30 | 0.88 → 0.97 | 0.052 → 0.052 |
| 11.4 | flat / 500 | 3.29 → 3.29 | 0.483 → 0.483 |
| 11.4 | flat / 1000 | 5.90 → 5.92 | 0.961 → 0.961 |
| 11.4 | tree / 30 | 0.99 → 0.93 | 0.054 → 0.054 |
| 11.4 | tree / 500 | 3.41 → 3.42 | 0.583 → 0.583 |
| 11.4 | tree / 1000 | 6.23 → 6.14 | 1.158 → 1.158 |
| 11.4 | chain / 250 | 20.42 → 10.65 | 2.992 → 2.999 |
| 11.4 | chain / 500 | 106.39 → 38.02 | 9.786 → 9.798 |
| 11.4 | mixed / 80 | 1.29 → 1.24 | 0.145 → 0.145 |

## Celé Apache/PHP HTTP

Úplně migrovaná vlastní DB355tabulek, fyzicky oddělené baseline booking/
kořenové PHP/includes. 20 scénářů při30 kategoriích: anonym/zákazník,
přehled/kategorie/chybějící/neplatná volba, hledání/prázdné, řazení/stránkování
a odmítnutí nesprávného CSRF. Další4 sady po4 scénářích (přehled/filtr ×
oba kontexty): flat1000, chain250, chain500, tree1000. Celkem36 scénářů/DB,
4 warmup+17 střídavých párů pozitivů,1 pár negativů. Skutečné Apache/PHP
bez request mocků, stejná data před/po a355 checksumů po každé fázi i Chrome.
Neplatný POST action=add nic nepřidá do košíku, žádné testovací objednávky.
Celé HTML/hlavičky/raw velikosti stejné, normalizovány jen request nonce,
64hex CSRF/32hex checkout_key a explicitní testovací origin. Z něj odvozený
CSP hash je nejprve ověřen proti skutečnému handleru a až potom přeložen
na tentýž handler se srovnávacím originem. Všechny cesty, atributy a ostatní
CSP zůstávají součástí porovnání, žádné ignorované bezpečnostní chyby.
Každý přehled chain250/500 musí získat ≥20%, ostatní ≤20%+20ms.

| MariaDB | Fáze / scénář | Celé HTTP ms před → po |
| --- | --- | ---: |
| 10.3 | matrix / overview-anonymous | 10.40 → 10.23 |
| 10.3 | matrix / category-anonymous | 9.76 → 8.54 |
| 10.3 | matrix / missing-anonymous | 8.50 → 9.97 |
| 10.3 | matrix / invalid-anonymous | 8.65 → 10.00 |
| 10.3 | matrix / search-anonymous | 8.53 → 9.94 |
| 10.3 | matrix / empty-anonymous | 9.71 → 9.69 |
| 10.3 | matrix / sort-price-anonymous | 8.67 → 8.61 |
| 10.3 | matrix / sort-name-anonymous | 9.99 → 10.00 |
| 10.3 | matrix / page-anonymous | 10.06 → 8.61 |
| 10.3 | matrix / overview-customer | 14.96 → 14.56 |
| 10.3 | matrix / category-customer | 13.20 → 14.91 |
| 10.3 | matrix / missing-customer | 14.91 → 15.25 |
| 10.3 | matrix / invalid-customer | 13.47 → 13.60 |
| 10.3 | matrix / search-customer | 15.01 → 14.24 |
| 10.3 | matrix / empty-customer | 13.91 → 13.22 |
| 10.3 | matrix / sort-price-customer | 13.03 → 12.94 |
| 10.3 | matrix / sort-name-customer | 13.64 → 13.17 |
| 10.3 | matrix / page-customer | 13.70 → 13.27 |
| 10.3 | matrix / csrf-customer | 13.27 → 14.50 |
| 10.3 | flat-1000 / overview-anonymous | 14.51 → 14.28 |
| 10.3 | flat-1000 / category-anonymous | 14.41 → 13.39 |
| 10.3 | flat-1000 / overview-customer | 17.92 → 19.51 |
| 10.3 | flat-1000 / category-customer | 19.20 → 17.99 |
| 10.3 | chain-250 / overview-anonymous | 22.52 → 15.55 |
| 10.3 | chain-250 / category-anonymous | 23.42 → 16.12 |
| 10.3 | chain-250 / overview-customer | 27.44 → 19.81 |
| 10.3 | chain-250 / category-customer | 27.01 → 20.85 |
| 10.3 | chain-500 / overview-anonymous | 81.05 → 32.30 |
| 10.3 | chain-500 / category-anonymous | 83.50 → 36.37 |
| 10.3 | chain-500 / overview-customer | 85.20 → 36.16 |
| 10.3 | chain-500 / category-customer | 88.79 → 40.31 |
| 10.3 | tree-1000 / overview-anonymous | 15.34 → 14.43 |
| 10.3 | tree-1000 / category-anonymous | 14.31 → 15.58 |
| 10.3 | tree-1000 / overview-customer | 19.42 → 19.94 |
| 10.3 | tree-1000 / category-customer | 20.30 → 18.79 |
| 11.4 | matrix / overview-anonymous | 12.75 → 12.42 |
| 11.4 | matrix / category-anonymous | 12.48 → 12.56 |
| 11.4 | matrix / missing-anonymous | 12.46 → 12.49 |
| 11.4 | matrix / invalid-anonymous | 12.31 → 12.35 |
| 11.4 | matrix / search-anonymous | 12.60 → 12.84 |
| 11.4 | matrix / empty-anonymous | 12.59 → 12.51 |
| 11.4 | matrix / sort-price-anonymous | 12.29 → 12.58 |
| 11.4 | matrix / sort-name-anonymous | 12.46 → 12.34 |
| 11.4 | matrix / page-anonymous | 12.38 → 12.56 |
| 11.4 | matrix / overview-customer | 18.36 → 18.68 |
| 11.4 | matrix / category-customer | 18.63 → 18.61 |
| 11.4 | matrix / missing-customer | 18.67 → 18.17 |
| 11.4 | matrix / invalid-customer | 18.04 → 18.23 |
| 11.4 | matrix / search-customer | 18.65 → 18.51 |
| 11.4 | matrix / empty-customer | 18.79 → 18.64 |
| 11.4 | matrix / sort-price-customer | 18.35 → 18.80 |
| 11.4 | matrix / sort-name-customer | 18.59 → 18.37 |
| 11.4 | matrix / page-customer | 18.51 → 18.80 |
| 11.4 | matrix / csrf-customer | 18.82 → 18.76 |
| 11.4 | flat-1000 / overview-anonymous | 18.98 → 19.09 |
| 11.4 | flat-1000 / category-anonymous | 18.98 → 19.00 |
| 11.4 | flat-1000 / overview-customer | 24.81 → 24.65 |
| 11.4 | flat-1000 / category-customer | 25.09 → 25.00 |
| 11.4 | chain-250 / overview-anonymous | 31.15 → 21.39 |
| 11.4 | chain-250 / category-anonymous | 31.93 → 22.35 |
| 11.4 | chain-250 / overview-customer | 37.04 → 27.43 |
| 11.4 | chain-250 / category-customer | 38.02 → 28.15 |
| 11.4 | chain-500 / overview-anonymous | 110.71 → 43.28 |
| 11.4 | chain-500 / category-anonymous | 114.24 → 46.68 |
| 11.4 | chain-500 / overview-customer | 117.16 → 48.28 |
| 11.4 | chain-500 / category-customer | 120.82 → 52.51 |
| 11.4 | tree-1000 / overview-anonymous | 20.14 → 20.59 |
| 11.4 | tree-1000 / category-anonymous | 20.15 → 20.29 |
| 11.4 | tree-1000 / overview-customer | 25.93 → 26.09 |
| 11.4 | tree-1000 / category-customer | 26.29 → 26.64 |

## Skutečný Chrome

Při tree1000 katalog a filtr, desktop1036×703 a mobil390×844, cold/warm:
8 případů,176 měřených+48 zahřívacích navigací,4 identické full-page pixelové
páry. Shodné formuláře, computed styles/geometrie/overflow a Bootstrap JS,
bez chyb konzole/požadavků. CDP skutečný CSS payload/hash, cold232803decodedB
a stejný gzip přenos, warm0→0B. FCP/ready gate20%+20ms zachovaný; všechny
směry níže. Light/dark jsou nastavené localStorage preference, veřejný shell
má vlastní světlý vzhled. Snímky také vizuálně zkontrolované desktop/mobil.
Tyto časy nejsou produkční SLA/UAT.

| Cesta / profil | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop-overview / light | cold | 84 → 80 | 113.2 → 109.5 |
| eshop-overview / light | warm | 64 → 64 | 73.7 → 84.4 |
| eshop-overview / dark | cold | 76 → 76 | 106.8 → 109.9 |
| eshop-overview / dark | warm | 60 → 64 | 83.1 → 81.4 |
| eshop-category / light | cold | 84 → 80 | 109.6 → 113.6 |
| eshop-category / light | warm | 64 → 68 | 80.3 → 85.1 |
| eshop-category / dark | cold | 80 → 80 | 107.9 → 112.8 |
| eshop-category / dark | warm | 64 → 64 | 82.5 → 83.3 |

[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).

## Aktuální a historická CI lane

CATMENU měl source lock zahrnující i nyní měněný helper. Jeho historická
workflow zůstává ručně přehratelná na původním testovaném8a545bb8. Aktuální
PR lane nahrazují dva CATLABEL MariaDB joby se stejnou plnou paritou/
checksumy/HTTP/CSP/Chrome a novými hlubokými cíli250/500, baseline30386ddb.
Všeobecné CI stejné; přechod výslovně součástí review. Žádná oslabená brána
ani vynechané testování; původní menu unit testy dál běží.

## Vydání a WIP

[Chráněný deploy 44189981302](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44189981302) SUCCESS,
2500 testů / 57277 kontrol, záloha 394 tabulek / 2 triggery.
Přesný testovaný/sloučený strom `ac82cc6b006259281efe2a6b595cfe787e5a10d3`. Config zachovaný bez doplnění,
pending migrace [], původní VS/zdravotní index shodné. Aktivace, HTTP200 a úklid PASS;
přímý runner HTTP200. UAT/importfalse, import přeskočený.
Živé anonymní GET celého katalogu (normalizované HTML/hlavičky), administrace
(redirect) a canonical CSS před/po stejné: [před](production-before.json), [po](production-after.json).
Normalizace request nonce i u redirectu, ostatní hlavičky/obsah beze změny.
Produkční výkonový profil a přihlášené/lidské UAT NOT RUN. Žádné skutečné
produkty, objednávky, platby, role nebo rozesílky tímto ověřením neměněné.
Doklady samostatným docs PR bez dalšího nasazení. [Receipt](release.json).

Původní worktree/HEAD `d1221f3594a965096d98da5e86a27097f66cd380` a WIP
`kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`
zachované. Souběžná práce ani historické prototypy nepřebírané.

[Integrita dokladů](artifact-integrity.json): SHA-256 skutečných Git blobů,
ověřitelná nezávisle na lokálním převodu konců řádků.
