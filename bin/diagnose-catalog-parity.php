<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** No actor, reason, customer, cost, token or arbitrary audit payload leaves the server.
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function catalogParityPublicEvent(array $row): array
{
    $before = json_decode((string) ($row['before_json'] ?? 'null'), true);
    $after = json_decode((string) ($row['after_json'] ?? 'null'), true);
    $before = is_array($before) ? $before : [];
    $after = is_array($after) ? $after : [];
    $fields = [];
    foreach (['public_name', 'public_summary', 'variant_name', 'amount_minor', 'currency', 'catalog_status', 'sort_order', 'image_url'] as $field) {
        $a = $before[$field] ?? null;
        $b = $after[$field] ?? null;
        if ($a !== $b && (is_scalar($a) || $a === null) && (is_scalar($b) || $b === null)) {
            $fields[$field] = ['before' => $a, 'after' => $b];
        }
    }
    return ['event_id' => (int) $row['id'], 'product_id' => (int) $row['product_id'],
        'variant_id' => isset($row['variant_id']) ? (int) $row['variant_id'] : null,
        'action' => (string) $row['action'], 'at_utc' => (string) $row['created_at'], 'public_changes' => $fields];
}

/** Fixed-window, bounded SELECT-only forensic read; never bootstrap the application or its session. */
function diagnoseCatalogParityMain(): int
{
    $pdo = null;
    $stage = 'configuration';
    try {
        $root = realpath((string) getenv('APP_ROOT'));
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || $root === false || !is_file($root . '/config.php')) {
            throw new RuntimeException('Expected production root.');
        }
        $configHash = hash_file('sha256', $root . '/config.php');
        require $root . '/config.php';
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
            if (!defined($constant)) {
                throw new RuntimeException('Database configuration unavailable.');
            }
        }
        $stage = 'read_only_connection';
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
        $pdo->exec("SET SESSION time_zone='+00:00'");
        $pdo->exec('START TRANSACTION READ ONLY');
        $window = ['2026-11-10 00:00:00', '2026-11-11 00:00:00'];
        $stage = 'audit_events';
        $events = $pdo->prepare('SELECT id,product_id,variant_id,action,before_json,after_json,created_at '
            . 'FROM shop_catalog_admin_events WHERE created_at>=? AND created_at<? ORDER BY created_at,id LIMIT 251');
        $events->execute($window);
        $rows = $events->fetchAll();
        if (count($rows) > 250) {
            throw new RuntimeException('Forensic event bound exceeded.');
        }
        $publicEvents = array_map('catalogParityPublicEvent', $rows);
        $stage = 'stock_history';
        $stock = [];
        foreach (['shop_inventory_movements', 'shop_stock_movements'] as $table) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS '
                . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME IN ('variant_id','created_at')");
            $exists->execute([$table]);
            if ((int) $exists->fetchColumn() !== 2) {
                $stock[$table] = ['available' => false];
                continue;
            }
            $query = $pdo->prepare('SELECT v.product_id,m.variant_id,COUNT(*) AS events,MIN(m.created_at) AS first_at_utc,'
                . 'MAX(m.created_at) AS last_at_utc FROM `' . $table . '` m JOIN shop_variants v ON v.id=m.variant_id '
                . 'WHERE m.created_at>=? AND m.created_at<? GROUP BY v.product_id,m.variant_id ORDER BY v.product_id,m.variant_id LIMIT 251');
            $query->execute($window);
            $result = $query->fetchAll();
            if (count($result) > 250) {
                throw new RuntimeException('Forensic stock bound exceeded.');
            }
            $stock[$table] = ['available' => true, 'groups' => $result];
        }
        $stage = 'source_identity';
        $source = [];
        foreach (['booking/eshop.php', 'includes/ui_shell.php', 'includes/shop_catalog_page_read.php',
            'includes/shop_catalog_cards.php', 'includes/shop_storefront.php', 'includes/shop_member_pricing_read.php'] as $file) {
            $source[$file] = hash_file('sha256', $root . '/' . $file);
        }
        $pdo->exec('ROLLBACK');
        $sameConfig = $configHash === hash_file('sha256', $root . '/config.php');
        if (!$sameConfig) {
            throw new RuntimeException('Configuration changed during read.');
        }
        echo json_encode(
            ['ok' => true, 'read_only_transaction' => true, 'rolled_back' => true,
            'configuration_unchanged' => true, 'captured_utc' => gmdate('c'), 'window_utc' => $window,
            'public_events' => $publicEvents, 'stock_activity' => $stock, 'source_sha256' => $source],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ), "\n";
        return 0;
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['ok' => false, 'stage' => $stage, 'error_class' => get_class($error)], JSON_THROW_ON_ERROR), "\n";
        return 1;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(diagnoseCatalogParityMain());
}
