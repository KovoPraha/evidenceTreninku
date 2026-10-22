<?php
declare(strict_types=1);
require_once __DIR__.'/admin_operational_overview.php';

/** References to canonical records, never copies of their workflow or financial status. */
function operationalCaseSources(): array
{
    return [
        'identity_review' => ['table'=>'person_identity_reviews','position'=>'registrar','label'=>'Ověření identity','href'=>'identity_reviews_admin.php',
            'unresolved'=>"s.status IN ('review','deferred')"],
        'family_claim' => ['table'=>'account_person_claim_requests','position'=>'registrar','label'=>'Propojení rodiny','href'=>'eshop_identity_admin.php',
            'unresolved'=>"s.status='pending'"],
        'paper_record' => ['table'=>'club_paper_records','position'=>'registrar','label'=>'Papírový dokument','href'=>'paper_registrations_admin.php',
            'unresolved'=>"((SELECT event_type FROM club_paper_record_events e WHERE e.record_id=s.id AND e.event_type<>'file_viewed' ORDER BY e.id DESC LIMIT 1)='recorded' OR (s.term_version_id IS NULL AND s.version_label='verze neověřena' AND (SELECT event_type FROM club_paper_record_events e WHERE e.record_id=s.id AND e.event_type<>'file_viewed' ORDER BY e.id DESC LIMIT 1)='accepted'))"],
        'notification' => ['table'=>'club_event_notifications','position'=>'order_operator','label'=>'Nedoručená zpráva','href'=>'eshop_notifications_admin.php',
            'unresolved'=>"s.status='failed' AND NOT EXISTS(SELECT 1 FROM club_event_notification_events e WHERE e.notification_id=s.id AND e.action='manual_contact_resolved' AND e.attempts_before=s.attempts AND e.id=(SELECT MAX(last_event.id) FROM club_event_notification_events last_event WHERE last_event.notification_id=s.id))"],
        'auth_mail' => ['table'=>'auth_mail_outbox','position'=>'order_operator','label'=>'Přístupový e-mail','href'=>'auth_mail_admin.php','unresolved'=>"s.status='failed'"],
        'family_summary' => ['table'=>'family_weekly_summaries','position'=>'order_operator','label'=>'Rodinný souhrn','href'=>'family_weekly_summaries_admin.php','unresolved'=>"s.status='failed'"],
        'member_reminder' => ['table'=>'member_charge_reminders','position'=>'finance_manager','label'=>'Připomínka příspěvku','href'=>'member_charge_reminders_admin.php','unresolved'=>"s.status='failed'"],
        'roster_message' => ['table'=>'roster_message_recipients','position'=>'registrar','label'=>'Zpráva soupisce','href'=>'roster_messages_admin.php','unresolved'=>"s.status='failed'"],
    ];
}

function operationalCaseActor(PDO $pdo, int $actor, string $position): void
{
    $lock=$pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $st=$pdo->prepare('SELECT t.id FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code=?) OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id))'.$lock);
    $st->execute([$actor,$position]);
    if (!$st->fetchColumn()) throw new DomainException('Pracovní oprávnění již neplatí.');
}

function operationalCaseSource(PDO $pdo, string $type, int $id, string $position): array
{
    $definition=operationalCaseSources()[$type]??null;
    if (!$definition || $definition['position']!==$position || $id<1) throw new DomainException('Případ není v této pracovní agendě.');
    $lock=$pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $st=$pdo->prepare('SELECT s.id,CASE WHEN '.$definition['unresolved'].' THEN 1 ELSE 0 END AS unresolved FROM '.$definition['table'].' s WHERE s.id=?'.$lock);
    $st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new DomainException('Zdrojový záznam nebyl nalezen.');
    return $row+$definition;
}

