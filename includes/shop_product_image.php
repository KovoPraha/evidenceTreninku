<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_manual_catalog.php';

final class ShopProductImageException extends RuntimeException {}

/**
 * Decode and re-encode a catalog image. The original filename, metadata and
 * EXIF data never cross the public-storage boundary.
 *
 * @return array{image_url:string,sha256_hex:string,byte_size:int,mime_type:string,width_px:int,height_px:int}
 */
function shopProductImageStoreFile(string $source, bool $uploaded = true, ?string $applicationRoot = null): array
{
    if (!is_file($source) || ($uploaded && !is_uploaded_file($source))) {
        throw new ShopProductImageException('Nahraný obrázek nebyl nalezen.');
    }
    $size = filesize($source);
    if (!is_int($size) || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new ShopProductImageException('Obrázek musí mít nejvýše 5 MB.');
    }
    $bytes = file_get_contents($source);
    if (!is_string($bytes)) {
        throw new ShopProductImageException('Obrázek nelze bezpečně načíst.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $sourceMime = strtolower((string)$finfo->buffer($bytes));
    if (!in_array($sourceMime, ['image/jpeg', 'image/png'], true)) {
        throw new ShopProductImageException('Obrázek musí být skutečný JPG nebo PNG soubor.');
    }
    $dimensions = @getimagesizefromstring($bytes);
    $width = is_array($dimensions) ? (int)($dimensions[0] ?? 0) : 0;
    $height = is_array($dimensions) ? (int)($dimensions[1] ?? 0) : 0;
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000) {
        throw new ShopProductImageException('Obrázek má neplatné rozměry; maximum je 6000 × 6000 px.');
    }
    $decoded = @imagecreatefromstring($bytes);
    if (!$decoded instanceof GdImage) {
        throw new ShopProductImageException('Obrázek nelze bezpečně dekódovat.');
    }

    $applicationRoot ??= dirname(__DIR__);
    $directory = shopProductImageEnsureDirectory($applicationRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'shop-products');
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $target = $directory . DIRECTORY_SEPARATOR . $name;
    try {
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof GdImage) {
            throw new ShopProductImageException('Pro obrázek se nepodařilo připravit bezpečné plátno.');
        }
        try {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            if (!imagecopy($canvas, $decoded, 0, 0, 0, 0, $width, $height) || !imagejpeg($canvas, $target, 90)) {
                throw new ShopProductImageException('Obrázek se nepodařilo uložit.');
            }
        } finally {
            imagedestroy($canvas);
        }
    } catch (Throwable $exception) {
        if (is_file($target)) shopProductImageQuarantine($target,$applicationRoot);
        throw $exception;
    } finally {
        imagedestroy($decoded);
    }
    @chmod($target, 0644);
    $storedSize = filesize($target);
    $hash = hash_file('sha256', $target);
    if (!is_int($storedSize) || !is_string($hash)) {
        shopProductImageQuarantine($target, $applicationRoot);
        throw new ShopProductImageException('Uložený obrázek nelze ověřit.');
    }
    return [
        'image_url'=>'uploads/shop-products/' . $name,
        'sha256_hex'=>$hash,
        'byte_size'=>$storedSize,
        'mime_type'=>'image/jpeg',
        'width_px'=>$width,
        'height_px'=>$height,
    ];
}

/** @return array{id:int,product_id:int,image_url:string,sort_order:int} */
function shopProductImageAdd(
    PDO $pdo,
    int $actorId,
    int $productId,
    string $source,
    int $sortOrder,
    string $reason,
    bool $confirmed,
    bool $uploaded = true,
    ?string $applicationRoot = null
): array {
    if ($sortOrder < -100000 || $sortOrder > 100000) {
        throw new InvalidArgumentException('Pořadí obrázku musí být mezi -100000 a 100000.');
    }
    shopManualCatalogDecision($actorId, $reason, $confirmed);
    $applicationRoot ??= dirname(__DIR__);
    $stored = shopProductImageStoreFile($source, $uploaded, $applicationRoot);
    try {
        $pdo->beginTransaction();
        $result=shopProductImageAddStoredInTransaction($pdo,$actorId,$productId,$stored,$sortOrder,$reason,true);
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        shopProductImageQuarantine(shopProductImagePath((string)$stored['image_url'],$applicationRoot),$applicationRoot);
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException) throw $exception;
        throw new ShopProductImageException('Obrázek se nepodařilo přidat bez částečného zápisu.',0,$exception);
    }
}

