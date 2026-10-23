<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once dirname(__DIR__,2).'/includes/club_event_notification.php';
require_once dirname(__DIR__,2).'/includes/local_message_outbox.php';

$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if (($descriptor['synthetic_only']??false)!==true || ($descriptor['external_sends_disabled']??false)!==true || (int)$descriptor['db_port']!==33426) throw new RuntimeException('Not the owned synthetic environment.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$admin->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if ((int)$identity['port']!==33426 || strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/'))) throw new RuntimeException('Database identity mismatch.');
$worker=($argv[1]??'')==='--worker';
if (!$worker) {
    $admin->exec('CREATE DATABASE IF NOT EXISTS evidence_b2_notifications CHARACTER SET utf8mb4');
    $admin->exec("GRANT ALL ON evidence_b2_notifications.* TO 'b1_app'@'127.0.0.1'");
}
$admin=null;
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname=evidence_b2_notifications;charset=utf8mb4','b1_app','',$options);
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='evidence_b2_notifications') throw new RuntimeException('Wrong database.');
if ($worker) {
    $barrier=$argv[2];$slot=$argv[3];file_put_contents($barrier.'.'.$slot.'.ready','ready');
    $deadline=microtime(true)+10;while(!is_file($barrier.'.go')) { if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout.');usleep(10000); }
    $sender=localMessageOutboxSender('127.0.0.1','r11-concurrency-v1',$barrier.'.outbox');
    $outcome=clubEventNotificationProcessOne($pdo,static function($email,$subject,$body)use($sender){usleep(150000);return $sender($email,$subject,$body);});
    echo json_encode(['outcome'=>$outcome],JSON_THROW_ON_ERROR);exit;
}
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$owned=['r11_synthetic_marker','club_event_notifications'];
if ($tables && (!in_array('r11_synthetic_marker',$tables,true)||array_diff($tables,$owned))) throw new RuntimeException('Refusing an unowned fixture.');
$pdo->exec('CREATE TABLE IF NOT EXISTS r11_synthetic_marker(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec("CREATE TABLE IF NOT EXISTS club_event_notifications(id BIGINT AUTO_INCREMENT PRIMARY KEY,notification_type VARCHAR(64),recipient_email VARCHAR(254),subject_plain VARCHAR(255),body_plain TEXT,status VARCHAR(16) DEFAULT 'pending',attempts INT DEFAULT 0,available_at DATETIME DEFAULT CURRENT_TIMESTAMP,claimed_at DATETIME,claim_token CHAR(32),sent_at DATETIME,last_error VARCHAR(500),updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$pdo->exec('DELETE FROM club_event_notifications');
$pdo->exec("INSERT INTO club_event_notifications(notification_type,recipient_email,subject_plain,body_plain) VALUES('synthetic','local@example.invalid','LOCALHOST NEPLATIT','Synthetic concurrency proof')");
$barrier=$runtime.'/r11-race-'.bin2hex(random_bytes(5));$children=[];
foreach(['a','b'] as $slot) {
    $process=proc_open([PHP_BINARY,__FILE__,'--worker',$barrier,$slot],[0=>['pipe','r'],1=>['file',$barrier.'.'.$slot.'.out','w'],2=>['file',$barrier.'.'.$slot.'.err','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);$children[$slot]=$process;
}
$deadline=microtime(true)+15;
while(!is_file($barrier.'.a.ready')||!is_file($barrier.'.b.ready')) { if(microtime(true)>$deadline)throw new RuntimeException('Worker readiness timeout.');usleep(10000); }
file_put_contents($barrier.'.go','go');$outcomes=[];
foreach($children as $slot=>$child){if(proc_close($child)!==0)throw new RuntimeException('Worker failed: '.file_get_contents($barrier.'.'.$slot.'.err'));$outcomes[]=json_decode((string)file_get_contents($barrier.'.'.$slot.'.out'),true,512,JSON_THROW_ON_ERROR)['outcome'];}
$assert=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$assert(count(array_filter($outcomes,static fn($x)=>$x===true))===1,'Exactly one worker must send.');
$assert(count(glob($barrier.'.outbox/*.json')?:[])===1,'Exactly one local artifact must exist.');
$assert((int)$pdo->query('SELECT attempts FROM club_event_notifications')->fetchColumn()===1,'Competing claim must not increment attempts.');
$assert(clubEventNotificationProcessOne($pdo,static fn()=>true)===null,'A repeat must not send.');
$pdo->exec("UPDATE club_event_notifications SET status='pending',available_at=CURRENT_TIMESTAMP");
$assert(clubEventNotificationProcessOne($pdo,static function(){throw new RuntimeException('Synthetic ambiguous transport');})===false,'Ambiguous delivery must be held.');
$assert($pdo->query('SELECT status FROM club_event_notifications')->fetchColumn()==='failed','Unknown delivery must be terminal until reviewed.');
$assert(clubEventNotificationProcessOne($pdo,static fn()=>true)===null,'Unknown delivery must never automatically retry.');
echo json_encode(['checks'=>7,'two_process_claim'=>'pass','single_local_outbox_artifact'=>true,'unknown_delivery_held'=>true,'database'=>'evidence_b2_notifications'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
