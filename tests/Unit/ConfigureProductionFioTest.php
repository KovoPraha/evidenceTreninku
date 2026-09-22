<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/bin/configure-production-fio.php';

final class ConfigureProductionFioTest extends TestCase
{
    /** @return array{token:string,lookback_days:int} */
    private function settings():array{return \kisProductionFioValidate(['token'=>'unit_readonly_token_1234567890','lookback_days'=>3]);}

    public function testManagedBlockIsIdempotentAndContainsFailClosedFlag():void
    {
        $block=\kisProductionFioManagedBlock($this->settings(),true);$once=\kisProductionFioMergeConfig("<?php\ndeclare(strict_types=1);\ndefine('DB_HOST','127.0.0.1');\n",$block);$twice=\kisProductionFioMergeConfig($once,$block);
        self::assertSame($once,$twice);self::assertStringContainsString("define('FIO_IMPORT_ENABLED', true)",$once);self::assertStringContainsString("define('FIO_API_TOKEN', 'unit_readonly_token_1234567890')",$once);self::assertSame(1,substr_count($once,\KIS_FIO_BLOCK_BEGIN));self::assertSame(1,substr_count($once,\KIS_FIO_BLOCK_END));
    }

    public function testDisabledBlockKeepsCredentialButDisablesImport():void
    {
        $block=\kisProductionFioManagedBlock($this->settings(),false);self::assertStringContainsString("define('FIO_IMPORT_ENABLED', false)",$block);self::assertStringContainsString('unit_readonly_token_1234567890',$block);
    }

    public function testMalformedTokenAndLookbackAreRejected():void
    {
        $rejected=0;foreach([['token'=>'short','lookback_days'=>3],['token'=>'unit_readonly_token_1234567890','lookback_days'=>31]]as$invalid){try{\kisProductionFioValidate($invalid);self::fail('Neplatné nastavení musí být odmítnuto.');}catch(\RuntimeException){$rejected++;}}self::assertSame(2,$rejected);
    }

    public function testWorkflowsKeepTokenOutOfCommandsAndUseProtectedProduction():void
    {
        $root=dirname(__DIR__,2);$configure=(string)file_get_contents($root.'/.github/workflows/configure-fio-production.yml');$schedule=(string)file_get_contents($root.'/.github/workflows/fio-import-production.yml');
        self::assertStringContainsString('secrets.KIS_FIO_API_TOKEN',$configure);self::assertStringContainsString('environment: production',$configure);self::assertStringContainsString('ZAPNOUT-FIO-READONLY',$configure);self::assertStringNotContainsString('echo "$FIO_API_TOKEN_VALUE"',$configure);self::assertStringNotContainsString('set -x',$configure);
        self::assertStringContainsString("cron: '*/10 * * * *'",$schedule);self::assertStringContainsString('FIO_IMPORT_ENABLED',$schedule);self::assertStringNotContainsString('KIS_FIO_API_TOKEN',$schedule);self::assertStringContainsString('environment: production',$schedule);
        self::assertStringContainsString('exit(78)',$schedule);self::assertStringContainsString('kontrola plateb neproběhla',$schedule);
    }
}
