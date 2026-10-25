<?php
declare(strict_types=1);
require_once __DIR__.'/notification_delivery_guard.php';
require_once __DIR__.'/roster_hierarchy.php';

final class RosterMessageException extends RuntimeException{}

function rosterMessageText(string$value,int$max,string$label):string{$value=str_replace(["\r\n","\r"],"\n",trim($value));if($value===''||mb_strlen($value,'UTF-8')>$max||preg_match('/[\r\n]/u',$label==='Předmět'?$value:'')===1)throw new InvalidArgumentException($label.' není platný text.');return$value;}

/** @return array{team:array<string,mixed>,subject:string,body:string,recipients:list<array<string,mixed>>,fingerprint:string,manual_person_ids:list<int>} */
function rosterMessagePreview(PDO$pdo,int$teamId,string$subject,string$body,?string$onDate=null):array
{
    $subject=rosterMessageText($subject,255,'Předmět');$body=rosterMessageText($body,10000,'Zpráva');$onDate??=(new DateTimeImmutable('today'))->format('Y-m-d');
    $audience=rosterMessageAudience($pdo,$teamId,$onDate);$team=$audience['team'];$recipients=$audience['recipients'];
    $fingerprint=hash('sha256',json_encode(['team_id'=>$teamId,'subject'=>$subject,'body'=>$body,'recipients'=>$recipients,'person_ids'=>$audience['person_ids'],'manual_person_ids'=>$audience['manual_person_ids']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return['team'=>$team,'subject'=>$subject,'body'=>$body,'recipients'=>$recipients,'fingerprint'=>$fingerprint,'manual_person_ids'=>$audience['manual_person_ids']];
}

/** Same effective roster as sports planning, including standing groups and dated descendants. */
function rosterMessageAudience(PDO $pdo,int $teamId,string $onDate):array {
    $onDate=kisRosterDate($onDate);
    $q=$pdo->prepare("SELECT t.id,t.name,s.name AS season_name FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id WHERE t.id=? AND t.status='active' AND (t.season_id IS NULL OR s.status='active')");$q->execute([$teamId]);$team=$q->fetch(PDO::FETCH_ASSOC);if(!$team)throw new RosterMessageException('Aktivní skupina nebo soupiska nebyla nalezena.');
    $ids=kisRosterEffectiveMemberIds($pdo,$teamId,$onDate);$recipients=[];$covered=[];
    if($ids){
        // For today's delivery, a relationship must be valid now, not merely overlap some part of today.
        $at=$onDate===date('Y-m-d')?date('Y-m-d H:i:s'):$onDate.' 00:00:00';
        $q=$pdo->prepare("SELECT r.sportovec_id,a.id AS account_id,a.email,a.jmeno,a.prijmeni FROM account_person_roles r JOIN verejni_uzivatele a ON a.id=r.account_id AND a.aktivni=1 AND a.email_overeno=1 WHERE r.sportovec_id IN (".implode(',',array_fill(0,count($ids),'?')).") AND r.status='approved' AND r.relation_role IN ('self','guardian') AND r.valid_from<=? AND (r.valid_to IS NULL OR r.valid_to>?) ORDER BY a.prijmeni,a.jmeno,a.id,r.sportovec_id");$q->execute([...$ids,$at,$at]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){if(!filter_var($r['email'],FILTER_VALIDATE_EMAIL))continue;$covered[(int)$r['sportovec_id']]=true;$recipients[(int)$r['account_id']]=['account_id'=>(int)$r['account_id'],'email'=>$r['email'],'name'=>trim($r['jmeno'].' '.$r['prijmeni'])];}
    }
    return ['team'=>$team,'person_ids'=>$ids,'recipients'=>array_values($recipients),'manual_person_ids'=>array_values(array_filter($ids,static fn($id)=>!isset($covered[$id])))];
}

/** @return array{id:int,recipient_count:int} */
function rosterMessageEnqueue(PDO$pdo,int$teamId,string$subject,string$body,string$fingerprint,int$actorId,string$reason,bool$confirmed):array
{
    $reason=trim($reason);if(!$confirmed||$actorId<1||mb_strlen($reason,'UTF-8')<5||mb_strlen($reason,'UTF-8')>1000||preg_match('/^[a-f0-9]{64}$/D',$fingerprint)!==1)throw new InvalidArgumentException('Odeslání vyžaduje náhled, důvod a výslovné potvrzení.');
    $pdo->beginTransaction();try{$lock='SELECT id FROM club_teams WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$lock.=' FOR UPDATE';$teamLock=$pdo->prepare($lock);$teamLock->execute([$teamId]);if($teamLock->fetchColumn()===false)throw new RosterMessageException('Soupiska nebyla nalezena.');$preview=rosterMessagePreview($pdo,$teamId,$subject,$body);if(!hash_equals($preview['fingerprint'],$fingerprint))throw new RosterMessageException('Soupiska nebo příjemci se od náhledu změnili. Obnovte náhled.');if($preview['recipients']===[])throw new RosterMessageException('Soupiska nemá žádný aktivní ověřený účet příjemce.');$pdo->prepare("INSERT INTO roster_messages(team_id,subject_plain,body_plain,recipient_count,preview_fingerprint,status,created_by_trainer_id,reason) VALUES(?,?,?,?,?,'queued',?,?)")->execute([$teamId,$preview['subject'],$preview['body'],count($preview['recipients']),$fingerprint,$actorId,$reason]);$id=(int)$pdo->lastInsertId();$insert=$pdo->prepare('INSERT INTO roster_message_recipients(message_id,account_id,recipient_email,recipient_name) VALUES(?,?,?,?)');foreach($preview['recipients']as$recipient)$insert->execute([$id,$recipient['account_id'],$recipient['email'],$recipient['name']]);$pdo->commit();return['id'=>$id,'recipient_count'=>count($preview['recipients'])];}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof RosterMessageException)throw$exception;throw new RosterMessageException('Zprávu se nepodařilo zařadit bez částečného zápisu.',0,$exception);}
}

/** @return array<string,mixed>|null */
function rosterMessageClaim(PDO$pdo):?array{$pdo->beginTransaction();try{$mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$stale=$mysql?'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE':"datetime('now','-15 minutes')";notificationQueueHoldStale($pdo,'roster_message_recipients',$stale);$sql="SELECT r.*,m.team_id,m.subject_plain,m.body_plain FROM roster_message_recipients r JOIN roster_messages m ON m.id=r.message_id WHERE r.status='pending' AND r.available_at<=CURRENT_TIMESTAMP ORDER BY r.id LIMIT 1";if($mysql)$sql.=' FOR UPDATE';$row=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);if(!$row){$pdo->commit();return null;}$token=bin2hex(random_bytes(16));$pdo->prepare("UPDATE roster_message_recipients SET status='processing',attempts=attempts+1,claimed_at=CURRENT_TIMESTAMP,claim_token=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$token,$row['id']]);$pdo->commit();$row['claim_token']=$token;$row['attempts']=(int)$row['attempts']+1;return$row;}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();throw$exception;}}

function rosterMessageComplete(PDO$pdo,array$row,bool$sent,string$error=''):void{$id=(int)$row['id'];$token=(string)$row['claim_token'];if($sent){$statement=$pdo->prepare("UPDATE roster_message_recipients SET status='sent',sent_at=CURRENT_TIMESTAMP,claim_token=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='processing' AND claim_token=?");$statement->execute([$id,$token]);}else{$terminal=(int)$row['attempts']>=5;$available=(new DateTimeImmutable('now +'.min(3600,300*max(1,(int)$row['attempts'])).' seconds'))->format('Y-m-d H:i:s');$statement=$pdo->prepare("UPDATE roster_message_recipients SET status=?,available_at=?,claim_token=NULL,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='processing' AND claim_token=?");$statement->execute([$terminal?'failed':'pending',$available,mb_substr($error,0,500,'UTF-8'),$id,$token]);}if($statement->rowCount()!==1)throw new RosterMessageException('Příjemce už nevlastní tento proces.');$messageId=(int)$row['message_id'];$counts=$pdo->prepare('SELECT status,COUNT(*) total FROM roster_message_recipients WHERE message_id=? GROUP BY status');$counts->execute([$messageId]);$map=[];foreach($counts->fetchAll(PDO::FETCH_ASSOC)as$count)$map[(string)$count['status']]=(int)$count['total'];if(($map['pending']??0)+($map['processing']??0)===0){$status=($map['failed']??0)>0?(($map['sent']??0)>0?'partial':'failed'):'sent';$pdo->prepare('UPDATE roster_messages SET status=?,completed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$messageId]);}}

/** @param callable(string,string,string):bool $sender */
function rosterMessageProcessOne(PDO$pdo,callable$sender):?bool{$row=rosterMessageClaim($pdo);if($row===null)return null;try{$preview=rosterMessagePreview($pdo,(int)$row['team_id'],(string)$row['subject_plain'],(string)$row['body_plain']);$allowed=false;foreach($preview['recipients'] as $recipient){if((int)$recipient['account_id']===(int)$row['account_id']&&$recipient['email']===$row['recipient_email'])$allowed=true;}if(!$allowed){notificationQueueHold($pdo,'roster_message_recipients',$row,'delivery_blocked: příjemce již není oprávněn.');return false;}$sent=$sender((string)$row['recipient_email'],(string)$row['subject_plain'],(string)$row['body_plain']);rosterMessageComplete($pdo,$row,$sent,$sent?'':'Transport odmítl zprávu.');return$sent;}catch(Throwable$exception){notificationQueueHold($pdo,'roster_message_recipients',$row,'delivery_unknown: ověřit oprávnění a doručení před opakováním.');return false;}}

function rosterMessageMailSender(string$recipient,string$subject,string$body):bool{if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient.$subject)===1)throw new RosterMessageException('Zpráva obsahuje neplatnou hlavičku.');return mail($recipient,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,"Content-Type: text/plain; charset=UTF-8\r\n");}
