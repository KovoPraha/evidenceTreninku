<?php
declare(strict_types=1);

function clubCatalogUnificationMain(): void
{
    if(PHP_SAPI!=='cli')throw new RuntimeException('Pouze CLI.');
    $root=realpath((string)getenv('APP_ROOT'));
    if($root===false||!is_file($root.'/config.php'))throw new RuntimeException('APP_ROOT musí ukazovat na instalaci s config.php.');
    $apply=(string)getenv('CLUB_CATALOG_UNIFY_CONFIRM')==='SJEDNOTIT-KROUZKY-2026-27';
    require $root.'/config.php';
    require_once $root.'/includes/club_catalog_unification.php';
    foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS']as$constant)if(!defined($constant))throw new RuntimeException('Neúplná konfigurace databáze.');
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $actor=(int)$pdo->query("SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    if($actor<1)throw new RuntimeException('Chybí aktivní administrátor pro audit převodu.');
    echo json_encode(clubCatalogUnify($pdo,$actor,$apply),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{clubCatalogUnificationMain();}
    catch(Throwable$e){fwrite(STDERR,'Sjednocení katalogu selhalo: '.$e->getMessage().PHP_EOL);exit(1);}
}
