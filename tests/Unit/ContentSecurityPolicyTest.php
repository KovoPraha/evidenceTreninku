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
        self::assertStringContainsString("'strict-dynamic'", $policy);
        self::assertStringNotContainsString("script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'", $policy);
        self::assertStringContainsString("script-src-attr 'none'", $policy);
    }

    public function testPplWidgetHostsAreScopedToCheckoutPolicy():void
    {
        self::assertStringNotContainsString('www.ppl.cz',\app_csp_header_value());
        $checkout=\app_csp_header_value([],true);
        self::assertStringContainsString('https://www.ppl.cz',$checkout);
        self::assertStringContainsString("connect-src 'self' https://api.dhl.com",$checkout);
        self::assertStringContainsString("'strict-dynamic'",$checkout);
    }

    public function testOutputFilterDoesNotAuthorizeInjectedScriptOrHandler(): void
    {
        $html = '<script>window.x=1</script><button onclick="this.form.submit()">OK</button>';
        $filtered = \app_csp_filter_output($html);
        self::assertSame($html, $filtered);
        self::assertStringNotContainsString('<script nonce=', $filtered);
    }

    public function testTrustedLegacyHandlerCanBeHashed(): void
    {
        $hash = base64_encode(hash('sha256', 'this.form.submit()', true));
        $trusted = '<button data-app-csp-handler="' . \app_csp_nonce() . '" onclick="this.form.submit()">OK</button>';
        $injected = '<button onclick="alert(1)">Bad</button>';
        self::assertSame([$hash], \app_csp_approved_handler_hashes($trusted . $injected));
        self::assertSame([], \app_csp_approved_handler_hashes($injected));
        self::assertStringContainsString($hash, \app_csp_header_value([$hash]));
        self::assertStringContainsString("script-src-attr 'unsafe-hashes'", \app_csp_header_value([$hash]));
        self::assertStringNotContainsString("script-src-attr 'unsafe-hashes'", \app_csp_header_value());
    }

    public function testFilterContainsABinaryResponseGuard(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/session_security.php');
        self::assertStringContainsString("str_starts_with(\$contentType, 'text/html')", $source);
        self::assertStringContainsString("str_starts_with(\$contentType, 'application/xhtml+xml')", $source);
    }

    public function testInlineTemplateScriptAndStyleTagsHaveExplicitNonces(): void
    {
        $root = dirname(__DIR__, 2);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $path = str_replace('\\', '/', $file->getPathname());
            if (preg_match('~/(?:vendor|tests|bin|\.codex-tmp|output)/~', $path)) continue;
            foreach (token_get_all((string)file_get_contents($path)) as $token) {
                if (!is_array($token) || $token[0] !== T_INLINE_HTML) continue;
                self::assertDoesNotMatchRegularExpression(
                    '/<(?:script|style)\b(?![^>]*\bnonce=)/i',
                    $token[1],
                    $path . ':' . $token[2]
                );
            }
        }
    }
}
