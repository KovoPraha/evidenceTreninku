<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Execute an unchanged copy of the real page, replacing only the database bootstrap. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PasswordResetPageTest extends TestCase
{
    private string $directory;
    private array $files = [];
    private PDO $pdo;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->directory = sys_get_temp_dir() . '/evidence-reset-page-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/booking', 0700, true);
        mkdir($this->directory . '/includes', 0700);
        $this->write('booking/nove_heslo.php', (string)file_get_contents($root . '/booking/nove_heslo.php'));
        foreach (['includes/session_security.php', 'csrf_helper.php', 'includes/password_reset_form.php',
            'includes/password_reset.php', 'includes/trainer_password_reset.php'] as $path) {
            $this->write($path, '<?php require_once ' . var_export($root . '/' . $path, true) . ';');
        }
        // No production config or database connection is loaded. The page inherits our SQLite PDO.
        $this->write('db.php', '<?php require_once ' . var_export($root . '/includes/ui_shell.php', true)
            . '; require_once ' . var_export($root . '/includes/auth_session.php', true) . ';');
        require_once $root . '/includes/password_reset_form.php';
        require_once $root . '/includes/session_security.php';
        require_once $root . '/csrf_helper.php';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/booking/nove_heslo.php';
        \app_session_start();
        $_SESSION = [];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,heslo TEXT,aktivni INTEGER,session_version INTEGER)');
        $this->pdo->exec("INSERT INTO treneri VALUES(1,'coach@example.test','old',1,1)");
        $this->pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,session_version INTEGER,trener_id INTEGER)');
        $this->pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test','old',1,1,1,NULL)");
        $this->pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY)');
        foreach (['20260804235900_password_reset_tokens', '20261019100000_auth_mail_outbox',
            '20261031090000_trainer_password_reset'] as $name) {
            $migration = require $root . '/migrations/' . $name . '.php';
            $migration['up']($this->pdo);
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        foreach (array_reverse($this->files) as $path) {
            unlink($path);
        }
        rmdir($this->directory . '/booking');
        rmdir($this->directory . '/includes');
        rmdir($this->directory);
    }

    public static function audiences(): array
    {
        return ['trainer' => [true], 'public' => [false]];
    }

    #[DataProvider('audiences')]
    public function testUsedLinkIsRejectedBeforePasswordEntryAndReplayCannotChangePassword(bool $trainer): void
    {
        $get = $this->render($trainer);
        self::assertStringNotContainsString('type="password"', $get);
        self::assertStringContainsString('id="password-reset-check-form"', $get);
        $token = $this->issue($trainer);
        $table = $trainer ? 'treneri' : 'verejni_uzivatele';
        $column = $trainer ? 'heslo' : 'heslo_hash';
        $tokens = $trainer ? 'trainer_password_reset_tokens' : 'password_reset_tokens';
        $check = ['action' => 'check', 'token' => $token];
        $form = $this->render($trainer, $check);
        self::assertStringContainsString('type="password"', $form);
        self::assertStringContainsString('value="' . $token . '"', $form);
        self::assertSame('old', $this->pdo->query("SELECT $column FROM $table WHERE id=1")->fetchColumn());
        self::assertNull($this->pdo->query("SELECT consumed_at FROM $tokens")->fetchColumn());

        $submission = ['token' => $token, 'heslo' => 'First synthetic password 123!', 'heslo2' => 'Mismatch'];
        $mismatch = $this->render($trainer, $submission);
        self::assertStringContainsString('Hesla se neshodují.', $mismatch);
        self::assertStringContainsString('value="' . $token . '"', $mismatch);
        self::assertStringNotContainsString('window.location.hash', $mismatch);
        $weak = $this->render($trainer, ['token' => $token, 'heslo' => 'short', 'heslo2' => 'short']);
        self::assertStringContainsString('type="password"', $weak);
        self::assertStringContainsString('value="' . $token . '"', $weak);
        self::assertSame('old', $this->pdo->query("SELECT $column FROM $table WHERE id=1")->fetchColumn());

        $submission['heslo2'] = $submission['heslo'];
        $success = $this->render($trainer, $submission);
        self::assertStringContainsString('Heslo bylo změněno.', $success);
        self::assertStringNotContainsString('type="password"', $success);
        $hash = $this->pdo->query("SELECT $column FROM $table WHERE id=1")->fetchColumn();
        self::assertTrue(password_verify($submission['heslo'], $hash));
        self::assertSame(2, (int)$this->pdo->query("SELECT session_version FROM $table WHERE id=1")->fetchColumn());

        foreach ([$check, ['token' => $token, 'heslo' => 'Replay synthetic password 456!',
            'heslo2' => 'Replay synthetic password 456!']] as $replay) {
            $rejected = $this->render($trainer, $replay);
            self::assertStringContainsString('Odkaz není platný nebo už byl použit.', $rejected);
            self::assertStringNotContainsString('type="password"', $rejected);
            self::assertStringNotContainsString($token, $rejected);
            self::assertSame($hash, $this->pdo->query("SELECT $column FROM $table WHERE id=1")->fetchColumn());
            self::assertSame(2, (int)$this->pdo->query("SELECT session_version FROM $table WHERE id=1")->fetchColumn());
        }
    }

    #[DataProvider('audiences')]
    public function testInvalidCsrfExpiredAndChangedAccountDoNotExposePasswordForm(bool $trainer): void
    {
        $token = $this->issue($trainer);
        $check = ['action' => 'check', 'token' => $token];
        $csrf = $this->render($trainer, $check + ['csrf_token' => 'invalid']);
        self::assertStringContainsString('Neplatný bezpečnostní token.', $csrf);
        self::assertStringNotContainsString('type="password"', $csrf);
        self::assertStringNotContainsString($token, $csrf);
        self::assertStringContainsString('type="password"', $this->render($trainer, $check));
        $table = $trainer ? 'treneri' : 'verejni_uzivatele';
        $this->pdo->exec("UPDATE $table SET aktivni=0 WHERE id=1");
        self::assertStringNotContainsString('type="password"', $this->render($trainer, $check));
        $this->pdo->exec("UPDATE $table SET aktivni=1 WHERE id=1");
        $tokens = $trainer ? 'trainer_password_reset_tokens' : 'password_reset_tokens';
        $this->pdo->exec("UPDATE $tokens SET expires_at='2000-01-01 00:00:00'");
        $expired = $this->render($trainer, $check);
        self::assertStringContainsString('Odkaz není platný nebo už byl použit.', $expired);
        self::assertStringNotContainsString('type="password"', $expired);
        self::assertSame(1, (int)$this->pdo->query("SELECT session_version FROM $table WHERE id=1")->fetchColumn());
        self::assertStringNotContainsString('type="password"', $this->render($trainer, ['action' => 'check', 'token' => 'invalid']));
    }

    private function issue(bool $trainer): string
    {
        $keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
        $result = $trainer
            ? \trainerPasswordResetQueueRequest($this->pdo, 'coach@example.test', 'https://example.test/reset?pro=trener', $keys)
            : \passwordResetQueueRequest($this->pdo, 'parent@example.test', 'https://example.test/reset', $keys);
        return $result['token'];
    }

    private function render(bool $trainer, ?array $post = null): string
    {
        $_GET = $trainer ? ['pro' => 'trener'] : [];
        $_POST = $post === null ? [] : $post + ['csrf_token' => \csrf_token()];
        $_SERVER['REQUEST_METHOD'] = $post === null ? 'GET' : 'POST';
        $pdo = $this->pdo;
        ob_start();
        try {
            require $this->directory . '/booking/nove_heslo.php';
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function write(string $path, string $contents): void
    {
        $file = $this->directory . '/' . $path;
        file_put_contents($file, $contents);
        $this->files[] = $file;
    }
}
