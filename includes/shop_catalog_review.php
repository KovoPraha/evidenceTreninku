<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_offer_classifier.php';

/** @return list<string> */
function shopCatalogReviewOfferTypes(): array
{
    return array_values(array_filter(
        ShopOfferClassifier::TYPES,
        static fn (string $type): bool => $type !== ShopOfferClassifier::UNCLASSIFIED
    ));
}

/** @return list<array<string,mixed>> */
function shopCatalogReviewRuns(PDO $pdo, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    return $pdo->query(
        'SELECT r.*, '
        . "(SELECT COUNT(*) FROM shop_catalog_product_candidates p WHERE p.run_id=r.id AND p.review_status='pending') AS pending_count, "
        . "(SELECT COUNT(*) FROM shop_catalog_product_candidates p WHERE p.run_id=r.id AND p.review_status='approved') AS approved_count, "
        . "(SELECT COUNT(*) FROM shop_catalog_product_candidates p WHERE p.run_id=r.id AND p.review_status='excluded') AS excluded_count "
        . 'FROM shop_catalog_import_runs r ORDER BY r.created_at DESC, r.id DESC LIMIT ' . $limit
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array{run:?array<string,mixed>,products:list<array<string,mixed>>,events:list<array<string,mixed>>}
 */
function shopCatalogReviewDetail(
    PDO $pdo,
    int $runId,
    string $status = '',
    string $type = '',
    string $search = ''
): array {
    $runStatement = $pdo->prepare('SELECT * FROM shop_catalog_import_runs WHERE id = ?');
    $runStatement->execute([$runId]);
    $run = $runStatement->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        return ['run' => null, 'products' => [], 'events' => []];
    }

    $where = ['p.run_id = ?'];
    $parameters = [$runId];
    if (in_array($status, ['pending', 'auto_classified', 'approved', 'excluded'], true)) {
        $where[] = 'p.review_status = ?';
        $parameters[] = $status;
    }
    if (in_array($type, ShopOfferClassifier::TYPES, true)) {
        $where[] = 'COALESCE(p.reviewed_offer_type, p.offer_type) = ?';
        $parameters[] = $type;
    }
    $search = trim($search);
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.external_product_key LIKE ? '
            . 'OR EXISTS (SELECT 1 FROM shop_catalog_variant_candidates sv '
            . 'WHERE sv.product_candidate_id=p.id AND sv.sku LIKE ?))';
        $needle = '%' . $search . '%';
        array_push($parameters, $needle, $needle, $needle);
    }

    $statement = $pdo->prepare(
        'SELECT p.*, '
        . '(SELECT COUNT(*) FROM shop_catalog_variant_candidates v WHERE v.product_candidate_id=p.id) AS variant_count, '
        . '(SELECT MIN(v.amount_minor) FROM shop_catalog_variant_candidates v WHERE v.product_candidate_id=p.id) AS min_amount_minor, '
        . '(SELECT MAX(v.amount_minor) FROM shop_catalog_variant_candidates v WHERE v.product_candidate_id=p.id) AS max_amount_minor, '
        . '(SELECT MIN(v.currency) FROM shop_catalog_variant_candidates v WHERE v.product_candidate_id=p.id) AS currency '
        . 'FROM shop_catalog_product_candidates p WHERE ' . implode(' AND ', $where)
        . " ORDER BY CASE p.review_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 "
        . "WHEN 'auto_classified' THEN 2 ELSE 3 END, p.name, p.id LIMIT 500"
    );
    $statement->execute($parameters);

    $events = $pdo->prepare(
        'SELECT e.*, p.name AS product_name, t.jmeno AS actor_name '
        . 'FROM shop_catalog_review_events e '
        . 'JOIN shop_catalog_product_candidates p ON p.id=e.product_candidate_id '
        . 'LEFT JOIN treneri t ON t.id=e.actor_trainer_id '
        . 'WHERE e.run_id=? ORDER BY e.created_at DESC, e.id DESC LIMIT 50'
    );
    $events->execute([$runId]);

    return [
        'run' => $run,
        'products' => $statement->fetchAll(PDO::FETCH_ASSOC),
        'events' => $events->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/**
 * @return array{run_id:int,product_id:int,status:string,effective_offer_type:?string,run_status:string}
 */
function shopCatalogReviewProduct(
    PDO $pdo,
    int $runId,
    int $productId,
    int $actorTrainerId,
    string $action,
    ?string $offerType,
    string $note
): array {
    if ($runId < 1 || $productId < 1 || $actorTrainerId < 1) {
        throw new InvalidArgumentException('Neplatny identifikator kontroly katalogu.');
    }
    if (!in_array($action, ['approve', 'exclude'], true)) {
        throw new InvalidArgumentException('Neplatna akce kontroly katalogu.');
    }
    $note = trim($note);
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznamka smi mit nejvyse 1000 znaku.');
    }
    if ($action === 'approve' && !in_array($offerType, shopCatalogReviewOfferTypes(), true)) {
        throw new InvalidArgumentException('Vyberte platny vysledny typ nabidky.');
    }
    if ($action === 'exclude') {
        $offerType = null;
        if ($note === '') {
            throw new InvalidArgumentException('Pri vyrazeni je poznamka povinna.');
        }
    }

    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM shop_catalog_product_candidates WHERE id=? AND run_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$productId, $runId]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            throw new RuntimeException('Produktovy kandidat nebyl nalezen.');
        }

        $fromType = $product['reviewed_offer_type'] ?: $product['offer_type'];
        if ($action === 'approve' && $offerType !== $fromType && $note === '') {
            throw new InvalidArgumentException('Pri zmene navrzeneho typu je poznamka povinna.');
        }
        $status = $action === 'approve' ? 'approved' : 'excluded';
        $update = $pdo->prepare(
            'UPDATE shop_catalog_product_candidates SET review_status=?, reviewed_offer_type=?, '
            . 'review_note=?, reviewed_by=?, reviewed_at=CURRENT_TIMESTAMP WHERE id=? AND run_id=?'
        );
        $update->execute([$status, $offerType, $note !== '' ? $note : null, $actorTrainerId, $productId, $runId]);

        $event = $pdo->prepare(
            'INSERT INTO shop_catalog_review_events '
            . '(run_id, product_candidate_id, actor_trainer_id, action, from_offer_type, to_offer_type, note) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $event->execute([
            $runId, $productId, $actorTrainerId, $action, $fromType, $offerType, $note !== '' ? $note : null,
        ]);

        $pending = $pdo->prepare(
            "SELECT COUNT(*) FROM shop_catalog_product_candidates WHERE run_id=? AND review_status='pending'"
        );
        $pending->execute([$runId]);
        $runStatus = (int)$pending->fetchColumn() === 0 ? 'ready_for_promotion' : 'pending_review';
        $updateRun = $pdo->prepare('UPDATE shop_catalog_import_runs SET status=? WHERE id=?');
        $updateRun->execute([$runStatus, $runId]);

        $pdo->commit();
        return [
            'run_id' => $runId,
            'product_id' => $productId,
            'status' => $status,
            'effective_offer_type' => $offerType,
            'run_status' => $runStatus,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
