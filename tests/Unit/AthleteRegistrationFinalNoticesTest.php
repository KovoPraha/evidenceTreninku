<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/athlete_registration.php';

final class AthleteRegistrationFinalNoticesTest extends TestCase
{
    public function testVersionedNoticesOpenMandatoryRegistrationAndKeepFederationConsentSeparate(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,actor_trainer_id INTEGER,actor_type TEXT,actor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $insert = $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain) VALUES('athlete_registration','athlete-registration-v1',?,?,?)");
        foreach ([
            ['member_data_notice','member-data-notice-2026-10-20-vzor-2','VZOROVÝ TEXT – old'],
            ['birth_number_legal_notice','birth-number-legal-notice-2026-10-20-vzor-2','VZOROVÝ TEXT – old'],
            ['photo_internal','photo-internal-v1','Internal photo.'],
            ['photo_public','photo-public-v1','Public photo.'],
        ] as $row) $insert->execute($row);

        $migration = require dirname(__DIR__, 2) . '/migrations/20261020160000_athlete_registration_final_notices.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $terms = \athleteRegistrationCurrentTerms($pdo);
        self::assertTrue(\athleteRegistrationTermsReady($terms));
        self::assertStringContainsString('§ 3e odst. 2 písm. c)', $terms['birth_number_legal_notice']['text']);
        self::assertStringContainsString('§ 13c odst. 1 písm. d)', $terms['birth_number_legal_notice']['text']);
        self::assertArrayHasKey('csc_birth_number_sharing', $terms);
        self::assertSame(7, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());

        $migration['up']($pdo);
        self::assertSame(7, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
    }
}
