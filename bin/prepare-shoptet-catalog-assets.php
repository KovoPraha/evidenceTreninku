<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/shoptet_product_input.php';
require_once dirname(__DIR__).'/includes/shop_catalog_contract.php';
require_once dirname(__DIR__).'/includes/shop_catalog_migration_assets.php';

$input='';$output='';$apply=false;
foreach(array_slice($argv,1)as$argument){
    if(str_starts_with($argument,'--input='))$input=substr($argument,8);
    elseif(str_starts_with($argument,'--output='))$output=substr($argument,9);
    elseif($argument==='--apply')$apply=true;
    else{fwrite(STDERR,"Neznámý parametr.\n");exit(64);}
}
if(!$apply||$input===''||$output===''){fwrite(STDERR,"Použití: php bin/prepare-shoptet-catalog-assets.php --input=<xml> --output=<nový-adresář> --apply\n");exit(64);}
try{$catalog=ShopCatalogContract::build(ShoptetProductInput::read($input));$result=shopCatalogMigrationPrepareAssets($catalog,$output);fwrite(STDOUT,json_encode(['ok'=>true,'source_sha256'=>$result['source_sha256'],'source_images'=>$result['source_images'],'unique_files'=>$result['unique_files'],'downloaded_files'=>$result['downloaded_files']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n");}
catch(Throwable$e){error_log('prepare-shoptet-catalog-assets.php: '.$e->getMessage());fwrite(STDERR,"Příprava obrázků selhala.\n");exit(2);}
