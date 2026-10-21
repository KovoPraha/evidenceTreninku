<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/shop_system_email.php';

$port=(string)(getenv('EVIDENCE_MARIADB_SMOKE_PORT')?:'3306');
if (!preg_match('/^[1-9][0-9]{0,4}$/D',$port)||(int)$port>65535) throw new RuntimeException('Invalid test port.');
$database=(string)(getenv('EVIDENCE_SYSTEM_EMAIL_SMOKE_DB')?:'evidence_shop_system_email_test');
if (!preg_match('/^evidence_shop_system_email_test(?:_[a-z0-9_]+)?$/D',$database)) throw new RuntimeException('Refusing non-test database.');
$connect=static fn(string $db=''):PDO=>new PDO('mysql:host=127.0.0.1;port='.$port.($db!==''?';dbname='.$db:'').';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if (($argv[1]??'')==='worker') {
    try {
        $pdo=$connect($database);$t=shopSystemEmailCurrent($pdo,'shop_order_ready');
        shopSystemEmailSave($pdo,'shop_order_ready',7,0,$t['subject_plain'],$t['body_plain']."\nLOCALHOST ".($argv[2]??''),'LOCALHOST competing edit');
        echo 'saved';
    } catch(ShopSystemEmailException){echo 'conflict';}
    exit;
}
$server=$connect();$quoted='`'.$database.'`';
$server->exec('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo=$connect($database);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY) ENGINE=InnoDB');$pdo->exec('INSERT INTO treneri VALUES(7)');
    $pdo->exec('CREATE TABLE club_event_notifications(id INT PRIMARY KEY,body_plain TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO club_event_notifications VALUES(1,'LOCALHOST old message')");
    $migration=require dirname(__DIR__,2).'/migrations/20261021170000_shop_system_email_templates.php';
    $migration['up']($pdo);$migration['up']($pdo);
    if (!$migration['verify']($pdo)) throw new RuntimeException('Migration verification failed.');
    $workers=[];
    foreach(['A','B'] as $worker) {
        $pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'worker',$worker],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if (!is_resource($p)) throw new RuntimeException('Cannot start competing writer.');
        fclose($pipes[0]);$workers[]=[$p,$pipes];
    }
    $results=[];
    foreach($workers as [$p,$pipes]) {
        $output=trim((string)stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($p)!==0||$error!=='') throw new RuntimeException('Competing writer failed: '.$error);
        $results[]=$output;
    }
    sort($results);
    if ($results!==['conflict','saved']||(int)$pdo->query('SELECT COUNT(*) FROM shop_system_email_versions')->fetchColumn()!==1) throw new RuntimeException('Lost-update prevention failed.');
    $row=$pdo->query('SELECT * FROM club_event_notifications WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if ($row['body_plain']!=='LOCALHOST old message'||$row['system_template_key']!==null) throw new RuntimeException('Migration rewrote existing mail.');
    echo "MariaDB system email smoke OK: idempotent migration, one winning editor, old mail preserved.\n";
} finally {$pdo=null;$server->exec('DROP DATABASE '.$quoted);}
