<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sports_measurement_contract.php';

final class SportsMeasurementContractTest extends TestCase
{
    public function testDistanceRequiresExplicitUnitAndNormalizesToMeters(): void
    {
        self::assertSame(1500.0, \sportsMeasurementDistanceMeters('1,5', 'km'));
        self::assertSame(250.25, \sportsMeasurementDistanceMeters('250.25', 'm'));
        self::assertNull(\sportsMeasurementDistanceMeters('', null));
    }

    public function testNonEmptyDistanceWithoutSupportedUnitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsMeasurementDistanceMeters('10', null);
    }

    #[DataProvider('durationProvider')]
    public function testDurationHasStrictUnambiguousFormat(string $input, int $milliseconds): void
    {
        self::assertSame($milliseconds, \sportsMeasurementDurationMilliseconds($input));
    }

    /** @return iterable<string,array{string,int}> */
    public static function durationProvider(): iterable
    {
        yield 'minutes and seconds' => ['12:34', 754000];
        yield 'more than one hour in two-part form' => ['90:00', 5400000];
        yield 'hours with milliseconds' => ['1:02:03.045', 3723045];
        yield 'tenths are padded' => ['00:01.5', 1500];
    }

    public function testFreeformDurationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsMeasurementDurationMilliseconds('30 min');
    }

    public function testRpeUsesOneToTenScale(): void
    {
        self::assertSame(7.5, \sportsMeasurementRpeValue('7,5'));
        self::assertSame(10.0, \sportsMeasurementRpeValue('10'));
        self::assertNull(\sportsMeasurementRpeValue(null));
    }

    public function testRpeOutsideScaleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsMeasurementRpeValue('11');
    }

    public function testRaceStatusUsesClosedVocabulary(): void
    {
        self::assertSame('finished', \sportsRaceResultStatus('FINISHED'));
        self::assertSame(['entered', 'finished', 'dns', 'dnf', 'dsq'], \sportsRaceResultStatuses());
        self::assertNull(\sportsRaceResultStatus(''));
    }

    public function testUnknownRaceStatusIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \sportsRaceResultStatus('cancelled');
    }
}
