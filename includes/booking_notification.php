<?php
declare(strict_types=1);

require_once __DIR__.'/app_url.php';

/** The booking change and its notification are committed together. No bearer links in this queue. */
function bookingNotificationEnqueue(PDO $pdo, int $reservationId, string $kind, string $recipientType = 'public'): int
{
    if (!$pdo->inTransaction()) throw new LogicException('Oznámení rezervace vyžaduje transakci.');
    if (!in_array($kind, ['created','promoted','confirmed','rejected','cancelled'], true)
        || !in_array($recipientType, ['public','trainer'], true)) throw new InvalidArgumentException('Neplatný druh oznámení.');
    $q = $pdo->prepare('SELECT r.id,r.stav,r.uzivatel_id,r.slot_cas_od,r.slot_cas_do,l.trener_id,l.nazev,l.datum,'
        . 'u.email AS public_email,t.email AS trainer_email FROM verejne_rezervace r '
        . 'JOIN individualni_lekce l ON l.id=r.lekce_id JOIN verejni_uzivatele u ON u.id=r.uzivatel_id '
        . 'JOIN treneri t ON t.id=l.trener_id WHERE r.id=?');
    $q->execute([$reservationId]); $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Rezervace pro oznámení neexistuje.');
    $key = 'booking:'.$reservationId.':'.$kind.':'.$recipientType;
    $old = $pdo->prepare('SELECT id FROM club_event_notifications WHERE dedupe_key=?');
    $old->execute([$key]);
    if ($id = (int)$old->fetchColumn()) return $id;
    $email = trim((string)$row[$recipientType.'_email']);
    $valid = filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $email);
    [$subject,$body]=bookingNotificationCompose($row,$kind,$recipientType);
    $q = $pdo->prepare('INSERT INTO club_event_notifications(dedupe_key,notification_type,recipient_email,recipient_name,subject_plain,body_plain,status,last_error) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$key,'booking_'.$kind,$valid ? $email : '', '',$subject,$body,$valid ? 'pending' : 'failed',
        $valid ? null : 'manual_contact_required: správce rezervací musí ověřit kontakt a domluvit osobní předání.']);
    $id = (int)$pdo->lastInsertId();
    $expires = (new DateTimeImmutable((string)$row['datum'].' 23:59:59'))->modify('+1 day')->format('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO booking_notification_context(notification_id,reservation_id,recipient_type,recipient_id,expected_state,expires_at) VALUES(?,?,?,?,?,?)')
        ->execute([$id,$reservationId,$recipientType,(int)$row[$recipientType === 'trainer' ? 'trener_id' : 'uzivatel_id'],$row['stav'],$expires]);
    return $id;
}

