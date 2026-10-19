<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_catalog_one_time_sync.php';

final class ShopCatalogMigrationAssetException extends RuntimeException {}

/**
 * @param null|callable(string):string $fetcher
 * @return array<string,mixed>
 */
function shopCatalogMigrationPrepareAssets(array $catalog,string $outputDirectory,?callable$fetcher=null):array
{
    $sourceSha=(string)($catalog['source']['sha256']??'');
    if(($catalog['summary']['contract_ready']??null)!==true||preg_match('/^[a-f0-9]{64}$/D',$sourceSha)!==1){
        throw new ShopCatalogMigrationAssetException('Katalog není připravený pro stažení obrázků.');
    }
    if(file_exists($outputDirectory)){if(!is_dir($outputDirectory)||shopCatalogMigrationDirectoryHasEntries($outputDirectory))throw new ShopCatalogMigrationAssetException('Výstupní adresář musí být nový nebo prázdný.');}
    elseif(!mkdir($outputDirectory,0700,true)&&!is_dir($outputDirectory))throw new ShopCatalogMigrationAssetException('Výstupní adresář nelze vytvořit.');
    $imageDirectory=$outputDirectory.DIRECTORY_SEPARATOR.'images';if(!mkdir($imageDirectory,0700,true)&&!is_dir($imageDirectory))throw new ShopCatalogMigrationAssetException('Adresář obrázků nelze vytvořit.');
    $rows=[];$downloaded=0;$reused=0;$urls=shopCatalogOneTimeSourceImageUrls($catalog);
    foreach(array_chunk($urls,24)as$batch){
        $contents=$fetcher===null?shopCatalogMigrationFetchBatch($batch):array_combine($batch,array_map($fetcher,$batch));
        if(!is_array($contents)||count($contents)!==count($batch))throw new ShopCatalogMigrationAssetException('Dávka obrázků není úplná.');
        foreach($batch as$url){
            shopCatalogMigrationAssertUrl($url);$bytes=$contents[$url]??null;if(!is_string($bytes))throw new ShopCatalogMigrationAssetException('Stažený obrázek chybí.');
            try{$encoded=shopCatalogMigrationEncodeWithRetry($url,$bytes);}catch(ShopCatalogMigrationAssetException$exception){throw new ShopCatalogMigrationAssetException('Zdrojový obrázek '.substr(hash('sha256',$url),0,16).' nelze připravit: '.$exception->getMessage(),0,$exception);}
            $sha=hash('sha256',$encoded);$name=substr($sha,0,32).'.jpg';$target=$imageDirectory.DIRECTORY_SEPARATOR.$name;
            if(is_file($target)){if(!hash_equals($sha,(string)hash_file('sha256',$target)))throw new ShopCatalogMigrationAssetException('Kolize názvu připraveného obrázku.');$reused++;}
            else{if(file_put_contents($target,$encoded,LOCK_EX)!==strlen($encoded))throw new ShopCatalogMigrationAssetException('Připravený obrázek nelze uložit.');@chmod($target,0600);$downloaded++;}
            $size=getimagesizefromstring($encoded);$rows[$url]=['file'=>'images/'.$name,'sha256'=>$sha,'bytes'=>strlen($encoded),'width'=>(int)($size[0]??0),'height'=>(int)($size[1]??0)];
        }
    }
    $manifest=['version'=>SHOP_CATALOG_ASSET_MANIFEST_VERSION,'source_sha256'=>$sourceSha,'source_images'=>count($rows),'unique_files'=>count(array_unique(array_column($rows,'file'))),'downloaded_files'=>$downloaded,'reused_files'=>$reused,'images'=>$rows];
    $manifestPath=$outputDirectory.DIRECTORY_SEPARATOR.'manifest.json';$json=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    if(file_put_contents($manifestPath,$json."\n",LOCK_EX)!==strlen($json)+1)throw new ShopCatalogMigrationAssetException('Manifest obrázků nelze uložit.');@chmod($manifestPath,0600);return$manifest;
}

function shopCatalogMigrationEncodeWithRetry(string$url,string$bytes):string
{
    $last=null;for($attempt=0;$attempt<3;$attempt++){
        try{return shopCatalogMigrationEncodeJpeg($bytes);}catch(ShopCatalogMigrationAssetException$exception){$last=$exception;if($attempt<2)$bytes=shopCatalogMigrationFetchHttps($url);}
    }
    throw new ShopCatalogMigrationAssetException('Obrázek selhal i po opakovaném stažení.',0,$last);
}

