<?php
declare(strict_types=1);

/**
 * Fixed, non-personal catalog inventory for the Pumptrack price guard.
 * The transaction is read-only even though the application DB user can write.
 */
function diagnosePumptrackCatalogMain(): void
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('Pouze CLI.');
    }
    $root = realpath((string) getenv('APP_ROOT'));
    $host = strtolower(trim((string) getenv('APP_HOST')));
    if ($root === false || $host !== 'kis.kovopraha.cz' || !is_file($root . '/config.php')) {
        throw new RuntimeException('Cíl musí být produkční instalace kis.kovopraha.cz.');
    }

    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    require $root . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('Diagnostika je určena pouze pro produkci.');
    }
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('Neúplná konfigurace databáze.');
        }
    }

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
    $pdo->exec('START TRANSACTION READ ONLY');
    try {
        $variants = $pdo->query(
            'SELECT v.id AS variant_id,v.product_id,v.sku,v.origin AS variant_origin,'
            . 'v.catalog_status AS variant_status,v.price_mode,v.amount_minor,'
            . 'v.compare_at_amount_minor,v.currency,v.unit_code,v.visible,'
            . 'v.stock_quantity_decimal,p.name AS product_name,p.origin AS product_origin,'
            . 'p.catalog_status AS product_status,p.offer_type '
            . 'FROM shop_variants v JOIN shop_products p ON p.id=v.product_id '
            . 'WHERE v.id IN (893,941) ORDER BY v.id'
        )->fetchAll();
        $canonical = $pdo->query(
            'SELECT o.id AS offer_id,o.purchase_option,o.status AS offer_status,'
            . 'v.id AS variant_id,v.product_id,v.sku,v.origin AS variant_origin,'
            . 'v.catalog_status AS variant_status,v.price_mode,v.amount_minor,'
            . 'v.compare_at_amount_minor,v.currency,v.unit_code,v.visible,'
            . 'v.stock_quantity_decimal,sp.name AS product_name,sp.origin AS product_origin,'
            . 'sp.catalog_status AS product_status,sp.offer_type '
            . 'FROM club_program_offers o '
            . 'JOIN club_programs p ON p.id=o.program_id '
            . 'JOIN shop_variants v ON v.id=o.variant_id '
            . 'JOIN shop_products sp ON sp.id=o.product_id '
            . "WHERE p.code='KROUZKY-2627-PUMPTRACK' AND sp.origin='manual' "
            . "AND o.purchase_option IN ('first_half','full_year') "
            . 'ORDER BY o.purchase_option,o.id'
        )->fetchAll();
        $pdo->exec('ROLLBACK');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }

    echo json_encode(
        ['variants' => $variants, 'canonical_offers' => $canonical],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        diagnosePumptrackCatalogMain();
    } catch (Throwable $error) {
        fwrite(STDERR, 'Diagnostika Pumptracku selhala. Zkontrolujte cíl, oprávnění a databázové schéma.' . PHP_EOL);
        exit(1);
    }
}
