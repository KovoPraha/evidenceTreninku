<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PrivateStorageProbeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/kis-web-probe-test-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        file_put_contents($this->root . '/existing.txt', 'preserve existing attachment');
    }

    private function probe(string $fault = '', ?string $configured = null, string $documentRoot = '', bool $restricted = false): array
    {
        $source = dirname(__DIR__, 2) . '/includes/private_storage_probe.php';
        // Namespace-local native-function overrides exist only in the child test
        // process. Production has no injected path, callback, or fault parameter.
        $faultCode = match ($fault) {
            'partial_write' => 'function fwrite($h,$b) { return \\fwrite($h,substr($b,0,4)); }',
            'flush_exception' => 'function fflush($h) { throw new \\RuntimeException("private path must not escape"); }',
            'read_mismatch' => 'function stream_get_contents($h) { return "wrong"; }',
            'chmod_failure' => 'function chmod($p,$m) { return false; }',
            'unlink_failure' => 'function unlink($p) { return false; }',
            'create_failure' => 'function fopen($p,$m) { return false; }',
            'replacement_symlink' => 'function fclose($h) { $p=\\stream_get_meta_data($h)["uri"]; $ok=\\fclose($h); \\rename($p,$p.".original"); \\symlink(\\dirname($p)."/existing.txt",$p); return $ok; }',
            default => '',
        };
        $code = 'namespace Kis\\PrivateStorageProbe {' . $faultCode . '} namespace {'
            . "define('JE_LOKALNE',false); \$_SERVER['DOCUMENT_ROOT']=" . var_export($documentRoot, true) . '; require '
            . var_export($source, true) . '; echo json_encode(\\Kis\\PrivateStorageProbe\\run()); }';
        $command = [PHP_BINARY];
        if ($restricted) array_push($command, '-d', 'open_basedir="' . dirname(__DIR__, 2) . '"');
        array_push($command, '-r', $code);
        $process = proc_open($command, [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, null,
            array_merge(getenv(), ['APP_PRIVATE_STORAGE_ROOT'=>$configured ?? $this->root]));
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertSame('', $errors);
        self::assertStringNotContainsString($this->root, $output);
        self::assertStringNotContainsString('private path', $output);
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        foreach ($report as $value) self::assertIsBool($value);
        self::assertSame('preserve existing attachment', file_get_contents($this->root . '/existing.txt'));
        return $report;
    }

    public function testCompleteCyclePreservesExistingFilesAndLeavesNoProbe(): void
    {
        $report = $this->probe();
        foreach (['preflight_passed','created','permissions_set','write_verified','read_verified','cleanup_verified','ok'] as $key) self::assertTrue($report[$key], $key);
        self::assertSame(DIRECTORY_SEPARATOR === '/', $report['unix_permissions_supported']);
        self::assertSame(DIRECTORY_SEPARATOR === '/', $report['unix_permissions_verified']);
        self::assertSame(['.','..','existing.txt'], scandir($this->root));
    }

    public function testMissingRootIsNotCreated(): void
    {
        $report = $this->probe(configured:$this->root . '/missing');
        self::assertFalse($report['preflight_passed']);
        self::assertFalse($report['created']);
        self::assertFalse($report['ok']);
        self::assertDirectoryDoesNotExist($this->root . '/missing');
    }

    public function testPublicRootAndOpenBasedirDenialFailBeforeCreation(): void
    {
        foreach ([$this->probe(documentRoot:$this->root), $this->probe(restricted:true)] as $report) {
            self::assertFalse($report['preflight_passed']);
            self::assertFalse($report['created']);
            self::assertFalse($report['ok']);
        }
        self::assertSame(['.','..','existing.txt'], scandir($this->root));
    }

    public function testPartialWriteAndThrowingFlushStillRemoveOwnFile(): void
    {
        foreach (['partial_write','flush_exception'] as $fault) {
            $report = $this->probe($fault);
            self::assertTrue($report['created']);
            self::assertFalse($report['write_verified']);
            self::assertFalse($report['ok']);
            self::assertTrue($report['cleanup_verified']);
            self::assertSame(['.','..','existing.txt'], scandir($this->root));
        }
    }

    public function testReadMismatchFailsAndCleansUp(): void
    {
        $report = $this->probe('read_mismatch');
        self::assertTrue($report['write_verified']);
        self::assertFalse($report['read_verified']);
        self::assertTrue($report['cleanup_verified']);
        self::assertFalse($report['ok']);
        self::assertSame(['.','..','existing.txt'], scandir($this->root));
    }

    public function testPermissionFailurePreventsWritingAndCleansUp(): void
    {
        $report = $this->probe('chmod_failure');
        self::assertFalse($report['permissions_set']);
        self::assertFalse($report['write_verified']);
        self::assertTrue($report['cleanup_verified']);
        self::assertFalse($report['ok']);
        self::assertSame(['.','..','existing.txt'], scandir($this->root));
    }

    public function testCreateFailureDoesNotRemoveAnyExistingFile(): void
    {
        $report = $this->probe('create_failure');
        self::assertTrue($report['preflight_passed']);
        self::assertFalse($report['created']);
        self::assertFalse($report['ok']);
        self::assertSame(['.','..','existing.txt'], scandir($this->root));
    }

    public function testCleanupFailureCannotReportSuccess(): void
    {
        $report = $this->probe('unlink_failure');
        self::assertTrue($report['read_verified']);
        self::assertFalse($report['cleanup_verified']);
        self::assertFalse($report['ok']);
        $files = glob($this->root . '/.uat-storage-probe-*');
        self::assertCount(1, $files);
        self::assertSame(64, filesize($files[0]));
        if (DIRECTORY_SEPARATOR === '/') self::assertSame(0600, fileperms($files[0]) & 0777);
    }

    public function testCanonicalPublicSymlinkFailsBeforeWriting(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') self::markTestSkipped('Unix symlink boundary is verified in Linux CI.');
        $link = $this->root . '-link';
        self::assertTrue(symlink($this->root, $link));
        try {
            $report = $this->probe(configured:$link, documentRoot:$this->root);
            self::assertFalse($report['preflight_passed']);
            self::assertFalse($report['created']);
            self::assertFalse($report['ok']);
        } finally { unlink($link); }
    }

    public function testCleanupDoesNotFollowOrDeleteReplacementSymlink(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') self::markTestSkipped('Unix replacement-symlink boundary is verified in Linux CI.');
        $report = $this->probe('replacement_symlink');
        self::assertTrue($report['read_verified']);
        self::assertFalse($report['cleanup_verified']);
        self::assertFalse($report['ok']);
        $files = glob($this->root . '/.uat-storage-probe-*');
        self::assertCount(2, $files);
        self::assertSame(1, count(array_filter($files, 'is_link')));
    }

    public function testRouteKeepsAuthorizationAndCsrfBeforeExplicitProbe(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/uat_readiness_admin.php');
        $probeAt = strpos($source, '$storageProbe = \\Kis\\PrivateStorageProbe\\run();');
        self::assertNotFalse($probeAt);
        foreach (["require_once __DIR__ . '/includes/init.php'", "!roleAtLeast('admin')", "staffRequireActivePosition('system_admin')", "=== 'POST'", "csrf_verify(\$_POST['csrf_token'])", "!== 'private_storage_probe'"] as $guard) {
            $at = strpos($source, $guard);
            self::assertNotFalse($at, $guard);
            self::assertLessThan($probeAt, $at, $guard);
        }
        $bootstrap = file_get_contents(dirname(__DIR__, 2) . '/db.php');
        self::assertStringContainsString('auth_session_validate($pdo)', $bootstrap);
        self::assertStringContainsString('staffEnforceCurrentRoute(__DIR__)', $bootstrap);
        self::assertStringContainsString("'route' => 'uat_readiness_admin.php'", file_get_contents(dirname(__DIR__, 2) . '/includes/staff_workspaces.php'));
        self::assertStringContainsString('<?=csrf_field()?>', $source);
    }

    public function testActualPageActionRejectsUnauthorizedAndCsrfAndGetDoesNotProbe(): void
    {
        $base = dirname(__DIR__, 2);
        $source = file_get_contents($base . '/uat_readiness_admin.php');
        $source = substr($source, 5, strpos($source, 'function uatPageH') - 5);
        // Execute the actual page's authorization/action block; replace only
        // the database/session bootstrap with deterministic session fixtures.
        $source = str_replace("require_once __DIR__ . '/includes/init.php';",
            "require_once __DIR__ . '/includes/funkce.php'; require_once __DIR__ . '/includes/staff_workspaces.php';", $source);
        $source = str_replace('__DIR__', var_export($base, true), $source);
        $admin = ['trener_id'=>1,'role'=>'admin','staff_positions'=>['system_admin','coach'],
            'staff_active_position'=>'system_admin','csrf_token'=>'known-test-token'];
        $post = ['action'=>'private_storage_probe','csrf_token'=>'known-test-token'];
        $cases = [
            [[], 'POST', $post, false],
            [array_merge($admin, ['role'=>'trener','staff_positions'=>['coach'],'staff_active_position'=>'coach']), 'POST', $post, false],
            [array_merge($admin, ['staff_active_position'=>'coach']), 'POST', $post, false],
            [$admin, 'POST', array_merge($post, ['csrf_token'=>'wrong']), false],
            [$admin, 'POST', ['action'=>'private_storage_probe'], false],
            [$admin, 'POST', array_merge($post, ['action'=>'unknown']), false],
            [$admin, 'GET', $post, false],
            [$admin, 'POST', $post, true],
        ];
        foreach ($cases as [$session,$method,$form,$expected]) {
            $script = '$_SESSION=' . var_export($session, true) . '; $_POST=' . var_export($form, true)
                . '; $_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; define("JE_LOKALNE",false);'
                . 'register_shutdown_function(function(){ echo "\\nREPORT:".json_encode($GLOBALS["storageProbe"]??null); });'
                . 'eval(' . var_export($source, true) . ');';
            $process = proc_open([PHP_BINARY,'-r',$script], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes, null,
                array_merge(getenv(), ['APP_PRIVATE_STORAGE_ROOT'=>$this->root]));
            self::assertIsResource($process);
            fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            self::assertSame('', $errors);
            $report = json_decode(substr($output, strpos($output, 'REPORT:') + 7), true, 512, JSON_THROW_ON_ERROR);
            if ($expected) self::assertTrue($report['ok']); else self::assertNull($report);
            self::assertSame(['.','..','existing.txt'], scandir($this->root));
        }
    }

    protected function tearDown(): void
    {
        // Only this test's explicitly created temporary directory and files.
        foreach (scandir($this->root) as $name) {
            if ($name !== '.' && $name !== '..') unlink($this->root . '/' . $name);
        }
        rmdir($this->root);
    }
}
