<?php
declare(strict_types=1);

/** Read-only inspection of original Kunratice and Točňáci split products. */
function diagnoseKunraticeCatalogMain(): void
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
        $stage = 'connection';
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec('START TRANSACTION READ ONLY');
        $readOnlyStarted = true;
        $stage = 'catalog_query';
        $imported = $pdo->query(
            'SELECT p.id AS product_id,p.name,p.origin AS product_origin,p.offer_type,'
            . 'p.catalog_status AS product_status,v.id AS variant_id,v.origin AS variant_origin,'
            . 'v.sku,v.price_mode,v.amount_minor,v.currency,v.unit_code,'
            . 'v.stock_quantity_decimal,v.catalog_status AS variant_status,'
            . 'pub.status AS publication_status,'
            . '(SELECT COUNT(*) FROM shop_variants vs WHERE vs.product_id=p.id) AS variant_count,'
            . '(SELECT COUNT(*) FROM club_program_offers co WHERE co.variant_id=v.id) AS linked_offer_count '
            . 'FROM shop_products p JOIN shop_variants v ON v.product_id=p.id '
            . 'LEFT JOIN shop_product_publications pub ON pub.product_id=p.id '
            . 'WHERE p.id IN (154,155,227) ORDER BY p.id,v.id'
        )->fetchAll();
        $canonical = $pdo->query(
            "SELECT p.id AS program_id,p.code,p.name,o.id AS offer_id,o.purchase_option,"
            . 'o.product_id,o.variant_id,o.capacity,v.amount_minor,v.currency,'
            . 'sp.catalog_status AS product_status,v.catalog_status AS variant_status '
            . 'FROM club_programs p JOIN club_program_offers o ON o.program_id=p.id '
            . 'JOIN shop_products sp ON sp.id=o.product_id '
            . 'JOIN shop_variants v ON v.id=o.variant_id '
            . "WHERE p.code LIKE 'KROUZKY-2627-%KUNRATICE' "
            . "OR p.name LIKE '%Točňáci%' ORDER BY p.id,o.id"
        )->fetchAll();
        $stage = 'rollback';
        $pdo->exec('ROLLBACK');
        $readOnlyStarted = false;
        echo json_encode(['imported' => $imported, 'canonical' => $canonical],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
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
            'error' => 'kunratice_catalog_diagnostic_failed',
            'stage' => $stage,
            'exception_class' => get_class($error),
            'sqlstate' => $sqlState,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    diagnoseKunraticeCatalogMain();
}
