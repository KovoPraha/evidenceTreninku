<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/deployment_release.php';

final class DeploymentReleaseTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'deployment-release-' . bin2hex(random_bytes(6));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'var', 0700, true);
    }

    protected function tearDown(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'deployment.json';
        if (is_file($path)) unlink($path);
        if (is_dir($this->root . DIRECTORY_SEPARATOR . 'var')) rmdir($this->root . DIRECTORY_SEPARATOR . 'var');
        if (is_dir($this->root)) rmdir($this->root);
    }

    public function testReadsOnlyValidReleaseEvidence(): void
    {
        file_put_contents($this->root . '/var/deployment.json', json_encode([
            'sha' => str_repeat('a', 40),
            'run_id' => '123456',
            'deployed_at' => '2026-09-08T10:00:00+00:00',
            'uat_approved' => true,
        ], JSON_THROW_ON_ERROR));

        self::assertSame([
            'sha' => str_repeat('a', 40),
            'run_id' => '123456',
            'deployed_at' => '2026-09-08T10:00:00+00:00',
            'uat_approved' => true,
        ], \deploymentReleaseInfo($this->root));

        file_put_contents($this->root . '/var/deployment.json', '{invalid');
        self::assertSame('', \deploymentReleaseInfo($this->root)['sha']);
        self::assertFalse(\deploymentReleaseInfo($this->root)['uat_approved']);
    }
}
