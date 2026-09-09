<?php
declare(strict_types=1);

const KIS_FIO_BLOCK_BEGIN='// BEGIN KIS MANAGED FIO PRODUCTION';
const KIS_FIO_BLOCK_END='// END KIS MANAGED FIO PRODUCTION';

/** @param array<string,mixed> $input @return array{token:string,lookback_days:int} */
function kisProductionFioValidate(array $input):array
{
    $token=trim((string)($input['token']??''));$lookback=(int)($input['lookback_days']??3);
    if(preg_match('/^[A-Za-z0-9_-]{20,128}$/D',$token)!==1)throw new RuntimeException('Fio API token is missing or invalid.');
    if($lookback<1||$lookback>30)throw new RuntimeException('Fio lookback must be between 1 and 30 days.');
    return['token'=>$token,'lookback_days'=>$lookback];
}

/** @param array{token:string,lookback_days:int} $settings */
function kisProductionFioManagedBlock(array $settings,bool $enabled):string
{
    return KIS_FIO_BLOCK_BEGIN."\n"
        ."defined('FIO_IMPORT_ENABLED') || define('FIO_IMPORT_ENABLED', ".($enabled?'true':'false').");\n"
        ."defined('FIO_API_TOKEN') || define('FIO_API_TOKEN', ".var_export($settings['token'],true).");\n"
        ."defined('FIO_IMPORT_LOOKBACK_DAYS') || define('FIO_IMPORT_LOOKBACK_DAYS', ".$settings['lookback_days'].");\n"
        .KIS_FIO_BLOCK_END;
}

function kisProductionFioMergeConfig(string $config,string $block):string
{
    if(!str_starts_with(ltrim($config),'<?php'))throw new RuntimeException('Production config.php is not a PHP file.');
    $pattern='/\R*'.preg_quote(KIS_FIO_BLOCK_BEGIN,'/').'.*?'.preg_quote(KIS_FIO_BLOCK_END,'/').'\R*/s';$without=preg_replace($pattern,"\n",$config);
    if(!is_string($without))throw new RuntimeException('Managed Fio block could not be replaced.');
    $opening='/\A(<\?php(?:\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?)/';$merged=preg_replace($opening,"$1\n\n".$block,$without,1,$count);
    if(!is_string($merged)||$count!==1)throw new RuntimeException('Managed Fio block could not be inserted safely.');token_get_all($merged,TOKEN_PARSE);return$merged;
}

function kisProductionFioWriteConfig(string $configPath,string $newConfig,string $backupDir):bool
{
    $oldConfig=(string)file_get_contents($configPath);$changed=!hash_equals(hash('sha256',$oldConfig),hash('sha256',$newConfig));if(!$changed)return false;
    if(!is_dir($backupDir)&&!mkdir($backupDir,0700,true)&&!is_dir($backupDir))throw new RuntimeException('Production config backup directory could not be created.');
    $backupPath=$backupDir.'/config-fio-'.gmdate('Ymd-His').'-'.substr(hash('sha256',$oldConfig),0,12).'-'.bin2hex(random_bytes(4)).'.php';
    if(file_put_contents($backupPath,$oldConfig,LOCK_EX)===false)throw new RuntimeException('Production config backup could not be written.');chmod($backupPath,0600);
    $temporaryPath=$configPath.'.fio-'.bin2hex(random_bytes(6)).'.tmp';if(file_put_contents($temporaryPath,$newConfig,LOCK_EX)===false)throw new RuntimeException('Temporary production config could not be written.');chmod($temporaryPath,0600);
    if(!rename($temporaryPath,$configPath)){@unlink($temporaryPath);throw new RuntimeException('Production config could not be activated atomically.');}chmod($configPath,0600);return true;
}

