<?php
declare(strict_types=1);

require_once __DIR__ . '/club_event.php';
require_once __DIR__ . '/club_event_roster_target.php';
require_once __DIR__ . '/account_person_role.php';
require_once __DIR__ . '/member_charge_admin.php';

final class ClubCalendarException extends RuntimeException {}

/** @return array<string,string> */
function clubCalendarKinds(): array
{
    return ['race'=>'Závod','camp'=>'Soustředění','training'=>'Školení','meeting'=>'Schůze','other'=>'Ostatní'];
}

/** @return array<string,string> */
function clubCalendarPlanningStatuses(): array
{
    return ['planned'=>'Předběžně plánováno','confirmed'=>'Potvrzeno','cancelled'=>'Zrušeno','completed'=>'Proběhlo'];
}

/** @return array<string,string> */
function clubCalendarVisibilities(): array
{
    return ['staff'=>'Pouze trenéři','rosters'=>'Vybrané soupisky','members'=>'Všichni přihlášení','public'=>'Veřejně bez přihlášení'];
}

function clubCalendarText(mixed $value, int $max, string $label, bool $required = false): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', (string)$value));
    if (($required && $text === '') || mb_strlen($text, 'UTF-8') > $max || preg_match('/[<>]/u', $text) === 1) {
        throw new InvalidArgumentException($label . ' musí být platný prostý text do ' . $max . ' znaků.');
    }
    return $text;
}

function clubCalendarLongText(mixed $value, int $max, string $label): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$value));
    if (mb_strlen($text, 'UTF-8') > $max || preg_match('/<\/?(?:script|iframe|object|embed)\b/i', $text) === 1) {
        throw new InvalidArgumentException($label . ' musí být prostý text do ' . $max . ' znaků.');
    }
    return $text;
}

function clubCalendarDateTime(string $value, string $label): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date) throw new InvalidArgumentException($label . ' nemá platné datum a čas.');
    return $date->format('Y-m-d H:i:s');
}

/** @return list<int> */
function clubCalendarTeamIds(array $values): array
{
    $ids = [];
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0) $ids[$id] = $id;
    }
    ksort($ids);
    return array_values($ids);
}

/** @return array<string,mixed> */
function clubCalendarValues(array $input): array
{
    $kind = (string)($input['activity_kind'] ?? 'other');
    $planning = (string)($input['planning_status'] ?? 'planned');
    $visibility = (string)($input['visibility'] ?? 'staff');
    if (!isset(clubCalendarKinds()[$kind])) throw new InvalidArgumentException('Vyberte platný druh akce.');
    if (!isset(clubCalendarPlanningStatuses()[$planning])) throw new InvalidArgumentException('Vyberte platný stav akce.');
    if (!isset(clubCalendarVisibilities()[$visibility])) throw new InvalidArgumentException('Vyberte platnou viditelnost.');
    $startsAt = clubCalendarDateTime((string)($input['starts_at'] ?? ''), 'Začátek');
    $endsAt = clubCalendarDateTime((string)($input['ends_at'] ?? ''), 'Konec');
    if ($startsAt >= $endsAt) throw new InvalidArgumentException('Konec akce musí být po začátku.');
    $capacity = max(1, min(10000, (int)($input['capacity'] ?? 100)));
    $fee = (int)($input['participant_fee_minor'] ?? 0);
    if ($fee < 0 || $fee > 100000000) throw new InvalidArgumentException('Cena musí být od 0 do 1 000 000 Kč.');
    $dueDays = max(1, min(90, (int)($input['fee_due_days'] ?? 14)));
    $teams = clubCalendarTeamIds((array)($input['team_ids'] ?? []));
    if ($visibility === 'rosters' && $teams === []) {
        throw new InvalidArgumentException('Pro kalendář soupisek vyberte alespoň jednu soupisku.');
    }
    return [
        'name'=>clubCalendarText($input['name'] ?? '',255,'Název',true),
        'activity_kind'=>$kind,'planning_status'=>$planning,'visibility'=>$visibility,
        'starts_at'=>$startsAt,'ends_at'=>$endsAt,
        'location'=>clubCalendarText($input['location'] ?? '',255,'Místo',true),
        'public_description_plain'=>clubCalendarLongText($input['public_description_plain'] ?? '',4000,'Popis pro členy'),
        'internal_note'=>clubCalendarLongText($input['internal_note'] ?? '',4000,'Interní poznámka'),
        'capacity'=>$capacity,'participant_fee_minor'=>$fee,'fee_due_days'=>$dueDays,'team_ids'=>$teams,
    ];
}

function clubCalendarAudit(PDO $pdo, int $eventId, int $actorId, string $action, string $note, array $payload): void
{
    clubEventAudit($pdo, $eventId, $actorId, $action, 'calendar', $eventId, $note, $payload);
}