/**
 * @param array{image_url:string,sha256_hex:string,byte_size:int,mime_type:string,width_px:int,height_px:int} $stored
 * @return array{id:int,product_id:int,image_url:string,sort_order:int}
 */
function shopProductImageAddStoredInTransaction(
    PDO $pdo,int $actorId,int $productId,array $stored,int $sortOrder,string $reason,bool $confirmed
):array{
    if(!$pdo->inTransaction())throw new LogicException('Přidání obrázku vyžaduje otevřenou transakci.');
    if($sortOrder < -100000 || $sortOrder > 100000)throw new InvalidArgumentException('Pořadí obrázku musí být mezi -100000 a 100000.');
    shopManualCatalogDecision($actorId,$reason,$confirmed);
    if(preg_match('~^uploads/shop-products/[a-f0-9]{32}\.jpg$~D',(string)($stored['image_url']??''))!==1
        ||preg_match('/^[a-f0-9]{64}$/D',(string)($stored['sha256_hex']??''))!==1
        ||(int)($stored['byte_size']??0)<1||(string)($stored['mime_type']??'')!=='image/jpeg'){
        throw new ShopProductImageException('Připravený obrázek nemá platný bezpečnostní záznam.');
    }
    shopManualCatalogLockProduct($pdo,$productId);
    $pdo->prepare('INSERT INTO shop_product_images(product_id,image_url,sort_order) VALUES(?,?,?)')
        ->execute([$productId,$stored['image_url'],$sortOrder]);
    $id=(int)$pdo->lastInsertId();
    $after=['id'=>$id,'product_id'=>$productId,'image_url'=>$stored['image_url'],'sort_order'=>$sortOrder,'file'=>$stored];
    shopManualCatalogEvent($pdo,$productId,null,$actorId,'add_image',null,$after,$reason);
    return['id'=>$id,'product_id'=>$productId,'image_url'=>$stored['image_url'],'sort_order'=>$sortOrder];
}

/** @return array{id:int,product_id:int,changed:bool} */
function shopProductImageUpdateOrder(PDO $pdo, int $actorId, int $imageId, int $sortOrder, string $reason, bool $confirmed): array
{
    if ($sortOrder < -100000 || $sortOrder > 100000) throw new InvalidArgumentException('Pořadí obrázku musí být mezi -100000 a 100000.');
    $productId=shopProductImageProductId($pdo,$imageId);
    $pdo->beginTransaction();
    try {
        shopManualCatalogDecision($actorId,$reason,$confirmed);
        shopManualCatalogLockProduct($pdo,$productId);
        $before = shopProductImageLock($pdo,$imageId);
        if ((int)$before['product_id']!==$productId) throw new ShopProductImageException('Obrázek během úpravy změnil produkt.');
        $changed = (int)$before['sort_order'] !== $sortOrder;
        if ($changed) {
            $pdo->prepare('UPDATE shop_product_images SET sort_order=? WHERE id=?')->execute([$sortOrder,$imageId]);
            $after = $before;$after['sort_order']=$sortOrder;
            shopManualCatalogEvent($pdo,(int)$before['product_id'],null,$actorId,'reorder_image',$before,$after,$reason);
        }
        $pdo->commit();
        return ['id'=>$imageId,'product_id'=>(int)$before['product_id'],'changed'=>$changed];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException || $exception instanceof ShopProductImageException) throw $exception;
        throw new ShopProductImageException('Pořadí obrázku se nepodařilo změnit bez částečného zápisu.',0,$exception);
    }
}

