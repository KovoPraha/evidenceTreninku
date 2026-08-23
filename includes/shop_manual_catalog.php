<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_catalog_origin.php';
require_once __DIR__ . '/shop_catalog_publication.php';
require_once __DIR__ . '/shop_coupon.php';

final class ShopManualCatalogException extends RuntimeException {}

function shopManualCatalogCreationReason(string $reason): string
{
    $reason = trim($reason);
    return $reason !== '' ? $reason : 'Ruční založení produktu do katalogu.';
}

/** @return list<array<string,mixed>> */
function shopManualCatalogProducts(PDO $pdo): array
{
    return $pdo->query(
        'SELECT p.id,p.name,p.offer_type,p.origin,p.catalog_status,p.visibility,'
        . 'COUNT(v.id) AS variant_count,MIN(v.amount_minor) AS min_amount_minor,'
        . 'MAX(v.amount_minor) AS max_amount_minor,MIN(v.currency) AS currency '
        . 'FROM shop_products p LEFT JOIN shop_variants v ON v.product_id=p.id '
        . 'GROUP BY p.id,p.name,p.offer_type,p.origin,p.catalog_status,p.visibility '
        . 'ORDER BY CASE p.origin WHEN \'manual\' THEN 0 ELSE 1 END,p.name,p.id'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{product:array<string,mixed>,variants:list<array<string,mixed>>,images:list<array<string,mixed>>,events:list<array<string,mixed>>} */
function shopManualCatalogDetail(PDO $pdo, int $productId): array
{
    if ($productId < 1) throw new InvalidArgumentException('Produkt nebyl vybrán.');
    $product = $pdo->prepare(
        'SELECT p.*,pub.status AS publication_status,pub.public_name,pub.public_summary '
        . 'FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=?'
    );
    $product->execute([$productId]);
    $product = $product->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new ShopManualCatalogException('Produkt nebyl nalezen.');
    $variants = $pdo->prepare('SELECT * FROM shop_variants WHERE product_id=? ORDER BY id');
    $variants->execute([$productId]);
    $images = $pdo->prepare('SELECT id,product_id,image_url,sort_order FROM shop_product_images WHERE product_id=? ORDER BY sort_order,id');
    $images->execute([$productId]);
    $events = $pdo->prepare(
        'SELECT e.*,t.jmeno AS actor_name FROM shop_catalog_admin_events e '
        . 'LEFT JOIN treneri t ON e.actor_type=\'trainer\' AND t.id=e.actor_id '
        . 'WHERE e.product_id=? ORDER BY e.id DESC LIMIT 100'
    );
    $events->execute([$productId]);
    return [
        'product'=>$product,
        'variants'=>$variants->fetchAll(PDO::FETCH_ASSOC),
        'images'=>$images->fetchAll(PDO::FETCH_ASSOC),
        'events'=>$events->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/** @param array<string,mixed> $product @param array<string,mixed> $variant @return array<string,mixed> */
function shopManualCatalogCreate(PDO $pdo, int $actorId, array $product, array $variant, string $reason, bool $confirmed): array
{
    $reason = shopManualCatalogCreationReason($reason);
    $pdo->beginTransaction();
    try {
        $result = shopManualCatalogCreateInTransaction($pdo,$actorId,$product,$variant,$reason,$confirmed);
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException) throw $exception;
        throw new ShopManualCatalogException('Ruční produkt se nepodařilo založit bez částečného zápisu.',0,$exception);
    }
}

/** @param array<string,mixed> $product @param array<string,mixed> $variant @return array<string,mixed> */
function shopManualCatalogCreateInTransaction(PDO $pdo, int $actorId, array $product, array $variant, string $reason, bool $confirmed): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Založení produktu vyžaduje otevřenou transakci.');
    shopManualCatalogDecision($actorId,$reason,$confirmed);
    $product = shopManualCatalogProductInput($product);
    $variant = shopManualCatalogVariantInput($variant);
    $externalKey = shopCatalogManualExternalProductKey();
    shopCatalogAssertManualExternalProductKey($externalKey);
    shopCatalogAssertManualSku($variant['sku']);
    shopCatalogAssertProductOrigin(ShopCatalogOrigin::MANUAL,null,null,$actorId);
    shopCatalogAssertVariantOrigin(ShopCatalogOrigin::MANUAL,null,$actorId,ShopCatalogOrigin::MANUAL);
    shopManualCatalogAssertSkuAvailable($pdo,$variant['sku']);
    $pdo->prepare(
        'INSERT INTO shop_products '
        . '(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,'
        . 'name,short_description,offer_type,visibility,item_type,catalog_status) '
        . "VALUES(NULL,NULL,'manual',?,?,?,?,?,?,?,'draft')"
    )->execute([
        $actorId,$externalKey,$product['name'],$product['short_description'],
        $product['offer_type'],$product['visibility'],$product['item_type'],
    ]);
    $productId = (int)$pdo->lastInsertId();
    $variantId = shopManualCatalogInsertVariant($pdo,$actorId,$productId,$variant,'draft');
    $after = ['product'=>shopManualCatalogLockProduct($pdo,$productId),'variant'=>shopManualCatalogLockVariant($pdo,$variantId)];
    shopManualCatalogEvent($pdo,$productId,$variantId,$actorId,'create_product',null,$after,$reason);
    return ['product_id'=>$productId,'variant_id'=>$variantId,'external_product_key'=>$externalKey];
}

/** @param array<string,mixed> $variant @return array{id:int,product_id:int} */
function shopManualCatalogAddVariant(PDO $pdo, int $actorId, int $productId, array $variant, string $reason, bool $confirmed): array
{
    $pdo->beginTransaction();
    try {
        shopManualCatalogDecision($actorId,$reason,$confirmed);
        $product = shopManualCatalogLockProduct($pdo,$productId);
        if ((string)$product['origin'] !== ShopCatalogOrigin::MANUAL) {
            throw new ShopManualCatalogException('Novou ruční variantu lze přidat jen k ručnímu produktu.');
        }
        $variant = shopManualCatalogVariantInput($variant);
        shopCatalogAssertManualSku($variant['sku']);
        shopManualCatalogAssertSkuAvailable($pdo,$variant['sku']);
        $variantStatus = (string)$product['catalog_status'] === 'active' ? 'active' : 'draft';
        $variantId = shopManualCatalogInsertVariant($pdo,$actorId,$productId,$variant,$variantStatus);
        $after = shopManualCatalogLockVariant($pdo,$variantId);
        shopManualCatalogEvent($pdo,$productId,$variantId,$actorId,'add_variant',null,$after,$reason);
        $pdo->commit();
        return ['id'=>$variantId,'product_id'=>$productId];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException) throw $exception;
        throw new ShopManualCatalogException('Variantu se nepodařilo přidat bez částečného zápisu.',0,$exception);
    }
}

/** @param array<string,mixed> $input @return array{id:int,changed:bool} */
function shopManualCatalogUpdateProduct(PDO $pdo, int $actorId, int $productId, array $input, string $reason, bool $confirmed): array
{
    $pdo->beginTransaction();
    try {
        shopManualCatalogDecision($actorId,$reason,$confirmed);
        $before = shopManualCatalogLockProduct($pdo,$productId);
        $values = shopManualCatalogProductInput($input);
        $after = $before;
        foreach ($values as $key=>$value) $after[$key]=$value;
        $changed = false;
        foreach (array_keys($values) as $key) if ((string)($before[$key]??'') !== (string)($after[$key]??'')) $changed=true;
        if ($changed) {
            $pdo->prepare(
                'UPDATE shop_products SET name=?,short_description=?,offer_type=?,visibility=?,item_type=?,'
                . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([
                $values['name'],$values['short_description'],$values['offer_type'],$values['visibility'],
                $values['item_type'],$productId,
            ]);
            $after = shopManualCatalogLockProduct($pdo,$productId);
            shopManualCatalogEvent($pdo,$productId,null,$actorId,'update_product',$before,$after,$reason);
        }
        $pdo->commit();
        return ['id'=>$productId,'changed'=>$changed];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException) throw $exception;
        throw new ShopManualCatalogException('Produkt se nepodařilo upravit bez částečného zápisu.',0,$exception);
    }
}

/** @param array<string,mixed> $input @return array{id:int,product_id:int,changed:bool} */
function shopManualCatalogUpdateVariant(PDO $pdo, int $actorId, int $variantId, array $input, string $reason, bool $confirmed): array
{
    $pdo->beginTransaction();
    try {
        shopManualCatalogDecision($actorId,$reason,$confirmed);
        $productLookup=$pdo->prepare('SELECT product_id FROM shop_variants WHERE id=?');
        $productLookup->execute([$variantId]);$productId=(int)$productLookup->fetchColumn();
        if($productId<1)throw new ShopManualCatalogException('Varianta nebyla nalezena.');
        shopManualCatalogLockProduct($pdo,$productId);
        $before = shopManualCatalogLockVariant($pdo,$variantId);
        if((int)$before['product_id']!==$productId)throw new ShopManualCatalogException('Varianta změnila produkt během úpravy.');
        $values = shopManualCatalogVariantInput($input);
        if ((string)$before['origin'] === ShopCatalogOrigin::MANUAL) {
            shopCatalogAssertManualSku($values['sku']);
        } elseif ($values['sku'] !== (string)$before['sku']) {
            throw new ShopManualCatalogException('SKU importované varianty se kvůli budoucím importům nemění.');
        }
        shopManualCatalogAssertSkuAvailable($pdo,$values['sku'],$variantId);
        // R11 keeps inventory corrections on their own audited movement path.
        if(shopManualCatalogComparable($values['stock_quantity_decimal'])!==shopManualCatalogComparable($before['stock_quantity_decimal']))throw new ShopManualCatalogException('Sklad upravte samostatnou auditovanou korekcí, aby vždy vznikl pohyb.');
        $values['stock_quantity_decimal']=$before['stock_quantity_decimal'];
        $changed = false;
        foreach ($values as $key=>$value) {
            if (shopManualCatalogComparable($before[$key]??null) !== shopManualCatalogComparable($value)) $changed=true;
        }
        if ($changed) {
            $pdo->prepare(
                'UPDATE shop_variants SET sku=?,ean=?,attributes_json=?,price_mode=\'fixed\',amount_minor=?,'
                . 'compare_at_amount_minor=?,currency=\'CZK\',includes_vat=?,vat_rate_basis_points=?,'
                . 'stock_quantity_decimal=?,unit_code=?,visible=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([
                $values['sku'],$values['ean'],$values['attributes_json'],$values['amount_minor'],
                $values['compare_at_amount_minor'],$values['includes_vat'],$values['vat_rate_basis_points'],
                $values['stock_quantity_decimal'],$values['unit_code'],$values['visible'],$variantId,
            ]);
            $after = shopManualCatalogLockVariant($pdo,$variantId);
            shopManualCatalogEvent($pdo,(int)$before['product_id'],$variantId,$actorId,'update_variant',$before,$after,$reason);
        }
        $pdo->commit();
        return ['id'=>$variantId,'product_id'=>(int)$before['product_id'],'changed'=>$changed];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException) throw $exception;
        throw new ShopManualCatalogException('Variantu se nepodařilo upravit bez částečného zápisu.',0,$exception);
    }
}

