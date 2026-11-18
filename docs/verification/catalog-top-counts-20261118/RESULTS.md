# P11-CATTOP — počty kořenových kategorií

18. 11. 2026, Europe/Prague. **NASAZENO #494/4312157834bd0b70dcc871e40d9e19c812537f47, 13 CI PASS.** [Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-cattop--počty-kořenových-kategorií-18-11-2026).
Base `a1bf2ba28a6e248ae99eb403d06b75749dcc04cf`, větev
`codex/catalog-top-counts-performance-20261118`, vlastní oddělený worktree.

Pouze blok topCategories uvnitř shopCatalogPageSelect. Počty prvních
doslovných segmentů před „ > “ jedním průchodem, set za záznam produktu,
nikoli za ID. Vytvoření výsledných položek zůstává v původním pořadí menu,
se stejnými názvy a nad všemi produkty před filtrem/stránkou. U nepolových
kategorií či neřetězcové cesty zcela původní smyčka; bez nového předčasného
TypeError/varování. Žádné SQL, cache, limity, ceny, prodejnost či writery
se nemění. Source contract: celý prefix/suffix modulu byteově stejný,
uvnitř povolen jen připnutý blok. Šest SQL celého výběru zůstává.

SQLite/PHP8.2.12, celý shopCatalogPageSelect,2 warmup+11 střídavých párů,
celý stav/hash přesně shodný. [Vzorky](local-probe.json), [původní sonda](local-probe.php.txt),
[kandidát](local-candidate.txt). Reprodukce sondy: kopie do output/ s názvy
cattop-probe.php.txt a cattop-candidate-block.txt, oddělený checkout tohoto
base, nikoli aktuálního optimalizovaného main. Diagnostická eval sonda má
jen platné řetězcové DTO; typové kontrakty ověřuje strict oracle níže.

| Produkty × kořeny | Celý výběr ms před → po |
| --- | ---: |
| 12 ×6 | 0,53 →0,52 |
| 1000 ×100 | 181,28 →63,27 |
| 5000 ×500 | 1012,89 →162,53 |
| 10000 ×1000 | 4897,82 →444,11 |
| 10000 ×1 | 348,23 →399,35 |

Single-root režie14,7% je zveřejněná; není to rychlejší každý katalog.
Tohle je místní diagnóza, nikoli MariaDB ani produkční měření.

Lokálně12 testů/1864 kontrol PASS včetně původní kompatibility katalogu:
doslovné mezery/case/Unicode/neplatné
cesty, duplicitní podkategorie i ID záznamů, technické cesty proti metadata
rodičům/skrytí, pořadí, filtr/hledání/stránka a searchMatches, rollback a
druhé spojení. Fallback nepolových kategorií (null/string/ArrayIterator),
přesný typ/message výjimky i počty/kódy/texty varování. Oracle explicitně
kompiluje declare(strict_types=1) jako původní modul. První typový test
odhalil implicitní slabý režim eval bez tohoto deklarování; opraven oracle,
aplikační algoritmus ani tolerance se neměnily. Původní menu testy pokračují.
Číselně vypadající kořeny01/+1/1.0/velké číslo mají přesné řetězcové prefixy.
PHPStan/styl/source contract PASS.

Brány před výsledkem:5000×500 a10000×1000 ≥20% zisku nativního celého
výběru i přehledového HTTP anonym/zákazník na obou MariaDB10.3/11.4.
Ostatní ≤20%+5ms funkce/+20msHTTP. Šest nativních sad v čerstvých procesech,
2+11 střídavých párů, query cacheOFF, stav/hash/6SQL/checksumy beze změny.
Reálné Apache/PHP nad355 migrovanými tabulkami:36 scénářů/DB (20matrix +
4large5000 +4large10000 +4single10000 +4browser120). Skutečné publikované
syntetické zboží a dostupné varianty, celé HTML/hlavičky/CSRF/filtry/řazení/
stránka,4warmup+17párů pozitivů/1pár negativů. Nonce/CSRF/checkout_key a
explicitní testovací origin normalizovány, odvozený CSP hash nejprve ověřen.
355checksumů po každé fázi i Chrome. Browser8 cold/warm/desktop/mobil
případů při120produktech/12kořenech,176měřených+48zahřívacích navigací,
4pixelové páry, formuláře/geometrie/styly/JS/CSS/console. FCP/ready gate20%+20ms.
Light/dark jsou preference profilu, veřejný shell má vlastní světlý vzhled.
Produkční profil a přihlášené/lidské UAT NOT RUN.

