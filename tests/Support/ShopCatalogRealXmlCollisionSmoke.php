<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_review.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_promotion.php';

const PROMPT_E_R2_EXPORT_SHA256 = 'f924ec583781ac6a18aa92c1574070d8a220ee49fc13baf4700ff85527046bdf';
const PROMPT_E_R2_EXPORT_PRODUCTS = 241;
const PROMPT_E_R2_EXPORT_VARIANTS = 807;

$input = $argv[1] ?? 'C:\\productsComplete.xml';
if (!is_string($input) || $input === '') {
    throw new RuntimeException('Missing local Shoptet XML input.');
}

$parsed = ShoptetProductInput::read($input);
$catalog = ShopCatalogContract::build($parsed);
if (($catalog['source']['sha256'] ?? null) !== PROMPT_E_R2_EXPORT_SHA256
    || ($catalog['summary']['products'] ?? null) !== PROMPT_E_R2_EXPORT_PRODUCTS
    || ($catalog['summary']['variants'] ?? null) !== PROMPT_E_R2_EXPORT_VARIANTS
    || ($catalog['summary']['contract_ready'] ?? null) !== true
) {
    throw new RuntimeException('The XML input is not the approved 241/807 Prompt E R2 export.');
}

$allSkus = [];
foreach ($catalog['products'] as $product) {
    foreach ($product['variants'] as $variant) {
        $allSkus[] = (string)$variant['sku'];
    }
}
sort($allSkus, SORT_STRING);
$collisionSku = $allSkus[0] ?? '';
$nonCollisionSku = shopCatalogManualSkuPrefix() . 'R2-REAL-UNIKAT';
if ($collisionSku === '' || in_array($nonCollisionSku, $allSkus, true)) {
    throw new RuntimeException('Cannot prepare deterministic real XML collision scenarios.');
}

$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$databases = ['evidence_prompt_e_r2_real_ok', 'evidence_prompt_e_r2_real_collision'];
foreach ($databases as $database) {
    $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}

