<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

final class KisPreviewIndexTest extends TestCase
{
    public function testIndexIsIdempotentNonUniqueAndPreservesExistingRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE kis_import_matches(run_id INTEGER,row_id INTEGER)');
        $pdo->exec('INSERT INTO kis_import_matches VALUES(1,1),(1,1),(2,1)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261030170000_kis_preview_match_index.php';
        self::assertFalse($migration['verify']($pdo));
        $before = $pdo->query('SELECT * FROM kis_import_matches')->fetchAll();
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame($before, $pdo->query('SELECT * FROM kis_import_matches')->fetchAll());
        $pdo->exec('INSERT INTO kis_import_matches VALUES(1,1)');
        self::assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM kis_import_matches')->fetchColumn());
    }

    public function testExistingWrongIndexFailsWithoutReplacingIt(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE kis_import_matches(run_id INTEGER,row_id INTEGER)');
        $pdo->exec('CREATE INDEX idx_kis_match_run_row ON kis_import_matches(row_id)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261030170000_kis_preview_match_index.php';
        self::assertFalse($migration['verify']($pdo));
        $this->expectException(\RuntimeException::class);
        $migration['up']($pdo);
    }
}
