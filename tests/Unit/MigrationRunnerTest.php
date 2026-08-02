<?php
declare(strict_types=1);

namespace Tests\Unit;

use Closure;
use EvidenceMigrationCatalog;
use EvidenceMigrationException;
use EvidenceMigrationExit;
use EvidenceMigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

final class MigrationRunnerTest extends TestCase
{
    public function testLegacyBaselineIsFrozenAndClassifiedFailClosed(): void
    {
        self::assertSame('2.20.2', \LEGACY_SCHEMA_VERSION);
        self::assertSame(\LEGACY_SCHEMA_VERSION, \SCHEMA_VERSION);
        self::assertSame('missing', \evidence_legacy_schema_state(''));
        self::assertSame('pending', \evidence_legacy_schema_state('2.20.1'));
        self::assertSame('current', \evidence_legacy_schema_state('2.20.2'));
        self::assertSame('ahead', \evidence_legacy_schema_state('2.20.3'));
        self::assertSame('invalid', \evidence_legacy_schema_state('release-2.20.2'));
    }

    public function testCheckIsReadOnlyWhenLedgerDoesNotExist(): void
    {
        $pdo = $this->databaseAtBaseline();
        $runner = new EvidenceMigrationRunner($pdo, []);

        $result = $runner->check();

        self::assertFalse($result['current']);
        self::assertSame('ledger_initialization_pending', $result['reason']);
        self::assertFalse($result['ledger_exists']);
        self::assertFalse($this->tableExists($pdo, EvidenceMigrationRunner::LEDGER_TABLE));
    }

    public function testApplyInitializesEmptyLedgerAndBecomesCurrent(): void
    {
        $pdo = $this->databaseAtBaseline();
        $runner = new EvidenceMigrationRunner($pdo, []);

        $result = $runner->apply();

        self::assertTrue($result['current']);
        self::assertSame('current', $result['reason']);
        self::assertTrue($this->tableExists($pdo, EvidenceMigrationRunner::LEDGER_TABLE));
        self::assertSame(
            EvidenceMigrationRunner::baselineChecksum(),
            $pdo->query(
                "SELECT checksum FROM evidence_schema_migrations WHERE id = '0000_legacy_2_20_2'"
            )->fetchColumn()
        );
    }

    public function testApplyRunsPendingMigrationAndRecordsItsChecksum(): void
    {
        $pdo = $this->databaseAtBaseline();
        $catalog = $this->catalog([
            '20260801120000_create_example' => static function (PDO $connection): void {
                $connection->exec('CREATE TABLE example (id INTEGER PRIMARY KEY)');
            },
        ]);
        $runner = new EvidenceMigrationRunner($pdo, $catalog);

        $result = $runner->apply();

        self::assertTrue($result['current']);
        self::assertTrue($this->tableExists($pdo, 'example'));
        self::assertSame(
            $catalog['20260801120000_create_example']['checksum'],
            $pdo->query(
                "SELECT checksum FROM evidence_schema_migrations WHERE id = '20260801120000_create_example'"
            )->fetchColumn()
        );
    }

    public function testApplyCanBringLowerLegacySchemaToExactBaseline(): void
    {
        $pdo = $this->databaseWithLegacyVersion('2.20.1');
        $legacyApplier = static function (PDO $connection): void {
            $statement = $connection->prepare(
                "UPDATE nastaveni SET hodnota = :version WHERE klic = 'schema_version'"
            );
            $statement->execute(['version' => \LEGACY_SCHEMA_VERSION]);
        };
        $runner = new EvidenceMigrationRunner($pdo, [], $legacyApplier);

        $result = $runner->apply();

        self::assertTrue($result['current']);
        self::assertSame(
            \LEGACY_SCHEMA_VERSION,
            $pdo->query("SELECT hodnota FROM nastaveni WHERE klic = 'schema_version'")->fetchColumn()
        );
    }

    public function testHigherLegacyVersionFailsWithoutDowngrade(): void
    {
        $pdo = $this->databaseWithLegacyVersion('2.21.0');
        $runner = new EvidenceMigrationRunner($pdo, []);

        try {
            $runner->apply();
            self::fail('Expected fail-closed integrity exception.');
        } catch (EvidenceMigrationException $exception) {
            self::assertSame(EvidenceMigrationExit::INTEGRITY, $exception->exitCode);
            self::assertSame('legacy_ahead', $exception->reason);
        }

        self::assertSame(
            '2.21.0',
            $pdo->query("SELECT hodnota FROM nastaveni WHERE klic = 'schema_version'")->fetchColumn()
        );
        self::assertFalse($this->tableExists($pdo, EvidenceMigrationRunner::LEDGER_TABLE));
    }

    public function testUnknownAppliedMigrationFailsClosed(): void
    {
        $pdo = $this->databaseAtBaseline();
        (new EvidenceMigrationRunner($pdo, []))->apply();
        $pdo->exec(
            "INSERT INTO evidence_schema_migrations (id, checksum) VALUES ('20260101000000_unknown', '"
            . str_repeat('a', 64) . "')"
        );

        $this->expectException(EvidenceMigrationException::class);
        $this->expectExceptionMessage('neznamou aplikovanou migraci');
        (new EvidenceMigrationRunner($pdo, []))->check();
    }

