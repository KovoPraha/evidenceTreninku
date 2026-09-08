<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/uat_readiness.php';

final class UatReadinessTest extends TestCase
{
    public function testEveryRequiredGateMustBeGreen(): void
    {
        $snapshot = [
            'release' => [
                'sha' => str_repeat('a', 40),
                'run_id' => '123456',
                'deployed_at' => '2026-09-08T10:00:00+00:00',
                'uat_approved' => true,
            ],
            'parents' => 2,
            'children' => 2,
            'staff_id' => 10,
            'positions' => 8,
            'superadmin' => true,
            'fixtures' => [
                'goods' => 1, 'program' => 1, 'free_event' => 1, 'paid_event' => 1,
                'velodrome' => 2, 'lesson' => 1, 'training' => 1, 'calendar_event' => 1,
            ],
            'technical_public_without_prefix' => 0,
            'stripe' => ['enabled' => true, 'test_pair' => true, 'live_pair' => false, 'webhook' => true],
            'bank_ready' => true,
            'bank_reconciliation_ready' => true,
            'inbox_ready' => true,
            'queue_available' => true,
            'queue_failed' => 0,
            'owner' => 'Vedoucí UAT',
            'window_end' => '2026-09-10 18:00:00',
        ];
        $now = new DateTimeImmutable('2026-09-08 12:00:00', new \DateTimeZone('Europe/Prague'));

        $ready = \uatReadinessEvaluate($snapshot, $now);
        self::assertTrue($ready['ready']);
        self::assertCount(10, $ready['checks']);

        $snapshot['stripe']['live_pair'] = true;
        $blocked = \uatReadinessEvaluate($snapshot, $now);
        self::assertFalse($blocked['ready']);
        $stripe = array_values(array_filter($blocked['checks'], static fn(array $check): bool => $check['key'] === 'stripe'))[0];
        self::assertFalse($stripe['ok']);
        self::assertStringContainsString('STOP', $stripe['detail']);
    }
}
