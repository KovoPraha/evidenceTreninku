<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopBankSettingsWiringTest extends TestCase
{
    /**
     * Kdo změní IBAN, přesměruje všechny příchozí platby klubu. Obrazovka proto
     * stojí na konfigurovatelném oprávnění se seedem na nejpřísnější roli;
     * chybí-li klíč v relaci, canAccess() spadne zpět na 'hlavni'.
     */
    public function testAdminScreenIsGuardedBeforeAnyDatabaseWork(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/eshop_bank_admin.php');

        self::assertStringContainsString("canAccess('eshop_bank_settings')", $page);
        self::assertStringContainsString("header('Location: login.php')", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString("header('Location: eshop_bank_admin.php', true, 303)", $page);
        self::assertStringNotContainsString('roleAtLeast(\'trener\')', $page);

        $guard = strpos($page, "canAccess('eshop_bank_settings')");
        self::assertIsInt($guard);
        foreach (['shopBankSettingsSave', 'shopBankSettingsResolve', 'shopBankSampleQr'] as $call) {
            self::assertGreaterThan($guard, strpos($page, $call), $call . ' se nesmí volat před kontrolou oprávnění.');
        }
    }

    public function testMigrationSeedsTheStrictestRoleAndBothOwnedTables(): void
    {
        $migration = (string)file_get_contents(
            dirname(__DIR__, 2) . '/migrations/20260819120000_shop_bank_settings.php'
        );
        self::assertStringContainsString("'eshop_bank_settings',", $migration);
        self::assertStringContainsString("'admin',", $migration);
        self::assertStringContainsString('INSERT IGNORE INTO opravneni', $migration);

        $backup = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/db-backup.php');
        foreach (['shop_bank_settings', 'shop_bank_settings_events'] as $table) {
            self::assertStringContainsString("    '" . $table . "',", $backup, $table . ' musí být ve vlastnictví zálohy.');
        }
    }

    public function testEveryBankReaderUsesTheResolvedSettings(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'booking/eshop.php' => 'shopBankSettingsEffective($pdo)',
            'bin/fio-import.php' => 'shopBankSettingsEffective($pdo)',
            'eshop_admin.php' => 'shopBankSettingsResolve($pdo)',
            'bin/deploy-preflight.php' => 'shopBankSettingsEffective(',
        ] as $file => $needle) {
            $source = (string)file_get_contents($root . '/' . $file);
            self::assertStringContainsString($needle, $source, $file);
            self::assertStringNotContainsString('shopBankSettingsFromConfig()', $source, $file . ' už nesmí číst jen konstanty.');
        }
        // Fio se nesmí párovat proti konstantě, když databáze určuje jiný účet.
        self::assertStringNotContainsString('SHOP_BANK_IBAN', (string)file_get_contents($root . '/bin/fio-import.php'));
    }

    public function testSingleValidatorCoversTheAccountLabelRange(): void
    {
        $checkout = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/shop_checkout.php');
        $settings = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/shop_bank_settings.php');

        self::assertStringContainsString("mb_strlen(\$label,'UTF-8')<3", $checkout);
        self::assertStringContainsString("mb_strlen(\$label,'UTF-8')>120", $checkout);
        self::assertStringContainsString('shopBankValidateSettings', $settings);
        // Administrace nesmí mít vlastní kopii pravidel.
        self::assertStringNotContainsString('preg_match(\'/^CZ[0-9]{22}$/D\'', $settings);
        self::assertStringNotContainsString('%97', $settings);
    }

    public function testSampleQrHelperCannotWriteToTheDatabase(): void
    {
        $settings = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/shop_bank_settings.php');
        $start = strpos($settings, 'function shopBankSampleQr(');
        self::assertIsInt($start);
        $body = substr($settings, $start);

        self::assertStringNotContainsString('PDO', $body, 'Ukázka nesmí dostat spojení do databáze.');
        foreach (['INSERT', 'UPDATE', 'DELETE', 'prepare(', 'exec('] as $needle) {
            self::assertStringNotContainsString($needle, $body, 'Ukázka nesmí zapisovat: ' . $needle);
        }
    }
}
