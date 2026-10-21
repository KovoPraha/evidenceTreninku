<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/club_registration_consent_admin.php';

final class CustomClubConsentsTest extends TestCase
{
    private PDO $pdo;
    protected function setUp():void
    {
        $_SESSION=['trener_id'=>7,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];
        $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT);INSERT INTO treneri VALUES(7,'LOCALHOST Registrář')");
        $this->pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY);INSERT INTO verejni_uzivatele VALUES(10)');
        $this->pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY);INSERT INTO shop_order_items VALUES(100)');
        $this->pdo->exec("CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,actor_trainer_id INTEGER,actor_type TEXT,actor_id INTEGER,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))");
        foreach(['20261021150000_athlete_registration_text_drafts.php','20261021170000_registration_text_titles.php','20261021190000_custom_club_registration_consents.php'] as $file){$m=require dirname(__DIR__,2).'/migrations/'.$file;($m['up'])($this->pdo);($m['up'])($this->pdo);self::assertTrue(($m['verify'])($this->pdo));}
    }
    protected function tearDown():void{$_SESSION=[];}
    private function create(bool $required=true,string $title='LOCALHOST Vlastní souhlas'):array
    {
        return \clubConsentCreate($this->pdo,$title,'LOCALHOST Konečné znění souhlasu.','LOCALHOST Vytvoření',$required,true);
    }
    private function input(bool $acceptRequired=true):array
    {
        $terms=\clubConsentCurrent($this->pdo);$accepted=[];
        foreach($terms as $term)if($acceptRequired&&(int)$term['custom_required']===1)$accepted[(int)$term['id']]='1';
        return ['club_consent_manifest'=>\clubConsentManifest($terms),'club_consent_accepted'=>$accepted];
    }
    public function testRequiredOptionalHideAndRestorePreserveOriginalAnswers():void
    {
        $required=$this->create();$optional=$this->create(false,'LOCALHOST Volitelný');
        self::assertSame([],\clubConsentCurrent($this->pdo));
        \clubConsentPublish($this->pdo,$required['id'],$required['draft_id'],true);
        \clubConsentPublish($this->pdo,$optional['id'],$optional['draft_id'],true);
        $missing=$this->input(false);$this->pdo->beginTransaction();
        try{\clubConsentValidate($this->pdo,$missing);self::fail('Mandatory consent must be enforced on the server.');}
        catch(\InvalidArgumentException $e){self::assertStringContainsString('povinný souhlas',$e->getMessage());}finally{$this->pdo->rollBack();}
        $input=$this->input();$this->pdo->beginTransaction();$terms=\clubConsentValidate($this->pdo,$input);\clubConsentStore($this->pdo,100,10,$terms);$this->pdo->commit();
        self::assertSame([1,0],array_map('intval',$this->pdo->query('SELECT accepted FROM club_registration_consent_acceptances ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        $current=\clubConsentTerm($this->pdo,$required['id']);\clubConsentHide($this->pdo,$required['id'],(int)$current['id'],true);
        self::assertCount(1,\clubConsentCurrent($this->pdo));
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM club_registration_consent_acceptances')->fetchColumn());
        $hidden=\clubConsentTerm($this->pdo,$required['id']);
        $draft=\clubConsentSaveDraft($this->pdo,$required['id'],(int)$hidden['id'],'LOCALHOST Obnovený','LOCALHOST Nové znění.','Obnovení jako volitelný',false,true);
        \clubConsentPublish($this->pdo,$required['id'],$draft,true);
        self::assertCount(2,\clubConsentCurrent($this->pdo));
        $snapshot=$this->pdo->query('SELECT title_snapshot,text_snapshot,required_snapshot FROM club_registration_consent_acceptances ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('LOCALHOST Vlastní souhlas',$snapshot['title_snapshot']);
        self::assertSame('LOCALHOST Konečné znění souhlasu.',$snapshot['text_snapshot']);self::assertSame(1,(int)$snapshot['required_snapshot']);
    }
    public function testVersionOrVisibilityChangeRejectsStaleApplication():void
    {
        $c=$this->create();\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true);$input=$this->input();
        $current=\clubConsentTerm($this->pdo,$c['id']);\clubConsentHide($this->pdo,$c['id'],(int)$current['id'],true);
        $this->pdo->beginTransaction();
        try{\clubConsentValidate($this->pdo,$input);self::fail('Stale manifest must fail even if the last consent was hidden.');}
        catch(\InvalidArgumentException $e){self::assertStringContainsString('se změnily',$e->getMessage());}finally{$this->pdo->rollBack();}
    }
    public function testPublicationIsConfirmedIdempotentAndRejectsCompetingDraft():void
    {
        $c=$this->create();$current=\clubConsentTerm($this->pdo,$c['id']);
        $other=\clubConsentSaveDraft($this->pdo,$c['id'],(int)$current['id'],'LOCALHOST Jiný','LOCALHOST Jiný obsah','Jiný koncept',false,true);
        try{\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],false);self::fail('Confirmation is required.');}catch(\InvalidArgumentException){}
        $term=\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true);
        self::assertSame($term,\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true));
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
        try{\clubConsentPublish($this->pdo,$c['id'],$other,true);self::fail('Stale draft must not overwrite.');}catch(\InvalidArgumentException $e){self::assertStringContainsString('starší verze',$e->getMessage());}
        self::assertFalse($this->pdo->inTransaction());
    }
    public function testFailedPublicationRollsBackNewVersionAndPointer():void
    {
        $c=$this->create();$base=\clubConsentTerm($this->pdo,$c['id']);
        $this->pdo->exec("CREATE TRIGGER fail_publish BEFORE UPDATE ON club_registration_consents BEGIN SELECT RAISE(ABORT,'LOCALHOST failure'); END");
        try{\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true);self::fail('Injected failure must roll back.');}catch(\PDOException){}
        self::assertSame($base,\clubConsentTerm($this->pdo,$c['id']));
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM club_event_term_versions')->fetchColumn());
        self::assertNull($this->pdo->query('SELECT published_term_id FROM athlete_registration_text_drafts')->fetchColumn());
    }
    public function testOtherPositionsCannotCreatePublishOrHide():void
    {
        $c=$this->create();$base=\clubConsentTerm($this->pdo,$c['id']);
        foreach(array_diff(\staffPositionCodes(),['registrar']) as $position){$_SESSION['staff_active_position']=$position;$_SESSION['staff_is_superadmin']=true;
            foreach(['create','publish','hide'] as $action){try{if($action==='create')$this->create();elseif($action==='publish')\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true);else \clubConsentHide($this->pdo,$c['id'],(int)$base['id'],true);self::fail('Wrong position must fail.');}catch(\RuntimeException $e){self::assertStringContainsString('Registrář',$e->getMessage());}}
        }
    }
    public function testHtmlIsEscapedOptionalIsNotRequiredAndStaleAnswersAreNotCarriedForward():void
    {
        $c=$this->create(false,'LOCALHOST <script>alert(1)</script>');\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true);
        ob_start();\clubConsentFields($this->pdo,['club_consent_manifest'=>'old','club_consent_accepted'=>[(int)\clubConsentCurrent($this->pdo)[0]['id']=>'1']]);$html=(string)ob_get_clean();
        self::assertStringContainsString('&lt;script&gt;',$html);self::assertStringNotContainsString('<script>',$html);
        self::assertStringNotContainsString(' required',$html);self::assertStringNotContainsString(' checked',$html);
        self::assertStringContainsString('Volitelný',$html);
    }
    public function testEmptyRegistryNeedsNoExtraConfirmationAndSnapshotMustBeTransactional():void
    {
        $this->pdo->beginTransaction();self::assertSame([],\clubConsentValidate($this->pdo,[]));$this->pdo->commit();
        $c=$this->create();\clubConsentPublish($this->pdo,$c['id'],$c['draft_id'],true);
        $this->expectException(\LogicException::class);\clubConsentStore($this->pdo,100,10,\clubConsentCurrent($this->pdo));
    }
}
