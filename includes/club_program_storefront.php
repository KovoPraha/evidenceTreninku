<?php
declare(strict_types=1);

require_once __DIR__.'/club_program.php';
require_once __DIR__.'/shop_product_image.php';
require_once __DIR__.'/shop_storefront.php';
require_once __DIR__.'/shop_manual_catalog.php';

final class ClubProgramStorefrontException extends RuntimeException{}

const CLUB_PROGRAM_PURCHASE_OPTIONS=['first_half','second_half','full_year','custom'];

function clubProgramPurchaseOption(string$value):string
{
    $value=trim($value);
    if(!in_array($value,CLUB_PROGRAM_PURCHASE_OPTIONS,true))throw new InvalidArgumentException('Platební varianta není podporována.');
    return$value;
}

function clubProgramPurchaseOptionLabel(string$value):string
{
    return match($value){
        'first_half'=>'1. pololetí',
        'second_half'=>'2. pololetí',
        'full_year'=>'Celý rok',
        default=>'Další varianta',
    };
}

function clubProgramStorefrontTime(string$value,string$label):string
{
    $value=trim($value);
    $time=DateTimeImmutable::createFromFormat('!H:i',$value);
    if(!$time||$time->format('H:i')!==$value)throw new InvalidArgumentException($label.' musí mít formát HH:MM.');
    return$value.':00';
}

/** @return array<string,mixed> */
function clubProgramStorefrontLockProgram(PDO$pdo,int$programId):array
{
    if($programId<1)throw new InvalidArgumentException('Program nebyl vybrán.');
    $sql="SELECT * FROM club_programs WHERE id=? AND status='active'";
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$programId]);$program=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$program)throw new ClubProgramStorefrontException('Aktivní program nebyl nalezen.');
    return$program;
}

/** @param array<string,mixed>$input @return array{program_id:int,changed:bool} */
function clubProgramStorefrontSavePresentation(PDO$pdo,int$actorId,int$programId,array$input,string$reason,bool$confirmed):array
{
    $reason=clubProgramText($reason,1000,'Důvod změny');
    if($actorId<1||!$confirmed)throw new InvalidArgumentException('Změna rozcestníku vyžaduje správce a potvrzení.');
    $publicName=clubProgramText((string)($input['public_name']??''),160,'Veřejný název');
    $summary=clubProgramText((string)($input['public_summary']??''),4000,'Veřejný popis',false);
    $location=clubProgramText((string)($input['location_name']??''),160,'Místo',false);
    $ageLabel=clubProgramText((string)($input['age_label']??''),80,'Věkové doporučení',false);
    $status=(string)($input['listing_status']??'draft');
    if(!in_array($status,['draft','published','hidden'],true))throw new InvalidArgumentException('Stav rozcestníku není podporován.');
    $sort=filter_var($input['sort_order']??0,FILTER_VALIDATE_INT);
    if($sort===false||$sort<-100000||$sort>100000)throw new InvalidArgumentException('Pořadí musí být mezi -100000 a 100000.');
    $interest=!empty($input['interest_enabled'])?1:0;
    $pdo->beginTransaction();
    try{
        clubProgramStorefrontLockProgram($pdo,$programId);
        $query=$pdo->prepare('SELECT * FROM club_program_presentations WHERE program_id=?');$query->execute([$programId]);$before=$query->fetch(PDO::FETCH_ASSOC)?:null;
        $after=['program_id'=>$programId,'public_name'=>$publicName,'public_summary'=>$summary,'location_name'=>$location,'age_label'=>$ageLabel,'listing_status'=>$status,'sort_order'=>(int)$sort,'interest_enabled'=>$interest];
        $changed=$before===null;
        if($before!==null)foreach(array_keys($after)as$key)if((string)($before[$key]??'')!==(string)$after[$key]){$changed=true;break;}
        if($changed){
            if($before===null)$pdo->prepare('INSERT INTO club_program_presentations(program_id,public_name,public_summary,location_name,age_label,listing_status,sort_order,interest_enabled) VALUES(?,?,?,?,?,?,?,?)')->execute([$programId,$publicName,$summary?:null,$location?:null,$ageLabel?:null,$status,$sort,$interest]);
            else$pdo->prepare('UPDATE club_program_presentations SET public_name=?,public_summary=?,location_name=?,age_label=?,listing_status=?,sort_order=?,interest_enabled=?,updated_at=CURRENT_TIMESTAMP WHERE program_id=?')->execute([$publicName,$summary?:null,$location?:null,$ageLabel?:null,$status,$sort,$interest,$programId]);
            $after['_audit_reason']=$reason;clubProgramEvent($pdo,$programId,null,'trainer',$actorId,'update_storefront_presentation',$before,$after);
        }
        $pdo->commit();return['program_id'=>$programId,'changed'=>$changed];
    }catch(Throwable$exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramStorefrontException)throw$exception;
        throw new ClubProgramStorefrontException('Prezentaci kroužku se nepodařilo uložit bez částečné změny.',0,$exception);
    }
}

