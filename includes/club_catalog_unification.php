<?php
declare(strict_types=1);

require_once __DIR__ . '/club_catalog_import.php';

final class ClubCatalogUnificationException extends RuntimeException {}

/**
 * Reviewed 20 October 2026 production inventory. A tuple is product ID,
 * variant ID, original Shoptet SKU, and the canonical purchase option.
 * The retired ambiguous Točňáci 1× and unsplit Kunratice are handled separately.
 *
 * @return array<string,list<array{0:int,1:int,2:string,3:string}>>
 */
function clubCatalogUnificationTargets(): array
{
    return [
        'Aligátoři'=>[[143,671,'675','first_half'],[177,726,'789','full_year']],
        'Cyklistická přípravka'=>[[178,727,'792','first_half'],[129,657,'633','full_year']],
        'Jezevci'=>[[185,734,'813','first_half'],[135,663,'651','full_year']],
        'Kamzíci'=>[[132,660,'642','first_half'],[180,729,'798','full_year']],
        'Kapybary'=>[[141,669,'669','first_half'],[186,735,'816','full_year']],
        'Kroužek +'=>[[136,664,'654','first_half'],[237,803,'982','full_year']],
        'Lamy'=>[[145,673,'681','first_half'],[189,738,'825','full_year']],
        'Lemuři'=>[[148,676,'688','first_half'],[192,741,'834','full_year']],
        'Lenochodi'=>[[142,670,'672','first_half'],[187,736,'819','full_year']],
        'Lvíčci'=>[[147,675,'687','first_half'],[191,740,'831','full_year']],
        'Mývalové'=>[[146,674,'684','first_half'],[190,739,'828','full_year']],
        'Papuchálci'=>[[131,659,'639','first_half'],[181,730,'801','full_year']],
        'Pásovci'=>[[144,672,'678','first_half'],[188,737,'822','full_year']],
        'Předpřípravka'=>[[153,681,'705','first_half'],[238,804,'985','full_year']],
        'Pumptrack'=>[[290,941,'997','first_half'],[279,893,'1000','full_year']],
        'Sloni'=>[[149,677,'693','first_half'],[195,744,'843','full_year']],
        'Sportovní přípravka'=>[[128,656,'630','first_half'],[179,728,'795','full_year']],
        'Sumci'=>[[183,732,'807','first_half'],[133,661,'645','full_year']],
        'Surikaty'=>[[193,742,'837','first_half'],[150,678,'696','full_year']],
        'Svišti'=>[[130,658,'636','first_half'],[182,731,'804','full_year']],
        'Točňáci 2× týdně'=>[[155,683,'709','first_half']],
        'Zebry'=>[[239,805,'988','first_half'],[240,806,'991','full_year']],
        'Žirafy'=>[[151,679,'699','first_half'],[194,743,'840','full_year']],
        'Zubříci'=>[[184,733,'810','first_half'],[134,662,'648','full_year']],
    ];
}

