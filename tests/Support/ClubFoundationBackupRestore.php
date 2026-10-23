<?php
declare(strict_types=1);
// Only restores an owned synthetic backup into a NEW database on the isolated instance.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if (($descriptor['synthetic_only']??false)!==true || (int)$descriptor['db_port']!==33426) throw new RuntimeException('Not the synthetic environment.');
$directory=$runtime.'/backups-r07-r12';$files=glob($directory.'/evidence_*.manifest.json')?:[];rsort($files);
if (!$files) throw new RuntimeException('Create the isolated backup first.');
$manifest=json_decode((string)file_get_contents($files[0]),true,512,JSON_THROW_ON_ERROR);
if ($manifest['database_name']!=='evidence_b1' || $manifest['ownership_contract']!=='2026-10-22.2' || basename($manifest['sql_file'])!==$manifest['sql_file']) throw new RuntimeException('Unexpected backup manifest.');
$path=$directory.'/'.$manifest['sql_file'];
if (!hash_equals($manifest['sha256'],hash_file('sha256',$path))) throw new RuntimeException('Backup checksum mismatch.');
$sql=gzdecode((string)file_get_contents($path));
if (!is_string($sql) || !str_ends_with($sql,"-- EVIDENCE BACKUP COMPLETE\n") || preg_match('/^\s*(?:USE\s|CREATE\s+DATABASE|DROP\s+DATABASE)/im',$sql)) throw new RuntimeException('Unexpected dump contents.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$root->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if ((int)$identity['port']!==33426 || strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/'))) throw new RuntimeException('Database identity mismatch.');
$target='evidence_b2_restore_'.bin2hex(random_bytes(4));
// No DROP DATABASE, no reuse of an existing target. Restore user has no source grants.
$root->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4');
$root->exec("CREATE USER IF NOT EXISTS 'b2_restore'@'127.0.0.1' IDENTIFIED BY ''");
$root->exec("GRANT ALL ON `".$target."`.* TO 'b2_restore'@'127.0.0.1'");
$root=null;
$process=proc_open(['C:/xampp/mysql/bin/mysql.exe','--host=127.0.0.1','--port=33426','--protocol=tcp','--user=b2_restore','--default-character-set=utf8mb4',$target],
    [0=>['pipe','r'],1=>['file',$runtime.'/b2-restore.stdout','w'],2=>['file',$runtime.'/b2-restore.stderr','w']],$pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot start restore client.');
$offset=0;while($offset<strlen($sql)) {$written=fwrite($pipes[0],substr($sql,$offset,65536));if($written===false||$written===0)throw new RuntimeException('Restore input failed.');$offset+=$written;}
fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Restore failed; inspect isolated stderr.');
$restored=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$target.';charset=utf8mb4','b2_restore','',$options);
foreach($manifest['tables'] as $table=>$expected) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) throw new RuntimeException('Invalid table name.');
    if ((int)$restored->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==(int)$expected) throw new RuntimeException('Restored count mismatch: '.$table);
}
$newTables=['person_identity_reviews','person_identity_review_events','club_paper_records','club_paper_record_events','operational_cases','operational_case_events','coach_scope_grants','coach_scope_events','booking_notification_context','operational_job_runs','notification_authorization_snapshots'];
foreach($newTables as $table)if(!array_key_exists($table,$manifest['tables']))throw new RuntimeException('New owned table missing: '.$table);
$triggers=$restored->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN);
sort($triggers);$expected=$manifest['triggers'];sort($expected);if($triggers!==$expected)throw new RuntimeException('Trigger mismatch.');
// Private paper files are covered by the existing private-directory backup, not the SQL dump.
require_once dirname(__DIR__,2).'/config.php';
require_once dirname(__DIR__,2).'/includes/private_storage.php';
$privateCount=0;
foreach($restored->query('SELECT storage_key,file_sha256 FROM club_paper_records WHERE storage_key IS NOT NULL') as $record) {
    $file=privateStorageResolve($record['storage_key']);
    if (!$file || !hash_equals($record['file_sha256'],hash_file('sha256',$file))) throw new RuntimeException('Restored reference lacks intact private evidence.');
    $privateCount++;
}
$report=['status'=>'PASS','database'=>$target,'tables'=>count($manifest['tables']),'new_owned_tables'=>count($newTables),'triggers'=>count($triggers),'private_references_checked'=>$privateCount,'backup_sha256'=>$manifest['sha256'],'private_storage_restored'=>false];
file_put_contents($runtime.'/club-foundation-backup-restore.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
