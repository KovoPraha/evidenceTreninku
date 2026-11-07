# P11 — katalog: parita a párové měření 7. 11. 2026

Stav: implementace a cílená parita PASS, finální nativní měření probíhá.
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md),
[předání](../../SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md),
[PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364).

## Začlenění cen a ochrana rozpracované práce

PR #358 sloučený na výslovný pokyn vlastníka jako
`37d0ad687211aa912a90e8ad105842847956890d`, head `02ad8016` po 11 CI PASS
(Testy `42362702007`). Produkční workflow se touto prací nespouštělo.
Katalog ve vlastní větvi `codex/catalog-read-performance-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-catalog-performance-20261107`.
Původní checkout `C:/xampp/htdocs/evidencePavel` se nepřepínal, cizí WIP
`kis_rosters_admin.php` má nadále SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Metoda a rozsah shody

Reference `tests/Support/CatalogPageBaseline.php` obsahuje zmrazený katalogový
řadič z main `37d0ad68` po #358; normalizovaný formát a obal pro explicitní vstup.
Defaultní úplné API bez výběru ID zachovává původní algoritmus. Nový reader
porovnáváme striktním `===` celého viditelného datového stavu: vybrané plné
karty včetně pořadí/typů variant, atributů, obrázků, programových nabídek,
veřejných a členských cen, filtry/štítky, stránka, celkové počty a menu.

Cíleně **31 testů / 2080 assertions PASS**, PHP 8.2.12 / SQLite, Windows.
[JUnit](targeted-junit.xml). Hranice 64/65, všechny druhy řazení, Unicode
hledání v názvu a popisu včetně přechodu mezi nimi a doslovných `%`/`_`,
prázdný výsledek, krajní stránka, kategorie, skryté a vícečetné varianty,
sklad 0/NULL, importy 157–159, způsobilost/odvolání, tarify a programové
podmínky/kapacita. Transakce vrací původní SQL/parametry/řádky; explicitně
ověřená hodnota aktuálních hodin se při porovnání tvaru SQL normalizuje,
protože dvě původní volání mohou překročit sekundu. Cenové výsledky zůstávají striktní.

Nativní profiler: `php tests/Support/CatalogPageProfile.php --mariadb` pouze
s `EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY`, vlastním localhost portem
`EVIDENCE_MARIADB_SMOKE_PORT` a ověřeným `EVIDENCE_PERFORMANCE_DATADIR`.
V CI izolovaný kontejner, root bez hesla, náhodné vlastní schéma, odstraněné ve
`finally`. Ovladač má `PDO::ATTR_EMULATE_PREPARES=false`; žádný config,
produkční dump ani připojení. Fixture má 12/1000/5000 kusů zboží a 15/80 programů,
syntetické popisy (~1,4 kB), čtyři atributy a tři bezpečné místní URL obrázků
na produkt. Žádné skutečné fotografie, lidé, platby ani transporty.
Schéma je syntetický kontrakt s pomocnými indexy, nikoli kopie živého schématu.

Pět zahřátí, 30 střídavých neinstrumentovaných PDO měření každé varianty ve
stejném procesu nad stejnými daty. Medián průměr prostředních dvou, p95 29. z 30.
SQL/vrácené řádky a EXPLAIN odděleně zahřátým `QueryProfilePdo`. Špička je
přírůstek používané paměti PHP během operace, nikoli RSS celého procesu.
Reporty obsahují všechny vzorky, SQL šablony, EXPLAIN, otisk výsledku i zdrojů.

## První měření před doplněním malé kanonické cesty

Měřený `dcb8f391`, CI `42363899233`. Nativní katalogové kroky obou MariaDB PASS;
plný Linux měl jednu zastaralou wiring kontrolu místa konstanty 12. Opraveno,
kvalita a dokumentace PASS. Tyto časy nejsou výsledkem finální heuristiky.
[Surový report 10.3](ci-10.3/catalog-page-comparison.json).

MariaDB 10.3: 5000 produktů 288,35 → 127,09 ms; PHP špička 71,59 → 16,78 MiB;
plné varianty 5000 → 12, obrázky 15000 → 36, SQL 23 → 39.
12 produktů ale 3,67 → 5,63 ms, 15 programů 17,10 → 19,44 ms. Proto
následná verze používá do 64 aktivních produktů původní cestu po sondě max. 65 ID.
Sonda vybírá jen strategii; nikdy neurčuje prodejnost, stránku ani počty.

## Finální ověření a otevřené hranice

Finální runtime `623dcaa5`, nativní měření a úplné CI zatím probíhají.
Doplnit reporty, finální SHA, výsledky a stav review po skutečném dokončení.
Místní nedokončená objemová měření během úprav byla ukončená a nepoužívají se.

Globální lehké kandidáty, ceny pro řazení, prodejnost a programový kontext jsou
stále O(katalog); nad 64 produkty se omezují těžká data na stránku 12 produktů,
nikoli na 12 variant při více variantách jednoho produktu. Vyhledávání čte
souhrny po 100 produktech. Více malých SQL je vědomý kompromis za menší přenos
obrázků/JSON a méně prezentace. Běžný GET nemá nový atomický snapshot;
transakční cesta i aktuální kontroly checkoutu zůstávají kanonické.
Plné HTTP/bootstrap/košík/navigace, browser a produkční přejímka **NOT RUN**.
Časy nejsou produkční latence ani slib celkového vykreslení stránky.
Katalogový PR se neslučuje automaticky; deploy/aktivace nejsou povolené.