    public function testMissingBaselineRowIsReportedAsPendingWithoutWrite(): void
    {
        $pdo = $this->databaseAtBaseline();
        $pdo->exec(
            'CREATE TABLE evidence_schema_migrations ('
            . 'id TEXT PRIMARY KEY, checksum TEXT NOT NULL, '
            . 'applied_at TEXT DEFAULT CURRENT_TIMESTAMP, execution_ms INTEGER DEFAULT 0)'
        );

        $result = (new EvidenceMigrationRunner($pdo, []))->check();

        self::assertFalse($result['current']);
        self::assertSame('baseline_ledger_pending', $result['reason']);
        self::assertSame([EvidenceMigrationRunner::BASELINE_ID], $result['pending']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM evidence_schema_migrations')->fetchColumn());
    }

    public function testChangedBaselineChecksumFailsClosed(): void
    {
        $pdo = $this->databaseAtBaseline();
        (new EvidenceMigrationRunner($pdo, []))->apply();
        $pdo->exec(
            "UPDATE evidence_schema_migrations SET checksum = '" . str_repeat('0', 64)
            . "' WHERE id = '0000_legacy_2_20_2'"
        );

        try {
            (new EvidenceMigrationRunner($pdo, []))->check();
            self::fail('Expected baseline checksum exception.');
        } catch (EvidenceMigrationException $exception) {
            self::assertSame(EvidenceMigrationExit::INTEGRITY, $exception->exitCode);
            self::assertSame('baseline_checksum_mismatch', $exception->reason);
        }
    }

    public function testChangedChecksumFailsClosed(): void
    {
        $pdo = $this->databaseAtBaseline();
        $catalog = $this->catalog([
            '20260801120000_example' => static function (PDO $connection): void {
                $connection->exec('CREATE TABLE checksum_example (id INTEGER)');
            },
        ]);
        (new EvidenceMigrationRunner($pdo, $catalog))->apply();
        $changed = $catalog;
        $changed['20260801120000_example']['checksum'] = str_repeat('f', 64);

        try {
            (new EvidenceMigrationRunner($pdo, $changed))->check();
            self::fail('Expected checksum integrity exception.');
        } catch (EvidenceMigrationException $exception) {
            self::assertSame(EvidenceMigrationExit::INTEGRITY, $exception->exitCode);
            self::assertSame('applied_checksum_mismatch', $exception->reason);
        }
    }

    public function testCatalogLoadsOnlyStrictImmutableFileContract(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-migrations-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0700));
        $file = $directory . DIRECTORY_SEPARATOR . '20260801123000_example.php';
        file_put_contents($file, <<<'PHP'
<?php
return [
    'id' => '20260801123000_example',
    'up' => static function (PDO $pdo): void {},
    'verify' => static function (PDO $pdo): bool { return true; },
];
PHP);

        try {
            $catalog = EvidenceMigrationCatalog::load($directory);
            self::assertSame(hash_file('sha256', $file), $catalog['20260801123000_example']['checksum']);
            self::assertInstanceOf(Closure::class, $catalog['20260801123000_example']['up']);
        } finally {
            unlink($file);
            rmdir($directory);
        }
    }

    public function testFailedPostconditionDoesNotRecordMigration(): void
    {
        $pdo = $this->databaseAtBaseline();
        $catalog = [
            '20260801124500_unverified' => [
                'id' => '20260801124500_unverified',
                'checksum' => hash('sha256', 'unverified'),
                'up' => static function (PDO $connection): void {
                    $connection->exec('CREATE TABLE unverified_change (id INTEGER)');
                },
                'verify' => static fn (PDO $connection): bool => false,
            ],
        ];

        try {
            (new EvidenceMigrationRunner($pdo, $catalog))->apply();
            self::fail('Expected postcondition failure.');
        } catch (EvidenceMigrationException $exception) {
            self::assertSame('migration_postcondition_failed', $exception->reason);
        }

        self::assertTrue($this->tableExists($pdo, 'unverified_change'));
        self::assertSame(
            0,
            (int)$pdo->query(
                "SELECT COUNT(*) FROM evidence_schema_migrations "
                . "WHERE id = '20260801124500_unverified'"
            )->fetchColumn()
        );
    }

    public function testLegacyAutoMigrationWritesOnlyFrozenBaselineConstant(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/auto_migrace.php');

        self::assertIsString($source);
        self::assertStringContainsString('evidence_legacy_schema_state($verze)', $source);
        self::assertStringContainsString('execute([LEGACY_SCHEMA_VERSION])', $source);
        self::assertStringNotContainsString('execute([SCHEMA_VERSION])', $source);
    }

    private function databaseAtBaseline(): PDO
    {
        return $this->databaseWithLegacyVersion(\LEGACY_SCHEMA_VERSION);
    }

    private function databaseWithLegacyVersion(string $version): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE nastaveni (klic TEXT PRIMARY KEY, hodnota TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO nastaveni (klic, hodnota) VALUES (:key, :value)');
        $statement->execute(['key' => 'schema_version', 'value' => $version]);
        return $pdo;
    }

    /**
     * @param array<string, Closure> $migrations
     * @return array<string, array{id:string, checksum:string, up:Closure, verify:Closure}>
     */
    private function catalog(array $migrations): array
    {
        $catalog = [];
        foreach ($migrations as $id => $up) {
            $catalog[$id] = [
                'id' => $id,
                'checksum' => hash('sha256', $id),
                'up' => $up,
                'verify' => static fn (PDO $connection): bool => true,
            ];
        }
        return $catalog;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table"
        );
        $statement->execute(['table' => $table]);
        return (bool)$statement->fetchColumn();
    }
}
