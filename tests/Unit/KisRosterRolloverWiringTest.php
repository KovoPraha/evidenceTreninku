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
}