/** @return list<array<string,mixed>> */
function clubCatalogUnificationPlan(PDO $pdo, bool $lock = false): array
{
    $suffix = $lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
    $productQuery=$pdo->prepare('SELECT p.id AS product_id,p.name AS product_name,p.origin AS product_origin,p.offer_type,p.catalog_status AS product_status,'
        .'v.id AS variant_id,v.sku,v.amount_minor,v.currency,v.origin AS variant_origin,v.catalog_status AS variant_status,v.stock_quantity_decimal,v.unit_code,v.visible,'
        .'pub.status AS publication_status FROM shop_products p JOIN shop_variants v ON v.product_id=p.id '
        .'LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=? AND v.id=?'.$suffix);
    $programQuery=$pdo->prepare('SELECT * FROM club_programs WHERE code=?'.$suffix);
    $offersQuery=$pdo->prepare('SELECT o.*,v.sku AS source_sku,v.amount_minor AS source_amount,v.catalog_status AS source_variant_status,'
        .'p.origin AS source_origin FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id '
        .'JOIN shop_products p ON p.id=o.product_id WHERE o.program_id=? ORDER BY o.id'.$suffix);
    $linkedQuery=$pdo->prepare('SELECT * FROM club_program_offers WHERE variant_id=?'.$suffix);
    $variantCount=$pdo->prepare('SELECT COUNT(*) FROM shop_variants WHERE product_id=?');
    $plan=[];
    foreach(clubCatalogUnificationTargets() as $group=>$variants){
        $code='KROUZKY-2627-'.clubCatalogImportSlug($group);
        $programQuery->execute([$code]);$program=$programQuery->fetch(PDO::FETCH_ASSOC);
        if(!$program||(string)$program['name']!==$group||(string)$program['status']!=='active')throw new ClubCatalogUnificationException("Kanonický program chybí nebo se změnil: $code");
        $offersQuery->execute([(int)$program['id']]);$offers=$offersQuery->fetchAll(PDO::FETCH_ASSOC);
        $teams=[];$seasons=[];
        foreach($offers as $offer){$teams[(int)$offer['team_id']]=true;$seasons[(int)$offer['season_id']]=true;}
        if(count($teams)!==1||count($seasons)!==1)throw new ClubCatalogUnificationException("Nabídky nesdílejí jedinou soupisku a sezonu: $code");
        foreach($variants as [$productId,$variantId,$sku,$option]){
            $productQuery->execute([$productId,$variantId]);$product=$productQuery->fetch(PDO::FETCH_ASSOC);
            if(!$product||(string)$product['sku']!==$sku||(string)$product['product_origin']!=='import'
                ||(string)$product['variant_origin']!=='import'||(string)$product['offer_type']!=='program'
                ||(string)$product['product_status']!=='active'||(string)$product['variant_status']!=='active'
                ||(string)$product['publication_status']!=='active'||(string)$product['currency']!=='CZK'
                ||$product['amount_minor']===null||(int)$product['amount_minor']<1
                ||$product['stock_quantity_decimal']!==null||(string)$product['unit_code']!=='person'){
                throw new ClubCatalogUnificationException("Importovaná varianta má jiný stav nebo identitu: $productId/$variantId/$sku");
            }
            $nameKey=legacyClubCatalogKey($group);$productKey=legacyClubCatalogKey((string)$product['product_name']);
            if(!str_contains($productKey,$nameKey))throw new ClubCatalogUnificationException("Název importovaného produktu neodpovídá skupině: $productId/$variantId");
            $variantCount->execute([$productId]);if((int)$variantCount->fetchColumn()!==1)throw new ClubCatalogUnificationException("Importovaný produkt má další varianty: $productId");
            $matches=array_values(array_filter($offers,static fn(array $offer):bool=>(string)$offer['purchase_option']===$option&&(string)$offer['source_origin']==='manual'));
            if(count($matches)!==1)throw new ClubCatalogUnificationException("Chybí jediná původní nabídka: $code/$option");
            $source=$matches[0];
            $linkedQuery->execute([$variantId]);$linked=$linkedQuery->fetch(PDO::FETCH_ASSOC);
            if($linked&&((int)$linked['program_id']!==(int)$program['id']||(int)$linked['team_id']!==(int)$source['team_id']
                ||(int)$linked['season_id']!==(int)$source['season_id']||(string)$linked['purchase_option']!==$option
                ||(int)$linked['product_id']!==$productId||(string)$linked['status']!=='active')){
                throw new ClubCatalogUnificationException("Varianta už má jiné přiřazení: $variantId");
            }
            if($linked&&(string)$source['status']!=='closed')throw new ClubCatalogUnificationException("Duplicitní původní nabídka zůstala otevřená: $code/$option");
            if(!$linked&&(string)$source['status']!=='active')throw new ClubCatalogUnificationException("Původní nabídka není aktivní: $code/$option");
            if((int)$product['amount_minor']!==(int)$source['source_amount'])
                throw new ClubCatalogUnificationException("Cena importované a původní nabídky se liší: $productId/$variantId");
            $plan[]=['group'=>$group,'program_code'=>$code,'program_id'=>(int)$program['id'],'product_id'=>$productId,
                'variant_id'=>$variantId,'sku'=>$sku,'purchase_option'=>$option,'team_id'=>(int)$source['team_id'],
                'season_id'=>(int)$source['season_id'],'source_offer'=>$source,'linked_offer'=>$linked?:null,
                'price_minor'=>(int)$product['amount_minor'],'source_price_minor'=>(int)$source['source_amount']];
        }
    }
    if(count($plan)!==47)throw new LogicException('Inventura musí obsahovat přesně 47 variant.');
    return $plan;
}

