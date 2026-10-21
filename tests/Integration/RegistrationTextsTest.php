<?php
declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/athlete_registration_texts.php';
require_once dirname(__DIR__, 2).'/includes/athlete_registration.php';

final class RegistrationTextsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $_SESSION = ['trener_id'=>7, 'staff_active_position'=>'registrar', 'staff_positions'=>['registrar']];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT); INSERT INTO treneri VALUES(7,'LOCALHOST Registrář'),(8,'LOCALHOST Druhý registrář')");
        $this->pdo->exec("CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,actor_trainer_id INTEGER,actor_type TEXT,actor_id INTEGER,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))");
        $insert = $this->pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,actor_type,actor_id) VALUES('athlete_registration','athlete-registration-v1',?,'seed-v1',?,'system',0)");
        foreach (array_keys(ATHLETE_REGISTRATION_TEXT_PURPOSES) as $purpose) $insert->execute([$purpose, 'LOCALHOST Původní informace '.$purpose]);
        $migration = require dirname(__DIR__,2).'/migrations/20261021150000_athlete_registration_text_drafts.php';
        ($migration['up'])($this->pdo);
        ($migration['up'])($this->pdo);
        self::assertTrue(($migration['verify'])($this->pdo));
    }

    protected function tearDown(): void { $_SESSION = []; }

    private function draft(string $purpose = 'birth_number_legal_notice', string $text = 'LOCALHOST Nová konečná informace k evidenci.'): int
    {
        return \registrationTextsSaveDraft($this->pdo, $purpose, $text, 'LOCALHOST Schválená změna', (int)\registrationTextsCurrent($this->pdo, $purpose)['id']);
    }

    public function testDraftCannotChangePublicRegistrationAndPublicationPreservesHistoryAndSnapshots(): void
    {
        $old = \athleteRegistrationCurrentTerms($this->pdo);
        $this->pdo->exec('CREATE TABLE acceptance(term_id INTEGER,text_snapshot TEXT)');
        $this->pdo->prepare('INSERT INTO acceptance VALUES(?,?)')->execute([$old['birth_number_legal_notice']['id'], $old['birth_number_legal_notice']['text']]);
        $draft = $this->draft();
        self::assertSame($old, \athleteRegistrationCurrentTerms($this->pdo));
        $_SESSION['trener_id'] = 8;
        $id = \registrationTextsPublish($this->pdo, $draft, true);
        $new = \athleteRegistrationCurrentTerms($this->pdo);
        self::assertSame($id, $new['birth_number_legal_notice']['id']);
        self::assertSame('LOCALHOST Nová konečná informace k evidenci.', $new['birth_number_legal_notice']['text']);
        self::assertSame($old['member_data_notice'], $new['member_data_notice']);
        $history = \registrationTextsHistory($this->pdo, 'birth_number_legal_notice');
        self::assertCount(2, $history);
        self::assertSame(8, (int)$history[0]['published_by']);
        self::assertSame(7, (int)$history[0]['draft_created_by']);
        self::assertSame($old['birth_number_legal_notice']['text'], $history[1]['consent_text_plain']);
        self::assertSame($old['birth_number_legal_notice']['text'], $this->pdo->query('SELECT text_snapshot FROM acceptance')->fetchColumn());
        self::assertSame($id, \registrationTextsPublish($this->pdo, $draft, true));
        self::assertCount(2, \registrationTextsHistory($this->pdo, 'birth_number_legal_notice'));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testCompetingDraftCannotOverwriteANewerPublishedVersion(): void
    {
        $first = $this->draft();
        $second = $this->draft('birth_number_legal_notice', 'LOCALHOST Druhý koncept.');
        $id = \registrationTextsPublish($this->pdo, $first, true);
        try {
            \registrationTextsPublish($this->pdo, $second, true);
            self::fail('Stale publication must fail.');
        } catch (InvalidArgumentException $e) { self::assertStringContainsString('staršího znění', $e->getMessage()); }
        self::assertSame($id, (int)\registrationTextsCurrent($this->pdo, 'birth_number_legal_notice')['id']);
        self::assertNull($this->pdo->query('SELECT published_term_id FROM athlete_registration_text_drafts WHERE id='.$second)->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testExplicitConfirmationIsRequiredAndSampleTextIsNotPublishable(): void
    {
        $draft = $this->draft('member_data_notice', 'VZOROVÝ TEXT – doplnit klubem');
        foreach ([false, true] as $confirm) {
            try { \registrationTextsPublish($this->pdo, $draft, $confirm); self::fail('Must reject an unconfirmed or sample publication.'); }
            catch (InvalidArgumentException $e) { self::assertNotSame('', $e->getMessage()); }
        }
        self::assertSame('seed-v1', \registrationTextsCurrent($this->pdo, 'member_data_notice')['terms_version']);
        self::assertSame(5, (int)$this->pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
    }

    public function testEveryRoleIncludingSuperadminMustUseRegistrarPosition(): void
    {
        $draft = $this->draft();
        foreach (array_diff(\staffPositionCodes(), ['registrar']) as $position) {
            $_SESSION['staff_active_position'] = $position;
            $_SESSION['staff_is_superadmin'] = true;
            foreach (['save', 'publish', 'history'] as $action) {
                try {
                    if ($action === 'save') $this->draft();
                    elseif ($action === 'publish') \registrationTextsPublish($this->pdo, $draft, true);
                    else \registrationTextsHistory($this->pdo, 'birth_number_legal_notice');
                    self::fail('Wrong active position must not access text administration.');
                } catch (\RuntimeException $e) { self::assertStringContainsString('Registrář', $e->getMessage()); }
            }
        }
        $_SESSION = ['staff_active_position'=>'registrar'];
        $this->expectException(\RuntimeException::class);
        \registrationTextsPublish($this->pdo, $draft, true);
    }

    public function testDraftValidationRejectsUnknownPurposeEmptyTextMissingReasonAndStaleBase(): void
    {
        $base = (int)\registrationTextsCurrent($this->pdo, 'member_data_notice')['id'];
        foreach ([['unknown','x','why',$base], ['member_data_notice','','why',$base], ['member_data_notice','x','',$base], ['member_data_notice','x','why',999], ['member_data_notice',str_repeat('x',12001),'why',$base]] as $input) {
            try { \registrationTextsSaveDraft($this->pdo, ...$input); self::fail('Invalid draft must fail.'); }
            catch (InvalidArgumentException $e) { self::assertNotSame('', $e->getMessage()); }
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM athlete_registration_text_drafts')->fetchColumn());
    }

    public function testFailedPublicationRollsBackBothRegistryAndDraft(): void
    {
        $draft = $this->draft();
        $this->pdo->exec("CREATE TRIGGER reject_publication BEFORE UPDATE ON athlete_registration_text_drafts BEGIN SELECT RAISE(ABORT,'LOCALHOST injected failure'); END");
        try { \registrationTextsPublish($this->pdo, $draft, true); self::fail('Injected failure must propagate.'); }
        catch (\PDOException $e) { self::assertStringContainsString('injected failure', $e->getMessage()); }
        self::assertSame(5, (int)$this->pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
        self::assertNull($this->pdo->query('SELECT published_term_id FROM athlete_registration_text_drafts')->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testEachPurposePublishesOnlyItsOwnVersionAndRetainsReadablePlainText(): void
    {
        foreach (array_keys(ATHLETE_REGISTRATION_TEXT_PURPOSES) as $purpose) {
            $text = 'LOCALHOST <script>plain text</script> '.$purpose;
            $draft = $this->draft($purpose, $text);
            $id = \registrationTextsPublish($this->pdo, $draft, true);
            self::assertSame($text, \athleteRegistrationCurrentTerms($this->pdo)[$purpose]['text']);
            self::assertSame($id, (int)\registrationTextsCurrent($this->pdo, $purpose)['id']);
            self::assertCount(2, \registrationTextsHistory($this->pdo, $purpose));
        }
    }
}
