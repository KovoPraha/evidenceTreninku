<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/account_person_role.php';
require_once dirname(__DIR__, 2) . '/includes/club_event_registration.php';

final class ClubEventRegistrationTest extends TestCase
{
    public function testApprovedChildRegistrationIsIdempotentCapacitySafeAndReversible(): void
    {
        $pdo = $this->database();
        $eventId = $this->openFreeEvent($pdo, 1);

        $first = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        $duplicate = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        self::assertTrue($first['created']);
        self::assertFalse($duplicate['created']);
        self::assertSame($first['id'], $duplicate['id']);
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM club_event_registrations WHERE status='confirmed'"
        )->fetchColumn());
        $snapshot=$pdo->query('SELECT consent_version_snapshot,consent_text_snapshot,cancellation_policy_snapshot,cancellation_deadline_snapshot,consented_at FROM club_event_registrations WHERE id='.$first['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026.1',$snapshot['consent_version_snapshot']);
        self::assertSame('Souhlasím s účastí dítěte.',$snapshot['consent_text_snapshot']);
        self::assertSame('Bezplatné storno je možné do uvedeného termínu.',$snapshot['cancellation_policy_snapshot']);
        self::assertNotEmpty($snapshot['cancellation_deadline_snapshot']);
        self::assertNotEmpty($snapshot['consented_at']);

        $waiting=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        $waitingDuplicate=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        self::assertSame('waitlisted',$waiting['status']);
        self::assertTrue($waiting['created']);
        self::assertFalse($waitingDuplicate['created']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_registrations WHERE status='waitlisted'")->fetchColumn());
        $mine=\clubEventMyRegistrations($pdo,10);
        $waitingRows=array_values(array_filter($mine,static fn(array $row):bool=>$row['status']==='waitlisted'));
        self::assertSame(1,$waitingRows[0]['waitlist_position']);

        $cancelled = \clubEventCancelRegistration($pdo, $first['id'], 10, 'Dítě se nemůže účastnit.');
        self::assertTrue($cancelled['changed']);
        self::assertSame($waiting['id'],$cancelled['promoted_registration_id']);
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM club_event_registrations WHERE status='confirmed'"
        )->fetchColumn());
        self::assertSame('confirmed',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$waiting['id'])->fetchColumn());
        self::assertNotEmpty($pdo->query('SELECT promoted_at FROM club_event_registrations WHERE id='.$waiting['id'])->fetchColumn());
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registration_events')->fetchColumn());

        $this->expectException(\PDOException::class);
        $pdo->exec(
            "INSERT INTO club_event_registrations "
            . "(event_id,account_id,sportovec_id,relation_role_snapshot,status,registered_at) "
            . "VALUES ($eventId,10,101,'guardian','confirmed',CURRENT_TIMESTAMP)"
        );
    }

    public function testUnapprovedOrUnverifiedPersonCannotBeRegistered(): void
    {
        $pdo = $this->database();
        $eventId = $this->openFreeEvent($pdo, 3);

        foreach ([[11, 100], [12, 102]] as [$accountId, $sportovecId]) {
            try {
                \clubEventRegisterParticipant($pdo, $eventId, $accountId, $sportovecId, '2026.1', true);
                self::fail('Only an approved K2 relation on an active verified account is allowed.');
            } catch (\ClubEventRegistrationException $exception) {
                self::assertStringContainsString('K2', $exception->getMessage());
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registrations')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registration_events')->fetchColumn());
    }

    public function testWaitlistFifoPromotionSkipsRevokedK2Relation(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,1);
        $confirmed=\clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        $firstWaiting=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        $secondWaiting=\clubEventRegisterParticipant($pdo,$eventId,10,103,'2026.1',true);
        self::assertSame('waitlisted',$firstWaiting['status']);
        self::assertSame('waitlisted',$secondWaiting['status']);
        $mine=\clubEventMyRegistrations($pdo,10);$positions=[];
        foreach($mine as$row){if($row['status']==='waitlisted')$positions[(int)$row['id']]=$row['waitlist_position'];}
        self::assertSame(1,$positions[$firstWaiting['id']]);
        self::assertSame(2,$positions[$secondWaiting['id']]);

        $relationId=(int)$pdo->query('SELECT id FROM account_person_roles WHERE account_id=10 AND sportovec_id=101')->fetchColumn();
        \accountPersonRoleRevoke($pdo,$relationId,7,'Vazba již není platná.');
        $cancelled=\clubEventCancelRegistration($pdo,$confirmed['id'],10,'Uvolnění místa.');
        self::assertSame($secondWaiting['id'],$cancelled['promoted_registration_id']);
        self::assertSame('cancelled',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$firstWaiting['id'])->fetchColumn());
        self::assertSame('confirmed',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$secondWaiting['id'])->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_registration_events WHERE action='waitlist_ineligible'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_registration_events WHERE action='promote_waitlist'")->fetchColumn());
    }

    public function testLeavingWaitlistDoesNotChangeConfirmedCapacity(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,1);
        \clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        $waiting=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        $cancelled=\clubEventCancelRegistration($pdo,$waiting['id'],10,'Už nechceme čekat.');
        self::assertTrue($cancelled['changed']);
        self::assertNull($cancelled['promoted_registration_id']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_registrations WHERE status='confirmed'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_event_registrations WHERE status='waitlisted'")->fetchColumn());
    }

    public function testCurrentConsentIsMandatoryAndCancellationDeadlineIsFailClosed(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,2);
        try {
            \clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',false);
            self::fail('Explicit consent is required.');
        } catch (\InvalidArgumentException) {
        }
        try {
            \clubEventRegisterParticipant($pdo,$eventId,10,100,'old-version',true);
            self::fail('A stale consent version must fail.');
        } catch (\ClubEventRegistrationException $exception) {
            self::assertStringContainsString('změnily',$exception->getMessage());
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_registrations')->fetchColumn());
        $registration=\clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        try {
            \clubEventConfigureRegistrationTerms($pdo,$eventId,7,'2026.2','Nový souhlas.','Nové storno.',((int)date('Y')+1).'-08-20T10:00',true);
            self::fail('Terms must be immutable after opening.');
        } catch (\ClubEventRegistrationException) {
        }
        $pdo->exec("UPDATE club_event_registrations SET cancellation_deadline_snapshot='2000-01-01 00:00:00' WHERE id=".$registration['id']);
        try {
            \clubEventCancelRegistration($pdo,$registration['id'],10,'Pozdní storno.');
            self::fail('Cancellation after the snapshotted deadline must fail.');
        } catch (\ClubEventRegistrationException $exception) {
            self::assertStringContainsString('uplynul',$exception->getMessage());
        }
        self::assertSame('confirmed',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$registration['id'])->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_registration_events')->fetchColumn());
    }

    public function testPromotionQueuesAndProcessesOneNotification(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,1);
        $confirmed=\clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        $waiting=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        $result=\clubEventCancelRegistration($pdo,$confirmed['id'],10,'Uvolnění místa.');
        self::assertSame($waiting['id'],$result['promoted_registration_id']);
        $queued=$pdo->query('SELECT * FROM club_event_notifications')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('pending',$queued['status']);
        self::assertSame('a@example.test',$queued['recipient_email']);
        self::assertStringContainsString('Bezplatný kroužek',$queued['subject_plain']);
        $delivered=[];
        $sent=\clubEventNotificationProcessOne(
            $pdo,
            static function(string $email,string $subject,string $body)use(&$delivered):bool{
                $delivered=[$email,$subject,$body];return true;
            }
        );
        self::assertTrue($sent);
        self::assertSame('a@example.test',$delivered[0]);
        self::assertStringContainsString('Bára Druhá',$delivered[2]);
        self::assertSame('sent',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT attempts FROM club_event_notifications')->fetchColumn());
        self::assertNull(\clubEventNotificationProcessOne($pdo,static fn():bool=>true));
    }

    public function testAdminCanAuditLateCancellationAndPromoteWaitlist(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,1);
        $confirmed=\clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        $waiting=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        $pdo->exec("UPDATE club_event_registrations SET cancellation_deadline_snapshot='2000-01-01 00:00:00' WHERE id=".$confirmed['id']);
        try {
            \clubEventCancelRegistration($pdo,$confirmed['id'],10,'Pozdní storno.');
            self::fail('Account cancellation must respect the deadline.');
        } catch (\ClubEventRegistrationException) {
        }
        try {
            \clubEventAdminCancelRegistration($pdo,$confirmed['id'],7,'Schválená výjimka.',false);
            self::fail('Admin override requires explicit confirmation.');
        } catch (\InvalidArgumentException) {
        }
        $result=\clubEventAdminCancelRegistration($pdo,$confirmed['id'],7,'Schválená výjimka.',true);
        self::assertTrue($result['changed']);
        self::assertSame($waiting['id'],$result['promoted_registration_id']);
        self::assertSame('cancelled',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$confirmed['id'])->fetchColumn());
        self::assertSame('confirmed',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$waiting['id'])->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_registration_events WHERE action='admin_cancel_late' AND actor_type='trainer' AND actor_id=7")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE registration_id=".$waiting['id'])->fetchColumn());
    }

    public function testFailedNotificationIsRetriedAndEventuallyQuarantined(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,1);
        $confirmed=\clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        \clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        \clubEventCancelRegistration($pdo,$confirmed['id'],10,'Uvolnění místa.');
        for($attempt=1;$attempt<=5;$attempt++){
            $pdo->exec("UPDATE club_event_notifications SET available_at='2000-01-01 00:00:00'");
            self::assertFalse(\clubEventNotificationProcessOne($pdo,static fn():bool=>false));
            self::assertSame($attempt,(int)$pdo->query('SELECT attempts FROM club_event_notifications')->fetchColumn());
        }
        self::assertSame('failed',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertNull(\clubEventNotificationProcessOne($pdo,static fn():bool=>true));
    }

    public function testNotificationPersistenceFailureRollsBackCancellationAndPromotion(): void
    {
        $pdo=$this->database();$eventId=$this->openFreeEvent($pdo,1);
        $confirmed=\clubEventRegisterParticipant($pdo,$eventId,10,100,'2026.1',true);
        $waiting=\clubEventRegisterParticipant($pdo,$eventId,10,101,'2026.1',true);
        $pdo->exec('DROP TABLE club_event_notifications');
        try {
            \clubEventAdminCancelRegistration($pdo,$confirmed['id'],7,'Test atomického rollbacku.',true);
            self::fail('Missing transactional outbox must fail closed.');
        } catch (\ClubEventRegistrationException) {
        }
        self::assertSame('confirmed',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$confirmed['id'])->fetchColumn());
        self::assertSame('waitlisted',$pdo->query('SELECT status FROM club_event_registrations WHERE id='.$waiting['id'])->fetchColumn());
    }

    public function testAgeWindowAndOwnershipChecksRollBackCleanly(): void
    {
        $pdo = $this->database();
        $eventId = $this->openFreeEvent($pdo, 3, 6, 10);
        try {
            \clubEventRegisterParticipant($pdo, $eventId, 12, 102, '2026.1', true);
            self::fail('Unverified account is blocked before age evaluation.');
        } catch (\ClubEventRegistrationException) {
        }
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=12');
        try {
            \clubEventRegisterParticipant($pdo, $eventId, 12, 102, '2026.1', true);
            self::fail('Age limit must be enforced on the first session date.');
        } catch (\ClubEventRegistrationException $exception) {
            self::assertStringContainsString('věkové', $exception->getMessage());
        }
        $pdo->exec("UPDATE club_events SET registration_ends_at='2000-01-01 00:00:00' WHERE id=$eventId");
        try {
            \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
            self::fail('Closed registration window must be enforced.');
        } catch (\ClubEventRegistrationException $exception) {
            self::assertStringContainsString('skončilo', $exception->getMessage());
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registrations')->fetchColumn());

        $pdo->exec('UPDATE club_events SET registration_ends_at=NULL,min_age=NULL,max_age=NULL WHERE id=' . $eventId);
        $registration = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        try {
            \clubEventCancelRegistration($pdo, $registration['id'], 11, 'Cizí účet.');
            self::fail('Another account must not cancel the registration.');
        } catch (\ClubEventRegistrationException) {
        }
        self::assertSame('confirmed', $pdo->query(
            'SELECT status FROM club_event_registrations WHERE id=' . $registration['id']
        )->fetchColumn());
    }

    public function testOpeningRequiresFreeClubEventSessionProductAndConfirmation(): void
    {
        $pdo = $this->database();
        $event = \clubEventCreateDraft($pdo, 7, $this->eventInput(2));
        foreach ([
            ['', true],
            ['Schválený provozní start.', false],
        ] as [$note, $confirmed]) {
            try {
                \clubEventOpenFreeRegistration($pdo, $event['id'], 7, $note, $confirmed);
                self::fail('Explicit note and confirmation are required.');
            } catch (\InvalidArgumentException) {
            }
        }
        try {
            \clubEventOpenFreeRegistration($pdo, $event['id'], 7, 'Chybí termín.', true);
            self::fail('Session is required.');
        } catch (\ClubEventRegistrationException) {
        }
        self::assertSame('draft', $pdo->query(
            'SELECT status FROM club_events WHERE id=' . $event['id']
        )->fetchColumn());
        $sessionYear=(int)date('Y')+1;
        \clubEventAddSession($pdo,$event['id'],7,$sessionYear.'-09-02T16:00',$sessionYear.'-09-02T17:00','Velodrom',2);
        \clubEventLinkProduct($pdo,$event['id'],501,7,'Bezplatný produkt.');
        \clubEventConfigureRegistrationTerms($pdo,$event['id'],7,'2026.1','Souhlasím s účastí dítěte.','Bezplatné storno je možné do uvedeného termínu.',$sessionYear.'-08-30T16:00',true);
        try {
            \clubEventConfigureRegistrationTerms($pdo,$event['id'],7,'2026.1','Jiný text pod stejnou verzí.','Bezplatné storno je možné do uvedeného termínu.',$sessionYear.'-08-30T16:00',true);
            self::fail('A consent version must be immutable.');
        } catch (\ClubEventRegistrationException $exception) {
            self::assertStringContainsString('novou verzi',$exception->getMessage());
        }
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
        $pdo->exec("UPDATE shop_variants SET price_mode='fixed',amount_minor=100 WHERE id=601");
        try {
            \clubEventOpenFreeRegistration($pdo,$event['id'],7,'Cena se změnila.',true);
            self::fail('Current product price must be checked again when opening.');
        } catch (\ClubEventRegistrationException $exception) {
            self::assertStringContainsString('není bezplatný', $exception->getMessage());
        }
        self::assertSame('draft', $pdo->query(
            'SELECT status FROM club_events WHERE id=' . $event['id']
        )->fetchColumn());
    }

    private function openFreeEvent(PDO $pdo, int $capacity, ?int $minAge = null, ?int $maxAge = null): int
    {
        $input = $this->eventInput($capacity);
        $input['min_age'] = $minAge === null ? '' : (string)$minAge;
        $input['max_age'] = $maxAge === null ? '' : (string)$maxAge;
        $event = \clubEventCreateDraft($pdo, 7, $input);
        \clubEventAddSession(
            $pdo,
            $event['id'],
            7,
            ((int)date('Y') + 1) . '-09-01T16:00',
            ((int)date('Y') + 1) . '-09-01T17:30',
            'Velodrom',
            1
        );
        \clubEventLinkProduct($pdo, $event['id'], 501, 7, 'Bezplatný produkt kroužku.');
        \clubEventConfigureRegistrationTerms(
            $pdo,
            $event['id'],
            7,
            '2026.1',
            'Souhlasím s účastí dítěte.',
            'Bezplatné storno je možné do uvedeného termínu.',
            ((int)date('Y') + 1) . '-08-30T16:00',
            true
        );
        \clubEventOpenFreeRegistration($pdo, $event['id'], 7, 'Schválený provozní start.', true);
        return $event['id'];
    }

    /** @return array<string,string|int> */
    private function eventInput(int $capacity): array
    {
        return [
            'code' => 'FREE-' . bin2hex(random_bytes(4)),
            'event_type' => 'club_event',
            'name' => 'Bezplatný kroužek',
            'description_plain' => 'První řízený průchod.',
            'audience_label' => 'Děti',
            'min_age' => '',
            'max_age' => '',
            'capacity' => $capacity,
            'pricing_policy' => 'free',
            'currency' => 'CZK',
            'registration_starts_at' => '',
            'registration_ends_at' => '',
        ];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec('CREATE TABLE sportovci (id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL,prijmeni TEXT NOT NULL,narozeni TEXT NULL)');
        $year = (int)date('Y') + 1;
        $pdo->exec("INSERT INTO sportovci VALUES (100,'Anna','První','" . ($year - 8) . "-05-10'),(101,'Bára','Druhá','" . ($year - 9) . "-04-03'),(102,'Cyril','Starší','" . ($year - 15) . "-01-01'),(103,'Dana','Třetí','" . ($year - 8) . "-02-02')");
        $pdo->exec('CREATE TABLE verejni_uzivatele (id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER NOT NULL,email_overeno INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES (10,'Rodič','První','a@example.test',1,1),(11,'Cizí','Účet','b@example.test',1,1),(12,'Neověřený','Rodič','c@example.test',1,0)");
        $pdo->exec('CREATE TABLE shop_products (id INTEGER PRIMARY KEY,name TEXT NOT NULL,offer_type TEXT NOT NULL,catalog_status TEXT NOT NULL)');
        $pdo->exec("INSERT INTO shop_products VALUES (501,'Bezplatný kroužek','club_event','internal_only')");
        $pdo->exec('CREATE TABLE shop_variants (id INTEGER PRIMARY KEY,product_id INTEGER NOT NULL,price_mode TEXT NOT NULL,amount_minor INTEGER NULL,currency TEXT NULL,visible INTEGER NULL)');
        $pdo->exec("INSERT INTO shop_variants VALUES (601,501,'free',0,'CZK',1)");

        foreach ([
            '20260802230000_account_person_roles.php',
            '20260803110000_club_events.php',
            '20260803130000_club_event_registrations.php',
            '20260803150000_club_event_terms.php',
            '20260803170000_club_event_waitlist.php',
            '20260803190000_club_event_notifications.php',
        ] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        \accountPersonRoleApprove($pdo, 10, 100, 'guardian', 7, 'Schválený rodič.');
        \accountPersonRoleApprove($pdo, 10, 101, 'guardian', 7, 'Schválený rodič.');
        \accountPersonRoleApprove($pdo, 10, 103, 'guardian', 7, 'Schválený rodič.');
        \accountPersonRoleApprove($pdo, 12, 102, 'guardian', 7, 'Schválený rodič, účet zatím neověřen.');
        return $pdo;
    }
}
