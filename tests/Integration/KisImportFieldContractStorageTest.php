<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_import_run_lib.php';

final class KisImportFieldContractStorageTest extends TestCase
{
    public function testReadyContractAndExternalIdAreStoredWithoutPiiInReport(): void
    {
        $pdo = $this->database();
        $person = ['kis_external_id' => 'KIS-9001', '_kis_external_id_raw' => 'KIS-9001', 'jmeno' => 'Storage', 'prijmeni' => 'Secret', 'narozeni' => '2011-02-03'];
        $runId = \kisImportCreateRun($pdo, [$person], $this->meta(), [], ['users' => 'users.csv'], 7);
        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
        $report = \kisFieldContractStoredReport($run);

        self::assertSame('ready_for_parity', $report['status']);
        self::assertSame('KIS-9001', $pdo->query('SELECT kis_external_id FROM kis_import_rows')->fetchColumn());
        self::assertStringNotContainsString('KIS-9001', (string)$run['field_contract_report_json']);
        self::assertStringNotContainsString('Storage', (string)$run['field_contract_report_json']);

        $secondRunId = \kisImportCreateRun($pdo, [$person], $this->meta(), [], ['users' => 'users.csv'], 7);
        $second = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $secondRunId)->fetch(PDO::FETCH_ASSOC);
        self::assertSame($report['fingerprint'], \kisFieldContractStoredReport($second)['fingerprint']);
    }

    public function testLegacyRowsRemainPreviewableButFieldContractIsBlocked(): void
    {
        $pdo = $this->database();
        $runId = \kisImportCreateRun(
            $pdo,
            [['jmeno' => 'Legacy', 'prijmeni' => 'Preview', 'narozeni' => '2010-01-01']],
            ['users' => ['headers' => ['jmeno', 'prijmeni', 'datumnarozeni'], 'rows' => 1]],
            [],
            ['users' => 'legacy.csv'],
            7
        );
        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('blocked', \kisFieldContractStoredReport($run)['status']);
        self::assertSame('preview', $run['status']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_rows')->fetchColumn());
    }

    private function meta(): array
    {
        return [
            'users' => ['headers' => ['kisid', 'jmeno', 'prijmeni', 'datumnarozeni'], 'rows' => 1],
            'payments' => ['headers' => ['kisid', 'idplatby', 'stav', 'castka', 'datumuhrady'], 'rows' => 1],
            'soupisky' => ['headers' => ['kisid', 'soupiska', 'jmeno', 'prijmeni'], 'rows' => 1],
        ];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,first_name_norm TEXT,last_name_norm TEXT,narozeni TEXT,email TEXT,uciid TEXT);
            CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_by INTEGER,status TEXT,source_users TEXT,source_payments TEXT,source_rosters TEXT,stats_json TEXT,warnings_json TEXT);
            CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,person_key TEXT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT,oddil TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,kis_posledni_uhrada TEXT,kis_soupisky TEXT,raw_json TEXT);
            CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,row_id INTEGER,sportovec_id INTEGER,match_status TEXT,confidence INTEGER,reason TEXT,candidate_json TEXT);
            SQL);
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804234800_kis_import_field_contract.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
