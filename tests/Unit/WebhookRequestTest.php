<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebhookRequestTooLargeException;

require_once dirname(__DIR__, 2) . '/includes/webhook_request.php';

final class WebhookRequestTest extends TestCase
{
    public function testBoundedReaderAcceptsSmallPayload(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'kis-webhook-');
        self::assertIsString($file);
        file_put_contents($file, '{"ok":true}');
        try {
            self::assertSame('{"ok":true}', \webhookReadBoundedBody($file, 32, 11));
        } finally {
            @unlink($file);
        }
    }

    public function testBoundedReaderRejectsDeclaredAndActualOversize(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'kis-webhook-');
        self::assertIsString($file);
        file_put_contents($file, str_repeat('x', 33));
        try {
            try {
                \webhookReadBoundedBody($file, 32, 33);
                self::fail('Declared oversize must fail.');
            } catch (WebhookRequestTooLargeException) {
            }
            $this->expectException(WebhookRequestTooLargeException::class);
            \webhookReadBoundedBody($file, 32, null);
        } finally {
            @unlink($file);
        }
    }
}
