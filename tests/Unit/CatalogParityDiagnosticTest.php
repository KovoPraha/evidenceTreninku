<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/diagnose-catalog-parity.php';

final class CatalogParityDiagnosticTest extends TestCase
{
    public function testOnlyReviewedPublicAuditFieldsLeaveTheServer(): void
    {
        $record = ['id' => 1, 'product_id' => 93, 'variant_id' => null, 'action' => 'update_product',
            'created_at' => '2026-11-10 11:45:00', 'actor_id' => 'PRIVATE_ACTOR', 'reason' => 'PRIVATE_REASON',
            'before_json' => json_encode(['public_name' => 'Původní nabídka', 'cost_minor' => 'PRIVATE_COST',
                'customer_email' => 'PRIVATE_CUSTOMER', 'nested' => ['token' => 'PRIVATE_TOKEN']]),
            'after_json' => json_encode(['public_name' => 'Nová nabídka', 'cost_minor' => 'PRIVATE_COST_2',
                'customer_email' => 'PRIVATE_CUSTOMER_2', 'nested' => ['token' => 'PRIVATE_TOKEN_2']])];
        $result = \catalogParityPublicEvent($record);
        self::assertSame(['public_name' => ['before' => 'Původní nabídka', 'after' => 'Nová nabídka']], $result['public_changes']);
        self::assertStringNotContainsString('PRIVATE_', json_encode($result, JSON_THROW_ON_ERROR));
        self::assertNull($result['variant_id']);
    }

    public function testMalformedAndNestedPayloadsAreNotExported(): void
    {
        $result = \catalogParityPublicEvent(['id' => 2, 'product_id' => 93, 'action' => 'update',
            'created_at' => '2026-11-10 11:45:00', 'before_json' => 'invalid',
            'after_json' => '{"public_name":{"token":"PRIVATE_TOKEN"},"reason":"PRIVATE_REASON"}']);
        self::assertSame([], $result['public_changes']);
        self::assertStringNotContainsString('PRIVATE_', json_encode($result, JSON_THROW_ON_ERROR));
    }
}
