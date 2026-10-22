<?php
declare(strict_types=1);
require_once __DIR__.'/coach_scope.php';
require_once __DIR__.'/training_roster_bridge.php';

final class TrainingClosureException extends RuntimeException {}

function trainingClosureReady(PDO $pdo):bool { return memberChargeReadTableExists($pdo,'training_closures'); }
function trainingClosureLockSuffix(PDO $pdo):string {
    if(!$pdo->inTransaction())throw new LogicException('Docházka vyžaduje transakci.');
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
}
function trainingClosureAssertActor(PDO $pdo,int $actor):void {
    $position=staffActivePosition();
    if($actor<1||$actor!==(int)($_SESSION['trener_id']??0)||!in_array($position,['coach','sports_lead'],true))throw new TrainingClosureException('Docházku spravuje trenér nebo vedoucí sportu.');
    $s=$pdo->prepare('SELECT 1 FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code=?) OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id))');$s->execute([$actor,$position]);
    if(!$s->fetchColumn())throw new TrainingClosureException('Pracovní oprávnění již neplatí.');
}
function trainingClosureAssertTraining(PDO $pdo,int $id,int $actor):array {
    trainingClosureAssertActor($pdo,$actor);
    $s=$pdo->prepare('SELECT * FROM treninky WHERE id=?'.($pdo->inTransaction()?trainingClosureLockSuffix($pdo):''));$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new TrainingClosureException('Trénink nebyl nalezen.');
    if(staffActivePositionIs('coach')) {
        $s=$pdo->prepare('SELECT 1 FROM trenink_trener WHERE trenink_id=? AND trener_id=?');$s->execute([$id,$actor]);
        if(!$s->fetchColumn())throw new TrainingClosureException('Trénink není přiřazen vám.');
        $s=$pdo->prepare('SELECT sportovec_id FROM trenink_sportovec WHERE trenink_id=?');$s->execute([$id]);$people=$s->fetchAll(PDO::FETCH_COLUMN);
        if(memberChargeReadTableExists($pdo,'trenink_mereni')&&memberChargeReadTableExists($pdo,'mereni_zaznamy')){$s=$pdo->prepare('SELECT m.sportovec_id FROM trenink_mereni tm JOIN mereni_zaznamy m ON m.id=tm.mereni_id WHERE tm.trenink_id=?');$s->execute([$id]);$people=array_merge($people,$s->fetchAll(PDO::FETCH_COLUMN));}
        coachScopeAssertPeople($pdo,$people);
        coachScopeAssertTeams($pdo,trainingRosterBridgeTrainingTeamIds($pdo,$id),(string)$row['datum']);
    }
    return $row;
}
function trainingClosureCurrent(PDO $pdo,int $id):?array {
    if(!trainingClosureReady($pdo))return null;
    $s=$pdo->prepare('SELECT r.* FROM training_closures c JOIN training_closure_revisions r ON r.id=c.current_revision_id WHERE c.training_id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;$row['snapshot']=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);return $row;
}
function trainingClosureDateTime(string $value):DateTimeImmutable {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('Europe/Prague'));
    if(!$d||$d->format('Y-m-d H:i:s')!==$value)throw new TrainingClosureException('Zadejte skutečný čas ve formátu datum a čas.');return $d;
}
/** Unknown attendance stays a draft. No planned/RSVP population is copied implicitly. */
function trainingClosureClose(PDO $pdo,int $trainingId,int $expectedVersion,array $input,int $actor):array {
    if(!trainingClosureReady($pdo))throw new TrainingClosureException('Uzavírání docházky není připraveno.');
    $reason=trim((string)($input['reason']??''));
    if(mb_strlen($reason)<10||mb_strlen($reason)>1000||($input['confirmed']??false)!==true)throw new TrainingClosureException('Potvrďte skutečnou docházku a uveďte důvod zápisu či opravy (10–1000 znaků).');
    $start=trainingClosureDateTime((string)($input['actual_started_at']??''));$end=trainingClosureDateTime((string)($input['actual_ended_at']??''));
    $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
    if($end<=$start||$end>$now||$end->getTimestamp()-$start->getTimestamp()>86400)throw new TrainingClosureException('Definitivní docházku lze uzavřít až po skutečném konci lekce s platnou délkou do 24 hodin.');
    $people=trainingClosureIds($input['participant_ids']??[]);$coaches=trainingClosureIds($input['coach_ids']??[]);
    if(!$coaches)throw new TrainingClosureException('Potvrďte alespoň jednoho skutečně přítomného trenéra.');
    $status=($input['void']??false)?'void':'closed';
    $pdo->beginTransaction();
    try {
        $training=trainingClosureAssertTraining($pdo,$trainingId,$actor);
        if($start->format('Y-m-d')!==(string)$training['datum'])throw new TrainingClosureException('Skutečný začátek musí odpovídat datu evidence.');
        $lock=trainingClosureLockSuffix($pdo);
        $s=$pdo->prepare('SELECT * FROM training_closures WHERE training_id=?'.$lock);$s->execute([$trainingId]);$closure=$s->fetch(PDO::FETCH_ASSOC);
        $version=$closure?(int)$closure['version']:0;
        if($expectedVersion!==$version)throw new TrainingClosureException('Docházku mezitím změnil jiný pracovník. Obnovte přehled; nic nebylo přepsáno.');
        if($status==='void' && $version===0)throw new TrainingClosureException('Zneplatnit lze již uzavřenou docházku.');
        coachScopeAssertPeople($pdo,$people);
        foreach($coaches as $id){$s=$pdo->prepare('SELECT 1 FROM treneri WHERE id=? AND aktivni=1');$s->execute([$id]);if(!$s->fetchColumn())throw new TrainingClosureException('Vybraný trenér není aktivní.');}
        if(staffActivePositionIs('coach')&&!in_array($actor,$coaches,true))throw new TrainingClosureException('Odstranění zapisujícího trenéra řeší vedoucí sportu.');
        $s=$pdo->prepare('SELECT * FROM planovane_treninky WHERE trenink_id=?'.$lock);$s->execute([$trainingId]);$plan=$s->fetch(PDO::FETCH_ASSOC);
        if($plan && $plan['stav']==='zruseny')throw new TrainingClosureException('Zrušenou lekci nelze uzavřít jako uskutečněnou.');
        $expected=[];
        if($plan){$expected=array_map('intval',array_column(trainingRosterBridgeExpectedForPlan($pdo,(int)$plan['id']),'id'));}
        $guestReasons=(array)($input['guest_reasons']??[]);
        if($plan)foreach(array_diff($people,$expected) as $person){if(mb_strlen(trim((string)($guestReasons[$person]??'')))<10)throw new TrainingClosureException('U neplánovaného hosta #'.$person.' doplňte důvod účasti.');}
        $before=trainingClosureCurrent($pdo,$trainingId);
        // Closed legacy reward periods retain their stored counts and amount; record an explicit review delta.
        $s=$pdo->prepare('SELECT sportovec_id FROM trenink_sportovec WHERE trenink_id=?'.$lock);$s->execute([$trainingId]);$rawParticipants=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
        $affected=array_unique(array_merge($people,$before['snapshot']['participant_ids']??[],$rawParticipants));sort($affected,SORT_NUMERIC);
        $review=[];
        if(memberChargeReadTableExists($pdo,'sportovec_obdobi'))foreach($affected as $person){
            $s=$pdo->prepare('SELECT id FROM sportovci WHERE id=?'.$lock);$s->execute([$person]);
            $s=$pdo->prepare("SELECT id FROM sportovec_obdobi WHERE sportovec_id=? AND datum_od<=? AND (datum_do IS NULL OR datum_do='' OR datum_do='0000-00-00' OR datum_do>=?) AND (vyplaceno<>0 OR (datum_do IS NOT NULL AND datum_do<>'' AND datum_do<>'0000-00-00'))".$lock);$s->execute([$person,$training['datum'],$training['datum']]);
            foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id)$review[]=['athlete_reward',(int)$id];
        }
        if($status==='void')$people=[];
        $minutes=(int)ceil(($end->getTimestamp()-$start->getTimestamp())/60);
        $snapshot=['training_id'=>$trainingId,'actual_date'=>$start->format('Y-m-d'),'actual_started_at'=>$start->format('Y-m-d H:i:s'),'actual_ended_at'=>$end->format('Y-m-d H:i:s'),'duration_minutes'=>$minutes,'participant_ids'=>$people,'coach_ids'=>$coaches,'guest_reasons'=>$guestReasons,'record_source'=>($input['record_source']??'coach')==='paper'?'paper':'coach','plan_id'=>$plan?(int)$plan['id']:null,'category'=>$training['kategorie']??null,'description'=>$training['napln']??null];
        $pdo->prepare('INSERT INTO training_closure_revisions(training_id,version,status,actual_date,actual_started_at,actual_ended_at,duration_minutes,snapshot_json,reason,closed_by) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$trainingId,$version+1,$status,$snapshot['actual_date'],$snapshot['actual_started_at'],$snapshot['actual_ended_at'],$minutes,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$reason,$actor]);$revision=(int)$pdo->lastInsertId();
        if($closure)$pdo->prepare('UPDATE training_closures SET current_revision_id=?,version=?,status=? WHERE training_id=?')->execute([$revision,$version+1,$status,$trainingId]);
        else $pdo->prepare('INSERT INTO training_closures(training_id,current_revision_id,version,status) VALUES(?,?,?,?)')->execute([$trainingId,$revision,$version+1,$status]);
        $pdo->prepare('DELETE FROM trenink_sportovec WHERE trenink_id=?')->execute([$trainingId]);$ins=$pdo->prepare('INSERT INTO trenink_sportovec(trenink_id,sportovec_id) VALUES(?,?)');foreach($people as $person)$ins->execute([$trainingId,$person]);
        $pdo->prepare('DELETE FROM trenink_trener WHERE trenink_id=?')->execute([$trainingId]);$ins=$pdo->prepare('INSERT INTO trenink_trener(trenink_id,trener_id) VALUES(?,?)');foreach($coaches as $coach)$ins->execute([$trainingId,$coach]);
        $pdo->prepare('UPDATE treninky SET delka=? WHERE id=?')->execute([$status==='void'?0:$minutes/60,$trainingId]);
        require_once __DIR__.'/club_makeup.php';
        if($status==='void')trainingClosureVoidMakeups($pdo,$trainingId,$plan?(int)$plan['id']:0,$actor);
        else {clubMakeupReconcileRecordedAttendance($pdo,$trainingId,$actor);if($plan)clubMakeupFinalizeTarget($pdo,(int)$plan['id'],$trainingId,$actor);}
        foreach($review as [$kind,$id])$pdo->prepare('INSERT INTO training_closure_reviews(training_id,revision_id,reference_kind,reference_id) VALUES(?,?,?,?)')->execute([$trainingId,$revision,$kind,$id]);
        $pdo->commit();return ['revision_id'=>$revision,'version'=>$version+1,'status'=>$status,'review_required'=>count($review)>0]+$snapshot;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function trainingClosureIds(array $ids):array {
    foreach($ids as $id)if(!ctype_digit((string)$id)||(int)$id<1)throw new TrainingClosureException('Neplatná identita účastníka.');
    $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids,SORT_NUMERIC);return $ids;
}
function trainingClosureVoidMakeups(PDO $pdo,int $trainingId,int $planId,int $actor):void {
    if(!$planId||!clubMakeupReady($pdo))return;
    $s=$pdo->prepare("SELECT id,status FROM club_makeup_credits WHERE source_plan_id=?".trainingClosureLockSuffix($pdo));$s->execute([$planId]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $credit){
        if(in_array($credit['status'],['reserved','used'],true))throw new TrainingClosureException('Nejdříve vyřešte rezervovanou nebo využitou náhradu zdrojové lekce.');
        if($credit['status']==='available'){$pdo->prepare("UPDATE club_makeup_credits SET status='revoked' WHERE id=?")->execute([$credit['id']]);clubMakeupEvent($pdo,(int)$credit['id'],null,'lesson_void','trainer',$actor);}
    }
    $s=$pdo->prepare("SELECT id,credit_id FROM club_makeup_reservations WHERE target_plan_id=? AND status IN ('booked','attended','no_show')".trainingClosureLockSuffix($pdo));$s->execute([$planId]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$pdo->prepare("UPDATE club_makeup_reservations SET status='cancelled',active_token=NULL WHERE id=?")->execute([$r['id']]);$pdo->prepare("UPDATE club_makeup_credits SET status='available' WHERE id=? AND status IN ('reserved','used')")->execute([$r['credit_id']]);clubMakeupEvent($pdo,(int)$r['credit_id'],(int)$r['id'],'lesson_void','trainer',$actor);}
}
/** All legacy mutation paths hold the training row until commit. Closed data uses the revision form. */
function trainingClosureAssertLegacyMutable(PDO $pdo,int $id,bool $deleting=false):void {
    $s=$pdo->prepare('SELECT * FROM treninky WHERE id=?'.trainingClosureLockSuffix($pdo));$s->execute([$id]);$training=$s->fetch(PDO::FETCH_ASSOC);
    if(!$training)throw new TrainingClosureException('Trénink nebyl nalezen.');
    if(trainingClosureCurrent($pdo,$id))throw new TrainingClosureException('Uzavřený trénink opravte nebo zneplatněte v Docházka a uzavření; historie se nemaže.');
    if(memberChargeReadTableExists($pdo,'sportovec_obdobi')){
        $s=$pdo->prepare("SELECT 1 FROM sportovec_obdobi o JOIN trenink_sportovec ts ON ts.sportovec_id=o.sportovec_id WHERE ts.trenink_id=? AND o.datum_od<=? AND (o.datum_do IS NULL OR o.datum_do='' OR o.datum_do='0000-00-00' OR o.datum_do>=?) AND (o.vyplaceno<>0 OR (o.datum_do IS NOT NULL AND o.datum_do<>'' AND o.datum_do<>'0000-00-00')) LIMIT 1");$s->execute([$id,$training['datum'],$training['datum']]);
        if($s->fetchColumn())throw new TrainingClosureException('Trénink zasahuje do uzavřeného podkladu pro odměnu. Použijte verzované uzavření a opravu.');
    }
    if($deleting)foreach(['trenink_mereni'=>'SELECT 1 FROM trenink_mereni WHERE trenink_id=? LIMIT 1','planovane_treninky'=>'SELECT 1 FROM planovane_treninky WHERE trenink_id=? LIMIT 1'] as $table=>$sql){if(memberChargeReadTableExists($pdo,$table)){$s=$pdo->prepare($sql);$s->execute([$id]);if($s->fetchColumn())throw new TrainingClosureException('Navázaný trénink nelze smazat. Použijte uzavření a auditované zneplatnění.');}}
}
/** Canonical read contract used by athlete history and reward snapshots. Caller applies person ACL. */
function trainingClosedActivitiesForPerson(PDO $pdo,int $personId,string $from,string $to):array {
    if(!trainingClosureReady($pdo))return [];
    $s=$pdo->prepare("SELECT r.* FROM training_closures c JOIN training_closure_revisions r ON r.id=c.current_revision_id WHERE c.status='closed' AND r.actual_date BETWEEN ? AND ? ORDER BY r.actual_date,r.training_id");$s->execute([$from,$to]);$out=[];
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row){$data=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);if(in_array($personId,$data['participant_ids'],true))$out[]=$data+['revision_id'=>(int)$row['id'],'version'=>(int)$row['version'],'closed_at'=>$row['closed_at'],'closed_by'=>(int)$row['closed_by']];}return $out;
}

