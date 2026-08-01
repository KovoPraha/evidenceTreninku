<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
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
}
