# P11-ATTR — objevování parametrů variant

17. 11. 2026, Europe/Prague. **Místní ověření PASS; MariaDB/HTTP/Chrome/CI/merge/deploy NOT RUN.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-attr--objevování-parametrů-variant-17-11-2026).
Base f8f1c17511a6826411b309d24910f37042dd5bf9,
větev codex/attribute-discovery-performance-20261117; vlastní worktree mimo původní htdocs.

Jediná funkční změna: shopAttributeDiscoveredKeys používá postupné fetch řádků
místo fetchAll a paměť přesně stejných surových JSON jen v rámci jednoho volání.
Maximálně 128 položek po 8192 B; velké hodnoty se dekódují pokaždé, žádná
kolace/normalizace raw klíče. SQL, PDO buffering, úplná množina výsledných
klíčů i jejich typy/řazení se nemění. Žádná perzistentní nebo sdílená cache.
Množina klíčů a samotný buffer PDO mohou dále růst s daty; nejde o konstantní
paměť celého požadavku ani serveru.

[Source contract](source-contract.json) potvrzuje přesnou zmrazenou původní
funkci a nezměněné jiné tokeny mimo discovery (40 konkrétních brace-only editů
vyžadovaných stylem). Původní funkce je v AttributeDiscoveryBaseline.txt,
CLI oracle z ní vytváří uzavřenou funkci; produkční kód eval nepoužívá.

Lokálně PASS: 25 testů / 124 kontrol (ShopAttribute* a nezměněný bezpečnostní
SRI kontrakt), PHPStan, styl, Python/JS syntaxe. Nová diferenciální sada:
Unicode/case/trim, pole a číselné klíče, skaláry/null, chybný JSON/UTF-8 a
hloubka, přesný velký dokument a cache eviction, čerstvost mezi voláními,
izolace připojení, transakční čtení/rollback, prázdná a chybějící tabulka.

Lokální diagnostika SQLite/PHP 8.2.12: 7 vzorků na pár, 1000/10000/30000
řádků po 16 klíčích. Při 30000 opakovaných JSON medián 230,04 → 75,15 ms,
při jedinečných 253,85 → 271,75 ms (+7,05 %). PHP peak přibližně
46,62 MB → 20,99 kB / 222,14 kB. [Vzorky](local-probe.json).
Tohle není čas ani paměť produkce/MariaDB; driver buffering se musí změřit.

Nativní plán: MariaDB 10.3/11.4, 11 střídavých párů po dvou warmupech,
2 SQL na volání, stejné klíče a checksumy; opakované/jedinečné 1k/10k/30k
plus historické hraniční JSON na BLOB bez kolace. Oba algoritmy se zahřejí
stejně před měřením, každý vzorek má vlastní PHP proces a měří také buffer PDO.
Gate repeated ≥20 % zisku; unique nejvýše 20 % + 5 ms režie.

Skutečné HTTP/Apache/PHP na plně migrované syntetické DB: 39 rolových/
kladných/záporných/CSRF scénářů při 1k repeated; další čtyři sady 10k/30k
repeated/unique s přehledem/detail cíle a kategoriemi jako kontrolou.
Celé HTML, hlavičky, formuláře a všechny checksumy shodné, jen nonce
normalizovaný. Gate repeated30k cílové celé HTTP ≥20 %, ostatní bez
regrese >20 % +20 ms. Chrome 16 cold/warm/desktop/mobil případů při 30k
unique, 352 měřených +96 warmup navigací, osm pixelových párů a stejné CSS.
Potom všechny CI, přesný merge a chráněný deploy se zálohou, invarianty,
smoke/úklidem; UAT/import false, přihlášené produkční UAT samostatně NOT RUN.
Původní fio/d1221f35 a kis_rosters_admin.php WIP zachovaný.
