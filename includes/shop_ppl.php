<?php
declare(strict_types=1);

final class ShopPplException extends RuntimeException {}

function shopPplSetting(string $name): string
{
    $value=getenv($name);
    return $value!==false ? trim($value) : (defined($name) ? trim((string)constant($name)) : '');
}

/** @return array{enabled:bool,price_minor:int,widget_api_key:string} */
function shopPplSettings(PDO $pdo): array
{
    $default=['enabled'=>false,'price_minor'=>0,'widget_api_key'=>''];
    if (!shopCheckoutColumnExists($pdo,'shop_orders','ppl_access_point_code')) return $default;
    $row=$pdo->query('SELECT enabled,price_minor,widget_api_key FROM shop_ppl_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) return $default;
    return ['enabled'=>(int)$row['enabled']===1,'price_minor'=>(int)$row['price_minor'],'widget_api_key'=>(string)$row['widget_api_key']];
}

/** Public checkout must never show an unfulfillable shipping option. */
function shopPplAvailable(array $settings): bool
{
    $mode=shopPplSetting('PPL_API_MODE');
    $host=strtolower((string)($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??''));
    $host=(string)preg_replace('/:\d+$/','',$host);
    $sandboxHost=($host===''&&PHP_SAPI==='cli')||in_array($host,['localhost','127.0.0.1','::1'],true)||str_ends_with($host,'.test');
    return $settings['enabled']===true && $settings['price_minor']>0
        && $settings['widget_api_key']!==''
        && in_array($mode,['test','production'],true) && ($mode!=='test'||$sandboxHost)
        && shopPplSetting('PPL_CLIENT_ID')!=='' && shopPplSetting('PPL_CLIENT_SECRET')!==''
        && function_exists('curl_init');
}

/** @param list<array<string,mixed>> $items */
function shopPplCartEligible(array $items): bool
{
    $hasGoods=false;
    foreach ($items as $item) {
        if (($item['offer_type']??'')!=='goods') return false;
        $hasGoods=true;
        if (($item['delivery_mode']??'pickup_only')!=='shipping_eligible') return false;
    }
    return $hasGoods;
}

/** @return array{code:string,name:string,address:string} */
function shopPplNormalizeAccessPoint(array $point, string $requestedCode): array
{
    $code=(string)($point['accessPointCode']??'');
    if (!hash_equals($requestedCode,$code) || ($point['accessPointType']??'')!=='ParcelBox'
        || strtoupper((string)($point['country']??''))!=='CZ') {
        throw new ShopPplException('Vybraný PPL box není dostupný. Vyberte jiný.');
    }
    $name=trim((string)($point['name']??''));
    $address=trim(implode(', ',array_filter([
        trim((string)($point['street']??'')),
        trim((string)($point['zipCode']??'').' '.(string)($point['city']??'')),
    ])));
    if ($name==='' || $address==='' || mb_strlen($name)>255 || mb_strlen($address)>500
        || preg_match('/[\x00-\x1F\x7F]/',$name.$address)) {
        throw new ShopPplException('PPL nevrátilo úplné údaje o boxu. Vyberte jiný.');
    }
    return ['code'=>$code,'name'=>$name,'address'=>$address];
}

function shopPplBaseUrl(): string
{
    return shopPplSetting('PPL_API_MODE')==='production'
        ? 'https://api.dhl.com/ecs/ppl/myapi2'
        : 'https://api-dev.dhl.com/ecs/ppl/myapi2';
}

/** @return array{status:int,body:string} */
function shopPplRequest(string $url, string $method, ?string $body=null, array $headers=[]): array
{
    $handle=curl_init($url);
    if ($handle===false) throw new ShopPplException('Spojení s PPL nyní není dostupné.');
    curl_setopt_array($handle,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>3,
        CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
    ]);
    if ($body!==null) curl_setopt($handle,CURLOPT_POSTFIELDS,$body);
    $response=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
    curl_close($handle);
    if (!is_string($response)||strlen($response)>1048576) throw new ShopPplException('PPL nyní neodpovídá správně. Zkuste to později.');
    return ['status'=>$status,'body'=>$response];
}

function shopPplAccessToken(): string
{
    static $cache=null;
    if (is_array($cache) && $cache['expires_at']>time()+30) return $cache['token'];
    $response=shopPplRequest(shopPplBaseUrl().'/login/getAccessToken','POST',http_build_query([
        'grant_type'=>'client_credentials','client_id'=>shopPplSetting('PPL_CLIENT_ID'),
        'client_secret'=>shopPplSetting('PPL_CLIENT_SECRET'),'scope'=>'myapi2',
    ]),['Content-Type: application/x-www-form-urlencoded','Accept: application/json']);
    $data=json_decode($response['body'],true);
    if ($response['status']!==200 || !is_array($data) || !is_string($data['access_token']??null)) {
        throw new ShopPplException('PPL ověření nyní není dostupné. Zvolte osobní odběr nebo to zkuste později.');
    }
    $cache=['token'=>$data['access_token'],'expires_at'=>time()+max(60,(int)($data['expires_in']??1800))];
    return $cache['token'];
}