/** @param list<string> $urls @return array<string,string> */
function shopCatalogMigrationFetchBatch(array$urls):array
{
    if(!function_exists('curl_multi_init'))throw new ShopCatalogMigrationAssetException('PHP cURL není dostupný.');
    $multi=curl_multi_init();$handles=[];$buffers=[];
    try{
        foreach($urls as$index=>$url){$downloadUrl=shopCatalogMigrationDownloadUrl($url);$buffers[$index]='';$curl=curl_init($downloadUrl);if($curl===false)throw new ShopCatalogMigrationAssetException('Stažení obrázku nelze připravit.');curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>60,CURLOPT_FAILONERROR=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'KIS-KovoPraha-OneTimeCatalogMigration/1.0',CURLOPT_RETURNTRANSFER=>false,CURLOPT_HEADER=>false,CURLOPT_WRITEFUNCTION=>static function($handle,string$chunk)use(&$buffers,$index):int{if(strlen($buffers[$index])+strlen($chunk)>5*1024*1024)return 0;$buffers[$index].=$chunk;return strlen($chunk);}]);curl_multi_add_handle($multi,$curl);$handles[$index]=$curl;}
        do{$status=curl_multi_exec($multi,$running);if($status!==CURLM_OK)throw new ShopCatalogMigrationAssetException('Dávkové stažení obrázků selhalo.');if($running>0)curl_multi_select($multi,1.0);}while($running>0);
        $result=[];foreach($handles as$index=>$curl){$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);if(in_array($status,[301,302,307,308],true)){$result[$urls[$index]]=shopCatalogMigrationFetchHttps($urls[$index],false);continue;}if(curl_errno($curl)!==0||$status!==200||$buffers[$index]==='')throw new ShopCatalogMigrationAssetException('Obrázek '.substr(hash('sha256',$urls[$index]),0,16).' se nepodařilo stáhnout přes HTTPS (HTTP '.$status.($error!==''?', cURL chyba':'').').');$result[$urls[$index]]=$buffers[$index];}return$result;
    }finally{foreach($handles as$curl){curl_multi_remove_handle($multi,$curl);curl_close($curl);}curl_multi_close($multi);}
}

function shopCatalogMigrationDirectoryHasEntries(string$directory):bool{$iterator=new FilesystemIterator($directory,FilesystemIterator::SKIP_DOTS);return$iterator->valid();}

function shopCatalogMigrationAssertUrl(string$url):void
{
    $parts=parse_url($url);if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||strtolower((string)($parts['host']??''))!=='cdn.myshoptet.com'||isset($parts['user'],$parts['pass'])||isset($parts['port'])||trim((string)($parts['path']??''))==='')throw new ShopCatalogMigrationAssetException('Obrázek používá nepovolenou zdrojovou URL.');
}

function shopCatalogMigrationDownloadUrl(string$url):string
{
    shopCatalogMigrationAssertUrl($url);$parts=parse_url($url);$path=(string)($parts['path']??'');
    if(!str_contains($path,'/user/shop/orig/'))throw new ShopCatalogMigrationAssetException('Obrázek nemá očekávanou Shoptet cestu.');
    return str_replace('/user/shop/orig/','/user/shop/big/',$url);
}

function shopCatalogMigrationFetchHttps(string$url,bool$preferRendition=true):string
{
    if(!function_exists('curl_init'))throw new ShopCatalogMigrationAssetException('PHP cURL není dostupný.');
    shopCatalogMigrationAssertUrl($url);$requestUrl=$preferRendition?shopCatalogMigrationDownloadUrl($url):$url;
    $buffer='';$curl=curl_init($requestUrl);if($curl===false)throw new ShopCatalogMigrationAssetException('Stažení obrázku nelze připravit.');
    curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>60,CURLOPT_FAILONERROR=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'KIS-KovoPraha-OneTimeCatalogMigration/1.0',CURLOPT_RETURNTRANSFER=>false,CURLOPT_HEADER=>false,CURLOPT_WRITEFUNCTION=>static function($handle,string$chunk)use(&$buffer):int{if(strlen($buffer)+strlen($chunk)>5*1024*1024)return 0;$buffer.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
    if($preferRendition&&in_array($status,[301,302,307,308],true))return shopCatalogMigrationFetchHttps($url,false);
    if($ok!==true||$status!==200||$buffer==='')throw new ShopCatalogMigrationAssetException('Obrázek se nepodařilo stáhnout přes HTTPS (HTTP '.$status.($error!==''?', cURL chyba':'').').');return$buffer;
}

function shopCatalogMigrationEncodeJpeg(string$bytes):string
{
    if(strlen($bytes)<1||strlen($bytes)>5*1024*1024)throw new ShopCatalogMigrationAssetException('Zdrojový obrázek má neplatnou velikost.');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=strtolower((string)$finfo->buffer($bytes));if(!in_array($mime,['image/jpeg','image/png'],true))throw new ShopCatalogMigrationAssetException('Zdrojový obrázek není JPG nebo PNG.');
    $size=@getimagesizefromstring($bytes);$width=is_array($size)?(int)($size[0]??0):0;$height=is_array($size)?(int)($size[1]??0):0;if($width<1||$height<1||$width>6000||$height>6000)throw new ShopCatalogMigrationAssetException('Zdrojový obrázek má neplatné rozměry.');
    $image=@imagecreatefromstring($bytes);if(!$image instanceof GdImage)throw new ShopCatalogMigrationAssetException('Zdrojový obrázek nelze dekódovat.');
    $canvas=imagecreatetruecolor($width,$height);if(!$canvas instanceof GdImage){imagedestroy($image);throw new ShopCatalogMigrationAssetException('Obrázek nelze překódovat.');}
    try{$white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);if(!imagecopy($canvas,$image,0,0,0,0,$width,$height))throw new ShopCatalogMigrationAssetException('Obrázek nelze překódovat.');ob_start();if(!imagejpeg($canvas,null,90)){ob_end_clean();throw new ShopCatalogMigrationAssetException('Obrázek nelze překódovat.');}$encoded=ob_get_clean();if(!is_string($encoded)||$encoded==='')throw new ShopCatalogMigrationAssetException('Obrázek nelze překódovat.');return$encoded;}finally{imagedestroy($canvas);imagedestroy($image);}
}
