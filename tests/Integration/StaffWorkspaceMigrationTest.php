<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/staff_workspaces.php';

final class StaffWorkspaceMigrationTest extends TestCase
{
    protected function tearDown(): void { $_SESSION = []; }

    public function testMigrationBackfillsLegacyAccountsWithoutMergingActiveWorkspace(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,role TEXT NOT NULL,aktivni INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'trener',1),(2,'hlavni',1),(3,'admin',1),(4,'admin',0)");
        $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=1')->fetchColumn());
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=2')->fetchColumn());
        self::assertSame(8, (int)$pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=3')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=4')->fetchColumn());
        self::assertSame('coach', (string)$pdo->query('SELECT position_code FROM staff_user_positions WHERE trainer_id=1 AND is_default=1')->fetchColumn());
        self::assertSame('sports_lead', (string)$pdo->query('SELECT position_code FROM staff_user_positions WHERE trainer_id=2 AND is_default=1')->fetchColumn());
        self::assertSame('system_admin', (string)$pdo->query('SELECT position_code FROM staff_user_positions WHERE trainer_id=3 AND is_default=1')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=3')->fetchColumn());
        $_SESSION = ['trener_id'=>3,'role'=>'admin'];
        \staffWorkspaceRefreshSession($pdo, 3);
        self::assertTrue(\staffIsSuperadmin());
        self::assertSame('system_admin', \staffActivePosition());
        self::assertCount(8, \staffAvailablePositions());
    }

    public function testEverySwitchIsAuditedAndNonSuperadminCannotEscapeAssignments(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,role TEXT NOT NULL,aktivni INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'hlavni',1)");
        $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
        $migration['up']($pdo);
        $_SESSION = ['trener_id'=>1,'role'=>'hlavni'];
        \staffWorkspaceRefreshSession($pdo, 1);
        self::assertSame('sports_lead', \staffActivePosition());
        \staffSwitchPosition($pdo, 1, 'registrar', 'Kontrola členské agendy');
        self::assertSame('registrar', \staffActivePosition());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM staff_position_switch_events')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT used_superadmin FROM staff_position_switch_events')->fetchColumn());
        $this->expectException(\InvalidArgumentException::class);
        \staffSwitchPosition($pdo, 1, 'finance_manager');
    }

    public function testMissingAssignmentFailsClosedAfterWorkspaceTablesExist(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,role TEXT NOT NULL,aktivni INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'admin',1)");
        $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
        $migration['up']($pdo);
        $pdo->exec('DELETE FROM staff_superadmins WHERE trainer_id=1');
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=1');

        self::assertFalse($migration['verify']($pdo));
        $_SESSION = ['trener_id'=>1,'role'=>'admin'];
        \staffWorkspaceRefreshSession($pdo, 1);
        self::assertSame([], \staffAvailablePositions());
        self::assertSame('', \staffActivePosition());
        self::assertSame('trener', \staffEffectiveLegacyRole('admin'));
    }
}
