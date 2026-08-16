<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_offer_classifier.php';
require_once __DIR__ . '/shop_catalog_origin.php';

final class ShopCatalogPromotionException extends RuntimeException
{
}

/** @return array{products:int,variants:int,draft_products:int,active_products:int,last_promotion:?array<string,mixed>} */
function shopCanonicalCatalogSummary(PDO $pdo): array
{
    $last = $pdo->query(
        'SELECT p.*, r.source_filename FROM shop_catalog_promotions p '
        . 'JOIN shop_catalog_import_runs r ON r.id=p.run_id '
        . "WHERE p.status='completed' ORDER BY p.completed_at DESC, p.id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    return [
        'products' => (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn(),
        'variants' => (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn(),
        'draft_products' => (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_products WHERE catalog_status='draft'"
        )->fetchColumn(),
        'active_products' => (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_products WHERE catalog_status='active'"
        )->fetchColumn(),
        'last_promotion' => $last ?: null,
    ];
}

/**
 * Move one reviewed staging run into the canonical draft catalog exactly once.
 * No storefront, order, booking, payment or inventory movement is created.
 *
 * @return array{promotion_id:int,run_id:int,created:bool,products:int,variants:int,catalog_status:string}
 */
function shopCatalogPromote(PDO $pdo, int $runId, int $actorTrainerId, bool $confirmed): array
{
    if ($runId < 1 || $actorTrainerId < 1 || !$confirmed) {
        throw new InvalidArgumentException('Převod do katalogu vyžaduje výslovné potvrzení administrátora.');
    }

    $pdo->beginTransaction();
    try {
        $runSql = 'SELECT * FROM shop_catalog_import_runs WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $runSql .= ' FOR UPDATE';
        }
        $runStatement = $pdo->prepare($runSql);
        $runStatement->execute([$runId]);
        $run = $runStatement->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            throw new ShopCatalogPromotionException('Importní běh nebyl nalezen.');
        }

        $existing = $pdo->prepare('SELECT * FROM shop_catalog_promotions WHERE run_id=?');
        $existing->execute([$runId]);
        $existingPromotion = $existing->fetch(PDO::FETCH_ASSOC);
        if ($existingPromotion && $existingPromotion['status'] === 'completed') {
            $pdo->commit();
            return shopCatalogPromotionResult($existingPromotion, false);
        }
        if ($run['status'] !== 'ready_for_promotion') {
            throw new ShopCatalogPromotionException(
                'Převést lze pouze běh, ve kterém jsou vyřešené všechny povinné kontroly.'
            );
        }

        $pending = $pdo->prepare(
            "SELECT COUNT(*) FROM shop_catalog_product_candidates "
            . "WHERE run_id=? AND review_status NOT IN ('auto_classified','approved','excluded')"
        );
        $pending->execute([$runId]);
        if ((int)$pending->fetchColumn() !== 0) {
            throw new ShopCatalogPromotionException('Importní běh stále obsahuje nevyřešené produkty.');
        }

        $products = $pdo->prepare(
            "SELECT * FROM shop_catalog_product_candidates "
            . "WHERE run_id=? AND review_status IN ('auto_classified','approved') ORDER BY id"
        );
        $products->execute([$runId]);
        $candidates = $products->fetchAll(PDO::FETCH_ASSOC);
        if ($candidates === []) {
            throw new ShopCatalogPromotionException('Importní běh neobsahuje žádný produkt k převodu.');
        }

        shopCatalogPromotionPreflight($pdo, $runId, $candidates);

        $createPromotion = $pdo->prepare(
            "INSERT INTO shop_catalog_promotions (run_id, actor_trainer_id, status) VALUES (?, ?, 'in_progress')"
        );
        $createPromotion->execute([$runId, $actorTrainerId]);
        $promotionId = (int)$pdo->lastInsertId();

        $insertProduct = $pdo->prepare(
            'INSERT INTO shop_products '
            . '(source_candidate_id, source_run_id, origin, created_by_trainer_id, external_product_key, '
            . 'source_pair_code, name, short_description, description_html_untrusted, offer_type, visibility, '
            . 'item_type, catalog_status) '
            . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')"
        );
        $insertCategory = $pdo->prepare(
            'INSERT INTO shop_product_categories (product_id, category_path, is_default, sort_order) '
            . 'VALUES (?, ?, ?, ?)'
        );
        $insertImage = $pdo->prepare(
            'INSERT INTO shop_product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)'
        );
        $variantCandidates = $pdo->prepare(
            'SELECT * FROM shop_catalog_variant_candidates WHERE product_candidate_id=? ORDER BY id'
        );
        $insertVariant = $pdo->prepare(
            'INSERT INTO shop_variants '
            . '(product_id, source_candidate_id, origin, created_by_trainer_id, sku, ean, attributes_json, '
            . 'price_mode, amount_minor, compare_at_amount_minor, currency, includes_vat, '
            . 'vat_rate_basis_points, stock_quantity_decimal, unit_code, availability_in_stock, '
            . 'availability_out_of_stock, free_shipping, free_billing, visible, catalog_status) '
            . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')"
        );

        $productCount = 0;
        $variantCount = 0;
        foreach ($candidates as $candidate) {
            $payload = shopCatalogPromotionJson((string)$candidate['payload_json'], 'produkt');
            $offerType = (string)($candidate['reviewed_offer_type'] ?: $candidate['offer_type']);
            if (!in_array($offerType, ShopOfferClassifier::TYPES, true)
                || $offerType === ShopOfferClassifier::UNCLASSIFIED
            ) {
                throw new ShopCatalogPromotionException('Produkt nemá platný výsledný typ nabídky.');
            }
            $sourceCandidateId = (int)$candidate['id'];
            shopCatalogAssertProductOrigin(
                ShopCatalogOrigin::IMPORT,
                $sourceCandidateId,
                $runId,
                null
            );
            $insertProduct->execute([
                $sourceCandidateId,
                $runId,
                ShopCatalogOrigin::IMPORT,
                null,
                (string)$candidate['external_product_key'],
                $candidate['source_pair_code'],
                (string)$candidate['name'],
                $payload['short_description'] ?? null,
                $payload['description_html_untrusted'] ?? null,
                $offerType,
                $payload['visibility'] ?? null,
                $payload['item_type'] ?? null,
            ]);
            $productId = (int)$pdo->lastInsertId();
            $productCount++;

            $sort = 0;
            $defaultCategory = trim((string)($payload['default_category_path'] ?? ''));
            if ($defaultCategory !== '') {
                $insertCategory->execute([$productId, $defaultCategory, 1, $sort++]);
            }
            foreach (($payload['additional_category_paths'] ?? []) as $category) {
                $category = trim((string)$category);
                if ($category !== '' && $category !== $defaultCategory) {
                    $insertCategory->execute([$productId, $category, 0, $sort++]);
                }
            }
            foreach (($payload['images'] ?? []) as $imageSort => $imageUrl) {
                $insertImage->execute([$productId, (string)$imageUrl, (int)$imageSort]);
            }

            $variantCandidates->execute([(int)$candidate['id']]);
            foreach ($variantCandidates->fetchAll(PDO::FETCH_ASSOC) as $variantCandidate) {
                $variant = shopCatalogPromotionJson((string)$variantCandidate['payload_json'], 'varianta');
                $variantSourceCandidateId = (int)$variantCandidate['id'];
                shopCatalogAssertVariantOrigin(
                    ShopCatalogOrigin::IMPORT,
                    $variantSourceCandidateId,
                    null,
                    ShopCatalogOrigin::IMPORT
                );
                $insertVariant->execute([
                    $productId,
                    $variantSourceCandidateId,
                    ShopCatalogOrigin::IMPORT,
                    null,
                    (string)$variantCandidate['sku'],
                    $variant['ean'] ?? null,
                    json_encode(
                        $variant['attributes'] ?? [],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    (string)$variantCandidate['price_mode'],
                    $variantCandidate['amount_minor'],
                    $variant['price']['compare_at_amount_minor'] ?? null,
                    $variantCandidate['currency'],
                    shopCatalogPromotionBool($variant['price']['includes_vat'] ?? null),
                    $variant['price']['vat_rate_basis_points'] ?? null,
                    $variantCandidate['stock_quantity_decimal'],
                    $variant['unit']['code'] ?? null,
                    $variant['stock']['availability_in_stock'] ?? null,
                    $variant['stock']['availability_out_of_stock'] ?? null,
                    shopCatalogPromotionBool($variant['fulfillment']['free_shipping'] ?? null),
                    shopCatalogPromotionBool($variant['fulfillment']['free_billing'] ?? null),
                    shopCatalogPromotionBool($variant['visible'] ?? null),
                ]);
                $variantCount++;
            }
        }

        $finish = $pdo->prepare(
            "UPDATE shop_catalog_promotions SET status='completed', product_count=?, variant_count=?, "
            . 'completed_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $finish->execute([$productCount, $variantCount, $promotionId]);
        $finishRun = $pdo->prepare(
            "UPDATE shop_catalog_import_runs SET status='promoted', promoted_at=CURRENT_TIMESTAMP, promoted_by=? "
            . 'WHERE id=?'
        );
        $finishRun->execute([$actorTrainerId, $runId]);
        $pdo->commit();

        return [
            'promotion_id' => $promotionId,
            'run_id' => $runId,
            'created' => true,
            'products' => $productCount,
            'variants' => $variantCount,
            'catalog_status' => 'draft',
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ShopCatalogPromotionException
        ) {
            throw $exception;
        }
        throw new ShopCatalogPromotionException(
            'Převod selhal bez částečného zápisu. Zkontrolujte kolize SKU a externích klíčů.',
            0,
            $exception
        );
    }
}

/**
 * Fail before the first promotion INSERT when canonical keys collide or the
 * staging run contains duplicate/non-import identities.
 *
 * @param list<array<string,mixed>> $candidates
 */
function shopCatalogPromotionPreflight(PDO $pdo, int $runId, array $candidates): void
{
    $variantStatement = $pdo->prepare(
        'SELECT sku FROM shop_catalog_variant_candidates '
        . 'WHERE run_id=? AND product_candidate_id=? ORDER BY sku,id'
    );
    $incoming = [];
    foreach ($candidates as $candidate) {
        $variantStatement->execute([$runId, (int)$candidate['id']]);
        $incoming[] = [
            'external_product_key' => (string)$candidate['external_product_key'],
            'skus' => array_map(
                static fn (mixed $sku): string => (string)$sku,
                $variantStatement->fetchAll(PDO::FETCH_COLUMN)
            ),
        ];
    }

    $lockSuffix = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $existingProductKeys = array_map(
        static fn (mixed $key): string => (string)$key,
        $pdo->query('SELECT external_product_key FROM shop_products ORDER BY id' . $lockSuffix)
            ->fetchAll(PDO::FETCH_COLUMN)
    );
    $existingSkus = array_map(
        static fn (mixed $sku): string => (string)$sku,
        $pdo->query('SELECT sku FROM shop_variants ORDER BY id' . $lockSuffix)
            ->fetchAll(PDO::FETCH_COLUMN)
    );
    $report = shopCatalogPromotionCollisionReport($incoming, $existingProductKeys, $existingSkus);

    if ($report['non_import_external_keys'] !== []) {
        $nonImportDetails = $report['non_import_skus'] !== []
            ? 'Dotčená SKU: ' . shopCatalogPromotionSkuList($report['non_import_skus'])
            : 'Dotčené externí klíče: ' . shopCatalogPromotionSkuList(
                $report['non_import_external_keys']
            );
        throw new ShopCatalogPromotionException(
            'Import byl zastaven před prvním zápisem do katalogu. Importní běh obsahuje produkt mimo '
            . 'vyhrazený namespace shoptet:. ' . $nonImportDetails
            . '. Ruční produkty patří přímo do katalogu a shopCatalogPromote() je nesmí zpracovat.'
        );
    }
    if ($report['colliding_skus'] !== [] || $report['colliding_external_keys'] !== []) {
        $collisionDetails = [];
        if ($report['colliding_skus'] !== []) {
            $collisionDetails[] = 'Kolidující SKU: '
                . shopCatalogPromotionSkuList($report['colliding_skus']);
        }
        if ($report['colliding_external_keys'] !== []) {
            $collisionDetails[] = 'Kolidující externí klíče: '
                . shopCatalogPromotionSkuList($report['colliding_external_keys']);
        }
        throw new ShopCatalogPromotionException(
            'Import byl zastaven před prvním zápisem do katalogu. '
            . implode('. ', $collisionDetails)
            . '. Přejmenujte v administraci ručně založená SKU z tohoto seznamu a import spusťte znovu. '
            . 'Pokud SKU patří dřívějšímu importu, nejprve zkontrolujte duplicitní běh; '
            . 'importní soubor není rozbitý.'
        );
    }
}

/**
 * @param list<array{external_product_key:string,skus:list<string>}> $incoming
 * @param list<string> $existingProductKeys
 * @param list<string> $existingSkus
 * @return array{
 *   colliding_skus:list<string>,
 *   colliding_external_keys:list<string>,
 *   non_import_skus:list<string>,
 *   non_import_external_keys:list<string>
 * }
 */
function shopCatalogPromotionCollisionReport(
    array $incoming,
    array $existingProductKeys,
    array $existingSkus
): array {
    $existingKeySet = array_fill_keys($existingProductKeys, true);
    $existingSkuSet = array_fill_keys($existingSkus, true);
    $seenKeys = [];
    $seenSkus = [];
    $collisions = [];
    $collidingExternalKeys = [];
    $nonImport = [];
    $nonImportExternalKeys = [];

    foreach ($incoming as $product) {
        $externalKey = (string)$product['external_product_key'];
        $skus = array_values(array_map('strval', $product['skus']));
        if (!str_starts_with($externalKey, 'shoptet:')) {
            $nonImportExternalKeys[$externalKey] = true;
            foreach ($skus as $sku) {
                $nonImport[$sku] = true;
            }
        }
        if (isset($existingKeySet[$externalKey]) || isset($seenKeys[$externalKey])) {
            $collidingExternalKeys[$externalKey] = true;
            foreach ($skus as $sku) {
                $collisions[$sku] = true;
            }
        }
        $seenKeys[$externalKey] = true;

        foreach ($skus as $sku) {
            if (isset($existingSkuSet[$sku]) || isset($seenSkus[$sku])) {
                $collisions[$sku] = true;
            }
            $seenSkus[$sku] = true;
        }
    }

    $collidingSkus = array_keys($collisions);
    $collidingKeys = array_keys($collidingExternalKeys);
    $nonImportSkus = array_keys($nonImport);
    $nonImportKeys = array_keys($nonImportExternalKeys);
    sort($collidingSkus, SORT_STRING);
    sort($collidingKeys, SORT_STRING);
    sort($nonImportSkus, SORT_STRING);
    sort($nonImportKeys, SORT_STRING);
    return [
        'colliding_skus' => $collidingSkus,
        'colliding_external_keys' => $collidingKeys,
        'non_import_skus' => $nonImportSkus,
        'non_import_external_keys' => $nonImportKeys,
    ];
}

/** @param list<string> $skus */
function shopCatalogPromotionSkuList(array $skus): string
{
    return implode(', ', array_map(static fn (string $sku): string => '`' . $sku . '`', $skus));
}

/** @return array<string,mixed> */
function shopCatalogPromotionJson(string $json, string $subject): array
{
    try {
        $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new ShopCatalogPromotionException('Stagingový payload pro ' . $subject . ' není platný.');
    }
    if (!is_array($value)) {
        throw new ShopCatalogPromotionException('Stagingový payload pro ' . $subject . ' není objekt.');
    }
    return $value;
}

function shopCatalogPromotionBool(mixed $value): ?int
{
    return $value === null ? null : ($value ? 1 : 0);
}

/** @param array<string,mixed> $promotion */
function shopCatalogPromotionResult(array $promotion, bool $created): array
{
    return [
        'promotion_id' => (int)$promotion['id'],
        'run_id' => (int)$promotion['run_id'],
        'created' => $created,
        'products' => (int)$promotion['product_count'],
        'variants' => (int)$promotion['variant_count'],
        'catalog_status' => 'draft',
    ];
}
