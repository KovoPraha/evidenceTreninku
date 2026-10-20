<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/disable-production-stripe.php';

final class DisableProductionStripeTest extends TestCase
{
    public function testManagedFlagIsDisabledWithoutChangingKeysOrOtherSettings(): void
    {
        $old = "<?php\ndeclare(strict_types=1);\n// BEGIN KIS MANAGED STRIPE SANDBOX\n"
            . "defined('STRIPE_ENABLED') || define('STRIPE_ENABLED', true);\n"
            . "defined('STRIPE_SECRET_KEY') || define('STRIPE_SECRET_KEY', 'sk_test_secret');\n"
            . "// END KIS MANAGED STRIPE SANDBOX\ndefine('DB_HOST', 'localhost');\n";
        $new = \kisStripeDisabledConfig($old);
        self::assertStringContainsString("define('STRIPE_ENABLED', false)", $new);
        self::assertStringContainsString("define('STRIPE_SECRET_KEY', 'sk_test_secret')", $new);
        self::assertStringContainsString("define('DB_HOST', 'localhost')", $new);
        self::assertSame($new, \kisStripeDisabledConfig($new));
    }

    public function testLegacyDefinitionsAreDisabledIncludingDynamicExpression(): void
    {
        $old = "<?php\ndefine(\"STRIPE_ENABLED\", true);\n"
            . "defined('STRIPE_ENABLED') || define('STRIPE_ENABLED', getenv('STRIPE_ENABLED') === '1');\n";
        $new = \kisStripeDisabledConfig($old);
        self::assertSame(2, substr_count($new, "define('STRIPE_ENABLED', false)" )
            + substr_count($new, 'define("STRIPE_ENABLED", false)'));
        self::assertSame($new, \kisStripeDisabledConfig($new));
    }

    public function testMissingFlagGetsDisabledDefinition(): void
    {
        $new = \kisStripeDisabledConfig("<?php\ndeclare(strict_types=1);\ndefine('DB_HOST', 'localhost');\n");
        self::assertStringContainsString("defined('STRIPE_ENABLED') || define('STRIPE_ENABLED', false);", $new);
    }

    public function testUnsupportedConstFailsClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        \kisStripeDisabledConfig("<?php\nconst STRIPE_ENABLED = true;\n");
    }
}
