<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PasswordResetRequestPageTest extends TestCase
{
    private string $directory;
    private PDO $pdo;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->directory = sys_get_temp_dir() . '/evidence-reset-request-page-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/booking', 0700, true);
        mkdir($this->directory . '/includes', 0700);
        $this->write('booking/zapomenute_heslo.php', (string)file_get_contents($root . '/booking/zapomenute_heslo.php'));
        foreach (['includes/session_security.php', 'csrf_helper.php', 'includes/password_reset_request_audit.php', 'includes/app_url.php'] as $path) {
            $this->write($path, '<?php require_once ' . var_export($root . '/' . $path, true) . ';');
        }
        $this->write('db.php', '<?php require_once ' . var_export($root . '/includes/ui_shell.php', true)
            . '; require_once ' . var_export($root . '/includes/auth_session.php', true) . ';');
        require_once $root . '/includes/session_security.php';
        require_once $root . '/csrf_helper.php';
        $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['SCRIPT_NAME'] = '/booking/zapomenute_heslo.php';
        defined('PERSON_RC_KEYRING') || define('PERSON_RC_KEYRING', ['v1' => random_bytes(32)]);
        defined('PERSON_RC_ACTIVE_KEY_VERSION') || define('PERSON_RC_ACTIVE_KEY_VERSION', 'v1');
        defined('PERSON_RC_INDEX_KEY') || define('PERSON_RC_INDEX_KEY', random_bytes(32));
        \app_session_start();
        $_SESSION = [];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,trener_id INTEGER)');
        $this->pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test','old',1,1,NULL)");
        $this->pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,heslo TEXT,aktivni INTEGER,session_version INTEGER)');
        $this->pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_key TEXT,active INTEGER)');
        $this->pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $this->pdo->exec('CREATE TABLE auth_login_limits(scope TEXT,key_hash TEXT,window_started_at INTEGER,attempts INTEGER,blocked_until INTEGER,updated_at INTEGER,PRIMARY KEY(scope,key_hash))');
        $this->pdo->exec('CREATE TABLE password_reset_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,'
            . 'target_id INTEGER,delivery_account_id INTEGER,token_hash TEXT,expires_at TEXT,consumed_at TEXT)');
        foreach (['20261019100000_auth_mail_outbox', '20261122120000_password_reset_request_audit'] as $name) {
            $migration = require $root . '/migrations/' . $name . '.php';
            $migration['up']($this->pdo);
        }
    }

    protected function tearDown(): void
    {
        foreach (['booking/zapomenute_heslo.php', 'includes/session_security.php', 'includes/password_reset_request_audit.php', 'includes/app_url.php', 'csrf_helper.php', 'db.php'] as $path) {
            @unlink($this->directory . '/' . $path);
        }
        @rmdir($this->directory . '/booking');
        @rmdir($this->directory . '/includes');
        @rmdir($this->directory);
    }

    public function testRealPagePostsQueueOrRejectWithSamePublicAnswerAndReference(): void
    {
        $known = $this->render(['identifier' => 'parent@example.test']);
        $unknown = $this->render(['identifier' => 'nobody@example.test']);
        foreach ([$known, $unknown] as $html) {
            self::assertStringContainsString('Pokud lze žádost zpracovat, pošleme odkaz', $html);
            self::assertMatchesRegularExpression('/Reference požadavku: <code>[a-f0-9]{32}<\/code>/', $html);
            self::assertStringNotContainsString('parent@example.test', $html);
        }
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
        self::assertSame(['queued', 'rejected'], $this->pdo->query('SELECT outcome FROM password_reset_request_audit ORDER BY outcome')->fetchAll(PDO::FETCH_COLUMN));
    }

    private function render(array $post): string
    {
        $_GET = [];
        $_POST = $post + ['csrf_token' => \csrf_token()];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $pdo = $this->pdo;
        ob_start();
        try {
            require $this->directory . '/booking/zapomenute_heslo.php';
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function write(string $path, string $contents): void
    {
        file_put_contents($this->directory . '/' . $path, $contents);
    }
}
