<?php
declare(strict_types=1);
namespace Tests\Integration;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/SportovecEditorDetailsFixture.php';

final class SportovecEditorDetailsTest extends TestCase
{
    protected function tearDown(): void { $_SESSION = []; }

    public function testMigrationPreservesRegistrationCiphertextAuditAndForeignKeys(): void
    {
        $pdo = $this->pdo(); $fixture = sportovecEditorDetailsFixture($pdo, false);
        $ciphertext = $pdo->query('SELECT rc_ciphertext FROM osoba_citlive_udaje')->fetchColumn();
        $migration = require dirname(__DIR__, 2) . '/migrations/20261020220000_athlete_editor_relations.php';
        self::assertFalse($migration['verify']($pdo));
        $migration['up']($pdo); $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame($ciphertext, $pdo->query('SELECT rc_ciphertext FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame('850101/0001', personSensitiveAdminReveal($pdo, $fixture['old_record_id'], 'LOCALHOST kontrola migrace', $fixture['keys']));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_pristupy')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn());
        self::assertFalse($pdo->query('PRAGMA foreign_key_check')->fetchColumn());
    }

    public function testNewAndCorrectedBirthNumberUseCanonicalEncryptedPersonContext(): void
    {
        $pdo = $this->pdo(); $fixture = sportovecEditorDetailsFixture($pdo); $digits = $this->digits('2010-01-02');
        sportovecEditorSaveBirthNumber($pdo, 10, $digits, '', 'LOCALHOST první doplnění', 7, $fixture['keys']);
        $row = $pdo->query('SELECT * FROM osoba_citlive_udaje WHERE sportovec_id=10')->fetch(\PDO::FETCH_ASSOC);
        self::assertNull($row['request_id']);
        self::assertSame('person-sensitive-person-v1', $row['contract_version']);
        self::assertSame($digits, personSensitiveDecryptForAdmin($row, $fixture['keys']));
        self::assertStringNotContainsString($digits, $row['rc_ciphertext']);
        self::assertNull(sportovecEditorPerson($pdo, 10)['rc']);
        self::assertSame('2010-01-02', sportovecEditorPerson($pdo, 10)['narozeni']);
        $next = $this->digits('2011-03-04');
        sportovecEditorSaveBirthNumber($pdo, 10, $next, '2011-03-04', 'LOCALHOST oprava údajů', 7, $fixture['keys']);
        $reveal = sportovecEditorReveal($pdo, 10, 'LOCALHOST ověření hodnoty', $fixture['keys']);
        self::assertSame(substr($next, 0, 6) . '/' . substr($next, 6), $reveal['current_birth_number']);
        self::assertSame('8601010000', $reveal['source_rows'][0]['rodnecislo']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_udaje WHERE sportovec_id=10')->fetchColumn());
        self::assertStringNotContainsString($next, json_encode($pdo->query('SELECT * FROM sportovec_history')->fetchAll()));
        self::assertStringNotContainsString($next, json_encode($pdo->query('SELECT * FROM osoba_citlive_pristupy')->fetchAll()));
        $row['sportovec_id'] = 11;
        try { personSensitiveDecryptForAdmin($row, $fixture['keys']); self::fail('Ciphertext moved to another person'); }
        catch (\PersonSensitiveException) { self::assertTrue(true); }
    }

    public function testDuplicateBirthNumberRollsBackDateAndAuditAndRejectsNonAdmin(): void
    {
        $pdo = $this->pdo(); $fixture = sportovecEditorDetailsFixture($pdo); $digits = $this->digits('2010-01-02');
        sportovecEditorSaveBirthNumber($pdo, 10, $digits, '', 'LOCALHOST první doplnění', 7, $fixture['keys']);
        $auditCount = (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_pristupy')->fetchColumn();
        try { sportovecEditorSaveBirthNumber($pdo, 11, $digits, '', 'LOCALHOST duplicitní údaj', 7, $fixture['keys']); self::fail('Duplicate RČ accepted'); }
        catch (\PersonSensitiveException) {
            self::assertNull(sportovecEditorPerson($pdo, 11)['narozeni']);
            self::assertSame($auditCount, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_pristupy')->fetchColumn());
        }
        $_SESSION['role'] = 'trener';
        $this->expectException(\PersonSensitiveException::class);
        sportovecEditorSaveBirthNumber($pdo, 11, $digits, '', 'LOCALHOST nepovolená změna', 7, $fixture['keys']);
    }

    public function testCanonicalReplaceAndKeyRotationAlsoWorkWithoutRegistrationRequest(): void
    {
        $pdo = $this->pdo(); $fixture = sportovecEditorDetailsFixture($pdo); $digits = $this->digits('2010-01-02');
        sportovecEditorSaveBirthNumber($pdo, 10, $digits, '', 'LOCALHOST doplnění údaje', 7, $fixture['keys']);
        $record = $pdo->query('SELECT * FROM osoba_citlive_udaje WHERE sportovec_id=10')->fetch(\PDO::FETCH_ASSOC);
        personSensitiveAdminReplace($pdo, (int)$record['id'], $digits, '2010-01-02', 'LOCALHOST kanonická oprava', $fixture['keys']);
        $rotated = $fixture['keys']; $rotated['keys']['next'] = str_repeat('n', 32); $rotated['active_version'] = 'next';
        personSensitiveAdminRotate($pdo, (int)$record['id'], 'LOCALHOST rotace klíče', $rotated);
        self::assertSame(substr($digits, 0, 6) . '/' . substr($digits, 6), personSensitiveAdminReveal($pdo, (int)$record['id'], 'LOCALHOST kontrola rotace', $rotated));
    }

    public function testEditingSharedParentPreservesLoginAndGroupsAliases(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo);
        sportovecEditorSaveParent($pdo, 10, 'link10', ['parent_name' => 'LOCALHOST Upravený rodič', 'parent_emails' => 'new-contact@example.test', 'parent_phones' => '456; 789', 'relationship' => 'zákonný zástupce'], 7);
        $context = sportovecEditorContext($pdo, 10);
        self::assertCount(1, $context['parents']);
        self::assertSame('LOCALHOST Upravený rodič', $context['parents'][0]['display_name']);
        self::assertSame(2, (int)$context['parents'][0]['shared_people']);
        self::assertSame('login@example.test', $context['parents'][0]['login_email']);
        self::assertSame(['456','789'], json_decode($context['parents'][0]['phones_json'], true));
        self::assertSame('zákonný zástupce', $pdo->query("SELECT relationship_label FROM kis_person_links WHERE source_key='link10b'")->fetchColumn());
        self::assertSame('matka', $pdo->query("SELECT relationship_label FROM kis_person_links WHERE source_key='link11'")->fetchColumn());
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM sportovec_history WHERE event_type='parent_edit'")->fetchColumn());
    }

    public function testUnlinkEndsAllPersonAliasesAndRevokesOnlyTheirGuardianAccess(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo);
        sportovecEditorUnlinkParent($pdo, 10, 'link10', 7);
        self::assertCount(0, sportovecEditorContext($pdo, 10)['parents']);
        self::assertCount(1, sportovecEditorContext($pdo, 11)['parents']);
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM kis_person_links WHERE sportovec_id=10 AND ended_at IS NOT NULL")->fetchColumn());
        self::assertSame('revoked', $pdo->query('SELECT status FROM account_person_roles WHERE sportovec_id=10')->fetchColumn());
        self::assertSame('approved', $pdo->query('SELECT status FROM account_person_roles WHERE sportovec_id=11')->fetchColumn());
        self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM verejni_uzivatele WHERE aktivni=0 AND id=1")->fetchColumn());
    }

    public function testUnlinkRollsBackContactsAndAccessIfHistoryFails(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo);
        $pdo->exec("CREATE TRIGGER fail_history BEFORE INSERT ON sportovec_history BEGIN SELECT RAISE(ABORT,'test audit failure'); END");
        try { sportovecEditorUnlinkParent($pdo, 10, 'link10', 7); self::fail('Unaudited unlink succeeded'); }
        catch (\PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_person_links WHERE ended_at IS NOT NULL')->fetchColumn());
            self::assertSame('approved', $pdo->query('SELECT status FROM account_person_roles WHERE sportovec_id=10')->fetchColumn());
            self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM account_person_role_events WHERE action='revoke'")->fetchColumn());
        }
    }

