<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class YoungestCourseProductionWorkflowTest extends TestCase
{
    public function testHostingInvokesArgumentFreePhpWrappersAfterShaCheckAndBackup(): void
    {
        $workflow=(string)file_get_contents(dirname(__DIR__,2).'/.github/workflows/prepare-youngest-courses-production.yml');
        self::assertStringContainsString('test "$(jq -r \'.sha\' /tmp/kis-youngest-deployment.json)" = "$GITHUB_SHA"',$workflow);
        self::assertStringContainsString('php \'$DEPLOY_BASE/run-youngest-backup.php\'',$workflow);
        self::assertStringContainsString('php \'$DEPLOY_BASE/run-youngest-preview.php\'',$workflow);
        self::assertStringContainsString('php \'$DEPLOY_BASE/run-youngest-apply.php\'',$workflow);
        self::assertStringContainsString("prepareYoungestCoursesMain(['prepare-youngest-courses.php','--dry-run']);",$workflow);
        self::assertStringContainsString("prepareYoungestCoursesMain(['prepare-youngest-courses.php','--apply']);",$workflow);
        self::assertStringNotContainsString('php \'$REMOTE_DIR/bin/prepare-youngest-courses.php\' --',$workflow);
        self::assertStringContainsString('YOUNGEST_COURSES_CONFIRM=PRIPRAVIT-NEJMENSI-2026-27',$workflow);
    }
}
