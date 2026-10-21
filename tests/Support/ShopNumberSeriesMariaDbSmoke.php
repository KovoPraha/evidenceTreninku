<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/shop_checkout.php';
require_once __DIR__.'/ShopNumberSeriesFixture.php';
defined('SHOP_VS_PRESERVATION_LIBRARY_ONLY')||define('SHOP_VS_PRESERVATION_LIBRARY_ONLY',true);
require_once dirname(__DIR__,2).'/bin/verify-shop-variable-symbols.php';

$port=(string)(getenv('EVIDENCE_MARIADB_SMOKE_PORT')?:'3306');
if (!preg_match('/^[1-9][0-9]{0,4}$/D',$port)||(int)$port>65535) throw new RuntimeException('Invalid test port.');
$database=(string)(getenv('EVIDENCE_SERIES_SMOKE_DB')?:'evidence_shop_number_series_test');
if (!preg_match('/^evidence_shop_number_series_test(?:_[a-z0-9_]+)?$/D',$database)) throw new RuntimeException('Refusing non-test database.');
$connect=static fn(string $db=''):PDO=>new PDO('mysql:host=127.0.0.1;port='.$port.($db!==''?';dbname='.$db:'').';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if (($argv[1]??'')==='worker') {
    $pdo=$connect($database);
    if (($argv[2]??'')==='allocate') {
        $pdo->beginTransaction();
        // Deliberately establish a REPEATABLE READ snapshot BEFORE the mutex.
        $pdo->query('SELECT COUNT(*) FROM shop_number_series_counters')->fetchColumn();
        echo "ready\n";flush();
        $vs=shopOrderAssignVariableSymbol($pdo,(int)$argv[3],new DateTimeImmutable('2026-10-21T12:00:00+02:00'));
        $pdo->commit();echo $vs;
    } else {
        echo "ready\n";flush();
        try {shopNumberSeriesSave($pdo,7,['action'=>'group','group_code'=>4,'name'=>'LOCALHOST '.(string)$argv[3],'revision'=>0],'LOCALHOST competing edit',true);echo 'saved';}
        catch(ShopNumberSeriesException){echo 'conflict';}
    }
    exit;
}
$server=$connect();$quoted='`'.$database.'`';
$server->exec('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo=null;$workers=[];
try {
    $pdo=$connect($database);shopNumberSeriesFixture($pdo);
    $race=static function(string $action,array $ids) use($pdo,&$workers):array {
        $pdo->beginTransaction();shopNumberSeriesLock($pdo);$workers=[];
        foreach($ids as $id) {
            $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$action,(string)$id],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot start series worker.');
            fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        foreach($workers as [$process,$pipes]) if(trim((string)fgets($pipes[1]))!=='ready') throw new RuntimeException('Worker did not reach series barrier.');
        $pdo->commit();$results=[];
        foreach($workers as [$process,$pipes]) {
            $result=trim((string)stream_get_contents($pipes[1]));$error=(string)stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            if(proc_close($process)!==0||$error!=='') throw new RuntimeException('Series worker failed: '.$error);
            $results[]=$result;
        }
        $workers=[];sort($results);return $results;
    };
    if($race('allocate',[1,4])!==['2680000001','2680000002']) throw new RuntimeException('Concurrent allocation reused a number.');
    if($race('allocate',[5,5])!==['2680000003','2680000003']) throw new RuntimeException('Same-order replay consumed another number.');
    if((int)$pdo->query('SELECT next_number FROM shop_number_series_counters')->fetchColumn()!==4) throw new RuntimeException('Wrong final counter.');
    if($race('admin',['A','B'])!==['conflict','saved']) throw new RuntimeException('Lost-update protection failed.');
    if((int)$pdo->query('SELECT COUNT(*) FROM shop_order_accounting_items')->fetchColumn()!==3) throw new RuntimeException('Missing/duplicate accounting snapshots.');
    $pdo->exec('ALTER TABLE member_fee_standing_orders ADD plan_id INT NULL, ADD sportovec_id INT NULL');
    $before=shopVsPreservationSnapshot($pdo);
    if($before!==shopVsPreservationSnapshot($pdo,$before['bounds'])) throw new RuntimeException('Read-only preservation snapshot changed.');
    $pdo->exec("INSERT INTO payments VALUES(1,'shop_order',1,'2680000001')");
    $before=shopVsPreservationSnapshot($pdo);
    $pdo->exec("UPDATE payments SET variable_symbol='2680000099' WHERE id=1");
    if($before===shopVsPreservationSnapshot($pdo,$before['bounds'])) throw new RuntimeException('Changed VS was not detected.');
    echo "MariaDB number series smoke OK: idempotent migration, concurrent allocation after old snapshot, same-order replay, one winning editor.\n";
    echo "MariaDB read-only VS snapshot OK: stable digests and changed symbol detection.\n";
} finally {
    if($pdo&&$pdo->inTransaction())$pdo->rollBack();
    foreach($workers as [$process,$pipes]) {proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
    $pdo=null;$server->exec('DROP DATABASE '.$quoted);
}
