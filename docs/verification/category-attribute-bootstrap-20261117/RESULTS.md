# P11-ASSET14 — kategorie a parametry e-shopu

17. 11. 2026, Europe/Prague. **NASAZENO #486/9a0bc834fc8d83f593700df985a35993ae9ab1e4, testovaný/sloučený strom f9832999bdcd58a9c471d0bbe8cf6ea3956da567, 13 CI PASS.**
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset14--kategorie-a-parametry-e-shopu-17-11-2026),
[PR #486](https://github.com/KovoPraha/evidenceTreninku/pull/486).
Testovaný head fb51c194f3b0a6ffe27b152f1654a4f30e210287, immutable baseline e30fdc97a369245478c25fd14c220df45aec3e18,
[párový běh 44111856114](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44111856114).

## Rozsah a shoda

Z eshop_categories_admin.php a eshop_attributes_admin.php odebrány přesně dva
CDN Bootstrap CSS tagy; canonical lokální payload, hash a SRI stejné.
Povinné formátování šablon má source contract 14/7 konkrétních editů tokenů:
guard závorky, hodnotově shodný SQL řetězec a emise stejného tagu ikon.
Ostatní tokeny a literální HTML se neliší. Žádné jiné pravidlo, migrace,
helper, writer, právo, cache nebo frontendový skript nebyly upravené.
[Kontrakt 10.3](mariadb-10.3/source-contract.json), [11.4](mariadb-11.4/source-contract.json).
Lokálně 30 testů / 184 kontrol, nezměněné SRI včetně negativů, PHPStan,
styl, syntaxe a dokumentační odkazy PASS.

## MariaDB a celé HTTP

Na každé DB 39 scénářů: všechny pracovní pozice na obou cestách,
anonymní přístup, hledání a prázdný filtr, vybraná kategorie/produkt,
chybná cesta/ID, neaktivní textový, číselný a výběrový parametr včetně
historické neaktivní volby, nový klíč a odmítnutí neplatného CSRF.
Skutečný Apache/PHP a obyčejné PDO, SQL query cache off.
Kladné scénáře 4 warmup/17 střídavých párů, negativy jeden pár.
Celé HTML a bezpečnostní hlavičky shodné po odebrání pouze přesného tagu
a request CSP nonce. Každé kladné HTML přesně −210 B; formuláře, hodnoty,
řazení, odkazy i CSRF stejné. Na DB 10.3/11.4 355/355
checksumů nezměněných tabulek; úklid schématu, účtů, configu a portů PASS.
[HTTP 10.3](mariadb-10.3/http.json.gz), [11.4](mariadb-11.4/http.json.gz).

| MariaDB | Scénář | Medián celého HTTP ms před → po |
| --- | --- | ---: |
| 10.3 | categories-catalog_manager | 6.115 → 6.352 |
| 10.3 | attributes-catalog_manager | 5.903 → 5.642 |
| 10.3 | category-detail | 6.446 → 6.346 |
| 10.3 | category-search | 6.171 → 6.101 |
| 10.3 | category-empty | 6.284 → 6.484 |
| 10.3 | category-invalid | 6.117 → 6.118 |
| 10.3 | category-product | 6.115 → 6.159 |
| 10.3 | category-missing-product | 6.175 → 6.221 |
| 10.3 | attribute-text-inactive | 5.946 → 6.102 |
| 10.3 | attribute-number | 5.808 → 5.992 |
| 10.3 | attribute-choice | 5.904 → 5.921 |
| 10.3 | attribute-missing | 5.878 → 5.833 |
| 10.3 | attribute-key | 5.678 → 5.671 |
| 10.3 | categories-csrf-rejected | 6.374 → 6.219 |
| 10.3 | attributes-csrf-rejected | 5.682 → 5.722 |
| 11.4 | categories-catalog_manager | 4.296 → 4.394 |
| 11.4 | attributes-catalog_manager | 3.882 → 3.960 |
| 11.4 | category-detail | 3.730 → 3.685 |
| 11.4 | category-search | 3.662 → 4.178 |
| 11.4 | category-empty | 4.095 → 4.280 |
| 11.4 | category-invalid | 4.398 → 3.680 |
| 11.4 | category-product | 4.292 → 3.650 |
| 11.4 | category-missing-product | 3.723 → 4.507 |
| 11.4 | attribute-text-inactive | 4.335 → 4.284 |
| 11.4 | attribute-number | 4.131 → 4.138 |
| 11.4 | attribute-choice | 4.244 → 4.218 |
| 11.4 | attribute-missing | 3.458 → 4.044 |
| 11.4 | attribute-key | 3.356 → 3.958 |
| 11.4 | categories-csrf-rejected | 4.292 → 3.627 |
| 11.4 | attributes-csrf-rejected | 4.002 → 3.457 |

## Skutečný Chrome

Přehled/detail obou stránek, desktop 1036×703 light a mobil 390×844 dark,
cold/warm: 16 případů, 352 měřených + 96 warmup navigací. Osm pixelově
identických párů. Geometrie, computed styles, formuláře a Bootstrap JS stejné,
žádné chyby konzole nebo neúspěšné požadavky. CDP ověřuje skutečné payloady
lokálního i CDN CSS, hash a SRI. Cold decoded CSS 465606 → 232803 B,
dvě kopie → jedna. Warm přenos 0 → 0 B. Níže všechny časy včetně nulových
a opačných rozdílů; významné zrychlení serveru tímto neprokazujeme.

| Cesta / schéma | Cache | CSS B před → po | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: | ---: |
| eshop_categories_admin-overview / light | cold | 53957 → 31248 | 64 → 60 | 88.5 → 85.3 |
| eshop_categories_admin-overview / light | warm | 0 → 0 | 44 → 40 | 59.2 → 63.1 |
| eshop_categories_admin-overview / dark | cold | 53957 → 31248 | 64 → 60 | 93.4 → 88.4 |
| eshop_categories_admin-overview / dark | warm | 0 → 0 | 44 → 40 | 67.5 → 61.9 |
| eshop_categories_admin-detail / light | cold | 53957 → 31248 | 64 → 64 | 90.2 → 87.2 |
| eshop_categories_admin-detail / light | warm | 0 → 0 | 48 → 44 | 59.1 → 59.7 |
| eshop_categories_admin-detail / dark | cold | 53957 → 31248 | 64 → 60 | 88.3 → 84.2 |
| eshop_categories_admin-detail / dark | warm | 0 → 0 | 44 → 44 | 60.5 → 56.3 |
| eshop_attributes_admin-overview / light | cold | 53957 → 31248 | 64 → 60 | 89.4 → 81.8 |
| eshop_attributes_admin-overview / light | warm | 0 → 0 | 44 → 44 | 61.1 → 58.9 |
| eshop_attributes_admin-overview / dark | cold | 53957 → 31248 | 60 → 56 | 90.0 → 85.4 |
| eshop_attributes_admin-overview / dark | warm | 0 → 0 | 44 → 40 | 67.0 → 56.7 |
| eshop_attributes_admin-detail / light | cold | 53957 → 31248 | 64 → 60 | 89.0 → 83.9 |
| eshop_attributes_admin-detail / light | warm | 0 → 0 | 48 → 44 | 62.5 → 58.4 |
| eshop_attributes_admin-detail / dark | cold | 53957 → 31248 | 64 → 60 | 90.6 → 83.9 |
| eshop_attributes_admin-detail / dark | warm | 0 → 0 | 44 → 40 | 65.6 → 57.5 |

Cold naměřené dvojice přenosu CSS: [(53957, 31248)]. Přijatý přínos je odstranění
jednoho požadavku a redundantního payloadu; nikoli tvrzení o produkčním SLA.
[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).

## Vydání, WIP a meze

[Chráněný deploy 44114428641](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44114428641) SUCCESS.
2487 testů / 57179 kontrol; ověřená záloha 394 tabulek / 2 triggery.
Konfigurace zachovaná bez doplnění, pending migrace [], původní VS i zdravotní
index shodné; aktivace, HTTP 200 a úklid config-copy PASS.
Přímý runner HTTP 200, import přeskočený, UAT/import false.
[Receipt](release.json). Nezávislé anonymní GET obou administrací a CSS bytes/
hash/cache před/po shodné ([před](production-before.json), [po](production-after.json)).
Přihlášené produkční UAT a přihlášený produkční waterfall NOT RUN.
Syntetický Chrome není produkční SLA; skutečné kategorie, parametry, produkty,
objednávky, role ani externí rozesílky nebyly ověřením měněné.
Výsledkové doklady samostatným PR bez dalšího deploye.

Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
Další kandidáti jsou zbývající konkrétně profilované CSS duplicity nebo
objemové seznamy po samostatném měření; nepřebírat jiné WIP/prototypy.

Archiv důkazů má [manifest SHA-256 Git blobů](artifact-integrity.json);
manifest sám je z výpočtu vyřazen. Soukromé logy, cookies a konfigurace v Gitu nejsou.
