<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';
$host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Only isolated localhost MariaDB is allowed.');
}
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'wave5_offers_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$results = [];
try {
    foreach ([1, 50, 401] as $count) {
        $pdo = new Tests\Support\StorefrontCountingPdo($dsn . ';dbname=' . $database, 'root', '');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $count !== 401);
        Tests\Support\programStorefrontFixture($count, $pdo);
        $cards = shopPublicCatalogCards($pdo);
        $offerQueries = array_values(array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'SELECT o.*,p.name AS program_name')));
        $linkQueries = array_values(array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'FROM club_program_physical_teams WHERE')));
        if (count($cards) !== $count || count($offerQueries) !== (int) ceil($count / 400) || count($linkQueries) !== count($offerQueries)
            || count($pdo->queries) > 2 * $count + 20 + 2 * (int) ceil($count / 80) + 2 * (int) ceil($count / 400)) {
            throw new RuntimeException('Offer batch budget or output changed.');
        }
        $results[] = ['programs' => $count, 'sql_count' => count($pdo->queries), 'offer_queries' => count($offerQueries),
            'link_queries' => count($linkQueries), 'max_parameters' => max(array_map(static fn (string $sql): int => substr_count($sql, '?'), $offerQueries)),
            'native_prepares' => $count === 401, 'output_sha256' => hash('sha256', json_encode($cards, JSON_THROW_ON_ERROR))];
        $context = new ClubProgramReadContext($pdo);
        $context->warmOffersForVariants(range(1, $count));
        foreach (range(1, $count) as $id) {
            if (clubProgramOfferForVariant($pdo, $id) !== $context->offer($id)) {
                throw new RuntimeException('Full batched offer differs from canonical offer.');
            }
        }
        if ($count > 1) {
            $pdo->exec('UPDATE club_program_offers SET capacity=1 WHERE id=1');
            $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,2),(1,1)');
            $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,2,42,NULL,'active')");
            $fresh = new ClubProgramReadContext($pdo);
            $fresh->warmOffersForVariants([1, 2]);
            if ($fresh->offer(1) !== clubProgramOfferForVariant($pdo, 1) || $fresh->offer(1)['available_count'] !== 0
                || array_keys($fresh->offer(1)['physical_team_states']) !== [1, 2]) {
                throw new RuntimeException('Shared capacity or ordered links changed.');
            }
            $pdo->beginTransaction();
            try {
                if (clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']) {
                    throw new RuntimeException('Locking read reused old capacity or physical links.');
                }
            } finally {
                $pdo->rollBack();
            }
            // Malformed legacy duplicate links must keep canonical selection.
            $pdo->exec("INSERT INTO club_program_offers VALUES(9999,1,1,1,'active',1,1,'2026-01-01','2099-12-31',NULL,NULL,9)");
            $legacy = new ClubProgramReadContext($pdo);
            $legacy->warmOffersForVariants([1]);
            if ($legacy->offer(1) !== clubProgramOfferForVariant($pdo, 1)) {
                throw new RuntimeException('Duplicate variant no longer uses canonical selection.');
            }
        }
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }
    }
    echo json_encode(['database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION), 'synthetic_only' => true,
        'canonical_rows_equal' => true, 'shared_capacity_equal' => true, 'locking_read_fresh' => true,
        'duplicate_fallback_equal' => true, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $database . '`');
}
