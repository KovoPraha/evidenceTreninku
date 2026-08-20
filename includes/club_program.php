<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';
require_once __DIR__ . '/club_program_terms.php';

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
    $pdo->beginTransaction();
    try {
        $result = clubProgramCreateInTransaction($pdo, $actorId, $code, $name, $description);
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubProgramException) {
            throw $exception;
        }
        throw new ClubProgramException('Program se nepodařilo založit bez částečného zápisu.', 0, $exception);
    }
}

function clubProgramColumnExists(PDO $pdo, string $table, string $column): bool
{
    if(preg_match('/^[a-z0-9_]+$/D',$table)!==1||preg_match('/^[a-z0-9_]+$/D',$column)!==1)return false;
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table,$column]);return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
}

/** @return array<string,mixed> */
function clubProgramCreateInTransaction(
    PDO $pdo,
    int $actorId,
    string $code,
    string $name,
    string $description = ''
): array {
    if (!$pdo->inTransaction()) throw new LogicException('Založení programu vyžaduje otevřenou transakci.');
    if ($actorId < 1) throw new InvalidArgumentException('Program vyžaduje správce.');
    $code = clubProgramCode($code);
    $name = clubProgramText($name, 160, 'Název programu');
    $description = clubProgramText($description, 4000, 'Popis programu', false);
    $sql = 'SELECT * FROM club_programs WHERE code=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$code]);
    $existing = $statement->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ((string)$existing['name'] !== $name || (string)($existing['description'] ?? '') !== $description) throw new ClubProgramException('Kód už označuje jiný stabilní program.');
        return $existing;
    }
    $pdo->prepare("INSERT INTO club_programs(code,name,description,status,created_by_trainer_id) VALUES (?,?,?,'active',?)")
        ->execute([$code,$name,$description !== '' ? $description : null,$actorId]);
    $result = ['id'=>(int)$pdo->lastInsertId(),'code'=>$code,'name'=>$name,'description'=>$description,'status'=>'active'];
    clubProgramEvent($pdo, (int)$result['id'], null, 'trainer', $actorId, 'create_program', null, $result);
    return $result;
}

/** @return array<string,mixed> */
function clubProgramCreateOffer(PDO $pdo, int $actorId, int $programId, int $seasonId, int $teamId, int $productId, int $variantId, string $code, string $name, string $startsOn, string $endsOn, ?string $salesOpenAt, ?string $salesCloseAt, ?int $capacity, string $status, ?int $birthYearFrom = null, ?int $birthYearTo = null): array
{
    $pdo->beginTransaction();
    try {
        $result = clubProgramCreateOfferInTransaction(
            $pdo, $actorId, $programId, $seasonId, $teamId, $productId, $variantId,
            $code, $name, $startsOn, $endsOn, $salesOpenAt, $salesCloseAt, $capacity, $status,
            $birthYearFrom, $birthYearTo
        );
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ClubProgramException) {
            throw $exception;
        }
        throw new ClubProgramException('Nabídku se nepodařilo založit bez částečného zápisu.', 0, $exception);
    }
}

