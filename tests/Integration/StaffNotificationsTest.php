<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ParentJourneyNotificationFixture;

require_once dirname(__DIR__, 2) . '/includes/account_person_claim.php';
require_once dirname(__DIR__, 2) . '/includes/club_event_notification.php';
require_once dirname(__DIR__, 2) . '/includes/staff_notification_worker.php';

final class StaffNotificationsTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,aktivni INTEGER,technical_email INTEGER DEFAULT 0);
            CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);
            CREATE TABLE staff_superadmins(trainer_id INTEGER);
            CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER);
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT);
            CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY);
            INSERT INTO treneri(id,jmeno,email,aktivni) VALUES
                (1,'LOCALHOST Registrář','registrar@example.test',1),
                (2,'LOCALHOST Druhý','second@example.test',1),
                (3,'LOCALHOST Superadmin','super@example.test',1),
                (4,'LOCALHOST Trenér','coach@example.test',1),
                (5,'LOCALHOST Neaktivní','inactive@example.test',0);
            INSERT INTO staff_user_positions VALUES(1,'registrar'),(2,'registrar'),(4,'coach'),(5,'registrar');
            INSERT INTO staff_superadmins VALUES(3);
            INSERT INTO verejni_uzivatele VALUES(1,'LOCALHOST','NEPLATIT','family@example.test',1,1);");
        $migration = require dirname(__DIR__, 2) . '/migrations/20260802233000_account_person_claim_requests.php';
        $migration['up']($pdo);
        ParentJourneyNotificationFixture::install($pdo);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261106090000_staff_notifications.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }

    private function submit(PDO $pdo): int
    {
        return \accountPersonClaimSubmit($pdo, 1, 'guardian', 'LOCALHOST', 'NEPLATIT', '2014-01-01', 'Soukromá poznámka')['id'];
    }

    public function testAtomicEventFanOutAndRepeatDoesNotDuplicateMail(): void
    {
        $pdo = $this->database();
        $id = $this->submit($pdo);
        self::assertSame($id, $this->submit($pdo));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM staff_notification_events')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
        $result = \staffNotificationSync($pdo);
        self::assertSame(2, $result['recipients']);
        self::assertSame(2, $result['emails']);
        self::assertSame(0, \staffNotificationSync($pdo)['emails']);
        self::assertSame(1, \staffNotificationInbox($pdo, 1)['unread']);
        self::assertSame(0, \staffNotificationInbox($pdo, 3)['unread']);
        self::assertSame(0, \staffNotificationInbox($pdo, 4)['unread']);
        $mail = $pdo->query('SELECT * FROM club_event_notifications ORDER BY id LIMIT 1')->fetch();
        self::assertStringNotContainsString('2014', $mail['body_plain']);
        self::assertStringNotContainsString('Soukromá', $mail['body_plain']);
        self::assertStringNotContainsString('NEPLATIT', $mail['body_plain']);
        self::assertStringContainsString('staff_notifications.php?notice=', $mail['body_plain']);
        $sent = [];
        self::assertTrue(\clubEventNotificationProcessOne($pdo, static function ($email, $subject, $body) use (&$sent) {
            $sent[] = $email;
            return true;
        }));
        self::assertSame(['registrar@example.test'], $sent);
    }

    public function testMissingEventSchemaRollsBackSourceSubmission(): void
    {
        $pdo = $this->database();
        $pdo->exec('DROP TABLE staff_notifications; DROP TABLE staff_notification_events');
        try {
            $this->submit($pdo);
            self::fail('Must not lose a required notification.');
        } catch (\AccountPersonClaimException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_requests')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_events')->fetchColumn());
        }
    }

    public function testStaffWorkerDoesNotDrainOrExpireUnrelatedQueueAndCountsReadCancellation(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_event_notifications(notification_type,recipient_email,recipient_name,subject_plain,body_plain,status,claimed_at)
            VALUES('unrelated','outside@example.test','','LOCALHOST','NEPLATIT','pending',NULL),
            ('unrelated','outside@example.test','','LOCALHOST','NEPLATIT','processing','2020-01-01 00:00:00')");
        $this->submit($pdo);
        \staffNotificationSync($pdo);
        $notice = \staffNotificationInbox($pdo, 1)['items'][0];
        \staffNotificationRead($pdo, 1, (int)$notice['id'], true);
        $sent = [];
        $result = \staffNotificationDrain($pdo, static function ($email) use (&$sent): bool {
            $sent[] = $email;
            return true;
        });
        self::assertSame(2, $result['processed']);
        self::assertSame(1, $result['sent']);
        self::assertSame(1, $result['cancelled']);
        self::assertSame(['second@example.test'], $sent);
        self::assertSame(['pending', 'processing'], $pdo->query("SELECT status FROM club_event_notifications WHERE notification_type='unrelated' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(0, \staffNotificationDrain($pdo, static fn (): bool => true)['processed']);
        self::assertSame(1, \staffNotificationWorkerStatus($pdo)['queue']['sent']);
    }

    public function testReadIsPersonalAndDoesNotResolveSource(): void
    {
        $pdo = $this->database();
        $id = $this->submit($pdo);
        \staffNotificationSync($pdo);
        $notice = \staffNotificationInbox($pdo, 1)['items'][0];
        \staffNotificationRead($pdo, 1, (int)$notice['id'], true);
        self::assertSame(0, \staffNotificationInbox($pdo, 1)['unread']);
        self::assertSame(1, \staffNotificationInbox($pdo, 2)['unread']);
        self::assertSame('pending', $pdo->query('SELECT status FROM account_person_claim_requests WHERE id=' . $id)->fetchColumn());
        \staffNotificationRead($pdo, 1, (int)$notice['id'], false);
        self::assertSame(1, \staffNotificationInbox($pdo, 1)['unread']);
        $this->expectException(\DomainException::class);
        \staffNotificationRead($pdo, 2, (int)$notice['id'], true);
    }

    public function testResolvedTaskDisappearsFromUnreadForEveryoneAndCancelsStaleMail(): void
    {
        $pdo = $this->database();
        $id = $this->submit($pdo);
        \staffNotificationSync($pdo);
        \accountPersonClaimReject($pdo, $id, 1, 'LOCALHOST ověření');
        self::assertSame(0, \staffNotificationInbox($pdo, 1)['unread']);
        self::assertSame(0, \staffNotificationInbox($pdo, 2)['unread']);
        self::assertSame(0, (int)\staffNotificationInbox($pdo, 2)['items'][0]['actionable']);
        self::assertSame('cancelled', \clubEventNotificationProcessOne($pdo, static function () {
            self::fail('Resolved task must not send.');
        }));
        self::assertSame('cancelled', \clubEventNotificationProcessOne($pdo, static function () {
            self::fail('Resolved task must not send.');
        }));
        self::assertSame(0, \staffNotificationSync($pdo)['emails']);
    }

    public function testRevokedRoleHiddenAndRevokedOrChangedRecipientNeverReceivesMail(): void
    {
        $pdo = $this->database();
        $this->submit($pdo);
        \staffNotificationSync($pdo);
        $notice = \staffNotificationInbox($pdo, 1)['items'][0];
        $pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=1; UPDATE treneri SET email='changed@example.test' WHERE id=2");
        self::assertSame([], \staffNotificationInbox($pdo, 1)['items']);
        self::assertSame('cancelled', \clubEventNotificationProcessOne($pdo, static function () {
            self::fail('Revoked recipient.');
        }));
        self::assertSame('cancelled', \clubEventNotificationProcessOne($pdo, static function () {
            self::fail('Changed recipient.');
        }));
        $this->expectException(\DomainException::class);
        \staffNotificationGet($pdo, 1, (int)$notice['id']);
    }

    public function testFallbackAndLaterAssignedRegistrarAndInvalidEmailRecovery(): void
    {
        $pdo = $this->database();
        $this->submit($pdo);
        $pdo->exec("DELETE FROM staff_user_positions WHERE position_code='registrar'");
        self::assertSame(1, \staffNotificationSync($pdo)['emails']);
        self::assertSame(1, \staffNotificationInbox($pdo, 3)['unread']);
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'registrar'); UPDATE treneri SET technical_email=1 WHERE id=1");
        $result = \staffNotificationSync($pdo);
        self::assertSame(1, $result['recipients']);
        self::assertSame(1, $result['invalid_email']);
        self::assertSame(1, \staffNotificationInbox($pdo, 1)['unread']);
        $pdo->exec('UPDATE treneri SET technical_email=0 WHERE id=1');
        self::assertSame(1, \staffNotificationSync($pdo)['emails']);
        self::assertSame(0, \staffNotificationSync($pdo)['emails']);
    }

    public function testUnassignedEventsRemainRecoverableAndOldPendingRequestsAreIncluded(): void
    {
        $pdo = $this->database();
        $this->submit($pdo);
        $pdo->exec('DELETE FROM staff_notification_events; DELETE FROM staff_superadmins; DELETE FROM staff_user_positions');
        $result = \staffNotificationSync($pdo);
        self::assertSame(1, $result['events']);
        self::assertSame(1, $result['without_recipient']);
        $pdo->exec('INSERT INTO staff_superadmins VALUES(3)');
        self::assertSame(1, \staffNotificationSync($pdo)['emails']);
    }

    public function testOutboxFailureRollsBackRecipientAndCanRetry(): void
    {
        $pdo = $this->database();
        $this->submit($pdo);
        $pdo->exec("CREATE TRIGGER reject_mail BEFORE INSERT ON club_event_notifications BEGIN SELECT RAISE(ABORT,'simulated failure'); END");
        try {
            \staffNotificationSync($pdo);
            self::fail('Outbox failure must propagate.');
        } catch (\PDOException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM staff_notifications')->fetchColumn());
        }
        $pdo->exec('DROP TRIGGER reject_mail');
        self::assertSame(2, \staffNotificationSync($pdo)['emails']);
    }

    public function testInboxPaginationAndSpecificClaimOutsideFirstPage(): void
    {
        $pdo = $this->database();
        $pdo->beginTransaction();
        for ($i = 1; $i <= 205; $i++) {
            $pdo->exec('INSERT INTO account_person_claim_requests'
                . '(id,account_id,requested_role,claimed_jmeno,claimed_prijmeni,claimed_narozeni,requester_message) '
                . "VALUES($i,1,'guardian','LOCALHOST','NEPLATIT','2014-01-01','')");
            \staffNotificationRecord($pdo, 'person_claim', $i);
        }
        $pdo->commit();
        \staffNotificationSync($pdo);
        $first = \staffNotificationInbox($pdo, 1);
        self::assertSame(205, $first['unread']);
        self::assertCount(25, $first['items']);
        self::assertTrue($first['has_next']);
        self::assertCount(5, \staffNotificationInbox($pdo, 1, false, 9)['items']);
        self::assertSame(205, (int)\accountPersonClaimListForAdmin($pdo, 200, 205)[0]['id']);
    }
}
