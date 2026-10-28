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
$database = 'wave4_terms_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$results = [];
try {
    foreach ([1, 50, 81] as $count) {
        $pdo = new Tests\Support\StorefrontCountingPdo($dsn . ';dbname=' . $database, 'root', '');
        Tests\Support\programStorefrontFixture($count, $pdo);
        $pdo->exec('ALTER TABLE club_event_term_versions MODIFY scope_type VARCHAR(40),MODIFY scope_key VARCHAR(190),'
            . 'MODIFY consent_purpose VARCHAR(40),MODIFY status VARCHAR(20)');
        $pdo->exec('CREATE INDEX idx_terms_scope_current ON club_event_term_versions(scope_type,scope_key,consent_purpose,status,id)');
        $pdo->queries = [];
        $cards = shopPublicCatalogCards($pdo);
        $termsQueries = array_values(array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'FROM club_event_term_versions')));
        $expectedBatches = 2 * (int) ceil($count / 80);
        if (count($cards) !== $count || count($termsQueries) !== $expectedBatches || count($pdo->queries) > 4 * $count + 20) {
            throw new RuntimeException('Public terms batch budget or output changed.');
        }
        $results[] = ['programs' => $count, 'sql_count' => count($pdo->queries), 'terms_queries' => count($termsQueries),
            'max_parameters' => max(array_map(static fn (string $sql): int => substr_count($sql, '?'), $termsQueries)),
            'output_sha256' => hash('sha256', json_encode($cards, JSON_THROW_ON_ERROR))];
        // Compare all complete rows, including field types, with canonical reads.
        $context = new ClubProgramReadContext($pdo);
        $context->warmTermsForVariants(range(1, $count));
        for ($id = 1; $id <= $count; $id++) {
            if (clubProgramTermsEffective($pdo, $id, $id) !== clubProgramTermsEffective($pdo, $id, $id, false, $context)) {
                throw new RuntimeException('Batched terms differ from canonical terms.');
            }
        }
        // Real column collation remains authoritative, including case, accents
        // and trailing spaces. Do not replace SQL matching with PHP key matching.
        $pdo->exec("UPDATE club_event_term_versions SET scope_type='CLÜB_PRÓGRAM',scope_key='PRÓGRAM:1 ',"
            . "consent_purpose=UPPER(consent_purpose),status='ÁCTIVE ' WHERE scope_key='program:1'");
        $canonical = clubProgramTermsEffective($pdo, 1, 1);
        if ($canonical['program_consent'] === null || $canonical['program_cancellation'] === null) {
            throw new RuntimeException('Expected unicode_ci comparison fixture to match.');
        }
        $fresh = new ClubProgramReadContext($pdo);
        $fresh->warmTermsForVariants([1]);
        if ($canonical !== clubProgramTermsEffective($pdo, 1, 1, false, $fresh)) {
            throw new RuntimeException('Batch changed SQL collation semantics.');
        }
        $pdo->exec("INSERT INTO club_event_term_versions VALUES "
            . "(10000,'club_program_offer','offer:1','program_consent','Earlier active','active'),"
            . "(10001,'club_program_offer','offer:1','program_consent','VZOR — later active','active'),"
            . "(10002,'club_program_offer','offer:1','program_consent','Later archived','archived')");
        $latest = new ClubProgramReadContext($pdo);
        $latest->warmTermsForVariants([1]);
        if ($latest->saleState(1)['saleable'] || (int) $latest->term('offer', 1, 'program_consent')['id'] !== 10001) {
            throw new RuntimeException('Latest active draft must block program fallback.');
        }
        $pdo->beginTransaction();
        try {
            if (clubProgramVariantSaleState($pdo, 1, null, true, $fresh)['saleable']) {
                throw new RuntimeException('Locking read reused cached terms.');
            }
        } finally {
            $pdo->rollBack();
        }
        $explain = $pdo->query("EXPLAIN SELECT MAX(id) FROM club_event_term_versions WHERE scope_type='club_program' "
            . "AND scope_key='program:1' AND consent_purpose='program_consent' AND status='active'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }
    }
    echo json_encode(['database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION), 'synthetic_only' => true,
        'canonical_rows_equal' => true, 'collation_equal' => true, 'locking_read_fresh' => true,
        'results' => $results, 'max_lookup_explain' => $explain], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $database . '`');
}
