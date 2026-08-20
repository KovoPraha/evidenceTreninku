<?php
declare(strict_types=1);

require_once __DIR__.'/shop_manual_catalog.php';
require_once __DIR__.'/shop_category_admin.php';

final class ShopCatalogManagementException extends RuntimeException{}

/** @return list<array<string,mixed>> */
function shopCatalogManagementVariants(PDO$pdo):array
{
    return$pdo->query('SELECT v.id,v.product_id,v.sku,v.stock_quantity_decimal,p.name AS product_name FROM shop_variants v JOIN shop_products p ON p.id=v.product_id ORDER BY p.name,v.sku,v.id')->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function shopCatalogManagementProducts(PDO$pdo):array
{
    $rows=$pdo->query(
        'SELECT p.id,p.name,p.short_description,p.offer_type,p.origin,p.catalog_status,p.visibility,p.sort_order,'
        .'p.external_product_key,pub.status AS publication_status,pub.public_name,pub.public_summary,'
        .'COUNT(v.id) AS variant_count,SUM(CASE WHEN v.amount_minor IS NOT NULL THEN 1 ELSE 0 END) AS priced_variant_count,'
        .'MIN(v.amount_minor) AS min_amount_minor,MAX(v.amount_minor) AS max_amount_minor,MIN(v.currency) AS currency,'
        .'(SELECT COUNT(*) FROM shop_product_images i WHERE i.product_id=p.id) AS image_count,'
        .'(SELECT COUNT(*) FROM shop_product_categories c WHERE c.product_id=p.id) AS category_count '
        .'FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id '
        .'LEFT JOIN shop_variants v ON v.product_id=p.id '
        .'GROUP BY p.id,p.name,p.short_description,p.offer_type,p.origin,p.catalog_status,p.visibility,p.sort_order,'
        .'p.external_product_key,pub.status,pub.public_name,pub.public_summary'
    )->fetchAll(PDO::FETCH_ASSOC);
    if($rows===[])return[];$ids=array_map(static fn(array$row):int=>(int)$row['id'],$rows);$marks=implode(',',array_fill(0,count($ids),'?'));
    $categories=$pdo->prepare("SELECT product_id,category_path FROM shop_product_categories WHERE product_id IN ($marks) ORDER BY product_id,is_default DESC,sort_order,id");$categories->execute($ids);$byProduct=[];
    foreach($categories->fetchAll(PDO::FETCH_ASSOC)as$category)$byProduct[(int)$category['product_id']][]=(string)$category['category_path'];
    foreach($rows as&$row){$row['categories']=$byProduct[(int)$row['id']]??[];$row['program_saleable']=null;$row['program_sale_reason']=null;if((string)$row['offer_type']==='program'&&(string)$row['catalog_status']==='active'){$state=clubProgramProductSaleState($pdo,(int)$row['id']);$row['program_saleable']=$state['saleable'];$row['program_sale_reason']=$state['reason'];}}
    unset($row);return$rows;
}

/** @param list<array<string,mixed>> $rows @return array<string,int> */
function shopCatalogManagementOverview(array$rows):array
{
    $result=['total'=>count($rows),'draft'=>0,'active'=>0,'inactive'=>0,'missing_image'=>0,'missing_category'=>0,'missing_price'=>0];
    foreach($rows as$row){$status=(string)$row['catalog_status'];if(isset($result[$status]))$result[$status]++;if((int)$row['image_count']===0)$result['missing_image']++;if((int)$row['category_count']===0)$result['missing_category']++;if((int)$row['priced_variant_count']===0)$result['missing_price']++;}
    return$result;
}

/** @param list<array<string,mixed>> $rows @param array<string,mixed> $filters @return list<array<string,mixed>> */
function shopCatalogManagementFilter(array$rows,array$filters):array
{
    $q=mb_strtolower(trim((string)($filters['q']??'')),'UTF-8');$status=(string)($filters['status']??'');$type=(string)($filters['offer_type']??'');$origin=(string)($filters['origin']??'');$category=trim((string)($filters['category']??''));$sort=(string)($filters['sort']??'order');
    $allowedStatus=['','draft','active','inactive'];$allowedType=['','goods','program','camp','rental','bookable_service','bookable_rental'];$allowedOrigin=['','import','manual'];$allowedSort=['order','name','price'];
    if(!in_array($status,$allowedStatus,true)||!in_array($type,$allowedType,true)||!in_array($origin,$allowedOrigin,true)||!in_array($sort,$allowedSort,true))throw new InvalidArgumentException('Filtr katalogu není podporovaný.');
    $rows=array_values(array_filter($rows,static function(array$row)use($q,$status,$type,$origin,$category):bool{
        if($status!==''&&(string)$row['catalog_status']!==$status)return false;if($type!==''&&(string)$row['offer_type']!==$type)return false;if($origin!==''&&(string)$row['origin']!==$origin)return false;if($category!==''&&!in_array($category,$row['categories'],true))return false;
        if($q==='')return true;$haystack=mb_strtolower(implode(' ',[(string)$row['public_name'],(string)$row['public_summary'],(string)$row['name'],(string)$row['external_product_key']]),'UTF-8');return str_contains($haystack,$q);
    }));
    usort($rows,static function(array$a,array$b)use($sort):int{
        if($sort==='price')return[(int)($a['min_amount_minor']??PHP_INT_MAX),mb_strtolower((string)($a['public_name']?:$a['name']),'UTF-8'),(int)$a['id']]<=>[(int)($b['min_amount_minor']??PHP_INT_MAX),mb_strtolower((string)($b['public_name']?:$b['name']),'UTF-8'),(int)$b['id']];
        if($sort==='name')return[mb_strtolower((string)($a['public_name']?:$a['name']),'UTF-8'),(int)$a['id']]<=>[mb_strtolower((string)($b['public_name']?:$b['name']),'UTF-8'),(int)$b['id']];
        return[(int)$a['sort_order'],mb_strtolower((string)($a['public_name']?:$a['name']),'UTF-8'),(int)$a['id']]<=>[(int)$b['sort_order'],mb_strtolower((string)($b['public_name']?:$b['name']),'UTF-8'),(int)$b['id']];
    });return$rows;
}

/** @param mixed $raw @return list<int> */
function shopCatalogManagementIds(mixed$raw):array
{
    if(!is_array($raw))throw new InvalidArgumentException('Vyberte alespoň jeden produkt.');$ids=[];foreach($raw as$value){$id=filter_var($value,FILTER_VALIDATE_INT);if($id===false||(int)$id<1)throw new InvalidArgumentException('Výběr obsahuje neplatný produkt.');$ids[(int)$id]=true;}
    $result=array_keys($ids);sort($result,SORT_NUMERIC);if($result===[]||count($result)>500)throw new InvalidArgumentException('Vyberte 1 až 500 produktů.');return$result;
}

/** @param list<int> $ids @param array<int|string,mixed> $names @param array<int|string,mixed> $summaries */
function shopCatalogBulkActivate(PDO$pdo,int$actorId,array$ids,array$names,array$summaries,string$reason,bool$confirmed):int
{
    shopManualCatalogDecision($actorId,$reason,$confirmed);$pdo->beginTransaction();try{$changed=0;foreach($ids as$id){$name=(string)($names[$id]??$names[(string)$id]??'');$summary=(string)($summaries[$id]??$summaries[(string)$id]??'');$result=shopCatalogPublicationActivateInTransaction($pdo,$id,$actorId,$name,$summary,$reason,true);if($result['changed'])$changed++;}$pdo->commit();return$changed;}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopCatalogPublicationException)throw$exception;throw new ShopCatalogManagementException('Hromadná aktivace selhala bez částečné změny.',0,$exception);}
}

