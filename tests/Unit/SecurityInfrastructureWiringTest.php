<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/JsdelivrSriSource.php';

final class SecurityInfrastructureWiringTest extends TestCase
{
    public function testGeneratedReportsAreNotServedOrDeployed(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('migrations|output|tests', (string)file_get_contents($root . '/.htaccess'));
        $workflow = (string)file_get_contents($root . '/.github/workflows/deploy-production.yml');
        self::assertSame(2, substr_count($workflow, "--exclude='output/'"));
    }

    public function testUploadDirectoriesBlockBrowserActiveExtensions(): void
    {
        $rules = (string)file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        foreach (['html?', 'svg', 'js', 'pht', 'phar'] as $suffix) {
            self::assertStringContainsString($suffix, $rules);
        }
    }

    public function testSensitiveUploadsUsePrivateControllerAndLegacyPathsAreDenied(): void
    {
        $root = dirname(__DIR__, 2);
        $htaccess = (string)file_get_contents($root . '/.htaccess');
        $receipt = (string)file_get_contents($root . '/uctenky/uloz.php');
        $stress = (string)file_get_contents($root . '/ulozit_zatezovy_test.php');
        $download = (string)file_get_contents($root . '/private_download.php');

        self::assertStringContainsString('uploads/(?:uctenky|zatezove_testy|servis|temp)', $htaccess);
        self::assertStringContainsString('privateStorageStore', $receipt);
        self::assertStringContainsString('beginTransaction()', $receipt);
        // Receipt files now participate in reversible mutations, then retain
        // retired originals in the private soft-delete archive after commit.
        self::assertStringContainsString('fileMutationRollback($fileMutations)', $receipt);
        self::assertStringContainsString('financialReceiptFilesCommitted($fileMutations)', $receipt);
        self::assertStringContainsString('privateStorageStore', $stress);
        self::assertStringContainsString('public_profile_access_session_athlete_id($pdo)', $download);
        self::assertStringNotContainsString("\$_GET['hash']", $download);
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
        self::assertStringContainsString('http_response_code(410)', $email);
        self::assertStringNotContainsString('public_profile_access_issue(', $email);
        self::assertStringNotContainsString('@mail(', $email);
    }

    public function testSecuritySensitiveUrlWiringDoesNotReadHostHeader(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['booking/zapomenute_heslo.php', 'booking/registrace.php', 'includes/booking_notification.php', 'cron_upominky.php'] as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            self::assertStringContainsString('appUrl(', $source, $path);
            self::assertStringNotContainsString('HTTP_HOST', $source, $path);
            self::assertStringNotContainsString('https://data.kovopraha.cz/evidence', $source, $path);
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

    public function testUciTemplateNeverUsesPublicWebrootStorage(): void
    {
        $root = dirname(__DIR__, 2);
        $route = (string)file_get_contents($root . '/export_uci.php');
        $storage = (string)file_get_contents($root . '/includes/private_storage.php');

        self::assertStringContainsString('privateStorageStoreUciTemp', $route);
        self::assertStringContainsString('privateStorageResolve', $route);
        self::assertStringContainsString("PRIVATE_STORAGE_UCI_TEMP = 'uci-temp'", $storage);
        self::assertStringContainsString("isset(\$_POST['reset_upload'])", $route);
        self::assertStringContainsString('csrf_verify', $route);
        self::assertStringNotContainsString("\$_GET['reset_upload']", $route);
        self::assertStringNotContainsString("\$tempDir     = __DIR__ . '/uploads/temp'", $route);
        self::assertStringNotContainsString("\$_SESSION['uci_upload_path'] =", $route);
    }

    public function testProductionHttpPolicyBlocksInternalTreesAndForcesCanonicalHttps(): void
    {
        $htaccess = (string)file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        self::assertStringContainsString('^kis\\.kovopraha\\.cz', $htaccess);
        self::assertStringContainsString('https://kis.kovopraha.cz%{REQUEST_URI}', $htaccess);
        self::assertStringContainsString('^(?:bin|database|docs|migrations|output|tests|var|vendor)', $htaccess);
        self::assertStringContainsString('^nahrane_zavody/results', $htaccess);
        $security = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/session_security.php');
        self::assertStringContainsString("base-uri 'self'", $security);
        self::assertStringContainsString("object-src 'none'", $security);
        self::assertStringContainsString("form-action 'self'", $security);
        self::assertStringContainsString("'nonce-", $security);
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
            $source = (string) file_get_contents($file->getPathname());
            if (!str_contains($source, 'cdn.jsdelivr.net')) {
                continue;
            }
            foreach (\Tests\Support\jsdelivrSriMissingLines($source) as $number) {
                $missing[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1) . ':' . $number;
            }
        }
        self::assertSame([], $missing, 'CDN assety bez SRI: ' . implode(', ', $missing));
    }

    public function testSriCheckRetainsNegativeDetectionAcrossLinesAndLiteralConcatenation(): void
    {
        $url = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
        foreach (['', ' integrity="sha384-synthetic"', ' crossorigin="anonymous"'] as $attributes) {
            $tag = '<link href="' . $url . '"' . $attributes . '>';
            $literal = '<?php echo ' . var_export(substr($tag, 0, 25), true) . "\n . " . var_export(substr($tag, 25), true) . ';';
            self::assertCount(1, \Tests\Support\jsdelivrSriMissingLines($tag));
            self::assertCount(1, \Tests\Support\jsdelivrSriMissingLines($literal));
        }
        $tag = '<link href="' . $url . '"' . "\n" . ' integrity="sha384-synthetic" crossorigin="anonymous">';
        self::assertSame([], \Tests\Support\jsdelivrSriMissingLines($tag));
        $literal = '<?php echo ' . var_export(substr($tag, 0, 35), true) . "\n . " . var_export(substr($tag, 35), true) . ';';
        self::assertSame([], \Tests\Support\jsdelivrSriMissingLines($literal));
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
