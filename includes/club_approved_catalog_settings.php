<?php
declare(strict_types=1);

require_once __DIR__.'/club_program.php';
require_once __DIR__.'/shop_manual_catalog.php';

final class ClubApprovedCatalogSettingsException extends RuntimeException {}

/** @return list<array{product_id:int,variant_id:int,sku:string,old_minor:int,new_minor:int,option:string}> */
function clubApprovedPumptrackPrices(): array
{
    return [
        ['product_id'=>290,'variant_id'=>941,'sku'=>'997','old_minor'=>440000,'new_minor'=>650000,'option'=>'first_half'],
        ['product_id'=>279,'variant_id'=>893,'sku'=>'1000','old_minor'=>850000,'new_minor'=>1150000,'option'=>'full_year'],
    ];
}

/**
 * An audited, repeatable application of the club's 2026/27 capacity and Pumptrack price decision.
 * The preview validates every row; apply validates again under locks and commits all changes together.
 *
 * @return array{applied:bool,capacity_offers:int,capacity_changed:int,price_variants:int,price_changed:int,offers:list<array<string,mixed>>,prices:list<array<string,mixed>>}
 */
function clubApprovedCatalogSettings(PDO $pdo,int $actorId,bool $apply):array
{
    if($actorId<1)throw new InvalidArgumentException('Změna ceníku a kapacity vyžaduje správce.');
    $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    if($apply)$pdo->beginTransaction();
    try{
        $season=$pdo->query("SELECT id,starts_on,ends_on FROM club_seasons WHERE code='SCHOOL-2026-27'")->fetch(PDO::FETCH_ASSOC);
        if(!$season||(string)$season['starts_on']!=='2026-09-01'||(string)$season['ends_on']!=='2027-08-31')
            throw new ClubApprovedCatalogSettingsException('Sezona SCHOOL-2026-27 neodpovídá očekávanému období.');
        $sql="SELECT o.*,p.code AS program_code,p.name AS program_name FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id "
            ."WHERE o.season_id=? AND p.code LIKE 'KROUZKY-2627-%' AND p.name NOT LIKE 'TEST - %' ORDER BY o.id";
        if($apply&&$mysql)$sql.=' FOR UPDATE';
        $query=$pdo->prepare($sql);$query->execute([(int)$season['id']]);$offers=$query->fetchAll(PDO::FETCH_ASSOC);
        if(count($offers)<74)throw new ClubApprovedCatalogSettingsException('Inventura obsahuje méně než 74 skutečných nabídek kroužků.');
        $offerPlan=[];
        foreach($offers as$offer){
            if((string)$offer['status']!=='active'&&(string)$offer['status']!=='draft'&&(string)$offer['status']!=='closed')
                throw new ClubApprovedCatalogSettingsException('Nabídka má nepodporovaný stav: '.$offer['id']);
            $state=clubProgramOfferCapacityState($pdo,$offer,null,$apply);
            if((int)$state['occupied_count']>30)throw new ClubApprovedCatalogSettingsException('Kapacita 30 je pod obsazením nabídky '.$offer['id'].'.');
            $offerPlan[]=['id'=>(int)$offer['id'],'program_code'=>(string)$offer['program_code'],
                'before'=>$offer['capacity']===null?null:(int)$offer['capacity'],'after'=>30,
                'occupied'=>(int)$state['occupied_count'],'changed'=>$offer['capacity']===null||(int)$offer['capacity']!==30,
                '_row'=>$offer];
        }
        $pricePlan=[];
        $variantSql='SELECT v.*,p.origin AS product_origin,p.name AS product_name,p.offer_type,p.catalog_status AS product_status '
            .'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.id=? AND p.id=?';
        if($apply&&$mysql)$variantSql.=' FOR UPDATE';
        $variantQuery=$pdo->prepare($variantSql);
        $canonicalQuery=$pdo->prepare("SELECT v.amount_minor FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id "
            ."JOIN shop_variants v ON v.id=o.variant_id JOIN shop_products sp ON sp.id=o.product_id "
            ."WHERE p.code='KROUZKY-2627-PUMPTRACK' AND o.purchase_option=? AND sp.origin='manual'");
        foreach(clubApprovedPumptrackPrices()as$target){
            $variantQuery->execute([$target['variant_id'],$target['product_id']]);$variant=$variantQuery->fetch(PDO::FETCH_ASSOC);
            if(!$variant||(string)$variant['sku']!==$target['sku']||(string)$variant['origin']!=='import'
                ||(string)$variant['product_origin']!=='import'||(string)$variant['currency']!=='CZK'
                ||(string)$variant['price_mode']!=='fixed'||(string)$variant['unit_code']!=='person'
                ||(string)$variant['catalog_status']!=='active'||(string)$variant['product_status']!=='active'
                ||(string)$variant['offer_type']!=='program'||stripos((string)$variant['product_name'],'Pumptrack')===false
                ||!in_array((int)$variant['amount_minor'],[$target['old_minor'],$target['new_minor']],true))
                throw new ClubApprovedCatalogSettingsException('Identita nebo cena importovaného Pumptracku se změnila: '.$target['variant_id']);
            $canonicalQuery->execute([$target['option']]);$canonicalPrices=$canonicalQuery->fetchAll(PDO::FETCH_COLUMN);
            if(count($canonicalPrices)!==1||(int)$canonicalPrices[0]!==$target['new_minor'])
                throw new ClubApprovedCatalogSettingsException('Kanonická cena Pumptracku není jednoznačná: '.$target['option']);
            $pricePlan[]=['product_id'=>$target['product_id'],'variant_id'=>$target['variant_id'],'sku'=>$target['sku'],
                'before'=>(int)$variant['amount_minor'],'after'=>$target['new_minor'],
                'changed'=>(int)$variant['amount_minor']!==$target['new_minor'],'_row'=>$variant];
        }
        $capacityChanged=0;$priceChanged=0;
        if($apply){
            $reason='Schváleno klubem 20. 10. 2026: kapacita každé skupiny 30 dětí, Pumptrack 6 500 / 11 500 Kč.';
            $updateCapacity=$pdo->prepare('UPDATE club_program_offers SET capacity=30,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            foreach($offerPlan as$row){
                if(!$row['changed'])continue;
                $before=$row['_row'];$after=$before;$after['capacity']=30;$after['_audit_reason']=$reason;
                $updateCapacity->execute([$row['id']]);
                clubProgramEvent($pdo,(int)$before['program_id'],$row['id'],'trainer',$actorId,'update_offer',$before,$after);
                $capacityChanged++;
            }
            $updatePrice=$pdo->prepare('UPDATE shop_variants SET amount_minor=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            foreach($pricePlan as$row){
                if(!$row['changed'])continue;
                $before=$row['_row'];$after=$before;$after['amount_minor']=$row['after'];
                $updatePrice->execute([$row['after'],$row['variant_id']]);
                shopManualCatalogEvent($pdo,$row['product_id'],$row['variant_id'],$actorId,'update_variant',$before,$after,$reason);
                $priceChanged++;
            }
            $pdo->commit();
        }
        foreach($offerPlan as&$row)unset($row['_row']);unset($row);
        foreach($pricePlan as&$row)unset($row['_row']);unset($row);
        return ['applied'=>$apply,'capacity_offers'=>count($offerPlan),'capacity_changed'=>$capacityChanged,
            'price_variants'=>count($pricePlan),'price_changed'=>$priceChanged,'offers'=>$offerPlan,'prices'=>$pricePlan];
    }catch(Throwable $e){if($apply&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