/** @param list<int> $ids */
function shopCatalogBulkDeactivate(PDO$pdo,int$actorId,array$ids,string$reason,bool$confirmed):int
{
    shopManualCatalogDecision($actorId,$reason,$confirmed);$pdo->beginTransaction();try{$changed=0;foreach($ids as$id)if(shopCatalogPublicationDeactivateInTransaction($pdo,$id,$actorId,$reason)['changed'])$changed++;$pdo->commit();return$changed;}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopCatalogPublicationException)throw$exception;throw new ShopCatalogManagementException('Hromadná deaktivace selhala bez částečné změny.',0,$exception);}
}

/** @param list<int> $ids */
function shopCatalogBulkAssignCategory(PDO$pdo,int$actorId,array$ids,string$path,string$reason,bool$confirmed):int
{
    shopManualCatalogDecision($actorId,$reason,$confirmed);$path=shopCategoryPath($path);$nodes=shopCategoryNodes($pdo);if(!isset($nodes[$path]))throw new InvalidArgumentException('Vybraná kategorie v katalogu neexistuje.');
    $pdo->beginTransaction();try{foreach($ids as$id){shopManualCatalogLockProduct($pdo,$id);$beforeStatement=$pdo->prepare('SELECT category_path,is_default,sort_order FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id');$beforeStatement->execute([$id]);$before=$beforeStatement->fetchAll(PDO::FETCH_ASSOC);$pdo->prepare('DELETE FROM shop_product_categories WHERE product_id=?')->execute([$id]);$pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,1,0)')->execute([$id,$path]);shopManualCatalogEvent($pdo,$id,null,$actorId,'bulk_assign_category',['categories'=>$before],['categories'=>[['category_path'=>$path,'is_default'=>1,'sort_order'=>0]]],$reason);}$pdo->commit();return count($ids);}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopManualCatalogException)throw$exception;throw new ShopCatalogManagementException('Hromadné přeřazení selhalo bez částečné změny.',0,$exception);}
}

/** @param list<int> $ids @param array<int|string,mixed> $orders */
function shopCatalogBulkSetOrder(PDO$pdo,int$actorId,array$ids,array$orders,string$reason,bool$confirmed):int
{
    shopManualCatalogDecision($actorId,$reason,$confirmed);$pdo->beginTransaction();try{$changed=0;foreach($ids as$id){$product=shopManualCatalogLockProduct($pdo,$id);$order=filter_var($orders[$id]??$orders[(string)$id]??null,FILTER_VALIDATE_INT);if($order===false||(int)$order< -100000||(int)$order>100000)throw new InvalidArgumentException('Pořadí musí být celé číslo mezi -100000 a 100000.');if((int)$product['sort_order']===(int)$order)continue;$pdo->prepare('UPDATE shop_products SET sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$order,$id]);$after=shopManualCatalogLockProduct($pdo,$id);shopManualCatalogEvent($pdo,$id,null,$actorId,'set_sort_order',$product,$after,$reason);$changed++;}$pdo->commit();return$changed;}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopManualCatalogException)throw$exception;throw new ShopCatalogManagementException('Uložení pořadí selhalo bez částečné změny.',0,$exception);}
}

/** @return array{id:int,product_id:int,changed:bool} */
function shopCatalogAdjustStock(PDO$pdo,int$actorId,int$variantId,string$newStock,string$reason,bool$confirmed):array
{
    shopManualCatalogDecision($actorId,$reason,$confirmed);$normalized=shopManualCatalogStock($newStock);if($normalized===null)throw new InvalidArgumentException('Cílový sklad je povinný.');$pdo->beginTransaction();try{$before=shopManualCatalogLockVariant($pdo,$variantId);$productId=(int)$before['product_id'];shopManualCatalogLockProduct($pdo,$productId);$changed=shopManualCatalogComparable($before['stock_quantity_decimal'])!==shopManualCatalogComparable($normalized);if($changed){$pdo->prepare('UPDATE shop_variants SET stock_quantity_decimal=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$normalized,$variantId]);shopManualCatalogInventoryMovement($pdo,$variantId,$actorId,$before['stock_quantity_decimal'],$normalized,$reason);$after=shopManualCatalogLockVariant($pdo,$variantId);shopManualCatalogEvent($pdo,$productId,$variantId,$actorId,'adjust_stock',$before,$after,$reason);}$pdo->commit();return['id'=>$variantId,'product_id'=>$productId,'changed'=>$changed];}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ShopManualCatalogException)throw$exception;throw new ShopCatalogManagementException('Korekce skladu selhala bez částečné změny.',0,$exception);}
}
