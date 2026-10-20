<?php
declare(strict_types=1);

function prepareKunraticeMain(array $argv):void
{
    if(PHP_SAPI!=='cli'||count($argv)!==2||!in_array($argv[1],['--dry-run','--apply'],true))
        throw new RuntimeException('Použití: prepare-kunratice.php --dry-run|--apply');
    $apply=$argv[1]==='--apply';
    if($apply&&(string)getenv('KUNRATICE_SPLIT_CONFIRM')!=='PRIPRAVIT-KUNRATICE-2026-27')
        throw new RuntimeException('Chybí výslovné potvrzení přípravy Kunratic.');
    $root=realpath((string)getenv('APP_ROOT'));$host=strtolower(trim((string)getenv('APP_HOST')));
    if($root===false||$host!=='kis.kovopraha.cz'||!is_file($root.'/config.php'))
        throw new RuntimeException('Produkční cíl není dostupný.');
    $_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=$host;require $root.'/config.php';
    if(!defined('JE_LOKALNE')||JE_LOKALNE!==false)throw new RuntimeException('Přípravu lze spustit pouze na produkci.');
    require_once $root.'/includes/club_kunratice_prepare.php';
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $actor=(int)$pdo->query("SELECT id FROM treneri WHERE aktivni=1 AND role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    if($actor<1)throw new RuntimeException('Chybí aktivní administrátor pro audit změny.');
    if($apply)$result=clubKunraticePrepare($pdo,$actor);
    else{
        $source=clubKunraticeSource($pdo)['source'];
        $existing=(int)$pdo->query("SELECT COUNT(*) FROM club_programs WHERE code IN ('KROUZKY-2627-KUNRATICE-3-4','KROUZKY-2627-KUNRATICE-5-6')")->fetchColumn();
        $result=['mode'=>'dry-run','source_program_id'=>(int)$source['program_id'],'source_product_id'=>(int)$source['product_id'],
            'imported_product_id'=>227,'imported_variant_id'=>793,'price_minor'=>(int)$source['amount_minor'],
            'existing_split_programs'=>$existing,'planned_group_count'=>2,'publication'=>'draft','schedule'=>'no time slots'];
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){try{prepareKunraticeMain($argv);}catch(Throwable$exception){fwrite(STDERR,'Příprava Kunratic selhala: '.$exception->getMessage().PHP_EOL);exit(1);}}