/** @return array{id:int,changed:bool} */
function shopManualCatalogArchive(PDO $pdo, int $actorId, int $productId, string $reason, bool $confirmed): array
{
    $pdo->beginTransaction();
    try {
        shopManualCatalogDecision($actorId,$reason,$confirmed);
        $before = shopManualCatalogDetailForAudit($pdo,$productId,true);
        if ((string)$before['product']['catalog_status'] === 'inactive') {
            $pdo->commit();
            return ['id'=>$productId,'changed'=>false];
        }
        $publication = $pdo->prepare('SELECT * FROM shop_product_publications WHERE product_id=?');
        $publication->execute([$productId]);
        $publication = $publication->fetch(PDO::FETCH_ASSOC);
        if ($publication) {
            $pdo->prepare(
                "UPDATE shop_product_publications SET status='inactive',decision_note=?,activated_by_trainer_id=?,"
                . 'deactivated_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE product_id=?'
            )->execute([$reason,$actorId,$productId]);
            shopCatalogPublicationEvent(
                $pdo,$productId,$actorId,'deactivate',(string)$publication['status'],'inactive',
                (string)$publication['public_name'],(string)$publication['public_summary'],$reason
            );
        }
        $pdo->prepare("UPDATE shop_products SET catalog_status='inactive',updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$productId]);
        $pdo->prepare("UPDATE shop_variants SET catalog_status='inactive',updated_at=CURRENT_TIMESTAMP WHERE product_id=?")
            ->execute([$productId]);
        $after = shopManualCatalogDetailForAudit($pdo,$productId,false);
        shopManualCatalogEvent($pdo,$productId,null,$actorId,'archive_product',$before,$after,$reason);
        $pdo->commit();
        return ['id'=>$productId,'changed'=>true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException) throw $exception;
        throw new ShopManualCatalogException('Produkt se nepodařilo archivovat bez částečného zápisu.',0,$exception);
    }
}

/** @param array<string,mixed> $input @return array<string,mixed> */
function shopManualCatalogProductInput(array $input): array
{
    $name = shopManualCatalogText((string)($input['name']??''),255,'Název produktu');
    $summary = shopManualCatalogText((string)($input['short_description']??''),4000,'Krátký popis',false);
    $offerType = (string)($input['offer_type']??'');
    if (!in_array($offerType,['goods','program'],true)) throw new InvalidArgumentException('Ruční produkt musí být zboží nebo program.');
    $itemType = (string)($input['item_type']??'');
    if (!in_array($itemType,['product','service'],true)) throw new InvalidArgumentException('Typ položky musí být product nebo service.');
    $visibility = mb_strtolower(trim((string)($input['visibility']??'')),'UTF-8');
    if ($visibility === '' || in_array($visibility,['hidden','private','false','no','0'],true)) {
        throw new InvalidArgumentException('Ruční produkt nesmí být označený jako skrytý.');
    }
    return [
        'name'=>$name,'short_description'=>$summary !== '' ? $summary : null,
        'offer_type'=>$offerType,'item_type'=>$itemType,'visibility'=>$visibility,
    ];
}

/** @param array<string,mixed> $input @return array<string,mixed> */
function shopManualCatalogVariantInput(array $input): array
{
    $sku = strtoupper(trim((string)($input['sku']??'')));
    if ($sku === '' || strlen($sku) > 64) throw new InvalidArgumentException('SKU je povinné a smí mít nejvýše 64 znaků.');
    $attributes = trim((string)($input['attributes_json']??'{}'));
    try { $decoded = json_decode($attributes,true,64,JSON_THROW_ON_ERROR); }
    catch (JsonException $exception) { throw new InvalidArgumentException('Parametry se nepodařilo zpracovat. Odeberte problematický řádek a přidejte jej znovu.',0,$exception); }
    if (!is_array($decoded) || (!str_starts_with(ltrim($attributes),'{'))) {
        throw new InvalidArgumentException('Parametry mají nepodporovaný tvar. Zadejte každý parametr jako samostatný název a hodnotu.');
    }
    $attributes = json_encode((object)$decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $amount = $input['amount_minor']??null;
    if (!is_int($amount) || $amount < 0 || $amount > 1000000000) throw new InvalidArgumentException('Cena není v podporovaném rozsahu.');
    $compare = $input['compare_at_amount_minor']??null;
    if ($compare !== null && (!is_int($compare) || $compare < $amount || $compare > 1000000000)) {
        throw new InvalidArgumentException('Původní cena musí být prázdná nebo nejméně rovná aktuální ceně.');
    }
    $includesVat = $input['includes_vat']??null;
    if ($includesVat !== null && !in_array($includesVat,[0,1],true)) throw new InvalidArgumentException('Příznak DPH není platný.');
    $vatRate = $input['vat_rate_basis_points']??null;
    if ($vatRate !== null && (!is_int($vatRate) || $vatRate < 0 || $vatRate > 10000)) throw new InvalidArgumentException('Sazba DPH není platná.');
    $ean = shopManualCatalogOptionalToken((string)($input['ean']??''),64,'EAN');
    $unit = shopManualCatalogOptionalToken((string)($input['unit_code']??''),32,'Jednotka');
    return [
        'sku'=>$sku,'ean'=>$ean,'attributes_json'=>$attributes,'price_mode'=>'fixed',
        'amount_minor'=>$amount,'compare_at_amount_minor'=>$compare,'currency'=>'CZK',
        'includes_vat'=>$includesVat,'vat_rate_basis_points'=>$vatRate,
        'stock_quantity_decimal'=>shopManualCatalogStock((string)($input['stock_quantity_decimal']??'')),
        'unit_code'=>$unit,'visible'=>!empty($input['visible']) ? 1 : 0,
    ];
}

function shopManualCatalogText(string $value, int $max, string $label, bool $required = true): string
{
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value,'UTF-8') > $max || preg_match('/[<>]/u',$value)===1) {
        throw new InvalidArgumentException($label . ' musí být prostý text do ' . $max . ' znaků.');
    }
    return $value;
}

