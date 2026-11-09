<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/diagnose-athlete-read-schema.php';
require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';

final class AthleteReadSchemaInventoryTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testOnlyFixedMetadataIsReturnedAndDomainRowsRemainUntouched(): void
    {
        $pdo = $this->database();
        $tables = ['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka', 'secret_other'];
        foreach ($tables as $table) {
            $pdo->exec('CREATE TABLE ' . $table . '(id INTEGER PRIMARY KEY,sportovec_id INT,mereni_id INT,note TEXT)');
            $pdo->exec("INSERT INTO " . $table . " VALUES(1,44,22,'PRIVATE_RECORD_VALUE')");
        }
        $pdo->exec('CREATE INDEX idx_sportovec ON mereni_zaznamy(sportovec_id);
            CREATE INDEX idx_mereni ON trenink_mereni(mereni_id,id)');
        $snapshot = static fn (): array => array_map(
            static fn (string $table): array => $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC),
            $tables
        );
        $before = $snapshot();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec('START TRANSACTION READ ONLY');
        } else {
            $pdo->beginTransaction();
        }
        $inventory = \athleteReadSchemaInventory($pdo);
        $pdo->rollBack();
        self::assertSame($before, $snapshot());
        self::assertFalse($inventory['domain_rows_read']);
        self::assertFalse($inventory['ddl_executed']);
        self::assertCount(5, $inventory['tables']);
        self::assertArrayNotHasKey('secret_other', $inventory['tables']);
        self::assertStringNotContainsString('PRIVATE_RECORD_VALUE', json_encode($inventory, JSON_THROW_ON_ERROR));
        foreach ($inventory['tables'] as $table) {
            self::assertTrue($table['exists']);
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                self::assertNull($table['rows_estimate']);
            } else {
                self::assertSame('InnoDB', $table['engine']);
            }
        }
        $indexes = array_values(array_filter(
            $inventory['tables']['trenink_mereni']['indexes'],
            static fn (array $index): bool => $index['name'] === 'idx_mereni'
        ));
        self::assertSame(['mereni_id', 'id'], array_column($indexes, 'column'));
        self::assertSame([1, 2], array_column($indexes, 'sequence'));
        $owner = array_values(array_filter(
            $inventory['tables']['mereni_zaznamy']['indexes'],
            static fn (array $index): bool => $index['name'] === 'idx_sportovec'
        ));
        self::assertSame('sportovec_id', $owner[0]['column']);
    }

    public function testMissingTablesAreReportedWithoutFallbackDomainReads(): void
    {
        $pdo = $this->database();
        $inventory = \athleteReadSchemaInventory($pdo);
        self::assertSame(array_fill(0, 5, false), array_column($inventory['tables'], 'exists'));
        foreach ($inventory['tables'] as $table) {
            self::assertSame([], $table['indexes']);
            self::assertSame([], $table['columns']);
        }
    }
}
