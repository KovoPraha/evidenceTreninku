<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MedicalStateFixture;
use Tests\Support\QueryProfilePdo;

use function Tests\Support\medicalStateBaselineList;
use function Tests\Support\medicalStateBaselinePage;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/MedicalStateFixture.php';
require_once dirname(__DIR__) . '/Support/MedicalStateBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/medical_clearance.php';

final class MedicalStateReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    private function seeded(int $watches = 240): QueryProfilePdo
    {
        $pdo = $this->database();
        MedicalStateFixture::seed($pdo, $watches);
        return $pdo;
    }

    public function testFrozenReferenceMatchesEveryStateSearchAndPage(): void
    {
        $pdo = $this->seeded();
        $pdo->exec("UPDATE medical_clearance_records SET state='PENDING' WHERE watch_id=4;
            UPDATE medical_clearance_records SET state='revoked ' WHERE watch_id=5");
        foreach (['', 'valid', 'expired', 'not_yet_valid', 'pending', 'missing', 'revoked', 'bad'] as $state) {
            foreach ([1, 2, 999999] as $page) {
                foreach (['', 'Synthetic', 'Žáková 100%_!', "%' OR 1=1 --"] as $q) {
                    $input = ['medical_state' => $state, 'medical_page' => $page, 'medical_q' => $q];
                    self::assertSame(medicalStateBaselineList($pdo, $input), \medicalClearanceList($pdo, $input), json_encode($input));
                }
            }
        }
        foreach ([1, 8, 99999] as $person) {
            foreach (['valid', 'missing', 'pending'] as $state) {
                $input = ['medical_state' => $state, 'person_id' => $person, 'medical_page' => 99999];
                self::assertSame(medicalStateBaselineList($pdo, $input), \medicalClearanceList($pdo, $input));
            }
        }
    }

    public function testDateBoundariesAndLegacyReplacementGraphsRemainCanonical(): void
    {
        $pdo = $this->seeded(60);
        $today = new \DateTimeImmutable('today');
        $future = $today->modify('+5 days')->format('Y-m-d');
        $pdo->exec("UPDATE medical_clearance_records SET issued_on='$future' WHERE watch_id=8;
            UPDATE medical_clearance_records SET replaces_id=82 WHERE id=80;
            INSERT INTO medical_clearance_records VALUES(82,8,'superseded','$future','$future',80,'PRIVATE MEDICAL SENTINEL');
            UPDATE medical_clearance_records SET replaces_id=999999 WHERE id=441;
            UPDATE medical_clearance_records SET replaces_id=90 WHERE id=201;
            UPDATE medical_clearance_records SET state='revoked' WHERE id=320;");
        $base = 'FROM medical_clearance_watches w JOIN sportovci s ON s.id=w.sportovec_id WHERE 1=1';
        foreach ([-2, -1, 0, 1, 2, 6] as $offset) {
            $on = $today->modify(($offset < 0 ? '' : '+') . $offset . ' days')->format('Y-m-d');
            foreach (['valid', 'expired', 'not_yet_valid', 'pending', 'missing', 'revoked'] as $state) {
                $filters = \staffListFilters(['medical_state' => $state], 'medical_', array_fill_keys([$state], $state));
                self::assertSame(
                    medicalStateBaselinePage($pdo, $base, [], $filters, $on),
                    \MedicalClearanceStateRead::page($pdo, $base, [], $filters, $on)
                );
            }
        }
    }

    public function testOrdinaryVolumeTransfersOnlyTheResultPage(): void
    {
        $pdo = $this->seeded(2400);
        $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL');
        $input = ['medical_state' => 'valid'];
        $expected = medicalStateBaselineList($pdo, $input);
        $pdo->profile = [];
        $pdo->profiling = true;
        $actual = \medicalClearanceList($pdo, $input);
        self::assertSame($expected, $actual);
        self::assertLessThanOrEqual(3, count($pdo->profile));
        self::assertLessThanOrEqual(26, array_sum(array_column($pdo->profile, 'fetched_rows')));
        self::assertStringNotContainsString('PRIVATE MEDICAL SENTINEL', json_encode($actual));
        foreach ($actual['rows'] as $row) {
            self::assertFalse((bool) array_filter(array_keys($row), static fn (string $k): bool => str_starts_with($k, '_medical_read_')));
        }
    }

    public function testFreshChangesAndTransactionReadShapeArePreserved(): void
    {
        $pdo = $this->seeded();
        foreach (["UPDATE medical_clearance_records SET state='revoked' WHERE state='verified'",
            "UPDATE medical_clearance_watches SET next_review_on='2030-01-01' WHERE id=3",
            'DELETE FROM medical_clearance_records WHERE watch_id=4'] as $mutation) {
            \medicalClearanceList($pdo, ['medical_state' => 'valid']);
            $pdo->exec($mutation);
            $input = ['medical_state' => 'valid'];
            self::assertSame(medicalStateBaselineList($pdo, $input), \medicalClearanceList($pdo, $input));
        }
        $pdo->beginTransaction();
        try {
            $pdo->profiling = true;
            $pdo->profile = [];
            $expected = medicalStateBaselineList($pdo, ['medical_state' => 'missing']);
            $before = $pdo->profile;
            $pdo->profile = [];
            self::assertSame($expected, \medicalClearanceList($pdo, ['medical_state' => 'missing']));
            self::assertSame(array_column($before, 'sql'), array_column($pdo->profile, 'sql'));
            self::assertSame(array_column($before, 'parameters'), array_column($pdo->profile, 'parameters'));
        } finally {
            $pdo->rollBack();
        }
    }

    public function testManyFutureChainsUseTheStreamingFallback(): void
    {
        $pdo = $this->seeded(1700);
        $input = ['medical_state' => 'valid'];
        $expected = medicalStateBaselineList($pdo, $input);
        $pdo->profiling = true;
        $pdo->profile = [];
        self::assertSame($expected, \medicalClearanceList($pdo, $input));
        self::assertSame(129, $pdo->profile[0]['fetched_rows']);
        self::assertTrue((bool) array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'medical_record_')));
    }

    public function testNativeUnbufferedModeSurvivesSuccessAndFailure(): void
    {
        $pdo = $this->seeded();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::assertSame(
                medicalStateBaselineList($pdo, ['medical_state' => 'valid']),
                \medicalClearanceList($pdo, ['medical_state' => 'valid'])
            );
            return;
        }
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        self::assertSame(
            medicalStateBaselineList($pdo, ['medical_state' => 'valid']),
            \medicalClearanceList($pdo, ['medical_state' => 'valid'])
        );
        self::assertFalse($pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY));
        $filters = \staffListFilters(['medical_state' => 'valid'], 'medical_', ['valid' => 'Valid']);
        try {
            \MedicalClearanceStateRead::page($pdo, 'FROM medical_clearance_watches w WHERE w.missing_column=?', [1], $filters, date('Y-m-d'));
            self::fail('Invalid SQL must fail.');
        } catch (\PDOException) {
            self::assertFalse($pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY));
        }
        self::assertSame(1, (int) $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testVeryLongReplacementHistoryIsNotTruncated(): void
    {
        $pdo = $this->seeded(12);
        $insert = $pdo->prepare("INSERT INTO medical_clearance_records VALUES(?,8,'superseded',?,?,NULL,'PRIVATE MEDICAL SENTINEL')");
        $pdo->beginTransaction();
        for ($i = 1000; $i < 5100; $i++) {
            $insert->execute([$i, date('Y-m-d'), date('Y-m-d')]);
        }
        $pdo->commit();
        $input = ['medical_state' => 'valid'];
        $expected = medicalStateBaselineList($pdo, $input);
        $pdo->profiling = true;
        $pdo->profile = [];
        self::assertSame($expected, \medicalClearanceList($pdo, $input));
        self::assertSame(4097, $pdo->profile[1]['fetched_rows']);
        self::assertTrue((bool) array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'medical_record_')));
    }
}
