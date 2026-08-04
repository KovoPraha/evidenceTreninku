<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_import_field_contract.php';

final class KisImportFieldContractTest extends TestCase
{
    public function testCompleteContractIsDeterministicAndDoesNotExposeIdentity(): void
    {
        $people = [
            ['kis_external_id' => 'KIS-1001', '_kis_external_id_raw' => 'KIS-1001', 'jmeno' => 'Secret', 'prijmeni' => 'Person'],
            ['kis_external_id' => 'KIS-1002', '_kis_external_id_raw' => 'KIS-1002', 'jmeno' => 'Private', 'prijmeni' => 'Child'],
        ];
        $report = \kisFieldContractEvaluate($people, $this->meta(), []);

        self::assertSame('ready_for_parity', $report['status']);
        self::assertSame(0, $report['summary']['total_blockers']);
        self::assertSame($report['fingerprint'], \kisFieldContractEvaluate($people, $this->meta(), [])['fingerprint']);
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('KIS-1001', $json);
        self::assertStringNotContainsString('Secret', $json);
        self::assertStringNotContainsString('Person', $json);
    }

    public function testMissingHeadersInvalidDuplicateAndConflictingIdsBlockParity(): void
    {
        $people = [
            ['kis_external_id' => '', '_kis_external_id_raw' => ''],
            ['kis_external_id' => '', '_kis_external_id_raw' => 'bad id!'],
            ['kis_external_id' => 'DUP-1', '_kis_external_id_raw' => 'DUP-1'],
            ['kis_external_id' => 'DUP-1', '_kis_external_id_raw' => 'DUP-1'],
            ['kis_external_id' => 'CONFLICT-1', '_kis_external_id_raw' => 'CONFLICT-1', '_kis_external_id_conflict' => true],
        ];
        $meta = $this->meta();
        $meta['payments']['headers'] = ['stav'];
        $report = \kisFieldContractEvaluate($people, $meta, ['PAYMENT_EXTERNAL_ID_UNMATCHED:1']);

        self::assertSame('blocked', $report['status']);
        self::assertGreaterThanOrEqual(7, $report['summary']['total_blockers']);
        self::assertSame('blocked', $report['sources']['payments']['status']);
        self::assertSame(
            ['missing_external_id', 'invalid_external_id', 'duplicate_external_id', 'duplicate_external_id', 'external_id_identity_conflict'],
            array_column($report['rows'], 'reason')
        );
    }

    private function meta(): array
    {
        return [
            'users' => ['headers' => ['kisid', 'jmeno', 'prijmeni', 'datumnarozeni'], 'rows' => 2],
            'payments' => ['headers' => ['kisid', 'idplatby', 'stav', 'castka'], 'rows' => 2],
            'soupisky' => ['headers' => ['kisid', 'soupiska', 'jmeno', 'prijmeni'], 'rows' => 2],
        ];
    }
}
