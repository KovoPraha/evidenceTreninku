<?php
declare(strict_types=1);

require_once __DIR__.'/one_time_token.php';
require_once __DIR__.'/person_sensitive.php';

final class AuthMailOutboxException extends RuntimeException {}

function authMailKey(string $version, ?array $override = null): string
{
    $config = personSensitiveConfig($override);
    if (!isset($config['keys'][$version])) throw new AuthMailOutboxException('Šifrovací klíč fronty není dostupný.');
    return hash_hkdf('sha256', $config['keys'][$version], 32, 'evidence-auth-mail-outbox-v1');
}

/** @return array{ciphertext:string,key_version:string} */
function authMailEncrypt(string $body, string $kind, string $targetType, int $targetId, string $email, ?array $override = null): array
{
    $config = personSensitiveConfig($override);
    $version = $config['active_version'];
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $associated = 'auth-mail-v1|'.$kind.'|'.$targetType.'|'.$targetId.'|'.$email;
    $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($body,$associated,$nonce,authMailKey($version,$override));
    return ['ciphertext'=>base64_encode($nonce.$cipher),'key_version'=>$version];
}

function authMailDecrypt(array $row, ?array $override = null): string
{
    $raw = base64_decode((string)($row['body_ciphertext']??''),true);
    $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    if (!is_string($raw) || strlen($raw) <= $nonceLength) throw new AuthMailOutboxException('Šifrovaný obsah zprávy není dostupný.');
    $associated = 'auth-mail-v1|'.$row['kind'].'|'.$row['target_type'].'|'.$row['target_id'].'|'.$row['recipient_email'];
    $body = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
        substr($raw,$nonceLength),$associated,substr($raw,0,$nonceLength),authMailKey((string)$row['key_version'],$override)
    );
    if (!is_string($body)) throw new AuthMailOutboxException('Šifrovaný obsah zprávy nelze ověřit.');
    return $body;
}

/** Called inside the same transaction that stores the one-time token hash. */
function authMailEnqueue(PDO $pdo,string $kind,string $targetType,int $targetId,string $tokenHash,string $email,string $subject,string $body,?array $keyConfig=null):int
{
    if (!$pdo->inTransaction() || !in_array($kind,['verify','reset'],true)
        || !in_array($targetType,['public','child'],true) || $targetId<1
        || preg_match('/^[a-f0-9]{64}$/D',$tokenHash)!==1
        || !filter_var($email,FILTER_VALIDATE_EMAIL)
        || trim($subject)==='' || preg_match('/[\r\n\x00-\x1F\x7F]/',$subject)
        || mb_strlen($subject,'UTF-8')>255 || $body==='') {
        throw new InvalidArgumentException('Neplatné údaje pro bezpečné zařazení e-mailu.');
    }
    $sealed=authMailEncrypt($body,$kind,$targetType,$targetId,$email,$keyConfig);
    $insert=$pdo->prepare('INSERT INTO auth_mail_outbox(kind,target_type,target_id,token_hash,recipient_email,subject_plain,body_ciphertext,key_version) VALUES(?,?,?,?,?,?,?,?)');
    $insert->execute([$kind,$targetType,$targetId,$tokenHash,$email,$subject,$sealed['ciphertext'],$sealed['key_version']]);
    return (int)$pdo->lastInsertId();
}

