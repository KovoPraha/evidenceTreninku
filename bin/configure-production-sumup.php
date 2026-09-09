<?php
declare(strict_types=1);

const KIS_SUMUP_BLOCK_BEGIN = '// BEGIN KIS MANAGED SUMUP PRODUCTION';
const KIS_SUMUP_BLOCK_END = '// END KIS MANAGED SUMUP PRODUCTION';

/** @param array<string,mixed> $input @return array{api_key:string,merchant_code:string,base_url:string} */
function kisProductionSumUpValidate(array $input, string $expectedHost): array
{
    $apiKey = trim((string)($input['api_key'] ?? ''));
    $merchantCode = strtoupper(trim((string)($input['merchant_code'] ?? '')));
    $baseUrl = rtrim(trim((string)($input['base_url'] ?? '')), '/');
    if (preg_match('/^sup_sk_[A-Za-z0-9._-]+$/D',$apiKey) !== 1) throw new RuntimeException('SumUp API key is missing or invalid.');
    if (preg_match('/^[A-Z0-9]{8}$/D',$merchantCode) !== 1) throw new RuntimeException('SumUp merchant code is missing or invalid.');
    $parts=parse_url($baseUrl);
    if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'
        ||strtolower((string)($parts['host']??''))!==strtolower($expectedHost)
        ||isset($parts['user'],$parts['pass'],$parts['query'],$parts['fragment'])){
        throw new RuntimeException('SumUp base URL must be the expected production HTTPS host.');
    }
    return ['api_key'=>$apiKey,'merchant_code'=>$merchantCode,'base_url'=>$baseUrl];
}

/** @param array{api_key:string,merchant_code:string,base_url:string} $settings */
function kisProductionSumUpManagedBlock(array $settings, bool $enabled): string
{
    return KIS_SUMUP_BLOCK_BEGIN . "\n"
        . "defined('SUMUP_ENABLED') || define('SUMUP_ENABLED', " . ($enabled?'true':'false') . ");\n"
        . "defined('SUMUP_API_KEY') || define('SUMUP_API_KEY', " . var_export($settings['api_key'],true) . ");\n"
        . "defined('SUMUP_MERCHANT_CODE') || define('SUMUP_MERCHANT_CODE', " . var_export($settings['merchant_code'],true) . ");\n"
        . KIS_SUMUP_BLOCK_END;
}

function kisProductionSumUpMergeConfig(string $config, string $block): string
{
    if(!str_starts_with(ltrim($config),'<?php'))throw new RuntimeException('Production config.php is not a PHP file.');
    $pattern='/\R*'.preg_quote(KIS_SUMUP_BLOCK_BEGIN,'/').'.*?'.preg_quote(KIS_SUMUP_BLOCK_END,'/').'\R*/s';
    $without=preg_replace($pattern,"\n",$config);
    if(!is_string($without))throw new RuntimeException('Managed SumUp block could not be replaced.');
    $opening='/\A(<\?php(?:\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?)/';
    $merged=preg_replace($opening,"$1\n\n".$block,$without,1,$count);
    if(!is_string($merged)||$count!==1)throw new RuntimeException('Managed SumUp block could not be inserted safely.');
    token_get_all($merged,TOKEN_PARSE);
    return $merged;
}

function kisProductionSumUpWriteConfig(string $configPath,string $newConfig,string $backupDir): bool
{
    $oldConfig=(string)file_get_contents($configPath);
    $changed=!hash_equals(hash('sha256',$oldConfig),hash('sha256',$newConfig));
    if(!$changed)return false;
    if(!is_dir($backupDir)&&!mkdir($backupDir,0700,true)&&!is_dir($backupDir))throw new RuntimeException('Production config backup directory could not be created.');
    $backupPath=$backupDir.'/config-sumup-'.gmdate('Ymd-His').'-'.substr(hash('sha256',$oldConfig),0,12).'-'.bin2hex(random_bytes(4)).'.php';
    if(file_put_contents($backupPath,$oldConfig,LOCK_EX)===false)throw new RuntimeException('Production config backup could not be written.');
    chmod($backupPath,0600);
    $temporaryPath=$configPath.'.sumup-'.bin2hex(random_bytes(6)).'.tmp';
    if(file_put_contents($temporaryPath,$newConfig,LOCK_EX)===false)throw new RuntimeException('Temporary production config could not be written.');
    chmod($temporaryPath,0600);
    if(!rename($temporaryPath,$configPath)){@unlink($temporaryPath);throw new RuntimeException('Production config could not be activated atomically.');}
    chmod($configPath,0600);
    return true;
}

