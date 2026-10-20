<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/athlete_registration.php';

final class AthleteRegistrationConfirmedFactsDraftTest extends TestCase
{
    public function testOwnerFactsCreateNewVersionsWithoutOpeningRegistrationOrOverwritingHumanText(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,actor_trainer_id INTEGER,actor_type TEXT,actor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $insert = $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain) VALUES('athlete_registration','athlete-registration-v1',?,?,?)");
        foreach ([
            ['member_data_notice','member-data-notice-2026-10-16-vzor','VZOROVÝ TEXT – old'],
            ['birth_number_legal_notice','birth-number-legal-notice-2026-10-16-vzor','VZOROVÝ TEXT – old'],
            ['photo_internal','photo-internal-v1','Internal photo.'],
            ['photo_public','photo-public-v1','Public photo.'],
        ] as $row) $insert->execute($row);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261020130000_athlete_registration_confirmed_facts_draft.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $terms = \athleteRegistrationCurrentTerms($pdo);
        self::assertStringContainsString('00541753', $terms['member_data_notice']['text']);
        self::assertStringContainsString('po dobu členství', $terms['birth_number_legal_notice']['text']);
        self::assertFalse(\athleteRegistrationTermsReady($terms));
        self::assertSame(6, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());

        $migration['up']($pdo);
        self::assertSame(6, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
        $insert->execute(['member_data_notice','club-approved-v3','Owner-authored final notice.']);
        $migration['up']($pdo);
        self::assertSame('club-approved-v3', \athleteRegistrationCurrentTerms($pdo)['member_data_notice']['version']);
    }
}
