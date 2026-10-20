<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/roster_target_selector.php';

final class RosterTargetSelectorTest extends TestCase
{
    public function testGroupAndSharedChildHaveOneTargetIdAndDistinctInputs(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT,team_kind TEXT)');
        $pdo->exec('CREATE TABLE club_team_edges(parent_team_id INTEGER,child_team_id INTEGER,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_teams VALUES(1,'Závodní tým','active','group'),(2,'Dráha','active','group'),(3,'Kadeti','active','roster'),(4,'Kadeti','active','roster')");
        $pdo->exec("INSERT INTO club_team_edges VALUES(1,3,'2026-01-01',NULL),(2,3,'2026-01-01',NULL)");

        $html = \rosterTargetSelectorHtml($pdo, '2026-10-20', [1, 3]);
        self::assertSame(2, substr_count($html, 'value="3"'));
        self::assertStringContainsString('Kadeti (#3)', $html);
        self::assertStringContainsString('Kadeti (#4)', $html);
        self::assertStringContainsString('name="team_ids[]" value="1"', $html);
        self::assertStringContainsString('id="roster-target-1-1-3"', $html);
        self::assertStringContainsString('id="roster-target-1-2-3"', $html);
        $pdo->exec("UPDATE club_teams SET status='closed' WHERE id=1");
        $historical = \rosterTargetSelectorHtml($pdo, '2026-10-20', [1]);
        self::assertStringContainsString('Historické cíle', $historical);
        self::assertStringContainsString('value="1" id="roster-target-2-historical-1" checked', $historical);
    }
}
