<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\KisMatcherDatabase;

require_once dirname(__DIR__, 2) . '/includes/kis_match_lib.php';

final class KisMatcherTest extends TestCase
{
    public function testMatchesAThenBWithoutShrinkingSnapshot(): void
    {
        $pdo = $this->databaseWithAAndB();

        $this->assertUciMatch(101, \kisMatchResolve($pdo, ['uciid' => 'A-101']));
        $this->assertUciMatch(202, \kisMatchResolve($pdo, ['uciid' => 'B-202']));
    }

    public function testMatchesBThenAWithoutShrinkingSnapshot(): void
    {
        $pdo = $this->databaseWithAAndB();

        $this->assertUciMatch(202, \kisMatchResolve($pdo, ['uciid' => 'B-202']));
        $this->assertUciMatch(101, \kisMatchResolve($pdo, ['uciid' => 'A-101']));
    }

    public function testUnknownLookupDoesNotRemoveKnownAthlete(): void
    {
        $pdo = $this->databaseWithAAndB();

        $unknown = \kisMatchResolve($pdo, ['uciid' => 'UNKNOWN']);
        self::assertSame('new', $unknown['status']);
        self::assertNull($unknown['sportovec_id']);
        self::assertSame(0, $unknown['confidence']);
        self::assertSame('Nenalezena shoda', $unknown['reason']);

        $this->assertUciMatch(101, \kisMatchResolve($pdo, ['uciid' => 'A-101']));
    }

    public function testSnapshotsAreIsolatedBetweenPdoConnections(): void
    {
        $firstPdo = KisMatcherDatabase::create([
            ['id' => 301, 'uciid' => 'FIRST-301'],
        ]);
        $secondPdo = KisMatcherDatabase::create([
            ['id' => 402, 'uciid' => 'SECOND-402'],
        ]);

        $this->assertUciMatch(301, \kisMatchResolve($firstPdo, ['uciid' => 'FIRST-301']));
        $this->assertUciMatch(402, \kisMatchResolve($secondPdo, ['uciid' => 'SECOND-402']));
    }

    public function testSnapshotDoesNotIncludeRowsInsertedAfterFirstLookup(): void
    {
        $pdo = KisMatcherDatabase::create([
            ['id' => 501, 'uciid' => 'EXISTING-501'],
        ]);
        $this->assertUciMatch(501, \kisMatchResolve($pdo, ['uciid' => 'EXISTING-501']));

        KisMatcherDatabase::insert($pdo, ['id' => 502, 'uciid' => 'LATE-502']);

        $late = \kisMatchResolve($pdo, ['uciid' => 'LATE-502']);
        self::assertSame('new', $late['status']);
        self::assertNull($late['sportovec_id']);
        self::assertSame('Nenalezena shoda', $late['reason']);
    }

    public function testExactStructuredNameAndBirthDateIsStrongMatch(): void
    {
        $pdo = KisMatcherDatabase::create([
            ['id' => 601, 'jmeno' => 'Alpha', 'prijmeni' => 'Rider', 'narozeni' => '2010-01-02'],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Alpha',
            'prijmeni' => 'Rider',
            'narozeni' => '2010-01-02',
        ]);

        self::assertSame('matched', $match['status']);
        self::assertSame(601, $match['sportovec_id']);
        self::assertSame(95, $match['confidence']);
        self::assertSame('jmeno+prijmeni, datum narozeni', $match['reason']);
        self::assertSame(
            ['id', 'jmeno', 'prijmeni', 'narozeni', 'uciid', '_match_score', '_match_reason'],
            array_keys($match['candidates'][0])
        );
    }

