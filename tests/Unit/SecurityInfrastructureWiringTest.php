<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SecurityInfrastructureWiringTest extends TestCase
{
    public function testSensitiveUploadsUsePrivateControllerAndLegacyPathsAreDenied(): void
    {
        $root = dirname(__DIR__, 2);
        $htaccess = (string)file_get_contents($root . '/.htaccess');
        $receipt = (string)file_get_contents($root . '/uctenky/uloz.php');
        $stress = (string)file_get_contents($root . '/ulozit_zatezovy_test.php');
        $download = (string)file_get_contents($root . '/private_download.php');

        self::assertStringContainsString('uploads/(?:uctenky|zatezove_testy)', $htaccess);
        self::assertStringContainsString('privateStorageStore', $receipt);
        self::assertStringContainsString('privateStorageStore', $stress);
        self::assertStringContainsString("hash_equals((string)\$file['hash'], \$publicHash)", $download);
        self::assertStringContainsString("(string)\$file['typ'] === 'public_img'", $download);
    }

    public function testMutatingActivityFormHasCsrfProtection(): void
    {
        $route = (string)file_get_contents(dirname(__DIR__, 2) . '/nova_cinnost.php');
        self::assertStringContainsString('csrf_verify', $route);
        self::assertStringContainsString('csrf_field()', $route);
    }

    public function testStoredValuesAreNotInsertedThroughKnownUnsafeSinks(): void
    {
        $root = dirname(__DIR__, 2);
        $training = (string)file_get_contents($root . '/edit_trenink.php');
        $sheets = (string)file_get_contents($root . '/google_sheets_linky.php');
        $email = (string)file_get_contents($root . '/odeslat_emaily.php');

        self::assertStringNotContainsString("chip.innerHTML = it.jmeno", $training);
        self::assertStringNotContainsString('el.innerHTML = `<span>${label}', $sheets);
        self::assertStringContainsString('JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT', $sheets);
        self::assertStringContainsString('safeEmailHtml', $email);
    }

    public function testSecuritySensitiveUrlWiringDoesNotReadHostHeader(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['booking/zapomenute_heslo.php', 'booking/registrace.php', 'booking/rezervovat.php'] as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            self::assertStringContainsString('appUrl(', $source, $path);
            self::assertStringNotContainsString('HTTP_HOST', $source, $path);
        }
    }
}
