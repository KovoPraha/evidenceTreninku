<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/club_event_notification.php';
require_once dirname(__DIR__,2).'/includes/operational_jobs.php';

final class OperationalDeliveryTest extends TestCase
{
    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,dedupe_key TEXT UNIQUE,notification_type TEXT,recipient_email TEXT,recipient_name TEXT,subject_plain TEXT,body_plain TEXT,status TEXT DEFAULT 'pending',attempts INTEGER DEFAULT 0,available_at TEXT DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT,claim_token TEXT,sent_at TEXT,last_error TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE verejne_rezervace(id INTEGER PRIMARY KEY,lekce_id INTEGER,uzivatel_id INTEGER,sportovec_id INTEGER,stav TEXT,slot_cas_od TEXT,slot_cas_do TEXT)');
        $pdo->exec('CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY,trener_id INTEGER,nazev TEXT,datum TEXT)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,aktivni INTEGER)');
        $pdo->exec('CREATE TABLE account_person_roles(account_id INTEGER,sportovec_id INTEGER,status TEXT,relation_role TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO verejne_rezervace VALUES(1,1,1,12,'potvrzena','10:00','11:00')");
        $pdo->exec("INSERT INTO individualni_lekce VALUES(1,1,'LOCALHOST NEPLATIT','2099-01-01')");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test',1,1)");
        $pdo->exec("INSERT INTO treneri VALUES(1,'trainer@example.test',1)");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,12,'approved','guardian','2020-01-01',NULL)");
        $migration=require dirname(__DIR__,2).'/migrations/20261022212000_operational_delivery.php';
        $migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }

    private function enqueue(PDO $pdo): int
    {
        $pdo->beginTransaction();$id=\bookingNotificationEnqueue($pdo,1,'confirmed');$pdo->commit();return $id;
    }

    public function testAtomicRollbackAndDedupeDoNotRepeatBusinessAction(): void
    {
        $pdo=$this->database();$pdo->beginTransaction();
        \bookingNotificationEnqueue($pdo,1,'confirmed');$pdo->rollBack();
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
        $id=$this->enqueue($pdo);self::assertSame($id,$this->enqueue($pdo));
        $calls=0;$sender=static function()use(&$calls):bool{$calls++;return true;};
        self::assertTrue(\clubEventNotificationProcessOne($pdo,$sender));
        self::assertNull(\clubEventNotificationProcessOne($pdo,$sender));self::assertSame(1,$calls);
        self::assertSame('potvrzena',$pdo->query('SELECT stav FROM verejne_rezervace')->fetchColumn());
        self::assertStringNotContainsString('#token=', $pdo->query('SELECT body_plain FROM club_event_notifications')->fetchColumn());
    }

    public function testKnownRejectionRetriesButAmbiguousDeliveryIsHeld(): void
    {
        $pdo=$this->database();$this->enqueue($pdo);
        self::assertFalse(\clubEventNotificationProcessOne($pdo,static fn()=>false));
        self::assertSame('pending',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertNull(\clubEventNotificationProcessOne($pdo,static fn()=>true));
        $pdo->exec("UPDATE club_event_notifications SET available_at=datetime('now','-1 minute')");
        self::assertFalse(\clubEventNotificationProcessOne($pdo,static function(){throw new \RuntimeException('unknown');}));
        self::assertSame('failed',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertStringContainsString('delivery_unknown',$pdo->query('SELECT last_error FROM club_event_notifications')->fetchColumn());
        self::assertNull(\clubEventNotificationProcessOne($pdo,static fn()=>true));
    }

    public function testStaleOrCompetingClaimCannotSendTwice(): void
    {
        $pdo=$this->database();$this->enqueue($pdo);$claim=\clubEventNotificationClaim($pdo);
        self::assertNotNull($claim);self::assertNull(\clubEventNotificationClaim($pdo));
        $pdo->exec("UPDATE club_event_notifications SET claimed_at=datetime('now','-1 hour')");
        self::assertNull(\clubEventNotificationClaim($pdo));
        self::assertSame('failed',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        $this->expectException(\ClubEventNotificationException::class);
        \clubEventNotificationComplete($pdo,(int)$claim['id'],$claim['claim_token'],true);
    }

    public function testChangedRecipientExpiredRevokedAndChangedBusinessStateNeverSend(): void
    {
        foreach (["UPDATE verejni_uzivatele SET aktivni=0","UPDATE verejni_uzivatele SET email='other@example.test'",
            "UPDATE account_person_roles SET status='revoked'","UPDATE account_person_roles SET valid_to='2020-01-01'",
            "UPDATE booking_notification_context SET expires_at='2020-01-01'","UPDATE verejne_rezervace SET stav='zrusena'", "UPDATE individualni_lekce SET datum='2099-02-02'", "UPDATE verejne_rezervace SET slot_cas_od='12:00'"] as $change) {
            $pdo=$this->database();$this->enqueue($pdo);$pdo->exec($change);$calls=0;
            self::assertFalse(\clubEventNotificationProcessOne($pdo,static function()use(&$calls){$calls++;return true;}));
            self::assertSame(0,$calls);self::assertSame('failed',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        }
    }

    public function testMissingContactCreatesVisibleManualCase(): void
    {
        $pdo=$this->database();$pdo->exec("UPDATE verejni_uzivatele SET email=''");$this->enqueue($pdo);
        self::assertSame('failed',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertStringContainsString('manual_contact_required',$pdo->query('SELECT last_error FROM club_event_notifications')->fetchColumn());
        self::assertNull(\clubEventNotificationProcessOne($pdo,static fn()=>true));
    }

    public function testJobMonitorDistinguishesDisabledMissingFailureLateAndSuccessReadOnly(): void
    {
        $pdo=$this->database();$run=\operationalJobStart($pdo,'transactional_notifications');
        \operationalJobFinish($pdo,$run,true);$failed=\operationalJobStart($pdo,'training_reminders');
        \operationalJobFinish($pdo,$failed,false,'queue_failed');
        $pdo->exec('PRAGMA query_only=ON');
        $config=['transactional_notifications'=>'expected','training_reminders'=>'expected','fio_import'=>'disabled'];
        $rows=array_column(\operationalJobsStatus($pdo,$config),'status','job_key');
        self::assertSame('healthy',$rows['transactional_notifications']);self::assertSame('failed',$rows['training_reminders']);
        self::assertSame('disabled',$rows['fio_import']);self::assertSame('configuration_missing',$rows['backup']);
        $rows=array_column(\operationalJobsStatus($pdo,$config,time()+5000),'status','job_key');
        self::assertSame('late',$rows['transactional_notifications']);
    }

    public function testPersonalResolutionIsAuditedIdempotentAndNeverFakeSent(): void
    {
        $pdo=$this->database();
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'order_operator')");
        $migration=require dirname(__DIR__,2).'/migrations/20260803210000_club_event_notification_admin.php';$migration['up']($pdo);
        $pdo->exec("UPDATE verejni_uzivatele SET email=''");$id=$this->enqueue($pdo);
        self::assertTrue(\clubEventNotificationResolveManually($pdo,$id,1,'LOCALHOST: osobně předáno členu.',true)['changed']);
        self::assertFalse(\clubEventNotificationResolveManually($pdo,$id,1,'Obnovený POST.',true)['changed']);
        self::assertSame('failed',$pdo->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertNull($pdo->query('SELECT sent_at FROM club_event_notifications')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notification_events WHERE action='manual_contact_resolved'")->fetchColumn());
        $pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(\ClubEventNotificationException::class);
        \clubEventNotificationResolveManually($pdo,$id,1,'Odebrané oprávnění.',true);
    }
}
