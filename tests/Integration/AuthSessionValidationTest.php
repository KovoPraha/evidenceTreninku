<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/auth_session.php';

final class AuthSessionValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testBothActiveIdentitiesWithMatchingVersionsRemainValid(): void
    {
        $pdo = $this->database();
        \auth_session_bind_trainer(1, 2);
        \auth_session_bind_public_user(1, 3);
        $_SESSION['role'] = 'admin';

        self::assertTrue(\auth_session_validate($pdo));
        self::assertSame(1, $_SESSION['trener_id']);
        self::assertSame(1, $_SESSION['verejny_uzivatel_id']);
        self::assertSame('admin', $_SESSION['role']);
    }

    public function testStaleTrainerVersionIsClearedWhileValidPublicIdentityIsPreserved(): void
    {
        $pdo = $this->database();
        \auth_session_bind_trainer(1, 1);
        \auth_session_bind_public_user(1, 3);
        $_SESSION['role'] = 'admin';
        $_SESSION['opravneni'] = ['trainers' => 'admin'];

        self::assertFalse(\auth_session_validate($pdo));
        self::assertArrayNotHasKey('trener_id', $_SESSION);
        self::assertArrayNotHasKey(AUTH_SESSION_TRAINER_VERSION_KEY, $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
        self::assertArrayNotHasKey('opravneni', $_SESSION);
        self::assertSame(1, $_SESSION['verejny_uzivatel_id']);
        self::assertSame(3, $_SESSION[AUTH_SESSION_PUBLIC_VERSION_KEY]);
    }

    public function testInactivePublicIdentityAndMissingVersionFailClosed(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni = 0 WHERE id = 1');
        $_SESSION['verejny_uzivatel_id'] = 1;

        self::assertFalse(\auth_session_validate($pdo));
        self::assertArrayNotHasKey('verejny_uzivatel_id', $_SESSION);
        self::assertArrayNotHasKey(AUTH_SESSION_PUBLIC_VERSION_KEY, $_SESSION);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRevocationRotatesSessionAndCsrfBeforeCallerTerminatesRequest(): void
    {
        $pdo = $this->database();
        \app_session_start(['HTTP_HOST' => 'localhost'], 1000);
        \auth_session_bind_trainer(1, 1);
        $_SESSION['role'] = 'admin';
        $_SESSION['csrf_token'] = 'old-csrf-token';
        $oldId = session_id();

        self::assertFalse(\auth_session_validate($pdo));
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertNotSame($oldId, session_id());
        self::assertNotSame('old-csrf-token', $_SESSION['csrf_token']);
        self::assertArrayNotHasKey('trener_id', $_SESSION);

        \app_session_destroy(1001);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec(
            'CREATE TABLE treneri ('
            . 'id INTEGER PRIMARY KEY, aktivni INTEGER NOT NULL, session_version INTEGER NOT NULL)'
        );
        $pdo->exec('INSERT INTO treneri (id, aktivni, session_version) VALUES (1, 1, 2)');
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY, aktivni INTEGER NOT NULL, session_version INTEGER NOT NULL)'
        );
        $pdo->exec('INSERT INTO verejni_uzivatele (id, aktivni, session_version) VALUES (1, 1, 3)');
        return $pdo;
    }
}