/** @return array<string,mixed> */
function clubProgramCreateOfferInTransaction(PDO $pdo, int $actorId, int $programId, int $seasonId, int $teamId, int $productId, int $variantId, string $code, string $name, string $startsOn, string $endsOn, ?string $salesOpenAt, ?string $salesCloseAt, ?int $capacity, string $status, ?int $birthYearFrom = null, ?int $birthYearTo = null): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Založení nabídky vyžaduje otevřenou transakci.');
    if (min($actorId,$programId,$seasonId,$teamId,$productId,$variantId) < 1) throw new InvalidArgumentException('Nabídka vyžaduje program, období, soupisku, produkt, variantu a správce.');
    $code = clubProgramCode($code);$name = clubProgramText($name, 180, 'Název nabídky');
    $startsOn = clubProgramDate($startsOn);$endsOn = clubProgramDate($endsOn);
    if ($startsOn > $endsOn) throw new InvalidArgumentException('Konec nabídky musí být nejdříve v den začátku.');
    if ($capacity !== null && ($capacity < 1 || $capacity > 100000)) throw new InvalidArgumentException('Kapacita musí být prázdná nebo mezi 1 a 100000.');
    [$birthYearFrom,$birthYearTo]=clubProgramBirthYearRange($birthYearFrom,$birthYearTo);
    if (!in_array($status, ['draft','active','closed'], true)) throw new InvalidArgumentException('Stav nabídky není podporován.');
    $salesOpenAt = clubProgramInputDateTime($salesOpenAt);
    $salesCloseAt = clubProgramInputDateTime($salesCloseAt);
    if ($salesOpenAt !== null && $salesCloseAt !== null && $salesOpenAt >= $salesCloseAt) throw new InvalidArgumentException('Konec prodeje musí být po jeho otevření.');
    $programSql = "SELECT id FROM club_programs WHERE id=? AND status='active'";
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $programSql .= ' FOR UPDATE';
    $program = $pdo->prepare($programSql);$program->execute([$programId]);
    if (!$program->fetchColumn()) throw new ClubProgramException('Aktivní program nebyl nalezen.');
    $teamSql = 'SELECT t.id,t.season_id,s.starts_on,s.ends_on FROM club_teams t JOIN club_seasons s ON s.id=t.season_id WHERE t.id=? AND t.season_id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $teamSql .= ' FOR UPDATE';
    $team = $pdo->prepare($teamSql);
    $team->execute([$teamId,$seasonId]);$team = $team->fetch(PDO::FETCH_ASSOC);
    if (!$team) throw new ClubProgramException('Soupiska nepatří do vybraného období.');
    if ($startsOn < (string)$team['starts_on'] || $endsOn > (string)$team['ends_on']) throw new InvalidArgumentException('Platnost nabídky musí ležet uvnitř sezony soupisky.');
    $variantSql = 'SELECT v.id,v.product_id,v.catalog_status AS variant_status,p.offer_type,p.catalog_status AS product_status '
        . 'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.id=? AND p.id=? AND ('
        . "(p.offer_type='goods' AND p.catalog_status='active' AND v.catalog_status='active') OR "
        . "(p.offer_type='program' AND p.catalog_status IN ('draft','active') AND v.catalog_status IN ('draft','active')))";
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $variantSql .= ' FOR UPDATE';
    $variant = $pdo->prepare($variantSql);
    $variant->execute([$variantId,$productId]);
    if (!$variant->fetch()) throw new ClubProgramException('Varianta není pro nabídku dostupná. Koncept je povolen jen typu program; zboží musí zůstat aktivní.');
    $existingSql = 'SELECT * FROM club_program_offers WHERE code=? OR variant_id=? ORDER BY id LIMIT 1';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $existingSql .= ' FOR UPDATE';
    $existing = $pdo->prepare($existingSql);
    $existing->execute([$code,$variantId]);$existing = $existing->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ((string)$existing['code'] !== $code || (int)$existing['variant_id'] !== $variantId) throw new ClubProgramException('Kód nebo varianta už patří jiné nabídce.');
        return $existing;
    }
    $pdo->prepare('INSERT INTO club_program_offers(program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,sales_open_at,sales_close_at,capacity,birth_year_from,birth_year_to,status,created_by_trainer_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$programId,$seasonId,$teamId,$productId,$variantId,$code,$name,$startsOn,$endsOn,$salesOpenAt,$salesCloseAt,$capacity,$birthYearFrom,$birthYearTo,$status,$actorId]);
    $result = [
        'id'=>(int)$pdo->lastInsertId(),'program_id'=>$programId,'season_id'=>$seasonId,
        'team_id'=>$teamId,'product_id'=>$productId,'variant_id'=>$variantId,'code'=>$code,
        'name'=>$name,'starts_on'=>$startsOn,'ends_on'=>$endsOn,'sales_open_at'=>$salesOpenAt,
        'sales_close_at'=>$salesCloseAt,'capacity'=>$capacity,'birth_year_from'=>$birthYearFrom,
        'birth_year_to'=>$birthYearTo,'status'=>$status,
    ];
    clubProgramEvent($pdo, $programId, (int)$result['id'], 'trainer', $actorId, 'create_offer', null, $result);
    return $result;
}

