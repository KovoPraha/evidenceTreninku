<?php

declare(strict_types=1);

require_once __DIR__ . '/management.php';
require_once __DIR__ . '/transfer_assets.php';

/** HTTP callers must supply only an is_uploaded_file() path, never a request pathname. */
function barSetProductImage(PDO $pdo, int $id, int $version, ?string $uploadPath, string $reason): void
{
    barActor($pdo, 'catalog_manage');
    $reason = barReason($reason);
    $image = $uploadPath === null ? null : barReadImage($uploadPath);
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, 'catalog_manage', true);
        $product = barLockedProduct($pdo, $id, $version, $suffix);
        $name = null;
        if ($image !== null) {
            $key = barStoreTransferImage($image);
            $name = 'upload-' . basename($key);
            $query = $pdo->prepare('SELECT * FROM kis_bar_assets WHERE source_name=?' . $suffix);
            $query->execute([$name]);
            $old = $query->fetch(PDO::FETCH_ASSOC);
            $meta = $image['meta'];
            if ($old && (!hash_equals($old['sha256_hex'], $meta['sha256']) || $old['storage_key'] !== $key
                || $old['mime_type'] !== $meta['mime_type'] || (int)$old['byte_size'] !== $meta['byte_size']
                || (int)$old['width_px'] !== $meta['width_px'] || (int)$old['height_px'] !== $meta['height_px'])) {
                throw new DomainException('Uložená fotografie má konfliktní údaje.');
            }
            if (!$old) {
                $pdo->prepare('INSERT INTO kis_bar_assets(source_name,storage_key,sha256_hex,mime_type,byte_size,width_px,height_px) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$name, $key, $meta['sha256'], $meta['mime_type'], $meta['byte_size'], $meta['width_px'], $meta['height_px']]);
            }
        }
        if ($product['image_name'] !== $name) {
            $pdo->prepare('UPDATE kis_bar_products SET image_name=?,lock_version=lock_version+1 WHERE id=?')->execute([$name, $id]);
            barEvent($pdo, $actor['id'], 'product', $id, 'image_changed', ['before' => $product['image_name'], 'after' => $name, 'reason' => $reason]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
