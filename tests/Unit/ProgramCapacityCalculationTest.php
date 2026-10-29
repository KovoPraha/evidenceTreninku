<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/club_program_capacity.php';

final class ProgramCapacityCalculationTest extends TestCase
{
    public function testOrderAndDuplicateRowsDoNotChangeIdentityMaximaOrMutateInputs(): void
    {
        $active = [50 => true];
        $rows = [
            ['id' => 1, 'beneficiary_sportovec_id' => '50', 'quantity' => 8],
            ['id' => 2, 'beneficiary_sportovec_id' => 51, 'athlete_registration_request_id' => 99, 'quantity' => 2],
            ['id' => 3, 'beneficiary_sportovec_id' => 51, 'quantity' => 4],
            ['id' => 4, 'athlete_registration_request_id' => '51', 'quantity' => 3],
            ['id' => 5, 'athlete_registration_request_id' => 51, 'quantity' => 1],
            ['id' => 6, 'quantity' => 2],
        ];
        $original = [$active, $rows];
        $expected = ['active_enrollment_count' => 1, 'held_order_count' => 9, 'occupied_count' => 10, 'available_count' => 2];
        foreach ([$rows, array_reverse($rows), array_merge($rows, $rows)] as $input) {
            self::assertSame($expected, \clubProgramCapacityFromRows($active, $input, true, 0, '12'));
        }
        self::assertSame($original, [$active, $rows]);
        self::assertSame(
            ['active_enrollment_count' => 1, 'held_order_count' => 5, 'occupied_count' => 6, 'available_count' => null],
            \clubProgramCapacityFromRows($active, $rows, true, 51, null)
        );
        self::assertSame(
            ['active_enrollment_count' => 1, 'held_order_count' => 17, 'occupied_count' => 18, 'available_count' => 0],
            \clubProgramCapacityFromRows($active, $rows, false, 0, 12)
        );
    }
}
