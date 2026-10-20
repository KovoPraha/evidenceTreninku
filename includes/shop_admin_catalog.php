<?php
declare(strict_types=1);

require_once __DIR__.'/shop_manual_catalog.php';
require_once __DIR__.'/shop_catalog_management.php';
require_once __DIR__.'/shop_attribute.php';

/** @param array<string,mixed> $filters @return array{rows:array,total:int,page:int,pages:int} */
function shopAdminCatalogPage(PDO $pdo,array $filters,bool $variants=false): array
{
    $where=[];$params=[];
    $query=trim((string)($filters['q']??''));
    if ($query!=='') {
        $where[]='(LOWER(p.name) LIKE LOWER(?) OR EXISTS (SELECT 1 FROM shop_variants s WHERE s.product_id=p.id AND LOWER(s.sku) LIKE LOWER(?)))';
        $params[]='%'.$query.'%';$params[]='%'.$query.'%';
    }
    $status=(string)($filters['status']??'current');
    if (!in_array($status,['current','all','active','draft','inactive'],true)) throw new InvalidArgumentException('Neplatný filtr stavu.');
    if ($status==='current') $where[]="p.catalog_status<>'inactive'";
    elseif ($status!=='all') {$where[]='p.catalog_status=?';$params[]=$status;}
    $type=(string)($filters['type']??'');
    if (!in_array($type,['','goods','program','camp','club_event','rental','bookable_service','bookable_rental'],true)) throw new InvalidArgumentException('Neplatný druh nabídky.');
    if ($type!=='') {$where[]='p.offer_type=?';$params[]=$type;}
    if ($variants && ($filters['stock']??'')==='empty') $where[]='v.stock_quantity_decimal IS NOT NULL AND CAST(v.stock_quantity_decimal AS DECIMAL(20,6))<=0';
    if ($variants && ($filters['stock']??'')==='unmanaged') $where[]='v.stock_quantity_decimal IS NULL';
    $from=$variants?'shop_variants v JOIN shop_products p ON p.id=v.product_id':'shop_products p';
    $sqlWhere=$where===[]?'':' WHERE '.implode(' AND ',$where);
    $count=$pdo->prepare('SELECT COUNT(*) FROM '.$from.$sqlWhere);$count->execute($params);$total=(int)$count->fetchColumn();
    $size=$variants?50:25;$pages=max(1,(int)ceil($total/$size));$page=min($pages,max(1,(int)($filters['page']??1)));
    $columns=$variants
        ? 'v.*,p.name AS product_name,p.offer_type,p.catalog_status AS product_status'
        : 'p.*,pub.status AS publication_status,(SELECT COUNT(*) FROM shop_variants v WHERE v.product_id=p.id) AS variant_count,'
          .'(SELECT MIN(amount_minor) FROM shop_variants v WHERE v.product_id=p.id AND (v.visible=1 OR v.visible IS NULL)) AS min_amount_minor,'
          .'(SELECT SUM(CAST(stock_quantity_decimal AS DECIMAL(20,6))) FROM shop_variants v WHERE v.product_id=p.id) AS stock_total,'
          .'(SELECT COUNT(*) FROM shop_variants v WHERE v.product_id=p.id AND v.stock_quantity_decimal IS NULL) AS unmanaged_count,'
          .'(SELECT image_url FROM shop_product_images i WHERE i.product_id=p.id ORDER BY i.sort_order,i.id LIMIT 1) AS image_url';
    if (!$variants) $from.=' LEFT JOIN shop_product_publications pub ON pub.product_id=p.id';
    $statement=$pdo->prepare('SELECT '.$columns.' FROM '.$from.$sqlWhere.' ORDER BY p.name,p.id'.($variants?',v.id':'').' LIMIT '.$size.' OFFSET '.(($page-1)*$size));
    $statement->execute($params);
    return ['rows'=>$statement->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages];
}

