<?php
declare(strict_types=1);

final class ClubEventException extends RuntimeException
{
}

/** @return list<string> */
function clubEventTypes(): array
{
    return ['club_event', 'camp'];
}

/** @return list<array<string,mixed>> */
function clubEventList(PDO $pdo): array
{
    return $pdo->query(
        'SELECT e.*, COUNT(DISTINCT s.id) AS session_count, COUNT(DISTINCT l.id) AS product_count '
        . 'FROM club_events e LEFT JOIN club_event_sessions s ON s.event_id=e.id '
        . 'LEFT JOIN shop_product_event_links l ON l.event_id=e.id '
        . 'GROUP BY e.id ORDER BY e.created_at DESC, e.id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{event:array<string,mixed>,sessions:list<array<string,mixed>>,products:list<array<string,mixed>>,events:list<array<string,mixed>>}|null */
function clubEventDetail(PDO $pdo, int $eventId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM club_events WHERE id=?');
    $statement->execute([$eventId]);
    $event = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        return null;
    }
    $sessions = $pdo->prepare('SELECT * FROM club_event_sessions WHERE event_id=? ORDER BY starts_at, id');
    $sessions->execute([$eventId]);
    $products = $pdo->prepare(
        'SELECT l.*, p.name AS product_name, p.offer_type, p.catalog_status '
        . 'FROM shop_product_event_links l JOIN shop_products p ON p.id=l.product_id WHERE l.event_id=? ORDER BY l.id'
    );
    $products->execute([$eventId]);
    $events = $pdo->prepare(
        'SELECT a.*, t.jmeno AS actor_name FROM club_event_admin_events a '
        . 'LEFT JOIN treneri t ON t.id=a.actor_trainer_id WHERE a.event_id=? ORDER BY a.created_at DESC, a.id DESC'
    );
    $events->execute([$eventId]);
    return [
        'event' => $event,
        'sessions' => $sessions->fetchAll(PDO::FETCH_ASSOC),
        'products' => $products->fetchAll(PDO::FETCH_ASSOC),
        'events' => $events->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/** @return array{id:int,status:string} */
function clubEventCreateDraft(PDO $pdo, int $actorId, array $input): array
{
    $value = clubEventValidateDraft($actorId, $input);
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO club_events (code,event_type,name,description_plain,audience_label,min_age,max_age,'
            . 'capacity,pricing_policy,currency,registration_starts_at,registration_ends_at,status,created_by_trainer_id) '
            . "VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'draft',?)"
        );
        $insert->execute([
            $value['code'], $value['event_type'], $value['name'], $value['description_plain'],
            $value['audience_label'], $value['min_age'], $value['max_age'], $value['capacity'],
            $value['pricing_policy'], $value['currency'], $value['registration_starts_at'],
            $value['registration_ends_at'], $actorId,
        ]);
        $eventId = (int)$pdo->lastInsertId();
        clubEventAudit($pdo, $eventId, $actorId, 'create', 'event', $eventId, 'Založení pracovní akce.', $value);
        $pdo->commit();
        return ['id' => $eventId, 'status' => 'draft'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubEventException) throw $exception;
        throw new ClubEventException('Akci se nepodařilo založit bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{id:int,event_id:int} */
function clubEventAddSession(PDO $pdo, int $eventId, int $actorId, string $startsAt, string $endsAt, string $location, ?int $capacityOverride): array
{
    [$startsAt, $endsAt] = [clubEventDateTime($startsAt), clubEventDateTime($endsAt)];
    $location = trim($location);
    if ($eventId < 1 || $actorId < 1 || $location === '' || mb_strlen($location, 'UTF-8') > 255) {
        throw new InvalidArgumentException('Termín vyžaduje akci, místo a oprávněného administrátora.');
    }
    if ($startsAt >= $endsAt || ($capacityOverride !== null && ($capacityOverride < 1 || $capacityOverride > 10000))) {
        throw new InvalidArgumentException('Konec termínu musí být po začátku a kapacita musí být platná.');
    }
    $pdo->beginTransaction();
    try {
        $event = clubEventLock($pdo, $eventId);
        if (!$event || $event['status'] !== 'draft') throw new ClubEventException('Termín lze přidat pouze k pracovní akci.');
        $overlap = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_sessions WHERE event_id=? AND status='scheduled' AND starts_at<? AND ends_at>?"
        );
        $overlap->execute([$eventId, $endsAt, $startsAt]);
        if ((int)$overlap->fetchColumn() > 0) throw new ClubEventException('Termín se překrývá s jiným termínem této akce.');
        $insert = $pdo->prepare('INSERT INTO club_event_sessions (event_id,starts_at,ends_at,location,capacity_override) VALUES (?,?,?,?,?)');
        $insert->execute([$eventId, $startsAt, $endsAt, $location, $capacityOverride]);
        $id = (int)$pdo->lastInsertId();
        clubEventAudit($pdo, $eventId, $actorId, 'add_session', 'session', $id, 'Přidán termín.', compact('startsAt','endsAt','location','capacityOverride'));
        $pdo->commit();
        return ['id' => $id, 'event_id' => $eventId];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubEventException) throw $exception;
        throw new ClubEventException('Termín se nepodařilo uložit bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{id:int,event_id:int,product_id:int,created:bool} */
function clubEventLinkProduct(PDO $pdo, int $eventId, int $productId, int $actorId, string $note): array
{
    $note = trim($note);
    if ($eventId < 1 || $productId < 1 || $actorId < 1 || $note === '' || mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Propojení vyžaduje akci, produkt, administrátora a důvod.');
    }
    $pdo->beginTransaction();
    try {
        $event = clubEventLock($pdo, $eventId);
        if (!$event || $event['status'] !== 'draft') throw new ClubEventException('Propojit lze pouze pracovní akci.');
        $productSql = 'SELECT * FROM shop_products WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $productSql .= ' FOR UPDATE';
        $productStatement = $pdo->prepare($productSql);
        $productStatement->execute([$productId]);
        $product = $productStatement->fetch(PDO::FETCH_ASSOC);
        if (!$product || $product['offer_type'] !== $event['event_type']) {
            throw new ClubEventException('Typ produktu musí přesně odpovídat typu klubové akce.');
        }
        $sessionCount = $pdo->prepare("SELECT COUNT(*) FROM club_event_sessions WHERE event_id=? AND status='scheduled'");
        $sessionCount->execute([$eventId]);
        if ((int)$sessionCount->fetchColumn() < 1) throw new ClubEventException('Před propojením přidejte alespoň jeden termín.');
        $variants = $pdo->prepare('SELECT price_mode,amount_minor,currency,visible FROM shop_variants WHERE product_id=?');
        $variants->execute([$productId]);
        $eligible = array_values(array_filter($variants->fetchAll(PDO::FETCH_ASSOC), static fn(array $v): bool => $v['visible'] === null || (int)$v['visible'] === 1));
        if ($eligible === []) throw new ClubEventException('Produkt nemá použitelnou variantu.');
        if ($event['pricing_policy'] === 'free') {
            foreach ($eligible as $variant) {
                if ($variant['price_mode'] !== 'free' && (int)($variant['amount_minor'] ?? -1) !== 0) {
                    throw new ClubEventException('Bezplatnou akci lze propojit pouze s nulovou variantou produktu.');
                }
            }
        } else {
            foreach ($eligible as $variant) {
                if (!in_array($variant['price_mode'], ['fixed','free'], true)) {
                    throw new ClubEventException('Produkt obsahuje nepodporovaný cenový režim.');
                }
                if ($variant['price_mode'] === 'fixed'
                    && strtoupper((string)$variant['currency']) !== (string)$event['currency']
                ) {
                    throw new ClubEventException('Měna produktu musí odpovídat měně klubové akce.');
                }
            }
        }
        $existing = $pdo->prepare('SELECT * FROM shop_product_event_links WHERE product_id=?');
        $existing->execute([$productId]);
        $link = $existing->fetch(PDO::FETCH_ASSOC);
        if ($link) {
            if ((int)$link['event_id'] === $eventId) {
                $pdo->commit();
                return ['id'=>(int)$link['id'],'event_id'=>$eventId,'product_id'=>$productId,'created'=>false];
            }
            throw new ClubEventException('Produkt už je propojen s jinou akcí.');
        }
        $insert = $pdo->prepare('INSERT INTO shop_product_event_links (product_id,event_id,actor_trainer_id,decision_note) VALUES (?,?,?,?)');
        $insert->execute([$productId,$eventId,$actorId,$note]);
        $id=(int)$pdo->lastInsertId();
        clubEventAudit($pdo,$eventId,$actorId,'link_product','product',$productId,$note,['product_name'=>$product['name']]);
        $pdo->commit();
        return ['id'=>$id,'event_id'=>$eventId,'product_id'=>$productId,'created'=>true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubEventException) throw $exception;
        throw new ClubEventException('Produkt se nepodařilo propojit bez částečného zápisu.',0,$exception);
    }
}

/** @return array{id:int,changed:bool} */
function clubEventUnlinkProduct(PDO $pdo, int $eventId, int $linkId, int $actorId, string $note): array
{
    $note=trim($note);
    if ($eventId<1||$linkId<1||$actorId<1||$note===''||mb_strlen($note,'UTF-8')>1000) throw new InvalidArgumentException('Odpojení vyžaduje vazbu, administrátora a důvod.');
    $pdo->beginTransaction();
    try {
        $event=clubEventLock($pdo,$eventId);
        if (!$event||$event['status']!=='draft') throw new ClubEventException('Produkt lze odpojit pouze od pracovní akce.');
        $statement=$pdo->prepare('SELECT * FROM shop_product_event_links WHERE id=? AND event_id=?');$statement->execute([$linkId,$eventId]);$link=$statement->fetch(PDO::FETCH_ASSOC);
        if (!$link) throw new ClubEventException('Vazba produktu nebyla nalezena.');
        $pdo->prepare('DELETE FROM shop_product_event_links WHERE id=?')->execute([$linkId]);
        clubEventAudit($pdo,$eventId,$actorId,'unlink_product','product',(int)$link['product_id'],$note,[]);
        $pdo->commit();return ['id'=>$linkId,'changed'=>true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException||$exception instanceof ClubEventException) throw $exception;
        throw new ClubEventException('Produkt se nepodařilo odpojit bez částečného zápisu.',0,$exception);
    }
}

/** @return array<string,mixed>|false */
function clubEventLock(PDO $pdo, int $eventId): array|false
{
    $sql='SELECT * FROM club_events WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$eventId]);return $statement->fetch(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed> */
function clubEventValidateDraft(int $actorId, array $input): array
{
    $code=strtoupper(trim((string)($input['code']??'')));$type=(string)($input['event_type']??'');
    $name=trim((string)($input['name']??''));$description=trim((string)($input['description_plain']??''));
    $audience=trim((string)($input['audience_label']??''));$capacity=(int)($input['capacity']??0);
    $minAge=($input['min_age']??'')===''?null:(int)$input['min_age'];$maxAge=($input['max_age']??'')===''?null:(int)$input['max_age'];
    $pricing=(string)($input['pricing_policy']??'');$currency=strtoupper(trim((string)($input['currency']??'CZK')));
    $regStart=clubEventNullableDateTime((string)($input['registration_starts_at']??''));$regEnd=clubEventNullableDateTime((string)($input['registration_ends_at']??''));
    if ($actorId<1 || preg_match('/^[A-Z0-9_-]{3,64}$/D',$code)!==1 || !in_array($type,clubEventTypes(),true)) throw new InvalidArgumentException('Neplatný kód, typ nebo administrátor akce.');
    if ($name==='' || mb_strlen($name,'UTF-8')>255 || preg_match('/[<>]/u',$name.$description.$audience)===1) throw new InvalidArgumentException('Název a texty musí být prostý text v povolené délce.');
    if (mb_strlen($description,'UTF-8')>2000 || $audience==='' || mb_strlen($audience,'UTF-8')>255) throw new InvalidArgumentException('Vyplňte platnou cílovou skupinu a popis.');
    if ($capacity<1 || $capacity>10000 || ($minAge!==null&&($minAge<0||$minAge>120)) || ($maxAge!==null&&($maxAge<0||$maxAge>120)) || ($minAge!==null&&$maxAge!==null&&$minAge>$maxAge)) throw new InvalidArgumentException('Kapacita nebo věkové omezení není platné.');
    if (!in_array($pricing,['free','product_variants'],true) || preg_match('/^[A-Z]{3}$/D',$currency)!==1) throw new InvalidArgumentException('Neplatná cenová politika nebo měna.');
    if ($regStart!==null&&$regEnd!==null&&$regStart>=$regEnd) throw new InvalidArgumentException('Konec registrace musí být po jejím začátku.');
    return ['code'=>$code,'event_type'=>$type,'name'=>$name,'description_plain'=>$description,'audience_label'=>$audience,'min_age'=>$minAge,'max_age'=>$maxAge,'capacity'=>$capacity,'pricing_policy'=>$pricing,'currency'=>$currency,'registration_starts_at'=>$regStart,'registration_ends_at'=>$regEnd];
}

function clubEventDateTime(string $value): string
{
    $value=trim($value);$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value);
    if (!$date || $date->format('Y-m-d\TH:i')!==$value) throw new InvalidArgumentException('Datum a čas nemají platný formát.');
    return $date->format('Y-m-d H:i:00');
}
function clubEventNullableDateTime(string $value): ?string { return trim($value)===''?null:clubEventDateTime($value); }

function clubEventAudit(PDO $pdo,int $eventId,int $actorId,string $action,string $subjectType,?int $subjectId,string $note,array $payload): void
{
    $statement=$pdo->prepare('INSERT INTO club_event_admin_events (event_id,actor_trainer_id,action,subject_type,subject_id,note,payload_json) VALUES (?,?,?,?,?,?,?)');
    $statement->execute([$eventId,$actorId,$action,$subjectType,$subjectId,$note,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}