/** @return array{accepted:true,queued:bool} */
function authMailResendVerification(PDO $pdo,string $email,string $verificationUrl,?array $keyConfig=null):array
{
    $email=strtolower(trim($email));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) return ['accepted'=>true,'queued'=>false];
    $pdo->beginTransaction();
    try {
        $sql='SELECT id,jmeno,email FROM verejni_uzivatele WHERE email=? AND aktivni=1 AND email_overeno=0';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
        $select=$pdo->prepare($sql);$select->execute([$email]);$row=$select->fetch(PDO::FETCH_ASSOC);
        if(!$row){$pdo->commit();return ['accepted'=>true,'queued'=>false];}
        $issued=one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION,86400);
        $id=(int)$row['id'];
        $pdo->prepare('UPDATE verejni_uzivatele SET verifikacni_token=?,verifikacni_token_expires_at=? WHERE id=? AND email_overeno=0')
            ->execute([$issued['hash'],$issued['expires_at'],$id]);
        $pdo->prepare("UPDATE auth_mail_outbox SET status='cancelled',body_ciphertext=NULL,last_error='Nahrazeno novým ověřovacím odkazem.' WHERE kind='verify' AND target_type='public' AND target_id=? AND status IN ('pending','failed')")
            ->execute([$id]);
        $link=$verificationUrl.'#token='.rawurlencode($issued['token']);
        authMailEnqueue($pdo,'verify','public',$id,$issued['hash'],(string)$row['email'],'Ověření registrace — Kovopraha',
            "Dobrý den {$row['jmeno']},\n\nPro dokončení registrace otevřete odkaz:\n{$link}\n\nOdkaz je platný 24 hodin.",$keyConfig);
        $pdo->commit();return ['accepted'=>true,'queued'=>true];
    } catch(Throwable $exception) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }
}

function authMailTokenIsCurrent(PDO $pdo,array $row,?int $now=null):bool
{
    $nowSql=gmdate('Y-m-d H:i:s',$now??time());
    if((string)$row['kind']==='verify'){
        $query=$pdo->prepare('SELECT 1 FROM verejni_uzivatele WHERE id=? AND email=? AND aktivni=1 AND email_overeno=0 AND verifikacni_token=? AND verifikacni_token_expires_at>=?');
        $query->execute([(int)$row['target_id'],(string)$row['recipient_email'],(string)$row['token_hash'],$nowSql]);
        return (bool)$query->fetchColumn();
    }
    $query=$pdo->prepare('SELECT * FROM password_reset_tokens WHERE target_type=? AND target_id=? AND token_hash=? AND consumed_at IS NULL AND expires_at>=?');
    $query->execute([(string)$row['target_type'],(int)$row['target_id'],(string)$row['token_hash'],$nowSql]);
    $reset=$query->fetch(PDO::FETCH_ASSOC);
    if(!$reset)return false;
    require_once __DIR__.'/password_reset.php';
    if(!passwordResetTargetStillAuthorized($pdo,$reset))return false;
    $email=$pdo->prepare('SELECT 1 FROM verejni_uzivatele WHERE id=? AND email=? AND aktivni=1 AND email_overeno=1');
    $email->execute([(int)$reset['delivery_account_id'],(string)$row['recipient_email']]);
    return (bool)$email->fetchColumn();
}

/** @return array<string,mixed>|null */
function authMailClaim(PDO $pdo):?array
{
    $pdo->beginTransaction();
    try{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $stale=$mysql?'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE':"datetime('now','-15 minutes')";
        $sql="SELECT * FROM auth_mail_outbox WHERE (status='pending' AND available_at<=CURRENT_TIMESTAMP) OR (status='processing' AND claimed_at<".$stale.") ORDER BY id LIMIT 1";
        if($mysql)$sql.=' FOR UPDATE';
        $row=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if(!$row){$pdo->commit();return null;}
        $claim=bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE auth_mail_outbox SET status='processing',attempts=attempts+1,claimed_at=CURRENT_TIMESTAMP,claim_token=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$claim,(int)$row['id']]);
        $pdo->commit();$row['claim_token']=$claim;$row['attempts']=(int)$row['attempts']+1;return $row;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function authMailComplete(PDO $pdo,array $row,string $status,string $error=''):void
{
    if(!in_array($status,['sent','failed','cancelled'],true))throw new InvalidArgumentException('Neplatný výsledek e-mailu.');
    $attempt=(int)$row['attempts'];
    $terminal=$status==='failed'&&$attempt>=5;
    $newStatus=$status==='failed'&&!$terminal?'pending':$status;
    $available=(new DateTimeImmutable('now +'.min(3600,300*max(1,$attempt)).' seconds'))->format('Y-m-d H:i:s');
    $query=$pdo->prepare('UPDATE auth_mail_outbox SET status=?,available_at=?,claim_token=NULL,body_ciphertext=CASE WHEN ? IN (\'sent\',\'cancelled\') THEN NULL ELSE body_ciphertext END,sent_at=CASE WHEN ?=\'sent\' THEN CURRENT_TIMESTAMP ELSE sent_at END,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status=\'processing\' AND claim_token=?');
    $query->execute([$newStatus,$available,$newStatus,$newStatus,mb_substr($error,0,500,'UTF-8'),(int)$row['id'],(string)$row['claim_token']]);
    if($query->rowCount()!==1)throw new AuthMailOutboxException('Zprávu již vlastní jiný proces.');
}

