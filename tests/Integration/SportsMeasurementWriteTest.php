<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sports_measurement_input.php';

final class SportsMeasurementWriteTest extends TestCase
{
    public function testSharedInsertPersistsLegacyAndNormalizedValuesTogether(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE mereni_zaznamy('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,typ TEXT,sportovec_id INTEGER,vzdalenost REAL,cas TEXT,prevod TEXT,'
            . 'cvik_id INTEGER,segment_id INTEGER,vaha REAL,opakovani INTEGER,rpe TEXT,poznamka TEXT,'
            . 'contract_version TEXT,distance_unit TEXT,distance_meters REAL,duration_ms INTEGER,rpe_value REAL)');

        $rows = \sportsMeasurementRows([[
            'typ' => 'beh',
            'sportovec_id' => 7,
            'vzdalenost' => '400',
            'distance_unit' => 'm',
            'cas' => '01:12.34',
        ]]);
        $statement = $pdo->prepare(\sportsMeasurementInsertSql());
        $statement->execute(\sportsMeasurementInsertParameters($rows[0]));

        $stored = $pdo->query('SELECT * FROM mereni_zaznamy')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('sports-measurement-v1', $stored['contract_version']);
        self::assertSame('m', $stored['distance_unit']);
        self::assertSame(400.0, (float)$stored['distance_meters']);
        self::assertSame(72340, (int)$stored['duration_ms']);
        self::assertSame('01:12.34', $stored['cas']);
    }
}
