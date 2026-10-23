<?php
declare(strict_types=1);

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/paper_registration_labels.php';

final class ClubRegistrationPagesTest extends TestCase
{
    /** Exercise real controller and form markup, with no database or writes. */
    private function admission(string $mode='error',array $overrides=[]):array
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/club_program_admissions_admin.php');
        $controller=strstr($source,'$query=trim',true);
        self::assertIsString($controller);
        $controller=preg_replace("~require_once __DIR__\\.[^;]+;~",'',$controller);
        $controller=str_replace("staffRequireActivePosition('registrar');",'',$controller);
        $controller=str_replace('header(','captureHeader(',$controller);
        preg_match('~<form method="post">.*?</form>~s',$source,$form);
        self::assertArrayHasKey(0,$form);
        $post=array_replace(['sportovec_id'=>'123','offer_id'=>'16','payer_account_id'=>'0','offline_payer_name'=>'TEST Plátce',
            'offline_payer_relation'=>'guardian','starts_on'=>'2026-10-15','paper_ids'=>['20'],'source_enrollment_id'=>'9',
            'financial_resolution'=>'SIMULACE rozhodnutí 9','reason'=>'SIMULACE pozdější vstup','confirmed'=>'1',
            'request_key'=>'admission-test-original-key','expected_amount_minor'=>'120000'],$overrides);
        $code='$mode='.var_export($mode,true).';$_POST='.var_export($post,true).';'.<<<'PHP'
$_SERVER['REQUEST_METHOD']=$mode==='get'?'GET':'POST';
$_GET=['person_id'=>'999','offer_id'=>'88'];$_SESSION=['trener_id'=>326];$pdo=new stdClass();
$GLOBALS['html']='';$GLOBALS['attempt']=false;$GLOBALS['redirect']=null;
function csrf_verify(string $token):bool{return $GLOBALS['mode']!=='csrf';}
function csrf_field():string{return '<input type="hidden" name="csrf_token" value="synthetic">';}
function captureHeader(string $value,bool $replace=true,int $status=0):void{$GLOBALS['redirect']=$value;$GLOBALS['redirect_status']=$status;}
function clubAdmissionPlace(mixed $pdo,int $actor,array $input):array{$GLOBALS['attempt']=true;if($GLOBALS['mode']==='success')return ['id'=>44];throw new DomainException('Chybí přijatá papírová přihláška pro tento program.');}
register_shutdown_function(static function():void{echo json_encode(['html'=>$GLOBALS['html'],'error'=>$GLOBALS['error']??'','attempt'=>$GLOBALS['attempt'],'redirect'=>$GLOBALS['redirect'],'redirect_status'=>$GLOBALS['redirect_status']??null,'status'=>http_response_code(),'person'=>$GLOBALS['person']??null,'offer'=>$GLOBALS['offerId']??null],JSON_THROW_ON_ERROR);});
PHP;
        $code.='eval('.var_export(substr($controller,5),true).');';
        $code.= <<<'PHP'
$amount=120000;$offer=['starts_on'=>'2026-10-01','ends_on'=>'2099-12-31'];
$payers=[['id'=>327,'jmeno'=>'UAT','prijmeni'=>'Online','email'=>'synthetic@example.invalid']];
$papers=[['id'=>20,'document_title'=>'Test souhlas','version_label'=>'v1','signed_on'=>'2026-10-01'],['id'=>21,'document_title'=>'Test přihláška','version_label'=>'v1','signed_on'=>'2026-10-01']];
ob_start();
PHP;
        $code.='eval('.var_export('?>'.$form[0],true).');$GLOBALS["html"]=ob_get_clean();';
        $process=proc_open([PHP_BINARY,'-r',$code],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        self::assertIsResource($process);fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$stderr);self::assertSame('',$stderr);
        return json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
    }

    private function xpath(string $html):DOMXPath
    {
        $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
        try{$doc->loadHTML('<?xml encoding="UTF-8">'.$html);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        return new DOMXPath($doc);
    }

    public function testMissingApplicationPreservesOfflineDraftAndOnlySelectedPapers():void
    {
        $r=$this->admission();self::assertSame(422,$r['status']);self::assertTrue($r['attempt']);self::assertNull($r['redirect']);
        self::assertSame(123,$r['person']);self::assertSame(16,$r['offer']);
        $x=$this->xpath($r['html']);
        foreach(['sportovec_id'=>'123','offer_id'=>'16','offline_payer_name'=>'TEST Plátce','starts_on'=>'2026-10-15',
            'source_enrollment_id'=>'9','financial_resolution'=>'SIMULACE rozhodnutí 9','reason'=>'SIMULACE pozdější vstup','request_key'=>'admission-test-original-key'] as $name=>$value){
            self::assertSame($value,$x->evaluate('string(//input[@name="'.$name.'"]/@value)'),$name);
        }
        self::assertSame('0',$x->evaluate('string(//select[@name="payer_account_id"]/option[@selected]/@value)'));
        self::assertSame('guardian',$x->evaluate('string(//select[@name="offline_payer_relation"]/option[@selected]/@value)'));
        self::assertSame('20',$x->evaluate('string(//input[@name="paper_ids[]"][@checked]/@value)'));
        self::assertSame(1,$x->query('//input[@name="paper_ids[]"][@checked]')->length);
        self::assertSame(1,$x->query('//input[@name="confirmed"][@checked]')->length);
    }

    public function testOnlineSelectionAndUncheckedConfirmationArePreserved():void
    {
        $r=$this->admission('error',['payer_account_id'=>'327','confirmed'=>'','paper_ids'=>[]]);$x=$this->xpath($r['html']);
        self::assertSame('327',$x->evaluate('string(//select[@name="payer_account_id"]/option[@selected]/@value)'));
        self::assertSame(0,$x->query('//input[@checked]')->length);
    }

    public function testEscapingAndMalformedValuesCannotCreateAttributesOrWarnings():void
    {
        $payload='TEST " autofocus onfocus="alert(1)<script>';
        $r=$this->admission('error',['offline_payer_name'=>$payload,'reason'=>$payload,'starts_on'=>$payload,'financial_resolution'=>$payload,
            'source_enrollment_id'=>$payload,'request_key'=>$payload,'paper_ids'=>[['nested'],20],'offline_payer_relation'=>['bad']]);
        $x=$this->xpath($r['html']);
        foreach(['offline_payer_name','reason','starts_on','financial_resolution','source_enrollment_id'] as $name)
            self::assertSame($payload,$x->evaluate('string(//input[@name="'.$name.'"]/@value)'));
        self::assertSame(0,$x->query('//@onfocus|//@autofocus|//script')->length);
        self::assertMatchesRegularExpression('/^admission-[a-f0-9]{32}$/',$x->evaluate('string(//input[@name="request_key"]/@value)'));
    }

    public function testCsrfErrorRetainsDraftWithoutCallingAdmissionWriter():void
    {
        $r=$this->admission('csrf');self::assertSame(403,$r['status']);self::assertFalse($r['attempt']);
        self::assertSame('TEST Plátce',$this->xpath($r['html'])->evaluate('string(//input[@name="offline_payer_name"]/@value)'));
    }

    public function testSuccessfulPostRedirectsAndFreshGetDoesNotRestoreOldDraft():void
    {
        $r=$this->admission('success');self::assertSame('Location: club_program_admissions_admin.php?saved=44',$r['redirect']);
        self::assertSame(303,$r['redirect_status']);self::assertSame('',$r['html']);
        $get=$this->admission('get');self::assertFalse($get['attempt']);self::assertSame(999,$get['person']);self::assertSame(88,$get['offer']);
        $x=$this->xpath($get['html']);foreach(['offline_payer_name','reason','source_enrollment_id','financial_resolution'] as $name)
            self::assertSame('',$x->evaluate('string(//input[@name="'.$name.'"]/@value)'));
        self::assertSame(0,$x->query('//input[@checked]|//option[@selected]')->length);
        self::assertNotSame('admission-test-original-key',$x->evaluate('string(//input[@name="request_key"]/@value)'));
    }

    public function testLegacyEmptyTitlesStillDistinguishPurposeAndExactVersion():void
    {
        $base=['id'=>41,'title_plain'=>'','scope_type'=>'club_program','scope_key'=>'program:16','terms_version'=>'v1'];
        $cancel=paperRegistrationTermLabel($base+['consent_purpose'=>'program_cancellation']);
        $consent=paperRegistrationTermLabel(array_replace($base,['id'=>42,'title_plain'=>'   ','consent_purpose'=>'program_consent']));
        self::assertSame('Storno podmínky programu · v1 · club_program program:16 · #41',$cancel);
        self::assertSame('Souhlas s účastí v programu · v1 · club_program program:16 · #42',$consent);
        self::assertStringContainsString('Podmínky bez uvedeného typu',paperRegistrationTermLabel(['id'=>43]));
        self::assertStringContainsString('Typ: custom-consent',paperRegistrationTermLabel(['id'=>44,'consent_purpose'=>'custom-consent']));
    }

    public function testPaperPageRendersPurposeLabelsEscapedAndKeepsSelectedId():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/paper_registrations_admin.php');
        self::assertStringContainsString('SELECT id,scope_type,scope_key,consent_purpose,terms_version,title_plain FROM',$source);
        preg_match('~<select id="term".*?</select>~s',$source,$markup);self::assertArrayHasKey(0,$markup);
        $terms=[['id'=>41,'title_plain'=>'<script>TEST</script>','scope_type'=>'club_program','scope_key'=>'program:16','terms_version'=>'v1','consent_purpose'=>'program_cancellation'],
            ['id'=>42,'title_plain'=>'','scope_type'=>'club_program','scope_key'=>'program:16','terms_version'=>'v1','consent_purpose'=>'program_consent']];
        $value=static fn(string $key):string=>'42';
        // Evaluate the real escaped option template without loading authentication/database bootstrap.
        $htmlCode=str_replace('paperH(',"htmlspecialchars(",$markup[0]);
        ob_start();try{eval('?>'.$htmlCode);$html=(string)ob_get_contents();}finally{ob_end_clean();}
        $x=$this->xpath($html);self::assertSame('42',$x->evaluate('string(//option[@selected]/@value)'));
        self::assertSame(0,$x->query('//script')->length);
        self::assertStringContainsString('Storno podmínky programu',$x->evaluate('string(//option[@value="41"])'));
        self::assertStringContainsString('Souhlas s účastí v programu',$x->evaluate('string(//option[@value="42"])'));
    }

    public function testMissingRequiredPaperNamesItsPurposeWithoutChangingCoverageValidation():void
    {
        $root=dirname(__DIR__,2);
        $source=(string)file_get_contents($root.'/includes/club_program_admission.php');
        $start=strpos($source,'function clubAdmissionPaperEvidence(');$end=strpos($source,'function clubAdmissionPlace(');
        self::assertNotFalse($start);self::assertNotFalse($end);
        $code='require '.var_export($root.'/includes/paper_registration_labels.php',true).';';
        $code.= <<<'PHP'
function paperRegistrationGet(PDO $pdo,int $id,bool $lock=false):array{return ['id'=>$id,'sportovec_id'=>123,'scope_type'=>'program','scope_id'=>16,'state'=>'accepted','signer_relation'=>'guardian','kind'=>'application','term_version_id'=>null];}
$pdo=new class extends PDO {public function __construct(){}};
PHP;
        $code.=substr($source,$start,$end-$start);
        $code.= <<<'PHP'
$messages=[];
foreach(['program_cancellation','program_consent'] as $purpose){
 try{clubAdmissionPaperEvidence($pdo,123,16,[['id'=>41,'title_plain'=>'','scope_type'=>'club_program','scope_key'=>'program:16','terms_version'=>'v1','consent_purpose'=>$purpose]],[20]);$messages[]=null;}
 catch(DomainException $e){$messages[]=$e->getMessage();}
}
echo json_encode($messages,JSON_THROW_ON_ERROR);
PHP;
        $process=proc_open([PHP_BINARY,'-r',$code],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        self::assertIsResource($process);fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$stderr);self::assertSame('',$stderr);
        $messages=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Chybí přijatý papírový souhlas s aktuální verzí: Storno podmínky programu',$messages[0]);
        self::assertStringContainsString('Souhlas s účastí v programu',$messages[1]);
        self::assertStringContainsString('#41',$messages[0]);
        self::assertStringNotContainsString('program_cancellation',$messages[0]);
    }
}
