<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);$appHost=getenv('APP_HOST');
if(!is_string($appHost)||preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di',$appHost)!==1){fwrite(STDERR,"APP_HOST musi byt explicitne nastaveny na hostname aplikace.\n");exit(64);}
$_SERVER['HTTP_HOST']=$appHost;$_SERVER['SERVER_NAME']=(string)preg_replace('/:\d+$/','',$appHost);
try{
    require_once $root.'/config.php';require_once $root.'/includes/fio_readonly_import.php';
    $enabled=getenv('FIO_IMPORT_ENABLED');
    if($enabled!=='1'&&(!defined('FIO_IMPORT_ENABLED')||FIO_IMPORT_ENABLED!==true))throw new RuntimeException('fio_import_disabled');
    $token=getenv('FIO_API_TOKEN');if(!is_string($token)||$token==='')throw new RuntimeException('fio_token_missing');
    if(!defined('SHOP_BANK_IBAN'))throw new RuntimeException('shop_bank_iban_missing');
    foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS']as$constant)if(!defined($constant))throw new RuntimeException('missing_database_configuration');
    $lookbackEnv=getenv('FIO_IMPORT_LOOKBACK_DAYS');
    $lookback=is_string($lookbackEnv)&&$lookbackEnv!==''?(int)$lookbackEnv:(defined('FIO_IMPORT_LOOKBACK_DAYS')?(int)FIO_IMPORT_LOOKBACK_DAYS:3);if($lookback<1||$lookback>30)throw new RuntimeException('fio_invalid_lookback');
    $to=new DateTimeImmutable('today',new DateTimeZone('Europe/Prague'));$from=$to->modify('-'.($lookback-1).' days');
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    if((int)$pdo->query("SELECT GET_LOCK('evidence:fio-readonly-import',0)")->fetchColumn()!==1)throw new RuntimeException('fio_import_already_running');
    try{
        $last=$pdo->query('SELECT started_at FROM fio_import_runs ORDER BY id DESC LIMIT 1')->fetchColumn();if($last!==false&&time()-strtotime((string)$last)<30)throw new RuntimeException('fio_import_rate_limited');
        $json=fioFetchPeriodJson($token,$from->format('Y-m-d'),$to->format('Y-m-d'));$result=fioImportJson($pdo,$json,$from->format('Y-m-d'),$to->format('Y-m-d'),(string)SHOP_BANK_IBAN);
    }finally{$pdo->query("SELECT RELEASE_LOCK('evidence:fio-readonly-import')");}
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $exception){error_log('fio-import.php: '.$exception->getMessage());fwrite(STDERR,"Fio import selhal; token ani bankovni data nebyla vypsana. Detail je v serverovem logu.\n");exit(1);}