/** Returns a safe reason code; a revoked relationship never falls back to matching email. */
function bookingNotificationInvalidReason(PDO $pdo, array $notification): ?string
{
    if (!filter_var((string)$notification['recipient_email'],FILTER_VALIDATE_EMAIL)) return 'manual_contact_required';
    if(($notification['notification_type']??'')==='training_schedule_changed'){
        if(!preg_match('/^training_schedule:([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*)$/D',(string)($notification['dedupe_key']??''),$m))return 'missing_training_context';
        $s=$pdo->prepare("SELECT 1 FROM training_schedule_events e JOIN planovane_treninky p ON p.id=e.plan_id WHERE e.id=? AND p.stav='zruseny'");$s->execute([(int)$m[1]]);if(!$s->fetchColumn())return 'training_state_changed';
        return operationalNotificationPersonAllowed($pdo,(int)$m[3],(int)$m[2],(string)$notification['recipient_email'])?null:'family_authorization_revoked';
    }
    if (($notification['notification_type']??'')==='waitlist_promoted') {
        $q=$pdo->prepare("SELECT account_id,sportovec_id,status FROM club_event_registrations WHERE id=?");
        $q->execute([(int)($notification['registration_id']??0)]);$registration=$q->fetch(PDO::FETCH_ASSOC);
        if (!$registration || $registration['status']!=='confirmed') return 'event_registration_changed';
        return operationalNotificationPersonAllowed($pdo,(int)$registration['account_id'],(int)$registration['sportovec_id'],(string)$notification['recipient_email']) ? null : 'family_authorization_revoked';
    }
    if (preg_match('/^claim:([1-9][0-9]*):(approved|rejected|cancelled)$/D',(string)($notification['dedupe_key']??''),$match)) {
        $q=$pdo->prepare('SELECT c.account_id,c.matched_sportovec_id,c.status,a.email,a.aktivni,a.email_overeno FROM account_person_claim_requests c JOIN verejni_uzivatele a ON a.id=c.account_id WHERE c.id=?');
        $q->execute([(int)$match[1]]);$claim=$q->fetch(PDO::FETCH_ASSOC);
        if (!$claim || $claim['status']!==$match[2] || (int)$claim['aktivni']!==1 || (int)$claim['email_overeno']!==1 || $claim['email']!==$notification['recipient_email']) return 'claim_or_recipient_changed';
        if ($match[2]==='approved' && !operationalNotificationPersonAllowed($pdo,(int)$claim['account_id'],(int)$claim['matched_sportovec_id'],(string)$notification['recipient_email'])) return 'family_authorization_revoked';
        return null;
    }
    if (preg_match('/^enrollment:([1-9][0-9]*):activated$/D',(string)($notification['dedupe_key']??''),$match)) {
        $q=$pdo->prepare("SELECT e.sportovec_id,e.status,o.account_id FROM club_program_enrollments e JOIN shop_order_items i ON i.id=e.source_order_item_id JOIN shop_orders o ON o.id=i.order_id WHERE e.id=?");
        $q->execute([(int)$match[1]]);$enrollment=$q->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment || $enrollment['status']!=='active') return 'enrollment_changed';
        return operationalNotificationPersonAllowed($pdo,(int)$enrollment['account_id'],(int)$enrollment['sportovec_id'],(string)$notification['recipient_email']) ? null : 'family_authorization_revoked';
    }
    if ($notification['notification_type']==='training_reminder') {
        if (!preg_match('/^training_reminder:([1-9][0-9]*)$/D',(string)($notification['dedupe_key']??''),$match)) return 'missing_training_context';
        $q=$pdo->prepare("SELECT 1 FROM planovane_treninky p JOIN treneri t ON t.id=p.trener_id WHERE p.id=? AND p.stav='planovany' AND t.aktivni=1 AND t.email=? AND p.datum>=?");
        $q->execute([(int)$match[1],$notification['recipient_email'],date('Y-m-d',strtotime('-14 days'))]);
        return $q->fetchColumn()?null:'training_or_recipient_changed';
    }
    if (!str_starts_with((string)$notification['notification_type'], 'booking_')) return null;
    $q = $pdo->prepare('SELECT * FROM booking_notification_context WHERE notification_id=?');
    $q->execute([(int)$notification['id']]); $context = $q->fetch(PDO::FETCH_ASSOC);
    if (!$context) return 'missing_delivery_context';
    if ($context['expires_at'] < date('Y-m-d H:i:s')) return 'expired_booking_notice';
    $q = $pdo->prepare('SELECT r.*,l.trener_id,l.nazev,l.datum FROM verejne_rezervace r JOIN individualni_lekce l ON l.id=r.lekce_id WHERE r.id=?');
    $q->execute([(int)$context['reservation_id']]); $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['stav'] !== $context['expected_state']) return 'booking_state_changed';
    [$subject,$body]=bookingNotificationCompose($row,substr((string)$notification['notification_type'],8),(string)$context['recipient_type']);
    if ($subject!==$notification['subject_plain'] || $body!==$notification['body_plain']) return 'booking_details_changed';
    $trainer = $context['recipient_type'] === 'trainer';
    $id = (int)$context['recipient_id'];
    if ($id !== (int)$row[$trainer ? 'trener_id' : 'uzivatel_id']) return 'recipient_changed';
    $q = $pdo->prepare('SELECT email,aktivni'.($trainer?'':',email_overeno').' FROM '.($trainer ? 'treneri' : 'verejni_uzivatele').' WHERE id=?');
    $q->execute([$id]); $recipient = $q->fetch(PDO::FETCH_ASSOC);
    if (!$recipient || (int)$recipient['aktivni'] !== 1 || $recipient['email'] !== $notification['recipient_email']) return 'recipient_inactive_or_changed';
    if (!$trainer && (int)($recipient['email_overeno']??0)!==1) return 'recipient_unverified';
    if (!$trainer && !empty($row['sportovec_id'])) {
        $q = $pdo->prepare("SELECT 1 FROM account_person_roles WHERE account_id=? AND sportovec_id=? AND status='approved' AND relation_role IN ('self','guardian') AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)");
        $q->execute([$id,(int)$row['sportovec_id']]);
        if (!$q->fetchColumn()) return 'family_authorization_revoked';
    }
    return null;
}

function operationalNotificationPersonAllowed(PDO $pdo,int $accountId,int $personId,string $email): bool
{
    $q=$pdo->prepare("SELECT 1 FROM account_person_roles r JOIN verejni_uzivatele a ON a.id=r.account_id WHERE r.account_id=? AND r.sportovec_id=? AND a.email=? AND a.aktivni=1 AND a.email_overeno=1 AND r.status='approved' AND r.relation_role IN ('self','guardian') AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP)");
    $q->execute([$accountId,$personId,$email]);return (bool)$q->fetchColumn();
}

function bookingNotificationCompose(array $row,string $kind,string $recipientType): array
{
    $labels = ['created'=>'Nová rezervace','promoted'=>'Uvolnilo se místo','confirmed'=>'Rezervace potvrzena',
        'rejected'=>'Rezervace zamítnuta','cancelled'=>'Lekce zrušena'];
    $subject = $labels[$kind].' — '.str_replace(["\r","\n"], ' ', (string)$row['nazev']);
    $body = $subject."\nDatum: ".$row['datum']."\nČas: ".$row['slot_cas_od'].'–'.$row['slot_cas_do']
        ."\nStav rezervace: ".$row['stav']."\n\nPřehled a další postup: "
        .appUrl($recipientType === 'trainer' ? 'individualni_lekce_sprava.php' : 'booking/moje_rezervace.php');
    return [$subject,$body];
}
