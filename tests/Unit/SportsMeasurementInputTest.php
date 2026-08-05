<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sports_measurement_input.php';

final class SportsMeasurementInputTest extends TestCase
{
    public function testJsonRowsAreNormalizedForSharedFormAndImportUse(): void
    {
        $rows = \sportsMeasurementRowsFromPost(['mereni_json' => json_encode([
            ['typ' => 'kolo', 'sportovec_id' => '12', 'vzdalenost' => '1,5', 'distance_unit' => 'km', 'cas' => '02:13.45'],
            ['typ' => 'posilovna', 'sportovec_id' => '12', 'cvik_id' => '3', 'rpe' => '7,5'],
        ], JSON_THROW_ON_ERROR)]);

        self::assertCount(2, $rows);
        self::assertSame('sports-measurement-v1', $rows[0]['contract_version']);
        self::assertSame('km', $rows[0]['distance_unit']);
        self::assertSame(1500.0, $rows[0]['distance_meters']);
        self::assertSame(133450, $rows[0]['duration_ms']);
        self::assertSame(7.5, $rows[1]['rpe_value']);
        self::assertSame(16, count(\sportsMeasurementInsertParameters($rows[0])));
    }

    public function testDistanceWithoutUnitFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('řádku 1');
        \sportsMeasurementRows([['typ' => 'beh', 'sportovec_id' => 1, 'vzdalenost' => '10']]);
    }

    public function testMalformedJsonRowFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Řádek musí být datový objekt');
        \sportsMeasurementRowsFromPost(['mereni_json' => '["poškozený řádek"]']);
    }

    public function testFreeformTimeFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsMeasurementRows([['typ' => 'kolo_mtb', 'sportovec_id' => 1, 'segment_id' => 2, 'cas' => 'půl hodiny']]);
    }

    public function testRaceImportMappingRequiresExplicitStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsRaceResultInput(['cas' => '01:00']);
    }

    public function testFinishedRaceImportIsNormalizedWithoutWriting(): void
    {
        self::assertSame([
            'poradi' => 2,
            'cas' => '1:02:03.4',
            'body' => 12.5,
            'result_contract_version' => 'sports-measurement-v1',
            'result_status' => 'finished',
            'result_time_ms' => 3723400,
        ], \sportsRaceResultInput([
            'status' => 'finished',
            'position' => '2',
            'time' => '1:02:03.4',
            'points' => '12,5',
        ]));
    }

    public function testNonFinishedRaceCannotPretendToHaveAResult(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsRaceResultInput(['status' => 'dnf', 'position' => '4']);
    }

    public function testAllMeasurementHandlersUseOnlyTheSharedParserAndInsertContract(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['ulozit_trenink.php', 'update_trenink.php', 'ulozit_zavod.php', 'update_zavod.php'] as $file) {
            $source = (string)file_get_contents($root . '/' . $file);
            self::assertStringContainsString("includes/sports_measurement_input.php", $source, $file);
            self::assertStringContainsString('sportsMeasurementRowsFromPost($_POST)', $source, $file);
            self::assertStringContainsString('sportsMeasurementInsertSql()', $source, $file);
            self::assertStringContainsString('sportsMeasurementInsertParameters($row)', $source, $file);
            self::assertStringNotContainsString('function buildMereniRowsFromPost', $source, $file);
        }
    }

    public function testAllMeasurementFormsSendExplicitDistanceUnit(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['formular.php', 'edit_trenink.php', 'formular_zavod.php', 'edit_zavod_form.php'] as $file) {
            $source = (string)file_get_contents($root . '/' . $file);
            self::assertStringContainsString('js-distance-unit', $source, $file);
            self::assertStringContainsString('obj.distance_unit', $source, $file);
            self::assertStringContainsString('MM:SS(.mmm)', $source, $file);
        }
    }
}
