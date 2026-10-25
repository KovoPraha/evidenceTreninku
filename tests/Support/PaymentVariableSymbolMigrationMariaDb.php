<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);require $root.'/config.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
if(DB_HOST!=='127.0.0.1;port=33426'||DB_NAME!=='evidence_b1'||trim(file_get_contents($runtime.'/owner.txt'))!==$root)throw new RuntimeException('Owned sandbox required.');
$descriptor=json_decode(file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Isolated sandbox required.');
$server=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if(strtolower(rtrim(str_replace('\\','/',$server->query('SELECT @@datadir')->fetchColumn()),'/'))!==strtolower(str_replace('\\','/',$runtime.'/mysql')))throw new RuntimeException('Wrong datadir.');
$database='evidence_payment_vs_test_'.bin2hex(random_bytes(5));$server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4');
require_once $root.'/includes/migration_runner.php';$checks=[];
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try {
    $pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$database.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');$pdo->exec('CREATE TABLE member_fee_plans(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE payments(id INT PRIMARY KEY,payable_type VARCHAR(30),payable_id INT,variable_symbol VARCHAR(10),method VARCHAR(30),UNIQUE KEY aaa_symbol(variable_symbol),UNIQUE KEY zzz_symbol(variable_symbol),UNIQUE KEY uq_payable(payable_type,payable_id),UNIQUE KEY uq_composite(variable_symbol,method)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO payments VALUES(1,'member_charge',11,'001234','bank'),(2,'member_charge',12,'009999','cash')");$before=$pdo->query('SELECT * FROM payments ORDER BY id')->fetchAll();
    $catalog=EvidenceMigrationCatalog::load($root.'/migrations');$legacy=$catalog['20260922190000_member_fee_standing_orders'];
    $check($legacy['checksum']===hash_file('sha256',$root.'/migrations/20260922190000_member_fee_standing_orders.php'),'Frozen migration checksum is unchanged');
    $check(paymentVariableSymbolUniqueIndexes($pdo)===['aaa_symbol','zzz_symbol'],'All single-column unique indexes detected independently of index ordering');
    $legacy['up']($pdo);$check($legacy['verify']($pdo),'Fresh install corrected execution passes without warnings');
    $check($before===$pdo->query('SELECT * FROM payments ORDER BY id')->fetchAll(),'Existing payment identifiers and leading-zero symbols remain exact');
    $pdo->exec("INSERT INTO payments VALUES(3,'member_charge',13,'001234','cash')");$check((int)$pdo->query("SELECT COUNT(*) FROM payments WHERE variable_symbol='001234'")->fetchColumn()===2,'Second charge can reuse payer variable symbol');
    foreach(["INSERT INTO payments VALUES(4,'member_charge',11,'another','cash')","INSERT INTO payments VALUES(4,'member_charge',14,'001234','cash')"] as $sql){try{$pdo->exec($sql);throw new RuntimeException('Unrelated uniqueness constraint was removed');}catch(PDOException $e){$check($e->getCode()==='23000','Canonical payable and composite constraints remain enforced');}}
    $pdo->exec('DELETE FROM payments WHERE id=3');$pdo->exec('ALTER TABLE payments ADD UNIQUE INDEX legacy_symbol(variable_symbol)');
    $forward=$catalog['20261024200000_payment_variable_symbol_index_repair'];$check(!$forward['verify']($pdo),'Forward migration identifies a previously missed unique symbol index');$forward['up']($pdo);$forward['up']($pdo);
    $check($forward['verify']($pdo)&&$before===$pdo->query('SELECT * FROM payments ORDER BY id')->fetchAll(),'Forward repair is idempotent and preserves all historical values');
    $legacy['up']($pdo);$check($legacy['verify']($pdo),'Corrected legacy execution is idempotent');
    echo json_encode(['status'=>'PASS','checks'=>count($checks),'scenarios'=>$checks,'synthetic_only'=>true,'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}finally {restore_error_handler();$pdo=null;$server->exec('DROP DATABASE `'.$database.'`');}