/** @return array<string,mixed> */
function kisProductionSumUpProbe(string $appRoot,string $appHost): array
{
    $_SERVER['HTTP_HOST']=$appHost;$_SERVER['SERVER_NAME']=$appHost;
    require $appRoot.'/config.php';
    require_once $appRoot.'/includes/sumup_gateway.php';
    $settings=sumupSettingsFromConfig();
    return [
        'ok'=>true,
        'environment'=>defined('JE_LOKALNE')&&JE_LOKALNE===false?'production':'invalid',
        'sumup_enabled'=>sumupIsEnabled($settings),
        'api_key_present'=>preg_match('/^sup_sk_[A-Za-z0-9._-]+$/D',$settings['api_key'])===1,
        'merchant_code'=>(string)$settings['merchant_code'],
        'merchant_code_ok'=>preg_match('/^[A-Z0-9]{8}$/D',(string)$settings['merchant_code'])===1,
        'base_url_ok'=>$settings['base_url']==='https://'.$appHost,
    ];
}

function kisProductionSumUpMain(): void
{
    if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
    if((string)getenv('CONFIGURE_PRODUCTION_SUMUP')!=='1')throw new RuntimeException('CONFIGURE_PRODUCTION_SUMUP=1 is required.');
    $action=trim((string)getenv('SUMUP_CONFIG_ACTION'));
    $appRoot=rtrim(trim((string)getenv('APP_ROOT')),'/\\');
    $appHost=strtolower(trim((string)getenv('APP_HOST')));
    if($appRoot===''||$appHost===''||!is_file($appRoot.'/config.php'))throw new RuntimeException('Production app root, host, or config.php is unavailable.');
    if($action==='probe'){echo json_encode(kisProductionSumUpProbe($appRoot,$appHost),JSON_THROW_ON_ERROR).PHP_EOL;return;}
    if(!in_array($action,['configure_live','disable'],true))throw new RuntimeException('Unsupported SumUp configuration action.');
    $backupDir=rtrim(trim((string)getenv('CONFIG_BACKUP_DIR')),'/\\');
    if($backupDir==='')throw new RuntimeException('SumUp config backup directory is unavailable.');
    if($action==='configure_live'){
        $secretFile=trim((string)getenv('SUMUP_SECRETS_FILE'));
        if($secretFile===''||!is_file($secretFile))throw new RuntimeException('SumUp secret bundle is unavailable.');
        $decoded=json_decode((string)file_get_contents($secretFile),true,16,JSON_THROW_ON_ERROR);
        if(!is_array($decoded))throw new RuntimeException('SumUp secret bundle is invalid.');
        $settings=kisProductionSumUpValidate($decoded,$appHost);
    }else{
        $_SERVER['HTTP_HOST']=$appHost;$_SERVER['SERVER_NAME']=$appHost;
        require $appRoot.'/config.php';
        $settings=kisProductionSumUpValidate([
            'api_key'=>defined('SUMUP_API_KEY')?(string)SUMUP_API_KEY:'',
            'merchant_code'=>defined('SUMUP_MERCHANT_CODE')?(string)SUMUP_MERCHANT_CODE:'',
            'base_url'=>defined('APP_BASE_URL')?(string)APP_BASE_URL:'',
        ],$appHost);
    }
    $configPath=$appRoot.'/config.php';
    $oldConfig=(string)file_get_contents($configPath);
    $newConfig=kisProductionSumUpMergeConfig($oldConfig,kisProductionSumUpManagedBlock($settings,$action==='configure_live'));
    $changed=kisProductionSumUpWriteConfig($configPath,$newConfig,$backupDir);
    echo json_encode(['ok'=>true,'changed'=>$changed,'mode'=>$action==='configure_live'?'live':'disabled'],JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{kisProductionSumUpMain();}catch(Throwable $exception){fwrite(STDERR,'SumUp production configuration failed: '.$exception->getMessage().PHP_EOL);exit(1);}
}
