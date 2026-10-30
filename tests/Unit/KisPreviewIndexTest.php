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
        $pdo->exec('CREATE INDEX idx_run_id ON kis_import_matches(run_id)');
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
        self::assertSame(['idx_kis_match_run_row'], array_column($pdo->query('PRAGMA index_list(kis_import_matches)')->fetchAll(PDO::FETCH_ASSOC), 'name'));
    }

    public function testCustomIndexWithLegacyNameIsPreserved(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE kis_import_matches(run_id INTEGER,row_id INTEGER)');
        $pdo->exec('CREATE UNIQUE INDEX idx_run_id ON kis_import_matches(row_id,run_id)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261030170000_kis_preview_match_index.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertCount(2, $pdo->query('PRAGMA index_list(kis_import_matches)')->fetchAll());
        self::assertSame(['row_id', 'run_id'], array_column($pdo->query('PRAGMA index_info(idx_run_id)')->fetchAll(PDO::FETCH_ASSOC), 'name'));
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
