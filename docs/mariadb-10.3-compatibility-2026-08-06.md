# Statická kontrola kompatibility s produkční MariaDB 10.3

Datum: 2026-08-06. Podnět: vlastník 2026-08-05 v phpMyAdmin ověřil, že produkční
DB server (`replikant3544`) běží MariaDB **10.3.39** na Debian 10, klientské
připojení bez SSL v privátní síti `10.5.x.x`. To je dnes nejstarší a jediné
netestované prostředí projektu — localhost XAMPP je `10.4.32`, CI dosud běželo
jen na `11.4`. Tento dokument je čistě statická analýza; k produkci se
nepřipojuje a nic v ní nemění. Otevřené provozní rozhodnutí (upgrade hostingu
vs. vědomé setrvání na 10.3/Debian 10 po EOL) zůstává u vlastníka — viz
poznámka v [SESSION_HANDOFF.md](plan-eshop-tymova-evidence/SESSION_HANDOFF.md#poslední-známý-důkazní-snapshot).

## Rozsah a metoda

Prohledáno: `migrations/*.php` (49 souborů), `includes/auto_migrace.php`,
`includes/migration_runner.php`, `db.php` a zbytek first-party PHP (vše mimo
`vendor/`) — přes 470 souborů. Metoda: cílený grep na konstrukce, které
MariaDB přidala až po 10.3, plus ruční přečtení migrační infrastruktury
(`auto_migrace.php`, `migration_runner.php`, `db.php`) a obou souborů, které
grep označil jako obsahující `CHECK`. Verze funkcí níže jsou ověřené proti
oficiální MariaDB dokumentaci (viz zdroje), ne jen z paměti.

## Nálezy podle konstrukce

| Konstrukce | Verze MariaDB | Nalezeno v first-party kódu | Závěr |
|---|---|---|---|
| `INSERT ... RETURNING` | 10.5.0 | ne | bez nálezu |
| `JSON_TABLE()` | 10.6.0 | ne | bez nálezu |
| `JSON_ARRAYAGG()` / `JSON_OBJECTAGG()` | 10.5.0 | ne | bez nálezu |
| `WITH ... AS (...)` / `WITH RECURSIVE` (CTE) | 10.2.0 (pod podlahou) | ne | bez nálezu |
| Window funkce `... OVER (...)` | 10.2.0 (pod podlahou) | ne | bez nálezu |
| `INTERSECT` / `EXCEPT` | 10.3.0 (na podlaze) | ne | bez nálezu |
| `CREATE SEQUENCE` | 10.3.0 (na podlaze) | ne | bez nálezu |
| `WITH SYSTEM VERSIONING` (temporální tabulky) | 10.3.4 (na podlaze) | ne | bez nálezu |
| `SKIP LOCKED` / `NOWAIT` | 10.6.0 | ne | bez nálezu |
| `LATERAL` derived table | 10.6.0 | ne | bez nálezu |
| `INVISIBLE` sloupce | 10.3.1 (na podlaze) | ne | bez nálezu |
| Nekonstantní `DEFAULT (výraz)` | 10.2.1 (pod podlahou) | ne (viz níže) | bez nálezu |
| `ALGORITHM=INSTANT/NOCOPY` explicitně na `ALTER TABLE` | — | ne | bez nálezu, viz níže |
| `CHECK (...)` constraint | enforcement od 10.2.1 (pod podlahou) | ano, 1× v produkčním MySQL/MariaDB kódu | kompatibilní, viz níže |
| `utf8mb4_0900_*` kolace (MySQL 8.0-only) | n/a (MySQL, ne MariaDB) | ne | bez nálezu |

### Detaily k nálezům, které vyžadovaly bližší pohled

**`DEFAULT\s*\(` falešné shody.** Prvotní case-insensitive grep vrátil 19
souborů, ale všechny shody byly JS/PHP identifikátory typu `toggleDefault(`,
`isDefault(`, `setDefault(` — substring `Default(` uvnitř camelCase názvu.
Case-sensitivní opakování (SQL v tomto repozitáři je vždy velkými písmeny)
nevrátilo nic. I kdyby se nekonstantní `DEFAULT (výraz)` použil, je dostupný
od 10.2.1, tedy pod podlahou 10.3 — nejde o hranici 10.3/10.4.

**`CHECK` constraint — dvě shody, jen jedna reálná.**
- [`migrations/20260803190000_club_event_notifications.php:78`](../migrations/20260803190000_club_event_notifications.php)
  má `CHECK(status IN (...))`, ale je to uvnitř větve `$driver !== 'mysql'`
  (SQLite testovací schéma) — proti MariaDB se nikdy neprovede. MySQL větev
  (řádek 52) používá `ENUM(...)`. Nejde o produkční konstrukci.
- [`migrations/20260804130000_training_roster_bridge.php:49`](../migrations/20260804130000_training_roster_bridge.php)
  má `CONSTRAINT chk_training_roster_owner CHECK ((plan_id IS NULL) <> (trenink_id IS NULL))`
  uvnitř `$mysql ? ... :` větve — **tohle se skutečně provede** na produkční
  MariaDB. MariaDB `CHECK` constrainty jsou syntakticky přijímané odjakživa,
  ale reálně vynucované (ne jen parsované a ignorované) až od **10.2.1** —
  produkční podlaha 10.3.39 je nad touto verzí, takže constraint funguje
  stejně na 10.3, 10.4 i 11.4. Beze změny.

**`ALGORITHM=INSTANT` — proč to hledat.** `includes/auto_migrace.php` dělá
desítky `ALTER TABLE ... ADD COLUMN ... AFTER sloupec`. Instant přidání
sloupce na libovolnou pozici (ne jen na konec) MariaDB podporuje až od 10.4;
10.3 umí instant `ADD COLUMN` jen na konec tabulky. Kdyby kód explicitně
vynucoval `ALGORITHM=INSTANT`, non-trailing `ADD COLUMN` by na 10.3 tvrdě
selhal. Žádné `ALGORITHM=` se v repozitáři nepoužívá — bez explicitní volby
si MariaDB sama vybere dostupný algoritmus (na 10.3 spadne zpět na
COPY/INPLACE, jen pomaleji, ne s chybou). Bez nálezu, jen rozdíl ve výkonu
migrace, ne v korektnosti.

**utf8mb4 kolace.** Celý repozitář používá výhradně `utf8mb4_unicode_ci`
(naprostá většina) a `utf8mb4_general_ci` (pár legacy míst) a vždy explicitním
`COLLATE=` — nikde se nespoléhá na výchozí kolaci serveru/databáze. Obě
pojmenované kolace jsou v MariaDB 10.3, 10.4 i 11.4 identické (založené na
Unicode-4.0.0/UCA). Nové UCA kolace (`utf8mb4_uca1400_ai_ci` apod.) přibyly
až v 10.10.1 (a `uca0900_ai_ci` varianty v 11.4.5 kvůli replikaci z MySQL
8.0) jako doplňkové, nepřepisují význam `utf8mb4_unicode_ci`. Žádné riziko
driftu mezi produkční 10.3.39, localhost 10.4.32 a CI 11.4.

**Migrační infrastruktura.** `db.php`, `includes/auto_migrace.php` a
`includes/migration_runner.php` byly přečteny celé. Používají jen `CREATE
TABLE [IF NOT EXISTS]`, `ALTER TABLE ADD/MODIFY COLUMN`, `ENUM`, `INSERT
IGNORE`, `INSERT ... ON DUPLICATE KEY UPDATE ... VALUES(...)`,
`GET_LOCK`/`RELEASE_LOCK`, dotazy do `information_schema` a `SHOW COLUMNS` —
vše dostupné v MariaDB od verzí hluboko pod 10.3. `bin/db-backup.php`
nevolá `mysqldump` jako externí proces (žádné riziko verzově odlišných
příznaků dump nástroje); export dělá čistě přes PDO a
`START TRANSACTION WITH CONSISTENT SNAPSHOT`, což je dostupné už v MySQL 4.1
a raném MariaDB.

## Závěr

**Bez nálezu.** Statická kontrola nenašla žádnou reálnou nekompatibilitu
first-party SQL s MariaDB 10.3. Nic se preventivně nepřepisovalo. Jediná
kódová změna v tomto řezu je oprava jednoho assertion řádku v
[`tests/Unit/DeployWorkflowContractTest.php`](../tests/Unit/DeployWorkflowContractTest.php),
nutná proto, že CI workflow nově používá maticovou verzi místo natvrdo
zapsané `mariadb:11.4` (viz níže) — nejde o kompatibilní opravu, ale o
aktualizaci testu na novou očekávanou strukturu workflow souboru.

## CI matice 10.3 / 11.4

`.github/workflows/tests.yml`, job `mariadb-smoke`, dostal `strategy.matrix`
se dvěma verzemi (`'10.3'`, `'11.4'`); `services.mariadb.image` nyní
interpoluje `${{ matrix.mariadb-version }}` místo pevné `mariadb:11.4`.
Všechny čtyři existující smoke kroky (`ChildAccessMariaDbSmoke.php`,
`KisHobbyTransitionMariaDbSmoke.php`, `DatabaseBackupMariaDbSmoke.php`,
`bin/sports-review-smoke.php`) zůstaly beze změny a poběží v obou paralelních
jobech. Žádný z nich není z principu blokovaný na 10.3 — všechny používají
jen SQL ověřené výše jako kompatibilní. Workflow nebyl spuštěn (zakázáno
zadáním); syntaxe byla ověřena ručně (žádný YAML linter nebyl v prostředí
dostupný) podle standardního GitHub Actions `strategy.matrix` vzoru.

## Lokální ověření (jen 10.4, ne 10.3)

10.3 není na tomto XAMPP k dispozici — poctivě přiznáno, ne zamlčeno. Lokálně
ověřeno proti reálné izolované MariaDB **10.4.32** (mezi produkční podlahou a
CI verzí):

```
MariaDB child access smoke OK
MariaDB hobby transition smoke OK
MariaDB database backup smoke OK (100 tables)
MariaDB sports review smoke OK — 5 measurements (1 v1), 3 race results (1 v1),
7 legacy text rows (2 recognized/5 ambiguous), 8 inventory records, 8 findings
```

Po běhu `SHOW DATABASES` potvrdilo, že žádná izolovaná testovací databáze
(`evidence_m18_child_test`, `evidence_m19_transition_test`,
`evidence_backup_smoke_test`, `evidence_sports_review_smoke_test`) nezůstala
a `evidence` je nedotčená. Skutečný běh na 10.3 proběhne až v CI matici po
autorizovaném spuštění workflow — to není součástí tohoto řezu.

## Zdroje

- [INSERT...RETURNING | MariaDB Documentation](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/inserting-loading-data/insertreturning) — 10.5.0
- [JSON_TABLE | MariaDB Documentation](https://mariadb.com/docs/server/reference/sql-functions/special-functions/json-functions/json_table) — 10.6.0
- [CONSTRAINT | MariaDB Documentation](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint) — CHECK enforcement od 10.2.1
- [Supported Character Sets and Collations | MariaDB Documentation](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations)
- [MDEV-27009 — Add UCA-14.0.0 collations](https://jira.mariadb.org/browse/MDEV-27009) — nové UCA kolace od 10.10.1/11.4.5, `utf8mb4_unicode_ci` beze změny
