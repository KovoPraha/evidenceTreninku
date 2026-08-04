<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class KisSyncLibTest extends TestCase
{
    public function testNormalizesHeadersAndNames(): void
    {
        self::assertSame('datumnarozeni', \kis_header_key("  Datum\u{00A0}narození "));
        self::assertSame('jiri novak', \kis_normalize_name('  Jiří ', ' Novák  '));
    }

    public function testConvertsSupportedDates(): void
    {
        self::assertSame('2025-12-31', \kis_date_to_mysql('31.12.2025'));
        self::assertSame('2025-12-31', \kis_date_to_mysql('2025-12-31'));

        $excelDate = ExcelDate::PHPToExcel(new DateTimeImmutable('2024-02-29 12:00:00'));
        self::assertSame('2024-02-29', \kis_date_to_mysql($excelDate));
    }

    public function testRejectsEmptyAndInvalidDates(): void
    {
        self::assertNull(\kis_date_to_mysql(null));
        self::assertNull(\kis_date_to_mysql(''));
        self::assertNull(\kis_date_to_mysql('31.02.2025'));
    }

    public function testConvertsCzechMoneyValues(): void
    {
        self::assertSame(1234.5, \kis_money_to_float('1 234,50 Kč'));
        self::assertSame(1234.5, \kis_money_to_float('1.234,50 Kč'));
        self::assertSame(-250.0, \kis_money_to_float('-250 Kč'));
        self::assertNull(\kis_money_to_float(''));
    }

    public function testParsesAndDeduplicatesRosters(): void
    {
        self::assertSame(
            ['Mladší žáci (2024/2025)', 'Starší žáci (2024/2025)'],
            \kis_parse_soupisky(
                'Mladší žáci (2024/2025), Starší žáci (2024/2025), Mladší žáci (2024/2025)'
            )
        );
        self::assertSame([], \kis_parse_soupisky(''));
    }

    public function testStableKisIdIsIndependentFromUciAndDetectsIdentityConflict(): void
    {
        self::assertSame(
            ['raw' => 'kis-001', 'value' => 'KIS-001', 'header' => 'kisid'],
            \kisFieldExtractExternalId(['kisid' => ' kis-001 ', 'uciid' => 'UCI-999'])
        );

        $people = [];
        [$key] = \kis_upsert_person($people, 'Original', 'Member', '2012-01-01', 'KIS-001', 'kis-001');
        self::assertSame('external:KIS-001', $key);
        self::assertSame('KIS-001', $people[$key]['kis_external_id']);
        self::assertFalse($people[$key]['_kis_external_id_conflict']);

        \kis_upsert_person($people, 'Different', 'Member', '2012-01-01', 'KIS-001', 'KIS-001');
        self::assertTrue($people[$key]['_kis_external_id_conflict']);
    }

    public function testThreeExportsJoinByStableKisIdEvenWhenPaymentHasNoName(): void
    {
        $paths = [
            'users' => $this->writeXlsx([
                ['KIS ID', 'Jmeno', 'Prijmeni', 'Datum narozeni'],
                ['KIS-501', 'Stable', 'Member', '01.02.2012'],
            ]),
            'payments' => $this->writeXlsx([
                ['ID uzivatele', 'ID platby', 'Stav', 'Castka'],
                ['KIS-501', 'PAY-501', 'zaplaceno', '500'],
            ]),
            'rosters' => $this->writeXlsx([
                ['ID clena', 'Soupiska', 'Jmeno', 'Prijmeni'],
                ['KIS-501', 'U15', 'Stable', 'Member'],
            ]),
        ];
        try {
            $payload = \kis_build_import($paths['users'], $paths['payments'], $paths['rosters']);
            self::assertCount(1, $payload['people']);
            $person = $payload['people'][0];
            self::assertSame('KIS-501', $person['kis_external_id']);
            self::assertSame(1, $person['kis_platebne_aktivni']);
            self::assertSame('PAY-501', $person['_kis_payment_rows'][0]['payment_external_id']);
            self::assertSame(50000, $person['_kis_payment_rows'][0]['amount_minor']);
            self::assertSame(0, $person['_kis_payment_rows'][0]['outstanding_minor']);
            self::assertSame('U15', $person['kis_soupisky']);
            self::assertSame([], $payload['warnings']);
            self::assertContains('iduzivatele', $payload['meta']['payments']['headers']);
        } finally {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }

    public function testPaymentWithoutAmountIsBlockedAndNotProjected(): void
    {
        $paths = [
            'users' => $this->writeXlsx([
                ['KIS ID', 'Jmeno', 'Prijmeni', 'Datum narozeni'],
                ['KIS-502', 'Missing', 'Amount', '01.02.2012'],
            ]),
            'payments' => $this->writeXlsx([
                ['ID uzivatele', 'ID platby', 'Stav', 'Castka'],
                ['KIS-502', 'PAY-502', 'ceka', ''],
            ]),
            'rosters' => $this->writeXlsx([
                ['ID clena', 'Soupiska', 'Jmeno', 'Prijmeni'],
                ['KIS-502', 'U15', 'Missing', 'Amount'],
            ]),
        ];
        try {
            $payload = \kis_build_import($paths['users'], $paths['payments'], $paths['rosters']);
            self::assertSame([], $payload['people'][0]['_kis_payment_rows']);
            self::assertContains('PAYMENT_PRESCRIPTION_AMOUNT_MISSING:1', $payload['warnings']);
        } finally {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }

    private function writeXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kis-field-');
        self::assertIsString($path);
        @unlink($path);
        $path .= '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return $path;
    }
}
