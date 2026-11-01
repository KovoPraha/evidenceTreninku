<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/password_reset_form.php';
require_once dirname(__DIR__, 2) . '/includes/auth_session.php';
require_once dirname(__DIR__, 2) . '/csrf_helper.php';

/** Synthetic SQLite accounts and two real, isolated PHP session files; no production config or network. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TrainerResetSessionRevocationTest extends TestCase
{
    private string $sessionDirectory;

    protected function setUp(): void
    {
        $this->sessionDirectory = sys_get_temp_dir() . '/evidence-reset-sessions-' . bin2hex(random_bytes(8));
        mkdir($this->sessionDirectory, 0700);
        session_save_path($this->sessionDirectory);
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_COOKIE = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        foreach (glob($this->sessionDirectory . '/sess_*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->sessionDirectory);
    }

    public static function publicIdentityLinks(): array
    {
        return ['unrelated public identity' => [false], 'explicitly linked public identity' => [true]];
    }

    #[DataProvider('publicIdentityLinks')]
    public function testResetInSessionBRevokesOldSessionAAndRejectsOldPasswordAndReplay(bool $linked): void
    {
        $oldPassword = 'Synthetic old password 123!';
        $newPassword = 'Synthetic new password 456!';
        $pdo = $this->database($oldPassword, $linked);
        $publicBefore = $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn();

        // A carries both identities, as a real browser can. Linking must never be inferred from email.
        \app_session_start();
        \auth_session_bind_trainer(1, 1);
        \auth_session_bind_public_user(1, 1);
        self::assertTrue(\auth_session_validate($pdo));
        $sessionA = session_id();
        $csrfA = \csrf_token();
        session_write_close();

        session_id('');
        \app_session_start();
        $sessionB = session_id();
        self::assertNotSame($sessionA, $sessionB);
        self::assertArrayNotHasKey('trener_id', $_SESSION);
        $keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
        $issued = \trainerPasswordResetQueueRequest($pdo, 'coach@example.test', 'https://example.test/reset?pro=trener', $keys);
        $check = ['action' => 'check', 'token' => $issued['token']];
        self::assertTrue(\passwordResetFormSubmit($pdo, true, $check, \csrf_verify(\csrf_token()))['showForm']);
        $submission = ['action' => 'consume', 'token' => $issued['token'], 'heslo' => $newPassword, 'heslo2' => $newPassword];
        self::assertTrue(\passwordResetFormSubmit($pdo, true, $submission, \csrf_verify(\csrf_token()))['success']);
        $newHash = $pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn();
        session_write_close();

        // The next request in A loads its old persisted session, then runs the real revocation guard.
        session_id($sessionA);
        \app_session_start();
        self::assertSame(1, $_SESSION['trener_id']);
        self::assertFalse(\auth_session_validate($pdo));
        self::assertArrayNotHasKey('trener_id', $_SESSION);
        self::assertArrayNotHasKey(AUTH_SESSION_TRAINER_VERSION_KEY, $_SESSION);
        self::assertNotSame($sessionA, session_id());
        self::assertNotSame($csrfA, \csrf_token());
        if ($linked) {
            self::assertArrayNotHasKey('verejny_uzivatel_id', $_SESSION);
        } else {
            self::assertSame(1, $_SESSION['verejny_uzivatel_id']);
        }
        session_write_close();

        // The exact password matcher used by login.php must reject old and accept new credentials.
        $candidates = $pdo->query('SELECT * FROM treneri WHERE id=1 AND aktivni=1')->fetchAll(PDO::FETCH_ASSOC);
        self::assertNull(\trainer_password_unique_match($candidates, $oldPassword));
        $login = \trainer_password_unique_match($candidates, $newPassword);
        self::assertNotNull($login);
        self::assertSame(1, (int)$login['id']);
        session_id($sessionB);
        \app_session_start();
        \auth_session_bind_trainer((int)$login['id'], (int)$login['session_version']);
        self::assertTrue(\auth_session_validate($pdo));

        self::assertFalse(\passwordResetFormSubmit($pdo, true, $check, true)['showForm']);
        $submission['heslo'] = $submission['heslo2'] = 'Rejected synthetic replay 789!';
        self::assertFalse(\passwordResetFormSubmit($pdo, true, $submission, true)['success']);
        self::assertSame($newHash, $pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM treneri WHERE id=1')->fetchColumn());
        self::assertTrue(\auth_session_validate($pdo));
        $publicAfter = $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn();
        self::assertSame($linked ? $newHash : $publicBefore, $publicAfter);
        self::assertSame($linked ? 2 : 1, (int)$pdo->query('SELECT session_version FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    private function database(string $oldPassword, bool $linked): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,heslo TEXT,aktivni INTEGER,session_version INTEGER,role TEXT)');
        $pdo->prepare("INSERT INTO treneri VALUES(1,'coach@example.test',?,1,1,'trener')")
            ->execute([\trainer_password_hash($oldPassword)]);
        $pdo->exec('CREATE TABLE opravneni(klic TEXT PRIMARY KEY,min_role TEXT)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,'
            . 'aktivni INTEGER,email_overeno INTEGER,session_version INTEGER,trener_id INTEGER)');
        $pdo->prepare("INSERT INTO verejni_uzivatele VALUES(1,'coach@example.test',?,1,1,1,?)")
            ->execute([\trainer_password_hash('Unrelated synthetic password 123!'), $linked ? 1 : null]);
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY)');
        foreach (['20260804235900_password_reset_tokens', '20261019100000_auth_mail_outbox',
            '20261031090000_trainer_password_reset'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
        }
        return $pdo;
    }
}