try {
    $cleanPdo = promptER2Database($server, $databases[0]);
    $cleanRun = shopCatalogStage($cleanPdo, $catalog);
    promptER2ResolvePending($cleanPdo, $cleanRun['run_id']);
    promptER2InsertManualProduct($cleanPdo, $nonCollisionSku, true);
    $cleanPromotion = shopCatalogPromote($cleanPdo, $cleanRun['run_id'], 7, true);
    if ($cleanPromotion['products'] !== PROMPT_E_R2_EXPORT_PRODUCTS
        || $cleanPromotion['variants'] !== PROMPT_E_R2_EXPORT_VARIANTS
        || (int)$cleanPdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn()
            !== PROMPT_E_R2_EXPORT_PRODUCTS + 1
        || (int)$cleanPdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn()
            !== PROMPT_E_R2_EXPORT_VARIANTS + 1
    ) {
        throw new RuntimeException('Real XML non-collision scenario did not promote the complete export.');
    }

    $collisionPdo = promptER2Database($server, $databases[1]);
    $collisionRun = shopCatalogStage($collisionPdo, $catalog);
    promptER2ResolvePending($collisionPdo, $collisionRun['run_id']);
    // Deliberately model a legacy/adversarial manual SKU created before the
    // reserved KP- validator. The current manual writer would reject it, but
    // promotion must still protect catalogs that already contain such a row.
    promptER2InsertManualProduct($collisionPdo, $collisionSku, false);
    $before = promptER2CanonicalCounts($collisionPdo);
    $message = '';
    try {
        shopCatalogPromote($collisionPdo, $collisionRun['run_id'], 7, true);
        throw new RuntimeException('Real XML collision scenario unexpectedly promoted.');
    } catch (ShopCatalogPromotionException $exception) {
        $message = $exception->getMessage();
    }
    $after = promptER2CanonicalCounts($collisionPdo);
    if ($before !== $after
        || $before !== ['promotions' => 0, 'products' => 1, 'variants' => 1]
        || !str_contains($message, '`' . $collisionSku . '`')
        || !str_contains($message, 'Přejmenujte v administraci ručně založená SKU')
        || !str_contains($message, 'před prvním zápisem')
    ) {
        throw new RuntimeException('Real XML collision did not fail before the first promotion INSERT.');
    }

    echo json_encode([
        'ok' => true,
        'export' => [
            'sha256' => (string)$catalog['source']['sha256'],
            'products' => (int)$catalog['summary']['products'],
            'variants' => (int)$catalog['summary']['variants'],
        ],
        'non_collision' => [
            'manual_sku' => $nonCollisionSku,
            'promoted_products' => $cleanPromotion['products'],
            'promoted_variants' => $cleanPromotion['variants'],
        ],
        'collision' => [
            'manual_sku' => $collisionSku,
            'before' => $before,
            'after' => $after,
            'message' => $message,
        ],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    foreach ($databases as $database) {
        $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    }
}

function promptER2Database(PDO $server, string $database): PDO
{
    if (preg_match('/^evidence_prompt_e_r2_real_(?:ok|collision)$/D', $database) !== 1) {
        throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
    }
    $server->exec(
        'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec(
        'CREATE TABLE treneri('
        . 'id INT NOT NULL PRIMARY KEY,jmeno VARCHAR(100) NOT NULL'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
    foreach ([
        '20260802170000_shop_catalog_staging.php',
        '20260802190000_shop_catalog_review.php',
        '20260802210000_shop_canonical_catalog.php',
        '20260816200000_shop_manual_catalog_origin.php',
    ] as $filename) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) {
            throw new RuntimeException('Prompt E R2 MariaDB fixture migration failed: ' . $filename);
        }
    }
    return $pdo;
}

function promptER2ResolvePending(PDO $pdo, int $runId): void
{
    $statement = $pdo->prepare(
        "SELECT id FROM shop_catalog_product_candidates WHERE run_id=? AND review_status='pending' ORDER BY id"
    );
    $statement->execute([$runId]);
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $candidateId) {
        shopCatalogReviewProduct(
            $pdo,
            $runId,
            (int)$candidateId,
            7,
            'approve',
            'goods',
            'Prompt E R2: explicit approval in disposable real XML smoke.'
        );
    }
}

function promptER2InsertManualProduct(PDO $pdo, string $sku, bool $enforceReservedPrefix): void
{
    $externalKey = shopCatalogManualExternalProductKey();
    shopCatalogAssertManualExternalProductKey($externalKey);
    if ($enforceReservedPrefix) {
        shopCatalogAssertManualSku($sku);
    }
    shopCatalogAssertProductOrigin(ShopCatalogOrigin::MANUAL, null, null, 7);
    shopCatalogAssertVariantOrigin(ShopCatalogOrigin::MANUAL, null, 7, ShopCatalogOrigin::MANUAL);
    $statement = $pdo->prepare(
        'INSERT INTO shop_products '
        . '(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,'
        . "offer_type,catalog_status) VALUES(NULL,NULL,'manual',7,?,'Prompt E R2 manual','goods','draft')"
    );
    $statement->execute([$externalKey]);
    $productId = (int)$pdo->lastInsertId();
    $statement = $pdo->prepare(
        'INSERT INTO shop_variants '
        . '(product_id,source_candidate_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,'
        . "amount_minor,currency,catalog_status) VALUES(?,NULL,'manual',7,?,'{}','fixed',10000,'CZK','draft')"
    );
    $statement->execute([$productId, $sku]);
}

/** @return array{promotions:int,products:int,variants:int} */
function promptER2CanonicalCounts(PDO $pdo): array
{
    return [
        'promotions' => (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn(),
        'products' => (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn(),
        'variants' => (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn(),
    ];
}