/** Serialize all planning writers, including copying a week and a recurring series. */
function trainingScheduleAssertAvailable(PDO $pdo,int $actor,int $trainerId,string $date,?string $from,?string $to,?int $venueId,array $teamIds,int $excludePlan=0):void {
    if(!trainingClosureReady($pdo))return;
    trainingClosureAssertActor($pdo,$actor);
    if(!staffActivePositionIs('sports_lead')&&$trainerId!==$actor)throw new TrainingClosureException('Plán jiného trenéra spravuje vedoucí sportu.');
    trainingClosureDateTime($date.' '.substr((string)$from,0,5).':00');trainingClosureDateTime($date.' '.substr((string)$to,0,5).':00');
    if($from>=$to)throw new TrainingClosureException('Zadejte začátek i konec plánované lekce.');
    $pdo->query('SELECT id FROM training_schedule_mutex WHERE id=1'.trainingClosureLockSuffix($pdo))->fetchColumn();
    coachScopeAssertTeams($pdo,$teamIds,$date);
    if($excludePlan>0 && !$venueId && memberChargeReadTableExists($pdo,'rezervace_sportovist')){$s=$pdo->prepare('SELECT rezervace_id FROM planovane_treninky WHERE id=?'.trainingClosureLockSuffix($pdo));$s->execute([$excludePlan]);if((int)$s->fetchColumn()>0)throw new TrainingClosureException('Lekce má rezervované sportoviště. Zrušte původní termín a propojte novou lekci.');}
    $branch=[];foreach($teamIds as $id)$branch=array_merge($branch,kisRosterDescendantIds($pdo,(int)$id,$date));
    $s=$pdo->prepare("SELECT id,trener_id,sportoviste_id,nazev FROM planovane_treninky WHERE datum=? AND stav<>'zruseny' AND id<>? AND COALESCE(cas_od,'00:00:00')<? AND COALESCE(cas_do,'23:59:59')>?".trainingClosureLockSuffix($pdo));$s->execute([$date,$excludePlan,$to,$from]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $other){
        $collision=(int)$other['trener_id']===$trainerId?'trenér':null;
        if($venueId && (int)$other['sportoviste_id']===$venueId)$collision='sportoviště';
        $targets=$pdo->prepare('SELECT team_id FROM training_roster_links WHERE plan_id=?'.trainingClosureLockSuffix($pdo));$targets->execute([(int)$other['id']]);
        foreach($targets->fetchAll(PDO::FETCH_COLUMN) as $target)if(array_intersect($branch,kisRosterDescendantIds($pdo,(int)$target,$date)))$collision='soupiska';
        if($collision)throw new TrainingClosureException('Kolize: '.$collision.' má v tomto čase lekci #'.$other['id'].'. Společnou lekci založte jako jeden termín s více soupiskami.');
    }
    if($venueId && memberChargeReadTableExists($pdo,'rezervace_sportovist')){
        $ownReservation=0;
        if($excludePlan>0){
            $s=$pdo->prepare('SELECT rezervace_id FROM planovane_treninky WHERE id=?'.trainingClosureLockSuffix($pdo));$s->execute([$excludePlan]);$ownReservation=(int)$s->fetchColumn();
            if($ownReservation>0){$s=$pdo->prepare('SELECT sportoviste_id,datum,cas_od,cas_do FROM rezervace_sportovist WHERE id=?'.trainingClosureLockSuffix($pdo));$s->execute([$ownReservation]);$own=$s->fetch(PDO::FETCH_ASSOC);
                if(!$own||(int)$own['sportoviste_id']!==$venueId||$own['datum']!==$date||substr($own['cas_od'],0,5)!==substr((string)$from,0,5)||substr($own['cas_do'],0,5)!==substr((string)$to,0,5))throw new TrainingClosureException('Lekce má rezervované sportoviště. Zrušte původní termín a propojte novou lekci; rezervace se nesmí změnit mlčky.');
            }
        }
        $s=$pdo->prepare('SELECT id FROM sportovist WHERE id=? AND aktivni=1'.trainingClosureLockSuffix($pdo));$s->execute([$venueId]);if(!$s->fetchColumn())throw new TrainingClosureException('Sportoviště není aktivní.');
        $s=$pdo->prepare('SELECT 1 FROM rezervace_sportovist WHERE sportoviste_id=? AND datum=? AND cas_od<? AND cas_do>? AND id<>? LIMIT 1'.trainingClosureLockSuffix($pdo));$s->execute([$venueId,$date,$to,$from,$ownReservation]);if($s->fetchColumn())throw new TrainingClosureException('Sportoviště již má rezervaci. Vyberte jiný čas nebo místo.');
    }
}

