<?php
declare(strict_types=1);

function clubApprovedCatalogSettingsMain():void
{
    if(PHP_SAPI!=='cli')throw new RuntimeException('Pouze CLI.');
    $root=realpath((string)getenv('APP_ROOT'));$host=strtolower(trim((string)getenv('APP_HOST')));
    if($root===false||$host!=='kis.kovopraha.cz'||!is_file($root.'/config.php'))
        throw new RuntimeException('Cíl musí být produkční instalace kis.kovopraha.cz.');
    $apply=(string)getenv('CLUB_APPROVED_SETTINGS_CONFIRM')==='NASTAVIT-KROUZKY-2026-27';
    $_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=$host;require $root.'/config.php';
    if(!defined('JE_LOKALNE')||JE_LOKALNE!==false)throw new RuntimeException('Nastavení lze spustit pouze na produkci.');
    require_once $root.'/includes/club_approved_catalog_settings.php';
    foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS']as$constant)
        if(!defined($constant))throw new RuntimeException('Neúplná konfigurace databáze.');
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $actor=(int)$pdo->query("SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    if($actor<1)throw new RuntimeException('Chybí aktivní administrátor pro audit.');
    echo json_encode(clubApprovedCatalogSettings($pdo,$actor,$apply),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{clubApprovedCatalogSettingsMain();}
    catch(Throwable$e){fwrite(STDERR,'Nastavení kapacit a cen selhalo: '.$e->getMessage().PHP_EOL);exit(1);}
}
