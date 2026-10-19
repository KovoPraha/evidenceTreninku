<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(function_exists('set_time_limit'))set_time_limit(900);
require_once dirname(__DIR__).'/includes/shoptet_product_input.php';
require_once dirname(__DIR__).'/includes/shop_catalog_contract.php';
require_once dirname(__DIR__).'/includes/shop_catalog_one_time_sync.php';

$input=(string)getenv('SHOPTET_INPUT');$manifestPath=(string)getenv('SHOPTET_ASSET_MANIFEST');$action=(string)getenv('SHOPTET_SYNC_ACTION');$confirmation=(string)getenv('SHOPTET_SYNC_CONFIRM');
$activeKeysRaw=trim((string)getenv('SHOPTET_ALLOWED_ACTIVE_KEYS'));$allowedActiveKeys=$activeKeysRaw===''||$activeKeysRaw==='none'?[]:explode(',',$activeKeysRaw);
foreach(array_slice($argv,1)as$argument){
    if(str_starts_with($argument,'--input='))$input=substr($argument,8);
    elseif(str_starts_with($argument,'--manifest='))$manifestPath=substr($argument,11);
    elseif($argument==='--preview')$action='preview';
    elseif($argument==='--apply')$action='apply';
    elseif(str_starts_with($argument,'--confirm='))$confirmation=substr($argument,10);
    else{fwrite(STDERR,"Neznámý parametr.\n");exit(64);}
}
if(!in_array($action,['preview','apply'],true)||$input===''||$manifestPath===''||(string)getenv('APP_HOST')===''){fwrite(STDERR,"Chybí vstup, manifest, akce preview/apply nebo APP_HOST.\n");exit(64);}
try{
    $catalog=ShopCatalogContract::build(ShoptetProductInput::read($input));$manifest=shopCatalogOneTimeManifest($manifestPath,(string)$catalog['source']['sha256']);require dirname(__DIR__).'/db.php';
    $result=$action==='preview'?shopCatalogOneTimePreview($pdo,$catalog,$manifest,$allowedActiveKeys):shopCatalogOneTimeApply($pdo,$catalog,$manifest,dirname(__DIR__),$confirmation,$allowedActiveKeys);
    fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");if(!($result['ok']??false))exit(2);
}catch(Throwable$e){error_log('sync-shoptet-catalog-once.php: '.$e->getMessage());$cause=$e;while($cause->getPrevious()instanceof Throwable)$cause=$cause->getPrevious();$failure=['ok'=>false,'error'=>'one_time_catalog_sync_failed','failure_type'=>get_debug_type($cause),'failure_code'=>(string)$cause->getCode(),'fingerprint'=>substr(hash('sha256',get_class($cause).'|'.$cause->getCode().'|'.$cause->getMessage()),0,16)];if($cause instanceof PDOException&&is_array($cause->errorInfo??null))$failure['driver_code']=(int)($cause->errorInfo[1]??0);fwrite(STDERR,json_encode($failure,JSON_UNESCAPED_SLASHES)."\n");exit(2);}
