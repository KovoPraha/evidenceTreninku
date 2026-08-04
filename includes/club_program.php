<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';

final class ClubProgramException extends RuntimeException {}

function clubProgramLifecycleAvailable(PDO $pdo): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
        $statement=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('club_program_offers','club_program_enrollments')");$statement->execute();return(int)$statement->fetchColumn()===2;
    }
    $statement=$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('club_program_offers','club_program_enrollments')");return(int)$statement->fetchColumn()===2;
}

function clubProgramCode(string $value): string
{
    $value = strtoupper(trim($value));
    if (strlen($value) > 64 || preg_match('/^[A-Z0-9][A-Z0-9_-]{2,}$/D', $value) !== 1) {
        throw new InvalidArgumentException('Kód musí mít alespoň 3 znaky A–Z, 0–9, pomlčku nebo podtržítko.');
    }
    return $value;
}

function clubProgramText(string $value, int $max, string $label, bool $required = true): string
{
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max || preg_match('/[<>]/u', $value) === 1) {
        throw new InvalidArgumentException($label . ' nemá platný prostý text.');
    }
    return $value;
}

function clubProgramDate(string $value): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Datum musí mít formát RRRR-MM-DD.');
    return $value;
}

/** @return array<string,mixed> */
function clubProgramCreate(PDO $pdo, int $actorId, string $code, string $name, string $description = ''): array
{
    if ($actorId < 1) throw new InvalidArgumentException('Program vyžaduje správce.');
    $code = clubProgramCode($code);
    $name = clubProgramText($name, 160, 'Název programu');
    $description = clubProgramText($description, 4000, 'Popis programu', false);
    $statement = $pdo->prepare('SELECT * FROM club_programs WHERE code=?');
    $statement->execute([$code]);
    $existing = $statement->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ((string)$existing['name'] !== $name || (string)($existing['description'] ?? '') !== $description) throw new ClubProgramException('Kód už označuje jiný stabilní program.');
        return $existing;
    }
    $pdo->prepare("INSERT INTO club_programs(code,name,description,status,created_by_trainer_id) VALUES (?,?,?,'active',?)")
        ->execute([$code,$name,$description !== '' ? $description : null,$actorId]);
    return ['id'=>(int)$pdo->lastInsertId(),'code'=>$code,'name'=>$name,'description'=>$description,'status'=>'active'];
}

