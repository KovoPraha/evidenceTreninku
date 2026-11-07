<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 3) . '/config.php';
if (DB_HOST !== '127.0.0.1;port=33427' || DB_NAME !== 'evidence_b1' || !JE_LOKALNE) throw new RuntimeException('Isolated course fixture only.');
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$identity=$pdo->query('SELECT @@port port, @@datadir datadir')->fetch();
if ((int)$identity['port']!==33427 || strtolower(str_replace('\\','/',$identity['datadir']))!=='c:/xampp/tmp/course-uat-20261107-db/') throw new RuntimeException('Unexpected local instance.');
$phase=$argv[1]??'pending';
if (!in_array($phase,['pending','paid','cancelled','refunded','mail'],true)) throw new RuntimeException('Invalid phase.');
$dir=dirname(__DIR__, 3).'/docs/verification/one-time-course-uat-20261107';
if ($phase==='mail') {
    require_once dirname(__DIR__, 3).'/includes/club_event_notification.php';
    $sent=[];
    $sender=static function(string $email,string $subject,string $body)use(&$sent):bool {
        if ($email!=='rodic@localhost.test') throw new RuntimeException('Unexpected recipient.');
        $sent[]=compact('email','subject','body');
        return true;
    };
    for($i=0;$i<10;$i++) {
        $result=clubEventNotificationProcessOne($pdo,$sender);
        if ($result===null) break;
        if ($result!==true) throw new RuntimeException('Local capture failed.');
    }
    if (clubEventNotificationProcessOne($pdo,$sender)!==null) throw new RuntimeException('Queue not drained.');
    if ($sent) file_put_contents($dir.'/captured-mail.json',json_encode(['transport'=>'local callable capture; no external send','messages'=>$sent],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
$out=['synthetic_only'=>true,'phase'=>$phase,'checked_at'=>date(DATE_ATOM)];
foreach([
    'orders'=>'SELECT id,account_id,public_code,status,payment_status,total_minor,currency FROM shop_orders',
    'payments'=>'SELECT id,payable_type,payable_id,status,amount_minor,variable_symbol FROM payments',
    'enrollments'=>'SELECT id,source_order_item_id,sportovec_id,status FROM club_program_enrollments',
    'course_rosters'=>"SELECT id,team_id,sportovec_id,status,source_ref FROM club_roster_members WHERE source='shop'",
    'notifications'=>'SELECT id,order_id,notification_type,status,attempts FROM club_event_notifications',
    'document_count'=>'SELECT COUNT(*) count FROM accounting_documents',
    'monthly_membership_count'=>'SELECT COUNT(*) count FROM monthly_memberships',
    'order_events'=>'SELECT action,from_status,to_status,note FROM shop_order_events ORDER BY id',
]as$key=>$sql)$out[$key]=$pdo->query($sql)->fetchAll();
file_put_contents($dir.'/'.$phase.'.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
