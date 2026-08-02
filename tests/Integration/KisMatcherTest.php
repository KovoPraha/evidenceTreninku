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
