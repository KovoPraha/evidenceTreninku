<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

final class ClubEventAuditMigrationTest extends TestCase
{
    public function testMigrationVerifiesOnSqliteAndAuditContractAllowsLongKnownAction():void
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_event_admin_events(id INTEGER PRIMARY KEY,action TEXT NOT NULL)');
        $migration=require dirname(__DIR__,2).'/migrations/20261007130000_club_event_admin_action_width.php';
        $migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        self::assertLessThanOrEqual(64,strlen('calendar_confirm_and_open_registration'));
        $source=(string)file_get_contents(dirname(__DIR__,2).'/includes/club_event.php');
        self::assertStringContainsString("{1,64}",$source);
    }
}
