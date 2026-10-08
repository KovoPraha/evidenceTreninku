<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/session_security.php';

final class ContentSecurityPolicyTest extends TestCase
{
    public function testPolicyUsesNonceInsteadOfUnsafeInlineScripts(): void
    {
        $policy = \app_csp_header_value();
        self::assertStringContainsString("script-src 'self' https://cdn.jsdelivr.net 'nonce-", $policy);
        self::assertStringNotContainsString("script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'", $policy);
        self::assertStringContainsString("script-src-attr 'none'", $policy);
    }

    public function testOutputFilterAddsNonceAndHashesLegacyHandler(): void
    {
        $html = '<script>window.x=1</script><button onclick="this.form.submit()">OK</button>';
        $filtered = \app_csp_filter_output($html);
        self::assertMatchesRegularExpression('/<script nonce="[^"]+">/', $filtered);
        $hash = base64_encode(hash('sha256', 'this.form.submit()', true));
        self::assertStringContainsString($hash, \app_csp_header_value([$hash]));
        self::assertStringContainsString("script-src-attr 'unsafe-hashes'", \app_csp_header_value([$hash]));
    }

    public function testFilterContainsABinaryResponseGuard(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/session_security.php');
        self::assertStringContainsString("str_starts_with(\$contentType, 'text/html')", $source);
        self::assertStringContainsString("str_starts_with(\$contentType, 'application/xhtml+xml')", $source);
    }
}
