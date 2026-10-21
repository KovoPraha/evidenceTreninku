<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ShopAnonymousCheckoutBootstrapTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testQuickProgramLoadsItsBankSettingsOnAColdRequest(): void
    {
        $this->assertPageLoadsBankSettings('rychla_prihlaska.php');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGuestPurchaseLoadsItsBankSettingsOnAColdRequest(): void
    {
        $this->assertPageLoadsBankSettings('rychly_nakup.php');
    }

    private function assertPageLoadsBankSettings(string $page): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/booking/' . $page);
        $bootstrap = explode("\nfunction ", str_replace("\r\n", "\n", $source), 2)[0];
        preg_match_all("~require_once\\s+dirname\\(__DIR__\\)\\s*\\.\\s*['\"](/[^'\"]+)['\"]\\s*;~", $bootstrap, $matches);

        self::assertNotEmpty($matches[1]);
        // Load the page's declared dependencies in a fresh process, without
        // opening the real database or inheriting helpers from other tests.
        foreach ($matches[1] as $path) {
            if ($path !== '/db.php') require_once $root . $path;
        }

        self::assertTrue(function_exists('shopBankSettingsEffective'), $page . ' must load the bank helper before POST checkout.');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE shop_bank_settings(id INTEGER PRIMARY KEY,iban TEXT,bic TEXT,account_label TEXT,due_days INTEGER,updated_at TEXT,updated_by_trainer_id INTEGER)');
        $pdo->exec("INSERT INTO shop_bank_settings VALUES(1,'CZ2799990000000011111111','','LOCALHOST NEPLATIT',5,CURRENT_TIMESTAMP,7)");

        self::assertSame([
            'iban' => 'CZ2799990000000011111111',
            'bic' => '',
            'account_label' => 'LOCALHOST NEPLATIT',
            'due_days' => 5,
        ], \shopBankSettingsEffective($pdo));
    }
}
