<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/configure-production-stripe.php';

final class ConfigureProductionStripeTest extends TestCase
{
    /** @return array{secret_key:string,publishable_key:string,webhook_secret:string,base_url:string} */
    private function settings(): array
    {
        return \kisProductionStripeValidate([
            'secret_key' => 'sk_test_unit_value',
            'publishable_key' => 'pk_test_unit_value',
            'webhook_secret' => 'whsec_unit_value',
            'base_url' => 'https://kis.kovopraha.cz',
        ], 'kis.kovopraha.cz');
    }

    public function testManagedBlockIsInsertedAfterStrictTypesAndContainsNoLiveMode(): void
    {
        $merged = \kisProductionStripeMergeConfig(
            "<?php\ndeclare(strict_types=1);\ndefine('DB_HOST', '127.0.0.1');\n",
            \kisProductionStripeManagedBlock($this->settings())
        );

        self::assertStringStartsWith("<?php\ndeclare(strict_types=1);\n\n// BEGIN KIS MANAGED STRIPE SANDBOX", $merged);
        self::assertStringContainsString("define('STRIPE_ENABLED', true)", $merged);
        self::assertStringContainsString('sk_test_unit_value', $merged);
        self::assertStringNotContainsString('sk_live_', $merged);
    }

    public function testManagedBlockIsReplacedWithoutDuplication(): void
    {
        $block = \kisProductionStripeManagedBlock($this->settings());
        $once = \kisProductionStripeMergeConfig("<?php\ndefine('DB_HOST', '127.0.0.1');\n", $block);
        $twice = \kisProductionStripeMergeConfig($once, $block);

        self::assertSame(1, substr_count($twice, \KIS_STRIPE_BLOCK_BEGIN));
        self::assertSame(1, substr_count($twice, \KIS_STRIPE_BLOCK_END));
        self::assertSame($once, $twice);
    }

    public function testLiveOrWrongHostSecretsAreRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        \kisProductionStripeValidate([
            'secret_key' => 'sk_live_forbidden',
            'publishable_key' => 'pk_live_forbidden',
            'webhook_secret' => 'whsec_unit_value',
            'base_url' => 'https://example.test',
        ], 'kis.kovopraha.cz');
    }
}
