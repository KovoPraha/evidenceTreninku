<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_import_run_lib.php';

final class KisImportRunTest extends TestCase
{
    public function testRunIsAtomicAndLeavesNoPartialPreview(): void
    {
        $pdo = $this->createDatabase();

        try {
            \kisImportCreateRun(
                $pdo,
                [
                    ['jmeno' => 'Safe', 'prijmeni' => 'Row', 'narozeni' => null],
                    ['jmeno' => 'FORCE_FAILURE', 'prijmeni' => 'Row', 'narozeni' => null],
                ],
                ['users' => ['rows' => 2]],
                [],
                ['users' => 'synthetic-users.xlsx'],
                7
            );
            self::fail('The fixture constraint should reject the second row.');
        } catch (PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_runs')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_rows')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_matches')->fetchColumn());
        }
    }

    public function testStatsArePreservedAndStoredPayloadsExcludeSentinelSecrets(): void
    {
        $pdo = $this->createDatabase();
        $pdo->exec(<<<'SQL'
            INSERT INTO sportovci
                (id, jmeno, prijmeni, narozeni, uciid, rc, telefon, adresa_ulice)
            VALUES
                (10, 'Synthetic', 'Member', '2010-01-02', 'UCI-SYNTH-10',
                 'SECRET_RC_SENTINEL', 'SECRET_PHONE_SENTINEL', 'SECRET_ADDRESS_SENTINEL')
            SQL);

        $runId = \kisImportCreateRun(
            $pdo,
            [[
                'jmeno' => 'Synthetic',
                'prijmeni' => 'Member',
                'narozeni' => '2010-01-02',
                'uciid' => 'UCI-SYNTH-10',
                'rc' => 'SOURCE_RC_SENTINEL',
                'telefon' => 'SOURCE_PHONE_SENTINEL',
                'adresa_ulice' => 'SOURCE_ADDRESS_SENTINEL',
            ]],
            ['users' => ['sheet' => 'Synthetic', 'rows' => 1]],
            ['synthetic warning'],
            ['users' => 'synthetic-users.xlsx'],
            7
        );

        $run = $pdo->query('SELECT stats_json, warnings_json FROM kis_import_runs')->fetch(PDO::FETCH_ASSOC);
        $stats = json_decode((string)$run['stats_json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $stats['rows']);
        self::assertSame(1, $stats['warnings']);
        self::assertSame('Synthetic', $stats['created_from']['users']['sheet']);
        self::assertSame(1, $stats['matches']['matched']);

        $stored = $pdo->query(<<<'SQL'
            SELECT ir.raw_json, im.candidate_json
            FROM kis_import_rows ir
            JOIN kis_import_matches im ON im.row_id = ir.id AND im.run_id = ir.run_id
            SQL)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('{}', $stored['raw_json']);
        $serialized = (string)$stored['raw_json'] . (string)$stored['candidate_json'];
        self::assertStringNotContainsString('SECRET_', $serialized);
        self::assertStringNotContainsString('SOURCE_', $serialized);

        $candidates = json_decode((string)$stored['candidate_json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            ['id', 'jmeno', 'prijmeni', 'narozeni', 'uciid', '_match_score', '_match_reason'],
            array_keys($candidates[0])
        );
        self::assertSame(1, $runId);
    }

    private function createDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(<<<'SQL'
            CREATE TABLE sportovci (
                id INTEGER PRIMARY KEY,
                jmeno TEXT NOT NULL DEFAULT '',
                prijmeni TEXT NOT NULL DEFAULT '',
                first_name_norm TEXT,
                last_name_norm TEXT,
                narozeni TEXT,
                email TEXT,
                uciid TEXT,
                rc TEXT,
                telefon TEXT,
                adresa_ulice TEXT
            );
            CREATE TABLE kis_import_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_by INTEGER,
                status TEXT NOT NULL,
                source_users TEXT,
                source_payments TEXT,
                source_rosters TEXT,
                stats_json TEXT,
                warnings_json TEXT
            );
            CREATE TABLE kis_import_rows (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id INTEGER NOT NULL,
                person_key TEXT NOT NULL,
                jmeno TEXT NOT NULL CHECK (jmeno <> 'FORCE_FAILURE'),
                prijmeni TEXT NOT NULL,
                narozeni TEXT,
                email TEXT,
                uciid TEXT,
                oddil TEXT,
                kis_aktivni INTEGER NOT NULL DEFAULT 0,
                kis_platebne_aktivni INTEGER NOT NULL DEFAULT 0,
                kis_neuhrazeno NUMERIC NOT NULL DEFAULT 0,
                kis_posledni_uhrada TEXT,
                kis_soupisky TEXT,
                raw_json TEXT
            );
            CREATE TABLE kis_import_matches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id INTEGER NOT NULL,
                row_id INTEGER NOT NULL,
                sportovec_id INTEGER,
                match_status TEXT NOT NULL,
                confidence INTEGER NOT NULL,
                reason TEXT,
                candidate_json TEXT
            );
            SQL);
        return $pdo;
    }
}