function shopAdminVariantName(array $variant): string
{
    try {$values=json_decode((string)($variant['attributes_json']??'{}'),true,64,JSON_THROW_ON_ERROR);} catch (JsonException) {$values=[];}
    if (!is_array($values)||$values===[]) return 'Základní varianta';
    $labels=[];
    foreach ($values as $key=>$value) $labels[]=(string)$key.': '.shopAttributeValueText($value);
    return implode(' · ',$labels);
}

function shopAdminStockText(mixed $stock): string
{
    if ($stock===null || $stock==='') return 'Sklad se nesleduje';
    $value=rtrim(rtrim(number_format((float)$stock,6,',',' '),'0'),',');
    return $value===''?'0':$value;
}

/** Change prices only; stock, imported source IDs and historical order snapshots are untouched.
 * @param array<int|string,array{amount:int,expected:int|null}> $prices
 */
function shopAdminUpdatePrices(PDO $pdo,int $actorId,array $prices,string $reason,bool $confirmed,?int $productId=null): int
{
    shopManualCatalogDecision($actorId,$reason,$confirmed);
    if ($prices===[]||count($prices)>100) throw new InvalidArgumentException('Vyberte 1 až 100 cen.');
    $ids=array_map('intval',array_keys($prices));sort($ids,SORT_NUMERIC);
    if (min($ids)<1||count(array_unique($ids))!==count($ids)) throw new InvalidArgumentException('Neplatný výběr variant.');
    $pdo->beginTransaction();
    try {
        // Lock all parent products first, in stable order shared with variant editing.
        $lookup=$pdo->prepare('SELECT id,product_id FROM shop_variants WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY product_id,id');
        $lookup->execute($ids);$found=$lookup->fetchAll(PDO::FETCH_ASSOC);
        if (count($found)!==count($ids)) throw new ShopManualCatalogException('Některá varianta již neexistuje.');
        foreach (array_unique(array_column($found,'product_id')) as $id) {
            if ($productId!==null && (int)$id!==$productId) throw new ShopManualCatalogException('Varianta nepatří k vybranému produktu.');
            shopManualCatalogLockProduct($pdo,(int)$id);
        }
        $changed=0;
        foreach ($ids as $id) {
            $before=shopManualCatalogLockVariant($pdo,$id);$input=$prices[$id];$amount=$input['amount'];
            if (!is_int($amount)||$amount<0||$amount>1000000000) throw new InvalidArgumentException('Neplatná cena varianty.');
            if (($before['amount_minor']===null?null:(int)$before['amount_minor'])!==$input['expected']) throw new ShopManualCatalogException('Cena se mezitím změnila. Obnovte stránku a zkontrolujte ji.');
            if ($before['compare_at_amount_minor']!==null && (int)$before['compare_at_amount_minor']<$amount) throw new InvalidArgumentException('Nová cena převyšuje původní cenu. Upravte obě ceny v detailu varianty.');
            if ($before['amount_minor']!==null && (int)$before['amount_minor']===$amount) continue;
            $pdo->prepare("UPDATE shop_variants SET amount_minor=?,price_mode='fixed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$amount,$id]);
            shopManualCatalogEvent($pdo,(int)$before['product_id'],$id,$actorId,'update_price',$before,shopManualCatalogLockVariant($pdo,$id),$reason);
            $changed++;
        }
        $pdo->commit();return $changed;
    } catch (Throwable $exception) {if ($pdo->inTransaction()) $pdo->rollBack();throw $exception;}
}

/** Reuse the canonical movement writer after checking the displayed balance under its lock. */
function shopAdminAdjustStock(PDO $pdo,int $actorId,int $variantId,int $productId,string $stock,string $expected,string $reason,bool $confirmed): array
{
    // The canonical writer performs its own transaction. Its optional expected
    // value closes the race between reading this screen and a real checkout.
    $lookup=$pdo->prepare('SELECT product_id FROM shop_variants WHERE id=?');$lookup->execute([$variantId]);
    if ((int)$lookup->fetchColumn()!==$productId) throw new ShopManualCatalogException('Varianta nepatří k vybranému produktu.');
    return shopCatalogAdjustStock($pdo,$actorId,$variantId,$stock,$reason,$confirmed,['stock'=>$expected==='__NULL__'?null:$expected]);
}
