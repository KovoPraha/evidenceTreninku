# P11-ASSET14 — kategorie a parametry e-shopu

17. 11. 2026, Europe/Prague. **K OVĚŘENÍ; native/Chrome/CI/merge/deploy NOT RUN.**
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset14--kategorie-a-parametry-e-shopu-17-11-2026).
Base e30fdc97a369245478c25fd14c220df45aec3e18,
větev codex/next-performance-20261117, vlastní worktree mimo původní htdocs.

Přesně dva Bootstrap CSS tagy odebrané; helpery, SQL pravidla, autentizace,
práva, writery a migrace zachované. Povinné formátování minifikovaných šablon
má zdrojový kontrakt proti immutable baseline: 14 konkrétních editů kategorií,
7 parametrů (závorky, hodnotově stejný SQL řetězec a emise stejného icon tagu).
Jiné tokeny ani literální HTML se neliší. [Kontrakt](source-contract.json).

Lokálně: source contract PASS, SharedUiShell a SecurityInfrastructureWiring
30 testů / 184 assertions PASS, cílený PHPStan bez chyb. Statický SRI test
včetně negativních případů beze změny. Python/JS syntaxe a docs/diff PASS.

Připraveno měření skutečných původních/nových stránek na jednom vlastním
syntetickém serveru. Všechny pracovní role a anonymní přístup, hledání,
prázdný filtr, vybraná kategorie/produkt, chybná cesta/ID, neaktivní textový,
číselný i výběrový parametr s historickou neaktivní volbou, nový klíč,
a odmítnutí neplatného CSRF. Žádné skutečné objednávky nebo role se nemění.

Chrome přehled/detail obou stránek, desktop světlý a mobil tmavý, cold/warm:
16 případů, 352 měřených + 96 warmup navigací, osm pixelových párů.
Celé HTTP/hlavičky/CSRF stejné po odstranění přesného tagu a nonce;
kontrola neměnných DB checksumů a bezpečného úklidu. Cílem je měřený
přenosový zisk, časy zveřejnit i při nulovém nebo opačném rozdílu.

Další krok: oba nativní MariaDB profily, skutečný Chrome a všechny CI.
Původní WIP kis_rosters_admin.php/d1221f35 zachovaný se stejným SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5.
Přihlášené produkční UAT NOT RUN. Bez PASS nevydávat.