/** @param array<string,mixed> $input @return array{id:int,changed:bool,status:string} */
function clubProgramUpdateOffer(PDO$pdo,int$actorId,int$offerId,array$input,string$reason,bool$confirmed):array
{
    $reason=trim($reason);if($actorId<1||$offerId<1||!$confirmed||$reason===''||mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Úprava nabídky vyžaduje správce, důvod a výslovné potvrzení.');
    $pdo->beginTransaction();try{
        $sql='SELECT o.*,s.starts_on AS season_starts_on,s.ends_on AS season_ends_on FROM club_program_offers o JOIN club_seasons s ON s.id=o.season_id WHERE o.id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$statement=$pdo->prepare($sql);$statement->execute([$offerId]);$before=$statement->fetch(PDO::FETCH_ASSOC);if(!$before)throw new ClubProgramException('Nabídka nebyla nalezena.');
        $name=clubProgramText((string)($input['name']??''),180,'Název nabídky');$startsOn=clubProgramDate((string)($input['starts_on']??''));$endsOn=clubProgramDate((string)($input['ends_on']??''));if($startsOn>$endsOn)throw new InvalidArgumentException('Konec nabídky musí být nejdříve v den začátku.');if($startsOn<(string)$before['season_starts_on']||$endsOn>(string)$before['season_ends_on'])throw new InvalidArgumentException('Platnost nabídky musí ležet uvnitř sezony soupisky.');
        $salesOpenAt=clubProgramInputDateTime(isset($input['sales_open_at'])?(string)$input['sales_open_at']:null);$salesCloseAt=clubProgramInputDateTime(isset($input['sales_close_at'])?(string)$input['sales_close_at']:null);if($salesOpenAt!==null&&$salesCloseAt!==null&&$salesOpenAt>=$salesCloseAt)throw new InvalidArgumentException('Konec prodeje musí být po jeho otevření.');
        $capacity=$input['capacity']??null;$capacity=$capacity===null||trim((string)$capacity)===''?null:filter_var($capacity,FILTER_VALIDATE_INT);if($capacity===false||($capacity!==null&&($capacity<1||$capacity>100000)))throw new InvalidArgumentException('Kapacita musí být prázdná nebo mezi 1 a 100000.');
        $birthFromRaw=$input['birth_year_from']??null;$birthToRaw=$input['birth_year_to']??null;[$birthYearFrom,$birthYearTo]=clubProgramBirthYearRange($birthFromRaw===null||trim((string)$birthFromRaw)===''?null:(int)$birthFromRaw,$birthToRaw===null||trim((string)$birthToRaw)===''?null:(int)$birthToRaw);$status=(string)($input['status']??'');if(!in_array($status,['draft','active','closed'],true))throw new InvalidArgumentException('Stav nabídky není podporován.');if((string)$before['status']==='closed'&&$status!=='closed')throw new ClubProgramException('Uzavřenou nabídku nelze znovu otevřít; pro další období založte novou.');
        $capacityState=clubProgramOfferCapacityState($pdo,$before,null,true);if($capacity!==null&&(int)$capacity<(int)$capacityState['occupied_count'])throw new ClubProgramException('Kapacitu nelze snížit pod počet aktivních účastí a platných rezervací.');
        $after=$before;foreach(['name'=>$name,'starts_on'=>$startsOn,'ends_on'=>$endsOn,'sales_open_at'=>$salesOpenAt,'sales_close_at'=>$salesCloseAt,'capacity'=>$capacity,'birth_year_from'=>$birthYearFrom,'birth_year_to'=>$birthYearTo,'status'=>$status]as$key=>$value)$after[$key]=$value;
        $changed=false;foreach(['name','starts_on','ends_on','sales_open_at','sales_close_at','capacity','birth_year_from','birth_year_to','status']as$key)if((string)($before[$key]??'')!==(string)($after[$key]??''))$changed=true;
        if($changed){$pdo->prepare('UPDATE club_program_offers SET name=?,starts_on=?,ends_on=?,sales_open_at=?,sales_close_at=?,capacity=?,birth_year_from=?,birth_year_to=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$name,$startsOn,$endsOn,$salesOpenAt,$salesCloseAt,$capacity,$birthYearFrom,$birthYearTo,$status,$offerId]);$fresh=$pdo->prepare('SELECT * FROM club_program_offers WHERE id=?');$fresh->execute([$offerId]);$after=$fresh->fetch(PDO::FETCH_ASSOC);$after['_audit_reason']=$reason;$action=$status==='closed'&&(string)$before['status']!=='closed'?'close_offer':'update_offer';clubProgramEvent($pdo,(int)$before['program_id'],$offerId,'trainer',$actorId,$action,$before,$after);}
        $pdo->commit();return['id'=>$offerId,'changed'=>$changed,'status'=>$status];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramException)throw$exception;throw new ClubProgramException('Nabídku se nepodařilo upravit bez částečného zápisu.',0,$exception);}
}

/** @return array{id:int,changed:bool,status:string} */
function clubProgramCloseOffer(PDO$pdo,int$actorId,int$offerId,string$reason,bool$confirmed):array
{
    $statement=$pdo->prepare('SELECT name,starts_on,ends_on,sales_open_at,sales_close_at,capacity,birth_year_from,birth_year_to,status FROM club_program_offers WHERE id=?');$statement->execute([$offerId]);$offer=$statement->fetch(PDO::FETCH_ASSOC);if(!$offer)throw new ClubProgramException('Nabídka nebyla nalezena.');$offer['status']='closed';return clubProgramUpdateOffer($pdo,$actorId,$offerId,$offer,$reason,$confirmed);
}

/** @return list<array<string,mixed>> */
function clubProgramAdminSelectableVariants(PDO $pdo): array
{
    return $pdo->query(
        'SELECT v.id AS variant_id,v.product_id,v.sku,v.catalog_status AS variant_status,'
        . 'p.name AS product_name,p.offer_type,p.catalog_status AS product_status '
        . 'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE '
        . "(p.offer_type='goods' AND p.catalog_status='active' AND v.catalog_status='active') OR "
        . "(p.offer_type='program' AND p.catalog_status IN ('draft','active') AND v.catalog_status IN ('draft','active')) "
        . 'ORDER BY p.name,v.sku,v.id'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function clubProgramAdminOffers(PDO $pdo): array
{
    $rows=$pdo->query('SELECT o.*,p.code AS program_code,p.name AS program_name,s.name AS season_name,t.name AS team_name,v.sku,sp.name AS product_name FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id JOIN club_seasons s ON s.id=o.season_id JOIN club_teams t ON t.id=o.team_id JOIN shop_variants v ON v.id=o.variant_id JOIN shop_products sp ON sp.id=o.product_id ORDER BY o.starts_on DESC,o.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as&$row){
        $row=array_merge($row,clubProgramOfferCapacityState($pdo,$row));
        $row['effective_terms']=clubProgramTermsEffective($pdo,(int)$row['program_id'],(int)$row['id']);
        $row['terms_complete']=clubProgramTermsComplete($row['effective_terms']);
        $saleState=clubProgramOfferSaleState($row);if($saleState['saleable']&&!$row['terms_complete'])$saleState=['saleable'=>false,'reason'=>'Nabídka nemá zveřejněné platné storno podmínky a souhlas.'];$row['saleable']=$saleState['saleable'];$row['sale_reason']=$saleState['reason'];
    }
    unset($row);
    return$rows;
}

/** @return array<string,mixed>|false */
function clubProgramOfferForVariant(PDO $pdo, int $variantId, ?DateTimeImmutable $now = null, bool $lock = false): array|false
{
    $sql=
        "SELECT o.*,p.name AS program_name "
        . "FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id "
        . "WHERE o.variant_id=? AND o.status='active' AND p.status='active'";
    if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$variantId]);
    $offer=$statement->fetch(PDO::FETCH_ASSOC);
    return$offer?array_merge($offer,clubProgramOfferCapacityState($pdo,$offer,$now,$lock)):false;
}

function clubProgramProductHasOfferLink(PDO $pdo, int $productId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM club_program_offers WHERE product_id=? LIMIT 1');
    $stmt->execute([$productId]);
    return $stmt->fetchColumn() !== false;
}

function clubProgramProductHasActiveOffer(PDO $pdo, int $productId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM club_program_offers o JOIN club_programs p ON p.id = o.program_id WHERE o.product_id = ? AND o.status = \'active\' AND p.status = \'active\' LIMIT 1');
    $stmt->execute([$productId]);
    return $stmt->fetchColumn() !== false;
}

/** @param array<string,mixed> $offer @return array{saleable:bool,reason:string} */
function clubProgramOfferSaleState(array $offer, ?DateTimeImmutable $now = null): array
{
    $timezone = new DateTimeZone('Europe/Prague');
    $now = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    if (($offer['status'] ?? null) !== 'active') return ['saleable'=>false,'reason'=>'Nabídka není aktivní.'];
    if (!empty($offer['sales_open_at'])) {
        $salesOpen = clubProgramPragueDateTime((string)$offer['sales_open_at']);
        if ($salesOpen === null) return ['saleable'=>false,'reason'=>'Začátek prodejního okna není platný.'];
        if ($now < $salesOpen) return ['saleable'=>false,'reason'=>'Prodejní okno ještě nezačalo.'];
    }
    if (!empty($offer['sales_close_at'])) {
        $salesClose = clubProgramPragueDateTime((string)$offer['sales_close_at']);
        if ($salesClose === null) return ['saleable'=>false,'reason'=>'Konec prodejního okna není platný.'];
        if ($now > $salesClose) return ['saleable'=>false,'reason'=>'Prodejní okno už skončilo.'];
    }
    $endsOn = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($offer['ends_on'] ?? ''), $timezone);
    if (!$endsOn || $now > $endsOn->setTime(23, 59, 59, 999999)) {
        return ['saleable'=>false,'reason'=>'Nabídka už skončila.'];
    }
    if (($offer['capacity'] ?? null) !== null
        && ((int)($offer['active_enrollment_count'] ?? 0)+(int)($offer['held_order_count']??0)) >= (int)$offer['capacity']) {
        return ['saleable'=>false,'reason'=>'Kapacita nabídky je naplněna.'];
    }
    return ['saleable'=>true,'reason'=>'Nabídka je právě v prodeji.'];
}

