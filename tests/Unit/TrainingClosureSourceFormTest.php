<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/training_closure_form.php';

final class TrainingClosureSourceFormTest extends TestCase
{
    public function testCorrectionKeepsPaperEvidenceAndRequiresFreshApproval(): void
    {
        $current = ['version' => 1, 'snapshot' => ['record_source' => 'paper']];
        $form = \trainingClosureFormState(null, $current, [17], [23]);

        self::assertSame('paper', $form['record_source']);
        self::assertSame('', $form['reason']);
        self::assertFalse($form['confirm_action']);
        self::assertSame(1, $form['version']);
    }

    #[DataProvider('legacySources')]
    public function testNewOrLegacyAttendanceDefaultsToCoach(?array $current): void
    {
        self::assertSame('coach', \trainingClosureFormState(null, $current, [], [])['record_source']);
    }

    public static function legacySources(): array
    {
        return [
            'new attendance' => [null],
            'legacy snapshot absent' => [['version' => 1]],
            'coach evidence' => [['snapshot' => ['record_source' => 'coach']]],
            'unknown legacy value' => [['snapshot' => ['record_source' => 'unknown']]],
        ];
    }

    public function testExplicitFailedCorrectionOverridesStoredSource(): void
    {
        $form = \trainingClosureFormState(
            ['version' => '1', 'record_source' => 'coach'],
            ['version' => 2, 'snapshot' => ['record_source' => 'paper']],
            [],
            []
        );

        self::assertSame('coach', $form['record_source']);
        self::assertSame(1, $form['version']);
        self::assertFalse($form['confirm_action']);
    }
}
