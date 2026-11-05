<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/bar-cutover-prepare.php';

final class BarSourceConfigurationTest extends TestCase
{
    private string $root;
    private array $server;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bar-config-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/includes', 0700, true);
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    }

    protected function tearDown(): void
    {
        if (is_file($this->root . '/includes/config.php')) {
            unlink($this->root . '/includes/config.php');
        }
        rmdir($this->root . '/includes');
        rmdir($this->root);
        $_SERVER = $this->server;
        unset($GLOBALS['bar_source_config_meta']);
    }

    public function testConfigLocalVariablesCannotOverwriteCallerAndHostIsRestored(): void
    {
        file_put_contents($this->root . '/includes/config.php', '<?php $legacy="changed"; $config=[]; $output="changed";'
            . 'return ["lokalne"=>$_SERVER["HTTP_HOST"]!=="bar.kovopraha.cz","db"=>["heslo"=>"LOCALHOST-only-secret"]];');
        $value = \barCutoverReadSourceConfig($this->root);
        self::assertFalse($value['lokalne']);
        self::assertSame('LOCALHOST-only-secret', $value['db']['heslo']);
        self::assertSame('kis.kovopraha.cz', $_SERVER['HTTP_HOST']);
        self::assertSame('kis.kovopraha.cz', $_SERVER['SERVER_NAME']);
        self::assertStringNotContainsString('LOCALHOST-only-secret', json_encode($GLOBALS['bar_source_config_meta'], JSON_THROW_ON_ERROR));
    }

    public static function invalidSource(): array
    {
        return [['return 1;'], ['return ["lokalne"=>true,"db"=>[]];'], ['echo "LOCALHOST-secret-output"; return ["lokalne"=>false,"db"=>[]];'],
            ['throw new RuntimeException("LOCALHOST-private-error");']];
    }

    public function testOmittedLocalFlagUsesTheLegacyApplicationsProductionDefault(): void
    {
        file_put_contents($this->root . '/includes/config.php', '<?php return ["db"=>["heslo"=>"LOCALHOST-only-secret"]];');
        $value = \barCutoverReadSourceConfig($this->root);
        self::assertArrayHasKey('db', $value);
        self::assertTrue($GLOBALS['bar_source_config_meta']['production_mode']);
        self::assertSame('null', $GLOBALS['bar_source_config_meta']['local_flag_type']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSource')]
    public function testInvalidConfigFailsWithoutLeakingOutputAndRestoresHost(string $body): void
    {
        file_put_contents($this->root . '/includes/config.php', '<?php ' . $body);
        ob_start();
        try {
            \barCutoverReadSourceConfig($this->root);
            self::fail('Invalid source accepted.');
        } catch (\RuntimeException) {
            self::assertSame('', ob_get_contents());
            self::assertSame('kis.kovopraha.cz', $_SERVER['HTTP_HOST']);
            self::assertSame('kis.kovopraha.cz', $_SERVER['SERVER_NAME']);
            self::assertStringNotContainsString('LOCALHOST-', json_encode($GLOBALS['bar_source_config_meta'], JSON_THROW_ON_ERROR));
        } finally {
            ob_end_clean();
        }
    }
}
