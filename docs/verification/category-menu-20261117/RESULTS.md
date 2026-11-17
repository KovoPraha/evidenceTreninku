# P11-CATMENU — průběžné ověření 17. 11. 2026

Stav: lokální implementace, merge a nasazení NOT RUN. Větev
`codex/catalog-category-menu-performance-20261117`, základ `832048619c3b5c4977e614a5c746979e03a30815`.

Jediná funkční změna: index přímých potomků uvnitř
`shopStorefrontCategoryMenu`, opakované BFS používá index, kurzor a striktní
navštívení. Index žije jedno volání. 3 SQL dotazy beze změny; generická funkce
potomků a řazení, prodejnost, ceny, cache a zápisy se nemění.
Source contract připíná frozen původní funkci a 27 pozic závorek mimo menu;
ostatní tokeny musí být shodné.

Místní SQLite profil je diagnóza, nikoli produkční výsledek:
[vzorky](local-probe.json). 30 flat1,79→1,86ms, 500 flat124,66→48,70ms,
500 tree468,01→57,94ms, 120 chain399,88→70,35ms. Celé menu shodné.

Testy: skrytí rodiče/potomci, virtuální kategorie, duplicity produktů,
nulové/negativní ID, Unicode/natural pořadí, sirotci/cykly, 12 smíšených
grafů, nezměněná data, dvě spojení a rollback. Číselná cesta v původní
`shopCategoryNodes` vyvolá TypeError; stejná výjimka se zachovává, neopravuje.

Nativní plán: MariaDB10.3 a11.4, 9 tvarů/velikostí, čerstvé procesy,
2+11 střídavých párů, celé menu a checksumy 3 tabulek, SQL budget3,
čas/peak/čtení. Reálný Apache/PHP celé stránky katalogu: 20 matrix scénářů
(anonymní/zákazník, hledání/řazení/kategorie/neplatná a prázdná volba/
stránkování/odmítnutí CSRF) +4 scénáře pro každý velký tvar. 4 zahřátí
+17 měřených párů pro pozitivní případ. Nonce a CSRF token jsou jediné
normalizace HTML spolu s náhodným 32hex checkout_key; bezpečnostní hlavičky se porovnávají. Kontroly checksumů
všech tabulek před/po. Chrome na11.4: katalog a filtr, desktop/light a
mobil/dark, cold/warm, 3+11 párů; CDP skutečný CSS payload, formuláře,
styly/geometrie, 4 pixelové páry. Bez mockování a změn produkčních dat.

Předem dané brány: ≥20% zisk velkých nativních a celého přehledového HTTP
případů na obou DB, malé ≤20%+5ms funkce/+20ms HTTP. Všechny směry
a paměť zveřejnit. Celé CI, přesný sloučený strom, chráněný deploy a živé
GET ověření čekají. Přihlášené produkční/lidské UAT a produkční profil NOT RUN.

Původní WIP `kis_rosters_admin.php` se zachovává v původním worktree,
SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
P11-REVIEW odmítnutý kvůli nestabilnímu zisku; důkazy v
[plánu](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-review--agregace-kontroly-importovaného-katalogu-17-11-2026).