/** @param callable(string,string,string):bool $sender
 * @return 'sent'|'failed'|'cancelled'|null
 */
function authMailProcessOne(PDO $pdo,callable $sender,?array $keyConfig=null):?string
{
    $row=authMailClaim($pdo);
    if($row===null)return null;
    if(!authMailTokenIsCurrent($pdo,$row)){
        authMailComplete($pdo,$row,'cancelled','Odkaz byl nahrazen nebo již není platný.');
        return 'cancelled';
    }
    try{
        $body=authMailDecrypt($row,$keyConfig);
        $sent=$sender((string)$row['recipient_email'],(string)$row['subject_plain'],$body);
        authMailComplete($pdo,$row,$sent?'sent':'failed',$sent?'':'Transport odmítl zprávu.');
        return $sent?'sent':'failed';
    }catch(Throwable $exception){
        authMailComplete($pdo,$row,'failed','Transport nebo šifrování vyvolaly chybu typu '.get_class($exception).'.');
        return 'failed';
    }
}

function authMailSummary(PDO $pdo):array
{
    $summary=['pending'=>0,'processing'=>0,'sent'=>0,'failed'=>0,'cancelled'=>0];
    foreach($pdo->query('SELECT status,COUNT(*) AS total FROM auth_mail_outbox GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row)
        $summary[(string)$row['status']]=(int)$row['total'];
    return $summary;
}

function authMailAdminList(PDO $pdo,string $status='',int $limit=100):array
{
    if($status!==''&&!in_array($status,['pending','processing','sent','failed','cancelled'],true))throw new InvalidArgumentException('Neplatný filtr.');
    $limit=max(1,min(200,$limit));
    $where=$status!==''?'WHERE status=?':'WHERE status IN (\'pending\',\'processing\',\'failed\')';
    $query=$pdo->prepare("SELECT id,kind,target_type,target_id,recipient_email,status,attempts,available_at,last_error,created_at FROM auth_mail_outbox $where ORDER BY id DESC LIMIT $limit");
    $query->execute($status!==''?[$status]:[]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function authMailAdminRetry(PDO $pdo,int $outboxId,int $actorId,string $reason,bool $confirmed):bool
{
    $reason=trim($reason);
    if($outboxId<1||$actorId<1||$reason===''||mb_strlen($reason,'UTF-8')>1000||!$confirmed)throw new InvalidArgumentException('Vyberte zprávu, důvod a potvrďte opakování.');
    $pdo->beginTransaction();
    try{
        $sql='SELECT * FROM auth_mail_outbox WHERE id=?';
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $query=$pdo->prepare($sql);$query->execute([$outboxId]);$row=$query->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['status']!=='failed'||empty($row['body_ciphertext'])||!authMailTokenIsCurrent($pdo,$row))throw new AuthMailOutboxException('Odkaz již nelze opakovat. Uživatel může požádat o nový.');
        $pdo->prepare("UPDATE auth_mail_outbox SET status='pending',attempts=0,available_at=CURRENT_TIMESTAMP,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$outboxId]);
        $pdo->prepare("INSERT INTO auth_mail_outbox_events(outbox_id,actor_trainer_id,action,reason) VALUES(?,?,'retry',?)")
            ->execute([$outboxId,$actorId,$reason]);
        $pdo->commit();return true;
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
