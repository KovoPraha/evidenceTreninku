<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class KisRosterRolloverWiringTest extends TestCase
{
    public function testExecutionRequiresAdminCsrfConfirmationReasonAndFingerprint():void
    {
        $root=dirname(__DIR__,2);$page=(string)file_get_contents($root.'/kis_rosters_admin.php');$service=(string)file_get_contents($root.'/includes/kis_roster.php');foreach(["roleAtLeast('admin')",'csrf_verify','confirm_rollover','preview_fingerprint','reason','kisRosterExecuteRollover','kisRosterSetRolloverException']as$needle)self::assertStringContainsString($needle,$page);foreach(['hash_equals','club_roster_rollover_runs','rollover_close_member','target_override']as$needle)self::assertStringContainsString($needle,$service);
    }

    public function testMysqlExecutionLocksRunExceptionsAndTargetMembership():void
    {
        $service=(string)file_get_contents(dirname(__DIR__,2).'/includes/kis_roster.php');
        self::assertStringContainsString("\$runSql.=' FOR UPDATE'",$service);
        self::assertStringContainsString('SELECT GET_LOCK(?,10)',$service);
        self::assertStringContainsString('SELECT RELEASE_LOCK(?)',$service);
        self::assertStringContainsString('club_roster_rollover_exceptions WHERE source_team_id=? AND target_season_id=? FOR UPDATE',$service);
        self::assertStringContainsString("\$targetSql.=' FOR UPDATE'",$service);
        self::assertStringContainsString("\$teamSql.=' FOR UPDATE'",$service);
    }
}