function shopManualCatalogOptionalToken(string $value, int $max, string $label): ?string
{
    $value = trim($value);
    if ($value === '') return null;
    if (strlen($value)>$max || preg_match('/^[A-Za-z0-9_.\/-]+$/D',$value)!==1) {
        throw new InvalidArgumentException($label . ' nemá podporovaný formát.');
    }
    return $value;
}

function shopManualCatalogStock(string $value): ?string
{
    $value = trim(str_replace(',','.',$value));
    if ($value === '') return null;
    if (preg_match('/^[0-9]{1,9}(?:\.[0-9]{1,6})?$/D',$value)!==1) {
        throw new InvalidArgumentException('Sklad musí být nezáporné číslo s nejvýše šesti desetinnými místy.');
    }
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    return (string)(int)$whole . '.' . str_pad($fraction,6,'0');
}

function shopManualCatalogDecision(int $actorId, string &$reason, bool $confirmed): void
{
    $reason = trim($reason);
    if ($actorId<1 || !$confirmed || $reason==='' || mb_strlen($reason,'UTF-8')>1000) {
        throw new InvalidArgumentException('Změna katalogu vyžaduje administrátora, důvod a výslovné potvrzení.');
    }
}

function shopManualCatalogAssertSkuAvailable(PDO $pdo, string $sku, ?int $exceptVariantId = null): void
{
    $sql='SELECT id FROM shop_variants WHERE sku=?';
    $params=[$sku];
    if ($exceptVariantId!==null) {$sql.=' AND id<>?';$params[]=$exceptVariantId;}
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute($params);
    if ($statement->fetchColumn()!==false) throw new ShopManualCatalogException('SKU už v katalogu existuje: ' . $sku);
}

