<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/roster_message.php';

final class RosterMessageTest extends TestCase
{
    public function testRecipientsComeFromActiveRosterAndQueueCompletes():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260922130000_roster_messages.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $preview=\rosterMessagePreview($pdo,10,'Změna tréninku','Zítřejší trénink začíná v 17:00.','2026-09-22');self::assertCount(1,$preview['recipients']);self::assertSame('parent@example.test',$preview['recipients'][0]['email']);
        $message=\rosterMessageEnqueue($pdo,10,$preview['subject'],$preview['body'],$preview['fingerprint'],7,'Provozní změna času.',true);self::assertSame(1,$message['recipient_count']);$sent=[];$result=\rosterMessageProcessOne($pdo,static function(string$email,string$subject,string$body)use(&$sent):bool{$sent=[$email,$subject,$body];return true;});self::assertTrue($result);self::assertSame('parent@example.test',$sent[0]);self::assertSame('sent',$pdo->query('SELECT status FROM roster_messages')->fetchColumn());self::assertNull(\rosterMessageProcessOne($pdo,static fn():bool=>true));
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY);CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT,status TEXT);CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT,status TEXT);CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER)");
        $pdo->exec("INSERT INTO treneri VALUES(7);INSERT INTO club_seasons VALUES(1,'2026','active');INSERT INTO club_teams VALUES(10,1,'Závodní U17','active');INSERT INTO club_roster_members VALUES(10,100,'active','2026-01-01',NULL),(10,101,'removed','2026-01-01','2026-08-01');INSERT INTO verejni_uzivatele VALUES(20,'parent@example.test','Petr','Rodič',1,1),(21,'inactive@example.test','Iva','Neaktivní',0,1);INSERT INTO account_person_roles VALUES(1,20,100,'guardian','approved','2026-01-01',NULL),(2,21,100,'guardian','approved','2026-01-01',NULL)");return$pdo;
    }
}
