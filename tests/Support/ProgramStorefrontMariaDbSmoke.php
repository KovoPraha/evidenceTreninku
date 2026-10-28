<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ProgramStorefrontFixture.php';
$root = $argv[1] ?? dirname(__DIR__, 2);
require_once $root . '/includes/shop_public_navigation.php';
$baseline = in_array('--baseline', $argv, true);
$host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Only isolated localhost MariaDB is allowed.');
}
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'wave2_program_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$results = [];
try {
    foreach ([1, 10, 50] as $count) {
        $pdo = new Tests\Support\StorefrontCountingPdo($dsn . ';dbname=' . $database, 'root', '');
        Tests\Support\programStorefrontFixture($count, $pdo);
        foreach ([['shop_variants', 'product_id'], ['shop_product_categories', 'product_id'],
            ['club_program_offers', 'variant_id'], ['club_program_offers', 'product_id'],
            ['club_program_enrollments', 'offer_id'], ['club_program_physical_teams', 'program_id']] as [$table, $column]) {
            $pdo->exec("CREATE INDEX idx_$column ON $table($column)");
        }
        foreach (['navigation', 'catalog', 'detail_with_navigation'] as $profile) {
            $samples = [];
            for ($iteration = 0; $iteration < 35; $iteration++) {
                $pdo->queries = [];
                $pdo->productRows = 0;
                memory_reset_peak_usage();
                $start = hrtime(true);
                $output = match ($profile) {
                    'navigation' => shopPublicNavigationItems($pdo),
                    'catalog' => shopPublicSaleableCatalog($pdo),
                    default => [shopStorefrontProductDetail($pdo, 1), shopPublicNavigationItems($pdo)],
                };
                $elapsed = (hrtime(true) - $start) / 1000000;
                if ($iteration >= 5) {
                    $samples[] = $elapsed;
                }
                $metadata = count(array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'information_schema')));
                if (!$baseline && ($metadata > 30 || count($pdo->queries) > 8 * $count + 55)) {
                    throw new RuntimeException('Program projection query budget exceeded.');
                }
                $visible = $profile === 'catalog' ? count($output) : ($profile === 'navigation' ? $output[0]['product_count'] : $output[1][0]['product_count']);
                if ($visible !== $count) {
                    throw new RuntimeException('Program projection lost visible products.');
                }
            }
            sort($samples);
            $results[] = ['programs' => $count, 'profile' => $profile, 'sql_count' => count($pdo->queries),
                'metadata_queries' => $metadata, 'product_rows' => $pdo->productRows,
                'median_ms' => ($samples[14] + $samples[15]) / 2, 'p95_ms' => $samples[28],
                'peak_bytes' => memory_get_peak_usage(true), 'samples_ms' => $samples,
                'output_sha256' => hash('sha256', json_encode($output, JSON_THROW_ON_ERROR))];
        }
        $explain = $pdo->query('EXPLAIN SELECT o.* FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE o.variant_id=1')->fetchAll(PDO::FETCH_ASSOC);
        if (!$baseline) {
            $context = new ClubProgramReadContext($pdo);
            if (!$context->saleState(1)['saleable']) {
                throw new RuntimeException('Expected saleable program.');
            }
            $pdo->exec('UPDATE club_program_offers SET capacity=0 WHERE id=1');
            $pdo->beginTransaction();
            try {
                if (clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']) {
                    throw new RuntimeException('Locking read reused a stale public projection.');
                }
            } finally {
                $pdo->rollBack();
            }
        }
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }
    }
    echo json_encode(['database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION),
        'baseline' => $baseline, 'synthetic_only' => true, 'warmup' => 5, 'measured' => 30,
        'results' => $results, 'offer_lookup_explain' => $explain], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $database . '`');
}
