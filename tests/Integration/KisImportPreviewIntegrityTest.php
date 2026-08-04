<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_import_run_lib.php';

final class KisImportPreviewIntegrityTest extends TestCase
{
    public function testArchivedPreviewIsCompleteDeterministicAndContainsNoPersonData(): void
    {
        $pdo = $this->database();
        $artifactId = $this->artifact($pdo);
        $runId = \kisImportCreateRun(
            $pdo,
            [
                ['jmeno' => 'Synthetic', 'prijmeni' => 'Member', 'narozeni' => '2010-01-02', 'uciid' => 'UCI-SYNTH-10'],
                ['jmeno' => 'Brand', 'prijmeni' => 'New', 'narozeni' => '2013-03-04'],
            ],
            [],
            [],
            ['users' => 'synthetic-users.xlsx'],
            7,
            ['users' => $artifactId]
        );

        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
        $report = \kisImportStoredPreviewReport($run);
        self::assertNotNull($report);
        self::assertSame('ready_for_test_review', $report['status']);
        self::assertSame(2, $report['summary']['classified_rows']);
        self::assertSame(0, $report['summary']['blocker_rows']);
        self::assertSame(1, $report['summary']['counts']['exact_match']);
        self::assertSame(1, $report['summary']['counts']['create']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $report['fingerprint']);
        self::assertSame($report['fingerprint'], \kisImportFinalizePreview($pdo, $runId)['fingerprint']);

        $secondRunId = \kisImportCreateRun(
            $pdo,
            [
                ['jmeno' => 'Synthetic', 'prijmeni' => 'Member', 'narozeni' => '2010-01-02', 'uciid' => 'UCI-SYNTH-10'],
                ['jmeno' => 'Brand', 'prijmeni' => 'New', 'narozeni' => '2013-03-04'],
            ],
            [],
            [],
            ['users' => 'synthetic-users.xlsx'],
            7,
            ['users' => $artifactId]
        );
        $second = \kisImportStoredPreviewReport(
            $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $secondRunId)->fetch(PDO::FETCH_ASSOC)
        );
        self::assertSame($report['fingerprint'], $second['fingerprint']);

        $serialized = json_encode($report, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Synthetic', $serialized);
        self::assertStringNotContainsString('Member', $serialized);
        self::assertStringNotContainsString('UCI-SYNTH-10', $serialized);
    }

    public function testPreviewWithoutImmutableArchiveIsBlockedPerRow(): void
    {
        $pdo = $this->database();
        $runId = \kisImportCreateRun(
            $pdo,
            [['jmeno' => 'No', 'prijmeni' => 'Archive', 'narozeni' => '2011-01-01']],
            [],
            [],
            ['users' => 'unarchived.xlsx'],
            7
        );
        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
        $report = \kisImportStoredPreviewReport($run);
        self::assertSame('blocked', $report['status']);
        self::assertSame(1, $report['summary']['blocker_rows']);
        self::assertSame('missing_without_archive', $report['rows'][0]['action']);
        self::assertSame('archived_source_required', $report['rows'][0]['reason']);
    }

    public function testDuplicateCanonicalTargetBecomesConflict(): void
    {
        $pdo = $this->database();
        $runId = \kisImportCreateRun(
            $pdo,
            [
                ['jmeno' => 'Synthetic', 'prijmeni' => 'Member', 'narozeni' => '2010-01-02'],
                ['jmeno' => 'Synthetic', 'prijmeni' => 'Member', 'narozeni' => '2010-01-02'],
            ],
            [],
            [],
            ['users' => 'duplicate.xlsx'],
            7,
            ['users' => $this->artifact($pdo)]
        );
        $report = \kisImportStoredPreviewReport(
            $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC)
        );
        self::assertSame('blocked', $report['status']);
        self::assertSame(2, $report['summary']['counts']['conflict']);
        self::assertSame(['duplicate_target', 'duplicate_target'], array_column($report['rows'], 'reason'));
    }

    public function testPreviewFailureRollsBackWholeRun(): void
    {
        $pdo = $this->database();
        $pdo->exec("CREATE TRIGGER fail_preview BEFORE UPDATE ON kis_import_runs BEGIN SELECT RAISE(ABORT,'preview failure'); END");
        try {
            \kisImportCreateRun(
                $pdo,
                [['jmeno' => 'Atomic', 'prijmeni' => 'Failure', 'narozeni' => '2012-01-01']],
                [],
                [],
                ['users' => 'failure.xlsx'],
                7
            );
            self::fail('Injected preview failure must abort the run.');
        } catch (\PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_runs')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_rows')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_matches')->fetchColumn());
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,first_name_norm TEXT,last_name_norm TEXT,narozeni TEXT,email TEXT,uciid TEXT);
            INSERT INTO sportovci VALUES(10,'Synthetic','Member','synthetic','member','2010-01-02',NULL,'UCI-SYNTH-10');
            CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_by INTEGER,status TEXT,source_users TEXT,source_payments TEXT,source_rosters TEXT,stats_json TEXT,warnings_json TEXT);
            CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,person_key TEXT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT,oddil TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,kis_posledni_uhrada TEXT,kis_soupisky TEXT,raw_json TEXT);
            CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,row_id INTEGER,sportovec_id INTEGER,match_status TEXT,confidence INTEGER,reason TEXT,candidate_json TEXT);
            SQL);
        foreach (['20260804233000_kis_import_source_artifacts.php', '20260804234500_kis_import_preview_integrity.php'] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        return $pdo;
    }

    private function artifact(PDO $pdo): int
    {
        $pdo->exec("INSERT INTO kis_import_source_artifacts(source_kind,contract_version,sha256,byte_size,original_filename,storage_key,archived_by) VALUES('users','synthetic-v1','" . str_repeat('a', 64) . "',123,'users.xlsx','users-synthetic.raw',7)");
        return (int)$pdo->lastInsertId();
    }
}
