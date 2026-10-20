<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/club_program_terms_activation.php';

final class ClubProgramTermsActivationTest extends TestCase
{
    public function testWorkingVersionsCoverAllRealOffersAndAreIdempotent(): void
    {
        $pdo = $this->database();
        $preview = \clubProgramTermsActivateWorkingVersions($pdo, 7, false);
        self::assertSame(2, $preview['programs']);
        self::assertSame(2, $preview['offers']);
        self::assertSame(4, $preview['terms_created']);
        self::assertSame(1, $preview['draft_overrides_archived']);
        self::assertFalse($preview['applied']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());

        $result = \clubProgramTermsActivateWorkingVersions($pdo, 7, true);
        self::assertTrue($result['applied']);
        self::assertSame(4, (int)$pdo->query("SELECT COUNT(*) FROM club_event_term_versions WHERE scope_type='club_program' AND status='active'")->fetchColumn());
        self::assertSame('archived', $pdo->query("SELECT status FROM club_event_term_versions WHERE scope_type='club_program_offer'")->fetchColumn());
        self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM club_event_term_versions WHERE scope_key='program:3'")->fetchColumn());
        foreach ([11 => 1, 12 => 2] as $offerId => $programId) {
            self::assertTrue(\clubProgramTermsComplete(\clubProgramTermsEffective($pdo, $programId, $offerId)));
        }
        self::assertSame(0, \clubProgramTermsActivateWorkingVersions($pdo, 7, true)['terms_created']);
        self::assertSame(5, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
    }

    public function testCustomOfferTermsStopActivationWithoutPartialWrites(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE club_event_term_versions SET consent_text_plain='Individuální platné storno pro konkrétní nabídku.'");
        $preview = \clubProgramTermsActivateWorkingVersions($pdo, 7, false);
        self::assertSame(['offer:11:program_cancellation'], $preview['conflicts']);
        try {
            \clubProgramTermsActivateWorkingVersions($pdo, 7, true);
            self::fail('Custom offer terms must be reviewed.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('offer:11:program_cancellation', $e->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        foreach ([
            "CREATE TABLE club_programs(id INTEGER PRIMARY KEY,code TEXT,name TEXT,status TEXT)",
            "INSERT INTO club_programs VALUES(1,'A','Aligátoři','active'),(2,'B','Papuchálci','draft'),(3,'TEST-UAT','TEST - UAT','active')",
            'CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER)',
            'INSERT INTO club_program_offers VALUES(11,1),(12,2),(13,3)',
            "CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER NULL,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT NULL,cancellation_deadline_at TEXT NULL,actor_trainer_id INTEGER NULL,actor_type TEXT,actor_id INTEGER,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,archived_at TEXT NULL,archived_by_trainer_id INTEGER NULL)",
            "INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,status) VALUES('club_program_offer','offer:11','program_cancellation','v1','vzorový text – doplnit','active')",
        ] as $sql) $pdo->exec($sql);
        return $pdo;
    }
}