function trainingScheduleCancel(PDO $pdo,int $planId,int $actor,string $reason,?int $replacementId=null):void {
    trainingClosureAssertActor($pdo,$actor);coachScopeAssertPlan($pdo,$planId);
    if(mb_strlen(trim($reason))<10)throw new TrainingClosureException('Uveďte důvod zrušení nebo přesunu lekce.');
    $s=$pdo->prepare('SELECT * FROM planovane_treninky WHERE id=?'.trainingClosureLockSuffix($pdo));$s->execute([$planId]);$old=$s->fetch(PDO::FETCH_ASSOC);
    if(!$old||$old['stav']!=='planovany')throw new TrainingClosureException('Zrušit lze pouze nezapsaný plánovaný termín. Zapsanou docházku opravte verzovaně.');
    if($replacementId!==null){
        if($replacementId===$planId)throw new TrainingClosureException('Náhradní termín musí být jiná lekce.');
        coachScopeAssertPlan($pdo,$replacementId);$s=$pdo->prepare("SELECT 1 FROM planovane_treninky WHERE id=? AND stav='planovany'");$s->execute([$replacementId]);if(!$s->fetchColumn())throw new TrainingClosureException('Náhradní termín není dostupný.');
    }
    $pdo->prepare("UPDATE planovane_treninky SET stav='zruseny' WHERE id=?")->execute([$planId]);
    require_once __DIR__.'/club_makeup.php';clubMakeupCancelPlannedTarget($pdo,$planId,$actor);
    $pdo->prepare('INSERT INTO training_schedule_events(plan_id,replacement_plan_id,action,reason,before_json,actor_id) VALUES(?,?,?,?,?,?)')->execute([$planId,$replacementId,$replacementId?'rescheduled':'cancelled',trim($reason),json_encode($old,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$actor]);
    $eventId=(int)$pdo->lastInsertId();
    if((int)($old['rezervace_id']??0)>0){$pdo->prepare('UPDATE planovane_treninky SET rezervace_id=NULL WHERE id=?')->execute([$planId]);$pdo->prepare('DELETE FROM rezervace_sportovist WHERE id=?')->execute([(int)$old['rezervace_id']]);}
    trainingScheduleNotify($pdo,$eventId,$planId,$replacementId,$old);
}

/** Queue only; delivery revalidates the current family relationship. Missing e-mail is an operator task. */
function trainingScheduleNotify(PDO $pdo,int $eventId,int $planId,?int $replacementId,array $old):void {
    if(!memberChargeReadTableExists($pdo,'club_event_notifications'))return;
    require_once __DIR__.'/booking_notification.php';require_once __DIR__.'/notification_delivery_guard.php';
    $people=array_map('intval',array_column(trainingRosterBridgeExpectedForPlan($pdo,$planId),'id'));
    foreach(['training_rsvps'=>'plan_id','club_makeup_reservations'=>'target_plan_id'] as $table=>$column)if(memberChargeReadTableExists($pdo,$table)){$s=$pdo->prepare("SELECT sportovec_id FROM $table WHERE $column=?");$s->execute([$planId]);$people=array_merge($people,array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN)));}
    foreach(array_unique($people) as $person){
        $recipients=[];
        $s=$pdo->prepare('SELECT DISTINCT a.id,a.email FROM account_person_roles r JOIN verejni_uzivatele a ON a.id=r.account_id WHERE r.sportovec_id=?');$s->execute([$person]);
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $a)if(operationalNotificationPersonAllowed($pdo,(int)$a['id'],$person,(string)$a['email']))$recipients[]=$a;
        if(!$recipients)$recipients=[['id'=>0,'email'=>'']];
        foreach($recipients as $a){
            $valid=(bool)filter_var($a['email'],FILTER_VALIDATE_EMAIL);$key='training_schedule:'.$eventId.':'.$person.':'.$a['id'];
            $body='Lekce #'.$planId.' dne '.$old['datum'].' v '.$old['cas_od'].' byla zrušena.'.($replacementId?' Navazující termín #'.$replacementId.' najdete v rozvrhu; případnou náhradu znovu rezervujte.':' Rezervované náhrady byly uvolněny.').' Podrobnosti: '.appUrl('booking/soupiskove_treninky.php');
            $pdo->prepare('INSERT INTO club_event_notifications(dedupe_key,notification_type,recipient_email,recipient_name,subject_plain,body_plain,status,last_error) VALUES(?,?,?,?,?,?,?,?)')->execute([$key,'training_schedule_changed',$valid?$a['email']:'','','Změna termínu klubové lekce',$body,$valid?'pending':'failed',$valid?null:'manual_contact_required: trenér musí osobně předat změnu sportovci #'.$person.'.']);
            if($valid)notificationAuthorizationRecord($pdo,'club_event_notifications',(int)$pdo->lastInsertId(),(int)$a['id']);
        }
    }
}