/** @param array<string,mixed>$input @return array{id:int,program_id:int} */
function clubProgramStorefrontAddSchedule(PDO$pdo,int$actorId,int$programId,array$input,string$reason,bool$confirmed):array
{
    $reason=clubProgramText($reason,1000,'Důvod změny');
    if($actorId<1||!$confirmed)throw new InvalidArgumentException('Přidání času vyžaduje správce a potvrzení.');
    $seasonId=(int)($input['season_id']??0);$weekday=(int)($input['weekday']??0);
    if($seasonId<1||$weekday<1||$weekday>7)throw new InvalidArgumentException('Vyberte sezonu a den týdne.');
    $starts=clubProgramStorefrontTime((string)($input['starts_at']??''),'Začátek');
    $ends=clubProgramStorefrontTime((string)($input['ends_at']??''),'Konec');
    if($starts>=$ends)throw new InvalidArgumentException('Konec tréninku musí být po začátku.');
    $location=clubProgramText((string)($input['location_name']??''),160,'Místo',false);
    $sort=filter_var($input['sort_order']??0,FILTER_VALIDATE_INT);if($sort===false||$sort<-100000||$sort>100000)throw new InvalidArgumentException('Pořadí času není platné.');
    $pdo->beginTransaction();
    try{
        clubProgramStorefrontLockProgram($pdo,$programId);
        $season=$pdo->prepare("SELECT id FROM club_seasons WHERE id=? AND status='active'");$season->execute([$seasonId]);if(!$season->fetchColumn())throw new ClubProgramStorefrontException('Aktivní sezona nebyla nalezena.');
        $pdo->prepare('INSERT INTO club_program_schedule_slots(program_id,season_id,weekday,starts_at,ends_at,location_name,sort_order) VALUES(?,?,?,?,?,?,?)')->execute([$programId,$seasonId,$weekday,$starts,$ends,$location?:null,$sort]);
        $id=(int)$pdo->lastInsertId();$after=['id'=>$id,'season_id'=>$seasonId,'weekday'=>$weekday,'starts_at'=>$starts,'ends_at'=>$ends,'location_name'=>$location,'sort_order'=>(int)$sort,'_audit_reason'=>$reason];
        clubProgramEvent($pdo,$programId,null,'trainer',$actorId,'add_storefront_schedule',null,$after);$pdo->commit();return['id'=>$id,'program_id'=>$programId];
    }catch(Throwable$exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramStorefrontException)throw$exception;
        if($exception instanceof PDOException&&$exception->getCode()==='23000')throw new ClubProgramStorefrontException('Stejný čas už je u programu uložený.',0,$exception);
        throw new ClubProgramStorefrontException('Čas tréninku se nepodařilo přidat.',0,$exception);
    }
}

