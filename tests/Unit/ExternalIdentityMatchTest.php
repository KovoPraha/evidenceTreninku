<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/external_sync_contract.php';

final class ExternalIdentityMatchTest extends TestCase
{
    public function testUciFixturesAndLeadingZeros(): void
    {
        $fixtures = json_decode(file_get_contents(dirname(__DIR__, 2) . '/docs/verification/csc-sportsoft-s0-20261107/fixtures.json'), true);
        foreach ($fixtures['uci_cases'] as $case) {
            $result = \externalIdentityUci($case['raw']);
            self::assertSame($case['expected'] ?? null, $result['uci']);
            self::assertSame($case['expected_state'] ?? 'valid', $result['state']);
        }
        self::assertSame('invalid_uci', \externalIdentityUci("00000000001\n")['state']);
    }

    public function testDatesAndNameOrderWithoutFuzzyFallback(): void
    {
        self::assertSame('2000-02-29', \externalIdentityDate('29. 2. 2000'));
        self::assertNull(\externalIdentityDate('29.02.2001'));
        self::assertNull(\externalIdentityDate('01/02/2000'));
        self::assertSame(\externalIdentityName('TEST NOVÁK Adam'), \externalIdentityName('Adam TEST Novak'));
        $person = ['id' => 1, 'jmeno' => 'Adam TEST', 'prijmeni' => 'Novák', 'narozeni' => '2000-01-02', 'uciid' => '00000000001'];
        $row = ['uci_id_raw' => '00000000001', 'display_name' => 'TEST NOVAK Adam', 'birth_date_raw' => '2.1.2000'];
        self::assertSame('matched', \externalIdentityResolve($row, [$person])['status']);
        $row['birth_date_raw'] = '3.1.2000';
        self::assertSame('identity_conflict', \externalIdentityResolve($row, [$person])['status']);
        $row['birth_date_raw'] = null;
        $row['display_name'] = 'TEST Adam Nová';
        self::assertSame('identity_conflict', \externalIdentityResolve($row, [$person])['status']);
        $row['uci_id_raw'] = null;
        self::assertSame('missing_uci', \externalIdentityResolve($row, [$person])['status']);
    }

    public function testAllInvalidContractFixturesAreRejected(): void
    {
        $fixtures = json_decode(file_get_contents(dirname(__DIR__, 2) . '/docs/verification/csc-sportsoft-s0-20261107/fixtures.json'), true);
        foreach ($fixtures['valid_batches'] as $batch) {
            self::assertSame($batch['collection'], \externalSyncBatch($batch)['collection']);
        }
        foreach ($fixtures['invalid_batches'] as $case) {
            try {
                \externalSyncBatch($case['batch']);
                self::fail($case['case']);
            } catch (\ExternalSyncException $error) {
                self::assertSame(422, $error->getCode());
            }
        }
    }

    public function testUtcTimestampAndUnobservedStatusesStayUnknown(): void
    {
        $fixtures = json_decode(file_get_contents(dirname(__DIR__, 2) . '/docs/verification/csc-sportsoft-s0-20261107/fixtures.json'), true);
        $batch = $fixtures['valid_batches'][0];
        $batch['observed_at'] = '2026-11-07T13:00:00.123456789Z';
        self::assertSame($batch['observed_at'], \externalSyncBatch($batch)['observed_at']);
        $batch['observed_at'] = '2026-02-30T13:00:00Z';
        try {
            \externalSyncBatch($batch);
            self::fail('Impossible calendar date accepted');
        } catch (\ExternalSyncException $error) {
            self::assertSame('invalid_observed_at', $error->getMessage());
        }
        require_once dirname(__DIR__, 2) . '/includes/external_sync_ingest.php';
        self::assertSame('unknown', \externalSyncNormalized('sportsoft_registrations', ['paid_raw' => 'ne'])['paid']);
        self::assertSame('unknown', \externalSyncNormalized('csc_licenses', ['license_status_raw' => null, 'validity_year_raw' => '2026'])['license_status']);
    }
}