function clubProgramPragueDateTime(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    $timezone = new DateTimeZone('Europe/Prague');
    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$dateTime || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $dateTime->format('Y-m-d H:i:s') !== $value) {
        return null;
    }
    return $dateTime;
}

function clubProgramInputDateTime(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timezone = new DateTimeZone('Europe/Prague');
    foreach (['!Y-m-d\TH:i', '!Y-m-d H:i:s'] as $format) {
        $dateTime = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $outputFormat = str_contains($format, '\T') ? 'Y-m-d\TH:i' : 'Y-m-d H:i:s';
        if ($dateTime && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $dateTime->format($outputFormat) === $value) {
            return $dateTime->format('Y-m-d H:i:s');
        }
    }
    throw new InvalidArgumentException('Prodejní termín není platné datum a čas v Europe/Prague.');
}

/** @return array{0:?int,1:?int} */
function clubProgramBirthYearRange(?int $from, ?int $to): array
{
    $currentYear=(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('Y');
    foreach(['od'=>$from,'do'=>$to]as$label=>$year){
        if($year!==null&&($year<1900||$year>$currentYear)){
            throw new InvalidArgumentException('Ročník '.$label.' musí být mezi 1900 a '.$currentYear.'.');
        }
    }
    if($from!==null&&$to!==null&&$from>$to)throw new InvalidArgumentException('Počáteční ročník nesmí být vyšší než koncový.');
    return[$from,$to];
}

/** @param array<string,mixed> $offer */
function clubProgramBirthYearLabel(array $offer): string
{
    $from=$offer['birth_year_from']??null;$to=$offer['birth_year_to']??null;
    if($from===null&&$to===null)return'bez omezení ročníku';
    if($from!==null&&$to!==null)return(int)$from===(int)$to?'pro ročník '.(int)$from:'pro ročníky '.(int)$from.'–'.(int)$to;
    return$from!==null?'pro ročníky od '.(int)$from:'pro ročníky do '.(int)$to;
}

/** @param array<string,mixed> $offer */
function clubProgramAssertBeneficiaryBirthYear(PDO $pdo, array $offer, int $sportovecId, bool $lock = false): void
{
    $from=$offer['birth_year_from']??null;$to=$offer['birth_year_to']??null;
    if($from===null&&$to===null)return;
    if($sportovecId<1)throw new ClubProgramException('Pro kroužek vyberte dítě nebo účastníka.');
    $sql='SELECT narozeni FROM sportovci WHERE id=?';
    if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$sportovecId]);$birth=$statement->fetchColumn();
    $birth=(string)($birth===false?'':$birth);
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$birth);
    if(!$date||$date->format('Y-m-d')!==$birth){
        throw new ClubProgramException('Vybraný účastník nemá vyplněné platné datum narození. Doplňte ho v části Moje osoby a zkuste to znovu.');
    }
    $year=(int)$date->format('Y');
    if(($from!==null&&$year<(int)$from)||($to!==null&&$year>(int)$to)){
        throw new ClubProgramException('Vybraný účastník nesplňuje věkové omezení nabídky ('.clubProgramBirthYearLabel($offer).').');
    }
}

