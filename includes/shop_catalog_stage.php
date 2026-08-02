<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_catalog_contract.php';

/**
 * Persist a validated catalog candidate for explicit review. This function
 * never creates canonical products, bookings, orders, payments or stock moves.
 *
 * @param array<string,mixed> $catalog
 * @return array{run_id:int,created:bool,products:int,variants:int,manual_review_products:int}
 */
function shopCatalogStage(PDO $pdo, array $catalog): array
{
    if (($catalog['contract_version'] ?? null) !== ShopCatalogContract::VERSION
        || ($catalog['provisional'] ?? null) !== true
        || ($catalog['summary']['contract_ready'] ?? null) !== true
    ) {
        throw new InvalidArgumentException('Katalogovy kandidat neni pripraven ke stagingu.');
    }

    $sourceHash = (string)($catalog['source']['sha256'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/D', $sourceHash) !== 1) {
        throw new InvalidArgumentException('Katalogovy kandidat nema platny SHA-256 otisk.');
    }

    $existing = $pdo->prepare(
        'SELECT id FROM shop_catalog_import_runs '
        . 'WHERE source_sha256 = ? AND contract_version = ? LIMIT 1'
    );
    $existing->execute([$sourceHash, ShopCatalogContract::VERSION]);
    $existingId = $existing->fetchColumn();
    if ($existingId !== false) {
        return shopCatalogStageResult($catalog, (int)$existingId, false);
    }

    $pdo->beginTransaction();
    try {
        $insertRun = $pdo->prepare(
            'INSERT INTO shop_catalog_import_runs '
            . '(source_sha256, source_filename, contract_version, status, product_count, '
            . 'variant_count, warning_count, manual_review_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertRun->execute([
            $sourceHash,
            basename((string)($catalog['source']['filename'] ?? 'catalog')),
            ShopCatalogContract::VERSION,
            'pending_review',
            (int)$catalog['summary']['products'],
            (int)$catalog['summary']['variants'],
            (int)$catalog['summary']['warnings'],
            (int)$catalog['summary']['manual_review_products'],
        ]);
        $runId = (int)$pdo->lastInsertId();

        $insertProduct = $pdo->prepare(
            'INSERT INTO shop_catalog_product_candidates '
            . '(run_id, external_product_key, source_pair_code, name, offer_type, '
            . 'classification_confidence, needs_manual_review, payload_json) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertVariant = $pdo->prepare(
            'INSERT INTO shop_catalog_variant_candidates '
            . '(run_id, product_candidate_id, sku, price_mode, amount_minor, currency, '
            . 'stock_quantity_decimal, payload_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($catalog['products'] as $product) {
            $classification = $product['offer_classification'];
            $insertProduct->execute([
                $runId,
                (string)$product['external_product_key'],
                $product['source_pair_code'],
                (string)$product['name'],
                (string)$classification['type'],
                (string)$classification['confidence'],
                $classification['needs_manual_review'] ? 1 : 0,
                shopCatalogJson($product),
            ]);
            $productId = (int)$pdo->lastInsertId();

            foreach ($product['variants'] as $variant) {
                $insertVariant->execute([
                    $runId,
                    $productId,
                    (string)$variant['sku'],
                    (string)$variant['price']['mode'],
                    $variant['price']['amount_minor'],
                    $variant['price']['currency'],
                    $variant['stock']['quantity_decimal'],
                    shopCatalogJson($variant),
                ]);
            }
        }

        $pdo->commit();
        return shopCatalogStageResult($catalog, $runId, true);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // A concurrent process may have staged the same immutable source.
        $existing->execute([$sourceHash, ShopCatalogContract::VERSION]);
        $existingId = $existing->fetchColumn();
        if ($existingId !== false) {
            return shopCatalogStageResult($catalog, (int)$existingId, false);
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $value */
function shopCatalogJson(array $value): string
{
    return json_encode(
        $value,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

/**
 * @param array<string,mixed> $catalog
 * @return array{run_id:int,created:bool,products:int,variants:int,manual_review_products:int}
 */
function shopCatalogStageResult(array $catalog, int $runId, bool $created): array
{
    return [
        'run_id' => $runId,
        'created' => $created,
        'products' => (int)$catalog['summary']['products'],
        'variants' => (int)$catalog['summary']['variants'],
        'manual_review_products' => (int)$catalog['summary']['manual_review_products'],
    ];
}