/** @return array{code:string,name:string,address:string} */
function shopPplResolveAccessPoint(string $code, ?callable $lookup=null): array
{
    if (preg_match('/^KM[0-9]{7}$/D',$code)!==1) throw new ShopPplException('Vyberte PPL box z mapy.');
    if ($lookup!==null) return shopPplNormalizeAccessPoint($lookup($code),$code);
    $response=shopPplRequest(shopPplBaseUrl().'/accessPoint?'.http_build_query(['AccessPointCode'=>$code,'CountryCode'=>'CZ','AccessPointTypes'=>'ParcelBox']),
        'GET',null,['Authorization: Bearer '.shopPplAccessToken(),'Accept: application/json']);
    $points=json_decode($response['body'],true);
    if ($response['status']!==200 || !is_array($points)) throw new ShopPplException('PPL box nyní nelze ověřit. Zvolte osobní odběr nebo to zkuste později.');
    foreach ($points as $point) if (is_array($point)&&($point['accessPointCode']??null)===$code) return shopPplNormalizeAccessPoint($point,$code);
    throw new ShopPplException('Zvolený PPL box již není dostupný. Vyberte jiný.');
}

/** @return array{method:string,price_minor:int,point:?array,phone:?string} */
function shopPplCheckoutChoice(PDO $pdo, array $items, string $method, string $code, string $phone, int $expectedPrice, ?callable $lookup=null): array
{
    if ($method==='personal_pickup') return ['method'=>$method,'price_minor'=>0,'point'=>null,'phone'=>null];
    if ($method!=='ppl_box') throw new ShopPplException('Vyberte způsob předání.');
    $settings=shopPplSettings($pdo);
    if (!shopPplAvailable($settings)||!shopPplCartEligible($items)) throw new ShopPplException('Doručení do PPL boxu není pro tento košík dostupné.');
    if ($expectedPrice!==$settings['price_minor']) throw new ShopPplException('Cena dopravy se změnila. Zkontrolujte nový souhrn objednávky.');
    $phone=trim($phone);
    if (preg_match('/^\+?[0-9][0-9 ()-]{7,22}$/D',$phone)!==1 || mb_strlen($phone)>50) throw new ShopPplException('Pro doručení do boxu zadejte platné telefonní číslo.');
    return ['method'=>$method,'price_minor'=>$settings['price_minor'],'point'=>shopPplResolveAccessPoint($code,$lookup),'phone'=>$phone];
}

function shopPplSaveSettings(PDO $pdo, int $actorId, int $priceMinor, string $widgetKey, bool $enabled, string $reason, bool $confirmed): void
{
    $reason=trim($reason);$widgetKey=trim($widgetKey);
    if ($actorId<1||!$confirmed||mb_strlen($reason)<5||mb_strlen($reason)>1000) throw new InvalidArgumentException('Potvrďte změnu a uveďte její důvod.');
    if ($priceMinor<0||$priceMinor>1000000||strlen($widgetKey)>255||preg_match('/[\x00-\x20<>]/',$widgetKey)) throw new InvalidArgumentException('Neplatná cena nebo klíč widgetu.');
    if ($enabled && ($priceMinor<1||$widgetKey===''||!shopPplAvailable(['enabled'=>true,'price_minor'=>$priceMinor,'widget_api_key'=>$widgetKey]))) {
        throw new ShopPplException('PPL lze zapnout až po nastavení ceny, klíče mapy a serverových přístupů k PPL API.');
    }
    $pdo->beginTransaction();
    try {
        $sql='SELECT enabled,price_minor,widget_api_key FROM shop_ppl_settings WHERE id=1';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
        $stored=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        $before=$stored ?: ['enabled'=>0,'price_minor'=>0,'widget_api_key'=>''];
        $after=['enabled'=>$enabled?1:0,'price_minor'=>$priceMinor,'widget_api_key'=>$widgetKey];
        if ($stored) $pdo->prepare('UPDATE shop_ppl_settings SET enabled=?,price_minor=?,widget_api_key=?,updated_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=1')->execute([$after['enabled'],$priceMinor,$widgetKey,$actorId]);
        else $pdo->prepare('INSERT INTO shop_ppl_settings(id,enabled,price_minor,widget_api_key,updated_by_trainer_id) VALUES (1,?,?,?,?)')->execute([$after['enabled'],$priceMinor,$widgetKey,$actorId]);
        $pdo->prepare('INSERT INTO shop_ppl_settings_events(actor_trainer_id,before_json,after_json,reason) VALUES (?,?,?,?)')->execute([
            $actorId,json_encode(['enabled'=>(int)$before['enabled'],'price_minor'=>(int)$before['price_minor'],'widget_key_changed'=>false],JSON_THROW_ON_ERROR),
            json_encode(['enabled'=>$after['enabled'],'price_minor'=>$priceMinor,'widget_key_changed'=>$before['widget_api_key']!==$widgetKey],JSON_THROW_ON_ERROR),$reason,
        ]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack();throw $error; }
}
