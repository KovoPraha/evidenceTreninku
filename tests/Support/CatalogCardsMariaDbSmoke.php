<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/CatalogCardsFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';
$host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Only isolated localhost MariaDB is allowed.');
}
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'wave3_cards_' . bin2hex(random_bytes(8));
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
        $hashes = [];
        foreach (['reference', 'cards'] as $profile) {
            $samples = [];
            for ($iteration = 0; $iteration < 35; $iteration++) {
                $pdo->queries = [];
                memory_reset_peak_usage();
                $start = hrtime(true);
                $output = $profile === 'reference' ? Tests\Support\catalogCardsReference($pdo) : shopPublicCatalogCards($pdo);
                $elapsed = (hrtime(true) - $start) / 1000000;
                if ($iteration >= 5) {
                    $samples[] = $elapsed;
                }
                $metadata = count(array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'information_schema')));
                if ($profile === 'cards' && ($metadata > 25 || count($pdo->queries) > 8 * $count + 50)) {
                    throw new RuntimeException('Card query budget exceeded.');
                }
                if (count($output) !== $count || $output[0]['program_offers'][0]['available_count'] !== 10) {
                    throw new RuntimeException('Card output changed.');
                }
                $hash = hash('sha256', json_encode($output, JSON_THROW_ON_ERROR));
                $hashes[$profile] ??= $hash;
                if ($hashes[$profile] !== $hash) {
                    throw new RuntimeException('Card output changed between iterations.');
                }
            }
            sort($samples);
            $results[] = ['programs' => $count, 'profile' => $profile, 'sql_count' => count($pdo->queries),
                'metadata_queries' => $metadata, 'median_ms' => ($samples[14] + $samples[15]) / 2,
                'p95_ms' => $samples[28], 'peak_bytes' => memory_get_peak_usage(true),
                'output_sha256' => $hashes[$profile], 'samples_ms' => $samples];
        }
        if ($hashes['reference'] !== $hashes['cards']) {
            throw new RuntimeException('Cards differ from the original page presentation.');
        }
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,42,NULL,'active')");
        if (shopPublicCatalogCards($pdo)[0]['program_offers'][0]['available_count'] !== 9) {
            throw new RuntimeException('A new card read reused stale capacity.');
        }
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }
    }
    echo json_encode(
        ['database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION),
        'synthetic_only' => true, 'warmup' => 5, 'measured' => 30, 'results' => $results],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $database . '`');
}
