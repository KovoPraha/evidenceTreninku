<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

if (!defined('EVIDENCE_BACKUP_LIBRARY_ONLY')) define('EVIDENCE_BACKUP_LIBRARY_ONLY', true);
require_once dirname(__DIR__, 2) . '/bin/db-backup.php';

final class DatabaseBackupMariaDbSmokeContractTest extends TestCase
{
    private function source(): string
    {
        return (string)file_get_contents(dirname(__DIR__) . '/Support/DatabaseBackupMariaDbSmoke.php');
    }

    public function testSmokePinsCurrentVersionAndCompleteColumnContract(): void
    {
        $source = $this->source();
        self::assertStringContainsString("!== '" . EVIDENCE_OWNERSHIP_CONTRACT_VERSION . "'", $source);
        self::assertSame(1, preg_match('/\$expectedColumnContract = (\[.*?\n    \]);/s', $source, $match));
        // Evaluate only the repository-owned literal array, never the smoke or its DB bootstrap.
        $expected = eval('return ' . $match[1] . ';');
        self::assertSame(EVIDENCE_OWNED_COLUMN_CONTRACT, $expected);
    }

    public function testSmokeRequiresEveryClubReleaseTableAndExactMigratedTableSet(): void
    {
        $source = $this->source();
        self::assertSame(1, preg_match('/\$required = (\[.*?\n    \]);/s', $source, $match));
        $required = eval('return ' . $match[1] . ';');
        $first = array_search('person_identity_reviews', EVIDENCE_TABLES, true);
        $last = array_search('club_payment_returns', EVIDENCE_TABLES, true);
        self::assertIsInt($first);
        self::assertIsInt($last);
        $releaseTables = array_slice(EVIDENCE_TABLES, $first, $last - $first + 1);
        self::assertCount(83, $releaseTables);
        self::assertSame([], array_values(array_diff($releaseTables, $required)));
        self::assertCount(count(array_unique($required)), $required);
        self::assertStringContainsString("TABLE_TYPE='BASE TABLE'", $source);
        self::assertStringContainsString('if ($backedUpTables !== $expectedTables)', $source);
        self::assertStringContainsString('if ($restoredRows !== (int)$expectedRows)', $source);
        self::assertStringContainsString('if ($restoredTriggers !== $expectedTriggers)', $source);
    }
}