/** @return array{applied:bool,targets:int,created:int,already_linked:int,rows:list<array<string,mixed>>} */
function clubCatalogUnify(PDO $pdo,int $actorId,bool $apply):array
{
    if($actorId<1)throw new InvalidArgumentException('Převod vyžaduje správce.');
    $reason='Sjednocení Shoptet variant s kroužky a soupiskami 2026/27 podle schváleného názvu a inventury 20. 10. 2026.';
    if($apply)$pdo->beginTransaction();
    try{
        $plan=clubCatalogUnificationPlan($pdo,$apply);$created=0;$already=0;$rows=[];
        foreach($plan as $row){
            $source=$row['source_offer'];$linked=$row['linked_offer'];
            if($linked){$already++;}
            elseif($apply){
                $new=clubProgramCreateOfferInTransaction($pdo,$actorId,$row['program_id'],$row['season_id'],$row['team_id'],
                    $row['product_id'],$row['variant_id'],'SHOPTET-2627-P'.$row['product_id'].'-V'.$row['variant_id'],
                    (string)$source['name'],(string)$source['starts_on'],(string)$source['ends_on'],
                    $source['sales_open_at']!==null?(string)$source['sales_open_at']:null,
                    $source['sales_close_at']!==null?(string)$source['sales_close_at']:null,
                    $source['capacity']!==null?(int)$source['capacity']:null,'active',
                    $source['birth_year_from']!==null?(int)$source['birth_year_from']:null,
                    $source['birth_year_to']!==null?(int)$source['birth_year_to']:null);
                $pdo->prepare('UPDATE club_program_offers SET purchase_option=?,is_featured=? WHERE id=?')
                    ->execute([$row['purchase_option'],(int)$source['is_featured'],(int)$new['id']]);
                clubProgramEvent($pdo,$row['program_id'],(int)$new['id'],'trainer',$actorId,'set_purchase_option',null,
                    ['purchase_option'=>$row['purchase_option'],'is_featured'=>(int)$source['is_featured'],
                     'source_offer_id'=>(int)$source['id'],'_audit_reason'=>$reason]);
                $after=$source;$after['status']='closed';$after['_audit_reason']=$reason;
                $pdo->prepare("UPDATE club_program_offers SET status='closed',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([(int)$source['id']]);
                clubProgramEvent($pdo,$row['program_id'],(int)$source['id'],'trainer',$actorId,'close_offer',$source,$after);
                $oldVariant=shopManualCatalogLockVariant($pdo,(int)$source['variant_id']);
                $pdo->prepare("UPDATE shop_variants SET catalog_status='inactive',visible=0,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([(int)$source['variant_id']]);
                shopManualCatalogEvent($pdo,(int)$source['product_id'],(int)$source['variant_id'],$actorId,'retire_duplicate_program_variant',
                    $oldVariant,shopManualCatalogLockVariant($pdo,(int)$source['variant_id']),$reason);
                $created++;
            }
            $rows[]=['group'=>$row['group'],'product_id'=>$row['product_id'],'variant_id'=>$row['variant_id'],
                'sku'=>$row['sku'],'option'=>$row['purchase_option'],'program_id'=>$row['program_id'],
                'team_id'=>$row['team_id'],'source_offer_id'=>(int)$source['id'],
                'linked_offer_id'=>$linked?(int)$linked['id']:null,'action'=>$linked?'already_linked':'create_and_close_duplicate',
                'price_minor'=>$row['price_minor'],'source_price_minor'=>$row['source_price_minor']];
        }
        if($apply)$pdo->commit();
        return ['applied'=>$apply,'targets'=>count($plan),'created'=>$created,'already_linked'=>$already,'rows'=>$rows];
    }catch(Throwable $e){if($apply&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
