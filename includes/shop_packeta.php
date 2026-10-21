<?php
declare(strict_types=1);

require_once __DIR__.'/shop_packeta_local_demo.php';

final class ShopPacketaException extends RuntimeException{}

/** @return array{enabled:bool,price_minor:int,widget_api_key:string} */
function shopPacketaSettings(PDO $pdo):array
{
    $empty=['enabled'=>false,'price_minor'=>0,'widget_api_key'=>''];
    if(!shopCheckoutColumnExists($pdo,'shop_orders','packeta_point_id'))return $empty;
    $row=$pdo->query('SELECT enabled,price_minor,widget_api_key FROM shop_packeta_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    return $row?['enabled'=>(int)$row['enabled']===1,'price_minor'=>(int)$row['price_minor'],'widget_api_key'=>(string)$row['widget_api_key']]:$empty;
}

function shopPacketaAvailable(array $settings):bool
{
    return ($settings['enabled']??false)===true&&(int)($settings['price_minor']??0)>0
        &&(shopPacketaLocalDemoEnabled()||(preg_match('/^[A-Za-z0-9]{16}$/D',(string)($settings['widget_api_key']??''))===1
        &&function_exists('curl_init')));
}

/** Fetch a current Z-BOX feed, caching it for four hours as Packeta recommends regular feed refreshes. */
function shopPacketaBoxFeed(string $apiKey):array
{
    if(preg_match('/^[A-Za-z0-9]{16}$/D',$apiKey)!==1)throw new ShopPacketaException('Klíč widgetu Zásilkovny není platný.');
    $cachePath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'evidence-packeta-zbox-'.hash('sha256',$apiKey).'.json';
    $handle=@fopen($cachePath,'c+');
    if($handle===false)throw new ShopPacketaException('Seznam Z-BOXů se nyní nepodařilo ověřit.');
    try{
        if(!flock($handle,LOCK_EX))throw new ShopPacketaException('Seznam Z-BOXů se nyní nepodařilo ověřit.');
        rewind($handle);$cached=stream_get_contents($handle);$envelope=is_string($cached)?json_decode($cached,true):null;
        if(is_array($envelope)&&isset($envelope['fetched_at'],$envelope['points'])&&time()-(int)$envelope['fetched_at']<14400&&is_array($envelope['points']))return $envelope['points'];
        $curl=curl_init('https://pickup-point.api.packeta.com/v5/'.$apiKey.'/box/json?'.http_build_query(['country'=>'cz','lang'=>'cs']));
        if($curl===false)throw new ShopPacketaException('Seznam Z-BOXů se nyní nepodařilo ověřit.');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if(!is_string($body)||strlen($body)>25000000||$status!==200)throw new ShopPacketaException('Seznam Z-BOXů od Zásilkovny nyní neodpovídá. Zvolte osobní odběr nebo to zkuste později.');
        $points=json_decode($body,true);
        if(!is_array($points)||!array_is_list($points))throw new ShopPacketaException('Zásilkovna vrátila neplatný seznam boxů.');
        rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode(['fetched_at'=>time(),'points'=>$points],JSON_THROW_ON_ERROR));fflush($handle);
        return $points;
    }finally{flock($handle,LOCK_UN);fclose($handle);}
}

/** @return array{id:string,name:string,address:string} */
function shopPacketaNormalizeBox(array $point,string $requestedId):array
{
    $id=(string)($point['id']??'');
    if(!hash_equals($requestedId,$id)||strtolower((string)($point['type']??''))!=='zbox'
        ||strtolower((string)($point['country']??''))!=='cz'||(int)($point['displayFrontend']??0)!==1
        ||(int)($point['status']['statusId']??0)!==1){
        throw new ShopPacketaException('Vybraný Z-BOX není dostupný. Vyberte jiný.');
    }
    $name=trim((string)($point['name']??''));
    $address=trim(implode(', ',array_filter([trim((string)($point['street']??'')),trim((string)($point['zip']??'').' '.(string)($point['city']??''))])));
    if($name===''||$address===''||mb_strlen($name)>255||mb_strlen($address)>500||preg_match('/[\x00-\x1F\x7F]/',$name.$address))throw new ShopPacketaException('Zásilkovna nevrátila úplné údaje o boxu. Vyberte jiný.');
    return ['id'=>$id,'name'=>$name,'address'=>$address];
}