Původní WIP/HEAD zachovaný: kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.


## Nativní celý výběr na MariaDB

[PR #494](https://github.com/KovoPraha/evidenceTreninku/pull/494), testovaný head `d66cbcd8a0f5d6f258f05e71072e46c3e4aa46ec`,
immutable baseline `a1bf2ba28a6e248ae99eb403d06b75749dcc04cf`. [Párový běh 44267837717](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44267837717) SUCCESS.
Šest profilů na každé DB, 2 zahřívací+11 střídavých párů v čerstvých PHP procesech,
oba algoritmy zahřáté včetně strict frozen oracle. Query cacheOFF, přesně6SQL,
celý stav/typy/pořadí i serialized SHA shodné, checksumy3tabulek a úklid PASS.
Paměť je přírůstek PHP peak včetně mysqlnd, nikoli DB/OS. Cíle a tolerance
zůstaly předem dané. Záporné procento znamená režii a je uvedeno také.

| MariaDB | Produkty ×kořeny | Medián ms před → po | Zrychlení | PHP peak MiB před → po |
| --- | --- | ---: | ---: | ---: |
| 10.3 | 12 ×6 | 1.29 → 1.29 | -0.3% | 0.040 → 0.040 |
| 10.3 | 1000 ×100 | 17.30 → 7.14 | +58.7% | 0.374 → 0.374 |
| 10.3 | 5000 ×500 | 301.56 → 39.49 | +86.9% | 1.774 → 1.774 |
| 10.3 | 10000 ×1000 | 1106.05 → 85.93 | +92.2% | 3.531 → 3.531 |
| 10.3 | 10000 ×1 | 73.78 → 76.10 | -3.1% | 0.885 → 0.885 |
| 10.3 | 1000 ×0 | 5.36 → 5.32 | +0.8% | 0.060 → 0.060 |
| 11.4 | 12 ×6 | 1.13 → 1.09 | +3.3% | 0.040 → 0.040 |
| 11.4 | 1000 ×100 | 11.94 → 5.73 | +52.0% | 0.374 → 0.374 |
| 11.4 | 5000 ×500 | 203.35 → 31.21 | +84.6% | 1.774 → 1.774 |
| 11.4 | 10000 ×1000 | 760.05 → 66.75 | +91.2% | 3.531 → 3.531 |
| 11.4 | 10000 ×1 | 59.49 → 60.78 | -2.2% | 0.885 → 0.885 |
| 11.4 | 1000 ×0 | 4.31 → 4.50 | -4.5% | 0.060 → 0.060 |

## Celé Apache/PHP HTTP

Na každé DB36scénářů:20matrix při12produktech/6kořenech, pak4scénáře
přehled/filtr ×anonym/zákazník pro5000/500,10000/1000,10000/1 a120/12.
Fixture vytváří10000 syntetických publikací a variant, pak přepíná aktivní
podmnožinu; nezadává již hotové topCategories. Celé skutečné HTML a bezpečnostní
hlavičky shodné,4warmup+17střídavých párů pozitivů/1negativ. Shodná délka raw
HTML před/po, žádné PHPchyby, změna355checksumů ani nevhodný CDN stylesheet.
Každý velký přehled musí získat≥20%; ostatní≤20%+20ms. Celé HTTP tedy
obsahuje i nezměněné načítání/prodejnost/členské ceny a rendering.

| MariaDB | Fáze /scénář | Celé HTTP ms před → po | Zrychlení |
| --- | --- | ---: | ---: |
| 10.3 | matrix / overview-anonymous | 6.09 → 6.03 | +1.0% |
| 10.3 | matrix / category-anonymous | 5.98 → 6.06 | -1.3% |
| 10.3 | matrix / missing-anonymous | 5.84 → 5.84 | +0.1% |
| 10.3 | matrix / invalid-anonymous | 5.76 → 5.81 | -0.7% |
| 10.3 | matrix / search-anonymous | 5.93 → 5.95 | -0.2% |
| 10.3 | matrix / empty-anonymous | 5.80 → 5.90 | -1.8% |
| 10.3 | matrix / sort-price-anonymous | 5.99 → 6.00 | -0.3% |
| 10.3 | matrix / sort-name-anonymous | 6.00 → 6.02 | -0.3% |
| 10.3 | matrix / page-anonymous | 5.96 → 5.99 | -0.5% |
| 10.3 | matrix / overview-customer | 11.85 → 11.92 | -0.6% |
| 10.3 | matrix / category-customer | 11.89 → 11.85 | +0.4% |
| 10.3 | matrix / missing-customer | 11.75 → 11.90 | -1.2% |
| 10.3 | matrix / invalid-customer | 11.64 → 11.66 | -0.2% |
| 10.3 | matrix / search-customer | 12.04 → 12.07 | -0.3% |
| 10.3 | matrix / empty-customer | 11.85 → 11.67 | +1.5% |
| 10.3 | matrix / sort-price-customer | 11.78 → 11.82 | -0.4% |
| 10.3 | matrix / sort-name-customer | 11.83 → 11.77 | +0.5% |
| 10.3 | matrix / page-customer | 12.14 → 12.01 | +1.1% |
| 10.3 | matrix / csrf-customer | 11.96 → 12.08 | -1.1% |
| 10.3 | large-5000 / overview-anonymous | 343.03 → 159.22 | +53.6% |
| 10.3 | large-5000 / category-anonymous | 306.34 → 119.37 | +61.0% |
| 10.3 | large-5000 / overview-customer | 355.23 → 171.24 | +51.8% |
| 10.3 | large-5000 / category-customer | 317.56 → 129.70 | +59.2% |
| 10.3 | large-10000 / overview-anonymous | 1079.32 → 321.09 | +70.3% |
| 10.3 | large-10000 / category-anonymous | 992.51 → 233.48 | +76.5% |
| 10.3 | large-10000 / overview-customer | 1095.63 → 335.81 | +69.3% |
| 10.3 | large-10000 / category-customer | 1009.58 → 250.14 | +75.2% |
| 10.3 | single-10000 / overview-anonymous | 274.01 → 277.25 | -1.2% |
| 10.3 | single-10000 / category-anonymous | 273.39 → 275.24 | -0.7% |
| 10.3 | single-10000 / overview-customer | 293.52 → 297.25 | -1.3% |
| 10.3 | single-10000 / category-customer | 295.15 → 298.10 | -1.0% |
| 10.3 | browser / overview-anonymous | 10.68 → 10.37 | +2.9% |
| 10.3 | browser / category-anonymous | 10.10 → 10.36 | -2.6% |
| 10.3 | browser / overview-customer | 16.70 → 16.60 | +0.6% |
| 10.3 | browser / category-customer | 16.15 → 16.14 | +0.1% |
| 11.4 | matrix / overview-anonymous | 6.48 → 6.28 | +3.1% |
| 11.4 | matrix / category-anonymous | 5.54 → 5.61 | -1.3% |
| 11.4 | matrix / missing-anonymous | 6.05 → 5.99 | +0.9% |
| 11.4 | matrix / invalid-anonymous | 5.67 → 6.02 | -6.3% |
| 11.4 | matrix / search-anonymous | 5.93 → 6.48 | -9.2% |
| 11.4 | matrix / empty-anonymous | 6.18 → 6.08 | +1.6% |
| 11.4 | matrix / sort-price-anonymous | 5.59 → 6.11 | -9.2% |
| 11.4 | matrix / sort-name-anonymous | 5.67 → 6.17 | -8.9% |
| 11.4 | matrix / page-anonymous | 5.80 → 6.18 | -6.5% |
| 11.4 | matrix / overview-customer | 10.14 → 10.50 | -3.6% |
| 11.4 | matrix / category-customer | 10.60 → 10.40 | +1.8% |
| 11.4 | matrix / missing-customer | 10.92 → 10.67 | +2.3% |
| 11.4 | matrix / invalid-customer | 11.01 → 10.91 | +0.9% |
| 11.4 | matrix / search-customer | 10.86 → 11.00 | -1.3% |
| 11.4 | matrix / empty-customer | 11.67 → 11.48 | +1.6% |
| 11.4 | matrix / sort-price-customer | 10.98 → 10.97 | +0.1% |
| 11.4 | matrix / sort-name-customer | 11.62 → 11.62 | +0.0% |
| 11.4 | matrix / page-customer | 10.52 → 11.72 | -11.4% |
| 11.4 | matrix / csrf-customer | 11.84 → 11.48 | +3.0% |
| 11.4 | large-5000 / overview-anonymous | 265.44 → 119.53 | +55.0% |
| 11.4 | large-5000 / category-anonymous | 228.58 → 83.48 | +63.5% |
| 11.4 | large-5000 / overview-customer | 305.13 → 137.26 | +55.0% |
| 11.4 | large-5000 / category-customer | 239.79 → 94.14 | +60.7% |
| 11.4 | large-10000 / overview-anonymous | 828.35 → 232.77 | +71.9% |
| 11.4 | large-10000 / category-anonymous | 750.41 → 159.64 | +78.7% |
| 11.4 | large-10000 / overview-customer | 839.66 → 242.22 | +71.2% |
| 11.4 | large-10000 / category-customer | 767.16 → 174.64 | +77.2% |
| 11.4 | single-10000 / overview-anonymous | 196.12 → 194.46 | +0.8% |
| 11.4 | single-10000 / category-anonymous | 199.46 → 197.23 | +1.1% |
| 11.4 | single-10000 / overview-customer | 216.99 → 215.78 | +0.6% |
| 11.4 | single-10000 / category-customer | 214.42 → 213.96 | +0.2% |
| 11.4 | browser / overview-anonymous | 18.18 → 18.88 | -3.9% |
| 11.4 | browser / category-anonymous | 9.30 → 9.26 | +0.4% |
| 11.4 | browser / overview-customer | 14.00 → 13.99 | +0.1% |
| 11.4 | browser / category-customer | 14.30 → 13.78 | +3.6% |

## Skutečný Chrome

120produktů/12kořenů, přehled a filtrLOCALHOST-11, desktop1036×703/mobil390×844,
cold/warm8případů.176měřených+48zahřívacích navigací,4identické full-page
pixelové páry. Stejné formuláře/styly/geometrie/overflow/BootstrapJS, bez
chyb konzole/požadavků. Skutečný CDP CSS payload/hash: cold232803decodedB,
shodný gzip přenos; warm0B. FCP/ready≤20%+20ms, všechny směry níže.
Light/dark jsou preference profilu; veřejný shell má vlastní světlý vzhled.
Produkční SLA ani přihlášené/lidské UAT se tím neměří.

| Cesta /profil | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop-overview / light | cold | 64 → 64 | 92.2 → 93.8 |
| eshop-overview / light | warm | 48 → 48 | 71.6 → 66.4 |
| eshop-overview / dark | cold | 60 → 60 | 89.0 → 86.7 |
| eshop-overview / dark | warm | 52 → 48 | 72.5 → 69.9 |
| eshop-category / light | cold | 60 → 64 | 85.3 → 85.3 |
| eshop-category / light | warm | 48 → 52 | 66.2 → 69.9 |
| eshop-category / dark | cold | 60 → 60 | 85.5 → 86.1 |
| eshop-category / dark | warm | 48 → 52 | 66.3 → 70.1 |

[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).
Historické CATMENU/CATLABEL workflows i jejich unit testy zachované;
CATTOP mění jiný modul a přidává vlastní2MariaDB joby. Všeobecné CI stejné.

## Vydání a WIP

[Chráněný deploy 44269813909](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44269813909) SUCCESS.
2506 testů / 57333 kontrol, záloha 394 tabulek / 2 triggery.
Přesný testovaný/sloučený strom `0142340dd157ea71080bc2cf05365523581bc39c`. Config zachovaný bez doplnění,
pending migrace [], původní VS/zdravotní index shodné. Aktivace, HTTP200 a úklid PASS;
přímý runner HTTP200. UAT/importfalse, import přeskočený.
Živé anonymní GET celého katalogu (normalizované HTML/hlavičky), administrace
(redirect) a canonical CSS před/po stejné: [před](production-before.json), [po](production-after.json).
Normalizace request nonce i u redirectu, ostatní hlavičky/obsah beze změny.
Produkční výkonový profil a přihlášené/lidské UAT NOT RUN. Žádné skutečné
produkty, objednávky, platby, role nebo rozesílky tímto ověřením neměněné.
Doklady samostatným docs PR bez dalšího nasazení. [Receipt](release.json).

Původní worktree/HEAD `d1221f3594a965096d98da5e86a27097f66cd380`, WIP
`kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`
zachované. Souběžná práce ani historické prototypy nepřebírané.

[Integrita dokladů](artifact-integrity.json): SHA-256 skutečných Git blobů,
ověřitelná nezávisle na lokálním převodu konců řádků.
