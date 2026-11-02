<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_evidence_operation.php';
require_once dirname(__DIR__, 2) . '/includes/password_security.php';

final class MemberEvidenceOperationTest extends TestCase
{
    private function operation(): array
    {
        return ['confirmation' => 'PREVEST-CLENSTVI', 'actor' => 'local-test', 'run_id' => '123',
            'workflow_ref' => 'KovoPraha/evidenceTreninku/.github/workflows/member-evidence-production.yml@refs/heads/main'];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER,session_version INTEGER)');
        $pdo->exec('CREATE TABLE nastaveni(klic TEXT PRIMARY KEY,hodnota TEXT)');
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT,is_default INTEGER,assigned_by_trainer_id INTEGER)');
        $pdo->exec('CREATE TABLE staff_account_events(id INTEGER PRIMARY KEY,trainer_id INTEGER,actor_trainer_id INTEGER,action TEXT,reason TEXT,before_json TEXT,after_json TEXT)');
        return $pdo;
    }

    public function testTechnicalIdentityCannotLogInAndRevocationIsIdempotent(): void
    {
        $pdo = $this->database();
        $actor = \memberEvidenceOperationActor($pdo, $this->operation());
        $row = $pdo->query('SELECT * FROM treneri')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('', $row['email']);
        self::assertFalse(\trainer_password_verify($row['heslo'], $row['heslo']));
        self::assertFalse(\trainer_password_verify('anything', $row['heslo']));
        self::assertSame('registrar', $pdo->query('SELECT position_code FROM staff_user_positions')->fetchColumn());
        self::assertTrue(\memberEvidenceOperationRevoke($pdo, $this->operation()));
        self::assertTrue(\memberEvidenceOperationRevoke($pdo, $this->operation()));
        self::assertSame(0, (int) $pdo->query('SELECT aktivni FROM treneri WHERE id=' . $actor)->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM staff_user_positions')->fetchColumn());
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM staff_account_events')->fetchColumn());
    }

    public function testForeignOperationCannotRevokeTheIdentity(): void
    {
        $pdo = $this->database();
        \memberEvidenceOperationActor($pdo, $this->operation());
        try {
            \memberEvidenceOperationRevoke($pdo, array_replace($this->operation(), ['run_id' => '124']));
            self::fail('Foreign operation accepted');
        } catch (\RuntimeException) {
            self::assertSame(1, (int) $pdo->query('SELECT aktivni FROM treneri')->fetchColumn());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM staff_user_positions')->fetchColumn());
        }
    }

    public function testUnsupportedAuthorityCannotCreateIdentity(): void
    {
        $pdo = $this->database();
        try {
            \memberEvidenceOperationActor($pdo, array_replace($this->operation(), ['workflow_ref' => 'other']));
            self::fail('Foreign authority accepted');
        } catch (\RuntimeException) {
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
        }
    }

    public function testMaintenanceBlocksCliWithoutTokenRestoresBytesAndRejectsFileDrift(): void
    {
        $base = sys_get_temp_dir() . '/kis-maintenance-test-' . bin2hex(random_bytes(8));
        mkdir($base);
        mkdir($base . '/app');
        mkdir($base . '/private');
        $original = "<?php\ndeclare(strict_types=1);\necho 'CONFIG-REACHED';\n";
        file_put_contents($base . '/app/config.php', $original);
        file_put_contents($base . '/app/.htaccess', "Options -Indexes\n");
        $previousToken = getenv('KIS_MEMBER_CUTOVER_TOKEN');
        try {
            $state = \memberEvidenceOperationPause($base . '/app', $base . '/private');
            self::assertSame($state, \memberEvidenceOperationAssertPaused($base . '/app', $base . '/private'));
            $child = static function (string $token) use ($base): string {
                putenv('KIS_MEMBER_CUTOVER_TOKEN=' . $token);
                $process = proc_open([PHP_BINARY, $base . '/app/config.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $error);
                return $output;
            };
            self::assertStringNotContainsString('CONFIG-REACHED', $child(''));
            self::assertSame('CONFIG-REACHED', $child($state['token']));
            $paused = file_get_contents($base . '/app/.htaccess');
            file_put_contents($base . '/app/.htaccess', 'CONCURRENT EDIT');
            try {
                \memberEvidenceOperationResume($base . '/app', $base . '/private');
                self::fail('Concurrent edit overwritten');
            } catch (\RuntimeException) {
                self::assertSame('CONCURRENT EDIT', file_get_contents($base . '/app/.htaccess'));
            }
            file_put_contents($base . '/app/.htaccess', $paused);
            self::assertTrue(\memberEvidenceOperationResume($base . '/app', $base . '/private'));
            self::assertTrue(\memberEvidenceOperationResume($base . '/app', $base . '/private'));
            self::assertSame($original, file_get_contents($base . '/app/config.php'));
            self::assertSame("Options -Indexes\n", file_get_contents($base . '/app/.htaccess'));
            try {
                \memberEvidenceOperationAssertPaused($base . '/app', $base . '/private');
                self::fail('Resumed application accepted as paused');
            } catch (\RuntimeException) {
                self::assertSame($original, file_get_contents($base . '/app/config.php'));
            }
        } finally {
            putenv($previousToken === false ? 'KIS_MEMBER_CUTOVER_TOKEN' : 'KIS_MEMBER_CUTOVER_TOKEN=' . $previousToken);
            foreach (['app', 'private'] as $subdir) {
                foreach (scandir($base . '/' . $subdir) as $name) {
                    if ($name !== '.' && $name !== '..') {
                        unlink($base . '/' . $subdir . '/' . $name);
                    }
                }
                rmdir($base . '/' . $subdir);
            }
            rmdir($base);
        }
    }
}
