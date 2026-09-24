<?php
declare(strict_types=1);

function clubCatalogImportMain():void
{
    if(PHP_SAPI!=='cli'||(string)getenv('CLUB_CATALOG_IMPORT_CONFIRM')!=='IMPORTOVAT-KROUZKY-2026-27')throw new RuntimeException('Chybí výslovné potvrzení importu kroužků.');
    $root=realpath((string)getenv('APP_ROOT'));$host=strtolower(trim((string)getenv('APP_HOST')));
    if($root===false||$host!=='kis.kovopraha.cz'||!is_file($root.'/config.php'))throw new RuntimeException('Produkční cíl není dostupný.');
    $_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=$host;require $root.'/config.php';
    if(!defined('JE_LOKALNE')||JE_LOKALNE!==false)throw new RuntimeException('Import lze spustit pouze na produkci.');
    require_once $root.'/includes/club_catalog_import.php';
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $actor=(int)$pdo->query("SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    if($actor<1)throw new RuntimeException('Chybí aktivní administrátor pro audit importu.');
    echo json_encode(clubCatalogImport($pdo,$actor,$root),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){try{clubCatalogImportMain();}catch(Throwable$exception){fwrite(STDERR,'Import kroužků selhal: '.$exception->getMessage().PHP_EOL);exit(1);}}
