<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/auth/sso_bridge.php';

final class SsoBridgeRevocationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            \app_session_destroy();
        } else {
            $_SESSION = [];
        }
    }

    public function testMissingSsoIdentityRevokesExistingTrainerAndRejectsRequest(): void
    {
        $_SESSION = [
            'trener_id' => 12,
            'trener_jmeno' => 'Trainer',
            'role' => 'trener',
            'opravneni' => ['example' => 'trener'],
            'velo_user_id_cached' => 88,
            \AUTH_SESSION_TRAINER_VERSION_KEY => 3,
        ];

        self::assertFalse(\velocotaSsoBridge($this->database()));
        self::assertArrayNotHasKey('trener_id', $_SESSION);
        self::assertArrayNotHasKey(\AUTH_SESSION_TRAINER_VERSION_KEY, $_SESSION);
    }

    public function testRemovedSsoRoleRevokesExistingTrainerAndRejectsRequest(): void
    {
        $_SESSION = [
            'trener_id' => 12,
            'velo_user_id' => 88,
            'velo_role' => 'clen',
            \AUTH_SESSION_TRAINER_VERSION_KEY => 3,
        ];

        self::assertFalse(\velocotaSsoBridge($this->database()));
        self::assertArrayNotHasKey('trener_id', $_SESSION);
    }

    public function testMissingSsoIdentityDoesNotRejectAnonymousRequest(): void
    {
        self::assertTrue(\velocotaSsoBridge($this->database()));
        self::assertSame([], $_SESSION);
    }

    public function testTrainerClearPreservesIndependentPublicIdentity(): void
    {
        $_SESSION = [
            'verejny_uzivatel_id' => 7,
            \AUTH_SESSION_PUBLIC_VERSION_KEY => 2,
        ];

        self::assertTrue(\velocotaSsoBridge($this->database()));
        self::assertSame(7, $_SESSION['verejny_uzivatel_id']);
        self::assertSame(2, $_SESSION[\AUTH_SESSION_PUBLIC_VERSION_KEY]);
    }

    public function testSameSsoUserWithDowngradedRoleIsReboundAndRotated(): void
    {
        $pdo = $this->ssoDatabase();
        \app_session_start(['HTTP_HOST' => 'localhost'], 1000);
        $oldSessionId = session_id();
        $_SESSION = [
            'trener_id' => 12,
            'trener_jmeno' => 'Trainer',
            'role' => 'admin',
            'velo_user_id' => 88,
            'velo_user_id_cached' => 88,
            'velo_role' => 'trener',
            'velo_jmeno' => 'Trainer',
            'velo_email' => 'trainer@example.test',
            \AUTH_SESSION_TRAINER_VERSION_KEY => 3,
        ];

        self::assertTrue(\velocotaSsoBridge($pdo));
        self::assertSame('trener', $_SESSION['role']);
        self::assertSame(12, $_SESSION['trener_id']);
        self::assertSame(3, $_SESSION[\AUTH_SESSION_TRAINER_VERSION_KEY]);
        self::assertNotSame($oldSessionId, session_id());
    }

    private function database(): PDO
    {
        return new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function ssoDatabase(): PDO
    {
        $pdo = $this->database();
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'evidence_test');
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec(
            'CREATE TABLE information_schema.COLUMNS ('
            . 'TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)'
        );
        $pdo->exec(
            "INSERT INTO information_schema.COLUMNS VALUES "
            . "('evidence_test', 'treneri', 'velo_user_id')"
        );
        $pdo->exec(
            'CREATE TABLE treneri ('
            . 'id INTEGER PRIMARY KEY, velo_user_id INTEGER, aktivni INTEGER, '
            . 'session_version INTEGER, jmeno TEXT, email TEXT, heslo TEXT, role TEXT)'
        );
        $pdo->exec(
            "INSERT INTO treneri "
            . "(id, velo_user_id, aktivni, session_version, jmeno, email, heslo, role) "
            . "VALUES (12, 88, 1, 3, 'Trainer', 'trainer@example.test', 'unused', 'admin')"
        );
        $pdo->exec('CREATE TABLE opravneni (klic TEXT, min_role TEXT)');

        return $pdo;
    }
}
