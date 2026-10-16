<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

final class AthleteRegistrationSampleNoticesTest extends TestCase
{
    public function testSamplesReplaceOnlyUntouchedProvisionalNotices(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,actor_trainer_id INTEGER,actor_type TEXT,actor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $insert = $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain) VALUES('athlete_registration','athlete-registration-v1',?,?,?)");
        $insert->execute(['member_data_notice','member-data-notice-2026-08-16-v1','old']);
        $insert->execute(['birth_number_legal_notice','birth-number-legal-notice-2026-08-16-v1','old']);
        $insert->execute(['birth_number_legal_notice','club-approved-v2','approved']);

        $migration = require dirname(__DIR__,2) . '/migrations/20261016120000_athlete_registration_sample_notices.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $current = $pdo->query("SELECT consent_purpose,terms_version,consent_text_plain FROM club_event_term_versions WHERE scope_type='athlete_registration' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(4,$current);
        self::assertSame('member-data-notice-2026-10-16-vzor',$current[3]['terms_version']);
        self::assertStringStartsWith('VZOROVÝ TEXT',$current[3]['consent_text_plain']);
        self::assertSame('club-approved-v2',$current[2]['terms_version']);

        $migration['up']($pdo);
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
    }
}