/** @return array<string,mixed> */
function clubProgramCreateOffer(PDO $pdo, int $actorId, int $programId, int $seasonId, int $teamId, int $productId, int $variantId, string $code, string $name, string $startsOn, string $endsOn, ?string $salesOpenAt, ?string $salesCloseAt, ?int $capacity, string $status): array
{
    if (min($actorId,$programId,$seasonId,$teamId,$productId,$variantId) < 1) throw new InvalidArgumentException('Nabídka vyžaduje program, období, soupisku, produkt, variantu a správce.');
    $code = clubProgramCode($code);$name = clubProgramText($name, 180, 'Název nabídky');
    $startsOn = clubProgramDate($startsOn);$endsOn = clubProgramDate($endsOn);
    if ($startsOn > $endsOn) throw new InvalidArgumentException('Konec nabídky musí být nejdříve v den začátku.');
    if ($capacity !== null && ($capacity < 1 || $capacity > 100000)) throw new InvalidArgumentException('Kapacita musí být prázdná nebo mezi 1 a 100000.');
    if (!in_array($status, ['draft','active','closed'], true)) throw new InvalidArgumentException('Stav nabídky není podporován.');
    foreach (['salesOpenAt'=>&$salesOpenAt,'salesCloseAt'=>&$salesCloseAt] as &$dateTime) {
        if ($dateTime !== null && trim($dateTime) !== '') {
            $parsed = date_create_immutable(trim($dateTime));
            if (!$parsed) throw new InvalidArgumentException('Prodejní termín není platné datum a čas.');
            $dateTime = $parsed->format('Y-m-d H:i:s');
        } else $dateTime = null;
    }
    unset($dateTime);
    if ($salesOpenAt !== null && $salesCloseAt !== null && $salesOpenAt >= $salesCloseAt) throw new InvalidArgumentException('Konec prodeje musí být po jeho otevření.');
    $program = $pdo->prepare("SELECT id FROM club_programs WHERE id=? AND status='active'");$program->execute([$programId]);
    if (!$program->fetchColumn()) throw new ClubProgramException('Aktivní program nebyl nalezen.');
    $team = $pdo->prepare('SELECT t.id,t.season_id,s.starts_on,s.ends_on FROM club_teams t JOIN club_seasons s ON s.id=t.season_id WHERE t.id=? AND t.season_id=?');
    $team->execute([$teamId,$seasonId]);$team = $team->fetch(PDO::FETCH_ASSOC);
    if (!$team) throw new ClubProgramException('Soupiska nepatří do vybraného období.');
    if ($startsOn < (string)$team['starts_on'] || $endsOn > (string)$team['ends_on']) throw new InvalidArgumentException('Platnost nabídky musí ležet uvnitř sezony soupisky.');
    $variant = $pdo->prepare('SELECT v.id,v.product_id FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.id=? AND p.id=?');
    $variant->execute([$variantId,$productId]);
    if (!$variant->fetch()) throw new ClubProgramException('Varianta nepatří k vybranému produktu.');
    $existing = $pdo->prepare('SELECT * FROM club_program_offers WHERE code=? OR variant_id=? ORDER BY id LIMIT 1');
    $existing->execute([$code,$variantId]);$existing = $existing->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ((string)$existing['code'] !== $code || (int)$existing['variant_id'] !== $variantId) throw new ClubProgramException('Kód nebo varianta už patří jiné nabídce.');
        return $existing;
    }
    $pdo->prepare('INSERT INTO club_program_offers(program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,sales_open_at,sales_close_at,capacity,status,created_by_trainer_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$programId,$seasonId,$teamId,$productId,$variantId,$code,$name,$startsOn,$endsOn,$salesOpenAt,$salesCloseAt,$capacity,$status,$actorId]);
    return ['id'=>(int)$pdo->lastInsertId(),'program_id'=>$programId,'team_id'=>$teamId,'variant_id'=>$variantId,'code'=>$code,'name'=>$name,'status'=>$status];
}

