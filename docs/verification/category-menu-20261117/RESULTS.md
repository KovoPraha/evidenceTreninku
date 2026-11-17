# P11-CATMENU — strom kategorií veřejného katalogu

17. 11. 2026 Europe/Prague. **NASAZENO #490/a3796752830c7d82503a52be6c1d6bfafd892c29, 13 CI PASS.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catmenu--strom-kategorií-veřejného-katalogu-17-11-2026),
[PR #490](https://github.com/KovoPraha/evidenceTreninku/pull/490).
Testovaný head `8a545bb88f52029bf521a62d60d1d81cf2dea440`, immutable baseline `832048619c3b5c4977e614a5c746979e03a30815`,
[párový běh 44151159980](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44151159980).

## Změna a shoda

Pouze shopStorefrontCategoryMenu: index přímých potomků v jednom volání,
stejné BFS pořadí se striktně typovaným navštívením, kurzor místo array_shift.
Celé menu (metadata, pořadí, descendant_paths, product_count, menu_label)
přesně stejné jako frozen původní funkce; žádný limit produktů/výsledku.
3 SQL dotazy zůstávají, žádný persistentní/sdílený cache. Generické potomky,
filtry/řazení, prodejnost/ceny/formuláře/writery neovlivňuje tento zásah.
Source contract: frozen funkce z baseline, všechny ostatní tokeny stejné
s výjimkou 27 připnutých pozic stylových závorek.

Lokálně 23 testů /166 kontrol PASS: skrytí rodiče/potomci, virtuální cesty,
duplicity, chybějící/nulové/negativní ID, Unicode/natural sort, sirotci/cykly,
12 smíšených grafů, nezměněná data a izolace spojení/transakce/rollbacku.
Číselná cesta již v původní shopCategoryNodes vyvolá TypeError (int do
explode); zachována stejná výjimka, oprava není součástí optimalizace.
[Lokální SQLite diagnóza](local-probe.json) není nativní/produkční profil.
PHPStan/styl/odkazy PASS v CI.

## Nativní MariaDB a PHP procesy

9 tvarů/velikostí na každé DB, vždy jeden přiřazený produkt ve velkém stromu;
počty jsou kategorie. Každý vzorek čerstvý PHP proces, oba algoritmy zahřáté
včetně kompilace frozen oracle, 2 warmup a11 střídavých měřených párů,
query cache OFF. Celé menu/typy/hash shodné, checksumy3 tabulek stejné,
3 SQL/volání. Úklid vlastního schématu PASS. Paměť je přírůstek PHP peak
včetně mysqlnd bufferu, nikoli paměť DB/OS; index má i paměťovou režii.
Předem určeno ≥20% zisku velkých případů, malé ≤20%+5ms. Brány neoslabené.

| MariaDB | Tvar / kategorie | Medián ms před → po | PHP peak MiB před → po |
| --- | --- | ---: | ---: |
| 10.3 | flat / 30 | 1.18 → 1.15 | 0.052 → 0.052 |
| 10.3 | flat / 500 | 10.00 → 3.98 | 0.483 → 0.483 |
| 10.3 | flat / 1000 | 31.29 → 7.30 | 0.961 → 0.961 |
| 10.3 | tree / 30 | 1.28 → 1.25 | 0.054 → 0.054 |
| 10.3 | tree / 500 | 34.11 → 4.20 | 0.526 → 0.583 |
| 10.3 | tree / 1000 | 143.36 → 7.61 | 1.046 → 1.158 |
| 10.3 | chain / 120 | 33.58 → 5.53 | 0.696 → 0.734 |
| 10.3 | chain / 250 | 284.08 → 22.89 | 2.918 → 2.992 |
| 10.3 | mixed / 80 | 3.74 → 1.73 | 0.125 → 0.145 |
| 11.4 | flat / 30 | 0.76 → 0.74 | 0.052 → 0.052 |
| 11.4 | flat / 500 | 7.88 → 3.06 | 0.483 → 0.483 |
| 11.4 | flat / 1000 | 24.87 → 5.57 | 0.961 → 0.961 |
| 11.4 | tree / 30 | 0.86 → 0.77 | 0.054 → 0.054 |
| 11.4 | tree / 500 | 25.17 → 3.15 | 0.526 → 0.583 |
| 11.4 | tree / 1000 | 104.28 → 5.77 | 1.046 → 1.158 |
| 11.4 | chain / 120 | 26.13 → 4.31 | 0.696 → 0.734 |
| 11.4 | chain / 250 | 211.55 → 18.89 | 2.918 → 2.992 |
| 11.4 | mixed / 80 | 2.75 → 1.15 | 0.125 → 0.145 |

## Celé HTTP

Reálné Apache/PHP, úplně migrovaná vlastní DB (355 tabulek), fyzicky oddělené
baseline booking/kořenové PHP/includes. 20 scénářů při30 kategoriích:
anonymní/zákazník, přehled/kategorie/chybějící/neplatná volba, hledání/prázdné,
řazení podle ceny/jména/stránkování a odmítnutí nesprávného CSRF. Další3 sady
po4 cestách (přehled/filtr × oba kontexty): flat1000, chain250 a tree1000.
32 scénářů/DB, 4 warmup +17 střídavých měřených párů pozitivů, negativ1 pár.
Celé HTML/hlavičky a raw velikosti stejné, normalizovány jen request nonce,
64hex CSRF,32hex checkout_key a očekávaný origin příslušného syntetického serveru.
Žádná cesta/atribut ani cenový/produktový obsah nejsou odstraněné.
355 checksumů stejné po každé fázi i po Chrome; skutečný neplatný POST action=add
nepřidá nic do košíku. Žádné produkční SQL či testovací objednávky.
Každé velké přehledové HTTP musí získat ≥20%, ostatní ≤20%+20ms; bez úpravy tolerance.

| MariaDB | Fáze / scénář | Celé HTTP ms před → po |
| --- | --- | ---: |
| 10.3 | matrix / overview-anonymous | 16.62 → 16.21 |
| 10.3 | matrix / category-anonymous | 16.17 → 16.16 |
| 10.3 | matrix / missing-anonymous | 16.54 → 16.30 |
| 10.3 | matrix / invalid-anonymous | 16.55 → 16.61 |
| 10.3 | matrix / search-anonymous | 16.52 → 16.76 |
| 10.3 | matrix / empty-anonymous | 16.62 → 16.78 |
| 10.3 | matrix / sort-price-anonymous | 16.12 → 15.96 |
| 10.3 | matrix / sort-name-anonymous | 15.78 → 15.87 |
| 10.3 | matrix / page-anonymous | 16.08 → 16.11 |
| 10.3 | matrix / overview-customer | 23.51 → 24.16 |
| 10.3 | matrix / category-customer | 24.69 → 24.27 |
| 10.3 | matrix / missing-customer | 23.95 → 24.06 |
| 10.3 | matrix / invalid-customer | 23.68 → 23.61 |
| 10.3 | matrix / search-customer | 23.89 → 23.93 |
| 10.3 | matrix / empty-customer | 23.91 → 23.90 |
| 10.3 | matrix / sort-price-customer | 23.76 → 23.73 |
| 10.3 | matrix / sort-name-customer | 23.52 → 23.58 |
| 10.3 | matrix / page-customer | 23.82 → 23.91 |
| 10.3 | matrix / csrf-customer | 24.20 → 23.81 |
| 10.3 | flat-1000 / overview-anonymous | 34.95 → 23.87 |
| 10.3 | flat-1000 / category-anonymous | 34.85 → 24.07 |
| 10.3 | flat-1000 / overview-customer | 43.60 → 32.41 |
| 10.3 | flat-1000 / category-customer | 43.42 → 32.30 |
| 10.3 | chain-250 / overview-anonymous | 174.52 → 35.94 |
| 10.3 | chain-250 / category-anonymous | 173.36 → 36.55 |
| 10.3 | chain-250 / overview-customer | 180.04 → 42.79 |
| 10.3 | chain-250 / category-customer | 183.05 → 43.91 |
| 10.3 | tree-1000 / overview-anonymous | 93.99 → 25.24 |
| 10.3 | tree-1000 / category-anonymous | 93.80 → 25.76 |
| 10.3 | tree-1000 / overview-customer | 102.03 → 33.26 |
| 10.3 | tree-1000 / category-customer | 102.29 → 33.02 |
| 11.4 | matrix / overview-anonymous | 12.38 → 12.33 |
| 11.4 | matrix / category-anonymous | 14.65 → 14.18 |
| 11.4 | matrix / missing-anonymous | 11.99 → 12.26 |
| 11.4 | matrix / invalid-anonymous | 12.18 → 12.44 |
| 11.4 | matrix / search-anonymous | 12.01 → 12.29 |
| 11.4 | matrix / empty-anonymous | 12.15 → 11.82 |
| 11.4 | matrix / sort-price-anonymous | 11.94 → 12.21 |
| 11.4 | matrix / sort-name-anonymous | 12.42 → 12.13 |
| 11.4 | matrix / page-anonymous | 12.34 → 12.44 |
| 11.4 | matrix / overview-customer | 18.23 → 18.28 |
| 11.4 | matrix / category-customer | 18.47 → 18.44 |
| 11.4 | matrix / missing-customer | 18.37 → 18.32 |
| 11.4 | matrix / invalid-customer | 18.31 → 18.25 |
| 11.4 | matrix / search-customer | 18.20 → 18.32 |
| 11.4 | matrix / empty-customer | 18.49 → 18.21 |
| 11.4 | matrix / sort-price-customer | 18.02 → 18.10 |
| 11.4 | matrix / sort-name-customer | 18.23 → 18.27 |
| 11.4 | matrix / page-customer | 18.23 → 18.41 |
| 11.4 | matrix / csrf-customer | 18.06 → 18.27 |
| 11.4 | flat-1000 / overview-anonymous | 26.83 → 18.22 |
| 11.4 | flat-1000 / category-anonymous | 27.28 → 18.37 |
| 11.4 | flat-1000 / overview-customer | 33.29 → 24.32 |
| 11.4 | flat-1000 / category-customer | 32.82 → 24.22 |
| 11.4 | chain-250 / overview-anonymous | 148.75 → 29.60 |
| 11.4 | chain-250 / category-anonymous | 149.10 → 30.20 |
| 11.4 | chain-250 / overview-customer | 153.94 → 35.55 |
| 11.4 | chain-250 / category-customer | 157.61 → 36.55 |
| 11.4 | tree-1000 / overview-anonymous | 76.34 → 19.23 |
| 11.4 | tree-1000 / category-anonymous | 75.73 → 19.75 |
| 11.4 | tree-1000 / overview-customer | 82.04 → 25.29 |
| 11.4 | tree-1000 / category-customer | 82.68 → 26.19 |

## Skutečný Chrome při tree1000

Katalog a filtr, desktop1036×703/light a mobil390×844/dark, cold/warm:
8 případů,176 měřených +48 zahřívacích navigací, 4 identické full-page pixelové
páry. Shodné formuláře, computed styles/geometrie/overflow a Bootstrap JS,
bez chyb konzole/požadavků. CDP ověřil skutečný CSS payload/hash;
cold decoded232803→232803B, stejný gzip přenos, warm0→0B.
FCP/ready gate ≤20%+20ms; i nulové/opačné směry níže. Není to produkční SLA/UAT.
Light/dark označuje nastavenou localStorage preferenci profilu; veřejný shell
na obou sadách snímků používá vlastní světlý vzhled. Desktop i mobil byly
navíc vizuálně zkontrolovány z uložených snímků.

| Cesta / schéma | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop-overview / light | cold | 140 → 80 | 166.6 → 107.0 |
| eshop-overview / light | warm | 120 → 64 | 137.1 → 77.6 |
| eshop-overview / dark | cold | 132 → 76 | 164.9 → 102.7 |
| eshop-overview / dark | warm | 120 → 60 | 134.9 → 81.9 |
| eshop-category / light | cold | 136 → 80 | 165.4 → 106.9 |
| eshop-category / light | warm | 120 → 64 | 136.8 → 79.4 |
| eshop-category / dark | cold | 136 → 76 | 166.0 → 101.5 |
| eshop-category / dark | warm | 120 → 60 | 138.7 → 81.8 |

[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).

## Zamítnuté měření a jiné kandidáty

První běh [44148895506](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44148895506)
neprošel HTTP gain bránou (11.4 flat1000 16,68→16,39ms). Symlink booking
v baseline způsobil, že PHP __DIR__ načítalo nový includes; HTTP srovnávalo
stejný algoritmus, proto nepoužitelné jako před/po. Fixture opravena fyzickou
kopií a realpath asercí, aplikační algoritmus/brány stejné. Finální běh výše
opakuje celé profily. Nativní frozen oracle byl správný už v prvním běhu.
Druhý běh44149428209 měl správné HTTP před/po, ale Chrome zastavil CSP kvůli
placeholder obrázkům odkazujícím starý port. Fixture nastavuje každému workeru
existující APP_BASE_URL env na jeho vlastní loopback origin, ten je při HTML/
hlavičkovém srovnání další očekávanou normalizací. Běh44150359799 zastavila
CSP shoda hash handleru obsahujícího origin. Helper ověří skutečný raw hash
a přeloží právě tento hash na tentýž handler s normalizovaným originem,
unit včetně chybějícího raw hash. Další CSP/cesty/payloady
a error gate stejné; celé finální profily zopakované, žádné mockování chyb.
P11-REVIEW SQL prototyp nevybraný pro nestabilní zisk/regrese filtrů,
[plán s doklady](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-review--agregace-kontroly-importovaného-katalogu-17-11-2026).

## Vydání a zachování WIP

[Chráněný deploy 44152881039](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44152881039) SUCCESS,
2496 testů / 57239 kontrol, záloha 394 tabulek / 2 trigerry.
Přesný testovaný/sloučený strom `4931882cb8228ab2c56641685e0b78de23c9eb59`. Config zachovaný bez doplnění,
pending migrace [], původní VS/zdravotní index shodné. Aktivace, HTTP200 a úklid PASS;
přímý runner HTTP200. UAT/import false, import přeskočený.
Živé anonymní GET celého katalogu (normalizované HTML/hlavičky), administrace
(redirect) a canonical CSS před/po stejné: [před](production-before.json), [po](production-after.json).
První živé porovnání zachytilo request nonce v CSP redirectu302; stejná již
určená nonce normalizace doplněna i na redirect. Celé normalizované HTML
katalogu mělo0 rozdílů, finální porovnání všech hlaviček/obsahu PASS.
Produkční měření výkonu a přihlášené/lidské UAT NOT RUN. Žádné skutečné
produkty, objednávky, platby, role ani rozesílky tímto ověřením neměněné.
Doklady samostatným docs PR bez dalšího nasazení. [Receipt](release.json).

Původní worktree/HEAD `d1221f3594a965096d98da5e86a27097f66cd380` a WIP
`kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`
zachované. Souběžné PR a staré lokální prototypy nepřebírané.