/** @return array{id:int,created:bool} */
function clubCalendarSaveEvent(PDO $pdo, int $actorId, int $eventId, array $input): array
{
    if ($actorId < 1) throw new InvalidArgumentException('Akci může uložit pouze přihlášený trenér.');
    $value = clubCalendarValues($input);
    $pdo->beginTransaction();
    try {
        $created = $eventId < 1;
        if ($created) {
            $code = 'PLAN-' . (new DateTimeImmutable($value['starts_at']))->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $eventType = $value['activity_kind'] === 'camp' ? 'camp' : 'club_event';
            $pricing = $value['participant_fee_minor'] > 0 ? 'member_charge' : 'free';
            $insert = $pdo->prepare(
                'INSERT INTO club_events(code,event_type,name,description_plain,audience_label,min_age,max_age,capacity,'
                . 'pricing_policy,currency,registration_starts_at,registration_ends_at,status,created_by_trainer_id,'
                . 'activity_kind,planning_status,visibility,public_description_plain,internal_note,participant_fee_minor,'
                . 'fee_due_days,public_published_at) VALUES(?,?,?,?,?,NULL,NULL,?,?,\'CZK\',NULL,NULL,\'draft\',?,?,?,?,?,?,?,?,?)'
            );
            $insert->execute([$code,$eventType,$value['name'],$value['public_description_plain'],'Kluboví sportovci',
                $value['capacity'],$pricing,$actorId,$value['activity_kind'],$value['planning_status'],$value['visibility'],
                $value['public_description_plain'],$value['internal_note'],$value['participant_fee_minor'],$value['fee_due_days'],
                $value['visibility']==='public' ? date('Y-m-d H:i:s') : null]);
            $eventId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO club_event_sessions(event_id,starts_at,ends_at,location,capacity_override,status) '
                . "VALUES(?,?,?,?,NULL,'scheduled')")->execute([$eventId,$value['starts_at'],$value['ends_at'],$value['location']]);
            $before = null;
        } else {
            $before = clubEventLock($pdo, $eventId);
            if (!$before) throw new ClubCalendarException('Akce nebyla nalezena.');
            if ((string)$before['status'] === 'archived') throw new ClubCalendarException('Archivovanou akci už nelze měnit.');
            $eventType = $value['activity_kind'] === 'camp' ? 'camp' : 'club_event';
            $pricing = $value['participant_fee_minor'] > 0 ? 'member_charge' : 'free';
            $status = $value['planning_status'] === 'cancelled' && $before['status'] === 'open' ? 'closed' : $before['status'];
            $pdo->prepare('UPDATE club_events SET event_type=?,name=?,description_plain=?,capacity=?,pricing_policy=?,'
                . 'activity_kind=?,planning_status=?,visibility=?,public_description_plain=?,internal_note=?,'
                . 'participant_fee_minor=?,fee_due_days=?,status=?,public_published_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$eventType,$value['name'],$value['public_description_plain'],$value['capacity'],$pricing,
                    $value['activity_kind'],$value['planning_status'],$value['visibility'],$value['public_description_plain'],
                    $value['internal_note'],$value['participant_fee_minor'],$value['fee_due_days'],$status,
                    $value['visibility']==='public' ? ($before['public_published_at'] ?: date('Y-m-d H:i:s')) : null,$eventId]);
            $session = $pdo->prepare("SELECT id FROM club_event_sessions WHERE event_id=? AND status='scheduled' ORDER BY starts_at,id LIMIT 1");
            $session->execute([$eventId]);
            $sessionId = (int)$session->fetchColumn();
            if ($sessionId > 0) {
                $pdo->prepare('UPDATE club_event_sessions SET starts_at=?,ends_at=?,location=? WHERE id=?')
                    ->execute([$value['starts_at'],$value['ends_at'],$value['location'],$sessionId]);
            } else {
                $pdo->prepare('INSERT INTO club_event_sessions(event_id,starts_at,ends_at,location,capacity_override,status) '
                    . "VALUES(?,?,?,?,NULL,'scheduled')")->execute([$eventId,$value['starts_at'],$value['ends_at'],$value['location']]);
            }
        }
        clubCalendarReplaceTargetsInTransaction($pdo,$eventId,$value['team_ids'],$actorId);
        clubCalendarAudit($pdo,$eventId,$actorId,$created?'calendar_create':'calendar_update',
            $created?'Založení plánované akce.':'Úprava plánované akce.',['before'=>$before,'after'=>$value]);
        $pdo->commit();
        return ['id'=>$eventId,'created'=>$created];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubCalendarException) throw $exception;
        throw new ClubCalendarException('Akci se nepodařilo uložit bez částečného zápisu.',0,$exception);
    }
}

/** @param list<int> $teamIds */
function clubCalendarReplaceTargetsInTransaction(PDO $pdo, int $eventId, array $teamIds, int $actorId): void
{
    $available = [];
    if ($teamIds !== []) {
        $placeholders = implode(',', array_fill(0,count($teamIds),'?'));
        $statement = $pdo->prepare("SELECT id FROM club_teams WHERE id IN ($placeholders) AND status='active'");
        $statement->execute($teamIds);
        $available = array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
        sort($available);
        $expected = $teamIds; sort($expected);
        if ($available !== $expected) throw new ClubCalendarException('Některá vybraná soupiska už není aktivní.');
    }
    $pdo->prepare('DELETE FROM club_event_roster_targets WHERE event_id=?')->execute([$eventId]);
    $insert = $pdo->prepare('INSERT INTO club_event_roster_targets(event_id,team_id,actor_trainer_id,decision_note) VALUES(?,?,?,?)');
    foreach ($teamIds as $teamId) $insert->execute([$eventId,$teamId,$actorId,'Cílení nastavené v klubovém kalendáři.']);
}

