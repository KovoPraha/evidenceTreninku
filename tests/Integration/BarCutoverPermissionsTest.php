<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/cutover_permissions.php';

final class BarCutoverPermissionsTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testRequestedNameMustBeUniqueActiveAndAlreadyAuthorizedForSystemWork(): void
    {
        $pdo = \barTestDatabase();
        $pdo->exec("UPDATE treneri SET jmeno='LOCALHOST Cutover Owner' WHERE id=1");
        self::assertSame(['id' => 1, 'session_version' => 1], \barCutoverResolveAdministrator($pdo, ' localhost cutover owner '));
        $pdo->exec("UPDATE treneri SET jmeno='LOCALHOST Cutover Owner' WHERE id=4");
        $this->expectException(\DomainException::class);
        \barCutoverResolveAdministrator($pdo, 'LOCALHOST Cutover Owner');
    }

    public function testBarOperatorCannotBeUsedAsTheSystemActorEvenWhenTheNameMatches(): void
    {
        $pdo = \barTestDatabase();
        $this->expectException(\DomainException::class);
        \barCutoverResolveAdministrator($pdo, 'LOCALHOST Operator');
    }

    public function testGrantPreservesAllExistingPositionsAndDefaultAndRepeatsWithoutMoreAudit(): void
    {
        $pdo = \barTestDatabase();
        $pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=3");
        $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(1,'coach',0)");
        \barTestLogin(1, 'system_admin');
        $before = $pdo->query('SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=1 ORDER BY position_code')->fetchAll(\PDO::FETCH_ASSOC);
        $first = \barCutoverGrantAdministrator($pdo, 1);
        self::assertTrue($first['admin_added']);
        self::assertTrue($first['consumption_added']);
        $preserved = $pdo->query("SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=1 AND position_code<>'bar_admin' ORDER BY position_code")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame($before, $preserved);
        self::assertSame(0, (int)$pdo->query("SELECT is_default FROM staff_user_positions WHERE trainer_id=1 AND position_code='bar_admin'")->fetchColumn());
        $events = (int)$pdo->query('SELECT COUNT(*) FROM staff_position_assignment_events')->fetchColumn();
        $barEvents = (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn();
        $repeat = \barCutoverGrantAdministrator($pdo, 1);
        self::assertFalse($repeat['admin_added']);
        self::assertFalse($repeat['consumption_added']);
        self::assertSame($events, (int)$pdo->query('SELECT COUNT(*) FROM staff_position_assignment_events')->fetchColumn());
        self::assertSame($barEvents, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        self::assertSame([1], array_map('intval', $pdo->query('SELECT trainer_id FROM kis_bar_permissions')->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function testOtherExistingGrantStopsOpeningWithoutRemovingIt(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(1, 'system_admin');
        try {
            \barCutoverGrantAdministrator($pdo, 1);
            self::fail('Other administrator was ignored.');
        } catch (\DomainException) {
            self::assertSame([3], array_map('intval', $pdo->query("SELECT trainer_id FROM staff_user_positions WHERE position_code='bar_admin'")->fetchAll(\PDO::FETCH_COLUMN)));
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_permissions')->fetchColumn());
            self::assertFalse($pdo->inTransaction());
        }
    }

    public function testRevokedActorCannotGrantAnything(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(1, 'system_admin');
        $pdo->exec('UPDATE treneri SET session_version=2 WHERE id=1');
        $this->expectException(\DomainException::class);
        \barCutoverGrantAdministrator($pdo, 3);
    }
}