    public function testManualParentAndAccountCanBeAddedWithoutInventingImportOrEnablingAccount(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo);
        sportovecEditorSaveParent($pdo, 10, '', ['parent_name' => 'LOCALHOST Nový', 'parent_emails' => 'manual@example.test', 'parent_phones' => '123', 'relationship' => 'otec'], 7);
        $row = $pdo->query("SELECT * FROM kis_person_contacts WHERE display_name='LOCALHOST Nový'")->fetch(\PDO::FETCH_ASSOC);
        self::assertNull($row['batch_fingerprint']);
        sportovecEditorGuardianAccount($pdo, 10, 2, 7, 'LOCALHOST explicitní propojení', $row['contact_key']);
        self::assertSame('approved', $pdo->query('SELECT status FROM account_person_roles WHERE account_id=2 AND sportovec_id=10')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT aktivni FROM verejni_uzivatele WHERE id=2')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT email_overeno FROM verejni_uzivatele WHERE id=2')->fetchColumn());
        self::assertSame(2, (int)$pdo->query("SELECT account_id FROM kis_person_contacts WHERE display_name='LOCALHOST Nový'")->fetchColumn());
    }

    public function testForeignParentAndAccountIdentifiersAreRejected(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo);
        $foreignRelation = (int)$pdo->query('SELECT id FROM account_person_roles WHERE sportovec_id=11')->fetchColumn();
        foreach ([
            static fn() => sportovecEditorUnlinkParent($pdo, 10, 'link11', 7),
            static fn() => sportovecEditorSaveParent($pdo, 10, 'link11', ['parent_name' => 'Wrong'], 7),
            static fn() => sportovecEditorRevokeAccount($pdo, 10, $foreignRelation, 7, 'LOCALHOST cizí vazba'),
        ] as $operation) {
            try { $operation(); self::fail('Foreign identifier accepted'); }
            catch (\InvalidArgumentException) { self::assertFalse($pdo->inTransaction()); }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_person_links WHERE ended_at IS NOT NULL')->fetchColumn());
    }

    public function testRosterAddIsIdempotentAndHistoryIncludesOldAndFutureMemberships(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo);
        sportovecEditorAddRoster($pdo, 10, 1, 7, '2026-10-20', 'LOCALHOST přidání');
        sportovecEditorAddRoster($pdo, 10, 1, 7, '2026-10-20', 'LOCALHOST opakování');
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES(2,10,'removed','kis_shadow','2025-09-01','2026-08-31',7),(3,10,'active','manual','2098-09-01',NULL,7),(1,11,'active','manual','2026-10-20',NULL,7)");
        $rosters = sportovecEditorRosters($pdo, 10, '2026-10-20');
        self::assertCount(1, $rosters['current']); self::assertCount(1, $rosters['history']); self::assertCount(1, $rosters['planned']);
        self::assertCount(1, $rosters['events']);
        self::assertSame([3,1], array_map('intval', array_column($rosters['choices'], 'id')));
        $this->expectException(\InvalidArgumentException::class);
        sportovecEditorAddRoster($pdo, 10, 2, 7, '2025-10-20', 'LOCALHOST stará sezona');
    }

    public function testAllAssignedPaymentsIncludeHistoryAndSharedOrdersButExcludeSiblings(): void
    {
        $pdo = $this->pdo(); sportovecEditorDetailsFixture($pdo); sportovecEditorPaymentFixture($pdo);
        $result = sportovecEditorPayments($pdo, 10);
        self::assertSame([2,1], array_map('intval', array_column($result['charges'], 'id')));
        self::assertSame('111', $result['charges'][1]['variable_symbol']);
        self::assertSame([3,1], array_map('intval', array_column($result['orders'], 'id')));
        self::assertSame(3000, (int)$result['orders'][0]['athlete_items_minor']);
        self::assertSame(8000, (int)$result['orders'][0]['total_minor']);
        self::assertSame('222', $result['orders'][1]['variable_symbol']);
        self::assertCount(1, $result['receipts']); self::assertCount(1, $result['allocations']);
        self::assertSame(1, (int)$result['allocations'][0]['charge_id']);
        self::assertStringNotContainsString('SIBLING', json_encode($result));
    }

    private function pdo(): \PDO { return new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]); }
    private function digits(string $date): string
    {
        $prefix = (new \DateTimeImmutable($date))->format('ymd');
        for ($suffix = 0; $suffix <= 9999; $suffix++) {
            $value = $prefix . str_pad((string)$suffix, 4, '0', STR_PAD_LEFT);
            if ((int)$value % 11 === 0) return $value;
        }
        throw new \LogicException('No synthetic number');
    }
}
