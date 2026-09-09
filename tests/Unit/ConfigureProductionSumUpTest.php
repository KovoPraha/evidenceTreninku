<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/bin/configure-production-sumup.php';

final class ConfigureProductionSumUpTest extends TestCase
{
    /** @return array{api_key:string,merchant_code:string,base_url:string} */
    private function settings(): array
    {
        return \kisProductionSumUpValidate([
            'api_key'=>'sup_sk_unit_value',
            'merchant_code'=>'MELE4XUL',
            'base_url'=>'https://kis.kovopraha.cz',
        ],'kis.kovopraha.cz');
    }

    public function testManagedLiveBlockIsInsertedAndReplacedWithoutDuplication(): void
    {
        $block=\kisProductionSumUpManagedBlock($this->settings(),true);
        $once=\kisProductionSumUpMergeConfig("<?php\ndeclare(strict_types=1);\ndefine('DB_HOST','127.0.0.1');\n",$block);
        $twice=\kisProductionSumUpMergeConfig($once,$block);
        self::assertSame($once,$twice);
        self::assertStringContainsString("define('SUMUP_ENABLED', true)",$once);
        self::assertSame(1,substr_count($once,\KIS_SUMUP_BLOCK_BEGIN));
        self::assertSame(1,substr_count($once,\KIS_SUMUP_BLOCK_END));
    }

    public function testDisabledBlockKeepsCredentialButFailsClosed(): void
    {
        $block=\kisProductionSumUpManagedBlock($this->settings(),false);
        self::assertStringContainsString("define('SUMUP_ENABLED', false)",$block);
        self::assertStringContainsString('sup_sk_unit_value',$block);
    }

    public function testWrongHostAndMalformedCredentialsAreRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        \kisProductionSumUpValidate(['api_key'=>'bad','merchant_code'=>'INVALID','base_url'=>'https://example.test'],'kis.kovopraha.cz');
    }

    public function testProductionWorkflowKeepsSecretOutOfCommandsAndValidatesInterpolatedPaths(): void
    {
        $workflow=(string)file_get_contents(dirname(__DIR__,2).'/.github/workflows/configure-sumup-production.yml');
        self::assertStringContainsString('secrets.KIS_SUMUP_API_KEY',$workflow);
        self::assertStringContainsString('vars.KIS_SUMUP_MERCHANT_CODE',$workflow);
        self::assertStringContainsString('[[ "$APP_HOST" =~ ^[a-z0-9.-]+$ ]]',$workflow);
        self::assertStringContainsString('[[ "$REMOTE_DIR" =~ ^[A-Za-z0-9._/-]+$ ]]',$workflow);
        self::assertStringContainsString('actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1',$workflow);
        self::assertStringNotContainsString('echo "$SUMUP_API_KEY_VALUE"',$workflow);
        self::assertStringNotContainsString('set -x',$workflow);
    }
}
