<?php
declare(strict_types=1);

require_once __DIR__.'/club_program_storefront.php';
require_once __DIR__.'/shop_manual_catalog.php';

final class ClubProgramRecurrenceException extends RuntimeException{}

function clubProgramShiftYear(string $value,int $years,bool $dateTime=false):string
{
    $date=substr($value,0,10);
    clubProgramDate($date);
    $targetYear=(int)substr($date,0,4)+$years;
    $shifted=sprintf('%04d%s',$targetYear,substr($date,4));
    if(clubProgramDateOrNull($shifted)===null)throw new ClubProgramRecurrenceException('Termín nelze bezpečně opakovat v cílovém roce.');
    return $dateTime?$shifted.substr($value,10):$shifted;
}

/** Repeating rules are opt-in, audit logged, and never change existing dated offers. */
function clubProgramRecurrenceConfigure(PDO $pdo,int $actorId,int $sourceOfferId,bool $enabled,int $horizonYears,string $reason):void
{
    $reason=clubProgramText($reason,1000,'Důvod změny');
    if($actorId<1||$sourceOfferId<1||$horizonYears<1||$horizonYears>2)throw new InvalidArgumentException('Opakování má neplatné údaje.');
    $pdo->beginTransaction();
    try{
        $sql='SELECT o.*,v.amount_minor,v.price_mode,v.currency,v.catalog_status AS variant_status,v.visible AS variant_visible,p.status AS program_status,sp.catalog_status AS product_status,pub.status AS publication_status FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id JOIN club_programs p ON p.id=o.program_id JOIN shop_products sp ON sp.id=o.product_id JOIN shop_product_publications pub ON pub.product_id=sp.id WHERE o.id=?';
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $q=$pdo->prepare($sql);$q->execute([$sourceOfferId]);$offer=$q->fetch(PDO::FETCH_ASSOC);
        if(!$offer||(int)($offer['recurrence_source_offer_id']??0)>0)throw new ClubProgramRecurrenceException('Opakování lze nastavit jen u původní nabídky kroužku.');
        if($enabled&&((string)$offer['program_status']!=='active'||(string)$offer['product_status']!=='active'||(string)$offer['variant_status']!=='active'||(int)($offer['variant_visible']??1)!==1||(string)$offer['publication_status']!=='active'||(string)$offer['price_mode']!=='fixed'||(string)$offer['currency']!=='CZK'||(int)$offer['amount_minor']<1||!clubProgramTermsComplete(clubProgramTermsEffective($pdo,(int)$offer['program_id'],$sourceOfferId,true)))){
            throw new ClubProgramRecurrenceException('Nejprve připravte aktivní program, schválené podmínky a kladnou cenu.');
        }
        $before=$pdo->prepare('SELECT enabled,horizon_years FROM club_program_recurrence_rules WHERE source_offer_id=?');$before->execute([$sourceOfferId]);$before=$before->fetch(PDO::FETCH_ASSOC)?:null;
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
            $pdo->prepare('INSERT INTO club_program_recurrence_rules(source_offer_id,enabled,horizon_years,updated_by_trainer_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),horizon_years=VALUES(horizon_years),updated_by_trainer_id=VALUES(updated_by_trainer_id)')->execute([$sourceOfferId,$enabled?1:0,$horizonYears,$actorId]);
        }else{
            $pdo->prepare('INSERT INTO club_program_recurrence_rules(source_offer_id,enabled,horizon_years,updated_by_trainer_id) VALUES(?,?,?,?) ON CONFLICT(source_offer_id) DO UPDATE SET enabled=excluded.enabled,horizon_years=excluded.horizon_years,updated_by_trainer_id=excluded.updated_by_trainer_id')->execute([$sourceOfferId,$enabled?1:0,$horizonYears,$actorId]);
        }
        clubProgramEvent($pdo,(int)$offer['program_id'],$sourceOfferId,'trainer',$actorId,'configure_recurrence',$before,['enabled'=>$enabled,'horizon_years'=>$horizonYears,'_audit_reason'=>$reason]);
        $pdo->commit();
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

/** @return array{created:int,skipped:int,errors:list<string>} */
function clubProgramRecurrenceGenerate(PDO $pdo,int $schoolYearStart,bool $apply=true):array
{
    if($schoolYearStart<2000||$schoolYearStart>2100)throw new InvalidArgumentException('Cílový školní rok není platný.');
    $rules=$pdo->query("SELECT r.source_offer_id FROM club_program_recurrence_rules r WHERE r.enabled=1 ORDER BY r.source_offer_id")->fetchAll(PDO::FETCH_COLUMN);
    $result=['created'=>0,'skipped'=>0,'errors'=>[]];
    foreach($rules as$sourceId){
        try{
            if(!$apply){$result['skipped']++;continue;}
            $pdo->beginTransaction();
            $created=clubProgramRecurrenceGenerateOne($pdo,(int)$sourceId,$schoolYearStart);
            $pdo->commit();
            $result[$created?'created':'skipped']++;
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();$result['errors'][]='Nabídka #'.(int)$sourceId.': '.$e->getMessage();}
    }
    return$result;
}

/** @return array{created:int,would_create:int,skipped:int,errors:list<string>} */
function clubProgramRecurrenceRefresh(PDO $pdo,?DateTimeImmutable $now=null,bool $apply=false):array
{
    $now??=new DateTimeImmutable('today',new DateTimeZone('Europe/Prague'));
    $currentYear=clubProgramSchoolYearStart($now->format('Y-m-d'));
    $rules=$pdo->query('SELECT r.source_offer_id,r.horizon_years,o.starts_on FROM club_program_recurrence_rules r JOIN club_program_offers o ON o.id=r.source_offer_id WHERE r.enabled=1 ORDER BY r.source_offer_id')->fetchAll(PDO::FETCH_ASSOC);
    $result=['created'=>0,'would_create'=>0,'skipped'=>0,'errors'=>[]];
    foreach($rules as$rule){
        for($year=max($currentYear,clubProgramSchoolYearStart((string)$rule['starts_on'])+1);$year<=$currentYear+min(2,max(1,(int)$rule['horizon_years']));$year++){
            try{
                $pdo->beginTransaction();
                $created=clubProgramRecurrenceGenerateOne($pdo,(int)$rule['source_offer_id'],$year);
                if($apply)$pdo->commit();else $pdo->rollBack();
                $result[$created?($apply?'created':'would_create'):'skipped']++;
            }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();$result['errors'][]='Nabídka #'.(int)$rule['source_offer_id'].' pro '.$year.': '.$e->getMessage();}
        }
    }
    return$result;
}

