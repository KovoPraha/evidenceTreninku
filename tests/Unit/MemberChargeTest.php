<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_charge.php';

final class MemberChargeTest extends TestCase
{
    public function testContractKeepsChargeAndPaymentSeparate(): void
    {
        $contract = \memberChargeTargetContract();

        self::assertSame('member-charge-v1', $contract['contract']);
        self::assertSame('member_charge', $contract['payable_type']);
        self::assertTrue($contract['rules']['payment_separate']);
        self::assertTrue($contract['rules']['source_idempotent']);
    }

    public function testBuildsSanitizedKisProjection(): void
    {
        $projection = \memberChargeProjection([
            'payment_external_id' => ' pay-2026-001 ',
            'status' => 'pending',
            'amount_minor' => 250000,
            'outstanding_minor' => 50000,
            'currency' => 'czk',
            'due_on' => '2026-02-15',
            'paid_on' => null,
        ]);

        self::assertSame('kis_import', $projection['source_system']);
        self::assertSame('PAY-2026-001', $projection['source_external_id']);
        self::assertSame(250000, $projection['amount_minor']);
        self::assertSame(50000, $projection['outstanding_minor']);
        self::assertSame('CZK', $projection['currency']);
    }

    #[DataProvider('invalidRows')]
    public function testRejectsInvalidProjection(array $row): void
    {
        $this->expectException(InvalidArgumentException::class);
        \memberChargeProjection($row);
    }

    public static function invalidRows(): array
    {
        $valid = [
            'payment_external_id' => 'PAY-1',
            'status' => 'pending',
            'amount_minor' => 10000,
            'outstanding_minor' => 10000,
            'currency' => 'CZK',
        ];

        return [
            'missing stable id' => [array_replace($valid, ['payment_external_id' => ''])],
            'outstanding over amount' => [array_replace($valid, ['outstanding_minor' => 10001])],
            'paid with balance' => [array_replace($valid, ['status' => 'paid'])],
            'invalid currency' => [array_replace($valid, ['currency' => 'Kc'])],
            'unknown status' => [array_replace($valid, ['status' => 'overdue'])],
        ];
    }
}
