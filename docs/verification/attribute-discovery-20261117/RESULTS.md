# P11-ATTR — objevování parametrů variant

17. 11. 2026, Europe/Prague. **NASAZENO #488/4270d0ccdf97499891a059d70e28df3169444247, testovaný/sloučený strom 2426a2ec7629b445717eee536061d87ec0d2a49d, 13 CI PASS.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-attr--objevování-parametrů-variant-17-11-2026),
[PR #488](https://github.com/KovoPraha/evidenceTreninku/pull/488).
Testovaný head 559795b1f1c6b4a4ac96bb10f04f79aaeef4ec1c, immutable baseline f8f1c17511a6826411b309d24910f37042dd5bf9,
[párový běh 44128919479](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44128919479).

## Změna a sémantika

shopAttributeDiscoveredKeys čte řádky postupně a přesně stejné raw JSON
dekóduje jen jednou, dokud jsou v omezené paměti jednoho volání (128 položek,
každá nejvýše 8192 B). Velké hodnoty se nadále dekódují pokaždé; pořadí,
číselné typy klíčů a třídění stejné. SQL i PDO buffering beze změny. Cache
není perzistentní/sdílená, každé volání vidí aktuální data včetně transakce.
Žádná umělá mez počtu výsledných klíčů; buffer PDO a výstup mohou dál růst.

Source contract potvrzuje přesný frozen oracle a všechny ostatní tokeny
mimo discovery, s výjimkou 40 připnutých guard závorek povinného stylu.
Ostatní pravidla formulářů, definic, prezentace, oprávnění a writery stejné.
[Kontrakt 10.3](mariadb-10.3/source-contract.json), [11.4](mariadb-11.4/source-contract.json).
Lokálně 25 testů / 124 kontrol, PHPStan/styl/syntaxe a odkazy PASS.

## Nativní funkce — MariaDB a PHP procesy

Sedm sad na každé DB: 1k/10k/30k repeated/unique a 400 legacy hodnot v BLOB,
který zachovává historické neplatné bajty. Null/skaláry/pole/číselné klíče,
case/Unicode/trim, neplatný JSON/UTF-8/hloubka, evikce a velké dokumenty
vrátily přesně stejné klíče jako frozen algoritmus. Dále unit diferenciální
testy čerstvosti mezi voláními, izolace připojení a transakce/rollbacku.
Na každé volání stále 2 SQL, neměnné checksumy, úklid vlastního schématu PASS.

Každý vzorek samostatný PHP proces, oba algoritmy před měřením stejně zahřáté
(také kompilace oracle), 2 warmup a 11 střídavých měřených párů, query cache off.
PHP peak níže zahrnuje skutečný mysqlnd buffer, jde o přírůstek během funkce.
Paměť serveru MariaDB a procesu OS se tím neměří. Repeated musí získat ≥20 %,
unique režie nejvýše 20 % +5ms; gate určený před výsledkem, nebyl oslabený.

| MariaDB | Distribuce / řádky | Medián ms před → po | Přírůstek PHP peak MiB před → po |
| --- | --- | ---: | ---: |
| 10.3 | repeated / 1000 | 4.05 → 2.31 | 2.943 → 1.478 |
| 10.3 | repeated / 10000 | 39.42 → 20.05 | 29.298 → 14.416 |
| 10.3 | repeated / 30000 | 121.85 → 62.96 | 87.556 → 43.126 |
| 10.3 | unique / 1000 | 4.12 → 4.50 | 2.943 → 1.670 |
| 10.3 | unique / 10000 | 39.31 → 43.33 | 29.298 → 14.607 |
| 10.3 | unique / 30000 | 115.96 → 132.10 | 90.400 → 46.162 |
| 10.3 | legacy / 400 | 1.68 → 1.73 | 0.404 → 0.337 |
| 11.4 | repeated / 1000 | 7.20 → 3.53 | 2.943 → 1.478 |
| 11.4 | repeated / 10000 | 65.79 → 30.96 | 29.298 → 14.416 |
| 11.4 | repeated / 30000 | 187.40 → 85.50 | 87.556 → 43.126 |
| 11.4 | unique / 1000 | 6.96 → 8.27 | 2.943 → 1.670 |
| 11.4 | unique / 10000 | 67.07 → 72.44 | 29.298 → 14.607 |
| 11.4 | unique / 30000 | 189.94 → 228.00 | 90.400 → 46.162 |
| 11.4 | legacy / 400 | 3.13 → 3.23 | 0.404 → 0.337 |

Nižší PHP peak je doložený také na jedinečných datech, čas ale může být vyšší.
Tento kompromis se nesmí vydávat za rychlejší každý katalog. Nativní přírůstek
paměti není zanedbatelný jako v SQLite: PDO buffer zůstává a není změněný.
[Nativní vzorky 10.3](mariadb-10.3/native.json), [11.4](mariadb-11.4/native.json).

## Celé HTTP a přesné HTML

Plně migrovaná vlastní syntetická DB: při 1k repeated 39 rolových/negativních/
kladných/CSRF scénářů, další čtyři sady při 10k/30k repeated/unique po třech
cestách (přehled/detail cíle a kategorie jako kontrola). Celkem 51 scénářů na DB,
každá fáze 355 checksumů beze změny. Objemy HTTP označují přidané syntetické
varianty k výchozímu fixture; nativní tabulka má přesně uvedený počet řádků. Kladné 4 warmup a 17 střídavých párů,
negativy 1 pár. Skutečné Apache/PHP a obyčejné PDO, žádné request mocky.
Celé HTML, hlavičky, CSRF, formuláře a raw velikosti stejné; normalizovaný
jen request CSP nonce. Žádný tag/asset/obsah není ignorovaný nebo odstraněný.
Repeated30k přehled i detail musí získat ≥20 %, ostatní nejvýše +20 % +20ms.

| MariaDB | Fáze / scénář | Medián celého HTTP ms před → po |
| --- | --- | ---: |
| 10.3 | matrix / categories-catalog_manager | 3.84 → 3.92 |
| 10.3 | matrix / attributes-catalog_manager | 8.10 → 5.86 |
| 10.3 | matrix / category-detail | 4.15 → 4.13 |
| 10.3 | matrix / category-search | 3.99 → 4.05 |
| 10.3 | matrix / category-empty | 3.88 → 3.87 |
| 10.3 | matrix / category-invalid | 3.78 → 3.80 |
| 10.3 | matrix / category-product | 3.87 → 3.89 |
| 10.3 | matrix / category-missing-product | 3.80 → 3.80 |
| 10.3 | matrix / attribute-text-inactive | 7.74 → 6.24 |
| 10.3 | matrix / attribute-number | 7.71 → 5.83 |
| 10.3 | matrix / attribute-choice | 8.01 → 6.08 |
| 10.3 | matrix / attribute-missing | 8.80 → 6.82 |
| 10.3 | matrix / attribute-key | 8.19 → 6.47 |
| 10.3 | matrix / categories-csrf-rejected | 3.96 → 3.92 |
| 10.3 | matrix / attributes-csrf-rejected | 7.81 → 5.81 |
| 10.3 | repeated-10000 / categories-catalog_manager | 3.86 → 3.88 |
| 10.3 | repeated-10000 / attributes-catalog_manager | 45.95 → 27.77 |
| 10.3 | repeated-10000 / attribute-choice | 46.01 → 27.52 |
| 10.3 | repeated-30000 / categories-catalog_manager | 4.41 → 4.00 |
| 10.3 | repeated-30000 / attributes-catalog_manager | 129.41 → 74.86 |
| 10.3 | repeated-30000 / attribute-choice | 126.92 → 74.37 |
| 10.3 | unique-10000 / categories-catalog_manager | 4.21 → 4.24 |
| 10.3 | unique-10000 / attributes-catalog_manager | 48.09 → 51.44 |
| 10.3 | unique-10000 / attribute-choice | 46.12 → 49.86 |
| 10.3 | unique-30000 / categories-catalog_manager | 3.81 → 3.78 |
| 10.3 | unique-30000 / attributes-catalog_manager | 135.49 → 143.99 |
| 10.3 | unique-30000 / attribute-choice | 130.80 → 146.97 |
| 11.4 | matrix / categories-catalog_manager | 7.40 → 7.44 |
| 11.4 | matrix / attributes-catalog_manager | 14.77 → 10.76 |
| 11.4 | matrix / category-detail | 7.66 → 7.77 |
| 11.4 | matrix / category-search | 7.30 → 7.40 |
| 11.4 | matrix / category-empty | 7.34 → 7.35 |
| 11.4 | matrix / category-invalid | 7.27 → 7.31 |
| 11.4 | matrix / category-product | 7.43 → 7.30 |
| 11.4 | matrix / category-missing-product | 7.48 → 7.40 |
| 11.4 | matrix / attribute-text-inactive | 15.04 → 11.40 |
| 11.4 | matrix / attribute-number | 14.49 → 11.32 |
| 11.4 | matrix / attribute-choice | 14.81 → 10.85 |
| 11.4 | matrix / attribute-missing | 14.56 → 10.78 |
| 11.4 | matrix / attribute-key | 14.17 → 10.93 |
| 11.4 | matrix / categories-csrf-rejected | 7.25 → 7.33 |
| 11.4 | matrix / attributes-csrf-rejected | 14.46 → 10.71 |
| 11.4 | repeated-10000 / categories-catalog_manager | 7.27 → 7.30 |
| 11.4 | repeated-10000 / attributes-catalog_manager | 83.42 → 46.00 |
| 11.4 | repeated-10000 / attribute-choice | 80.24 → 43.12 |
| 11.4 | repeated-30000 / categories-catalog_manager | 7.34 → 7.51 |
| 11.4 | repeated-30000 / attributes-catalog_manager | 233.53 → 117.91 |
| 11.4 | repeated-30000 / attribute-choice | 216.39 → 107.46 |
| 11.4 | unique-10000 / categories-catalog_manager | 7.42 → 7.48 |
| 11.4 | unique-10000 / attributes-catalog_manager | 83.96 → 87.67 |
| 11.4 | unique-10000 / attribute-choice | 79.77 → 84.97 |
| 11.4 | unique-30000 / categories-catalog_manager | 7.29 → 7.41 |
| 11.4 | unique-30000 / attributes-catalog_manager | 228.15 → 249.76 |
| 11.4 | unique-30000 / attribute-choice | 228.49 → 242.17 |

## Chrome při 30k unique

16 cold/warm/desktop light/mobil dark případů, 352 měřených +96 warmup
navigací. Osm full-page pixelových párů identických, stejné formuláře,
geometrie, computed styles a Bootstrap JS; žádné chyby konzole/požadavků.
CSS payloady a hash stejné, cold decoded 232803→232803 B, warm přenos 0→0 B.
Kontrola geometrie/času není produkční UAT ani SLA. Níže i nulové a opačné
časové rozdíly; FCP/ready gate +20 % +20ms nebyl změněný.

| Cesta / schéma | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop_categories_admin-overview / light | cold | 88 → 84 | 111.5 → 106.2 |
| eshop_categories_admin-overview / light | warm | 56 → 56 | 58.0 → 57.8 |
| eshop_categories_admin-overview / dark | cold | 76 → 76 | 102.0 → 102.9 |
| eshop_categories_admin-overview / dark | warm | 52 → 52 | 71.4 → 73.1 |
| eshop_categories_admin-detail / light | cold | 84 → 84 | 106.7 → 107.3 |
| eshop_categories_admin-detail / light | warm | 56 → 60 | 61.4 → 64.6 |
| eshop_categories_admin-detail / dark | cold | 80 → 80 | 107.4 → 104.0 |
| eshop_categories_admin-detail / dark | warm | 56 → 56 | 73.5 → 68.4 |
| eshop_attributes_admin-overview / light | cold | 316 → 324 | 349.0 → 345.1 |
| eshop_attributes_admin-overview / light | warm | 280 → 304 | 289.3 → 321.3 |
| eshop_attributes_admin-overview / dark | cold | 296 → 320 | 322.9 → 348.8 |
| eshop_attributes_admin-overview / dark | warm | 276 → 304 | 296.4 → 321.2 |
| eshop_attributes_admin-detail / light | cold | 296 → 324 | 320.8 → 352.4 |
| eshop_attributes_admin-detail / light | warm | 276 → 308 | 280.3 → 313.4 |
| eshop_attributes_admin-detail / dark | cold | 296 → 328 | 327.0 → 356.4 |
| eshop_attributes_admin-detail / dark | warm | 272 → 304 | 284.8 → 312.6 |

[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).

## Lokální sondy a nevydané varianty

Před implementací [SQLite vzorky](local-probe.json): repeated30k 230→75 ms,
unique254→272ms. Tyto přírůstky paměti nepočítají stejně jako MariaDB buffer.
Pozdější [adaptivní backoff](adaptive-probe.json) a [fetchColumn](column-probe.json)
neprokázaly přínos navíc (druhý unique +32,83 %); žádná z nich nebyla do
produkčního kandidáta převzatá. Jsou to jednotlivé lokální sondy s jinou
časovou baseline, nikoli nativní měření přijaté implementace.

## Vydání, WIP a meze

[Chráněný deploy 44131137781](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44131137781) SUCCESS.
2491 testů / 57194 kontrol, záloha 394 tabulek / 2 triggery.
Konfigurace zachovaná bez doplnění, pending migrace [], původní VS a zdravotní
index stejné. Aktivace/HTTP200/úklid PASS, přímý runner HTTP 200.
UAT/import false, import přeskočený. [Receipt](release.json).
Živé anonymní GET obou administrací a canonical CSS payload/hash/cache před/po
stejné ([před](production-before.json), [po](production-after.json)).
Žádné skutečné produkty, definice, objednávky, účasti, role nebo rozesílky tímto
ověřením neměněné. Přihlášené produkční UAT a produkční výkonový profil NOT RUN.
Doklady vlastní docs PR bez dalšího deploye.

Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
Jiné WIP/PR/prototypy nepřebírané. Další výkonový zásah až po nové samostatné
diagnóze; tento řez nemění další stránky, schéma nebo provozní přepínače.

Archiv důkazů má [manifest SHA-256 Git blobů](artifact-integrity.json).
Manifest sám je z výpočtu vyřazen; soukromé logy, cookies a konfigurace v Gitu nejsou.
