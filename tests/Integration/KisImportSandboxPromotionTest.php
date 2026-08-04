<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_import_sandbox_promotion.php';

final class KisImportSandboxPromotionTest extends TestCase
{
    public function testPromoteRollbackAndReapplyAreAuditedIdempotentAndIsolated(): void
    {
        $pdo = $this->database();
        [$runId, $fingerprint] = $this->preview($pdo);
        $peopleBefore = (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn();

        $applied = \kisImportSandboxPromote($pdo, $runId, $fingerprint, 7, 'První sandbox test.', true, true);
        self::assertFalse($applied['idempotent']);
        self::assertSame('applied', $applied['status']);
        self::assertSame(2, (int)$applied['active_items']);
        self::assertSame(1, (int)$applied['event_count']);
        self::assertSame($peopleBefore, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());

        $again = \kisImportSandboxPromote($pdo, $runId, $fingerprint, 7, 'Opakovaný sandbox test.', true, true);
        self::assertTrue($again['idempotent']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_events')->fetchColumn());

        $firstMatchId = (int)$pdo->query('SELECT MIN(id) FROM kis_import_matches WHERE run_id=' . $runId)->fetchColumn();
        $pdo->exec("UPDATE kis_import_matches SET match_status='conflict' WHERE id=" . $firstMatchId);
        $rolledBack = \kisImportSandboxRollback($pdo, $runId, $fingerprint, 7, 'Kompenzační návrat.', true, true);
        self::assertFalse($rolledBack['idempotent']);
        self::assertSame('rolled_back', $rolledBack['status']);
        self::assertSame(0, (int)$rolledBack['active_items']);
        self::assertSame(2, (int)$rolledBack['event_count']);
        self::assertSame($peopleBefore, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());

        $rollbackAgain = \kisImportSandboxRollback($pdo, $runId, $fingerprint, 7, 'Opakovaný návrat.', true, true);
        self::assertTrue($rollbackAgain['idempotent']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_events')->fetchColumn());

        $pdo->exec("UPDATE kis_import_matches SET match_status='new' WHERE id=" . $firstMatchId);
        $reapplied = \kisImportSandboxPromote($pdo, $runId, $fingerprint, 7, 'Opakovaná aplikace.', true, true);
        self::assertTrue($reapplied['reapplied']);
        self::assertSame('applied', $reapplied['status']);
        self::assertSame(2, (int)$reapplied['apply_count']);
        self::assertSame(2, (int)$reapplied['active_items']);
        self::assertSame(3, (int)$reapplied['event_count']);
        self::assertSame($peopleBefore, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertSame(
            ['applied', 'rolled_back', 'reapplied'],
            $pdo->query('SELECT action FROM kis_import_sandbox_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testStalePreviewOrMissingLocalAuthorizationCannotPromote(): void
    {
        $pdo = $this->database();
        [$runId, $fingerprint] = $this->preview($pdo);
        try {
            \kisImportSandboxPromote($pdo, $runId, $fingerprint, 7, 'Bez localhost oprávnění.', true, false);
            self::fail('Missing localhost authorization must block promote.');
        } catch (\KisImportSandboxException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_promotions')->fetchColumn());
        }

        $pdo->exec("UPDATE kis_import_matches SET match_status='conflict',sportovec_id=NULL WHERE run_id=" . $runId . ' AND id=(SELECT MIN(id) FROM kis_import_matches WHERE run_id=' . $runId . ')');
        try {
            \kisImportSandboxPromote($pdo, $runId, $fingerprint, 7, 'Starý fingerprint.', true, true);
            self::fail('Stale preview must block promote.');
        } catch (\KisImportSandboxException $exception) {
            self::assertStringContainsString('změnil', $exception->getMessage());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_promotions')->fetchColumn());
        }
    }

    public function testInjectedItemFailureRollsBackPromotionAndAudit(): void
    {
        $pdo = $this->database();
        [$runId, $fingerprint] = $this->preview($pdo);
        $pdo->exec("CREATE TRIGGER fail_sandbox_item BEFORE INSERT ON kis_import_sandbox_items BEGIN SELECT RAISE(ABORT,'sandbox item failure'); END");
        try {
            \kisImportSandboxPromote($pdo, $runId, $fingerprint, 7, 'Test atomického rollbacku.', true, true);
            self::fail('Injected item failure must abort promotion.');
        } catch (\PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_promotions')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_items')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_events')->fetchColumn());
        }
    }

    private function preview(PDO $pdo): array
    {
        $pdo->exec("INSERT INTO kis_import_source_artifacts(source_kind,contract_version,sha256,byte_size,original_filename,storage_key,archived_by) VALUES('users','sandbox-v1','" . str_repeat('b', 64) . "',123,'sandbox.xlsx','sandbox.raw',7)");
        $artifactId = (int)$pdo->lastInsertId();
        $runId = \kisImportCreateRun(
            $pdo,
            [
                ['kis_external_id' => 'KIS-SANDBOX-1', '_kis_external_id_raw' => 'KIS-SANDBOX-1', 'jmeno' => 'Sandbox', 'prijmeni' => 'Create', 'narozeni' => '2012-01-01'],
                ['kis_external_id' => 'KIS-SANDBOX-2', '_kis_external_id_raw' => 'KIS-SANDBOX-2', 'jmeno' => 'Sandbox', 'prijmeni' => 'Second', 'narozeni' => '2013-02-02'],
            ],
            [
                'users' => ['headers' => ['kisid', 'jmeno', 'prijmeni', 'datumnarozeni'], 'rows' => 2],
                'payments' => ['headers' => ['kisid', 'idplatby', 'stav', 'castka'], 'rows' => 2],
                'soupisky' => ['headers' => ['kisid', 'soupiska', 'jmeno', 'prijmeni'], 'rows' => 2],
            ],
            [],
            ['users' => 'sandbox.xlsx'],
            7,
            ['users' => $artifactId]
        );
        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
        $report = \kisImportStoredPreviewReport($run);
        self::assertSame('ready_for_test_review', $report['status']);
        return [$runId, (string)$report['fingerprint']];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec(<<<'SQL'
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,first_name_norm TEXT,last_name_norm TEXT,narozeni TEXT,email TEXT,uciid TEXT);
            INSERT INTO sportovci VALUES(10,'Existing','Person','existing','person','2010-01-02',NULL,'EXISTING-10');
            CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_by INTEGER,status TEXT,source_users TEXT,source_payments TEXT,source_rosters TEXT,stats_json TEXT,warnings_json TEXT);
            CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,person_key TEXT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT,oddil TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,kis_posledni_uhrada TEXT,kis_soupisky TEXT,raw_json TEXT);
            CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,row_id INTEGER,sportovec_id INTEGER,match_status TEXT,confidence INTEGER,reason TEXT,candidate_json TEXT);
            SQL);
        foreach (['20260804233000_kis_import_source_artifacts.php', '20260804234500_kis_import_preview_integrity.php', '20260804234700_kis_import_sandbox_promotion.php', '20260804234800_kis_import_field_contract.php'] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        return $pdo;
    }
}
