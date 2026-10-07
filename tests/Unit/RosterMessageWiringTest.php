<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RosterMessageWiringTest extends TestCase
{
    protected function setUp():void
    {
        require_once dirname(__DIR__,2).'/includes/roster_message_worker_options.php';
    }

    public function testEnvironmentOptionsAreUsedWithoutCliArguments():void
    {
        self::assertSame(
            ['limit'=>50,'transport'=>'mail'],
            \rosterMessageWorkerOptions([], '50', 'mail')
        );
    }

    public function testCliArgumentsRemainCompatibleAndOverrideEnvironment():void
    {
        self::assertSame(
            ['limit'=>7,'transport'=>'local-outbox'],
            \rosterMessageWorkerOptions(['--limit=7','--transport=local-outbox'], '50', 'mail')
        );
    }

    public function testInvalidEnvironmentOptionsFailClosed():void
    {
        $this->expectException(\InvalidArgumentException::class);
        \rosterMessageWorkerOptions([], '100', 'mail');
    }

    public function testWorkerAndProtectedScheduleUseTheQueue():void
    {
        $root=dirname(__DIR__,2);$worker=(string)file_get_contents($root.'/bin/roster-message-worker.php');$options=(string)file_get_contents($root.'/includes/roster_message_worker_options.php');$workflow=(string)file_get_contents($root.'/.github/workflows/roster-messages-production.yml');$admin=(string)file_get_contents($root.'/roster_messages_admin.php');
        self::assertStringContainsString('rosterMessageProcessOne',$worker);self::assertStringContainsString('local-outbox',$worker);self::assertStringContainsString('exit($failed > 0 ? 2 : 0)',$worker);
        self::assertStringContainsString('ROSTER_MESSAGE_LIMIT',$worker);self::assertStringContainsString('ROSTER_MESSAGE_TRANSPORT',$worker);self::assertStringContainsString('--limit=',$options);self::assertStringContainsString('--transport=',$options);
        self::assertStringContainsString("cron: '*/5 * * * *'",$workflow);self::assertStringContainsString('environment: production',$workflow);self::assertStringContainsString('StrictHostKeyChecking=yes',$workflow);self::assertStringContainsString('ROSTER_MESSAGE_LIMIT=50',$workflow);self::assertStringContainsString('ROSTER_MESSAGE_TRANSPORT=mail',$workflow);
        self::assertStringContainsString("php '\$REMOTE_DIR/bin/roster-message-worker.php'\"",$workflow);
        self::assertStringNotContainsString("roster-message-worker.php' --limit",$workflow);
        self::assertStringContainsString('rosterMessagePreview',$admin);self::assertStringContainsString('preview_fingerprint',$admin);
    }
}