function clubProgramRecurrenceGenerateOne(PDO $pdo,int $sourceOfferId,int $schoolYearStart):bool
{
    if(!$pdo->inTransaction())throw new LogicException('Generování vyžaduje transakci.');
    $sql='SELECT o.*,r.enabled,r.updated_by_trainer_id,v.sku,v.attributes_json,v.amount_minor,v.compare_at_amount_minor,v.includes_vat,v.vat_rate_basis_points,v.catalog_status AS variant_status,v.visible AS variant_visible,v.price_mode,v.currency,p.status AS program_status,sp.catalog_status AS product_status,pub.status AS publication_status FROM club_program_offers o JOIN club_program_recurrence_rules r ON r.source_offer_id=o.id JOIN shop_variants v ON v.id=o.variant_id JOIN club_programs p ON p.id=o.program_id JOIN shop_products sp ON sp.id=o.product_id JOIN shop_product_publications pub ON pub.product_id=sp.id WHERE o.id=?';
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $q=$pdo->prepare($sql);$q->execute([$sourceOfferId]);$source=$q->fetch(PDO::FETCH_ASSOC);
    if(!$source||!$source['enabled'])return false;
    $sourceYear=clubProgramSchoolYearStart((string)$source['starts_on']);
    if($schoolYearStart<=$sourceYear)return false;
    if((string)$source['program_status']!=='active'||(string)$source['product_status']!=='active'||(string)$source['variant_status']!=='active'||(int)($source['variant_visible']??1)!==1||(string)$source['publication_status']!=='active'||(string)$source['price_mode']!=='fixed'||(string)$source['currency']!=='CZK'||(int)$source['amount_minor']<1)throw new ClubProgramRecurrenceException('Produkt nebo schválená cena již nejsou aktivní.');
    if(!clubProgramTermsComplete(clubProgramTermsEffective($pdo,(int)$source['program_id'],$sourceOfferId,true)))throw new ClubProgramRecurrenceException('Chybí schválené podmínky.');
    $existing=$pdo->prepare('SELECT id FROM club_program_offers WHERE recurrence_source_offer_id=? AND starts_on=?');
    $diff=$schoolYearStart-$sourceYear;$start=clubProgramShiftYear((string)$source['starts_on'],$diff);$end=clubProgramShiftYear((string)$source['ends_on'],$diff);
    $existing->execute([$sourceOfferId,$start]);if($existing->fetchColumn()!==false)return false;
    $overlap=$pdo->prepare('SELECT id FROM club_program_offers WHERE program_id=? AND team_id=? AND purchase_option=? AND starts_on<=? AND ends_on>=? LIMIT 1');
    $overlap->execute([(int)$source['program_id'],(int)$source['team_id'],(string)$source['purchase_option'],$end,$start]);
    if($overlap->fetchColumn()!==false)throw new ClubProgramRecurrenceException('V cílovém roce již existuje překrývající se nabídka stejného typu.');
    $suffix='-Y'.$schoolYearStart;
    $code=substr((string)$source['code'],0,64-strlen($suffix)).$suffix;
    $sku=substr((string)$source['sku'],0,64-strlen($suffix)).$suffix;
    shopManualCatalogAssertSkuAvailable($pdo,$sku);
    $name=preg_replace('/\b20\d{2}\s*\/\s*(?:20)?\d{2}\b/u',clubProgramSchoolYearLabel($start),(string)$source['name']);
    if($name===(string)$source['name'])$name=mb_substr((string)$name,0,170,'UTF-8').' '.clubProgramSchoolYearLabel($start);
    $variant=shopManualCatalogVariantInput(['sku'=>$sku,'ean'=>'','attributes_json'=>(string)$source['attributes_json'],'amount_minor'=>(int)$source['amount_minor'],'compare_at_amount_minor'=>$source['compare_at_amount_minor']===null?null:(int)$source['compare_at_amount_minor'],'includes_vat'=>$source['includes_vat']===null?null:(int)$source['includes_vat'],'vat_rate_basis_points'=>$source['vat_rate_basis_points']===null?null:(int)$source['vat_rate_basis_points'],'stock_quantity_decimal'=>'','unit_code'=>'person','visible'=>1]);
    $actorId=(int)$source['updated_by_trainer_id'];
    $variantId=shopManualCatalogInsertVariant($pdo,$actorId,(int)$source['product_id'],$variant,'active');
    $open=$source['sales_open_at']===null?null:clubProgramShiftYear((string)$source['sales_open_at'],$diff,true);
    $close=$source['sales_close_at']===null?null:clubProgramShiftYear((string)$source['sales_close_at'],$diff,true);
    $offer=clubProgramCreateOfferInTransaction($pdo,$actorId,(int)$source['program_id'],(int)$source['season_id'],(int)$source['team_id'],(int)$source['product_id'],$variantId,$code,$name,$start,$end,$open,$close,$source['capacity']===null?null:(int)$source['capacity'],'draft',$source['birth_year_from']===null?null:(int)$source['birth_year_from']+$diff,$source['birth_year_to']===null?null:(int)$source['birth_year_to']+$diff);
    $pdo->prepare('UPDATE club_program_offers SET recurrence_source_offer_id=?,purchase_option=?,is_featured=? WHERE id=?')->execute([$sourceOfferId,(string)$source['purchase_option'],(int)$source['is_featured'],(int)$offer['id']]);
    if(!clubProgramTermsComplete(clubProgramTermsEffective($pdo,(int)$source['program_id'],(int)$offer['id'],true)))throw new ClubProgramRecurrenceException('Nová nabídka nemá schválené podmínky; nebyla vytvořena.');
    clubProgramEvent($pdo,(int)$source['program_id'],(int)$offer['id'],'trainer',$actorId,'repeat_offer',$source,['source_offer_id'=>$sourceOfferId,'target_school_year'=>$schoolYearStart,'status'=>'draft','requires_price_capacity_terms_review'=>true,'offer_id'=>(int)$offer['id']]);
    return true;
}
