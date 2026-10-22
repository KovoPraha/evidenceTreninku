<?php
declare(strict_types=1);
// Only restores an owned synthetic backup into a NEW database on the isolated instance.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if (($descriptor['synthetic_only']??false)!==true || (int)$descriptor['db_port']!==33426) throw new RuntimeException('Not the synthetic environment.');
$directory=$runtime.'/backups-r18-r24';$files=glob($directory.'/evidence_*.manifest.json')?:[];rsort($files);
if (!$files) throw new RuntimeException('Create the isolated backup first.');
$manifest=json_decode((string)file_get_contents($files[0]),true,512,JSON_THROW_ON_ERROR);
if ($manifest['database_name']!=='evidence_b1' || $manifest['ownership_contract']!=='2026-10-22.4' || basename($manifest['sql_file'])!==$manifest['sql_file']) throw new RuntimeException('Unexpected backup manifest.');
$path=$directory.'/'.$manifest['sql_file'];
if (!hash_equals($manifest['sha256'],hash_file('sha256',$path))) throw new RuntimeException('Backup checksum mismatch.');
$sql=gzdecode((string)file_get_contents($path));
if (!is_string($sql) || !str_ends_with($sql,"-- EVIDENCE BACKUP COMPLETE\n") || preg_match('/^\s*(?:USE\s|CREATE\s+DATABASE|DROP\s+DATABASE)/im',$sql)) throw new RuntimeException('Unexpected dump contents.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$root->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if ((int)$identity['port']!==33426 || strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/'))) throw new RuntimeException('Database identity mismatch.');
$target='evidence_b4_restore_'.bin2hex(random_bytes(4));
// No DROP DATABASE, no reuse of an existing target. Restore user has no source grants.
$root->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4');
$root->exec("CREATE USER IF NOT EXISTS 'b4_restore'@'127.0.0.1' IDENTIFIED BY ''");
$root->exec("GRANT ALL ON `".$target."`.* TO 'b4_restore'@'127.0.0.1'");
$root=null;
$process=proc_open(['C:/xampp/mysql/bin/mysql.exe','--host=127.0.0.1','--port=33426','--protocol=tcp','--user=b4_restore','--default-character-set=utf8mb4',$target],
    [0=>['pipe','r'],1=>['file',$runtime.'/b4-restore.stdout','w'],2=>['file',$runtime.'/b4-restore.stderr','w']],$pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot start restore client.');
$offset=0;while($offset<strlen($sql)) {$written=fwrite($pipes[0],substr($sql,$offset,65536));if($written===false||$written===0)throw new RuntimeException('Restore input failed.');$offset+=$written;}
fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Restore failed; inspect isolated stderr.');
$restored=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$target.';charset=utf8mb4','b4_restore','',$options);
foreach($manifest['tables'] as $table=>$expected) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) throw new RuntimeException('Invalid table name.');
    if ((int)$restored->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==(int)$expected) throw new RuntimeException('Restored count mismatch: '.$table);
}
$newTables=['shop_stock_policy','shop_stock_balances','shop_stock_movements','shop_stock_reservations','shop_stock_lots','shop_stock_lot_uses','shop_stock_cost_revisions','shop_stock_counts','shop_fulfillments','shop_fulfillment_items','shop_delivery_events','shop_assisted_sales','shop_return_cases','shop_return_events','shop_return_finance_links','shop_stock_documents'];
foreach($newTables as $table)if(!array_key_exists($table,$manifest['tables']))throw new RuntimeException('New owned table missing: '.$table);
$triggers=$restored->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN);
sort($triggers);$expected=$manifest['triggers'];sort($expected);if($triggers!==$expected)throw new RuntimeException('Trigger mismatch.');
// Archive and independently restore the isolated private tree. Never touch the original.
require_once dirname(__DIR__,2).'/config.php';
require_once dirname(__DIR__,2).'/includes/private_storage.php';
$privateRoot=realpath($runtime.'/private');
if(!$privateRoot||strtolower(str_replace('\\','/',privateStorageRoot()))!==strtolower(str_replace('\\','/',$privateRoot)))throw new RuntimeException('Private root is not the isolated sandbox.');
$privateZip=$directory.'/private-'.bin2hex(random_bytes(8)).'.zip';
$zip=new ZipArchive();if($zip->open($privateZip,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot archive private tree.');
$fileManifest=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($privateRoot,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $entry){if(!$entry->isFile()||$entry->isLink())continue;$relative=str_replace('\\','/',substr($entry->getPathname(),strlen($privateRoot)+1));if(str_contains($relative,'..'))throw new RuntimeException('Invalid private path.');$zip->addFile($entry->getPathname(),$relative);$fileManifest[$relative]=hash_file('sha256',$entry->getPathname());}
if(!$zip->close())throw new RuntimeException('Private archive failed.');
$restoreRoot=$runtime.'/private-restore-'.bin2hex(random_bytes(8));if(!mkdir($restoreRoot,0700))throw new RuntimeException('Cannot create separate restore directory.');
$zip=new ZipArchive();if($zip->open($privateZip)!==true)throw new RuntimeException('Cannot read private archive.');
foreach($fileManifest as $relative=>$hash){$bytes=$zip->getFromName($relative);if(!is_string($bytes)||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Archived file checksum mismatch.');$destination=$restoreRoot.'/'.$relative;if(!is_dir(dirname($destination)))mkdir(dirname($destination),0700,true);if(file_put_contents($destination,$bytes)===false||!hash_equals($hash,hash_file('sha256',$destination)))throw new RuntimeException('Restored private checksum mismatch.');}
$zip->close();putenv('APP_PRIVATE_STORAGE_ROOT='.$restoreRoot);
$privateCount=0;
foreach($restored->query("SELECT pdf_storage_key AS storage_key,pdf_sha256 AS file_sha256 FROM accounting_documents WHERE status='issued' UNION ALL SELECT storage_key,archive_sha256 AS file_sha256 FROM accounting_export_batches UNION ALL SELECT storage_key,pdf_sha256 AS file_sha256 FROM shop_stock_documents UNION ALL SELECT storage_key,file_sha256 FROM club_paper_records WHERE storage_key IS NOT NULL") as $record) {
    $file=privateStorageResolve($record['storage_key']);
    if (!$file || !hash_equals($record['file_sha256'],hash_file('sha256',$file))) throw new RuntimeException('Restored reference lacks intact private evidence.');
    $privateCount++;
}
// Verify restored stock projections and immutable source ties, beyond table counts.
$stockChecks=0;
foreach($restored->query('SELECT * FROM shop_stock_balances') as $balance){
 $q=$restored->prepare('SELECT COALESCE(SUM(physical_delta),0) AS p,COALESCE(SUM(reserved_delta),0) AS r,COALESCE(SUM(blocked_delta),0) AS b FROM shop_stock_movements WHERE variant_id=?');$q->execute([$balance['variant_id']]);$sum=$q->fetch();
 if([(int)$sum['p'],(int)$sum['r'],(int)$sum['b']]!==[(int)$balance['physical_quantity'],(int)$balance['reserved_quantity'],(int)$balance['blocked_quantity']])throw new RuntimeException('Restored stock movements do not reconcile.');
 $q=$restored->prepare('SELECT COALESCE(SUM(remaining_quantity),0),COALESCE(SUM(blocked_quantity),0) FROM shop_stock_lots WHERE variant_id=?');$q->execute([$balance['variant_id']]);$lot=$q->fetch(PDO::FETCH_NUM);if([(int)$lot[0],(int)$lot[1]]!==[(int)$balance['physical_quantity'],(int)$balance['blocked_quantity']])throw new RuntimeException('Restored FIFO lots do not reconcile.');
 $stockChecks++;
}
$report=['status'=>'PASS','stock_variants_reconciled'=>$stockChecks,'database'=>$target,'tables'=>count($manifest['tables']),'new_owned_tables'=>count($newTables),'triggers'=>count($triggers),'private_references_checked'=>$privateCount,'backup_sha256'=>$manifest['sha256'],'private_storage_restored'=>true,'private_files_restored'=>count($fileManifest),'private_archive_sha256'=>hash_file('sha256',$privateZip)];
file_put_contents($runtime.'/inventory-backup-restore.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