/** @return list<array<string,mixed>> */
function clubCalendarEvents(PDO $pdo, string $from, string $to, ?int $accountId = null, bool $staff = false): array
{
    $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d',$from);
    $toDate = DateTimeImmutable::createFromFormat('!Y-m-d',$to);
    if (!$fromDate || !$toDate || $fromDate > $toDate) throw new InvalidArgumentException('Neplatný rozsah kalendáře.');
    $statement = $pdo->prepare(
        'SELECT e.*,s.id session_id,s.starts_at,s.ends_at,s.location '
        . 'FROM club_events e JOIN club_event_sessions s ON s.event_id=e.id AND s.status=\'scheduled\' '
        . 'WHERE s.starts_at<? AND s.ends_at>=? AND e.status<>\'archived\' ORDER BY s.starts_at,e.name,e.id'
    );
    $statement->execute([$toDate->modify('+1 day')->format('Y-m-d 00:00:00'),$fromDate->format('Y-m-d 00:00:00')]);
    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!$staff && !clubCalendarAccountCanSee($pdo,(int)$row['id'],$accountId,(string)$row['visibility'])) continue;
        $row['teams'] = clubEventRosterTargets($pdo,(int)$row['id']);
        $row['links'] = clubCalendarLinks($pdo,(int)$row['id']);
        $row['people'] = clubCalendarPeople($pdo,(int)$row['id'],$staff);
        $row['registration_count'] = clubCalendarConfirmedCount($pdo,(int)$row['id']);
        $row['vehicle_conflicts'] = $staff ? clubCalendarVehicleConflictsForEvent($pdo,(int)$row['id']) : [];
        if (!$staff) unset($row['internal_note']);
        $rows[] = $row;
    }
    return $rows;
}

function clubCalendarAccountCanSee(PDO $pdo, int $eventId, ?int $accountId, string $visibility): bool
{
    if ($visibility === 'public') return true;
    if ($accountId === null || $accountId < 1 || $visibility === 'staff') return false;
    if ($visibility === 'members') return true;
    if ($visibility !== 'rosters') return false;
    $statement = $pdo->prepare(
        "SELECT 1 FROM club_event_roster_targets rt JOIN club_roster_members m ON m.team_id=rt.team_id "
        . "JOIN account_person_roles ar ON ar.sportovec_id=m.sportovec_id "
        . "JOIN club_teams t ON t.id=m.team_id JOIN club_seasons s ON s.id=t.season_id "
        . "WHERE rt.event_id=? AND ar.account_id=? AND ar.status='approved' AND m.status='active' "
        . "AND (m.valid_to IS NULL OR m.valid_to>=CURRENT_DATE) AND t.status='active' AND s.status='active' LIMIT 1"
    );
    $statement->execute([$eventId,$accountId]);
    return (bool)$statement->fetchColumn();
}

/** @return array<string,mixed>|null */
function clubCalendarDetail(PDO $pdo, int $eventId): ?array
{
    $statement = $pdo->prepare('SELECT e.*,s.id session_id,s.starts_at,s.ends_at,s.location FROM club_events e '
        . "LEFT JOIN club_event_sessions s ON s.event_id=e.id AND s.status='scheduled' WHERE e.id=? ORDER BY s.starts_at LIMIT 1");
    $statement->execute([$eventId]);
    $event = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$event) return null;
    $event['teams']=clubEventRosterTargets($pdo,$eventId);
    $event['people']=clubCalendarPeople($pdo,$eventId,true);
    $event['links']=clubCalendarLinks($pdo,$eventId);
    $event['vehicles']=clubCalendarVehicles($pdo,$eventId);
    $event['participants']=clubCalendarParticipants($pdo,$eventId);
    $event['vehicle_conflicts']=clubCalendarVehicleConflictsForEvent($pdo,$eventId);
    return $event;
}

/** @return list<array<string,mixed>> */
function clubCalendarPeople(PDO $pdo, int $eventId, bool $staff): array
{
    $sql='SELECT p.*,t.jmeno trainer_name FROM club_event_people p LEFT JOIN treneri t ON t.id=p.trainer_id WHERE p.event_id=?';
    if (!$staff) $sql.=' AND p.visible_to_members=1';
    $sql.=' ORDER BY p.id';
    $statement=$pdo->prepare($sql);$statement->execute([$eventId]);
    $rows=$statement->fetchAll(PDO::FETCH_ASSOC);
    if (!$staff) foreach($rows as &$row){unset($row['external_contact'],$row['note']);} unset($row);
    return $rows;
}

