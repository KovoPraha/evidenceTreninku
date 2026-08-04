<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_member_charge_promotion.php';

final class KisMemberChargePromotionTest extends TestCase
{
    public function testPromoteIsAuditedIdempotentAndRollbackCanBeReapplied(): void
    {
        [$pdo, $runId] = $this->preview();
        $before = $this->parity($pdo, $runId);
        self::assertSame(['payment_prescriptions_not_promoted'], $before['coverage_blockers']);

        $applied = \kisMemberChargePromote($pdo, $runId, $before['fingerprint'], 7, 'Testovací přenos M2.3g.', true, true);
        self::assertFalse($applied['idempotent']);
        self::assertSame('applied', $applied['status']);
        self::assertSame(1, (int)$applied['active_items']);
        self::assertSame(1, (int)$applied['payment_count']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame('paid', $pdo->query('SELECT status FROM club_member_charges')->fetchColumn());
        $payment = $pdo->query('SELECT * FROM payments')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('member_charge', $payment['payable_type']);
        self::assertSame('kis_import', $payment['method']);
        self::assertSame('paid', $payment['status']);
        self::assertSame('2026-01-15', substr((string)$payment['paid_at'], 0, 10));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charge_events')->fetchColumn());

        $after = $this->parity($pdo, $runId);
        self::assertTrue($after['cutover_ready']);
        self::assertSame(1, $after['domains']['payment_prescriptions']['target_same']);
        self::assertSame([], $after['coverage_blockers']);
        $repeat = \kisMemberChargePromote($pdo, $runId, $after['fingerprint'], 7, 'Opakování bez zápisu.', true, true);
        self::assertTrue($repeat['idempotent']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_charge_promotion_events')->fetchColumn());

        $rolledBack = \kisMemberChargeRollback($pdo, $runId, $after['fingerprint'], 7, 'Vrácení testovacího přenosu.', true, true);
        self::assertFalse($rolledBack['idempotent']);
        self::assertSame('rolled_back', $rolledBack['status']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charge_events')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_charge_promotion_events')->fetchColumn());

        $restoredParity = $this->parity($pdo, $runId);
        $reapplied = \kisMemberChargePromote($pdo, $runId, $restoredParity['fingerprint'], 7, 'Druhé testovací přenesení.', true, true);
        self::assertTrue($reapplied['reapplied']);
        self::assertSame(2, (int)$reapplied['apply_count']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_charge_promotion_events')->fetchColumn());
    }

    public function testChangedStagingAndUnsafeContextFailClosed(): void
    {
        [$pdo, $runId] = $this->preview();
        $report = $this->parity($pdo, $runId);
        try {
            \kisMemberChargePromote($pdo, $runId, $report['fingerprint'], 7, 'Mimo localhost.', true, false);
            self::fail('Non-local promotion must be rejected.');
        } catch (\KisMemberChargePromotionException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        }

        $pdo->exec('UPDATE kis_import_payment_rows SET amount_minor=260000');
        try {
            \kisMemberChargePromote($pdo, $runId, $report['fingerprint'], 7, 'Změněný staging.', true, true);
            self::fail('Staging drift must invalidate the parity fingerprint.');
        } catch (\KisMemberChargePromotionException $exception) {
            self::assertStringContainsString('fingerprintu', $exception->getMessage());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_charge_promotions')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        }
    }

    public function testRollbackRefusesTargetWithAdditionalAuditHistory(): void
    {
        [$pdo, $runId] = $this->preview();
        $before = $this->parity($pdo, $runId);
        \kisMemberChargePromote($pdo, $runId, $before['fingerprint'], 7, 'První testovací přenos.', true, true);
        $chargeId = (int)$pdo->query('SELECT id FROM club_member_charges')->fetchColumn();
        $pdo->prepare("INSERT INTO club_member_charge_events(charge_id,action,from_status,to_status,actor_type,actor_id,reason,snapshot_json) VALUES (?,'manual_note','paid','paid','trainer',7,'Pozdější zásah','{}')")
            ->execute([$chargeId]);
        $after = $this->parity($pdo, $runId);

        try {
            \kisMemberChargeRollback($pdo, $runId, $after['fingerprint'], 7, 'Nebezpečný rollback.', true, true);
            self::fail('Rollback must preserve a target changed after promotion.');
        } catch (\KisMemberChargePromotionException $exception) {
            self::assertStringContainsString('historie', $exception->getMessage());
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
            self::assertSame('applied', $pdo->query('SELECT status FROM kis_import_charge_promotions')->fetchColumn());
        }
    }

    /** @return array{0:PDO,1:int} */
    private function preview(): array
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,kis_external_id,kis_aktivni,kis_platebne_aktivni,kis_neuhrazeno,kis_posledni_uhrada,kis_soupisky) VALUES(10,'Localhost','ChargeOne','2012-01-01','KIS-M23G-001',1,1,0,'2026-01-15','Testovaci soupiska')");
        $artifactId = $this->artifact($pdo);
        $people = [[
            'kis_external_id' => 'KIS-M23G-001', '_kis_external_id_raw' => 'KIS-M23G-001',
            'jmeno' => 'Localhost', 'prijmeni' => 'ChargeOne', 'narozeni' => '2012-01-01',
            'kis_aktivni' => 1, 'kis_platebne_aktivni' => 1, 'kis_neuhrazeno' => 0,
            'kis_posledni_uhrada' => '2026-01-15', 'kis_soupisky' => 'Testovaci soupiska',
            '_soupisky_parsed' => ['Testovaci soupiska'], '_kis_payment' => ['paid_rows' => 1, 'open_rows' => 0],
            '_kis_payment_rows' => [[
                'payment_external_id' => 'PAY-M23G-001', 'status' => 'paid',
                'amount_minor' => 250000, 'outstanding_minor' => 0, 'currency' => 'CZK',
                'due_on' => '2026-01-31', 'paid_on' => '2026-01-15',
            ]],
        ]];
        $meta = [
            'users' => ['headers' => ['kisid','jmeno','prijmeni','datumnarozeni'], 'rows' => 1],
            'payments' => ['headers' => ['kisid','idplatby','stav','castka','datumuhrady'], 'rows' => 1],
            'soupisky' => ['headers' => ['kisid','soupiska','jmeno','prijmeni'], 'rows' => 1],
        ];
        $runId = \kisImportCreateRun($pdo, $people, $meta, [], ['users' => 'm23g-users.xlsx'], 7, ['users' => $artifactId]);
        return [$pdo, $runId];
    }

    /** @return array<string,mixed> */
    private function parity(PDO $pdo, int $runId): array
    {
        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
        $report = \kisImportStoredParityReport($run);
        self::assertNotNull($report);
        return $report;
    }

    private function artifact(PDO $pdo): int
    {
        $hash = str_repeat('d', 64);
        $pdo->prepare('INSERT INTO kis_import_source_artifacts(source_kind,contract_version,sha256,byte_size,original_filename,storage_key,archived_by) VALUES(?,?,?,?,?,?,?)')
            ->execute(['users','m23g-v1',$hash,123,'m23g-users.xlsx','m23g-users.raw',7]);
        return (int)$pdo->lastInsertId();
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec(<<<'SQL'
            CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT);
            INSERT INTO treneri VALUES(7,'Admin');
            CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY);
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,first_name_norm TEXT,last_name_norm TEXT,narozeni TEXT,email TEXT,uciid TEXT,kis_aktivni INTEGER DEFAULT 0,kis_platebne_aktivni INTEGER DEFAULT 0,kis_neuhrazeno REAL DEFAULT 0,kis_posledni_uhrada TEXT,kis_soupisky TEXT);
            CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_by INTEGER,status TEXT,source_users TEXT,source_payments TEXT,source_rosters TEXT,stats_json TEXT,warnings_json TEXT);
            CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,person_key TEXT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT,oddil TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,kis_posledni_uhrada TEXT,kis_soupisky TEXT,raw_json TEXT);
            CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,row_id INTEGER,sportovec_id INTEGER,match_status TEXT,confidence INTEGER,reason TEXT,candidate_json TEXT);
            CREATE TABLE payments(id INTEGER PRIMARY KEY AUTOINCREMENT,payable_type TEXT NOT NULL,payable_id INTEGER NOT NULL,method TEXT NOT NULL,status TEXT NOT NULL,amount_minor INTEGER NOT NULL,currency TEXT NOT NULL,variable_symbol TEXT NOT NULL UNIQUE,iban_snapshot TEXT NOT NULL,bic_snapshot TEXT NULL,account_label_snapshot TEXT NOT NULL,spd_payload TEXT NOT NULL,due_at TEXT NOT NULL,paid_at TEXT NULL,confirmed_by_trainer_id INTEGER NULL,confirmation_note TEXT NULL,UNIQUE(payable_type,payable_id));
            SQL);
        foreach ([
            '20260804233000_kis_import_source_artifacts.php',
            '20260804234500_kis_import_preview_integrity.php',
            '20260804234800_kis_import_field_contract.php',
            '20260804234900_kis_import_parity_report.php',
            '20260804234950_member_charge_target.php',
            '20260804234975_kis_member_charge_promotion.php',
        ] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        return $pdo;
    }
}
