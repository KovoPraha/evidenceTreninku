<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PacketaProductionDiagnosticTest extends TestCase
{
    private function runProbe(array $environment, ?string $config = null): array
    {
        $root = sys_get_temp_dir() . '/packeta-probe-' . bin2hex(random_bytes(8));
        mkdir($root);
        if ($config !== null) file_put_contents($root . '/config.php', $config);
        $script = dirname(__DIR__, 2) . '/bin/diagnose-production-packeta.php';
        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            array_merge(getenv(), ['APP_HOST' => 'kis.kovopraha.cz',
                'KIS_PACKETA_DIAGNOSTIC_ROOT' => $root,
                'KIS_PACKETA_DIAGNOSTIC_CONFIRM' => 'OVERIT-PACKETU-VYPNUTOU'], $environment));
        try {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            return [$code, json_decode($stdout, true, 512, JSON_THROW_ON_ERROR), $stdout . $stderr];
        } finally {
            if (is_file($root . '/config.php')) unlink($root . '/config.php');
            rmdir($root);
        }
    }

    public function testWrongHostOrMissingConfirmationIsRefusedBeforeConfig(): void
    {
        foreach ([['APP_HOST' => 'localhost'], ['KIS_PACKETA_DIAGNOSTIC_CONFIRM' => '']] as $env) {
            [$code, $result, $output] = $this->runProbe($env, '<?php echo "SECRET_CONFIG";');
            self::assertSame(2, $code);
            self::assertSame('target', $result['stage']);
            self::assertStringNotContainsString('SECRET_CONFIG', $output);
        }
    }

    public function testConfigOutputAndExceptionAreRedacted(): void
    {
        [$code, $result, $output] = $this->runProbe([], '<?php echo "SECRET_KEY"; throw new RuntimeException("SECRET_PASSWORD");');
        self::assertSame(2, $code);
        self::assertSame('config', $result['stage']);
        self::assertStringNotContainsString('SECRET_', $output);
    }

    public function testLocalIdentityAndSimulationAreRefusedBeforeConnection(): void
    {
        foreach (["define('JE_LOKALNE',true);", "define('JE_LOKALNE',false); define('SHOP_PACKETA_LOCAL_DEMO',true);"] as $config) {
            [$code, $result] = $this->runProbe([], '<?php ' . $config . " define('APP_BASE_URL','https://kis.kovopraha.cz');");
            self::assertSame(2, $code);
            self::assertSame('config', $result['stage']);
        }
    }

    public function testWorkflowIsManualMainOnlyAndUsesPrivateTemporaryFiles(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/diagnose-packeta-production.yml');
        self::assertStringContainsString('workflow_dispatch:', $workflow);
        self::assertStringContainsString("if: github.ref == 'refs/heads/main'", $workflow);
        self::assertStringContainsString('environment: production', $workflow);
        self::assertStringContainsString('group: produkce-kis', $workflow);
        self::assertStringContainsString('StrictHostKeyChecking', file_get_contents(dirname(__DIR__, 2) . '/.github/scripts/production-ssh-retry.sh'));
        self::assertStringContainsString('DEPLOY_BASE: data/.kis-deploy', $workflow);
        self::assertStringContainsString('if: always()', $workflow);
        self::assertStringContainsString('.enabled_checkout_uat == false', $workflow);
        self::assertStringNotContainsString('schedule:', $workflow);
        $probe = file_get_contents(dirname(__DIR__, 2) . '/bin/diagnose-production-packeta.php');
        self::assertStringContainsString('SET SESSION TRANSACTION READ ONLY', $probe);
        self::assertStringNotContainsString("'/db.php'", $probe);
        self::assertStringNotContainsString('shopCheckoutCreate', $probe);
    }
}
