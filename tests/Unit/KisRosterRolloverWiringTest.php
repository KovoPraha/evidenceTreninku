<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisRosterRolloverWiringTest extends TestCase
{
    public function testExecutionRequiresAdminCsrfConfirmationReasonAndFingerprint(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2).'/kis_rosters_admin.php');
        self::assertStringContainsString('http_response_code(410)', $page);
        self::assertStringContainsString("staffRequireActivePosition('registrar')", $page);
        self::assertStringNotContainsString('kisRosterExecuteRollover', $page);
        self::assertStringNotContainsString('kisRosterSetRolloverException', $page);
    }

    public function testMysqlExecutionLocksRunExceptionsAndTargetMembership(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2).'/includes/kis_roster.php');
        self::assertStringContainsString("\$runSql .= ' FOR UPDATE'", $service);
        self::assertStringContainsString('SELECT GET_LOCK(?,10)', $service);
        self::assertStringContainsString('SELECT RELEASE_LOCK(?)', $service);
        self::assertStringContainsString(
            'club_roster_rollover_exceptions WHERE source_team_id=? AND target_season_id=? FOR UPDATE',
            $service
        );
        self::assertStringContainsString("\$targetSql .= ' FOR UPDATE'", $service);
        self::assertStringContainsString("\$teamSql .= ' FOR UPDATE'", $service);
    }
}
