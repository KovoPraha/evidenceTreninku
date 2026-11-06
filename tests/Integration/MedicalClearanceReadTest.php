<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\MedicalClearanceListBaseline;
use Tests\Support\MedicalClearanceReadFixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/medical_clearance.php';

final class MedicalClearanceReadTest extends TestCase
{
    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('PRAGMA foreign_keys=ON');
        MedicalClearanceReadFixture::seed($pdo);
        return $pdo;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testAllFiltersAndClampedPagesMatchTheFrozenAlgorithm(): void
    {
        $pdo = $this->database();
        $inputs = MedicalClearanceReadFixture::inputs();
        $changes = $pdo->query('SELECT total_changes()')->fetchColumn();
        foreach ($inputs as $input) {
            $expected = MedicalClearanceListBaseline::read($pdo, $input);
            $actual = medicalClearanceList($pdo, $input);
            self::assertSame($expected, $actual, json_encode($input, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('PRIVATE SECRET', json_encode($actual, JSON_THROW_ON_ERROR));
        }
        self::assertSame($changes, $pdo->query('SELECT total_changes()')->fetchColumn());
    }

    public function testPageReadsOnlyItsRecordsAndStateScanStreamsHistories(): void
    {
        $pdo = $this->database();
        $pdo->profiling = true;
        $list = medicalClearanceList($pdo, []);
        self::assertCount(25, $list['rows']);
        self::assertCount(3, $pdo->profile);
        self::assertSame(25, $pdo->profile[1]['fetched_rows']);
        self::assertLessThanOrEqual(75, $pdo->profile[2]['fetched_rows']);
        $pdo->profile = [];
        medicalClearanceList($pdo, ['medical_state' => 'valid']);
        self::assertCount(1, $pdo->profile);
        foreach ($pdo->profile as $query) {
            self::assertStringStartsWith('SELECT', $query['sql']);
            self::assertLessThanOrEqual(431 * 3, $query['fetched_rows']);
        }
    }

    public function testPersonLookupFindsAnyPersonEscapesSearchAndKeepsSelection(): void
    {
        $pdo = $this->database();
        $first = medicalClearancePersonChoices($pdo, [], 57);
        self::assertSame(57, $first['total']);
        self::assertCount(26, $first['rows']);
        self::assertSame(57, (int)$first['selected']['id']);
        $ids = [];
        for ($page = 1; $page <= 3; $page++) {
            $choices = medicalClearancePersonChoices($pdo, ['medical_person_page' => $page], 0);
            self::assertLessThanOrEqual(25, count($choices['rows']));
            $ids = [...$ids, ...array_column($choices['rows'], 'id')];
        }
        sort($ids);
        self::assertSame(range(1, 57), $ids);
        $match = medicalClearancePersonChoices($pdo, ['medical_person_q' => 'Žáková 100%_!'], 0);
        self::assertSame([57], array_column($match['rows'], 'id'));
        self::assertSame(0, medicalClearancePersonChoices($pdo, ['medical_person_q' => "%' OR 1=1 --"], 0)['total']);
        self::assertSame(57, (int)medicalClearancePersonChoices($pdo, ['medical_person_q' => 'No such name'], 57)['rows'][0]['id']);
        self::assertNull(medicalClearancePersonChoices($pdo, [], 999)['selected']);
    }

    public function testPersonLookupRejectsChangedRoleAndRevokedRegistrar(): void
    {
        $pdo = $this->database();
        $_SESSION['staff_positions'] = ['coach'];
        $_SESSION['staff_active_position'] = 'coach';
        try {
            medicalClearancePersonChoices($pdo, [], 0);
            self::fail('Wrong role accepted');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $_SESSION['staff_positions'] = ['registrar'];
        $_SESSION['staff_active_position'] = 'registrar';
        $pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(DomainException::class);
        medicalClearancePersonChoices($pdo, [], 0);
    }
}
