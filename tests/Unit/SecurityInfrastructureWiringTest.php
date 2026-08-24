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

        self::assertStringContainsString('uploads/(?:uctenky|zatezove_testy|servis)', $htaccess);
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

    public function testTrainingEntrySettingsUseCanonicalCsrfAndDoNotExposeDatabaseErrors(): void
    {
        $route = (string)file_get_contents(dirname(__DIR__, 2) . '/nastaveni_zadavani.php');

        self::assertStringContainsString("csrf_verify((string)(\$_POST['csrf_token'] ?? ''))", $route);
        self::assertStringContainsString('csrf_field()', $route);
        self::assertStringNotContainsString("'Chyba při ukládání: ' . \$e->getMessage()", $route);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS nastaveni', $route);
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

    public function testFormerPublicInternalReportsRequireAStaffSession(): void
    {
        $root = dirname(__DIR__, 2);
        $legacy = (string)file_get_contents($root . '/pub.php');
        self::assertStringContainsString("Location: booking/treninky.php", $legacy);
        self::assertStringNotContainsString('SELECT ', $legacy);

        foreach (['prehled_skupin.php', 'prehled_skupina.php', 'prehled_skupiny.php', 'prehled_podskupin.php'] as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            self::assertStringContainsString('app_session_start()', $source, $path);
            self::assertStringContainsString("isset(\$_SESSION['trener_id'])", $source, $path);
        }
        $subgroups = (string)file_get_contents($root . '/prehled_podskupin.php');
        self::assertStringNotContainsString("\$_GET['id']", $subgroups);
        self::assertStringContainsString("\$_GET['hash']", $subgroups);
    }

    public function testServiceDocumentsUsePrivateStorageAndAuthorizedDownload(): void
    {
        $root = dirname(__DIR__, 2);
        $storage = (string)file_get_contents($root . '/includes/private_storage.php');
        $save = (string)file_get_contents($root . '/servis/uloz.php');
        $list = (string)file_get_contents($root . '/servis/seznam.php');
        $download = (string)file_get_contents($root . '/private_download.php');

        self::assertStringContainsString("PRIVATE_STORAGE_SERVICE_DOCUMENTS = 'service-documents'", $storage);
        self::assertStringContainsString('privateStorageStore(', $save);
        self::assertStringContainsString('10 * 1024 * 1024', $save);
        self::assertStringContainsString('private_download.php?kind=service&amp;id=', $list);
        self::assertStringContainsString("\$kind === 'service'", $download);
        self::assertStringContainsString("staffActivePositionIs('finance_manager')", $download);
        self::assertStringContainsString("canAccess('servis')", $download);
    }

    public function testProductionHttpPolicyBlocksInternalTreesAndForcesCanonicalHttps(): void
    {
        $htaccess = (string)file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        self::assertStringContainsString('^kis\\.kovopraha\\.cz', $htaccess);
        self::assertStringContainsString('https://kis.kovopraha.cz%{REQUEST_URI}', $htaccess);
        self::assertStringContainsString('^(?:bin|docs|migrations|tests|var|vendor)', $htaccess);
        self::assertStringContainsString('^nahrane_zavody/results', $htaccess);
        self::assertStringContainsString("base-uri 'self'", $htaccess);
        self::assertStringContainsString("object-src 'none'", $htaccess);
        self::assertStringContainsString("form-action 'self'", $htaccess);
    }

    public function testEveryJsdelivrAssetIsPinnedWithSri(): void
    {
        $root = dirname(__DIR__, 2);
        $missing = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'html'], true)) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/vendor/')
                || str_contains($path, '/tests/')
                || str_contains($path, '/var/')
                || str_contains($path, '/.git/')
            ) {
                continue;
            }
            foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
                if (str_contains($line, 'cdn.jsdelivr.net')
                    && (!str_contains($line, 'integrity="sha384-') || !str_contains($line, 'crossorigin="anonymous"'))
                ) {
                    $missing[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1) . ':' . ($number + 1);
                }
            }
        }
        self::assertSame([], $missing, 'CDN assety bez SRI: ' . implode(', ', $missing));
    }

    public function testPushSubscriptionsRejectArbitraryOutboundEndpoints(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/push_subscription_security.php';

        self::assertSame(
            'https://fcm.googleapis.com/fcm/send/example',
            \pushSubscriptionValidateEndpoint('https://fcm.googleapis.com/fcm/send/example')
        );
        foreach (['http://fcm.googleapis.com/x', 'https://127.0.0.1/x', 'https://example.com/x', 'https://user@fcm.googleapis.com/x'] as $endpoint) {
            try {
                \pushSubscriptionValidateEndpoint($endpoint);
                self::fail('Endpoint měl být odmítnut: ' . $endpoint);
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testReminderCronIsCliOnly(): void
    {
        $cron = (string)file_get_contents(dirname(__DIR__, 2) . '/cron_upominky.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $cron);
        self::assertStringNotContainsString('UPOMINKA_SECRET', $cron);
        self::assertStringNotContainsString("\$_GET", $cron);
    }
}
