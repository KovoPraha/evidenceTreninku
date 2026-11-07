# P11 — katalog: parita a párové měření 7. 11. 2026

Stav: implementace, striktní parita a finální nativní měření PASS; PR #364.
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

Finální runtime `623dcaa5`, integrovaný beze změny kódu v `0252ba58`.
Párová nativní parita **PASS na obou MariaDB**, 8 scénářů × 30 párů × 2 DB
= **480 párů / 960 měřených volání**, mimo zahřátí a instrumentaci.
[Report 10.3](ci-final-10.3/catalog-page-comparison.json),
[report 11.4](ci-final-11.4/catalog-page-comparison.json),
[ověření otisků a vzorků](verification.json).
Všech 9 otisků zdrojů v obou reportech odpovídá přesným Git blobům `0252ba58`.
Všech 10 vlastních náhodných schémat (5 na DB) odstraněno přes `finally`.

| MariaDB / případ | Medián ms před → po | p95 ms před → po | PHP MiB před → po | SQL před → po | řádky před → po |
| --- | --- | --- | --- | --- | --- |
| 10.3 / 12 zboží | 2.57 → 2.69 | 3.10 → 3.13 | 0.21 → 0.24 | 15 → 16 | 70 → 82 |
| 10.3 / 1000 zboží | 31.16 → 12.00 | 39.00 → 13.68 | 14.33 → 3.37 | 15 → 24 | 5010 → 2141 |
| 10.3 / 5000 zboží | 202.18 → 87.13 | 208.62 → 90.65 | 71.59 → 16.82 | 23 → 40 | 25010 → 10141 |
| 10.3 / 5000, člen / cena / strana 2 | 238.17 → 121.34 | 299.63 → 161.99 | 71.59 → 24.89 | 45 → 62 | 30013 → 15144 |
| 10.3 / 5000, hledání / strana 400 | 279.65 → 174.95 | 310.13 → 189.71 | 71.59 → 17.46 | 23 → 90 | 25010 → 15141 |
| 10.3 / 5000, prázdná kategorie | 205.12 → 76.60 | 225.92 → 90.50 | 71.59 → 16.76 | 23 → 32 | 25010 → 10075 |
| 10.3 / 15 programů | 11.92 → 11.95 | 12.62 → 12.54 | 0.31 → 0.35 | 58 → 59 | 236 → 251 |
| 10.3 / 80 programů | 37.77 → 36.81 | 38.83 → 46.10 | 1.49 → 0.75 | 188 → 195 | 1016 → 931 |
| 11.4 / 12 zboží | 5.42 → 5.66 | 6.53 → 7.47 | 0.21 → 0.24 | 15 → 16 | 70 → 82 |
| 11.4 / 1000 zboží | 58.65 → 35.55 | 60.55 → 37.16 | 14.33 → 3.37 | 15 → 24 | 5010 → 2141 |
| 11.4 / 5000 zboží | 340.46 → 198.57 | 350.43 → 208.14 | 71.59 → 16.82 | 23 → 40 | 25010 → 10141 |
| 11.4 / 5000, člen / cena / strana 2 | 407.83 → 260.62 | 417.85 → 273.00 | 71.59 → 24.89 | 45 → 62 | 30013 → 15144 |
| 11.4 / 5000, hledání / strana 400 | 417.03 → 303.08 | 427.73 → 306.99 | 71.59 → 17.46 | 23 → 90 | 25010 → 15141 |
| 11.4 / 5000, prázdná kategorie | 314.03 → 165.11 | 323.56 → 172.19 | 71.59 → 16.76 | 23 → 32 | 25010 → 10075 |
| 11.4 / 15 programů | 22.93 → 23.12 | 24.34 → 24.36 | 0.31 → 0.35 | 58 → 59 | 236 → 251 |
| 11.4 / 80 programů | 89.54 → 90.84 | 93.54 → 93.98 | 1.49 → 0.75 | 188 → 195 | 1016 → 931 |

5000 zboží: plné varianty **5000 → 12**, obrázkové řádky **15000 → 36**.
Lehký katalog a sonda zůstávají globální, proto celkové načtené řádky neklesají
na 12 a SQL je více. Malé katalogy mají pouze režii sondy; u 80 programů dál
dominuje sdílené čtení kapacity a podmínek. Žádný údaj se nepřenáší mezi GET.
Místní nedokončená objemová měření při úpravách byla ukončená a nepoužívají se.

Globální lehké kandidáty, ceny pro řazení, prodejnost a programový kontext jsou
stále O(katalog); nad 64 produkty se omezují těžká data na stránku 12 produktů,
nikoli na 12 variant při více variantách jednoho produktu. Vyhledávání čte
souhrny po 100 produktech. Více malých SQL je vědomý kompromis za menší přenos
obrázků/JSON a méně prezentace. Běžný GET nemá nový atomický snapshot;
transakční cesta i aktuální kontroly checkoutu zůstávají kanonické.
Plné HTTP/bootstrap/košík/navigace, browser a produkční přejímka nového katalogu **NOT RUN**.
Samostatné [HTTP/browser měření #363](../full-http-performance-20261107/RESULTS.md)
na starší verzi `feb8fe8c` zůstává zachované; nejde o přeměření této optimalizace.
Časy nejsou produkční latence ani slib celkového vykreslení stránky.
Katalogový PR se neslučuje automaticky; deploy/aktivace nejsou povolené.

## Regrese a provozní hranice při předání

Měřený runtime `0252ba58`: Linux **2350 testů / 45220 assertions PASS**,
navíc unixové soukromé úložiště 3/45 a Python 5/5. PHPStan/styl i kontrola
dokumentace PASS. Úplné MariaDB joby a poslední dokumentační head mají živý
stav v [PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364);
katalogové profilovací kroky 10.3/11.4 jsou dokončené PASS.
V čtecí inventuře před převzetím #361 byl poslední deploy `42362258878` mířil na `9460ee76`,
před merge cen; samostatný produkční handoff #362 se přebírá bez opakování operací.
Tato práce neprovedla deploy, aktivaci, změnu skutečných dat ani odesílání.

Před předáním převzatý také #361 / `3b37e053`. Jde o oddělenou prezentaci
přístupů; katalogové zdroje a testy se nezměnily. Nejnovější integrační CI
běží u PR; úspěšná plná CI měřeného runtime zůstává `42365054949`.