/** Paper/telephone receipt time is explicit; transcription time and author are immutable. No cutoff override. */
function trainingPersonalExcuseRecord(PDO $pdo,int $planId,int $personId,string $receivedAt,string $source,string $reason,int $actor):array {
    require_once __DIR__.'/club_makeup.php';
    $received=trainingClosureDateTime($receivedAt);$reason=trim($reason);
    if($received>new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'))||!in_array($source,['paper','phone','in_person'],true)||mb_strlen($reason)<10||mb_strlen($reason)>1000)throw new TrainingClosureException('Doplňte skutečný čas přijetí, zdroj a důvod přepisu omluvy.');
    $pdo->beginTransaction();
    try{
        trainingClosureAssertActor($pdo,$actor);coachScopeAssertPlan($pdo,$planId);coachScopeAssertPeople($pdo,[$personId]);
        $s=$pdo->prepare('SELECT * FROM planovane_treninky WHERE id=?'.trainingClosureLockSuffix($pdo));$s->execute([$planId]);$plan=$s->fetch(PDO::FETCH_ASSOC);
        if(!$plan||$plan['stav']==='zruseny'||!clubMakeupPersonIsRegular($pdo,$planId,$personId,$plan['datum']))throw new TrainingClosureException('Omluva nepatří k platnému termínu a jeho sportovci.');
        if($received>=clubMakeupStart($plan))throw new TrainingClosureException('Přijetí omluvy musí předcházet začátku lekce; pozdější absenci evidujte v docházce.');
        $s=$pdo->prepare('SELECT id FROM training_personal_excuses WHERE plan_id=? AND sportovec_id=?');$s->execute([$planId,$personId]);if($s->fetchColumn())throw new TrainingClosureException('Přepis osobní omluvy již existuje a nelze přepsat jeho čas.');
        $s=$pdo->prepare('SELECT id FROM club_makeup_credits WHERE source_plan_id=? AND sportovec_id=?');$s->execute([$planId,$personId]);if($s->fetchColumn())throw new TrainingClosureException('K lekci již existuje nárok. Jeho zdrojovou omluvu nelze přepisovat.');
        $entitlement=clubMakeupExcuseEntitlement($pdo,$planId,$personId,$received,true);
        $denial=$entitlement?null:clubMakeupExcuseFailureReason($pdo,$planId,$personId,$received,true);
        $s=$pdo->prepare('SELECT id FROM training_rsvps WHERE plan_id=? AND sportovec_id=?');$s->execute([$planId,$personId]);$rsvp=$s->fetchColumn();
        if(!$rsvp){$pdo->prepare("INSERT INTO training_rsvps(plan_id,sportovec_id,response,member_note) VALUES(?,?,'not_going',?)")->execute([$planId,$personId,'Osobní omluva: '.$source]);$rsvp=(int)$pdo->lastInsertId();}
        $pdo->prepare("UPDATE training_rsvps SET response='not_going',makeup_excused_at=?,makeup_source_enrollment_id=?,makeup_cutoff_hours=?,makeup_denial_reason=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$entitlement?$receivedAt:null,$entitlement['enrollment_id']??null,$entitlement['hours']??null,$denial,$rsvp]);
        $pdo->prepare('INSERT INTO training_personal_excuses(plan_id,sportovec_id,received_at,recorded_by,source,reason,entitlement_json) VALUES(?,?,?,?,?,?,?)')->execute([$planId,$personId,$receivedAt,$actor,$source,$reason,json_encode(['entitlement'=>$entitlement,'denial'=>$denial],JSON_THROW_ON_ERROR)]);
        if((int)$plan['trenink_id']>0)clubMakeupFinalizeSource($pdo,$planId,(int)$plan['trenink_id'],$actor);
        $pdo->commit();return ['eligible'=>$entitlement!==null,'denial'=>$denial];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