/**
 * Capacity has one source of truth: active enrollments plus pending order
 * items whose existing order payment deadline has not elapsed.
 *
 * @param array<string,mixed> $offer
 * @return array{active_enrollment_count:int,held_order_count:int,occupied_count:int,available_count:?int}
 */
function clubProgramOfferCapacityState(PDO $pdo, array $offer, ?DateTimeImmutable $now = null, bool $lock = false): array
{
    $offerId=(int)($offer['id']??$offer['offer_id']??0);
    $productId=(int)($offer['product_id']??0);$variantId=(int)($offer['variant_id']??0);
    if(min($offerId,$productId,$variantId)<1)throw new LogicException('Výpočet kapacity vyžaduje nabídku, produkt a variantu.');
    $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $activeSql="SELECT id FROM club_program_enrollments WHERE offer_id=? AND status='active'";
    if($lock&&$mysql)$activeSql.=' FOR UPDATE';
    $active=$pdo->prepare($activeSql);$active->execute([$offerId]);$activeCount=count($active->fetchAll(PDO::FETCH_COLUMN));
    $timezone=new DateTimeZone('Europe/Prague');
    $now=($now??new DateTimeImmutable('now',$timezone))->setTimezone($timezone);
    $hasHoldSchema=clubProgramColumnExists($pdo,'shop_order_items','variant_id')
        &&clubProgramColumnExists($pdo,'shop_orders','payment_expires_at');
    $heldCount=0;
    if($hasHoldSchema){
        $heldSql=
            "SELECT oi.id,oi.quantity FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id "
            . "WHERE oi.product_id=? AND oi.variant_id=? AND o.status='placed' AND o.payment_status='pending' "
            . 'AND o.payment_expires_at IS NOT NULL AND o.payment_expires_at>=? ';
        if(clubProgramColumnExists($pdo,'club_program_enrollments','source_order_item_id'))$heldSql.="AND NOT EXISTS(SELECT 1 FROM club_program_enrollments e WHERE e.source_order_item_id=oi.id AND e.status='active') ";
        if($lock&&$mysql)$heldSql.=' FOR UPDATE';
        $held=$pdo->prepare($heldSql);$held->execute([$productId,$variantId,$now->format('Y-m-d H:i:s')]);
        foreach($held->fetchAll(PDO::FETCH_ASSOC)as$row)$heldCount+=(int)$row['quantity'];
    }
    $occupied=$activeCount+$heldCount;$capacity=$offer['capacity']??null;
    return[
        'active_enrollment_count'=>$activeCount,'held_order_count'=>$heldCount,'occupied_count'=>$occupied,
        'available_count'=>$capacity===null?null:max(0,(int)$capacity-$occupied),
    ];
}

