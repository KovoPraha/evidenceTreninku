<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RosterMessageWiringTest extends TestCase
{
    public function testWorkerAndProtectedScheduleUseTheQueue():void
    {
        $root=dirname(__DIR__,2);$worker=(string)file_get_contents($root.'/bin/roster-message-worker.php');$workflow=(string)file_get_contents($root.'/.github/workflows/roster-messages-production.yml');$admin=(string)file_get_contents($root.'/roster_messages_admin.php');
        self::assertStringContainsString('rosterMessageProcessOne',$worker);self::assertStringContainsString('local-outbox',$worker);self::assertStringContainsString('exit($failed>0?2:0)',$worker);
        self::assertStringContainsString("cron: '*/5 * * * *'",$workflow);self::assertStringContainsString('environment: production',$workflow);self::assertStringContainsString('StrictHostKeyChecking=yes',$workflow);self::assertStringContainsString('roster-message-worker.php',$workflow);
        self::assertStringContainsString('rosterMessagePreview',$admin);self::assertStringContainsString('preview_fingerprint',$admin);
    }
}
