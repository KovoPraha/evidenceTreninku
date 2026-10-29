<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';

final class QueryProfilePdoTest extends TestCase
{
    public function testCountsFetchedRowsAndReexecutionsWithoutCountingFixtureWrites(): void
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE sample(id INTEGER)');
        $pdo->exec('INSERT INTO sample VALUES(0),(1),(2)');
        self::assertSame([], $pdo->profile);
        $pdo->profiling = true;
        $q = $pdo->prepare('SELECT id FROM sample WHERE id>=? ORDER BY id');
        $q->execute([0]);
        self::assertSame(0, $q->fetchColumn());
        self::assertSame(['id' => 1], $q->fetch());
        self::assertSame([2], $q->fetchAll(PDO::FETCH_COLUMN));
        self::assertFalse($q->fetch());
        $q->execute([2]);
        self::assertSame([['id' => 2]], $q->fetchAll());
        self::assertSame([3, 1], array_column($pdo->profile, 'fetched_rows'));
        self::assertSame([[0], [2]], array_column($pdo->profile, 'parameters'));
        self::assertSame([0, 1, 2], $pdo->query('SELECT id FROM sample ORDER BY id', PDO::FETCH_COLUMN)->fetchAll());
        self::assertSame(3, $pdo->profile[2]['fetched_rows']);
        foreach ($pdo->profile as $entry) {
            self::assertGreaterThanOrEqual(0, $entry['execute_ms']);
            self::assertGreaterThanOrEqual(0, $entry['fetch_ms']);
        }
    }
}
