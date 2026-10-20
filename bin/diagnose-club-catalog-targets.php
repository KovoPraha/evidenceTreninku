<?php
declare(strict_types=1);

/** Read-only inventory of the exact 47 imported variants selected for club unification. */
function diagnoseClubCatalogTargetsMain(): void
{
    $stage = 'target';
    $pdo = null;
    $readOnlyStarted = false;
    try {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Only CLI is allowed.');
        }
        $root = realpath((string) getenv('APP_ROOT'));
        $host = strtolower(trim((string) getenv('APP_HOST')));
        if ($root === false || $host !== 'kis.kovopraha.cz' || !is_file($root . '/config.php')) {
            throw new RuntimeException('Unexpected target.');
        }

        $stage = 'config';
        $_SERVER['HTTP_HOST'] = $host;
        $_SERVER['SERVER_NAME'] = $host;
        require $root . '/config.php';
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
            throw new RuntimeException('Production only.');
        }
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
            if (!defined($constant)) {
                throw new RuntimeException('Incomplete DB config.');
            }
        }

        $stage = 'targets';
        require_once $root . '/includes/club_catalog_unification.php';
        $expected = [];
        foreach (clubCatalogUnificationTargets() as $group => $targets) {
            foreach ($targets as [$productId, $variantId, $sku]) {
                if (isset($expected[$variantId])) {
                    throw new RuntimeException('Duplicate target variant.');
                }
                $expected[$variantId] = ['product_id' => (int) $productId, 'sku' => (string) $sku];
            }
        }
        if (count($expected) !== 47) {
            throw new RuntimeException('Unexpected target count.');
        }
        ksort($expected, SORT_NUMERIC);

        $stage = 'connection';
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $stage = 'read_only_transaction';
        $pdo->exec('START TRANSACTION READ ONLY');
        $readOnlyStarted = true;
        $stage = 'catalog_query';
        $ids = array_keys($expected);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = $pdo->prepare(
            'SELECT v.id AS variant_id,v.product_id,v.sku,p.offer_type,v.unit_code,'
            . 'v.stock_quantity_decimal,p.catalog_status AS product_status,'
            . 'v.catalog_status AS variant_status,p.origin AS product_origin,'
            . 'v.origin AS variant_origin FROM shop_variants v '
            . 'JOIN shop_products p ON p.id=v.product_id '
            . 'WHERE v.id IN (' . $placeholders . ') ORDER BY v.id'
        );
        $query->execute($ids);
        $rows = $query->fetchAll();
        $stage = 'rollback';
        $pdo->exec('ROLLBACK');
        $readOnlyStarted = false;

        $stage = 'aggregation';
        $groups = [];
        $found = [];
        $catalogRows = [];
        foreach ($rows as $row) {
            $id = (int) $row['variant_id'];
            $found[$id] = true;
            $dimensions = [
                'offer_type' => (string) $row['offer_type'],
                'unit_code' => $row['unit_code'] === null ? null : (string) $row['unit_code'],
                'stock' => $row['stock_quantity_decimal'] === null ? 'null' : 'non_null',
                'product_status' => (string) $row['product_status'],
                'variant_status' => (string) $row['variant_status'],
                'product_origin' => (string) $row['product_origin'],
                'variant_origin' => (string) $row['variant_origin'],
            ];
            $key = json_encode($dimensions, JSON_THROW_ON_ERROR);
            if (!isset($groups[$key])) {
                $groups[$key] = ['dimensions' => $dimensions, 'count' => 0];
            }
            $groups[$key]['count']++;
            $catalogRows[] = [
                'variant_id' => $id,
                'product_id' => (int) $row['product_id'],
                'sku' => (string) $row['sku'],
                'expected_product_id' => $expected[$id]['product_id'],
                'expected_sku' => $expected[$id]['sku'],
                'dimensions' => $dimensions,
                '_key' => $key,
            ];
        }
        $aggregation = array_values($groups);
        usort($aggregation, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp(
                json_encode($a['dimensions'], JSON_THROW_ON_ERROR),
                json_encode($b['dimensions'], JSON_THROW_ON_ERROR)
            );
        });
        $majority = $aggregation[0] ?? null;
        $majorityKey = $majority === null ? null : json_encode($majority['dimensions'], JSON_THROW_ON_ERROR);
        $outliers = [];
        foreach ($catalogRows as $row) {
            $identityMismatch = $row['product_id'] !== $row['expected_product_id']
                || $row['sku'] !== $row['expected_sku'];
            if ($row['_key'] !== $majorityKey || $identityMismatch) {
                unset($row['_key']);
                $row['identity_mismatch'] = $identityMismatch;
                $outliers[] = $row;
            }
        }
        $missing = array_values(array_diff($ids, array_keys($found)));
        echo json_encode([
            'expected_count' => 47,
            'found_count' => count($rows),
            'majority' => $majority,
            'majority_is_absolute' => $majority !== null && $majority['count'] > count($rows) / 2,
            'aggregation' => $aggregation,
            'outliers' => $outliers,
            'missing_variant_ids' => $missing,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } catch (Throwable $error) {
        if ($readOnlyStarted && $pdo !== null) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // Preserve the first failure category.
            }
        }
        $sqlState = $error instanceof PDOException && preg_match('/^[A-Z0-9]{5}$/', (string) $error->getCode())
            ? (string) $error->getCode() : null;
        fwrite(STDERR, json_encode([
            'error' => 'club_catalog_targets_diagnostic_failed',
            'stage' => $stage,
            'exception_class' => get_class($error),
            'sqlstate' => $sqlState,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    diagnoseClubCatalogTargetsMain();
}
