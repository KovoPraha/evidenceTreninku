<?php
declare(strict_types=1);

require_once __DIR__.'/club_catalog_import.php';
require_once __DIR__.'/shop_catalog_publication.php';

final class ClubCatalogUnificationException extends RuntimeException {}

/**
 * Reviewed 20 October 2026 inventory. Each tuple is imported product ID,
 * imported variant ID, Shoptet SKU, and the canonical purchase option.
 * Točňáci 1× and Kunratice have their own replacement workflows.
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
    $suffix=$lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $productQuery=$pdo->prepare('SELECT p.id AS product_id,p.name AS product_name,p.origin AS product_origin,p.offer_type,p.catalog_status AS product_status,'
        .'v.id AS variant_id,v.sku,v.amount_minor,v.currency,v.origin AS variant_origin,v.catalog_status AS variant_status,v.stock_quantity_decimal,v.unit_code,'
        .'pub.status AS publication_status FROM shop_products p JOIN shop_variants v ON v.product_id=p.id '
        .'LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=? AND v.id=?'.$suffix);
    $programQuery=$pdo->prepare('SELECT * FROM club_programs WHERE code=?'.$suffix);
    $offersQuery=$pdo->prepare('SELECT o.*,v.sku AS source_sku,v.amount_minor AS source_amount,v.catalog_status AS source_variant_status,'
        .'p.origin AS source_origin,p.offer_type AS source_offer_type,p.catalog_status AS source_product_status,pub.status AS source_publication_status '
        .'FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id '
        .'JOIN shop_products p ON p.id=o.product_id JOIN shop_product_publications pub ON pub.product_id=p.id '
        .'WHERE o.program_id=? ORDER BY o.id'.$suffix);
    $linkedQuery=$pdo->prepare('SELECT id FROM club_program_offers WHERE variant_id=?'.$suffix);
    $variantCount=$pdo->prepare('SELECT COUNT(*) FROM shop_variants WHERE product_id=?');
    $plan=[];
    foreach(clubCatalogUnificationTargets() as $group=>$variants){
        $code='KROUZKY-2627-'.clubCatalogImportSlug($group);
        $programQuery->execute([$code]);$program=$programQuery->fetch(PDO::FETCH_ASSOC);
        if(!$program||(string)$program['name']!==$group||(string)$program['status']!=='active')
            throw new ClubCatalogUnificationException("Kanonický program chybí nebo se změnil: $code");
        $offersQuery->execute([(int)$program['id']]);$offers=$offersQuery->fetchAll(PDO::FETCH_ASSOC);
        $teams=[];$seasons=[];
        foreach($offers as $offer){$teams[(int)$offer['team_id']]=true;$seasons[(int)$offer['season_id']]=true;}
        if(count($teams)!==1||count($seasons)!==1)
            throw new ClubCatalogUnificationException("Nabídky nesdílejí jedinou soupisku a sezonu: $code");
        foreach($variants as [$productId,$variantId,$sku,$option]){
            $productQuery->execute([$productId,$variantId]);$product=$productQuery->fetch(PDO::FETCH_ASSOC);
            if(!$product||(string)$product['sku']!==$sku||(string)$product['product_origin']!=='import'
                ||(string)$product['variant_origin']!=='import'||(string)$product['offer_type']!=='goods'
                ||(string)$product['currency']!=='CZK'||$product['amount_minor']===null||(int)$product['amount_minor']<1
                ||(string)$product['unit_code']!=='piece'||$product['stock_quantity_decimal']===null)
                throw new ClubCatalogUnificationException("Importované zboží má jiný stav nebo identitu: $productId/$variantId/$sku");
            $active=(string)$product['product_status']==='active'&&(string)$product['variant_status']==='active'
                &&(string)$product['publication_status']==='active';
            $archived=(string)$product['product_status']==='inactive'&&(string)$product['variant_status']==='inactive'
                &&(string)$product['publication_status']==='inactive';
            if(!$active&&!$archived)throw new ClubCatalogUnificationException("Importovaný produkt je pouze částečně archivovaný: $productId");
            if(!str_contains(legacyClubCatalogKey((string)$product['product_name']),legacyClubCatalogKey($group)))
                throw new ClubCatalogUnificationException("Název importovaného produktu neodpovídá skupině: $productId/$variantId");
            $variantCount->execute([$productId]);if((int)$variantCount->fetchColumn()!==1)
                throw new ClubCatalogUnificationException("Importovaný produkt má další varianty: $productId");
            $linkedQuery->execute([$variantId]);if($linkedQuery->fetchColumn()!==false)
                throw new ClubCatalogUnificationException("Staré zboží je navázané na program; historické objednávky nelze bezpečně oddělit: $variantId");
            $matches=array_values(array_filter($offers,static fn(array $offer):bool=>(string)$offer['purchase_option']===$option
                &&(string)$offer['source_origin']==='manual'));
            if(count($matches)!==1)throw new ClubCatalogUnificationException("Chybí jediná původní nabídka: $code/$option");
            $source=$matches[0];
            if((string)$source['status']!=='active'||(string)$source['source_offer_type']!=='program'
                ||(string)$source['source_product_status']!=='active'||(string)$source['source_variant_status']!=='active'
                ||(string)$source['source_publication_status']!=='active'||$source['capacity']===null||(int)$source['capacity']!==30)
                throw new ClubCatalogUnificationException("Cílová nabídka není aktivní a připravená s kapacitou 30: $code/$option");
            $targetPrice=(int)$source['source_amount'];$importedPrice=(int)$product['amount_minor'];
            if($group==='Pumptrack'){
                $approved=$option==='first_half'?650000:1150000;
                $old=$option==='first_half'?440000:850000;
                if($targetPrice!==$approved||!in_array($importedPrice,[$old,$approved],true))
                    throw new ClubCatalogUnificationException("Pumptrack nemá schválený cíl nebo očekávanou starou cenu: $option");
            }elseif($targetPrice!==$importedPrice){
                throw new ClubCatalogUnificationException("Cena importovaného zboží a cílové nabídky se liší: $productId/$variantId");
            }
            $plan[]=['group'=>$group,'program_code'=>$code,'program_id'=>(int)$program['id'],'product_id'=>$productId,
                'variant_id'=>$variantId,'sku'=>$sku,'purchase_option'=>$option,'team_id'=>(int)$source['team_id'],
                'season_id'=>(int)$source['season_id'],'source_offer'=>$source,'linked_offer'=>null,
                'target_product_id'=>(int)$source['product_id'],'target_variant_id'=>(int)$source['variant_id'],
                'price_minor'=>$importedPrice,'source_price_minor'=>$targetPrice,'archived'=>$archived,
                'stock_quantity_decimal'=>$product['stock_quantity_decimal'],'unit_code'=>$product['unit_code']];
        }
    }
    if(count($plan)!==47)throw new LogicException('Inventura musí obsahovat přesně 47 variant.');
    return $plan;
}

/**
 * An archived Shoptet detail resolves to the already published manual offer.
 * It never turns a historical goods order into a program order.
 * @return array{product_id:int,variant_id:int}|null
 */
