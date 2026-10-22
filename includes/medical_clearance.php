<?php
declare(strict_types=1);
require_once __DIR__.'/operational_cases.php';
require_once __DIR__.'/coach_scope.php';
require_once __DIR__.'/family_portal.php';
require_once __DIR__.'/private_storage.php';

function medicalClearanceText(array $input,string $key,int $max=1000):string {
 $value=$input[$key]??'';
 if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new InvalidArgumentException('Vyplňte platné pole '.$key.'.');
 return trim($value);
}
function medicalClearanceDate(string $date):string {
 $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('Neplatné datum.');return $date;
}
function medicalClearanceRegistrar(PDO $pdo):int {
 $actor=(int)($_SESSION['trener_id']??0);
 if(!staffActivePositionIs('registrar'))throw new DomainException('Podklady spravuje pověřený registrář.');
 operationalCaseActor($pdo,$actor,'registrar');return $actor;
}
function medicalClearanceLock(PDO $pdo):string {return $pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
function medicalClearanceWatch(PDO $pdo,int $id):array {
 $q=$pdo->prepare('SELECT w.*,p.reminder_days,p.activity_rule FROM medical_clearance_watches w JOIN medical_clearance_policies p ON p.id=w.policy_id WHERE w.id=?'.medicalClearanceLock($pdo));$q->execute([$id]);
 return $q->fetch(PDO::FETCH_ASSOC)?:throw new DomainException('Sledovaný podklad neexistuje.');
}
function medicalClearanceEvent(PDO $pdo,int $watch,int $actor,string $action,string $reason,?int $record=null):void {
 $q=$pdo->prepare('INSERT INTO medical_clearance_events(watch_id,record_id,actor_id,action,reason) VALUES(?,?,?,?,?)');$q->execute([$watch,$record,$actor,$action,$reason]);
}
/** Policy is an explicit human decision, never a medical judgement or an automatic admission ban. */
function medicalClearancePolicyCreate(PDO $pdo,array $input):int {
 $actor=medicalClearanceRegistrar($pdo);
 $values=[];foreach(['purpose'=>100,'legal_basis'=>1000,'retention_rule'=>1000,'activity_rule'=>1000]as$key=>$max)$values[]=medicalClearanceText($input,$key,$max);
 $days=filter_var($input['reminder_days']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>365]]);
 if($days===false||($input['confirmed']??'')!=='1')throw new InvalidArgumentException('Potvrďte pravidla a počet dní upozornění.');
 $pdo->beginTransaction();try{
  medicalClearanceRegistrar($pdo);
  $q=$pdo->prepare('INSERT INTO medical_clearance_policies(purpose,legal_basis,retention_rule,activity_rule,reminder_days,approved_by) VALUES(?,?,?,?,?,?)');$q->execute([...$values,$days,$actor]);$id=(int)$pdo->lastInsertId();$pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function medicalClearanceWatchCreate(PDO $pdo,array $input):int {
 $actor=medicalClearanceRegistrar($pdo);$reason=medicalClearanceText($input,'reason');$person=(int)($input['sportovec_id']??0);$policy=(int)($input['policy_id']??0);
 $pdo->beginTransaction();try {
  medicalClearanceRegistrar($pdo);
  $q=$pdo->prepare('SELECT id FROM sportovci WHERE id=?'.medicalClearanceLock($pdo));$q->execute([$person]);if(!$q->fetchColumn())throw new DomainException('Vyberte existující osobu.');
  $q=$pdo->prepare('SELECT * FROM medical_clearance_policies WHERE id=?');$q->execute([$policy]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p)throw new DomainException('Nejprve potvrďte účel a pravidla podkladu.');
  $q=$pdo->prepare('SELECT id FROM medical_clearance_watches WHERE sportovec_id=? AND purpose=?');$q->execute([$person,$p['purpose']]);$id=(int)$q->fetchColumn();
  if(!$id){$q=$pdo->prepare('INSERT INTO medical_clearance_watches(sportovec_id,purpose,policy_id,next_review_on,created_by) VALUES(?,?,?,?,?)');$q->execute([$person,$p['purpose'],$policy,date('Y-m-d'),$actor]);$id=(int)$pdo->lastInsertId();medicalClearanceEvent($pdo,$id,$actor,'watch_created',$reason);}
  $pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
/** New evidence is pending. Existing verified evidence remains usable until replacement is verified. */
function medicalClearanceRecord(PDO $pdo,array $input,?array $upload=null):int {
 $actor=medicalClearanceRegistrar($pdo);$reason=medicalClearanceText($input,'reason');$watch=(int)($input['watch_id']??0);
 $issued=medicalClearanceDate((string)($input['issued_on']??''));$until=medicalClearanceDate((string)($input['valid_until']??''));
 if($issued>date('Y-m-d')||$until<$issued)throw new InvalidArgumentException('Platnost musí vycházet z doloženého podkladu.');
 $location=trim((string)($input['original_location']??''));if(mb_strlen($location)>1000)throw new InvalidArgumentException('Umístění originálu je příliš dlouhé.');
 $hasFile=$upload!==null&&(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
 if(!$hasFile&&$location==='')throw new InvalidArgumentException('Doložte soukromý soubor nebo umístění papírového originálu.');
 if($hasFile){privateStorageValidateUploadBatch([$upload],1);if(!is_uploaded_file((string)$upload['tmp_name']))throw new InvalidArgumentException('Neplatné nahrání souboru.');}
 $key=null;$hash=null;$pdo->beginTransaction();try {
  medicalClearanceRegistrar($pdo);medicalClearanceWatch($pdo,$watch);
  $q=$pdo->prepare("SELECT id FROM medical_clearance_records WHERE watch_id=? AND state='pending'");$q->execute([$watch]);if($q->fetchColumn())throw new DomainException('Nejprve rozhodněte rozpracovaný podklad.');
  if($hasFile){$hash=hash_file('sha256',$upload['tmp_name']);$key=privateStorageStore($upload['tmp_name'],PRIVATE_STORAGE_MEDICAL_DOCUMENTS);}
  $q=$pdo->prepare('INSERT INTO medical_clearance_records(watch_id,issued_on,valid_until,original_location,storage_key,file_sha256,recorded_by) VALUES(?,?,?,?,?,?,?)');$q->execute([$watch,$issued,$until,$location,$key,$hash,$actor]);$id=(int)$pdo->lastInsertId();
  medicalClearanceEvent($pdo,$watch,$actor,'recorded',$reason,$id);$pdo->prepare('UPDATE medical_clearance_watches SET next_review_on=? WHERE id=?')->execute([date('Y-m-d'),$watch]);$pdo->commit();return$id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($key!==null&&($path=privateStorageResolve($key))!==null)unlink($path);throw$e;}
}
function medicalClearanceDecide(PDO $pdo,int $id,int $revision,string $action,string $reason):void {
 $actor=medicalClearanceRegistrar($pdo);$reason=medicalClearanceText(['reason'=>$reason],'reason');
 if(!in_array($action,['verified','rejected','revoked'],true))throw new InvalidArgumentException('Neplatné rozhodnutí.');
 $pdo->beginTransaction();try {
  medicalClearanceRegistrar($pdo);
  $q=$pdo->prepare('SELECT watch_id FROM medical_clearance_records WHERE id=?');$q->execute([$id]);$watch=medicalClearanceWatch($pdo,(int)$q->fetchColumn());
  $q=$pdo->prepare('SELECT * FROM medical_clearance_records WHERE id=?'.medicalClearanceLock($pdo));$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row||(int)$row['revision']!==$revision||!in_array($row['state'],['pending','verified','superseded'],true)||($row['state']!=='pending'&&$action!=='revoked'))throw new DomainException('Podklad mezitím změnil stav. Obnovte přehled.');
  $old=null;
  if($action==='verified') {
   if($row['storage_key']!==null){$path=privateStorageResolve($row['storage_key']);if(!$path||!hash_equals($row['file_sha256'],(string)hash_file('sha256',$path)))throw new DomainException('Důkaz chybí nebo nesouhlasí jeho otisk.');}
   $q=$pdo->prepare("SELECT id FROM medical_clearance_records WHERE watch_id=? AND state='verified'");$q->execute([$watch['id']]);$old=(int)$q->fetchColumn()?:null;
   if($old){$pdo->prepare("UPDATE medical_clearance_records SET state='superseded',revision=revision+1 WHERE id=?")->execute([$old]);medicalClearanceEvent($pdo,(int)$watch['id'],$actor,'superseded','Nahrazen ověřeným podkladem #'.$id.'. '.$reason,$old);}
  }
  $q=$pdo->prepare('UPDATE medical_clearance_records SET state=?,revision=revision+1,verified_by=?,verified_at=?,replaces_id=? WHERE id=?');
  $q->execute([$action,$action==='verified'?$actor:$row['verified_by'],$action==='verified'?date('Y-m-d H:i:s'):$row['verified_at'],$old??$row['replaces_id'],$id]);
  medicalClearanceEvent($pdo,(int)$watch['id'],$actor,$action,$reason,$id);
  // Rejected drafts or revoked historical predecessors must not turn a still
  // valid current proof into a permanently unresolvable expiry work item.
  $next=date('Y-m-d');
  $q=$pdo->prepare("SELECT state,valid_until FROM medical_clearance_records WHERE watch_id=? AND state IN ('pending','verified') ORDER BY CASE state WHEN 'pending' THEN 0 ELSE 1 END,id DESC LIMIT 1");$q->execute([$watch['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);
  if($current&&$current['state']==='verified')$next=(new DateTimeImmutable($current['valid_until']))->modify('-'.(int)$watch['reminder_days'].' days')->format('Y-m-d');
  $pdo->prepare('UPDATE medical_clearance_watches SET next_review_on=? WHERE id=?')->execute([$next,$watch['id']]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
/** Pure reduction intentionally has no diagnosis, scan location, issuer or staff notes. */
function medicalClearanceStatus(PDO $pdo,int $person,string $purpose,string $on):array {
 medicalClearanceDate($on);
 $q=$pdo->prepare('SELECT r.* FROM medical_clearance_records r JOIN medical_clearance_watches w ON w.id=r.watch_id WHERE w.sportovec_id=? AND w.purpose=? ORDER BY r.id DESC');$q->execute([$person,$purpose]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 $state='missing';$until=null;
 $byId=[];foreach($rows as$r)$byId[(int)$r['id']]=$r;
 $hasVerified=false;
 foreach($rows as$r)if($r['state']==='verified'){
  $hasVerified=true;$seen=[];
  // Replacement only takes over from its own issued date. Earlier dates can
  // use the explicitly linked predecessor, never an arbitrary older record.
  while($on<$r['issued_on']&&!empty($r['replaces_id'])){
   $previous=$byId[(int)$r['replaces_id']]??null;
   if(!$previous||isset($seen[(int)$r['id']])||$previous['state']!=='superseded'){
    $state=($previous['state']??'')==='revoked'?'revoked':'missing';$r=null;break;
   }
   $seen[(int)$r['id']]=true;$r=$previous;
  }
  if($r!==null){$until=$r['valid_until'];$state=$on<$r['issued_on']?'not_yet_valid':($on>$until?'expired':'valid');}
  break;
 }
 // Revoking the current evidence never reactivates an older superseded scan.
 if(!$hasVerified&&$rows)$state=match($rows[0]['state']){'pending'=>'pending','revoked'=>'revoked',default=>'missing'};
 return['status'=>$state,'valid_until'=>$until,'on'=>$on,'next_step'=>$state==='valid'?'Podklad je k vybranému dni platný.':'Kontaktujte registráře k doložení nebo ověření podkladu. O účasti rozhodne pověřený pracovník.'];
}
function medicalClearanceView(PDO $pdo,int $person,string $on,?int $account=null):array {
 if($account!==null){if(!familyPortalCanViewPerson($pdo,$account,$person))throw new DomainException('K osobě nemáte platné rodinné oprávnění.');}
 else {$position=staffActivePosition();if(!in_array($position,['registrar','sports_lead','coach'],true))throw new DomainException('Přístup odepřen.');operationalCaseActor($pdo,(int)($_SESSION['trener_id']??0),$position);if(!coachScopeCanViewPerson($pdo,$person))throw new DomainException('Osoba není ve svěřeném rozsahu.');}
 $q=$pdo->prepare('SELECT purpose FROM medical_clearance_watches WHERE sportovec_id=? ORDER BY purpose');$q->execute([$person]);$result=[];
 foreach($q->fetchAll(PDO::FETCH_COLUMN)as$purpose)$result[]=['purpose'=>$purpose]+medicalClearanceStatus($pdo,$person,$purpose,$on);return$result;
}
function medicalClearanceDownload(PDO $pdo,int $id):string {
 $actor=medicalClearanceRegistrar($pdo);$pdo->beginTransaction();try{
  medicalClearanceRegistrar($pdo);
  $q=$pdo->prepare('SELECT watch_id FROM medical_clearance_records WHERE id=?');$q->execute([$id]);$watch=(int)$q->fetchColumn();medicalClearanceWatch($pdo,$watch);
  $q=$pdo->prepare('SELECT * FROM medical_clearance_records WHERE id=?'.medicalClearanceLock($pdo));$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r||!$r['storage_key']||!($path=privateStorageResolve($r['storage_key']))||!hash_equals($r['file_sha256'],(string)hash_file('sha256',$path)))throw new DomainException('Soukromý podklad není dostupný.');
  medicalClearanceEvent($pdo,(int)$r['watch_id'],$actor,'file_viewed','Zobrazení soukromého podkladu.',$id);$pdo->commit();return$path;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
function medicalClearanceContact(PDO $pdo,int $watch,string $reason):void {
 $actor=medicalClearanceRegistrar($pdo);$reason=medicalClearanceText(['reason'=>$reason],'reason');$pdo->beginTransaction();try{
  medicalClearanceRegistrar($pdo);medicalClearanceWatch($pdo,$watch);medicalClearanceEvent($pdo,$watch,$actor,'manual_contact',$reason);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
