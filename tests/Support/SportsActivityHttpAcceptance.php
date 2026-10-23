<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);require $root.'/config.php';
if(DB_NAME!=='evidence_b1'||DB_HOST!=='127.0.0.1;port=33426'||rtrim((string)APP_BASE_URL,'/')!=='http://127.0.0.1:18086')throw new RuntimeException('Synthetic sandbox required.');
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Synthetic descriptor required.');
session_save_path($runtime.'/sessions');require_once $root.'/includes/auth_session.php';require_once $root.'/csrf_helper.php';require_once $root.'/includes/sports_activity.php';
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$actualDir=strtolower(rtrim(str_replace('\\','/',(string)$pdo->query('SELECT @@datadir')->fetchColumn()),'/'));$expectedDir=strtolower(rtrim(str_replace('\\','/',$runtime.'/mysql'),'/'));
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==33426||$actualDir!==$expectedDir)throw new RuntimeException('Owned sandbox database required.');
$run='LOCALHOST NEPLATIT R28 '.bin2hex(random_bytes(6));$checks=[];
function sahCheck(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL '.$label);$GLOBALS['checks'][]=['scenario'=>$label,'status'=>'PASS'];}
function sahInsert(string $table,array $data):int {$q=$GLOBALS['pdo']->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')');$q->execute(array_values($data));return(int)$GLOBALS['pdo']->lastInsertId();}
function sahSession(int $trainer):array {session_id('');app_session_start();$_SESSION=[];auth_session_bind_trainer($trainer,1);auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$trainer);app_session_mark_authenticated();$s=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $s;}
function sahRequest(array &$session,?array $post=null,string $route='sports_activity_admin.php'):array {$c=curl_init('http://127.0.0.1:18086/'.$route);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$session['cookie'],CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$session):int{if(preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$m))$session['cookie']=$m[1];return strlen($line);}]);if($post!==null){$post+=['csrf_token'=>$session['csrf']];curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$error=curl_error($c);curl_close($c);if(!is_string($body)||$status===0||$status>=500)throw new RuntimeException('HTTP '.$status.' '.$error.' '.substr((string)$body,0,500));return ['status'=>$status,'body'=>$body];}
foreach(['sports_lead','coach','finance_manager'] as $role){$id=sahInsert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);sahInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$role,'is_default'=>1]);$actors[$role]=$id;$sessions[$role]=sahSession($id);}
$person=sahInsert('sportovci',['jmeno'=>$run,'prijmeni'=>'Syntetický účastník','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'']);
$event=sahInsert('club_events',['code'=>'R28-'.bin2hex(random_bytes(8)),'event_type'=>'race','name'=>$run,'description_plain'=>$run,'audience_label'=>'SYNTHETIC ONLY','capacity'=>10,'pricing_policy'=>'free','currency'=>'CZK','status'=>'draft','created_by_trainer_id'=>$actors['sports_lead'],'activity_kind'=>'race','planning_status'=>'completed']);
$lead=$sessions['sports_lead'];$coach=$sessions['coach'];$finance=$sessions['finance_manager'];$base=['action'=>'participation','person_id'=>$person,'event_id'=>$event,'expected_version'=>'0','stage'=>'nominated','actual_date'=>'','result_status'=>'','reason'=>$run,'source_reference'=>$run.' papírová listina','confirmed'=>'1','request_key'=>'sport-http-'.bin2hex(random_bytes(16)),'from'=>'2026-01-01','to'=>'2026-12-31'];
sahCheck(sahRequest($lead)['status']===200,'Sports workspace GET is read-only');
sahCheck(sahRequest($finance,$base)['status']===403,'Finance workspace cannot mutate actual sports evidence');
sahCheck(sahRequest($coach,$base)['status']===403,'Coach without explicit scope cannot write foreign athlete');
sahCheck(sahRequest($lead,['csrf_token'=>'forged']+$base)['status']===403&&sportsEventCurrent($pdo,$person)===[],'Forged CSRF writes nothing');
sahCheck(sahRequest($lead,['confirmed'=>'0']+$base)['status']===422&&sportsEventCurrent($pdo,$person)===[],'Actual evidence requires explicit confirmation');
sahCheck(sahRequest($lead,$base)['status']===303,'Nomination recorded through HTTP');
sahCheck(sahRequest($lead,$base)['status']===303&&count(sportsEventCurrent($pdo,$person))===1,'Repeated nomination returns same version');
sahCheck(sportsActivitySummary($pdo,$person,'2026-01-01','2026-12-31')['race_count']===0,'Completed calendar planning and nomination do not imply start');
$accepted=['stage'=>'accepted','expected_version'=>'1','request_key'=>'accepted-'.$base['request_key']]+$base;sahCheck(sahRequest($lead,$accepted)['status']===303,'Accepted nomination is separate factual stage');
$registered=['stage'=>'registered','expected_version'=>'2','request_key'=>'registered-'.$base['request_key']]+$base;sahCheck(sahRequest($lead,$registered)['status']===303&&sportsActivitySummary($pdo,$person,'2026-01-01','2026-12-31')['race_count']===0,'External registration remains separate from start');
$actual=['stage'=>'started','result_status'=>'DNF','actual_date'=>'2026-01-10','expected_version'=>'3','request_key'=>'actual-'.$base['request_key']]+$base;
sahCheck(sahRequest($lead,['actual_date'=>'2099-01-01']+$actual)['status']===422,'Future actual date rejected');
sahCheck(sahRequest($lead,['result_status'=>'DNS']+$actual)['status']===422,'DNS cannot count as a start');
sahCheck(sahRequest($lead,$actual)['status']===303,'Actual DNF start recorded after evidence check');
$summary=sportsActivitySummary($pdo,$person,'2026-01-01','2026-12-31');sahCheck($summary['race_count']===1&&$summary['activities'][0]['result_value']===null,'One DNF start counted, unknown value remains null');
$export=sahRequest($lead,null,'sports_activity_export.php?person_id='.$person.'&from=2026-01-01&to=2026-12-31');sahCheck($export['status']===200&&$export['body']===sportsActivityCsv($summary),'HTTP CSV uses exactly the canonical summary and source revision');
$statement=['action'=>'statement','person_id'=>$person,'from'=>'2026-01-01','to'=>'2026-12-31','confirmed'=>'1'];sahCheck(sahRequest($lead,$statement)['status']===303,'Verified period frozen as a versioned statement');
$q=$pdo->prepare('SELECT * FROM sports_activity_statements WHERE sportovec_id=?');$q->execute([$person]);$saved=$q->fetch();$id=(int)$saved['id'];
sahCheck(sahRequest($lead,$statement)['status']===303,'Repeated period handover deduplicates immutable statement');
sahCheck(sahRequest($lead,['expected_version'=>'2','request_key'=>'stale-'.$base['request_key']]+$actual)['status']===422,'Stale form cannot overwrite newer actual revision');
$cancel=['stage'=>'cancelled','expected_version'=>'4','request_key'=>'cancel-'.$base['request_key']]+$base;sahCheck(sahRequest($lead,$cancel)['status']===303&&sportsActivitySummary($pdo,$person,'2026-01-01','2026-12-31')['race_count']===0,'Correction removes actual count without destroying prior version');
$frozen=sahRequest($lead,null,'sports_activity_export.php?statement_id='.$id);sahCheck($frozen['status']===200&&$frozen['body']===sportsActivityCsv(json_decode($saved['snapshot_json'],true))&&str_contains($frozen['body'],'DNF'),'Export of old statement retains original DNF after correction');
$view=sahRequest($lead,null,'sports_activity_admin.php?person_id='.$person.'&from=2026-01-01&to=2026-12-31');sahCheck(str_contains($view['body'],'ZMĚNA K POSOUZENÍ'),'Correction visibly flags statement for review');
sahCheck(sahRequest($coach,null,'sports_activity_export.php?statement_id='.$id)['status']===403,'Foreign coach cannot download another athlete statement directly');
$pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='sports_lead'")->execute([$actors['sports_lead']]);
sahCheck(sahRequest($lead,null,'sports_activity_export.php?statement_id='.$id)['status']===403,'Revoked stale lead session cannot download private statement');
echo json_encode(['run'=>$run,'checks'=>count($checks),'results'=>$checks,'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
