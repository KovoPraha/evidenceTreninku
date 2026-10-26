<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/KisReviewFixture.php';
require_once dirname(__DIR__,2).'/includes/race_results.php';
$port=getenv('EVIDENCE_MARIADB_SMOKE_PORT')?:'3306';if(!ctype_digit($port))throw new RuntimeException('Invalid port.');
$server=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='evidence_race_kis_smoke_'.bin2hex(random_bytes(6));$archive=sys_get_temp_dir().'/'.$name;mkdir($archive);$old=getenv('KIS_SOURCE_ARCHIVE_DIR');putenv('KIS_SOURCE_ARCHIVE_DIR='.$archive);
$server->exec('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4');
try{
 $p=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 kisReviewFixture($p,$archive);
 $check=static function(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";};
 $fp=kisReviewSource($p,1)['fingerprint'];
 $plan=kisReviewPreview($p,1,$fp,[1=>['target'=>'5','teams'=>[1]],2=>['target'=>'new','teams'=>[1]]],'2026-10-25',7,true);
 $check(kisReviewExecute($p,$plan['id'],$plan['fingerprint'],7,true,true,'Synthetic apply')['people']===2,'canonical people and roster apply');
 $check(kisReviewExecute($p,$plan['id'],$plan['fingerprint'],7,true,true,'Repeated apply')['idempotent'],'idempotent apply');
 $p->exec("UPDATE sportovci SET jmeno='New edit' WHERE id=5");
 try{kisReviewExecute($p,$plan['id'],$plan['fingerprint'],7,true,true,'Conflicting rollback',true);throw new RuntimeException('Rollback overwrote newer data');}catch(DomainException){$check(kisReviewPlan($p,$plan['id'])['status']==='applied','rollback drift blocked');}
 $p->exec("UPDATE sportovci SET jmeno='Opravená' WHERE id=5");
 kisReviewExecute($p,$plan['id'],$plan['fingerprint'],7,true,true,'Synthetic rollback',true);
 $check((int)$p->query("SELECT COUNT(*) FROM club_roster_members WHERE status='active'")->fetchColumn()===0,'compensating roster rollback');
 $check($p->query('SELECT variable_symbol FROM payments')->fetchColumn()==='1234567890'&&$p->query('SELECT status FROM payments')->fetchColumn()==='paid','financial history unchanged');
 $p->exec('CREATE TABLE zavody(id INTEGER PRIMARY KEY) ENGINE=InnoDB;CREATE TABLE zavod_sportovec(zavod_id INTEGER,sportovec_id INTEGER) ENGINE=InnoDB;INSERT INTO zavody VALUES(1);INSERT INTO zavod_sportovec VALUES(1,5)');
 $m=require dirname(__DIR__,2).'/migrations/20261025230000_race_result_revisions.php';$m['up']($p);
 $rows=[['key'=>'person:5','person_id'=>'5','name'=>'Club rider','club'=>'Kovo','discipline'=>'Sprint','heat'=>'1','status'=>'finished','time'=>'00:12.345','rank'=>'1'],['key'=>'ext:1','person_id'=>'','name'=>'External','club'=>'Other','discipline'=>'Sprint','heat'=>'1','status'=>'dns','time'=>'','rank'=>'']];
 raceResultsWrite($p,1,7,true,0,'draft',$rows,'Synthetic draft',str_repeat('a',32));
 $published=raceResultsWrite($p,1,7,true,1,'published',$rows,'Synthetic publish',str_repeat('b',32));
 $check(count(raceResultsRevision($p,1,$published['id'])['rows'])===2,'published internal and external results');
 try{raceResultsWrite($p,1,7,true,1,'draft',$rows,'Stale version',str_repeat('c',32));throw new RuntimeException('Stale version accepted');}catch(DomainException){$check((int)raceResultsState($p,1)['version']===2,'race version conflict blocked');}
 echo "OK race + KIS review MariaDB smoke\n";
}finally{
 if(!preg_match('/^evidence_race_kis_smoke_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Invalid cleanup target.');
 $server->exec('DROP DATABASE '.$name);foreach(glob($archive.'/*.raw') as $f)unlink($f);rmdir($archive);putenv($old===false?'KIS_SOURCE_ARCHIVE_DIR':'KIS_SOURCE_ARCHIVE_DIR='.$old);
}
