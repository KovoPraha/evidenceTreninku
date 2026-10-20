<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
umask(0077);
try {
    $relative=(string)getenv('KIS_IMPORT_APP_ROOT');
    if($relative==='' || str_contains($relative,'..') || !preg_match('~^[a-zA-Z0-9._/-]+$~D',$relative) || str_starts_with($relative,'/'))throw new RuntimeException('Invalid app root.');
    $hostingRoot=dirname(__DIR__,2);
    $appRoot=realpath($hostingRoot.'/'.$relative);
    if(!$appRoot || !is_file($appRoot.'/config.php'))throw new RuntimeException('Application configuration missing.');
    $_SERVER['HTTP_HOST']=(string)getenv('APP_HOST');
    $_SERVER['SERVER_NAME']=$_SERVER['HTTP_HOST'];
    require $appRoot.'/config.php';
    require $appRoot.'/includes/person_sensitive.php';
    $private=$hostingRoot.'/data/.kis-secrets';
    $backup=$hostingRoot.'/data/.kis-backups/keys';
    foreach([$private,$backup] as $dir) {
        if(!is_dir($dir) && !mkdir($dir,0700,true))throw new RuntimeException('Cannot create private directory.');
        if(!chmod($dir,0700))throw new RuntimeException('Cannot protect private directory.');
    }
    $keyFile=$private.'/kis-person-import.json';
    $backupFile=$backup.'/kis-person-import.json';
    $write=static function(string $path,string $bytes):void {
        if(file_exists($path))throw new RuntimeException('Refusing to overwrite key.');
        $tmp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes) || !chmod($tmp,0600) || !rename($tmp,$path))throw new RuntimeException('Cannot save protected key.');
    };
    $created=false;$restored=false;
    if(!is_file($keyFile) && is_file($backupFile)) {
        $bytes=(string)file_get_contents($backupFile);
        personSensitiveConfig(json_decode($bytes,true,16,JSON_THROW_ON_ERROR));
        $write($keyFile,$bytes);$restored=true;
    }
    if(!is_file($keyFile)) {
        $sharedReady=false;try{personSensitiveConfig();$sharedReady=true;}catch(PersonSensitiveException){}
        $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $exists=$pdo->query("SHOW TABLES LIKE 'kis_person_batches'")->fetchColumn();
        if($exists && (int)$pdo->query('SELECT COUNT(*) FROM kis_person_batches')->fetchColumn()>0 && !$sharedReady)throw new RuntimeException('Existing archive requires recovery of its original key.');
        $config=['keys'=>['kis-import-v1'=>base64_encode(random_bytes(32))],
            'active_version'=>'kis-import-v1','index_key'=>base64_encode(random_bytes(32))];
        personSensitiveConfig($config);
        $write($keyFile,json_encode($config,JSON_THROW_ON_ERROR));$created=true;
    }
    $bytes=(string)file_get_contents($keyFile);
    personSensitiveConfig(json_decode($bytes,true,16,JSON_THROW_ON_ERROR));
    if(!is_file($backupFile))$write($backupFile,$bytes);
    if(!hash_equals($bytes,(string)file_get_contents($backupFile)))throw new RuntimeException('Key backup differs; refusing to replace either copy.');
    if(!chmod($keyFile,0600) || !chmod($backupFile,0600))throw new RuntimeException('Cannot protect key files.');
    echo json_encode(['ok'=>true,'created'=>$created,'restored'=>$restored,'private_backup_verified'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
    // Exception details may contain connection parameters; never print them.
    fwrite(STDERR,"KIS archive key provisioning failed; no existing key was replaced.\n");
    exit(1);
}