/** @param array<string,mixed> $variant */
function shopManualCatalogInsertVariant(
    PDO $pdo,
    int $actorId,
    int $productId,
    array $variant,
    string $catalogStatus
): int
{
    if (!in_array($catalogStatus,['draft','active'],true)) throw new LogicException('Nová varianta nemá platný stav.');
    try {
        $pdo->prepare(
            'INSERT INTO shop_variants '
            . '(product_id,source_candidate_id,origin,created_by_trainer_id,sku,ean,attributes_json,price_mode,'
            . 'amount_minor,compare_at_amount_minor,currency,includes_vat,vat_rate_basis_points,'
            . 'stock_quantity_decimal,unit_code,visible,catalog_status) '
            . "VALUES(?,NULL,'manual',?,?,?,?,'fixed',?,?,'CZK',?,?,?,?,?,?)"
        )->execute([
            $productId,$actorId,$variant['sku'],$variant['ean'],$variant['attributes_json'],
            $variant['amount_minor'],$variant['compare_at_amount_minor'],$variant['includes_vat'],
            $variant['vat_rate_basis_points'],$variant['stock_quantity_decimal'],$variant['unit_code'],$variant['visible'],
            $catalogStatus,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new ShopManualCatalogException('SKU už v katalogu existuje: ' . $variant['sku'],0,$exception);
        }
        throw $exception;
    }
    return (int)$pdo->lastInsertId();
}

/** @return array<string,mixed> */
function shopManualCatalogLockProduct(PDO $pdo, int $productId): array
{
    $sql='SELECT * FROM shop_products WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$productId]);$row=$statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new ShopManualCatalogException('Produkt nebyl nalezen.');
    return $row;
}

/** @return array<string,mixed> */
function shopManualCatalogLockVariant(PDO $pdo, int $variantId): array
{
    $sql='SELECT * FROM shop_variants WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$variantId]);$row=$statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new ShopManualCatalogException('Varianta nebyla nalezena.');
    return $row;
}

/** @return array<string,mixed> */
function shopManualCatalogDetailForAudit(PDO $pdo, int $productId, bool $lock): array
{
    $product = $lock ? shopManualCatalogLockProduct($pdo,$productId) : null;
    if ($product===null) {
        $statement=$pdo->prepare('SELECT * FROM shop_products WHERE id=?');$statement->execute([$productId]);
        $product=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$product)throw new ShopManualCatalogException('Produkt nebyl nalezen.');
    }
    $variants=$pdo->prepare('SELECT * FROM shop_variants WHERE product_id=? ORDER BY id');$variants->execute([$productId]);
    return ['product'=>$product,'variants'=>$variants->fetchAll(PDO::FETCH_ASSOC)];
}

