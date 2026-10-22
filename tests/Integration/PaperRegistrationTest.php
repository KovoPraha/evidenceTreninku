<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/staff_workspaces.php';
require_once dirname(__DIR__,2).'/includes/paper_registration.php';
require_once dirname(__DIR__,2).'/includes/account_person_role.php';
require_once dirname(__DIR__,2).'/includes/family_portal.php';

final class PaperRegistrationTest extends TestCase
{
    private array $priorSession;
    protected function setUp(): void { $this->priorSession=$_SESSION??[]; $_SESSION=['trener_id'=>7,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar']; }
    protected function tearDown(): void { $_SESSION=$this->priorSession; }

    public function testPaperMemberWithoutAccountPreservesUnknownVersionAndRealSignature(): void
    {
        $pdo=$this->database(); $id=\paperRegistrationRecord($pdo,$this->input());
        $row=\paperRegistrationGet($pdo,$id);
        self::assertSame('verze neověřena',$row['version_label']);
        self::assertNull($row['term_version_id']);
        self::assertSame('2020-01-02',$row['signed_on']);
        self::assertSame('recorded',$row['state']);
        self::assertSame(7,(int)$row['recorded_by']);
        self::assertNotSame($row['signed_on'],substr($row['recorded_at'],0,10));
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        self::assertSame('neclen',$pdo->query('SELECT stav_clenstvi FROM sportovci WHERE id=10')->fetchColumn());
        \paperRegistrationDecide($pdo,$id,'accepted','Papír ověřen osobně.');
        self::assertSame('accepted',\paperRegistrationGet($pdo,$id)['state']);
        self::assertSame(0,(int)$pdo->query('SELECT email_overeno FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertFalse(\accountPersonCanManage($pdo,1,10));
    }

    public function testVersionSnapshotSurvivesNewVersionAndCorrectionLeavesOriginalUntouched(): void
    {
        $pdo=$this->database(); $id=\paperRegistrationRecord($pdo,$this->input()+['term_version_id'=>1]);
        $original=$pdo->query('SELECT * FROM club_paper_records WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Původní podepsaný text.',$original['text_snapshot']);
        $pdo->exec("INSERT INTO club_event_term_versions VALUES(2,'athlete_registration','all',NULL,'v2','Nový text.','Nový dokument')");
        $fixed=\paperRegistrationRecord($pdo,array_replace($this->input(),['term_version_id'=>1,'signer_name'=>'Opravené jméno','replaces_id'=>$id]));
        self::assertSame($original,$pdo->query('SELECT * FROM club_paper_records WHERE id='.$id)->fetch(PDO::FETCH_ASSOC));
        self::assertSame('superseded',\paperRegistrationGet($pdo,$id)['state']);
        self::assertSame($id,(int)\paperRegistrationGet($pdo,$fixed)['replaces_id']);
        self::assertSame('v1',\paperRegistrationGet($pdo,$fixed)['version_label']);
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_paper_record_events')->fetchColumn());
        $this->expectException(\DomainException::class);
        \paperRegistrationRecord($pdo,$this->input()+['replaces_id'=>$id]);
    }

    public function testRevocationIsAppendOnlyAndCannotBeReaccepted(): void
    {
        $pdo=$this->database(); $id=\paperRegistrationRecord($pdo,$this->input());
        $before=$pdo->query('SELECT * FROM club_paper_records')->fetch(PDO::FETCH_ASSOC);
        \paperRegistrationDecide($pdo,$id,'accepted','Ověřeno.');
        \paperRegistrationDecide($pdo,$id,'revoked','Odvolání předané osobně.');
        self::assertSame($before,$pdo->query('SELECT * FROM club_paper_records')->fetch(PDO::FETCH_ASSOC));
        self::assertSame('revoked',\paperRegistrationGet($pdo,$id)['state']);
        $this->expectException(\DomainException::class);
        \paperRegistrationDecide($pdo,$id,'accepted','Přepsání nesmí projít.');
    }

    public function testCrossPurposeVersionAndCorrectionAreRejectedWithoutWrites(): void
    {
        $pdo=$this->database();
        foreach ([['term_version_id'=>999],['scope_type'=>'program','scope_id'=>4,'term_version_id'=>1],['signed_on'=>'2020-02-31'],['signed_on'=>'2999-01-01'],['original_location'=>''],['sportovec_id'=>999],['reason'=>'']] as $invalid) {
            try { \paperRegistrationRecord($pdo,array_replace($this->input(),$invalid)); self::fail('Invalid evidence accepted.'); }
            catch (\InvalidArgumentException) { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_paper_records')->fetchColumn()); }
        }
        $id=\paperRegistrationRecord($pdo,$this->input());
        try { \paperRegistrationRecord($pdo,array_replace($this->input(),['sportovec_id'=>11,'replaces_id'=>$id])); self::fail(); }
        catch (\DomainException) { self::assertSame('recorded',\paperRegistrationGet($pdo,$id)['state']); }
    }

    public function testAuditFailureRollsBackWholeRecord(): void
    {
        $pdo=$this->database();
        $pdo->exec("CREATE TRIGGER reject_paper_audit BEFORE INSERT ON club_paper_record_events BEGIN SELECT RAISE(FAIL, 'synthetic failure'); END");
        try { \paperRegistrationRecord($pdo,$this->input()); self::fail(); }
        catch (\PDOException) { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_paper_records')->fetchColumn()); }
        self::assertFalse($pdo->inTransaction());
    }

    public function testProgramAndEventTermsCannotLeakIntoUnrelatedPurposes(): void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_event_term_versions VALUES(2,'club_program','program:4',NULL,'kurz-v1','Kurz','Kurz'),(3,'club_event','event:5',5,'akce-v1','Akce','Akce'),(4,'club_registration','all-programs',NULL,'vlastni-v1','Vlastní souhlas','Vlastní souhlas')");
        foreach ([['program',4,2],['event',5,3],['program',4,4]] as [$scope,$scopeId,$termId]) {
            $id=\paperRegistrationRecord($pdo,array_replace($this->input(),['kind'=>'consent','scope_type'=>$scope,'scope_id'=>$scopeId,'term_version_id'=>$termId]));
            self::assertSame($termId,(int)\paperRegistrationGet($pdo,$id)['term_version_id']);
        }
        foreach ([['membership',0,4],['event',5,2],['program',4,3]] as [$scope,$scopeId,$termId]) {
            try { \paperRegistrationRecord($pdo,array_replace($this->input(),['kind'=>'consent','scope_type'=>$scope,'scope_id'=>$scopeId,'term_version_id'=>$termId])); self::fail(); }
            catch (\InvalidArgumentException) { self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_paper_records')->fetchColumn()); }
        }
    }

    public function testInvalidIdentifierCannotSilentlyBecomeUnknownVersionOrNewRecord(): void
    {
        $pdo=$this->database();
        foreach (['term_version_id','replaces_id','scope_id'] as $key) {
            foreach ([-1,'invalid',[],1.5] as $value) {
                try { \paperRegistrationRecord($pdo,array_replace($this->input(),[$key=>$value])); self::fail(); }
                catch (\InvalidArgumentException) { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_paper_records')->fetchColumn()); }
            }
        }
    }

    public function testGuardianAndOtherStaffCannotRecordDecideOrDownloadEvenWithKnownId(): void
    {
        $pdo=$this->database(); $id=\paperRegistrationRecord($pdo,$this->input());
        foreach ([['verejny_uzivatel_id'=>2],['trener_id'=>7,'staff_positions'=>['coach'],'staff_active_position'=>'coach'],[]] as $session) {
            $_SESSION=$session;
            foreach ([fn()=>\paperRegistrationRecord($pdo,$this->input()),fn()=>\paperRegistrationDecide($pdo,$id,'accepted','Zkouška'),fn()=>\paperRegistrationDownload($pdo,$id)] as $call) {
                try { $call(); self::fail('Access unexpectedly granted.'); }
                catch (\DomainException) { self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_paper_record_events')->fetchColumn()); }
            }
        }
    }

    public function testGuardianRevocationImmediatelyRemovesOneChildWithoutAffectingOtherParentOrSelf(): void
    {
        $pdo=$this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1');
        \accountPersonRoleApprove($pdo,1,10,'guardian',7,'Doložený první rodič.');
        $second=\accountPersonRoleApprove($pdo,1,11,'guardian',7,'Doložené druhé dítě.');
        \accountPersonRoleApprove($pdo,2,10,'guardian',7,'Doložený druhý rodič.');
        self::assertCount(2,\familyPortalAuthorizedPeople($pdo,1));
        \accountPersonRoleRevoke($pdo,$second['relation_id'],7,'Odvolané zastupování druhého dítěte.');
        self::assertFalse(\familyPortalCanViewPerson($pdo,1,11));
        self::assertFalse(\accountPersonCanManage($pdo,1,11));
        self::assertTrue(\familyPortalCanViewPerson($pdo,1,10));
        self::assertTrue(\familyPortalCanViewPerson($pdo,2,10));
        $this->expectException(\FamilyPortalAccessDenied::class);
        \familyPortalPersonOverview($pdo,1,11);
    }

    public function testMigrationIsRepeatableAndExistingRecordsSurvive(): void
    {
        $pdo=$this->database(); $id=\paperRegistrationRecord($pdo,$this->input());
        $migration=require dirname(__DIR__,2).'/migrations/20261022211000_paper_registrations.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame($id,(int)\paperRegistrationGet($pdo,$id)['id']);
    }

    private function input(): array { return ['sportovec_id'=>10,'kind'=>'application','scope_type'=>'membership','document_title'=>'Papírová přihláška LOCALHOST','signer_name'=>'LOCALHOST Rodič','signer_relation'=>'guardian','signed_on'=>'2020-01-02','original_location'=>'Skříň LOCALHOST, složka NEPLATIT','reason'=>'Osobně přijatý papír.']; }
    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST Registrář')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'LOCALHOST','Dítě A','2015-01-01','neclen'),(11,'LOCALHOST','Dítě B','2016-01-01','neclen')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,0),(2,1,1)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT,created_by_trainer_id INTEGER,approved_by_trainer_id INTEGER,decision_note TEXT,updated_at TEXT, UNIQUE(account_id,sportovec_id))');
        $pdo->exec('CREATE TABLE account_person_role_events(id INTEGER PRIMARY KEY AUTOINCREMENT,relation_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,relation_role TEXT,note TEXT)');
        $pdo->exec('CREATE TABLE club_programs(id INTEGER PRIMARY KEY)'); $pdo->exec('INSERT INTO club_programs VALUES(4)');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY)'); $pdo->exec('INSERT INTO club_events VALUES(5)');
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY,scope_type TEXT,scope_key TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,title_plain TEXT)');
        $pdo->exec("INSERT INTO club_event_term_versions VALUES(1,'athlete_registration','all',NULL,'v1','Původní podepsaný text.','Původní dokument')");
        $migration=require dirname(__DIR__,2).'/migrations/20261022211000_paper_registrations.php'; $migration['up']($pdo);
        return $pdo;
    }
}
