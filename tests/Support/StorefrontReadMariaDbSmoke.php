<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/StorefrontReadFixture.php';
$root = $argv[1] ?? dirname(__DIR__, 2);
require_once $root . '/includes/shop_public_navigation.php';
$baseline = in_array('--baseline', $argv, true);
$host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !ctype_digit($port) || (int) $port > 65535 || (int) $port < 1) {
    throw new RuntimeException('Storefront benchmark requires an isolated localhost MariaDB.');
}
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'wave1_storefront_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$results = [];
try {
    foreach ([1, 50, 250, 1000] as $count) {
        foreach (['detail', 'manual_navigation', 'imported_navigation'] as $profile) {
            $pdo = new Tests\Support\StorefrontCountingPdo($dsn . ';dbname=' . $database, 'root', '');
            Tests\Support\storefrontReadFixture($count, $profile === 'imported_navigation', $pdo);
            // Same lookup indexes as the production table definitions; no application migration.
            $pdo->exec('CREATE INDEX idx_variants_product ON shop_variants(product_id)');
            $pdo->exec('CREATE INDEX idx_categories_product ON shop_product_categories(product_id)');
            $pdo->exec('CREATE INDEX idx_images_product ON shop_product_images(product_id)');
            $pdo->exec('CREATE INDEX idx_offers_product ON club_program_offers(product_id)');
            $pdo->exec('CREATE INDEX idx_offers_variant ON club_program_offers(variant_id)');
            $samples = [];
            for ($iteration = 0; $iteration < 35; $iteration++) {
                $pdo->queries = [];
                $pdo->productRows = 0;
                memory_reset_peak_usage();
                $start = hrtime(true);
                $output = $profile === 'detail' ? shopStorefrontProductDetail($pdo, 1) : shopPublicNavigationItems($pdo);
                $elapsed = (hrtime(true) - $start) / 1000000;
                $queryCount = count($pdo->queries);
                if ($iteration >= 5) {
                    $samples[] = $elapsed;
                }
                if (!$baseline && ($queryCount > ($profile === 'detail' ? 12 : 20)
                    || ($profile === 'detail' && $pdo->productRows !== 1))) {
                    throw new RuntimeException('Query/row budget exceeded: ' . $profile . '/' . $count);
                }
                if ($profile === 'detail' ? ($output['product_id'] ?? 0) !== 1 : array_column($output, 'product_count') !== [$count, $count]) {
                    throw new RuntimeException('Unexpected benchmark result.');
                }
            }
            $peakBytes = memory_get_peak_usage(true);
            $productRows = $pdo->productRows;
            $select = array_values(array_filter($pdo->queries, static fn (string $sql): bool =>
                str_contains($sql, 'FROM shop_products p JOIN shop_product_publications')))[0];
            $explain = $pdo->prepare('EXPLAIN ' . $select);
            $explain->execute(str_contains($select, 'AND p.id=?') ? [1] : []);
            sort($samples);
            $results[] = [
                'products' => $count, 'profile' => $profile, 'sql_count' => $queryCount, 'product_rows' => $productRows,
                'median_ms' => ($samples[14] + $samples[15]) / 2, 'p95_ms' => $samples[28],
                'peak_bytes' => $peakBytes, 'output_sha256' => hash('sha256', json_encode($output, JSON_THROW_ON_ERROR)),
                'samples_ms' => $samples, 'explain' => $explain->fetchAll(PDO::FETCH_ASSOC),
            ];
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
            }
        }
    }
    echo json_encode([
        'database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION),
        'baseline' => $baseline, 'warmup' => 5, 'measured' => 30, 'synthetic_only' => true, 'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $database . '`');
}
