<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sportovec_history_lib.php';

final class PersonSensitiveExportGuardTest extends TestCase
{
    public function testExportsFeedsStoriesAuditsAndKisReportsCannotImportSensitiveStorage(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            'export_csv.php', 'export_xls.php', 'export_seznam.php', 'export_uci.php',
            'export_draha.php', 'club_event_participants_export.php', 'generuj_story.php',
            'booking/verejny_kalendar.php', 'booking/rodinny_kalendar.php',
            'includes/public_calendar_feed.php', 'includes/family_calendar_feed.php',
            'auditlog/seznam.php', 'person_audit_admin.php',
            'includes/kis_import_parity_report.php', 'includes/kis_parity_contract.php',
            'includes/kis_import_run_lib.php',
        ];
        foreach ($paths as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            foreach (['person_sensitive.php', 'osoba_citlive_udaje', 'rc_ciphertext', 'sportovci.rc'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $path . ' imports sensitive data.');
            }
        }
    }

    public function testKisSyncNeverPersistsOrSelectsLegacyBirthNumber(): void
    {
        $root = dirname(__DIR__, 2);
        $sync = (string)file_get_contents($root . '/sync_evidence.php');
        $library = (string)file_get_contents($root . '/includes/kis_sync_lib.php');

        self::assertStringNotContainsString("'rc' =>", $sync);
        self::assertStringNotContainsString("'rc' =>", $library);
        self::assertStringNotContainsString('SELECT * FROM sportovci', $sync);
        self::assertStringNotContainsString("'email','rc'", $sync);
        self::assertStringNotContainsString("'email','telefon','rc'", $library);
        self::assertStringContainsString('syncEvidenceSafePersonColumns()', $sync);
    }

    public function testHistoryRedactsLegacyAndEncryptedSensitiveKeysRecursively(): void
    {
        $payload = \sportovecHistorySanitize([
            'jmeno' => 'LOCALHOST',
            'rc' => 'SENSITIVE_SENTINEL',
            'nested' => [
                'rodnecislo' => 'SENSITIVE_SENTINEL',
                'rc_ciphertext' => 'SENSITIVE_SENTINEL',
                'rc_blind_index' => 'SENSITIVE_SENTINEL',
            ],
        ]);
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('SENSITIVE_SENTINEL', $serialized);
        self::assertSame('LOCALHOST', $payload['jmeno']);
        self::assertSame('[REDACTED]', $payload['nested']['rc_ciphertext']);
    }

    public function testSensitiveHttpSurfacesUseHardAdminChecksAndNoStore(): void
    {
        $root = dirname(__DIR__, 2);
        $reveal = (string)file_get_contents($root . '/athlete_sensitive_admin.php');
        $download = (string)file_get_contents($root . '/private_download.php');

        self::assertStringContainsString("(string)(\$_SESSION['role'] ?? '') !== 'admin'", $reveal);
        self::assertStringContainsString('csrf_verify', $reveal);
        self::assertStringContainsString('Cache-Control: no-store', $reveal);
        self::assertStringNotContainsString('canAccess(', $reveal);
        self::assertStringContainsString("\$kind === 'athlete-photo'", $download);
        self::assertStringContainsString('personSensitiveAdminAuditPhotoView', $download);
        self::assertStringNotContainsString("canAccess('athlete", $download);
    }

    public function testLegacyPreflightIsExplicitAndSelectOnly(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__, 2) . '/bin/athlete-registration-preflight.php'
        );
        self::assertStringContainsString("KIS_ATHLETE_PREFLIGHT_CONFIRM') !== 'OVERIT'", $source);
        self::assertStringContainsString("NULLIF(TRIM(rc), '') IS NOT NULL", $source);
        self::assertStringContainsString("'query_mode' => 'select_only'", $source);
        self::assertStringContainsString("'birth_number_values_exposed' => false", $source);
        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'REPLACE ', 'ALTER ', 'DROP '] as $mutation) {
            self::assertStringNotContainsString($mutation, $source);
        }
    }
}