/** @return array{id:int,product_id:int,quarantined:bool} */
function shopProductImageRemove(
    PDO $pdo,
    int $actorId,
    int $imageId,
    string $reason,
    bool $confirmed,
    ?string $applicationRoot = null
): array {
    $applicationRoot ??= dirname(__DIR__);
    $productId=shopProductImageProductId($pdo,$imageId);
    $movedFrom = null;$movedTo = null;
    $pdo->beginTransaction();
    try {
        shopManualCatalogDecision($actorId,$reason,$confirmed);
        shopManualCatalogLockProduct($pdo,$productId);
        $before = shopProductImageLock($pdo,$imageId);
        if ((int)$before['product_id']!==$productId) throw new ShopProductImageException('Obrázek během odebrání změnil produkt.');
        $localPath = shopProductImagePath((string)$before['image_url'],$applicationRoot);
        if ($localPath !== null && is_file($localPath)) {
            $movedFrom=$localPath;
            $movedTo=shopProductImageQuarantine($localPath,$applicationRoot);
        }
        $pdo->prepare('DELETE FROM shop_product_images WHERE id=?')->execute([$imageId]);
        $after=['removed'=>true,'quarantine_file'=>$movedTo===null?null:basename($movedTo)];
        shopManualCatalogEvent($pdo,$productId,null,$actorId,'remove_image',$before,$after,$reason);
        $pdo->commit();
        return ['id'=>$imageId,'product_id'=>$productId,'quarantined'=>$movedTo!==null];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($movedFrom!==null && $movedTo!==null && is_file($movedTo)) @rename($movedTo,$movedFrom);
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopManualCatalogException || $exception instanceof ShopProductImageException) throw $exception;
        throw new ShopProductImageException('Obrázek se nepodařilo odebrat bez částečného zápisu.',0,$exception);
    }
}

/** @return array<string,mixed> */
function shopProductImageLock(PDO $pdo, int $imageId): array
{
    if ($imageId < 1) throw new InvalidArgumentException('Obrázek nebyl vybrán.');
    $sql='SELECT id,product_id,image_url,sort_order FROM shop_product_images WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$imageId]);$row=$statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new ShopProductImageException('Obrázek nebyl nalezen.');
    return $row;
}

function shopProductImageProductId(PDO $pdo, int $imageId): int
{
    if ($imageId < 1) throw new InvalidArgumentException('Obrázek nebyl vybrán.');
    $statement=$pdo->prepare('SELECT product_id FROM shop_product_images WHERE id=?');$statement->execute([$imageId]);
    $productId=(int)$statement->fetchColumn();
    if ($productId<1) throw new ShopProductImageException('Obrázek nebyl nalezen.');
    return $productId;
}

function shopProductImagePath(string $imageUrl, string $applicationRoot): ?string
{
    if (preg_match('~^uploads/shop-products/([a-f0-9]{32}\.jpg)$~D',$imageUrl,$matches)!==1) return null;
    return rtrim($applicationRoot,'/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'shop-products' . DIRECTORY_SEPARATOR . $matches[1];
}

function shopProductImageEnsureDirectory(string $directory): string
{
    if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) {
        throw new ShopProductImageException('Úložiště obrázků se nepodařilo připravit.');
    }
    return $directory;
}

function shopProductImageQuarantine(?string $source, string $applicationRoot): ?string
{
    if ($source===null || !is_file($source)) return null;
    $directory=shopProductImageEnsureDirectory(rtrim($applicationRoot,'/\\') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . '_to_delete' . DIRECTORY_SEPARATOR . 'shop-product-images');
    $target=$directory . DIRECTORY_SEPARATOR . gmdate('YmdHis') . '-' . bin2hex(random_bytes(8)) . '-' . basename($source);
    if (!rename($source,$target)) throw new ShopProductImageException('Soubor obrázku se nepodařilo přesunout do karantény.');
    return $target;
}