    public function testDifferentNonEmptyBirthDateRequiresManualResolution(): void
    {
        $pdo = KisMatcherDatabase::create([
            ['id' => 602, 'jmeno' => 'Beta', 'prijmeni' => 'Rider', 'narozeni' => '2010-01-02'],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Beta',
            'prijmeni' => 'Rider',
            'narozeni' => '2011-03-04',
        ]);

        self::assertSame('conflict', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame(60, $match['confidence']);
        self::assertStringContainsString('Datum narozeni se lisi', $match['reason']);
    }

    public function testNameOnlyAndEmailOnlyAreNotAutomaticMatches(): void
    {
        $namePdo = KisMatcherDatabase::create([
            ['id' => 603, 'jmeno' => 'Gamma', 'prijmeni' => 'Rider'],
        ]);
        $nameMatch = \kisMatchResolve($namePdo, ['jmeno' => 'Gamma', 'prijmeni' => 'Rider']);
        self::assertSame('conflict', $nameMatch['status']);
        self::assertSame(60, $nameMatch['confidence']);

        $emailPdo = KisMatcherDatabase::create([
            ['id' => 604, 'email' => 'synthetic-604@example.invalid'],
        ]);
        $emailMatch = \kisMatchResolve($emailPdo, ['email' => 'synthetic-604@example.invalid']);
        self::assertSame('conflict', $emailMatch['status']);
        self::assertSame(85, $emailMatch['confidence']);
    }

    public function testSharedEmailAndNameDoNotAutoMatchWithoutConfirmedBirthDate(): void
    {
        $pdo = KisMatcherDatabase::create([
            [
                'id' => 607,
                'jmeno' => 'Family',
                'prijmeni' => 'Member',
                'narozeni' => null,
                'email' => 'shared-family@example.invalid',
            ],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Family',
            'prijmeni' => 'Member',
            'narozeni' => '2013-07-08',
            'email' => 'shared-family@example.invalid',
        ]);

        self::assertSame('conflict', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame(100, $match['confidence']);
        self::assertSame('email, jmeno+prijmeni', $match['candidates'][0]['_match_reason']);
    }

    public function testEmailMaySupportButNotReplaceExactNameAndBirthEvidence(): void
    {
        $pdo = KisMatcherDatabase::create([
            [
                'id' => 608,
                'jmeno' => 'Confirmed',
                'prijmeni' => 'Member',
                'narozeni' => '2014-09-10',
                'email' => 'shared-family@example.invalid',
            ],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Confirmed',
            'prijmeni' => 'Member',
            'narozeni' => '2014-09-10',
            'email' => 'shared-family@example.invalid',
        ]);

        self::assertSame('matched', $match['status']);
        self::assertSame(608, $match['sportovec_id']);
        self::assertSame(100, $match['confidence']);
    }

    public function testExactUciIdRemainsIndependentStrongEvidence(): void
    {
        $pdo = KisMatcherDatabase::create([
            [
                'id' => 609,
                'jmeno' => 'Uci',
                'prijmeni' => 'Member',
                'narozeni' => null,
                'email' => 'shared-family@example.invalid',
                'uciid' => 'UCI-STRONG-609',
            ],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Uci',
            'prijmeni' => 'Member',
            'narozeni' => '2015-11-12',
            'email' => 'shared-family@example.invalid',
            'uciid' => 'UCI-STRONG-609',
        ]);

        self::assertSame('matched', $match['status']);
        self::assertSame(609, $match['sportovec_id']);
        self::assertSame(100, $match['confidence']);
    }

    public function testExactNameAndBirthCannotOverrideDifferentNonEmptyUciIds(): void
    {
        $pdo = KisMatcherDatabase::create([
            [
                'id' => 610,
                'jmeno' => 'Strong',
                'prijmeni' => 'Conflict',
                'narozeni' => '2016-01-02',
                'uciid' => 'UCI-DB-610',
            ],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Strong',
            'prijmeni' => 'Conflict',
            'narozeni' => '2016-01-02',
            'uciid' => 'UCI-IMPORT-610',
        ]);

        self::assertSame('conflict', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame(95, $match['confidence']);
        self::assertStringContainsString('UCI ID se lisi', $match['reason']);
    }

    public function testExactUciIdCannotOverrideDifferentNonEmptyBirthDates(): void
    {
        $pdo = KisMatcherDatabase::create([
            [
                'id' => 611,
                'jmeno' => 'Database',
                'prijmeni' => 'Identity',
                'narozeni' => '2016-03-04',
                'uciid' => 'UCI-SAME-611',
            ],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Imported',
            'prijmeni' => 'Identity',
            'narozeni' => '2017-05-06',
            'uciid' => 'UCI-SAME-611',
        ]);

        self::assertSame('conflict', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame(95, $match['confidence']);
        self::assertStringContainsString('Datum narozeni se lisi', $match['reason']);
    }

    public function testStrongMatchRequiresMarginOverSecondCandidate(): void
    {
        $pdo = KisMatcherDatabase::create([
            ['id' => 605, 'jmeno' => 'Delta', 'prijmeni' => 'Rider', 'narozeni' => '2012-05-06'],
            ['id' => 606, 'jmeno' => 'Delta', 'prijmeni' => 'Rider', 'narozeni' => '2012-05-06'],
        ]);

        $match = \kisMatchResolve($pdo, [
            'jmeno' => 'Delta',
            'prijmeni' => 'Rider',
            'narozeni' => '2012-05-06',
        ]);

        self::assertSame('ambiguous', $match['status']);
        self::assertNull($match['sportovec_id']);
        self::assertSame(95, $match['confidence']);
    }

    private function databaseWithAAndB(): \PDO
    {
        return KisMatcherDatabase::create([
            ['id' => 101, 'uciid' => 'A-101'],
            ['id' => 202, 'uciid' => 'B-202'],
        ]);
    }

    /**
     * @param array<string, mixed> $match
     */
    private function assertUciMatch(int $expectedId, array $match): void
    {
        self::assertSame('matched', $match['status']);
        self::assertSame($expectedId, $match['sportovec_id']);
        self::assertSame(95, $match['confidence']);
        self::assertSame('UCI ID', $match['reason']);
        self::assertSame('UCI ID', $match['candidates'][0]['_match_reason']);
    }
}
