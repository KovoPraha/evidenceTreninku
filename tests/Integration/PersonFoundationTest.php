<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/person_identity_review.php';
require_once dirname(__DIR__,2).'/includes/person_state.php';

final class PersonFoundationTest extends TestCase
{
    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST Registrář')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,kis_external_id TEXT,stav_clenstvi TEXT,stav_manualni INTEGER,stav_duvod TEXT,stav_aktualizovan TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,skupiny TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(1,'LOCALHOST','Test','2012-01-01',NULL,'aktivni',1,'Přijat do oddílu','2026-01-01',0,0,500,'stará skupina'),(2,'LOCALHOST','Test','1900-01-01',NULL,'cekajici',0,NULL,NULL,1,1,0,'stará skupina')");
        $pdo->exec('CREATE TABLE sportovec_history(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,created_by INTEGER,source TEXT,event_type TEXT,title TEXT,detail TEXT,old_json TEXT,new_json TEXT,ref_table TEXT,ref_id INTEGER)');
        $pdo->exec('CREATE TABLE ucto_audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT,uzivatel_id INTEGER,akce TEXT,tabulka TEXT,zaznam_id INTEGER,detail TEXT,ip_adresa TEXT,user_agent TEXT)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(10,1,1),(11,0,1)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,10,1,'guardian','approved','2026-01-01',NULL),(2,11,1,'guardian','approved','2026-01-01',NULL),(3,10,2,'guardian','pending','2026-01-01',NULL)");
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT)');
        $pdo->exec("INSERT INTO club_teams VALUES(20,'LOCALHOST U15','active'),(21,'LOCALHOST U17','active')");
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,20,1,'active','2026-01-01',NULL),(2,20,1,'active','2026-01-01',NULL)");
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,variable_symbol TEXT,status TEXT)');
        $pdo->exec("INSERT INTO payments VALUES(1,'0000000013','pending')");
        $migration=require dirname(__DIR__,2).'/migrations/20261022210000_person_identity_reviews.php';$migration['up']($pdo);
        return $pdo;
    }

    private function input(string $id='legacy-1'): array
    {
        return ['source_namespace'=>'evidence/LOCALHOST','source_id'=>$id,'jmeno'=>'LOCALHOST','prijmeni'=>'Test','narozeni'=>'2012-01-01','evidence_ref'=>'LOCALHOST syntetický podklad NEPLATIT'];
    }

    public function testExactCandidateIsNotAutomaticallyVerifiedAndRetryIsIdempotent(): void
    {
        $pdo=$this->database();$id=personIdentityReviewCreate($pdo,$this->input(),7);
        $row=personIdentityReviewGet($pdo,$id);
        self::assertSame('review',$row['status']);self::assertNull($row['sportovec_id']);
        self::assertSame([1,2],json_decode($row['candidate_ids_json'],true));
        self::assertSame($id,personIdentityReviewCreate($pdo,$this->input(),7));
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM person_identity_review_events')->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
    }

    public function testSameSourceWithDifferentEvidenceNeverOverwritesCase(): void
    {
        $pdo=$this->database();$id=personIdentityReviewCreate($pdo,$this->input(),7);
        try {personIdentityReviewCreate($pdo,array_replace($this->input(),['jmeno'=>'Jiná osoba']),7);self::fail('Changed evidence must fail');}
        catch(RuntimeException $e){self::assertStringContainsString('jinými podklady',$e->getMessage());}
        self::assertSame('LOCALHOST',personIdentityReviewGet($pdo,$id)['first_name']);
        self::assertFalse($pdo->inTransaction());
    }

    public function testDeferredThenVerifiedAndRevisedDecisionRetainsHistoryWithoutMovingData(): void
    {
        $pdo=$this->database();$id=personIdentityReviewCreate($pdo,$this->input(),7);
        $before=$pdo->query('SELECT * FROM account_person_roles')->fetchAll();
        personIdentityReviewDecide($pdo,$id,1,'deferred',1,'Chybí podklad pro jednoznačné ověření.','LOCALHOST papír 1',7);
        self::assertNull(personIdentityReviewGet($pdo,$id)['sportovec_id']);
        personIdentityReviewDecide($pdo,$id,2,'verified',1,'Identita výslovně doložena v podkladu.','LOCALHOST papír 2',7);
        self::assertSame(1,(int)personIdentityReviewGet($pdo,$id)['sportovec_id']);
        personIdentityReviewDecide($pdo,$id,3,'excluded',null,'Duplicitní zdroj se samostatně nepřevádí.','LOCALHOST papír 3',7);
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM person_identity_review_events')->fetchColumn());
        $history=json_decode($pdo->query('SELECT before_json FROM person_identity_review_events WHERE revision=4')->fetchColumn(),true);
        self::assertSame('verified',$history['status']);self::assertSame(1,$history['sportovec_id']);
        self::assertSame($before,$pdo->query('SELECT * FROM account_person_roles')->fetchAll());
        self::assertSame('0000000013',$pdo->query('SELECT variable_symbol FROM payments')->fetchColumn());
    }

    public function testStaleDecisionFailsWithoutSecondAudit(): void
    {
        $pdo=$this->database();$id=personIdentityReviewCreate($pdo,$this->input(),7);
        personIdentityReviewDecide($pdo,$id,1,'verified',1,'Ověřeno podle původního podkladu.','LOCALHOST 1',7);
        try {personIdentityReviewDecide($pdo,$id,1,'verified',2,'Druhý pracovník se starým formulářem.','LOCALHOST 2',7);self::fail('Stale update');}
        catch(RuntimeException $e){self::assertStringContainsString('mezitím změnil',$e->getMessage());}
        self::assertSame(1,(int)personIdentityReviewGet($pdo,$id)['sportovec_id']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM person_identity_review_events')->fetchColumn());
    }

    public function testMissingTargetOrReasonDoesNotApproveAnIdentity(): void
    {
        $pdo=$this->database();$id=personIdentityReviewCreate($pdo,$this->input(),7);
        foreach([[null,'Ověřeno podle podkladů.'],[999,'Ověřeno podle podkladů.'],[1,'krátké']] as [$target,$reason]) {
            try {personIdentityReviewDecide($pdo,$id,1,'verified',$target,$reason,'LOCALHOST podklad',7);self::fail('Invalid approval');}
            catch(InvalidArgumentException){self::assertSame('review',personIdentityReviewGet($pdo,$id)['status']);}
        }
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM person_identity_review_events')->fetchColumn());
    }

    public function testAuditFailureRollsBackIdentityDecision(): void
    {
        $pdo=$this->database();$id=personIdentityReviewCreate($pdo,$this->input(),7);
        $pdo->exec("CREATE TRIGGER fail_identity_audit BEFORE INSERT ON person_identity_review_events BEGIN SELECT RAISE(ABORT,'audit failure'); END");
        try {personIdentityReviewDecide($pdo,$id,1,'verified',1,'Ověřeno podle původního podkladu.','LOCALHOST 1',7);self::fail('Must roll back');}
        catch(PDOException){self::assertSame('review',personIdentityReviewGet($pdo,$id)['status']);}
        self::assertSame(1,(int)personIdentityReviewGet($pdo,$id)['revision']);
    }

    public function testImportedAliasCannotBeReassignedByReviewLedger(): void
    {
        $pdo=$this->database();
        $pdo->exec('CREATE TABLE kis_person_aliases(source_key TEXT,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE kis_person_profiles(sportovec_id INTEGER,batch_fingerprint TEXT)');
        $pdo->exec('CREATE TABLE kis_person_batches(fingerprint TEXT,source_namespace TEXT)');
        $pdo->exec("INSERT INTO kis_person_aliases VALUES('legacy-1',1)");
        $pdo->exec("INSERT INTO kis_person_profiles VALUES(1,'batch1')");
        $pdo->exec("INSERT INTO kis_person_batches VALUES('batch1','evidence/LOCALHOST')");
        $id=personIdentityReviewCreate($pdo,$this->input(),7);
        try {personIdentityReviewDecide($pdo,$id,1,'verified',2,'Pokus o přesměrování existujícího aliasu.','LOCALHOST podklad',7);self::fail('Alias collision');}
        catch(RuntimeException $e){self::assertStringContainsString('již navázán',$e->getMessage());}
        self::assertSame('review',personIdentityReviewGet($pdo,$id)['status']);
    }

    public function testPlaceholderAndMissingIncomingBirthDateOnlyProduceSimilarity(): void
    {
        $pdo=$this->database();
        foreach(['1900-01-01',''] as $date) {
            $match=personMatchV1($pdo,['jmeno'=>'LOCALHOST','prijmeni'=>'Test','narozeni'=>$date]);
            self::assertSame(PERSON_MATCH_SIMILARITY,$match['level']);self::assertSame(0,$match['exact_count']);
            self::assertSame([1,2],array_column($match['candidates'],'id'));
        }
        self::assertNull(personMatchV1BirthDate('1900-01-01'));
        self::assertSame('1900-01-01',personMatchV1Date('1900-01-01'),'General date parser must preserve archival dates');
    }

    public function testLegacyPaymentSignalsNeverChangeMembershipAndProjectionDoesNotWrite(): void
    {
        $pdo=$this->database();$before=$pdo->query('SELECT * FROM sportovci')->fetchAll();
        self::assertSame('aktivni',sportovecStatusCompute($before[0])['status']);
        self::assertSame('cekajici',sportovecStatusCompute($before[1])['status']);
        self::assertSame('review',sportovecStatusCompute($before[1])['verification']);
        sportovecStatusUpdate($pdo,2,array_replace($before[1],['kis_platebne_aktivni'=>0,'kis_neuhrazeno'=>999]));
        self::assertSame($before,$pdo->query('SELECT * FROM sportovci')->fetchAll());
        $summary=personStateSummary($pdo,1,'2026-10-22');
        self::assertCount(1,$summary['rosters'],'Two paths to same team count once');
        self::assertSame([true,false],array_column($summary['relations'],'access_active'));
        self::assertStringContainsString('nepotvrzuje bezdlužnost',personStateSummaryHtml($summary));
    }

    public function testMembershipDecisionPreservesRelationshipsMoneyAndPermanentRoster(): void
    {
        $pdo=$this->database();$old=$pdo->query('SELECT * FROM sportovci WHERE id=1')->fetch();
        $relations=$pdo->query('SELECT * FROM account_person_roles')->fetchAll();$rosters=$pdo->query('SELECT * FROM club_roster_members')->fetchAll();$payments=$pdo->query('SELECT * FROM payments')->fetchAll();
        sportovecMembershipDecide($pdo,1,'neaktivni','Člen požádal o přerušení členství.',7,sportovecMembershipRevision($old));
        self::assertSame('neaktivni',$pdo->query('SELECT stav_clenstvi FROM sportovci WHERE id=1')->fetchColumn());
        self::assertSame($relations,$pdo->query('SELECT * FROM account_person_roles')->fetchAll());self::assertSame($rosters,$pdo->query('SELECT * FROM club_roster_members')->fetchAll());self::assertSame($payments,$pdo->query('SELECT * FROM payments')->fetchAll());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());
        try {sportovecMembershipDecide($pdo,1,'aktivni','Stará stránka nesmí přepsat rozhodnutí.',7,sportovecMembershipRevision($old));self::fail('Stale membership');}
        catch(RuntimeException){self::assertSame('neaktivni',$pdo->query('SELECT stav_clenstvi FROM sportovci WHERE id=1')->fetchColumn());}
    }

    public function testReviewRetainsRecordedMembershipAndAuditFailureRollsBack(): void
    {
        $pdo=$this->database();$old=$pdo->query('SELECT * FROM sportovci WHERE id=1')->fetch();
        sportovecMembershipDecide($pdo,1,'review','Původní podklad je třeba znovu ověřit.',7,sportovecMembershipRevision($old));
        $fresh=$pdo->query('SELECT * FROM sportovci WHERE id=1')->fetch();self::assertSame('aktivni',$fresh['stav_clenstvi']);self::assertSame(0,(int)$fresh['stav_manualni']);
        $pdo->exec("CREATE TRIGGER fail_membership_audit BEFORE INSERT ON sportovec_history BEGIN SELECT RAISE(ABORT,'audit failure'); END");
        try {sportovecMembershipDecide($pdo,1,'archiv','Pokus bez dostupného auditního záznamu.',7,sportovecMembershipRevision($fresh));self::fail('Audit required');}
        catch(PDOException){self::assertSame($fresh,$pdo->query('SELECT * FROM sportovci WHERE id=1')->fetch());}
    }
}
