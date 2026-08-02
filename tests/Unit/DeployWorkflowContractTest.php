<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DeployWorkflowContractTest extends TestCase
{
    public function testBackupMigrationAndReleaseActivationHaveSafeOrder(): void
    {
        $workflow = $this->source('.github/workflows/deploy-production.yml');

        $backup = strpos($workflow, '- name: Vytvořit a ověřit produkční zálohu');
        $prepare = strpos($workflow, '- name: Připravit kompletní release mimo webroot');
        $migrate = strpos($workflow, '- name: Aplikovat migrace z připraveného release');
        $activate = strpos($workflow, '- name: Aktivovat ověřený release');
        $smoke = strpos($workflow, '- name: HTTP smoke test bez tajného tokenu');

        foreach ([$backup, $prepare, $migrate, $activate, $smoke] as $position) {
            self::assertNotFalse($position);
        }
        self::assertLessThan($prepare, $backup);
        self::assertLessThan($migrate, $prepare);
        self::assertLessThan($activate, $migrate);
        self::assertLessThan($smoke, $activate);

        self::assertStringContainsString('AUTH_RATE_LIMIT_PEPPER', $workflow);
        self::assertStringContainsString('strlen(\\$pepper) < 32', $workflow);
        self::assertStringContainsString('RELEASE_DIR: .evidence-deploy/releases/', $workflow);
        self::assertStringContainsString('cd \\"\\$HOME/$RELEASE_DIR\\"', $workflow);
        self::assertStringContainsString('\\"\\$HOME/$RELEASE_DIR/\\"', $workflow);
        self::assertStringContainsString("--exclude='config.php'", $workflow);
        self::assertStringContainsString('Odstranit kopii produkční konfigurace z release', $workflow);
        self::assertStringNotContainsString('./ "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"', $workflow);
    }

    public function testBackupOwnershipIncludesAuthLimiterTable(): void
    {
        $backup = $this->source('bin/db-backup.php');

        self::assertStringContainsString("'auth_login_limits'", $backup);
        self::assertStringContainsString(
            "EVIDENCE_OWNERSHIP_CONTRACT_VERSION = '2026-08-02.3'",
            $backup
        );
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($source);
        return $source;
    }
}