/** @param array<string,mixed> $offer */
function clubProgramOfferIsOnSale(array $offer, ?DateTimeImmutable $now = null): bool
{
    return clubProgramOfferSaleState($offer, $now)['saleable'];
}

/** @return array{saleable:bool,reason:string,offer:array<string,mixed>|null} */
function clubProgramVariantSaleState(PDO $pdo, int $variantId, ?DateTimeImmutable $now = null, bool $lock = false): array
{
    $offer = clubProgramOfferForVariant($pdo, $variantId, $now, $lock);
    if (!$offer) return ['saleable'=>false,'reason'=>'Varianta nemá aktivní nabídku programu.','offer'=>null];
    $state = clubProgramOfferSaleState($offer, $now);
    if($state['saleable']&&clubProgramTermsRegistryAvailable($pdo)&&!clubProgramTermsComplete(clubProgramTermsEffective($pdo,(int)$offer['program_id'],(int)$offer['id'],$lock))){
        $state=['saleable'=>false,'reason'=>'Nabídka nemá zveřejněné platné storno podmínky a souhlas.'];
    }
    return $state + ['offer'=>$offer];
}

function clubProgramProductHasEffectiveTerms(PDO $pdo,int $productId):bool
{
    $statement=$pdo->prepare('SELECT id,program_id FROM club_program_offers WHERE product_id=? ORDER BY id');$statement->execute([$productId]);
    foreach($statement->fetchAll(PDO::FETCH_ASSOC)as$offer)if(clubProgramTermsComplete(clubProgramTermsEffective($pdo,(int)$offer['program_id'],(int)$offer['id'])))return true;
    return false;
}

