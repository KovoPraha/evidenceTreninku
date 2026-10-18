<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SecurityAuditRegressionTest extends TestCase
{
    public function testProductionHttpRedirectDoesNotTrustForwardedProtoFromTheRequest(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        self::assertStringContainsString('RewriteCond %{HTTPS} !=on', $rules);
        self::assertStringContainsString('RewriteRule ^ https://kis.kovopraha.cz%{REQUEST_URI} [R=308,L,NE]', $rules);
        self::assertStringNotContainsString('HTTP:X-Forwarded-Proto', $rules);
    }

    public function testInternalBootstrapFilesAndIncludesAreNotWebRoutes(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        self::assertStringContainsString('^(?:config|db|csrf_helper)\\.php$', $rules);
        self::assertStringContainsString('docs|includes|migrations', $rules);
    }

    public function testExerciseMutationsRequireAValidCsrfToken(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/cviky.php');

        self::assertStringContainsString("if (!csrf_verify(\$_POST['csrf_token'] ?? '')) {", $source);
        self::assertStringContainsString('http_response_code(403);', $source);
        self::assertStringContainsString("if (\$action === 'delete' && empty(\$errors))", $source);
        self::assertStringContainsString("if (\$action === 'toggle' && empty(\$errors))", $source);
        self::assertStringNotContainsString("\$errors[] = 'Chyba při ukládání: ' . \$e->getMessage()", $source);
        self::assertStringNotContainsString("\$errors[] = 'Chyba při mazání: ' . \$e->getMessage()", $source);
    }

    public function testAthleteSearchRendersNamesAsTextRatherThanHtml(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/sportovec_detail.php');

        self::assertStringContainsString("surname.textContent = String(r.prijmeni || '')", $source);
        self::assertStringContainsString("category.textContent = String(r.category || r.kategorie || '')", $source);
        self::assertStringContainsString('resultsWrap.replaceChildren(list)', $source);
        self::assertStringNotContainsString('onclick="location.href=', $source);
    }
}
