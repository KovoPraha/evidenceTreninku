<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/payment_variable_symbol_schema.php';

final class PaymentVariableSymbolSchemaTest extends TestCase
{
    public function testOnlyExactHistoricalContentReceivesCompatibilityOverride(): void
    {
        $definition = require dirname(__DIR__, 2) . '/migrations/20260922190000_member_fee_standing_orders.php';
        self::assertSame($definition, \paymentStandingOrderMigrationCompatibility($definition, str_repeat('0', 64)));
        foreach (['d47af0b6ace92ab8fdc316f0e8741d3735b7a70c802f26447231c9283bbf7f42', 'a7aab616aa0e2dfa5d587774952b98fdf24777bbc4c5c4242331c571496a8494'] as $checksum) {
            $patched = \paymentStandingOrderMigrationCompatibility($definition, $checksum);
            self::assertNotSame($definition['up'], $patched['up']);
            self::assertNotSame($definition['verify'], $patched['verify']);
            self::assertSame($definition['id'], $patched['id']);
            $other = array_replace($definition, ['id' => '20261024000000_other_migration']);
            self::assertSame($other, \paymentStandingOrderMigrationCompatibility($other, $checksum));
        }
    }

    public function testCompatibilityDelegatesSqliteAndPreservesItsResult(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $calls = [];
        $definition = [
            'id' => '20260922190000_member_fee_standing_orders',
            'up' => static function (PDO $db) use (&$calls): void { $calls[] = $db; },
            'verify' => static function (PDO $db) use (&$calls): bool { $calls[] = $db; return false; },
        ];
        $patched = \paymentStandingOrderMigrationCompatibility($definition, 'a7aab616aa0e2dfa5d587774952b98fdf24777bbc4c5c4242331c571496a8494');
        $patched['up']($pdo);
        self::assertFalse($patched['verify']($pdo));
        self::assertSame([$pdo, $pdo], $calls);
    }

    public function testSymbolIndexDetectionPreservesCompositePaymentIdentity(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY, variable_symbol TEXT, payable_type TEXT, payable_id INTEGER)');
        $pdo->exec('CREATE UNIQUE INDEX uq_payable ON payments(payable_type,payable_id)');
        $pdo->exec('CREATE UNIQUE INDEX uq_composite ON payments(variable_symbol,payable_id)');
        $pdo->exec('CREATE INDEX idx_symbol ON payments(variable_symbol)');
        self::assertSame([], \paymentVariableSymbolUniqueIndexes($pdo));
        $pdo->exec('CREATE UNIQUE INDEX uq_symbol ON payments(variable_symbol)');
        self::assertSame(['uq_symbol'], \paymentVariableSymbolUniqueIndexes($pdo));
        $this->expectException(\LogicException::class);
        \paymentVariableSymbolRepairMysql($pdo);
    }
}
