<?php
declare(strict_types=1);

namespace Tests\Integration;

use EvidenceMigrationCatalog;
use EvidenceMigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

final class AuthSecurityMigrationTest extends TestCase
{
    public function testActualMigrationUpgradesIsolatedBaselineDatabaseAndIsIdempotent(): void
    {
        $pdo = $this->baselineDatabase();
        $catalog = EvidenceMigrationCatalog::load(dirname(__DIR__, 2) . '/migrations');
        $runner = new EvidenceMigrationRunner($pdo, $catalog);

        $first = $runner->apply();
        $second = $runner->apply();

        self::assertTrue($first['current']);
        self::assertTrue($second['current']);
        self::assertSame(
            ['20260802120000_auth_revocation_rate_limit'],
            array_keys($catalog)
        );
        self::assertSame(1, $this->sessionVersion($pdo, 'treneri', 1));
        self::assertSame(1, $this->sessionVersion($pdo, 'verejni_uzivatele', 1));
        self::assertTrue($this->tableExists($pdo, 'auth_login_limits'));
        self::assertTrue($this->indexExists($pdo, 'idx_auth_login_limits_blocked'));
        self::assertSame(
            1,
            (int)$pdo->query(
                "SELECT COUNT(*) FROM evidence_schema_migrations "
                . "WHERE id = '20260802120000_auth_revocation_rate_limit'"
            )->fetchColumn()
        );
    }

    private function baselineDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE nastaveni (klic TEXT PRIMARY KEY, hodnota TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO nastaveni (klic, hodnota) VALUES (:key, :value)');
        $statement->execute(['key' => 'schema_version', 'value' => \LEGACY_SCHEMA_VERSION]);

        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY, aktivni INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec('INSERT INTO treneri (id, aktivni) VALUES (1, 1)');
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY, aktivni INTEGER NOT NULL DEFAULT 1)'
        );
        $pdo->exec('INSERT INTO verejni_uzivatele (id, aktivni) VALUES (1, 1)');

        return $pdo;
    }

    private function sessionVersion(PDO $pdo, string $table, int $id): int
    {
        return (int)$pdo->query(
            'SELECT session_version FROM ' . $table . ' WHERE id = ' . $id
        )->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return (bool)$statement->fetchColumn();
    }

    private function indexExists(PDO $pdo, string $index): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = :name"
        );
        $statement->execute(['name' => $index]);
        return (bool)$statement->fetchColumn();
    }
}