/** @return array{changed:bool,program_id:int} */
function clubProgramStorefrontRemoveSchedule(PDO$pdo,int$actorId,int$slotId,string$reason,bool$confirmed):array
{
    $reason=clubProgramText($reason,1000,'Důvod změny');if($actorId<1||$slotId<1||!$confirmed)throw new InvalidArgumentException('Odebrání času vyžaduje správce a potvrzení.');
    $pdo->beginTransaction();try{
        $sql='SELECT * FROM club_program_schedule_slots WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $query=$pdo->prepare($sql);$query->execute([$slotId]);$before=$query->fetch(PDO::FETCH_ASSOC);if(!$before)throw new ClubProgramStorefrontException('Čas nebyl nalezen.');
        clubProgramStorefrontLockProgram($pdo,(int)$before['program_id']);$pdo->prepare('DELETE FROM club_program_schedule_slots WHERE id=?')->execute([$slotId]);
        clubProgramEvent($pdo,(int)$before['program_id'],null,'trainer',$actorId,'remove_storefront_schedule',$before,['removed'=>true,'_audit_reason'=>$reason]);$pdo->commit();return['changed'=>true,'program_id'=>(int)$before['program_id']];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramStorefrontException)throw$exception;throw new ClubProgramStorefrontException('Čas se nepodařilo odebrat.',0,$exception);}
}

/** @return array{id:int,program_id:int,image_url:string} */
function clubProgramStorefrontAddImage(PDO$pdo,int$actorId,int$programId,string$source,string$altText,int$sortOrder,string$reason,bool$confirmed,bool$uploaded=true,?string$applicationRoot=null):array
{
    $altText=clubProgramText($altText,255,'Popis obrázku');$reason=clubProgramText($reason,1000,'Důvod změny');
    if($actorId<1||$programId<1||!$confirmed||$sortOrder<-100000||$sortOrder>100000)throw new InvalidArgumentException('Nahrání obrázku vyžaduje správce, pořadí, důvod a potvrzení.');
    $applicationRoot??=dirname(__DIR__);$stored=shopProductImageStoreFile($source,$uploaded,$applicationRoot);
    try{
        $pdo->beginTransaction();clubProgramStorefrontLockProgram($pdo,$programId);
        $pdo->prepare('INSERT INTO club_program_images(program_id,image_url,alt_text,sort_order) VALUES(?,?,?,?)')->execute([$programId,$stored['image_url'],$altText,$sortOrder]);$id=(int)$pdo->lastInsertId();
        clubProgramEvent($pdo,$programId,null,'trainer',$actorId,'add_storefront_image',null,['id'=>$id,'image_url'=>$stored['image_url'],'alt_text'=>$altText,'sort_order'=>$sortOrder,'file'=>$stored,'_audit_reason'=>$reason]);
        $pdo->commit();return['id'=>$id,'program_id'=>$programId,'image_url'=>(string)$stored['image_url']];
    }catch(Throwable$exception){
        if($pdo->inTransaction())$pdo->rollBack();shopProductImageQuarantine(shopProductImagePath((string)$stored['image_url'],$applicationRoot),$applicationRoot);
        if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramStorefrontException)throw$exception;
        throw new ClubProgramStorefrontException('Obrázek kroužku se nepodařilo uložit.',0,$exception);
    }
}

/** @return array{changed:bool,program_id:int} */
function clubProgramStorefrontRemoveImage(PDO$pdo,int$actorId,int$imageId,string$reason,bool$confirmed,?string$applicationRoot=null):array
{
    $reason=clubProgramText($reason,1000,'Důvod změny');if($actorId<1||$imageId<1||!$confirmed)throw new InvalidArgumentException('Odebrání obrázku vyžaduje správce a potvrzení.');
    $applicationRoot??=dirname(__DIR__);$movedFrom=null;$movedTo=null;$pdo->beginTransaction();
    try{
        $sql='SELECT * FROM club_program_images WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$query=$pdo->prepare($sql);$query->execute([$imageId]);$before=$query->fetch(PDO::FETCH_ASSOC);if(!$before)throw new ClubProgramStorefrontException('Obrázek nebyl nalezen.');
        clubProgramStorefrontLockProgram($pdo,(int)$before['program_id']);$movedFrom=shopProductImagePath((string)$before['image_url'],$applicationRoot);if($movedFrom!==null&&is_file($movedFrom))$movedTo=shopProductImageQuarantine($movedFrom,$applicationRoot);
        $pdo->prepare('DELETE FROM club_program_images WHERE id=?')->execute([$imageId]);clubProgramEvent($pdo,(int)$before['program_id'],null,'trainer',$actorId,'remove_storefront_image',$before,['removed'=>true,'_audit_reason'=>$reason]);
        $pdo->commit();return['changed'=>true,'program_id'=>(int)$before['program_id']];
    }catch(Throwable$exception){
        if($pdo->inTransaction())$pdo->rollBack();if($movedFrom!==null&&$movedTo!==null&&is_file($movedTo))@rename($movedTo,$movedFrom);
        if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramStorefrontException)throw$exception;
        throw new ClubProgramStorefrontException('Obrázek kroužku se nepodařilo odebrat.',0,$exception);
    }
}

