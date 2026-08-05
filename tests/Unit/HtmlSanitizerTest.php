<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/html_sanitizer.php';

final class HtmlSanitizerTest extends TestCase
{
    public function testKeepsBasicFormattingAndDropsActiveContent(): void
    {
        $clean = \safeEmailHtml(
            '<p onclick="alert(1)">Ahoj <strong>Marek</strong></p>'
            . '<img src=x onerror=alert(2)><script>alert(3)</script>'
        );

        self::assertSame('<p>Ahoj <strong>Marek</strong></p>alert(3)', $clean);
        self::assertStringNotContainsString('onclick', $clean);
        self::assertStringNotContainsString('<script', $clean);
    }

    public function testRejectsDangerousLinkScheme(): void
    {
        self::assertSame('<a>odkaz</a>', \safeEmailHtml('<a href="javascript:alert(1)">odkaz</a>'));
        self::assertSame(
            '<a href="https://data.kovopraha.cz/evidence" rel="noopener noreferrer">profil</a>',
            \safeEmailHtml('<a href="https://data.kovopraha.cz/evidence" style="color:red">profil</a>')
        );
    }
}
