<?php
declare(strict_types=1);

final class ShopCatalogPublicationException extends RuntimeException
{
}

/** @return list<array<string,mixed>> */
function shopCatalogPublicationProducts(PDO $pdo): array
{
    return $pdo->query(
        'SELECT p.*, pub.status AS publication_status, pub.public_name, pub.public_summary, '
        . 'pub.decision_note AS publication_note, pub.activated_at, pub.deactivated_at, '
        . 'COUNT(v.id) AS variant_count, '
        . 'SUM(CASE WHEN v.visible=1 OR v.visible IS NULL THEN 1 ELSE 0 END) AS visible_variant_count, '
        . 'MIN(CASE WHEN v.visible=1 OR v.visible IS NULL THEN v.amount_minor END) AS min_amount_minor, '
        . 'MAX(CASE WHEN v.visible=1 OR v.visible IS NULL THEN v.amount_minor END) AS max_amount_minor '
        . ', MIN(CASE WHEN v.visible=1 OR v.visible IS NULL THEN v.currency END) AS currency '
        . 'FROM shop_products p '
        . 'LEFT JOIN shop_product_publications pub ON pub.product_id=p.id '
        . 'LEFT JOIN shop_variants v ON v.product_id=p.id '
        . 'GROUP BY p.id, pub.product_id, pub.status, pub.public_name, pub.public_summary, '
        . 'pub.decision_note, pub.activated_at, pub.deactivated_at '
        . 'ORDER BY CASE p.catalog_status WHEN \'active\' THEN 0 WHEN \'draft\' THEN 1 ELSE 2 END, p.name, p.id'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function shopCatalogPublicationEvents(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    return $pdo->query(
        'SELECT e.*, p.name AS product_name, t.jmeno AS actor_name '
        . 'FROM shop_product_publication_events e '
        . 'JOIN shop_products p ON p.id=e.product_id '
        . 'LEFT JOIN treneri t ON t.id=e.actor_trainer_id '
        . 'ORDER BY e.created_at DESC, e.id DESC LIMIT ' . $limit
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{ready:bool,blockers:list<string>,visible_variants:int} */
function shopCatalogPublicationReadiness(PDO $pdo, int $productId): array
{
    $product = $pdo->prepare('SELECT * FROM shop_products WHERE id=?');
    $product->execute([$productId]);
    $product = $product->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return ['ready' => false, 'blockers' => ['Produkt nebyl nalezen.'], 'visible_variants' => 0];
    }
    $blockers = [];
    if ((string)$product['offer_type'] !== 'goods') {
        $blockers[] = 'Typ ' . $product['offer_type'] . ' čeká na doménovou funkci K3 nebo rezervace.';
    }
    if (trim((string)$product['name']) === '') {
        $blockers[] = 'Produkt nemá název.';
    }
    $hiddenValues = ['hidden', 'private', 'false', 'no', '0'];
    if (in_array(mb_strtolower(trim((string)$product['visibility']), 'UTF-8'), $hiddenValues, true)) {
        $blockers[] = 'Zdrojový produkt je označený jako skrytý.';
    }

    $variants = $pdo->prepare('SELECT * FROM shop_variants WHERE product_id=? ORDER BY id');
    $variants->execute([$productId]);
    $visible = 0;
    foreach ($variants->fetchAll(PDO::FETCH_ASSOC) as $variant) {
        if ($variant['visible'] !== null && (int)$variant['visible'] !== 1) {
            continue;
        }
        $visible++;
        $sku = trim((string)$variant['sku']);
        if ($sku === '') {
            $blockers[] = 'Viditelná varianta nemá SKU.';
        }
        $mode = (string)$variant['price_mode'];
        if ($mode === 'fixed') {
            if ($variant['amount_minor'] === null || (int)$variant['amount_minor'] < 0) {
                $blockers[] = 'Varianta ' . ($sku ?: '#' . $variant['id']) . ' nemá platnou pevnou cenu.';
            }
            if (preg_match('/^[A-Z]{3}$/D', (string)$variant['currency']) !== 1) {
                $blockers[] = 'Varianta ' . ($sku ?: '#' . $variant['id']) . ' nemá platnou měnu.';
            }
        } elseif ($mode === 'free') {
            if ($variant['amount_minor'] !== null && (int)$variant['amount_minor'] !== 0) {
                $blockers[] = 'Bezplatná varianta nesmí mít nenulovou cenu.';
            }
        } else {
            $blockers[] = 'Varianta ' . ($sku ?: '#' . $variant['id']) . ' má nepodporovaný cenový režim.';
        }
    }
    if ($visible === 0) {
        $blockers[] = 'Produkt nemá žádnou viditelnou variantu.';
    }
    $blockers = array_values(array_unique($blockers));
    return ['ready' => $blockers === [], 'blockers' => $blockers, 'visible_variants' => $visible];
}

/** @return array{product_id:int,status:string,changed:bool,visible_variants:int} */
function shopCatalogPublicationActivate(
    PDO $pdo,
    int $productId,
    int $actorTrainerId,
    string $publicName,
    string $publicSummary,
    string $note,
    bool $confirmed
): array {
    [$publicName, $publicSummary, $note] = shopCatalogPublicationValidateDecision(
        $productId,
        $actorTrainerId,
        $publicName,
        $publicSummary,
        $note,
        $confirmed
    );
    $pdo->beginTransaction();
    try {
        $product = shopCatalogPublicationLockProduct($pdo, $productId);
        if (!$product) {
            throw new ShopCatalogPublicationException('Produkt nebyl nalezen.');
        }
        $readiness = shopCatalogPublicationReadiness($pdo, $productId);
        if (!$readiness['ready']) {
            throw new ShopCatalogPublicationException('Aktivace je blokována: ' . implode(' ', $readiness['blockers']));
        }
        $existing = $pdo->prepare('SELECT * FROM shop_product_publications WHERE product_id=?');
        $existing->execute([$productId]);
        $publication = $existing->fetch(PDO::FETCH_ASSOC);
        if ($product['catalog_status'] === 'active' && $publication && $publication['status'] === 'active') {
            if ($publication['public_name'] === $publicName && $publication['public_summary'] === $publicSummary) {
                $pdo->commit();
                return [
                    'product_id' => $productId,
                    'status' => 'active',
                    'changed' => false,
                    'visible_variants' => $readiness['visible_variants'],
                ];
            }
            throw new ShopCatalogPublicationException('Aktivní veřejný text nelze tiše přepsat. Produkt nejprve deaktivujte.');
        }
        if ($publication) {
            $update = $pdo->prepare(
                "UPDATE shop_product_publications SET status='active', public_name=?, public_summary=?, "
                . 'decision_note=?, activated_by_trainer_id=?, activated_at=CURRENT_TIMESTAMP, '
                . 'deactivated_at=NULL, updated_at=CURRENT_TIMESTAMP WHERE product_id=?'
            );
            $update->execute([$publicName, $publicSummary, $note, $actorTrainerId, $productId]);
            $action = 'reactivate';
            $fromStatus = (string)$publication['status'];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO shop_product_publications '
                . '(product_id, status, public_name, public_summary, decision_note, '
                . "activated_by_trainer_id, activated_at) VALUES (?, 'active', ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );
            $insert->execute([$productId, $publicName, $publicSummary, $note, $actorTrainerId]);
            $action = 'activate';
            $fromStatus = null;
        }
        $pdo->prepare("UPDATE shop_products SET catalog_status='active', updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$productId]);
        $pdo->prepare(
            "UPDATE shop_variants SET catalog_status=CASE WHEN visible=1 OR visible IS NULL THEN 'active' ELSE 'inactive' END, "
            . 'updated_at=CURRENT_TIMESTAMP WHERE product_id=?'
        )->execute([$productId]);
        shopCatalogPublicationEvent(
            $pdo,
            $productId,
            $actorTrainerId,
            $action,
            $fromStatus,
            'active',
            $publicName,
            $publicSummary,
            $note
        );
        $pdo->commit();
        return [
            'product_id' => $productId,
            'status' => 'active',
            'changed' => true,
            'visible_variants' => $readiness['visible_variants'],
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCatalogPublicationException) {
            throw $exception;
        }
        throw new ShopCatalogPublicationException('Aktivace selhala bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{product_id:int,status:string,changed:bool} */
function shopCatalogPublicationDeactivate(PDO $pdo, int $productId, int $actorTrainerId, string $note): array
{
    $note = trim($note);
    if ($productId < 1 || $actorTrainerId < 1 || $note === '') {
        throw new InvalidArgumentException('Deaktivace vyžaduje produkt, administrátora a důvod.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
    $pdo->beginTransaction();
    try {
        $product = shopCatalogPublicationLockProduct($pdo, $productId);
        if (!$product) {
            throw new ShopCatalogPublicationException('Produkt nebyl nalezen.');
        }
        $statement = $pdo->prepare('SELECT * FROM shop_product_publications WHERE product_id=?');
        $statement->execute([$productId]);
        $publication = $statement->fetch(PDO::FETCH_ASSOC);
        if ($product['catalog_status'] !== 'active' || !$publication || $publication['status'] !== 'active') {
            if ($publication && $publication['status'] === 'inactive') {
                $pdo->commit();
                return ['product_id' => $productId, 'status' => 'inactive', 'changed' => false];
            }
            throw new ShopCatalogPublicationException('Deaktivovat lze pouze aktivní produkt.');
        }
        $pdo->prepare(
            "UPDATE shop_product_publications SET status='inactive', decision_note=?, "
            . 'activated_by_trainer_id=?, deactivated_at=CURRENT_TIMESTAMP, '
            . 'updated_at=CURRENT_TIMESTAMP WHERE product_id=?'
        )->execute([$note, $actorTrainerId, $productId]);
        $pdo->prepare("UPDATE shop_products SET catalog_status='inactive', updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$productId]);
        $pdo->prepare("UPDATE shop_variants SET catalog_status='inactive', updated_at=CURRENT_TIMESTAMP WHERE product_id=?")
            ->execute([$productId]);
        shopCatalogPublicationEvent(
            $pdo,
            $productId,
            $actorTrainerId,
            'deactivate',
            'active',
            'inactive',
            (string)$publication['public_name'],
            (string)$publication['public_summary'],
            $note
        );
        $pdo->commit();
        return ['product_id' => $productId, 'status' => 'inactive', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCatalogPublicationException) {
            throw $exception;
        }
        throw new ShopCatalogPublicationException('Deaktivace selhala bez částečného zápisu.', 0, $exception);
    }
}

/** @return array<string,mixed>|false */
function shopCatalogPublicationLockProduct(PDO $pdo, int $productId): array|false
{
    $sql = 'SELECT * FROM shop_products WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$productId]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

/** @return array{string,string,string} */
function shopCatalogPublicationValidateDecision(
    int $productId,
    int $actorTrainerId,
    string $publicName,
    string $publicSummary,
    string $note,
    bool $confirmed
): array {
    $publicName = trim($publicName);
    $publicSummary = trim($publicSummary);
    $note = trim($note);
    if ($productId < 1 || $actorTrainerId < 1 || !$confirmed) {
        throw new InvalidArgumentException('Aktivace vyžaduje produkt a výslovné potvrzení administrátora.');
    }
    if ($publicName === '' || mb_strlen($publicName, 'UTF-8') > 255) {
        throw new InvalidArgumentException('Veřejný název musí mít 1 až 255 znaků.');
    }
    if ($publicSummary === '' || mb_strlen($publicSummary, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Veřejný popis musí mít 1 až 1000 znaků.');
    }
    if ($note === '' || mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Aktivace vyžaduje důvod o délce nejvýše 1000 znaků.');
    }
    if (preg_match('/[<>]/u', $publicName . $publicSummary) === 1) {
        throw new InvalidArgumentException('Veřejný název a popis musí být prostý text bez HTML.');
    }
    return [$publicName, $publicSummary, $note];
}

function shopCatalogPublicationEvent(
    PDO $pdo,
    int $productId,
    int $actorTrainerId,
    string $action,
    ?string $fromStatus,
    string $toStatus,
    string $publicName,
    string $publicSummary,
    string $note
): void {
    $statement = $pdo->prepare(
        'INSERT INTO shop_product_publication_events '
        . '(product_id, actor_trainer_id, action, from_status, to_status, public_name, public_summary, note) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([
        $productId,
        $actorTrainerId,
        $action,
        $fromStatus,
        $toStatus,
        $publicName,
        $publicSummary,
        $note,
    ]);
}
