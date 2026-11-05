<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/bar-cutover-operation.php';

final class BarCutoverPlanTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bar-cutover-plan-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/files', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->root . '/files', $this->root] as $directory) {
            foreach (new \FilesystemIterator($directory) as $file) {
                if ($file->isFile()) {
                    unlink($file->getPathname());
                }
            }
        }
        rmdir($this->root . '/files');
        rmdir($this->root);
    }

    private function proof(): array
    {
        $sql = 'evidence_2026-11-05_localhost.sql.gz';
        file_put_contents($this->root . '/' . $sql, gzencode('CREATE TABLE LOCALHOST_only (id INT);'));
        $zip = new \ZipArchive();
        $zip->open($this->root . '/files/kis-files-123.zip', \ZipArchive::CREATE | \ZipArchive::EXCL);
        $zip->addFromString('manifest.json', '{}');
        $zip->close();
        $proof = ['restore_run' => '123', 'sql_file' => $sql, 'sql_sha256' => hash_file('sha256', $this->root . '/' . $sql),
            'files_archive' => 'kis-files-123.zip', 'files_sha256' => hash_file('sha256', $this->root . '/files/kis-files-123.zip')];
        file_put_contents($this->root . '/evidence_2026-11-05_localhost.manifest.json', json_encode([
            'application' => 'evidence', 'sql_file' => $sql, 'sha256' => $proof['sql_sha256'],
            'tables' => ['kis_bar_products' => 0, 'kis_bar_import_runs' => 0]], JSON_THROW_ON_ERROR));
        return $proof;
    }

    public function testPlanPinsOnePreparedBundleReleaseAndRequestedAdministrator(): void
    {
        $plan = ['prepare_run' => '123', 'bundle_sha256' => str_repeat('a', 64), 'release_sha' => str_repeat('b', 40),
            'administrator_name' => 'LOCALHOST Cutover Owner'];
        file_put_contents($this->root . '/plan.json', json_encode($plan, JSON_THROW_ON_ERROR));
        self::assertSame($plan, \barCutoverReadPlan($this->root . '/plan.json'));
        $plan['password'] = 'LOCALHOST-do-not-accept';
        file_put_contents($this->root . '/plan.json', json_encode($plan, JSON_THROW_ON_ERROR));
        $this->expectException(\InvalidArgumentException::class);
        \barCutoverReadPlan($this->root . '/plan.json');
    }

    public function testBackupFingerprintsAreCheckedAndCorruptionStopsTheOperation(): void
    {
        $proof = $this->proof();
        self::assertTrue(\barCutoverVerifyBackup($proof, $this->root)['verified']);
        file_put_contents($this->root . '/files/kis-files-123.zip', 'corrupt');
        $this->expectException(\RuntimeException::class);
        \barCutoverVerifyBackup($proof, $this->root);
    }

    public function testStaleBackupIsRejected(): void
    {
        $proof = $this->proof();
        touch($this->root . '/' . $proof['sql_file'], time() - 3700);
        clearstatcache();
        $this->expectException(\RuntimeException::class);
        \barCutoverVerifyBackup($proof, $this->root);
    }

    public function testFileArchiveMustBelongToTheVerifiedRestoreRun(): void
    {
        $proof = $this->proof();
        $proof['restore_run'] = '124';
        $this->expectException(\InvalidArgumentException::class);
        \barCutoverVerifyBackup($proof, $this->root);
    }

    public function testNonemptyCatalogueBackupIsRejected(): void
    {
        $proof = $this->proof();
        $manifest = $this->root . '/evidence_2026-11-05_localhost.manifest.json';
        $data = json_decode((string)file_get_contents($manifest), true, 32, JSON_THROW_ON_ERROR);
        $data['tables']['kis_bar_products'] = 1;
        file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR));
        $this->expectException(\RuntimeException::class);
        \barCutoverVerifyBackup($proof, $this->root);
    }
}
