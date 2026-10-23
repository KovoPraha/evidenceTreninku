<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__,2);require $root.'/config.php';require_once $root.'/includes/club_program_admission.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';$base=(string)($argv[1]??'');
if(DB_NAME!=='evidence_b1'||DB_HOST!=='127.0.0.1;port=33426'||!str_starts_with(str_replace('\\','/',$base),str_replace('\\','/',$runtime).'/r25race-'))throw new RuntimeException('Synthetic worker only.');
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Synthetic descriptor required.');
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==33426||strtolower(rtrim(str_replace('\\','/',(string)$pdo->query('SELECT @@datadir')->fetchColumn()),'/'))!==strtolower(str_replace('\\','/',realpath($runtime.'/mysql')?:'')))throw new RuntimeException('Foreign database.');
[$actor,$input]=json_decode((string)file_get_contents($base.'.json'),true,512,JSON_THROW_ON_ERROR);file_put_contents($base.'.ready',(string)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
try{$result=clubAdmissionPlace($pdo,(int)$actor,$input);echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR);}
catch(Throwable $e){echo json_encode(['ok'=>false,'type'=>get_class($e),'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