/** @param array<string,mixed>$input @return array{offer_id:int,variant_id:int,product_id:int,program_id:int} */
function clubProgramCreatePaymentOption(PDO$pdo,int$actorId,int$baseOfferId,array$input,string$reason,bool$confirmed):array
{
    $reason=clubProgramText($reason,1000,'Důvod změny');if($actorId<1||$baseOfferId<1||!$confirmed)throw new InvalidArgumentException('Nová platební varianta vyžaduje správce a potvrzení.');
    $option=clubProgramPurchaseOption((string)($input['purchase_option']??'custom'));$featured=!empty($input['is_featured'])?1:0;
    $variant=shopManualCatalogVariantInput([
        'sku'=>(string)($input['sku']??''),'ean'=>'','attributes_json'=>json_encode(['období'=>clubProgramPurchaseOptionLabel($option)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        'amount_minor'=>$input['amount_minor']??null,'compare_at_amount_minor'=>$input['compare_at_amount_minor']??null,'includes_vat'=>$input['includes_vat']??null,
        'vat_rate_basis_points'=>$input['vat_rate_basis_points']??null,'stock_quantity_decimal'=>'','unit_code'=>'person','visible'=>1,
    ]);
    $pdo->beginTransaction();try{
        $sql='SELECT o.*,p.origin AS product_origin,p.catalog_status AS product_status,p.offer_type FROM club_program_offers o JOIN shop_products p ON p.id=o.product_id WHERE o.id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $query=$pdo->prepare($sql);$query->execute([$baseOfferId]);$base=$query->fetch(PDO::FETCH_ASSOC);if(!$base)throw new ClubProgramStorefrontException('Výchozí nabídka nebyla nalezena.');
        if((string)$base['offer_type']!=='program'||(string)$base['product_origin']!=='manual'||(string)$base['product_status']==='inactive')throw new ClubProgramStorefrontException('Platební variantu lze přidat pouze k aktivnímu ručně spravovanému kroužku.');
        shopManualCatalogAssertSkuAvailable($pdo,(string)$variant['sku']);
        $variantId=shopManualCatalogInsertVariant($pdo,$actorId,(int)$base['product_id'],$variant,(string)$base['product_status']==='active'?'active':'draft');
        shopManualCatalogEvent($pdo,(int)$base['product_id'],$variantId,$actorId,'add_program_payment_option',null,shopManualCatalogLockVariant($pdo,$variantId),$reason);
        $offer=clubProgramCreateOfferInTransaction($pdo,$actorId,(int)$base['program_id'],(int)$base['season_id'],(int)$base['team_id'],(int)$base['product_id'],$variantId,
            (string)($input['code']??''),(string)($input['name']??''),(string)($input['starts_on']??''),(string)($input['ends_on']??''),
            isset($input['sales_open_at'])?(string)$input['sales_open_at']:null,isset($input['sales_close_at'])?(string)$input['sales_close_at']:null,
            ($input['capacity']??'')===''?null:(int)$input['capacity'],(string)($input['status']??'active'),
            ($input['birth_year_from']??'')===''?null:(int)$input['birth_year_from'],($input['birth_year_to']??'')===''?null:(int)$input['birth_year_to']);
        $pdo->prepare('UPDATE club_program_offers SET purchase_option=?,is_featured=? WHERE id=?')->execute([$option,$featured,(int)$offer['id']]);
        clubProgramEvent($pdo,(int)$base['program_id'],(int)$offer['id'],'trainer',$actorId,'set_purchase_option',null,['purchase_option'=>$option,'is_featured'=>$featured,'base_offer_id'=>$baseOfferId,'_audit_reason'=>$reason]);
        $pdo->commit();return['offer_id'=>(int)$offer['id'],'variant_id'=>$variantId,'product_id'=>(int)$base['product_id'],'program_id'=>(int)$base['program_id']];
    }catch(Throwable$exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramStorefrontException||$exception instanceof ClubProgramException||$exception instanceof ShopManualCatalogException)throw$exception;
        throw new ClubProgramStorefrontException('Platební variantu se nepodařilo založit bez částečné změny.',0,$exception);
    }
}

/** @return list<array<string,mixed>> */
function clubProgramStorefrontCatalog(PDO$pdo,?DateTimeImmutable$now=null):array
{
    $now??=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));$today=$now->format('Y-m-d');
    $programs=$pdo->query("SELECT p.id,p.code,p.name,p.description,pr.public_name,pr.public_summary,pr.location_name,pr.age_label,pr.sort_order,pr.interest_enabled FROM club_program_presentations pr JOIN club_programs p ON p.id=pr.program_id WHERE pr.listing_status='published' AND p.status='active' ORDER BY pr.sort_order,pr.public_name,p.id")->fetchAll(PDO::FETCH_ASSOC);
    $result=[];
    foreach($programs as$program){
        $programId=(int)$program['id'];
        $schedule=$pdo->prepare('SELECT ss.*,s.name AS season_name FROM club_program_schedule_slots ss JOIN club_seasons s ON s.id=ss.season_id WHERE ss.program_id=? AND s.status=\'active\' ORDER BY ss.sort_order,ss.weekday,ss.starts_at,ss.id');$schedule->execute([$programId]);
        $images=$pdo->prepare('SELECT id,image_url,alt_text,sort_order FROM club_program_images WHERE program_id=? ORDER BY sort_order,id');$images->execute([$programId]);$safeImages=[];
        foreach($images->fetchAll(PDO::FETCH_ASSOC)as$image){$url=shopStorefrontSafeImageUrl((string)$image['image_url']);if($url!==null){$image['image_url']=$url;$safeImages[]=$image;}}
        $offers=$pdo->prepare("SELECT o.*,v.amount_minor,v.currency,v.sku,spub.public_name AS product_public_name FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id JOIN shop_products p ON p.id=o.product_id JOIN shop_product_publications spub ON spub.product_id=p.id WHERE o.program_id=? AND o.status='active' AND o.ends_on>=? AND p.catalog_status='active' AND v.catalog_status='active' AND (v.visible=1 OR v.visible IS NULL) AND spub.status='active' ORDER BY o.is_featured DESC,CASE o.purchase_option WHEN 'full_year' THEN 0 WHEN 'first_half' THEN 1 WHEN 'second_half' THEN 2 ELSE 3 END,o.starts_on,o.id");
        $offers->execute([$programId,$today]);$publicOffers=[];
        foreach($offers->fetchAll(PDO::FETCH_ASSOC)as$offer){$offer=array_merge($offer,clubProgramOfferCapacityState($pdo,$offer,$now));$sale=clubProgramOfferSaleState($offer,$now);$terms=clubProgramTermsEffective($pdo,$programId,(int)$offer['id']);if($sale['saleable']&&!clubProgramTermsComplete($terms))$sale=['saleable'=>false,'reason'=>'Přihlášení se připravuje.'];$offer['saleable']=$sale['saleable'];$offer['sale_reason']=$sale['reason'];$offer['purchase_label']=clubProgramPurchaseOptionLabel((string)$offer['purchase_option']);$publicOffers[]=$offer;}
        if($publicOffers===[]&&!$program['interest_enabled'])continue;
        $program['schedule']=$schedule->fetchAll(PDO::FETCH_ASSOC);$program['images']=$safeImages;$program['offers']=$publicOffers;$result[]=$program;
    }
    return$result;
}

function clubProgramStorefrontWeekdayLabel(int$weekday):string
{
    return[1=>'Pondělí',2=>'Úterý',3=>'Středa',4=>'Čtvrtek',5=>'Pátek',6=>'Sobota',7=>'Neděle'][$weekday]??'';
}