/** @return list<array<string,mixed>> */
function clubProgramAdminOffers(PDO $pdo): array
{
    return $pdo->query('SELECT o.*,p.code AS program_code,p.name AS program_name,s.name AS season_name,t.name AS team_name,v.sku,sp.name AS product_name,(SELECT COUNT(*) FROM club_program_enrollments e WHERE e.offer_id=o.id AND e.status=\'active\') AS enrollment_count FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id JOIN club_seasons s ON s.id=o.season_id JOIN club_teams t ON t.id=o.team_id JOIN shop_variants v ON v.id=o.variant_id JOIN shop_products sp ON sp.id=o.product_id ORDER BY o.starts_on DESC,o.id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed>|false */
function clubProgramOfferForVariant(PDO $pdo, int $variantId): array|false
{
    $statement = $pdo->prepare("SELECT o.*,p.name AS program_name FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE o.variant_id=? AND o.status='active' AND p.status='active'");
    $statement->execute([$variantId]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function clubProgramProductHasActiveOffer(PDO $pdo, int $productId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM club_program_offers o JOIN club_programs p ON p.id = o.program_id WHERE o.product_id = ? AND o.status = \'active\' AND p.status = \'active\' LIMIT 1');
    $stmt->execute([$productId]);
    return $stmt->fetchColumn() !== false;
}

/** @param array<string,mixed> $offer */
function clubProgramOfferIsOnSale(array $offer, ?DateTimeImmutable $now = null): bool
{
    $now ??= new DateTimeImmutable('now');
    if (($offer['status'] ?? null) !== 'active') return false;
    if (!empty($offer['sales_open_at']) && $now < new DateTimeImmutable((string)$offer['sales_open_at'])) return false;
    if (!empty($offer['sales_close_at']) && $now > new DateTimeImmutable((string)$offer['sales_close_at'])) return false;
    return true;
}

/** @return list<array<string,mixed>> */
function clubProgramPendingOrderItems(PDO $pdo, int $accountId): array
{
    $statement = $pdo->prepare(
        'SELECT DISTINCT oi.id AS order_item_id,oi.beneficiary_sportovec_id,oi.product_name_snapshot,oi.line_amount_minor,oi.currency,o.public_code,o.payment_status,o.status AS order_status,cp.name AS program_name,co.name AS offer_name,co.starts_on,co.ends_on,s.jmeno,s.prijmeni,e.id AS enrollment_id '
        . 'FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id JOIN club_program_offers co ON co.variant_id=oi.variant_id JOIN club_programs cp ON cp.id=co.program_id JOIN sportovci s ON s.id=oi.beneficiary_sportovec_id JOIN account_person_roles r ON r.account_id=? AND r.sportovec_id=s.id AND r.relation_role IN (\'self\',\'guardian\') AND r.status=\'approved\' AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) LEFT JOIN club_program_enrollments e ON e.source_order_item_id=oi.id WHERE o.account_id=? ORDER BY o.created_at DESC,oi.id DESC'
    );
    $statement->execute([$accountId,$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function clubProgramEnrollmentsForAccount(PDO $pdo, int $accountId): array
{
    $statement = $pdo->prepare('SELECT DISTINCT e.*,cp.name AS program_name,co.name AS offer_name,co.code AS offer_code,t.name AS team_name,s.jmeno,s.prijmeni,o.public_code FROM club_program_enrollments e JOIN club_program_offers co ON co.id=e.offer_id JOIN club_programs cp ON cp.id=co.program_id JOIN club_teams t ON t.id=co.team_id JOIN sportovci s ON s.id=e.sportovec_id JOIN shop_order_items oi ON oi.id=e.source_order_item_id JOIN shop_orders o ON o.id=oi.order_id JOIN account_person_roles r ON r.account_id=? AND r.sportovec_id=e.sportovec_id AND r.relation_role IN (\'self\',\'guardian\') AND r.status=\'approved\' AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) ORDER BY e.valid_from DESC,e.id DESC');
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{id:int,created:bool,roster_created:bool} */
function clubProgramActivateOrderItem(PDO $pdo, int $accountId, int $orderItemId): array
{
    if ($accountId < 1 || $orderItemId < 1) throw new InvalidArgumentException('Aktivace vyžaduje účet a objednávkovou položku.');
    $pdo->beginTransaction();
    try {
        $result = clubProgramActivateOrderItemInTransaction($pdo,$accountId,$orderItemId,'account',$accountId);
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubProgramException || $exception instanceof ShopCheckoutException) throw $exception;
        throw new ClubProgramException('Účast se nepodařilo aktivovat bez částečného zápisu.',0,$exception);
    }
}

/** @return array{id:int,created:bool,roster_created:bool} */
function clubProgramActivateOrderItemInTransaction(PDO $pdo, int $accountId, int $orderItemId, string $actorType, int $actorId): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Aktivace programu vyžaduje otevřenou transakci.');
    if ($accountId < 1 || $orderItemId < 1 || $actorId < 1 || !in_array($actorType,['account','trainer'],true)) throw new InvalidArgumentException('Aktivace programu nemá platného vlastníka nebo auditora.');
    $itemSql='SELECT oi.id AS order_item_id,oi.beneficiary_sportovec_id,oi.quantity,oi.line_amount_minor,oi.product_id,oi.variant_id,o.id AS order_id,o.account_id,o.status AS order_status,o.payment_status FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id JOIN verejni_uzivatele vu ON vu.id=o.account_id AND vu.aktivni=1 AND vu.email_overeno=1 WHERE oi.id=? AND o.account_id=?';
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$itemSql.=' FOR UPDATE';
    $itemStatement=$pdo->prepare($itemSql);$itemStatement->execute([$orderItemId,$accountId]);$item=$itemStatement->fetch(PDO::FETCH_ASSOC);
    if(!$item)throw new ClubProgramException('Objednávková položka programu nebyla nalezena.');
    $offerLookup=$pdo->prepare('SELECT team_id FROM club_program_offers WHERE variant_id=? AND product_id=?');$offerLookup->execute([(int)$item['variant_id'],(int)$item['product_id']]);$teamId=$offerLookup->fetchColumn();
    if($teamId===false)throw new ClubProgramException('Objednávková položka programu nebyla nalezena.');
    $teamLockSql='SELECT id FROM club_teams WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$teamLockSql.=' FOR UPDATE';$teamLock=$pdo->prepare($teamLockSql);$teamLock->execute([(int)$teamId]);if($teamLock->fetchColumn()===false)throw new ClubProgramException('Cílová soupiska programu nebyla nalezena.');
    $offerSql='SELECT id AS offer_id,team_id,starts_on,ends_on,capacity,status AS offer_status,created_by_trainer_id FROM club_program_offers WHERE variant_id=? AND product_id=? AND team_id=?';
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$offerSql.=' FOR UPDATE';
    $offerStatement=$pdo->prepare($offerSql);$offerStatement->execute([(int)$item['variant_id'],(int)$item['product_id'],(int)$teamId]);$offer=$offerStatement->fetch(PDO::FETCH_ASSOC);
    if(!$offer)throw new ClubProgramException('Nabídka programu se během aktivace změnila. Zkuste operaci znovu.');
    $item=array_merge($item,$offer);
    if ($item['beneficiary_sportovec_id'] === null || (int)$item['quantity'] !== 1) throw new ClubProgramException('Položka programu musí mít právě jednoho příjemce a množství 1.');
    $sportovecId=(int)$item['beneficiary_sportovec_id'];
    $existing=$pdo->prepare('SELECT id FROM club_program_enrollments WHERE source_order_item_id=?');$existing->execute([$orderItemId]);$existingId=$existing->fetchColumn();
    if ($existingId) return ['id'=>(int)$existingId,'created'=>false,'roster_created'=>false];
    $duplicate=$pdo->prepare("SELECT id FROM club_program_enrollments WHERE offer_id=? AND sportovec_id=? AND status='active'");$duplicate->execute([(int)$item['offer_id'],$sportovecId]);
    if($duplicate->fetchColumn()!==false)throw new ClubProgramException('Tento sportovec už má stejné období aktivované jinou objednávkou. Duplicitní platbu nelze potvrdit.');
    shopBeneficiaryAssertAccessible($pdo,$accountId,$sportovecId,true);
    if ((string)$item['order_status']==='cancelled' || (string)$item['offer_status']!=='active' || (int)$item['line_amount_minor']<0) throw new ClubProgramException('Objednávka nebo nabídka už není aktivovatelná.');
    if ((int)$item['line_amount_minor'] !== 0 && ((string)$item['payment_status'] !== 'paid' || !in_array((string)$item['order_status'],['processing','ready','completed'],true))) throw new ClubProgramException('Placenou účast lze aktivovat až po potvrzení platby.');
    if ($item['capacity']!==null) {
        $capacity=$pdo->prepare("SELECT COUNT(*) FROM club_program_enrollments WHERE offer_id=? AND status='active'");$capacity->execute([(int)$item['offer_id']]);
        if ((int)$capacity->fetchColumn()>=(int)$item['capacity']) throw new ClubProgramException('Kapacita období je vyčerpána.');
    }
    $pdo->prepare("INSERT INTO club_program_enrollments(offer_id,sportovec_id,account_id,source_order_item_id,status,active_token,valid_from,valid_to,activated_at) VALUES (?,?,?,?,'active','active',?,?,CURRENT_TIMESTAMP)")
        ->execute([(int)$item['offer_id'],$sportovecId,$accountId,$orderItemId,(string)$item['starts_on'],(string)$item['ends_on']]);
    $enrollmentId=(int)$pdo->lastInsertId();
    $memberSql='SELECT id,status,source FROM club_roster_members WHERE team_id=? AND sportovec_id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$memberSql.=' FOR UPDATE';
    $member=$pdo->prepare($memberSql);$member->execute([(int)$item['team_id'],$sportovecId]);$member=$member->fetch(PDO::FETCH_ASSOC);$rosterCreated=false;
    if (!$member) {
        $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES (?,?,'active','shop',?,NULL,?)")
            ->execute([(int)$item['team_id'],$sportovecId,(string)$item['starts_on'],(int)$item['created_by_trainer_id']]);
        $memberId=(int)$pdo->lastInsertId();$rosterCreated=true;
        $after=json_encode(['status'=>'active','source'=>'shop','sportovec_id'=>$sportovecId,'program_enrollment_id'=>$enrollmentId],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO club_roster_events(team_id,roster_member_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,'add',NULL,?,'Automatické zařazení po aktivaci kroužku.')")
            ->execute([(int)$item['team_id'],$memberId,(int)$item['created_by_trainer_id'],$after]);
    } elseif ((string)$member['status']!=='active') {
        if ((string)$member['source']!=='shop') throw new ClubProgramException('Dřívější ruční členství v cílové soupisce je ukončené; obnovení musí posoudit správce.');
        $before=json_encode($member,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $pdo->prepare("UPDATE club_roster_members SET status='active',valid_from=?,valid_to=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status<>'active'")
            ->execute([(string)$item['starts_on'],(int)$member['id']]);
        $after=json_encode(['status'=>'active','valid_from'=>(string)$item['starts_on'],'valid_to'=>null,'source'=>'shop','program_enrollment_id'=>$enrollmentId],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO club_roster_events(team_id,roster_member_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,'restore',?,?,?)")
            ->execute([(int)$item['team_id'],(int)$member['id'],(int)$item['created_by_trainer_id'],$before,$after,'Automatické obnovení po nové aktivaci kroužku.']);
    }
    $payload=json_encode(['order_item_id'=>$orderItemId,'sportovec_id'=>$sportovecId,'team_id'=>(int)$item['team_id'],'roster_created'=>$rosterCreated],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO club_program_enrollment_events(offer_id,enrollment_id,actor_type,actor_id,action,payload_json) VALUES (?,?,?,?,\'activate\',?)')
        ->execute([(int)$item['offer_id'],$enrollmentId,$actorType,$actorId,$payload]);
    return ['id'=>$enrollmentId,'created'=>true,'roster_created'=>$rosterCreated];
}

/** @return array{program_items:int,created:int} */
function clubProgramActivatePaidOrderInTransaction(PDO $pdo, int $orderId, int $actorTrainerId): array
{
    if (!$pdo->inTransaction() || $orderId<1 || $actorTrainerId<1) throw new InvalidArgumentException('Synchronizace zaplacené objednávky vyžaduje transakci, objednávku a administrátora.');
    $order=$pdo->prepare("SELECT id,account_id,status,payment_status FROM shop_orders WHERE id=? AND payment_status='paid' AND status IN ('processing','ready','completed')");$order->execute([$orderId]);$order=$order->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new ClubProgramException('Objednávka není ve stavu potvrzené platby.');
    $items=$pdo->prepare('SELECT oi.id FROM shop_order_items oi JOIN club_program_offers co ON co.variant_id=oi.variant_id AND co.product_id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');$items->execute([$orderId]);$items=$items->fetchAll(PDO::FETCH_COLUMN);
    $created=0;foreach($items as $itemId){$result=clubProgramActivateOrderItemInTransaction($pdo,(int)$order['account_id'],(int)$itemId,'trainer',$actorTrainerId);if($result['created'])$created++;}
    return ['program_items'=>count($items),'created'=>$created];
}

/** @return array{cancelled:int,rosters_ended:int} */
function clubProgramCancelOrderInTransaction(PDO $pdo, int $orderId, int $actorTrainerId, string $reason): array
{
    if (!$pdo->inTransaction() || $orderId<1 || $actorTrainerId<1 || trim($reason)==='') throw new InvalidArgumentException('Ukončení účasti vyžaduje transakci, objednávku, administrátora a důvod.');
    $sql="SELECT e.id,e.offer_id,e.sportovec_id,co.team_id FROM club_program_enrollments e JOIN shop_order_items oi ON oi.id=e.source_order_item_id JOIN club_program_offers co ON co.id=e.offer_id WHERE oi.order_id=? AND e.status='active' ORDER BY e.id";
    $statement=$pdo->prepare($sql);$statement->execute([$orderId]);$enrollments=$statement->fetchAll(PDO::FETCH_ASSOC);$pairs=[];
    foreach($enrollments as$enrollment)$pairs[(int)$enrollment['team_id'].':'.(int)$enrollment['sportovec_id']]=[(int)$enrollment['team_id'],(int)$enrollment['sportovec_id']];
    ksort($pairs,SORT_STRING);$lockedMembers=[];$teamIds=[];foreach($pairs as[$teamId])$teamIds[$teamId]=$teamId;sort($teamIds,SORT_NUMERIC);
    foreach($teamIds as$teamId){$teamSql='SELECT id FROM club_teams WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$teamSql.=' FOR UPDATE';$team=$pdo->prepare($teamSql);$team->execute([$teamId]);if($team->fetchColumn()===false)throw new ClubProgramException('Cílová soupiska ukončované účasti nebyla nalezena.');}
    foreach($pairs as$key=>[$teamId,$sportovecId]){
        $memberSql='SELECT * FROM club_roster_members WHERE team_id=? AND sportovec_id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$memberSql.=' FOR UPDATE';
        $member=$pdo->prepare($memberSql);$member->execute([$teamId,$sportovecId]);$lockedMembers[$key]=$member->fetch(PDO::FETCH_ASSOC);
    }
    foreach($enrollments as$enrollment){
        $pdo->prepare("UPDATE club_program_enrollments SET status='cancelled',active_token=NULL,ended_at=CURRENT_TIMESTAMP,ended_reason=?,ended_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='active'")->execute([trim($reason),$actorTrainerId,(int)$enrollment['id']]);
        $payload=json_encode(['order_id'=>$orderId,'sportovec_id'=>(int)$enrollment['sportovec_id'],'team_id'=>(int)$enrollment['team_id'],'reason'=>trim($reason)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO club_program_enrollment_events(offer_id,enrollment_id,actor_type,actor_id,action,payload_json) VALUES (?,?,'trainer',?,'cancel',?)")->execute([(int)$enrollment['offer_id'],(int)$enrollment['id'],$actorTrainerId,$payload]);
    }
    $rostersEnded=0;$today=(new DateTimeImmutable('today'))->format('Y-m-d');
    foreach($pairs as$key=>[$teamId,$sportovecId]){
        $otherSql="SELECT e.id FROM club_program_enrollments e JOIN club_program_offers co ON co.id=e.offer_id WHERE e.sportovec_id=? AND co.team_id=? AND e.status='active' LIMIT 1";if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$otherSql.=' FOR UPDATE';
        $other=$pdo->prepare($otherSql);$other->execute([$sportovecId,$teamId]);if($other->fetchColumn()!==false)continue;
        $member=$lockedMembers[$key];if(!$member||(string)$member['status']!=='active'||(string)$member['source']!=='shop')continue;
        $endDate=max($today,(string)$member['valid_from']);$pdo->prepare("UPDATE club_roster_members SET status='removed',valid_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='active'")->execute([$endDate,(int)$member['id']]);
        $before=json_encode($member,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$after=json_encode(['status'=>'removed','valid_to'=>$endDate,'source'=>'shop'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO club_roster_events(team_id,roster_member_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,'remove',?,?,?)")->execute([$teamId,(int)$member['id'],$actorTrainerId,$before,$after,'Automatické ukončení po stornu objednávky: '.trim($reason)]);$rostersEnded++;
    }
    return ['cancelled'=>count($enrollments),'rosters_ended'=>$rostersEnded];
}

function clubProgramAssertOrderHasNoActiveEnrollments(PDO $pdo, int $orderId): void
{
    $statement=$pdo->prepare("SELECT COUNT(*) FROM club_program_enrollments e JOIN shop_order_items oi ON oi.id=e.source_order_item_id WHERE oi.order_id=? AND e.status='active'");$statement->execute([$orderId]);
    if((int)$statement->fetchColumn()>0)throw new ClubProgramException('Vratku nelze potvrdit, protože objednávka má stále aktivní programovou účast.');
}
