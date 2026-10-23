<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$mode=$argv[1]??'--dry-run';
if(!in_array($mode,['--dry-run','--apply'],true)||count($argv)>2){fwrite(STDERR,"Usage: php refresh-club-program-offers.php [--dry-run|--apply]\n");exit(64);}
if($mode==='--apply'&&getenv('CLUB_PROGRAM_RECURRENCE_APPLY_ENABLED')!=='true'){
    fwrite(STDOUT,json_encode(['ok'=>false,'mode'=>'apply','reason'=>'apply_disabled'],JSON_THROW_ON_ERROR).PHP_EOL);exit(78);
}
$host=getenv('APP_HOST');
if(!is_string($host)||preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di',$host)!==1){fwrite(STDERR,"APP_HOST must be configured.\n");exit(64);}
$_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=(string)preg_replace('/:\d+$/','',$host);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/includes/club_program_recurrence.php';
foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS']as$name)if(!defined($name))throw new RuntimeException('Missing database configuration.');
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$lock=$pdo->query("SELECT GET_LOCK('kis.club_program.recurrence',0)")->fetchColumn();
if((int)$lock!==1){fwrite(STDOUT,json_encode(['ok'=>false,'mode'=>$mode,'reason'=>'locked'],JSON_THROW_ON_ERROR).PHP_EOL);exit(75);}
require_once dirname(__DIR__).'/includes/operational_jobs.php';
if($mode==='--apply')$jobRun=operationalJobStart($pdo,'program_offers');
try{
    $result=clubProgramRecurrenceRefresh($pdo,null,$mode==='--apply');
    $result=['ok'=>$result['errors']===[],'mode'=>$mode==='--apply'?'apply':'dry-run','database_connected'=>true]+$result;
    if(isset($jobRun))operationalJobFinish($pdo,$jobRun,$result['ok'],$result['ok']?'completed':'refresh_failed');
    fwrite(STDOUT,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){if(isset($jobRun))operationalJobFinish($pdo,$jobRun,false,'worker_exception');throw $error;}finally{$pdo->query("SELECT RELEASE_LOCK('kis.club_program.recurrence')");}
exit($result['ok']?0:1);
