<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config.php';
if(!defined('DB_HOST')||DB_HOST!=='127.0.0.1;port=33426'||DB_NAME!=='evidence_b1'||rtrim((string)APP_BASE_URL,'/')!=='http://127.0.0.1:18086'){fwrite(STDERR,"Povolena je pouze izolovaná syntetická zkouška B1.\n");exit(2);}
require_once dirname(__DIR__).'/includes/club_migration.php';
$args=[];foreach(array_slice($argv,1) as $arg){if(str_starts_with($arg,'--')){[$k,$v]=array_pad(explode('=',substr($arg,2),2),2,'');$options[$k]=$v;}else$args[]=$arg;}
try{
 $p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);clubMigrationGuard($p);
 $actor=(int)($options['actor']??0);$position=(string)($options['position']??'');$_SESSION=['trener_id'=>$actor,'staff_positions'=>[$position],'staff_active_position'=>$position];clubMigrationActor($p,$actor);
 $command=$args[0]??'';
 if($command==='stage'){$id=clubMigrationStageFile($p,(string)($args[1]??''),$actor);$out=clubMigrationReport($p,$id);}
 elseif($command==='report')$out=clubMigrationReport($p,(int)($args[1]??0));
 elseif($command==='review'){$proposal=[];if(isset($options['proposal'])){$base=realpath(getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022/private/imports');$file=realpath($options['proposal']);if(!$base||!$file||!str_starts_with(strtolower(str_replace('\\','/',$file)),strtolower(str_replace('\\','/',$base)).'/')||filesize($file)>100000)throw new DomainException('Návrh musí být v soukromé složce importů.');$proposal=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);}$i=clubMigrationReview($p,(int)($args[1]??0),(int)($options['revision']??-1),(string)($options['decision']??''),$proposal,(string)($options['source-hash']??''),$actor,(string)($options['reason']??''));$out=clubMigrationReport($p,(int)$i['batch_id']);}
 elseif($command==='apply')$out=clubMigrationApply($p,(int)($args[1]??0),(string)($options['plan-hash']??''),$actor,(int)($options['limit']??100));
 else throw new DomainException('Použití: stage SOUBOR | report DÁVKA | review POLOŽKA --revision=N --decision=approve|archive|quarantine --source-hash=SHA --proposal=SOUBOR --reason=TEXT | apply DÁVKA --plan-hash=SHA [--limit=N]; vždy --actor=ID --position=registrar|sports_lead|finance_manager.');
 echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
