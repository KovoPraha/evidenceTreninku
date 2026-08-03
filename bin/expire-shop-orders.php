<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root=dirname(__DIR__);$arguments=array_slice($argv,1);
if(in_array('--help',$arguments,true)){
    echo "Pouziti: APP_HOST=<host> php bin/expire-shop-orders.php [--apply] [--limit=N] [--now='Y-m-d H:i:s'] [--json]\n";
    echo "Bez --apply se nic nemeni (dry-run). --now je urceno pro testy a rucni obnovu, ne pro bezny cron.\n";
    exit(0);
}
$apply=in_array('--apply',$arguments,true);$json=in_array('--json',$arguments,true);$limit=200;$now=null;$unknown=[];
foreach($arguments as$argument){
    if(in_array($argument,['--apply','--json'],true))continue;
    if(preg_match('/^--limit=([1-9][0-9]{0,2})$/D',$argument,$match)===1){$limit=min(500,(int)$match[1]);continue;}
    if(str_starts_with($argument,'--now=')){$now=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',substr($argument,6))?:null;if(!$now)$unknown[]=$argument;continue;}
    $unknown[]=$argument;
}
$host=getenv('APP_HOST');
if($unknown!==[]||!is_string($host)||$host===''||preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di',$host)!==1){
    fwrite(STDERR,"Neplatne argumenty nebo chybi APP_HOST. Pouzijte --help.\n");exit(64);
}
$_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=(string)preg_replace('/:\d+$/','',$host);
try{
    require_once $root.'/config.php';
    foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS']as$constant)if(!defined($constant))throw new RuntimeException('missing_database_configuration');
    require_once $root.'/includes/shop_checkout.php';
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $summary=shopOrderExpireBatch($pdo,$now??new DateTimeImmutable('now'),$apply,$limit);
    $summary['mode']=$apply?'apply':'dry-run';
    if($json)echo json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
    else{
        echo strtoupper($summary['mode']).': examined='.$summary['examined'].' expired='.$summary['expired'].' unchanged='.$summary['unchanged'].' failed='.$summary['failed'].PHP_EOL;
        foreach($summary['results']as$result)echo $result['public_code'].' #'.$result['order_id'].' '.$result['status'].(isset($result['error'])?' - '.$result['error']:'').PHP_EOL;
    }
    exit($summary['failed']>0?2:0);
}catch(Throwable $exception){fwrite(STDERR,'Expirace objednavek selhala: '.$exception->getMessage().PHP_EOL);exit(1);}
