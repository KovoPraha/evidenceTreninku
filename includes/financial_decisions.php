<?php
declare(strict_types=1);
require_once __DIR__.'/accounting_export.php';

function financialDecisionGet(PDO $pdo,int $id,bool $lock=false):array
{
 $q=$pdo->prepare('SELECT * FROM financial_decisions WHERE id=?'.($lock&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new DomainException('Rozhodnutí nebylo nalezeno.');return $row;
}

/** Evidence of an actual decision; this does not grant a role, pay money or amend an approved claim. */
function financialDecisionRecord(PDO $pdo,int $actor,array $input,?array $upload=null):int
{
 accountingExportActor($pdo,$actor);
 $title=trim((string)($input['title']??''));$kind=(string)($input['kind']??'');$state=(string)($input['state']??'pending');
 $from=(string)($input['valid_from']??'');$to=trim((string)($input['valid_to']??''));accountingExportDates($from,$from);if($to!==''){accountingExportDates($to,$to);if($to<$from)throw new DomainException('Konec platnosti předchází začátku.');}
 $source=trim((string)($input['source_reference']??''));$body=trim((string)($input['decision_text']??''));$location=trim((string)($input['original_location']??''));$reason=trim((string)($input['reason']??''));$replaces=(int)($input['replaces_id']??0);
 if($title===''||mb_strlen($title)>160||!in_array($kind,['approval_rules','rates','reward_basis','accounting_method','financial_task'],true)||!in_array($state,['pending','confirmed','withdrawn'],true)||$source===''||mb_strlen($source)>1000||$body===''||mb_strlen($body)>20000||$reason===''||mb_strlen($reason)>1000||mb_strlen($location)>1000)throw new DomainException('Vyplňte druh, název, skutečný zdroj rozhodnutí, obsah a důvod.');
 if(($input['confirmed']??'')!=='1')throw new DomainException('Potvrďte záznam skutečného podkladu.');
 $hasFile=$upload!==null&&(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
 if($hasFile&&(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new DomainException('Přílohu se nepodařilo přijmout.');
 if($state==='confirmed'&&!$hasFile&&$location==='')throw new DomainException('Potvrzený podklad potřebuje přílohu nebo umístění papírového originálu.');
 $storage=null;$sha=null;$pdo->beginTransaction();
 try{
  accountingExportActor($pdo,$actor);
  if($replaces){$old=financialDecisionGet($pdo,$replaces,true);$q=$pdo->prepare('SELECT id FROM financial_decisions WHERE replaces_id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$replaces]);if($q->fetchColumn())throw new DomainException('Podklad již má novější verzi. Obnovte stránku.');if($old['kind']!==$kind)throw new DomainException('Nová verze musí zachovat druh podkladu.');}
  elseif($state==='withdrawn')throw new DomainException('Odvolání musí odkazovat na předchozí verzi.');
  if($hasFile){$sha=hash_file('sha256',(string)$upload['tmp_name']);$storage=privateStorageStore((string)$upload['tmp_name'],PRIVATE_STORAGE_ACCOUNTING_DOCUMENTS);}
  $q=$pdo->prepare('INSERT INTO financial_decisions(title,kind,state,valid_from,valid_to,source_reference,decision_text,original_location,storage_key,file_sha256,replaces_id,created_by,reason) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $q->execute([$title,$kind,$state,$from,$to?:null,$source,$body,$location,$storage,$sha,$replaces?:null,$actor,$reason]);$id=(int)$pdo->lastInsertId();$pdo->commit();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($storage){$path=privateStorageResolve($storage);if($path)is_file($path)&&unlink($path);}throw $e;}
}

function financialDecisionFile(PDO $pdo,int $actor,int $id):array
{
 accountingExportActor($pdo,$actor);$row=financialDecisionGet($pdo,$id);$path=privateStorageResolve((string)$row['storage_key']);if(!$path||!hash_equals((string)$row['file_sha256'],hash_file('sha256',$path)))throw new DomainException('Příloha chybí nebo její otisk nesouhlasí.');return ['path'=>$path,'row'=>$row];
}

/** Explicit human handover is separate from download and from payment status. */
function accountingExportHandover(PDO $pdo,int $actor,int $id,string $action,int $expectedEvent,string $reason,bool $confirmed):void
{
 if(!$confirmed||trim($reason)===''||mb_strlen($reason)>1000||!in_array($action,['handed_over','returned','accepted'],true))throw new DomainException('Potvrďte krok předání a uveďte komu, kdy a podle jakého dokladu.');
 $pdo->beginTransaction();try{
  accountingExportActor($pdo,$actor);$q=$pdo->prepare('SELECT id FROM accounting_export_batches WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);if(!$q->fetchColumn())throw new DomainException('Export nebyl nalezen.');
  $q=$pdo->prepare("SELECT id,action FROM accounting_export_events WHERE batch_id=? AND action IN ('handed_over','returned','accepted') ORDER BY id DESC LIMIT 1".($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);$last=$q->fetch(PDO::FETCH_ASSOC);if((int)($last['id']??0)!==$expectedEvent)throw new DomainException('Stav předání se změnil; obnovte stránku.');
  $allowed=['new'=>['handed_over'],'handed_over'=>['returned','accepted'],'returned'=>['handed_over'],'accepted'=>['returned']];if(!in_array($action,$allowed[$last['action']??'new'],true))throw new DomainException('Tento krok nenavazuje na stav předání.');
  accountingExportEvent($pdo,$id,$actor,$action,trim($reason));$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