/** @return array{environment:string,account_match:bool} */
function kisProductionFioVerifyReadOnlyAccess(string $appRoot,string $appHost,string $token):array
{
    $_SERVER['HTTP_HOST']=$appHost;$_SERVER['SERVER_NAME']=$appHost;require_once$appRoot.'/config.php';
    if(!defined('DB_HOST')||!defined('DB_NAME')||!defined('DB_USER')||!defined('DB_PASS'))throw new RuntimeException('Production database configuration is incomplete.');
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    require_once$appRoot.'/includes/fio_readonly_import.php';require_once$appRoot.'/includes/shop_bank_settings.php';$expected=fioNormalizeIban((string)shopBankSettingsEffective($pdo)['iban']);$today=(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    $document=json_decode(fioFetchPeriodJson($token,$today,$today),true,64,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);$actual=fioNormalizeIban((string)($document['accountStatement']['info']['iban']??''));
    if($actual===''||!hash_equals($expected,$actual))throw new RuntimeException('Fio token belongs to a different account than the account configured in KIS.');
    return['environment'=>defined('JE_LOKALNE')&&JE_LOKALNE===false?'production':'invalid','account_match'=>true];
}

/** @return array<string,mixed> */
function kisProductionFioProbe(string $appRoot,string $appHost):array
{
    $_SERVER['HTTP_HOST']=$appHost;$_SERVER['SERVER_NAME']=$appHost;require$appRoot.'/config.php';
    return['ok'=>true,'environment'=>defined('JE_LOKALNE')&&JE_LOKALNE===false?'production':'invalid','fio_enabled'=>defined('FIO_IMPORT_ENABLED')&&FIO_IMPORT_ENABLED===true,'token_present'=>defined('FIO_API_TOKEN')&&preg_match('/^[A-Za-z0-9_-]{20,128}$/D',(string)FIO_API_TOKEN)===1,'lookback_days'=>defined('FIO_IMPORT_LOOKBACK_DAYS')?(int)FIO_IMPORT_LOOKBACK_DAYS:3];
}

function kisProductionFioMain():void
{
    if(PHP_SAPI!=='cli'){http_response_code(404);exit;}if((string)getenv('CONFIGURE_PRODUCTION_FIO')!=='1')throw new RuntimeException('CONFIGURE_PRODUCTION_FIO=1 is required.');
    $action=trim((string)getenv('FIO_CONFIG_ACTION'));$appRoot=rtrim(trim((string)getenv('APP_ROOT')),'/\\');$appHost=strtolower(trim((string)getenv('APP_HOST')));
    if($appRoot===''||$appHost===''||!is_file($appRoot.'/config.php'))throw new RuntimeException('Production app root, host, or config.php is unavailable.');
    if($action==='probe'){echo json_encode(kisProductionFioProbe($appRoot,$appHost),JSON_THROW_ON_ERROR).PHP_EOL;return;}
    if(!in_array($action,['enable','disable'],true))throw new RuntimeException('Unsupported Fio configuration action.');$backupDir=rtrim(trim((string)getenv('CONFIG_BACKUP_DIR')),'/\\');if($backupDir==='')throw new RuntimeException('Fio config backup directory is unavailable.');
    if($action==='enable'){$secretFile=trim((string)getenv('FIO_SECRETS_FILE'));if($secretFile===''||!is_file($secretFile))throw new RuntimeException('Fio secret bundle is unavailable.');$decoded=json_decode((string)file_get_contents($secretFile),true,8,JSON_THROW_ON_ERROR);if(!is_array($decoded))throw new RuntimeException('Fio secret bundle is invalid.');$settings=kisProductionFioValidate($decoded);$verification=kisProductionFioVerifyReadOnlyAccess($appRoot,$appHost,$settings['token']);if($verification['environment']!=='production'||!$verification['account_match'])throw new RuntimeException('Fio production verification failed.');}
    else{$_SERVER['HTTP_HOST']=$appHost;$_SERVER['SERVER_NAME']=$appHost;require$appRoot.'/config.php';$settings=kisProductionFioValidate(['token'=>defined('FIO_API_TOKEN')?(string)FIO_API_TOKEN:'disabled-placeholder-token','lookback_days'=>defined('FIO_IMPORT_LOOKBACK_DAYS')?(int)FIO_IMPORT_LOOKBACK_DAYS:3]);}
    $configPath=$appRoot.'/config.php';$newConfig=kisProductionFioMergeConfig((string)file_get_contents($configPath),kisProductionFioManagedBlock($settings,$action==='enable'));$changed=kisProductionFioWriteConfig($configPath,$newConfig,$backupDir);
    echo json_encode(['ok'=>true,'changed'=>$changed,'mode'=>$action==='enable'?'enabled':'disabled'],JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){try{kisProductionFioMain();}catch(Throwable$exception){fwrite(STDERR,'Fio production configuration failed: '.$exception->getMessage().PHP_EOL);exit(1);}}
