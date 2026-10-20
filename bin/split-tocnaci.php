<?php
declare(strict_types=1);

function clubTocnaciSplitMain():void
{
    if(PHP_SAPI!=='cli')throw new RuntimeException('Převod lze spustit pouze z CLI.');
    $root=realpath((string)getenv('APP_ROOT'));$host=strtolower(trim((string)getenv('APP_HOST')));
    if($root===false||$host!=='kis.kovopraha.cz'||!is_file($root.'/config.php'))throw new RuntimeException('Produkční cíl není dostupný.');
    $_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=$host;require $root.'/config.php';
    if(!defined('JE_LOKALNE')||JE_LOKALNE!==false)throw new RuntimeException('Produkční cíl nebyl potvrzen.');
    require_once $root.'/includes/club_tocnaci_split.php';
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $plan=clubTocnaciSplitPlan($pdo);
    if((string)getenv('CLUB_TOCNACI_SPLIT_CONFIRM')!=='ROZDELIT-TOCNACI-2026-27'){
        echo json_encode(['mode'=>'dry-run','plan'=>$plan],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
        return;
    }
    $actor=(int)$pdo->query("SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    if($actor<1)throw new RuntimeException('Chybí aktivní administrátor pro audit převodu.');
    $result=clubTocnaciSplitApply($pdo,$actor,$root);
    echo json_encode(['mode'=>'apply','result'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{clubTocnaciSplitMain();}catch(Throwable$exception){fwrite(STDERR,'Rozdělení Točňáků selhalo: '.$exception->getMessage().PHP_EOL);exit(1);}
}