function clubCatalogLegacyOfferTarget(PDO $pdo,int $productId,int $variantId=0):?array
{
    $target=null;
    foreach(clubCatalogUnificationTargets() as $group=>$variants){
        foreach($variants as [$oldProductId,$oldVariantId,$sku,$option])if($oldProductId===$productId){
            if($variantId>0&&$variantId!==$oldVariantId)return null;
            $target=[$group,$oldVariantId,$sku,$option];break 2;
        }
    }
    if($target===null)return null;
    [$group,$oldVariantId,$sku,$option]=$target;
    $old=$pdo->prepare("SELECT p.origin AS product_origin,p.offer_type,p.catalog_status AS product_status,v.origin AS variant_origin,v.catalog_status AS variant_status,v.sku,pub.status AS publication_status FROM shop_products p JOIN shop_variants v ON v.product_id=p.id JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=? AND v.id=?");
    $old->execute([$productId,$oldVariantId]);$row=$old->fetch(PDO::FETCH_ASSOC);
    if(!$row||(string)$row['product_origin']!=='import'||(string)$row['variant_origin']!=='import'
        ||(string)$row['offer_type']!=='goods'||(string)$row['sku']!==$sku
        ||(string)$row['product_status']!=='inactive'||(string)$row['variant_status']!=='inactive'
        ||(string)$row['publication_status']!=='inactive')return null;
    $code='KROUZKY-2627-'.clubCatalogImportSlug($group);
    $query=$pdo->prepare("SELECT o.product_id,o.variant_id FROM club_program_offers o JOIN club_programs cp ON cp.id=o.program_id JOIN shop_products p ON p.id=o.product_id JOIN shop_variants v ON v.id=o.variant_id JOIN shop_product_publications pub ON pub.product_id=p.id WHERE cp.code=? AND cp.status='active' AND o.purchase_option=? AND o.status='active' AND p.origin='manual' AND p.offer_type='program' AND p.catalog_status='active' AND v.catalog_status='active' AND pub.status='active'");
    $query->execute([$code,$option]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)!==1)return null;
    return ['product_id'=>(int)$rows[0]['product_id'],'variant_id'=>(int)$rows[0]['variant_id']];
}

/** @return array{applied:bool,targets:int,created:int,already_linked:int,rows:list<array<string,mixed>>} */
function clubCatalogUnify(PDO $pdo,int $actorId,bool $apply):array
{
    if($actorId<1)throw new InvalidArgumentException('Sjednocení vyžaduje správce.');
    $reason='Schválené sjednocení Shoptet nabídky kroužků 2026/27: historické zboží archivováno, nákup vede na kanonickou soupisku.';
    if($apply)$pdo->beginTransaction();
    try{
        $plan=clubCatalogUnificationPlan($pdo,$apply);$created=0;$already=0;$rows=[];
        foreach($plan as $row){
            if($row['archived'])$already++;
            elseif($apply){
                shopCatalogPublicationDeactivateInTransaction($pdo,$row['product_id'],$actorId,$reason);
                $created++;
            }
            $rows[]=['group'=>$row['group'],'product_id'=>$row['product_id'],'variant_id'=>$row['variant_id'],
                'sku'=>$row['sku'],'option'=>$row['purchase_option'],'program_id'=>$row['program_id'],
                'team_id'=>$row['team_id'],'source_offer_id'=>(int)$row['source_offer']['id'],
                'linked_offer_id'=>(int)$row['source_offer']['id'],
                'target_product_id'=>$row['target_product_id'],'target_variant_id'=>$row['target_variant_id'],
                'action'=>$row['archived']?'already_archived':'archive_and_redirect',
                'price_minor'=>$row['price_minor'],'source_price_minor'=>$row['source_price_minor'],
                'stock_quantity_decimal'=>$row['stock_quantity_decimal'],'unit_code'=>$row['unit_code']];
        }
        if($apply)$pdo->commit();
        return ['applied'=>$apply,'targets'=>count($plan),'created'=>$created,'already_linked'=>$already,'rows'=>$rows];
    }catch(Throwable $e){if($apply&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
