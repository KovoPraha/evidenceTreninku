<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MedicalStateFixture;
use Tests\Support\QueryProfilePdo;

use function Tests\Support\medicalFilterBaselineList;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/MedicalStateFixture.php';
require_once dirname(__DIR__) . '/Support/MedicalFilterBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/medical_clearance.php';

final class MedicalFilterReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    private function seeded(int $size = 1200, bool $indexed = true): QueryProfilePdo
    {
        $pdo = $this->database();
        MedicalStateFixture::seed($pdo, $size);
        $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL WHERE watch_id>100');
        if ($indexed) {
            $migration = require dirname(__DIR__, 2) . '/migrations/20261108070000_medical_read_covering_index.php';
            $migration['up']($pdo);
        }
        return $pdo;
    }

    public function testCoveringIndexMigrationIsIdempotentAndKeepsRecordValues(): void
    {
        $pdo = $this->seeded(60, false);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261108070000_medical_read_covering_index.php';
        $before = $pdo->query('SELECT * FROM medical_clearance_records ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertFalse($migration['verify']($pdo));
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $indexes = \MedicalClearanceReadIndex::definitions($pdo);
        $name = \MedicalClearanceReadIndex::coveringName($indexes);
        self::assertNotNull($name);
        self::assertSame(\MedicalClearanceReadIndex::COLUMNS, array_slice($indexes[$name]['columns'], 0, 6));
        $migration['up']($pdo);
        self::assertSame($indexes, \MedicalClearanceReadIndex::definitions($pdo));
        self::assertSame($before, $pdo->query('SELECT * FROM medical_clearance_records ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testEquivalentIndexIsReusedInsteadOfDuplicated(): void
    {
        $pdo = $this->seeded(60, false);
        $pdo->exec('CREATE INDEX existing_medical_cover ON medical_clearance_records(watch_id,id,state,issued_on,valid_until,replaces_id)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261108070000_medical_read_covering_index.php';
        $migration['up']($pdo);
        self::assertSame('existing_medical_cover', \MedicalClearanceReadIndex::coveringName(\MedicalClearanceReadIndex::definitions($pdo)));
        self::assertArrayNotHasKey(\MedicalClearanceReadIndex::NAME, \MedicalClearanceReadIndex::definitions($pdo));
    }

    public function testIndexNameCollisionDoesNotReplaceAnExistingIndex(): void
    {
        $pdo = $this->seeded(60, false);
        $pdo->exec('CREATE INDEX ' . \MedicalClearanceReadIndex::NAME . ' ON medical_clearance_records(state)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261108070000_medical_read_covering_index.php';
        $migration['up']($pdo);
        $indexes = \MedicalClearanceReadIndex::definitions($pdo);
        self::assertSame(['state'], $indexes[\MedicalClearanceReadIndex::NAME]['columns']);
        self::assertNotNull(\MedicalClearanceReadIndex::coveringName($indexes));
        self::assertNotSame(\MedicalClearanceReadIndex::NAME, \MedicalClearanceReadIndex::coveringName($indexes));
    }

    public function testIndexInventoryReadsMetadataWithoutExposingRecordValues(): void
    {
        require_once dirname(__DIR__, 2) . '/bin/diagnose-medical-read-indexes.php';
        $pdo = $this->seeded(60);
        $before = $pdo->query('SELECT * FROM medical_clearance_records ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? 'START TRANSACTION READ ONLY' : 'PRAGMA query_only=ON');
        try {
            $inventory = \medicalReadIndexInventory($pdo);
            self::assertTrue($inventory['exists']);
            self::assertNotNull($inventory['covering_index']);
            self::assertFalse($inventory['clinical_rows_read']);
            self::assertFalse($inventory['ddl_executed']);
            self::assertStringNotContainsString('PRIVATE MEDICAL SENTINEL', json_encode($inventory));
            self::assertSame($before, $pdo->query('SELECT * FROM medical_clearance_records ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            if ($mysql) {
                $pdo->rollBack();
            } else {
                $pdo->exec('PRAGMA query_only=OFF');
            }
        }
    }

    public function testAllStatesSearchDateAndPagesMatchCurrentMain(): void
    {
        $pdo = $this->seeded();
        $today = new \DateTimeImmutable('today');
        $d = static fn (int $n): string => $today->modify('+' . $n . ' days')->format('Y-m-d');
        $filters = [['medical_q' => 'Synthetic'], ['medical_q' => 'Žáková 100%_!'],
            ['medical_q' => 'purpose 1199'], ['medical_q' => '__no_such_person__'],
            ['medical_q' => "%' OR 1=1 --"], ['medical_from' => $d(1), 'medical_to' => $d(1)],
            ['medical_from' => $d(1), 'medical_to' => $d(2)],
            ['medical_q' => 'Synthetic', 'medical_to' => $d(1)]];
        foreach (['', 'valid', 'expired', 'not_yet_valid', 'pending', 'revoked', 'missing', 'bad'] as $state) {
            foreach ([1, 2, 999999] as $page) {
                foreach ($filters as $filter) {
                    $input = $filter + ['medical_state' => $state, 'medical_page' => $page];
                    $expected = medicalFilterBaselineList($pdo, $input);
                    $actual = \medicalClearanceList($pdo, $input);
                    self::assertSame($expected, $actual, json_encode($input));
                    self::assertStringNotContainsString('PRIVATE MEDICAL SENTINEL', json_encode($actual));
                }
            }
        }
        foreach ([['medical_from' => $d(1)], ['medical_to' => $d(2)], ['medical_from' => '2026-02-31'],
            ['medical_from' => $d(2), 'medical_to' => $d(1)], ['medical_q' => ['invalid']]] as $filter) {
            foreach (['', 'valid', 'missing'] as $state) {
                $input = $filter + ['medical_state' => $state, 'medical_page' => 999999];
                self::assertSame(medicalFilterBaselineList($pdo, $input), \medicalClearanceList($pdo, $input));
            }
        }
    }

    public function testBroadDateFiltersKeepBrokenAndRevokedFutureGraphsCanonical(): void
    {
        $pdo = $this->seeded();
        $future = date('Y-m-d', strtotime('+5 days'));
        $pdo->exec("UPDATE medical_clearance_records SET issued_on='$future' WHERE watch_id=8;
            UPDATE medical_clearance_records SET replaces_id=82 WHERE id=80;
            INSERT INTO medical_clearance_records VALUES(82,8,'superseded','$future','$future',80,'PRIVATE MEDICAL SENTINEL');
            UPDATE medical_clearance_records SET replaces_id=999999 WHERE id=441;
            UPDATE medical_clearance_records SET replaces_id=90 WHERE id=201;
            UPDATE medical_clearance_records SET state='revoked' WHERE id=320;");
        foreach (['valid', 'expired', 'not_yet_valid', 'pending', 'revoked', 'missing'] as $state) {
            foreach ([1, 999999] as $page) {
                $input = ['medical_q' => 'Synthetic', 'medical_from' => date('Y-m-d'),
                    'medical_to' => date('Y-m-d', strtotime('+3 days')), 'medical_state' => $state, 'medical_page' => $page];
                self::assertSame(medicalFilterBaselineList($pdo, $input), \medicalClearanceList($pdo, $input), json_encode($input));
            }
        }
    }

    public function testBroadProjectionAndEmptyResultsHaveBoundedReads(): void
    {
        $pdo = $this->seeded();
        $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL');
        foreach ([['medical_q' => 'Synthetic'], ['medical_from' => date('Y-m-d'),
            'medical_to' => date('Y-m-d', strtotime('+1 day'))]] as $filter) {
            $input = $filter + ['medical_state' => 'valid'];
            $expected = medicalFilterBaselineList($pdo, $input);
            $pdo->profiling = true;
            $pdo->profile = [];
            self::assertSame($expected, \medicalClearanceList($pdo, $input));
            self::assertLessThanOrEqual(4, count($pdo->profile));
            self::assertSame(257, $pdo->profile[0]['fetched_rows']);
            self::assertLessThanOrEqual(307, array_sum(array_column($pdo->profile, 'fetched_rows')));
            $pdo->profiling = false;
        }
        foreach (['', 'valid'] as $state) {
            $input = ['medical_q' => '__no_such_person__', 'medical_state' => $state, 'medical_page' => 999999];
            $expected = medicalFilterBaselineList($pdo, $input);
            $pdo->profiling = true;
            $pdo->profile = [];
            self::assertSame($expected, \medicalClearanceList($pdo, $input));
            self::assertCount(1, $pdo->profile);
            self::assertSame(0, $pdo->profile[0]['fetched_rows']);
            $pdo->profiling = false;
        }
    }

    public function testCandidateBoundaryPreservesCanonicalStatusesAndClamping(): void
    {
        $pdo = $this->seeded(600);
        foreach ([256, 257] as $count) {
            $pdo->exec("UPDATE medical_clearance_watches SET purpose='other'");
            $pdo->exec("UPDATE medical_clearance_watches SET purpose='boundary' WHERE id<=$count");
            foreach (['', 'valid', 'missing'] as $state) {
                $input = ['medical_q' => 'boundary', 'medical_state' => $state, 'medical_page' => 999999];
                $expected = medicalFilterBaselineList($pdo, $input);
                $pdo->profiling = true;
                $pdo->profile = [];
                self::assertSame($expected, \medicalClearanceList($pdo, $input));
                self::assertSame($count, $pdo->profile[0]['fetched_rows']);
                if ($state !== '') {
                    $sql = implode(' ', array_column($pdo->profile, 'sql'));
                    self::assertStringContainsString($count === 256 ? 'w.id IN (' : 'COUNT(*) OVER ()', $sql);
                }
                $pdo->profiling = false;
            }
        }
    }

    public function testPersonAndTransactionsKeepTheExactOriginalSql(): void
    {
        $pdo = $this->seeded(60);
        foreach ([false, true] as $transaction) {
            if ($transaction) {
                $pdo->beginTransaction();
            }
            try {
                foreach (['', 'valid'] as $state) {
                    $input = ['medical_q' => 'Synthetic', 'medical_from' => date('Y-m-d'), 'medical_state' => $state];
                    if (!$transaction) {
                        $input['person_id'] = 1;
                    }
                    $pdo->profiling = true;
                    $pdo->profile = [];
                    $expected = medicalFilterBaselineList($pdo, $input);
                    $before = $pdo->profile;
                    $pdo->profile = [];
                    self::assertSame($expected, \medicalClearanceList($pdo, $input));
                    self::assertSame(array_column($before, 'sql'), array_column($pdo->profile, 'sql'));
                    self::assertSame(array_column($before, 'parameters'), array_column($pdo->profile, 'parameters'));
                    $pdo->profiling = false;
                }
            } finally {
                if ($transaction) {
                    $pdo->rollBack();
                }
            }
        }
    }

    public function testFreshPersonDateAndRecordChangesAreNotCached(): void
    {
        $pdo = $this->seeded(600);
        $input = ['medical_q' => 'Synthetic', 'medical_from' => date('Y-m-d'), 'medical_state' => 'valid'];
        foreach (["UPDATE sportovci SET jmeno='Changed',prijmeni='Changed' WHERE id=1",
            "UPDATE medical_clearance_watches SET next_review_on='2000-01-01' WHERE id=9",
            "UPDATE medical_clearance_records SET state='revoked' WHERE state='verified' AND watch_id=10",
            'DELETE FROM medical_clearance_records WHERE watch_id=13'] as $mutation) {
            \medicalClearanceList($pdo, $input);
            $pdo->exec($mutation);
            self::assertSame(medicalFilterBaselineList($pdo, $input), \medicalClearanceList($pdo, $input));
        }
    }

    public function testFutureAndHistoryFallbacksStillMatchCurrentMain(): void
    {
        $pdo = $this->seeded(1700);
        $pdo->exec("UPDATE medical_clearance_records SET replaces_id=watch_id*10 WHERE state='verified' AND watch_id%12=8");
        $input = ['medical_q' => 'Synthetic', 'medical_state' => 'valid'];
        $expected = medicalFilterBaselineList($pdo, $input);
        $pdo->profiling = true;
        $pdo->profile = [];
        self::assertSame($expected, \medicalClearanceList($pdo, $input));
        self::assertSame(129, $pdo->profile[1]['fetched_rows']);
        $pdo->profiling = false;
        $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL WHERE watch_id>100');
        $pdo->beginTransaction();
        $insert = $pdo->prepare("INSERT INTO medical_clearance_records VALUES(?,8,'superseded',?,?,NULL,'PRIVATE MEDICAL SENTINEL')");
        for ($i = 0; $i < 4100; $i++) {
            $insert->execute([2000000 + $i, date('Y-m-d'), date('Y-m-d')]);
        }
        $pdo->commit();
        $expected = medicalFilterBaselineList($pdo, $input);
        $pdo->profiling = true;
        $pdo->profile = [];
        self::assertSame($expected, \medicalClearanceList($pdo, $input));
        self::assertSame(4097, $pdo->profile[2]['fetched_rows']);
    }

    public function testNativeUnbufferedModeSurvivesEveryCandidatePathAndFailure(): void
    {
        $pdo = $this->seeded();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::assertSame(medicalFilterBaselineList($pdo, ['medical_q' => 'Synthetic']), \medicalClearanceList($pdo, ['medical_q' => 'Synthetic']));
            return;
        }
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $disabled = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        self::assertFalse((bool) $disabled);
        foreach ([['medical_q' => 'Synthetic', 'medical_state' => 'valid'],
            ['medical_q' => 'purpose 1199', 'medical_state' => 'missing'],
            ['medical_q' => '__no_such_person__', 'medical_state' => 'valid']] as $input) {
            self::assertSame(medicalFilterBaselineList($pdo, $input), \medicalClearanceList($pdo, $input));
            self::assertSame($disabled, $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY));
            self::assertSame(1, (int) $pdo->query('SELECT 1')->fetchColumn());
        }
        $pdo->exec('RENAME TABLE medical_clearance_records TO medical_clearance_records_failed');
        try {
            try {
                \medicalClearanceList($pdo, ['medical_q' => 'Synthetic', 'medical_state' => 'valid']);
                self::fail('Expected an owned missing-table failure.');
            } catch (\PDOException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
            self::assertSame($disabled, $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY));
            self::assertSame(1, (int) $pdo->query('SELECT 1')->fetchColumn());
        } finally {
            $pdo->exec('RENAME TABLE medical_clearance_records_failed TO medical_clearance_records');
        }
    }
}
