<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(function_exists('set_time_limit'))set_time_limit(900);
require_once dirname(__DIR__).'/includes/shop_catalog_one_time_publish.php';

$action=(string)getenv('SHOPTET_PUBLISH_ACTION');$confirmation=(string)getenv('SHOPTET_PUBLISH_CONFIRM');$sourceSha=(string)getenv('SHOPTET_SOURCE_SHA256');
$expectedProducts=(int)getenv('SHOPTET_EXPECTED_PRODUCTS');$expectedVisible=(int)getenv('SHOPTET_EXPECTED_VISIBLE_PRODUCTS');$expectedHidden=(int)getenv('SHOPTET_EXPECTED_HIDDEN_PRODUCTS');$expectedVariants=(int)getenv('SHOPTET_EXPECTED_ACTIVE_VARIANTS');
if(!in_array($action,['preview','apply'],true)||(string)getenv('APP_HOST')===''||$sourceSha===''){fwrite(STDERR,"Chybí akce preview/apply, APP_HOST nebo otisk zdroje.\n");exit(64);}
try{require dirname(__DIR__).'/db.php';$result=$action==='preview'?shopCatalogOneTimePublishPreview($pdo,$sourceSha,$expectedProducts,$expectedVisible,$expectedHidden,$expectedVariants):shopCatalogOneTimePublishApply($pdo,$sourceSha,$expectedProducts,$expectedVisible,$expectedHidden,$expectedVariants,$confirmation);fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");if(!($result['ok']??false))exit(2);
}catch(Throwable$e){error_log('publish-shoptet-catalog-once.php: '.$e->getMessage());$cause=$e;while($cause->getPrevious()instanceof Throwable)$cause=$cause->getPrevious();fwrite(STDERR,json_encode(['ok'=>false,'error'=>'one_time_catalog_publish_failed','failure_type'=>get_debug_type($cause),'failure_code'=>(string)$cause->getCode(),'fingerprint'=>substr(hash('sha256',get_class($cause).'|'.$cause->getCode().'|'.$cause->getMessage()),0,16)],JSON_UNESCAPED_SLASHES)."\n");exit(2);}