/** @return array{saleable:bool,reason:string} */
function clubProgramProductSaleState(PDO $pdo, int $productId, ?DateTimeImmutable $now = null): array
{
    $variants = $pdo->prepare('SELECT variant_id FROM club_program_offers WHERE product_id=? ORDER BY id DESC');
    $variants->execute([$productId]);
    $reason = 'Produkt nemá navázanou nabídku.';
    $hasReason = false;
    foreach ($variants->fetchAll(PDO::FETCH_COLUMN) as $variantId) {
        $state = clubProgramVariantSaleState($pdo, (int)$variantId, $now);
        if ($state['saleable']) return ['saleable'=>true,'reason'=>$state['reason']];
        if (!$hasReason) {
            $reason = $state['reason'];
            $hasReason = true;
        }
    }
    return ['saleable'=>false,'reason'=>$reason];
}

/** @param array<string,mixed>|null $before @param array<string,mixed> $after */
function clubProgramEvent(PDO $pdo, int $programId, ?int $offerId, string $actorType, int $actorId, string $action, ?array $before, array $after): void
{
    if (!$pdo->inTransaction() || $programId < 1 || $actorType !== 'trainer' || $actorId < 1
        || !in_array($action, ['create_program','create_offer','update_offer','close_offer'], true)) {
        throw new LogicException('Audit programu vyžaduje transakci, objekt, správce a podporovanou akci.');
    }
    $pdo->prepare(
        'INSERT INTO club_program_events(program_id,offer_id,actor_type,actor_id,action,before_json,after_json) '
        . 'VALUES (?,?,?,?,?,?,?)'
    )->execute([
        $programId,$offerId,$actorType,$actorId,$action,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        json_encode($after, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
    ]);
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
function clubProgramActivateOrderItemInTransaction(PDO $pdo, int $accountId, int $orderItemId, string $actorType, ?int $actorId): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Aktivace programu vyžaduje otevřenou transakci.');
    if ($accountId < 1 || $orderItemId < 1 || !in_array($actorType,['account','trainer','system'],true) || ($actorType==='system'&&$actorId!==null) || ($actorType!=='system'&&($actorId??0)<1)) throw new InvalidArgumentException('Aktivace programu nemá platného vlastníka nebo auditora.');
    $termsSelect=clubProgramTermsAcceptanceAvailable($pdo)?'oi.program_terms_snapshot_json,oi.program_terms_accepted_at,oi.program_terms_accepted_by_account_id':'NULL AS program_terms_snapshot_json,NULL AS program_terms_accepted_at,NULL AS program_terms_accepted_by_account_id';
    $itemSql='SELECT oi.id AS order_item_id,oi.beneficiary_sportovec_id,oi.quantity,oi.line_amount_minor,oi.product_id,oi.variant_id,'.$termsSelect.',o.id AS order_id,o.account_id,o.status AS order_status,o.payment_status FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id JOIN verejni_uzivatele vu ON vu.id=o.account_id AND vu.aktivni=1 AND vu.email_overeno=1 WHERE oi.id=? AND o.account_id=?';
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
    $termsRequired=clubProgramTermsAcceptanceAvailable($pdo);
    if($termsRequired&&((int)($item['program_terms_accepted_by_account_id']??0)!==$accountId||empty($item['program_terms_accepted_at'])||!clubProgramTermsSnapshotValid((string)($item['program_terms_snapshot_json']??''))))throw new ClubProgramException('Objednávka nemá doložitelný souhlas s podmínkami kroužku.');
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
    if($termsRequired)$pdo->prepare("INSERT INTO club_program_enrollments(offer_id,sportovec_id,account_id,source_order_item_id,status,active_token,valid_from,valid_to,activated_at,terms_snapshot_json,terms_accepted_at,terms_accepted_by_account_id) VALUES (?,?,?,?,'active','active',?,?,CURRENT_TIMESTAMP,?,?,?)")
        ->execute([(int)$item['offer_id'],$sportovecId,$accountId,$orderItemId,(string)$item['starts_on'],(string)$item['ends_on'],(string)$item['program_terms_snapshot_json'],(string)$item['program_terms_accepted_at'],$accountId]);
    else $pdo->prepare("INSERT INTO club_program_enrollments(offer_id,sportovec_id,account_id,source_order_item_id,status,active_token,valid_from,valid_to,activated_at) VALUES (?,?,?,?,'active','active',?,?,CURRENT_TIMESTAMP)")
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
function clubProgramActivatePaidOrderInTransaction(PDO $pdo, int $orderId, ?int $actorId, string $actorType='trainer'): array
{
    if (!$pdo->inTransaction() || $orderId<1 || !in_array($actorType,['trainer','system'],true) || ($actorType==='trainer'&&($actorId??0)<1) || ($actorType==='system'&&$actorId!==null)) throw new InvalidArgumentException('Synchronizace zaplacené objednávky vyžaduje transakci, objednávku a platného auditora.');
    $order=$pdo->prepare("SELECT id,account_id,status,payment_status FROM shop_orders WHERE id=? AND payment_status='paid' AND status IN ('processing','ready','completed')");$order->execute([$orderId]);$order=$order->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new ClubProgramException('Objednávka není ve stavu potvrzené platby.');
    $items=$pdo->prepare('SELECT oi.id FROM shop_order_items oi JOIN club_program_offers co ON co.variant_id=oi.variant_id AND co.product_id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');$items->execute([$orderId]);$items=$items->fetchAll(PDO::FETCH_COLUMN);
    $created=0;foreach($items as $itemId){$result=clubProgramActivateOrderItemInTransaction($pdo,(int)$order['account_id'],(int)$itemId,$actorType,$actorId);if($result['created'])$created++;}
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