/** GET is read-only: unresolved records do not acquire a case until assigned explicitly. */
function operationalCaseList(PDO $pdo, string $position, int $page=1, bool $closed=false): array
{
    $items=[]; $unavailable=[]; $page=max(1,min(1000,$page)); $offset=($page-1)*30;$hasNext=false;
    foreach (operationalCaseSources() as $type=>$def) {
        if ($def['position']!==$position) continue;
        $required=[$def['table'],'operational_cases','operational_case_events'];
        if ($type==='paper_record') $required[]='club_paper_record_events';
        if ($type==='notification') $required[]='club_event_notification_events';
        if (!adminOperationalHasTables($pdo,$required)) { $unavailable[]=$def['label']; continue; }
        $filter=$closed ? "c.status='closed' AND NOT (".$def['unresolved'].")" : '('.$def['unresolved'].") OR c.status='open'";
        $sql='SELECT s.id AS source_id,c.id AS case_id,c.owner_trainer_id,c.next_step,c.review_on,c.status,c.revision,t.jmeno AS owner_name,t.aktivni AS owner_active, '
            .'CASE WHEN '.$def['unresolved'].' THEN 1 ELSE 0 END AS unresolved '
            .'FROM '.$def['table'].' s LEFT JOIN operational_cases c ON c.source_type=? AND c.source_id=s.id LEFT JOIN treneri t ON t.id=c.owner_trainer_id '
            .'WHERE '.$filter.' ORDER BY CASE WHEN c.id IS NULL THEN 0 ELSE 1 END,c.review_on,s.id LIMIT 31 OFFSET '.$offset;
        $st=$pdo->prepare($sql);$st->execute([$type]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>30) {$hasNext=true;array_pop($rows);}
        foreach ($rows as $row) $items[]=$row+['source_type'=>$type,'label'=>$def['label'],'href'=>$def['href'].(in_array($type,['identity_review','paper_record'],true)?'?id='.(int)$row['source_id']:'')];
    }
    return ['items'=>$items,'unavailable'=>$unavailable,'page'=>$page,'has_next'=>$hasNext,'closed'=>$closed];
}

function operationalCaseSave(PDO $pdo, int $actor, string $position, array $input): int
{
    operationalCaseActor($pdo,$actor,$position);
    $type=(string)($input['source_type']??'');$sourceId=(int)($input['source_id']??0);
    $owner=(int)($input['owner_trainer_id']??0);$step=trim((string)($input['next_step']??''));
    $date=(string)($input['review_on']??'');$reason=trim((string)($input['reason']??''));
    $status=(string)($input['status']??'open');$revision=(int)($input['revision']??0);
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date || $step==='' || mb_strlen($step)>1000 || $reason==='' || mb_strlen($reason)>1000 || !in_array($status,['open','closed'],true)) {
        throw new DomainException('Vyplňte odpovědnou osobu, další krok, platné datum kontroly a důvod (nejvýše 1000 znaků).');
    }
    operationalCaseActor($pdo,$owner,$position);
    $pdo->beginTransaction();
    try {
        operationalCaseActor($pdo,$actor,$position);
        operationalCaseActor($pdo,$owner,$position);
        $source=operationalCaseSource($pdo,$type,$sourceId,$position);
        if ($status==='closed' && (int)$source['unresolved']===1) throw new DomainException('Nejdříve vyřešte zdrojový záznam. Úkol nemůže obejít jeho schválení.');
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $st=$pdo->prepare('SELECT * FROM operational_cases WHERE source_type=? AND source_id=?'.$lock);$st->execute([$type,$sourceId]);$before=$st->fetch(PDO::FETCH_ASSOC)?:[];
        if ((int)($before['revision']??0)!==$revision) throw new DomainException('Případ mezitím změnil jiný pracovník. Obnovte přehled.');
        $after=['owner_trainer_id'=>$owner,'next_step'=>$step,'review_on'=>$date,'status'=>$status,'revision'=>$revision+1];
        if ($before) {
            $id=(int)$before['id'];$st=$pdo->prepare('UPDATE operational_cases SET owner_trainer_id=?,next_step=?,review_on=?,status=?,revision=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $st->execute([...array_values($after),$id]);
        } else {
            if ($status==='closed') throw new DomainException('Vyřešený záznam nepotřebuje nový uzavřený úkol.');
            $st=$pdo->prepare('INSERT INTO operational_cases(source_type,source_id,owner_trainer_id,next_step,review_on,status,revision) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$type,$sourceId,...array_values($after)]);$id=(int)$pdo->lastInsertId();
        }
        $st=$pdo->prepare('INSERT INTO operational_case_events(case_id,actor_trainer_id,revision,reason,before_json,after_json) VALUES (?,?,?,?,?,?)');
        $st->execute([$id,$actor,$revision+1,$reason,json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();return $id;
    } catch(Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}

function operationalCaseOwners(PDO $pdo,string $position): array
{
    $st=$pdo->prepare('SELECT t.id,t.jmeno FROM treneri t WHERE t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code=?) OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id)) ORDER BY t.jmeno,t.id');
    $st->execute([$position]);return $st->fetchAll(PDO::FETCH_ASSOC);
}