/** @return array{id:string,name:string,address:string} */
function shopPacketaResolveBox(string $id,?callable $lookup=null,string $apiKey=''):array
{
    if(preg_match('/^[0-9]{1,12}$/D',$id)!==1)throw new ShopPacketaException('Vyberte Z-BOX z mapy Zásilkovny.');
    if($lookup!==null)return shopPacketaNormalizeBox($lookup($id),$id);
    if(shopPacketaLocalDemoEnabled()){
        foreach(shopPacketaLocalDemoPoints()as$point)if($point['id']===$id)return shopPacketaNormalizeBox($point,$id);
        throw new ShopPacketaException('Vyberte dostupný syntetický localhost Z-BOX.');
    }
    if(preg_match('/^[A-Za-z0-9]{16}$/D',$apiKey)!==1)throw new ShopPacketaException('Doručení do Z-BOXu není dostupné.');
    foreach(shopPacketaBoxFeed($apiKey)as$point)if(is_array($point)&&(string)($point['id']??'')===$id)return shopPacketaNormalizeBox($point,$id);
    throw new ShopPacketaException('Zvolený Z-BOX už není dostupný. Vyberte jiný.');
}

/** @return array{method:string,price_minor:int,point:?array} */
function shopPacketaCheckoutChoice(PDO $pdo,array $items,string $method,string $pointId,int $expectedPrice,?callable $lookup=null):array
{
    if($method==='personal_pickup')return ['method'=>$method,'price_minor'=>0,'point'=>null];
    if($method!=='packeta_zbox')throw new ShopPacketaException('Vyberte způsob předání.');
    $settings=shopPacketaSettings($pdo);
    if(!shopPacketaAvailable($settings)||!shopPplCartEligible($items))throw new ShopPacketaException('Doručení do Z-BOXu není pro tento košík dostupné.');
    if($expectedPrice!==$settings['price_minor'])throw new ShopPacketaException('Cena dopravy se změnila. Zkontrolujte nový souhrn objednávky.');
    $point=shopPacketaResolveBox($pointId,$lookup,$settings['widget_api_key']);
    return ['method'=>$method,'price_minor'=>$settings['price_minor'],'point'=>$point];
}

function shopPacketaSaveSettings(PDO $pdo,int $actorId,int $priceMinor,string $widgetKey,bool $enabled,string $reason,bool $confirmed):void
{
    $widgetKey=trim($widgetKey);$reason=trim($reason);
    if($actorId<1||!$confirmed||mb_strlen($reason)<5||mb_strlen($reason)>1000)throw new InvalidArgumentException('Potvrďte změnu a uveďte důvod.');
    if($priceMinor<0||$priceMinor>1000000||($widgetKey!==''&&preg_match('/^[A-Za-z0-9]{16}$/D',$widgetKey)!==1))throw new InvalidArgumentException('Neplatná cena nebo klíč widgetu Zásilkovny.');
    if($enabled&&($priceMinor<1||!shopPacketaAvailable(['enabled'=>true,'price_minor'=>$priceMinor,'widget_api_key'=>$widgetKey])))throw new ShopPacketaException('Zásilkovnu lze zapnout až po nastavení ceny a platného veřejného klíče widgetu.');
    $pdo->beginTransaction();
    try{
        $sql='SELECT enabled,price_minor,widget_api_key FROM shop_packeta_settings WHERE id=1';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $stored=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);$before=$stored?:['enabled'=>0,'price_minor'=>0,'widget_api_key'=>''];$after=['enabled'=>$enabled?1:0,'price_minor'=>$priceMinor,'widget_api_key'=>$widgetKey];
        if($stored)$pdo->prepare('UPDATE shop_packeta_settings SET enabled=?,price_minor=?,widget_api_key=?,updated_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=1')->execute([$after['enabled'],$priceMinor,$widgetKey,$actorId]);
        else $pdo->prepare('INSERT INTO shop_packeta_settings(id,enabled,price_minor,widget_api_key,updated_by_trainer_id) VALUES (1,?,?,?,?)')->execute([$after['enabled'],$priceMinor,$widgetKey,$actorId]);
        $pdo->prepare('INSERT INTO shop_packeta_settings_events(actor_trainer_id,before_json,after_json,reason) VALUES (?,?,?,?)')->execute([$actorId,json_encode(['enabled'=>(int)$before['enabled'],'price_minor'=>(int)$before['price_minor'],'widget_key_changed'=>false],JSON_THROW_ON_ERROR),json_encode(['enabled'=>$after['enabled'],'price_minor'=>$priceMinor,'widget_key_changed'=>$before['widget_api_key']!==$widgetKey],JSON_THROW_ON_ERROR),$reason]);
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