function shopManualCatalogInventoryMovement(PDO $pdo, int $variantId, int $actorId, mixed $before, mixed $after, string $reason): void
{
    if ($after===null) throw new LogicException('Auditní skladový pohyb vyžaduje cílový stav.');
    $beforeMicros=shopManualCatalogStockMicros($before===null?'0':(string)$before);
    $afterMicros=shopManualCatalogStockMicros((string)$after);
    $delta=$afterMicros-$beforeMicros;
    $pdo->prepare(
        "INSERT INTO shop_inventory_movements(variant_id,order_id,order_item_id,movement_type,actor_type,"
        . "actor_id,reason,quantity_delta_decimal,stock_after_decimal) VALUES(?,NULL,NULL,'manual_adjustment',"
        . "'trainer',?,?,?,?)"
    )->execute([$variantId,$actorId,$reason,shopManualCatalogMicrosDecimal($delta),shopManualCatalogMicrosDecimal($afterMicros)]);
}

function shopManualCatalogStockMicros(string $value): int
{
    $normalized=shopManualCatalogStock($value);
    if($normalized===null)return 0;
    [$whole,$fraction]=explode('.',$normalized,2);
    return (int)$whole*1000000+(int)$fraction;
}

function shopManualCatalogMicrosDecimal(int $micros): string
{
    $sign=$micros<0?'-':'';$micros=abs($micros);
    return $sign . intdiv($micros,1000000) . '.' . str_pad((string)($micros%1000000),6,'0',STR_PAD_LEFT);
}

function shopManualCatalogComparable(mixed $value): string
{
    return $value===null?'__NULL__':(string)$value;
}

/** @param array<string,mixed>|null $before @param array<string,mixed> $after */
function shopManualCatalogEvent(PDO $pdo, int $productId, ?int $variantId, int $actorId, string $action, ?array $before, array $after, string $reason): void
{
    if (!$pdo->inTransaction() || $productId<1 || $actorId<1) throw new LogicException('Audit katalogu vyžaduje transakci, produkt a správce.');
    $pdo->prepare(
        'INSERT INTO shop_catalog_admin_events(product_id,variant_id,actor_type,actor_id,action,before_json,after_json,reason) '
        . "VALUES(?,?,'trainer',?,?,?,?,?)"
    )->execute([
        $productId,$variantId,$actorId,$action,
        $before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$reason,
    ]);
}
