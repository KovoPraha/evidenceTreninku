<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_payment_notification.php';

final class ShopSystemEmailTest extends TestCase
{
    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY)');$pdo->exec('INSERT INTO treneri VALUES(7)');
        $pdo->exec("CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,status TEXT,customer_email_snapshot TEXT,customer_name_snapshot TEXT)");
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'LOCALHOST01','ready','buyer@example.test','LOCALHOST Kupující')");
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,quantity INTEGER)');
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,1,1,'LOCALHOST NEPLATIT',1)");
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER,registration_event_id INTEGER,order_id INTEGER,notification_type TEXT,recipient_email TEXT,recipient_name TEXT,subject_plain TEXT,body_plain TEXT,UNIQUE(order_id,notification_type))");
        $migration=require dirname(__DIR__,2).'/migrations/20261021170000_shop_system_email_templates.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }

    public function testEveryExistingEventHasAValidPreviewWithoutWritingAnything():void
    {
        $pdo=$this->database();
        foreach(\shopSystemEmailCatalog() as $key=>$definition) {
            $message=\shopSystemEmailMessage($pdo,$key,\shopSystemEmailSampleValues());
            self::assertSame(0,$message['version']);self::assertStringContainsString('LOCALHOST-NEPLATIT',$message['subject']);
            self::assertStringNotContainsString('{order_code}',$message['body']);
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_system_email_versions')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
    }

    public function testNewVersionChangesNewEmailsButNeverRewritesAnExistingOutboxRow():void
    {
        $pdo=$this->database();$key='shop_order_ready';
        $template=\shopSystemEmailCurrent($pdo,$key);
        self::assertTrue(\shopSystemEmailSave($pdo,$key,7,0,$template['subject_plain'],$template['body_plain']."\nOdběr: LOCALHOST recepce 16–18 h.",'Doplnění místa.')['changed']);
        $pdo->beginTransaction();$id=\shopOrderReadyNotificationEnqueue($pdo,1);$pdo->commit();
        $original=$pdo->query('SELECT * FROM club_event_notifications')->fetch();
        self::assertSame(1,(int)$original['system_template_version']);
        self::assertSame($key,$original['system_template_key']);self::assertStringContainsString('recepce',$original['body_plain']);
        $current=\shopSystemEmailCurrent($pdo,$key);
        \shopSystemEmailSave($pdo,$key,7,1,$current['subject_plain'],$current['body_plain']."\nNový kontakt: LOCALHOST.",'Doplnění kontaktu.');
        $pdo->beginTransaction();self::assertSame($id,\shopOrderReadyNotificationEnqueue($pdo,1));$pdo->commit();
        self::assertSame($original,$pdo->query('SELECT * FROM club_event_notifications')->fetch());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_system_email_versions')->fetchColumn());
        self::assertStringContainsString('Nový kontakt',\shopSystemEmailMessage($pdo,$key,\shopSystemEmailSampleValues())['body']);
    }

    public function testStaleEditAndMissingActorDoNotPartiallyChangeTheTemplate():void
    {
        $pdo=$this->database();$t=\shopSystemEmailCurrent($pdo,'shop_order_ready');
        \shopSystemEmailSave($pdo,'shop_order_ready',7,0,$t['subject_plain'],$t['body_plain']."\nPrvní úprava.",'První.');
        try {\shopSystemEmailSave($pdo,'shop_order_ready',7,0,$t['subject_plain'],$t['body_plain']."\nZastaralá úprava.",'Stará.');self::fail('Stale editor must be rejected.');}catch(\ShopSystemEmailException){}
        try {\shopSystemEmailSave($pdo,'shop_order_ready',999,1,$t['subject_plain'],$t['body_plain']."\nCizí autor.",'Pokus.');self::fail('Unknown actor must be rejected.');}catch(\PDOException){}
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_system_email_versions')->fetchColumn());
        self::assertStringContainsString('První úprava',\shopSystemEmailCurrent($pdo,'shop_order_ready')['body_plain']);
        self::assertFalse($pdo->inTransaction());
    }

    public function testUnknownVariableAndMissingAccessLinkAreRejectedBeforeSave():void
    {
        $pdo=$this->database();
        foreach([['shop_order_ready','{order_code} {items} {internal_note}'],['shop_guest_order_placed','{order_code} {amount} {variable_symbol} {due_at}']] as [$key,$body]) {
            try {\shopSystemEmailSave($pdo,$key,7,0,'Objednávka {order_code}',$body,'Pokus.');self::fail('Unsafe template must be rejected.');}catch(\InvalidArgumentException){}
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_system_email_versions')->fetchColumn());
    }

    public function testSubjectInjectionIsRejectedAfterExpandingCustomerData():void
    {
        $pdo=$this->database();$t=\shopSystemEmailCurrent($pdo,'shop_order_ready');
        \shopSystemEmailSave($pdo,'shop_order_ready',7,0,'Pro {customer_name} – {order_code}',$t['body_plain'],'Oslovení.');
        $values=\shopSystemEmailSampleValues();$values['customer_name']="Test\r\nBcc: another@example.test";
        $this->expectException(\ShopSystemEmailException::class);
        \shopSystemEmailMessage($pdo,'shop_order_ready',$values);
    }
}
