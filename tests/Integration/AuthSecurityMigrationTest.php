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
            [
                '20260802120000_auth_revocation_rate_limit',
                '20260802133000_one_time_tokens',
                '20260802170000_shop_catalog_staging',
                '20260802190000_shop_catalog_review',
                '20260802210000_shop_canonical_catalog',
                '20260802230000_account_person_roles',
                '20260802233000_account_person_claim_requests',
                '20260803090000_shop_product_publication',
                '20260803110000_club_events',
                '20260803130000_club_event_registrations',
                '20260803150000_club_event_terms',
                '20260803170000_club_event_waitlist',
                '20260803190000_club_event_notifications',
            ],
            array_keys($catalog)
        );
        self::assertSame(1, $this->sessionVersion($pdo, 'treneri', 1));
        self::assertSame(1, $this->sessionVersion($pdo, 'verejni_uzivatele', 1));
        self::assertTrue($this->tableExists($pdo, 'auth_login_limits'));
        self::assertTrue($this->tableExists($pdo, 'shop_catalog_import_runs'));
        self::assertTrue($this->indexExists($pdo, 'idx_auth_login_limits_blocked'));
        self::assertSame(
            one_time_token_hash(ONE_TIME_TOKEN_EMAIL_VERIFICATION, str_repeat('a', 64)),
            $pdo->query('SELECT verifikacni_token FROM verejni_uzivatele WHERE id = 1')->fetchColumn()
        );
        self::assertSame(
            one_time_token_hash(ONE_TIME_TOKEN_BOOKING_APPROVAL, str_repeat('b', 48)),
            $pdo->query('SELECT potvrzovaci_token FROM verejne_rezervace WHERE id = 1')->fetchColumn()
        );
        self::assertNotFalse(
            $pdo->query('SELECT verifikacni_token_expires_at FROM verejni_uzivatele WHERE id = 1')->fetchColumn()
        );
        self::assertNotFalse(
            $pdo->query('SELECT potvrzovaci_token_expires_at FROM verejne_rezervace WHERE id = 1')->fetchColumn()
        );
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
        $pdo->exec('CREATE TABLE sportovci (id INTEGER PRIMARY KEY)');
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY, aktivni INTEGER NOT NULL DEFAULT 1, '
            . 'verifikacni_token TEXT NULL, registrovan TEXT NOT NULL)'
        );
        $pdo->exec(
            "INSERT INTO verejni_uzivatele (id, aktivni, verifikacni_token, registrovan) "
            . "VALUES (1, 1, '" . str_repeat('a', 64) . "', '2026-08-02 08:00:00')"
        );
        $pdo->exec(
            'CREATE TABLE verejne_rezervace ('
            . 'id INTEGER PRIMARY KEY, potvrzovaci_token TEXT NULL, cas_rezervace TEXT NOT NULL)'
        );
        $pdo->exec(
            "INSERT INTO verejne_rezervace (id, potvrzovaci_token, cas_rezervace) "
            . "VALUES (1, '" . str_repeat('b', 48) . "', '2026-08-02 08:00:00')"
        );

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