/** @return array{id:int} */
function clubCalendarAddPerson(PDO $pdo,int $eventId,int $actorId,array $input):array
{
    $role=(string)($input['person_role']??'other');if(!in_array($role,['lead_coach','coach','driver','organizer','other'],true))throw new InvalidArgumentException('Neplatná role osoby.');
    $trainerId=(int)($input['trainer_id']??0);$trainerId=$trainerId>0?$trainerId:null;
    $name=clubCalendarText($input['external_name']??'',255,'Jméno externí osoby');
    if($trainerId===null&&$name==='')throw new InvalidArgumentException('Vyberte trenéra nebo napište jméno externí osoby.');
    $contact=clubCalendarText($input['external_contact']??'',255,'Kontakt');$note=clubCalendarText($input['note']??'',1000,'Poznámka');
    $pdo->beginTransaction();try{if(!clubEventLock($pdo,$eventId))throw new ClubCalendarException('Akce nebyla nalezena.');
        if($trainerId!==null){$s=$pdo->prepare('SELECT 1 FROM treneri WHERE id=? AND aktivni=1');$s->execute([$trainerId]);if(!$s->fetchColumn())throw new ClubCalendarException('Aktivní trenér nebyl nalezen.');}
        $pdo->prepare('INSERT INTO club_event_people(event_id,person_role,trainer_id,external_name,external_contact,visible_to_members,note,created_by_trainer_id) VALUES(?,?,?,?,?,?,?,?)')->execute([$eventId,$role,$trainerId,$name!==''?$name:null,$contact!==''?$contact:null,isset($input['visible_to_members'])?1:0,$note,$actorId]);$id=(int)$pdo->lastInsertId();
        clubCalendarAudit($pdo,$eventId,$actorId,'calendar_add_person','Přidána zajišťující osoba.',['person_id'=>$id,'role'=>$role]);$pdo->commit();return['id'=>$id];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Osobu se nepodařilo přidat.',0,$e);}
}

/** @return list<array<string,mixed>> */
function clubCalendarLinks(PDO $pdo,int $eventId):array
{$s=$pdo->prepare('SELECT * FROM club_event_links WHERE event_id=? ORDER BY sort_order,id');$s->execute([$eventId]);return$s->fetchAll(PDO::FETCH_ASSOC);}

/** @return array{id:int} */
function clubCalendarAddLink(PDO $pdo,int $eventId,int $actorId,array$input):array
{
    $type=(string)($input['link_type']??'other');if(!in_array($type,['propositions','registration','map','results','other'],true))throw new InvalidArgumentException('Neplatný typ odkazu.');
    $label=clubCalendarText($input['label']??'',255,'Název odkazu',true);$url=trim((string)($input['url']??''));
    $parts=parse_url($url);if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||strlen($url)>2048)throw new InvalidArgumentException('Odkaz musí být bezpečná HTTP nebo HTTPS adresa bez přihlašovacích údajů.');
    $pdo->beginTransaction();try{if(!clubEventLock($pdo,$eventId))throw new ClubCalendarException('Akce nebyla nalezena.');$pdo->prepare('INSERT INTO club_event_links(event_id,link_type,label,url,sort_order,created_by_trainer_id) VALUES(?,?,?,?,?,?)')->execute([$eventId,$type,$label,$url,(int)($input['sort_order']??0),$actorId]);$id=(int)$pdo->lastInsertId();clubCalendarAudit($pdo,$eventId,$actorId,'calendar_add_link','Přidán odkaz.',['link_id'=>$id,'type'=>$type]);$pdo->commit();return['id'=>$id];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Odkaz se nepodařilo přidat.',0,$e);}
}

/** @return list<array<string,mixed>> */
function clubCalendarVehicles(PDO $pdo,int $eventId):array
{
    $s=$pdo->prepare('SELECT r.*,v.znacka_model,v.spz,t.jmeno driver_trainer_name FROM club_event_vehicle_reservations r JOIN ucto_vozidla v ON v.id=r.vehicle_id LEFT JOIN treneri t ON t.id=r.driver_trainer_id WHERE r.event_id=? ORDER BY r.starts_at,r.id');$s->execute([$eventId]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as&$row){$row['conflicts']=clubCalendarVehicleConflicts($pdo,(int)$row['vehicle_id'],(string)$row['starts_at'],(string)$row['ends_at'],(int)$row['id']);}unset($row);return$rows;
}

/** @return list<array<string,mixed>> */
function clubCalendarVehicleConflicts(PDO$pdo,int$vehicleId,string$startsAt,string$endsAt,int$excludeId=0):array
{
    $s=$pdo->prepare("SELECT r.id,r.event_id,r.starts_at,r.ends_at,e.name,e.planning_status FROM club_event_vehicle_reservations r JOIN club_events e ON e.id=r.event_id WHERE r.vehicle_id=? AND r.status='active' AND r.id<>? AND r.starts_at<? AND r.ends_at>? ORDER BY r.starts_at,r.id");$s->execute([$vehicleId,$excludeId,$endsAt,$startsAt]);return$s->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function clubCalendarVehicleConflictsForEvent(PDO$pdo,int$eventId):array
{
    $conflicts=[];foreach(clubCalendarVehicles($pdo,$eventId)as$row)foreach($row['conflicts']as$conflict)$conflicts[]=['reservation_id'=>(int)$row['id'],'vehicle'=>(string)$row['znacka_model'].' '.(string)$row['spz'],'conflict'=>$conflict];return$conflicts;
}

/** @return array{id:int,conflict_count:int} */
function clubCalendarReserveVehicle(PDO$pdo,int$eventId,int$actorId,array$input):array
{
    $vehicleId=(int)($input['vehicle_id']??0);$startsAt=clubCalendarDateTime((string)($input['starts_at']??''),'Začátek rezervace');$endsAt=clubCalendarDateTime((string)($input['ends_at']??''),'Konec rezervace');if($vehicleId<1||$startsAt>=$endsAt)throw new InvalidArgumentException('Vyberte vozidlo a platný interval.');
    $driverId=(int)($input['driver_trainer_id']??0);$driverId=$driverId>0?$driverId:null;$driverName=clubCalendarText($input['driver_name']??'',255,'Jméno řidiče');$note=clubCalendarText($input['note']??'',1000,'Poznámka');$ack=isset($input['conflict_acknowledged']);
    $pdo->beginTransaction();try{if(!clubEventLock($pdo,$eventId))throw new ClubCalendarException('Akce nebyla nalezena.');$v=$pdo->prepare('SELECT 1 FROM ucto_vozidla WHERE id=?');$v->execute([$vehicleId]);if(!$v->fetchColumn())throw new ClubCalendarException('Vozidlo nebylo nalezeno.');
        $conflicts=clubCalendarVehicleConflicts($pdo,$vehicleId,$startsAt,$endsAt);if($conflicts!==[]&&!$ack)throw new ClubCalendarException('Vozidlo je v tomto čase už rezervované. Zaškrtněte výrazné potvrzení, pokud je kolize domluvená mimo systém.');
        $conflictNote=$conflicts!==[]?clubCalendarText($input['conflict_note']??'',1000,'Vysvětlení kolize',true):null;
        $pdo->prepare('INSERT INTO club_event_vehicle_reservations(event_id,vehicle_id,starts_at,ends_at,driver_trainer_id,driver_name,note,conflict_acknowledged,conflict_note,status,created_by_trainer_id) VALUES(?,?,?,?,?,?,?,?,?,\'active\',?)')->execute([$eventId,$vehicleId,$startsAt,$endsAt,$driverId,$driverName!==''?$driverName:null,$note,$conflicts!==[]?1:0,$conflictNote,$actorId]);$id=(int)$pdo->lastInsertId();clubCalendarAudit($pdo,$eventId,$actorId,'calendar_reserve_vehicle',$conflicts===[]?'Rezervováno vozidlo.':'Vozidlo rezervováno přes známou kolizi.',['reservation_id'=>$id,'vehicle_id'=>$vehicleId,'conflicts'=>array_column($conflicts,'id'),'acknowledged'=>$conflicts!==[]]);$pdo->commit();return['id'=>$id,'conflict_count'=>count($conflicts)];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Vozidlo se nepodařilo rezervovat.',0,$e);}
}

function clubCalendarConfirmedCount(PDO$pdo,int$eventId):int
{
    $s=$pdo->prepare(
        "SELECT COUNT(*) FROM ("
        . "SELECT sportovec_id FROM club_event_planned_participants WHERE event_id=? AND status='confirmed' "
        . "UNION SELECT sportovec_id FROM club_event_registrations WHERE event_id=? AND status IN ('confirmed','payment_pending')"
        . ") confirmed_people"
    );
    $s->execute([$eventId,$eventId]);
    return(int)$s->fetchColumn();
}

/** @return list<array<string,mixed>> */
function clubCalendarParticipants(PDO$pdo,int$eventId):array
{
    $s=$pdo->prepare('SELECT p.*,sp.jmeno,sp.prijmeni,vu.email,c.status charge_status,c.amount_minor FROM club_event_planned_participants p JOIN sportovci sp ON sp.id=p.sportovec_id LEFT JOIN verejni_uzivatele vu ON vu.id=p.account_id LEFT JOIN club_member_charges c ON c.id=p.charge_id WHERE p.event_id=? ORDER BY CASE p.status WHEN \'confirmed\' THEN 0 WHEN \'planned\' THEN 1 WHEN \'waitlisted\' THEN 2 ELSE 3 END,sp.prijmeni,sp.jmeno,p.id');$s->execute([$eventId]);return$s->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed>|null */
function clubCalendarPayer(PDO$pdo,int$sportovecId,?int$preferredAccountId=null):?array
{
    $sql="SELECT ar.account_id,ar.relation_role,vu.email FROM account_person_roles ar JOIN verejni_uzivatele vu ON vu.id=ar.account_id WHERE ar.sportovec_id=? AND ar.status='approved' AND vu.aktivni=1 AND vu.email_overeno=1";$params=[$sportovecId];if($preferredAccountId!==null){$sql.=' AND ar.account_id=?';$params[]=$preferredAccountId;}$sql.=" ORDER BY CASE ar.relation_role WHEN 'self' THEN 0 ELSE 1 END,ar.id LIMIT 1";$s=$pdo->prepare($sql);$s->execute($params);$row=$s->fetch(PDO::FETCH_ASSOC);return$row?:null;
}

/** @return array{id:int,status:string,payer_missing:bool,charge_id:?int} */
function clubCalendarAddParticipant(PDO$pdo,int$eventId,int$sportovecId,int$actorId,bool$confirmed,?int$preferredAccountId=null):array
{
    if(min($eventId,$sportovecId,$actorId)<1)throw new InvalidArgumentException('Vyberte akci, sportovce a trenéra.');
    $pdo->beginTransaction();try{$event=clubEventLock($pdo,$eventId);if(!$event)throw new ClubCalendarException('Akce nebyla nalezena.');$person=$pdo->prepare('SELECT 1 FROM sportovci WHERE id=?');$person->execute([$sportovecId]);if(!$person->fetchColumn())throw new ClubCalendarException('Sportovec nebyl nalezen.');
        $result=clubCalendarUpsertParticipantInTransaction($pdo,$event,$sportovecId,$actorId,$confirmed,$preferredAccountId,'trainer');$pdo->commit();return$result;
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Účastníka se nepodařilo uložit.',0,$e);}
}

/** @param array<string,mixed> $event @return array{id:int,status:string,payer_missing:bool,charge_id:?int} */
function clubCalendarUpsertParticipantInTransaction(PDO$pdo,array$event,int$sportovecId,int$actorId,bool$confirmed,?int$preferredAccountId,string$source):array
{
    $eventId=(int)$event['id'];$payer=clubCalendarPayer($pdo,$sportovecId,$preferredAccountId);
    $find=$pdo->prepare('SELECT * FROM club_event_planned_participants WHERE event_id=? AND sportovec_id=?');$find->execute([$eventId,$sportovecId]);$row=$find->fetch(PDO::FETCH_ASSOC);
    $status=$confirmed?'confirmed':'planned';
    $alreadyConfirmed=$row&&$row['status']==='confirmed';
    if($confirmed&&!$alreadyConfirmed&&clubCalendarConfirmedCount($pdo,$eventId)>=(int)$event['capacity'])$status='waitlisted';
    if($row){$id=(int)$row['id'];$pdo->prepare('UPDATE club_event_planned_participants SET account_id=?,status=?,confirmed_by_trainer_id=?,confirmed_at=?,cancelled_at=NULL WHERE id=?')->execute([$payer['account_id']??null,$status,$confirmed&&$actorId>0?$actorId:null,$confirmed?date('Y-m-d H:i:s'):null,$id]);}
    else{$pdo->prepare('INSERT INTO club_event_planned_participants(event_id,sportovec_id,account_id,status,created_by_trainer_id,confirmed_by_trainer_id,confirmed_at) VALUES(?,?,?,?,?,?,?)')->execute([$eventId,$sportovecId,$payer['account_id']??null,$status,$source==='trainer'?$actorId:null,$confirmed&&$actorId>0?$actorId:null,$confirmed?date('Y-m-d H:i:s'):null]);$id=(int)$pdo->lastInsertId();}
    $registrationId=null;$chargeId=null;
    if($status==='confirmed'&&$payer){$registrationId=clubCalendarEnsureRegistrationInTransaction($pdo,$event,$sportovecId,(int)$payer['account_id'],(string)$payer['relation_role']);if((int)$event['participant_fee_minor']>0)$chargeId=clubCalendarEnsureChargeInTransaction($pdo,$event,$sportovecId,(int)$payer['account_id'],$actorId,$source);$pdo->prepare('UPDATE club_event_planned_participants SET registration_id=?,charge_id=? WHERE id=?')->execute([$registrationId,$chargeId,$id]);}
    if ($source === 'trainer') {
        clubCalendarAudit($pdo,$eventId,$actorId,$confirmed?'calendar_confirm_participant':'calendar_plan_participant',
            'Účastník přidán trenérem.',['participant_id'=>$id,'sportovec_id'=>$sportovecId,'status'=>$status,
                'payer_missing'=>$confirmed&&!$payer,'charge_id'=>$chargeId]);
    }
    return['id'=>$id,'status'=>$status,'payer_missing'=>$confirmed&&!$payer,'charge_id'=>$chargeId];
}

function clubCalendarEnsureRegistrationInTransaction(PDO$pdo,array$event,int$sportovecId,int$accountId,string$relationRole):int
{
    $s=$pdo->prepare('SELECT id,status FROM club_event_registrations WHERE event_id=? AND sportovec_id=?');$s->execute([(int)$event['id'],$sportovecId]);$row=$s->fetch(PDO::FETCH_ASSOC);if($row){$pdo->prepare("UPDATE club_event_registrations SET account_id=?,status='confirmed',cancelled_at=NULL,cancellation_note=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$accountId,(int)$row['id']]);return(int)$row['id'];}
    $eligibility=clubEventRosterEligibility($pdo,(int)$event['id'],$sportovecId);if(!$eligibility)$eligibility=['mode'=>'public','team_ids'=>[],'reason'=>'Účast vložená trenérem mimo cílení soupisky.'];$deadline=(string)($event['starts_at']??'');if($deadline===''){$q=$pdo->prepare("SELECT MIN(starts_at) FROM club_event_sessions WHERE event_id=? AND status='scheduled'");$q->execute([(int)$event['id']]);$deadline=(string)$q->fetchColumn();}
    $pdo->prepare('INSERT INTO club_event_registrations(event_id,account_id,sportovec_id,relation_role_snapshot,status,registered_at,consent_version_snapshot,consent_text_snapshot,consented_at,cancellation_policy_snapshot,cancellation_deadline_snapshot,eligibility_team_ids_snapshot,eligibility_reason_snapshot) VALUES(?,?,?,?,\'confirmed\',CURRENT_TIMESTAMP,\'calendar-v1\',\'Souhlas s účastí na klubové akci.\',CURRENT_TIMESTAMP,\'Účast lze zrušit do začátku akce.\',?,?,?)')->execute([(int)$event['id'],$accountId,$sportovecId,$relationRole,$deadline,clubEventRosterEligibilityJson($eligibility),(string)$eligibility['reason']]);$id=(int)$pdo->lastInsertId();clubEventRegistrationAudit($pdo,$id,'system',null,'calendar_confirm',null,'confirmed','Potvrzená účast z klubového kalendáře.');return$id;
}

function clubCalendarEnsureChargeInTransaction(PDO$pdo,array$event,int$sportovecId,int$accountId,int$actorId,string$source):int
{
    $external='event:'.(int)$event['id'].':athlete:'.$sportovecId;$existing=$pdo->prepare("SELECT id FROM club_member_charges WHERE source_system='club_event' AND source_external_id=?");$existing->execute([$external]);$id=(int)$existing->fetchColumn();if($id>0)return$id;
    $settings=shopBankSettingsEffective($pdo);do{$code=memberChargeAdminPublicCode();$check=$pdo->prepare('SELECT 1 FROM club_member_charges WHERE public_code=?');$check->execute([$code]);}while($check->fetchColumn());
    $starts=(string)($event['starts_at']??'');if($starts===''){$s=$pdo->prepare("SELECT MIN(starts_at) FROM club_event_sessions WHERE event_id=? AND status='scheduled'");$s->execute([(int)$event['id']]);$starts=(string)$s->fetchColumn();}$eventDate=substr($starts,0,10);$due=(new DateTimeImmutable('today'))->modify('+'.(int)$event['fee_due_days'].' days')->format('Y-m-d');
    $values=['sportovec_id'=>$sportovecId,'payer_account_id'=>$accountId,'title_snapshot'=>'Účast: '.(string)$event['name'],'period_from'=>$eventDate?:null,'period_to'=>$eventDate?:null,'amount_minor'=>(int)$event['participant_fee_minor'],'currency'=>'CZK','due_on'=>$due];
    $pdo->prepare("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,amount_minor,currency,due_on,status,source_system,source_external_id,source_import_run_id) VALUES(?,?,?,'club_event',?,?,?,?,?,?,'pending','club_event',?,NULL)")->execute([$sportovecId,$accountId,$code,$values['title_snapshot'],$values['period_from'],$values['period_to'],$values['amount_minor'],'CZK',$due,$external]);$id=(int)$pdo->lastInsertId();$payment=memberChargeAdminInsertPayment($pdo,$id,$values,$settings,$code,max(1,$actorId));
    $pdo->prepare('INSERT INTO club_member_charge_events(charge_id,action,from_status,to_status,actor_type,actor_id,reason,snapshot_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,'event_participation_create',null,'pending',$source==='account'?'account':'trainer',$actorId>0?$actorId:$accountId,'Potvrzená účast na klubové akci.',json_encode($values+['event_id'=>(int)$event['id'],'payment_id'=>$payment['id']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);return$id;
}

/** @return array{id:int,status:string,payer_missing:bool,charge_id:?int} */
function clubCalendarFamilyRegister(PDO$pdo,int$eventId,int$accountId,int$sportovecId,bool$consented):array
{
    if(min($eventId,$accountId,$sportovecId)<1||!$consented)throw new InvalidArgumentException('Přihlášení vyžaduje akci, osobu a potvrzený souhlas.');$relation=clubEventEligibleRelation($pdo,$accountId,$sportovecId);if(!$relation)throw new ClubCalendarException('Tuto osobu nemáte schválenou pro přihlášení.');
    $pdo->beginTransaction();try{$event=clubEventLock($pdo,$eventId);if(!$event||$event['status']!=='open'||$event['planning_status']!=='confirmed')throw new ClubCalendarException('Přihlašování na tuto akci není otevřené.');if(!clubCalendarAccountCanSee($pdo,$eventId,$accountId,(string)$event['visibility']))throw new ClubCalendarException('Akce není určena vašemu účtu.');if(!clubEventRosterEligibility($pdo,$eventId,$sportovecId))throw new ClubCalendarException('Vybraná osoba není v cílové soupisce akce.');$event['starts_at']=(string)$pdo->query('SELECT MIN(starts_at) FROM club_event_sessions WHERE event_id='.(int)$eventId)->fetchColumn();$result=clubCalendarUpsertParticipantInTransaction($pdo,$event,$sportovecId,0,true,$accountId,'account');$pdo->commit();return$result;}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Přihlášení se nepodařilo uložit.',0,$e);}
}

/** @return array{changed:bool,status:string} */
function clubCalendarSetRegistration(PDO$pdo,int$eventId,int$actorId,bool$open):array
{
    $pdo->beginTransaction();try{$event=clubEventLock($pdo,$eventId);if(!$event)throw new ClubCalendarException('Akce nebyla nalezena.');if($open&&$event['planning_status']!=='confirmed')throw new ClubCalendarException('Přihlašování lze otevřít až u potvrzené akce.');$count=$pdo->prepare("SELECT COUNT(*) FROM club_event_sessions WHERE event_id=? AND status='scheduled'");$count->execute([$eventId]);if($open&&(int)$count->fetchColumn()<1)throw new ClubCalendarException('Akce musí mít termín.');$target=$open?'open':'closed';$changed=$event['status']!==$target;if($changed)$pdo->prepare('UPDATE club_events SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$target,$eventId]);clubCalendarAudit($pdo,$eventId,$actorId,$open?'calendar_open_registration':'calendar_close_registration',$open?'Trenér otevřel přihlašování.':'Trenér uzavřel přihlašování.',['before'=>$event['status'],'after'=>$target]);$pdo->commit();return['changed'=>$changed,'status'=>$target];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Stav přihlašování se nepodařilo změnit.',0,$e);}
}

/** @return array{id:int,created:bool} */
function clubCalendarCreateLegacyRace(PDO$pdo,int$eventId,int$actorId,string$category):array
{
    if(!in_array($category,['silnice','draha','mtb'],true))throw new InvalidArgumentException('Vyberte disciplínu závodu.');$pdo->beginTransaction();try{$event=clubEventLock($pdo,$eventId);if(!$event||$event['activity_kind']!=='race')throw new ClubCalendarException('Výsledkový záznam lze založit pouze pro závod.');if(!empty($event['legacy_race_id'])){$pdo->commit();return['id'=>(int)$event['legacy_race_id'],'created'=>false];}$s=$pdo->prepare("SELECT MIN(starts_at) FROM club_event_sessions WHERE event_id=? AND status='scheduled'");$s->execute([$eventId]);$date=substr((string)$s->fetchColumn(),0,10);$url=$pdo->prepare("SELECT url FROM club_event_links WHERE event_id=? AND link_type='results' ORDER BY sort_order,id LIMIT 1");$url->execute([$eventId]);$pdo->prepare('INSERT INTO zavody(datum,kategorie,popis,poznamka,url_vysledky,trener_id) VALUES(?,?,?,?,?,?)')->execute([$date,$category,(string)$event['name'],(string)($event['internal_note']??''),(string)($url->fetchColumn()?:''),$actorId]);$raceId=(int)$pdo->lastInsertId();$pdo->prepare("UPDATE club_events SET legacy_race_id=?,planning_status='completed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$raceId,$eventId]);clubCalendarAudit($pdo,$eventId,$actorId,'calendar_create_race_record','Založen výsledkový záznam závodu.',['legacy_race_id'=>$raceId,'category'=>$category]);$pdo->commit();return['id'=>$raceId,'created'=>true];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Výsledkový záznam se nepodařilo založit.',0,$e);}
}

/** @return array{changed:bool,status:string} */
function clubCalendarCancelParticipant(PDO $pdo,int $participantId,int $actorId,string $reason):array
{
    $reason=clubCalendarText($reason,1000,'Důvod zrušení',true);if(min($participantId,$actorId)<1)throw new InvalidArgumentException('Zrušení vyžaduje účastníka a trenéra.');
    $pdo->beginTransaction();try{$s=$pdo->prepare('SELECT p.*,e.name event_name FROM club_event_planned_participants p JOIN club_events e ON e.id=p.event_id WHERE p.id=?');$s->execute([$participantId]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new ClubCalendarException('Účastník nebyl nalezen.');if($row['status']==='cancelled'){$pdo->commit();return['changed'=>false,'status'=>'cancelled'];}clubEventLock($pdo,(int)$row['event_id']);
        $pdo->prepare("UPDATE club_event_planned_participants SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$participantId]);
        if((int)($row['registration_id']??0)>0){$r=$pdo->prepare('SELECT status FROM club_event_registrations WHERE id=?');$r->execute([(int)$row['registration_id']]);$from=(string)$r->fetchColumn();if($from!==''&&$from!=='cancelled'){$pdo->prepare("UPDATE club_event_registrations SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,cancellation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$reason,(int)$row['registration_id']]);clubEventRegistrationAudit($pdo,(int)$row['registration_id'],'trainer',$actorId,'calendar_cancel',$from,'cancelled',$reason);}}
        if((int)($row['charge_id']??0)>0){$c=$pdo->prepare('SELECT * FROM club_member_charges WHERE id=?');$c->execute([(int)$row['charge_id']]);$charge=$c->fetch(PDO::FETCH_ASSOC);if($charge&&$charge['status']==='pending'){$pdo->prepare("UPDATE club_member_charges SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$row['charge_id']]);$pdo->prepare("UPDATE payments SET status='cancelled',confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE payable_type='member_charge' AND payable_id=? AND status='pending'")->execute([$reason,(int)$row['charge_id']]);$to='cancelled';}elseif($charge&&$charge['status']==='paid'){$pdo->prepare("UPDATE club_member_charges SET status='refund_required',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$row['charge_id']]);$pdo->prepare("UPDATE payments SET status='refund_required',confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE payable_type='member_charge' AND payable_id=?")->execute([$reason,(int)$row['charge_id']]);$to='refund_required';}else{$to=(string)($charge['status']??'cancelled');}if($charge){$pdo->prepare('INSERT INTO club_member_charge_events(charge_id,action,from_status,to_status,actor_type,actor_id,reason,snapshot_json) VALUES(?,?,?,?,\'trainer\',?,?,?)')->execute([(int)$row['charge_id'],'event_participation_cancel',(string)$charge['status'],$to,$actorId,$reason,json_encode(['event_id'=>(int)$row['event_id'],'participant_id'=>$participantId],JSON_THROW_ON_ERROR)]);}}
        clubCalendarAudit($pdo,(int)$row['event_id'],$actorId,'calendar_cancel_participant',$reason,['participant_id'=>$participantId,'charge_id'=>$row['charge_id']]);$pdo->commit();return['changed'=>true,'status'=>'cancelled'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Účast se nepodařilo zrušit bez částečného zápisu.',0,$e);}
}

/** @return array{changed:bool} */
function clubCalendarRemoveItem(PDO$pdo,int$eventId,int$actorId,string$type,int$id):array
{
    $map=['person'=>['club_event_people','id'],'link'=>['club_event_links','id'],'vehicle'=>['club_event_vehicle_reservations','id']];if(!isset($map[$type])||min($eventId,$actorId,$id)<1)throw new InvalidArgumentException('Neplatná položka akce.');
    $pdo->beginTransaction();try{if(!clubEventLock($pdo,$eventId))throw new ClubCalendarException('Akce nebyla nalezena.');[$table,$column]=$map[$type];$s=$pdo->prepare("SELECT * FROM $table WHERE $column=? AND event_id=?");$s->execute([$id,$eventId]);$before=$s->fetch(PDO::FETCH_ASSOC);if(!$before){$pdo->commit();return['changed'=>false];}if($type==='vehicle')$pdo->prepare("UPDATE $table SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);else$pdo->prepare("DELETE FROM $table WHERE id=? AND event_id=?")->execute([$id,$eventId]);clubCalendarAudit($pdo,$eventId,$actorId,'calendar_remove_'.$type,'Položka odebrána z akce.',['before'=>$before]);$pdo->commit();return['changed'=>true];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof ClubCalendarException)throw$e;throw new ClubCalendarException('Položku se nepodařilo odebrat.',0,$e);}
}
