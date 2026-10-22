<?php
declare(strict_types=1);

/** Fixed-target, read-only probe. Never loads a worker or creates an order/parcel. */
function diagnoseProductionPacketa(): int
{
    $stage = 'target';
    $pdo = null;
    $bufferLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        if (PHP_SAPI !== 'cli' || getenv('APP_HOST') !== 'kis.kovopraha.cz'
            || getenv('KIS_PACKETA_DIAGNOSTIC_CONFIRM') !== 'OVERIT-PACKETU-VYPNUTOU') {
            throw new RuntimeException('Unexpected target.');
        }
        $root = realpath((string) getenv('KIS_PACKETA_DIAGNOSTIC_ROOT'));
        if ($root === false || !is_file($root . '/config.php')) throw new RuntimeException('Missing config.');
        $stage = 'config';
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        require $root . '/config.php';
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false
            || !defined('APP_BASE_URL') || APP_BASE_URL !== 'https://kis.kovopraha.cz'
            || (defined('SHOP_PACKETA_LOCAL_DEMO') && SHOP_PACKETA_LOCAL_DEMO !== false)) {
            throw new RuntimeException('Production identity or simulation mismatch.');
        }
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
            if (!defined($constant)) throw new RuntimeException('Missing DB configuration.');
        }
        $stage = 'connection';
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        // Session default also protects any subsequent transaction on this connection.
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        $stage = 'deployed_code';
        require_once $root . '/includes/shop_checkout.php';
        if (shopPacketaLocalDemoEnabled()) throw new RuntimeException('Simulation active.');
        $stage = 'disabled_settings';
        $settings = shopPacketaSettings($pdo);
        if ($settings['enabled'] !== false || $settings['price_minor'] !== 15000
            || preg_match('/^[A-Za-z0-9]{16}$/D', $settings['widget_api_key']) !== 1) {
            throw new RuntimeException('Unexpected shipping settings.');
        }
        $snapshot = static function (PDO $connection): array {
            $orders = $connection->query('SELECT COUNT(*) AS count,COALESCE(MAX(id),0) AS max_id FROM shop_orders')->fetch(PDO::FETCH_ASSOC);
            $queue = $connection->query('SELECT id,status,attempts,sent_at FROM club_event_notifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $statuses = [];
            $attempts = 0;
            foreach ($queue as $row) {
                $statuses[$row['status']] = ($statuses[$row['status']] ?? 0) + 1;
                $attempts += (int) $row['attempts'];
            }
            return ['orders' => ['count' => (int) $orders['count'], 'max_id' => (int) $orders['max_id']],
                'queue' => ['statuses' => $statuses, 'attempts' => $attempts,
                    'metadata_sha256' => hash('sha256', json_encode($queue, JSON_THROW_ON_ERROR))]];
        };
        $before = $snapshot($pdo);
        $stage = 'published_goods';
        $product = $pdo->query('SELECT id AS product_id,offer_type,packeta_shipping_enabled FROM shop_products WHERE id=93')->fetch(PDO::FETCH_ASSOC);
        $variant = null;
        foreach (shopStorefrontProducts($pdo) as $candidate) {
            if ((int) $candidate['product_id'] === 93 && (int) $candidate['variant_id'] === 576) $variant = $candidate;
        }
        if (!$product || !$variant || !shopPacketaCartEligible([$product])) throw new RuntimeException('Goods unavailable.');
        $stage = 'disabled_checkout_guard';
        $blocked = false;
        try {
            shopPacketaCheckoutChoice($pdo, [$product], 'packeta_zbox', '22312', 15000);
        } catch (ShopPacketaException $exception) {
            $blocked = $exception->getMessage() === 'Doručení do Z-BOXu není pro tento košík dostupné.';
        }
        if (!$blocked) throw new RuntimeException('Disabled shipping accepted.');
        $personal = shopPacketaCheckoutChoice($pdo, [$product], 'personal_pickup', '', 0);
        if ($personal !== ['method' => 'personal_pickup', 'price_minor' => 0, 'point' => null]) {
            throw new RuntimeException('Unexpected pickup result.');
        }
        $stage = 'live_zbox_validation';
        $box = shopPacketaResolveBox('22312', null, $settings['widget_api_key']);
        $rejected = [];
        foreach (['39209', '999000001'] as $id) {
            $stage = 'negative_validation_' . $id;
            try {
                shopPacketaResolveBox($id, null, $settings['widget_api_key']);
                $rejected[$id] = false;
            } catch (ShopPacketaException $exception) {
                // A network failure must never be reported as a correct negative result.
                $rejected[$id] = $exception->getMessage() === 'Vybraný Z-BOX není dostupný. Vyberte jiný.';
            }
            if (!$rejected[$id]) throw new RuntimeException('Negative validation inconclusive.');
        }
        $stage = 'final_snapshot';
        $pdo->rollBack();
        $pdo->beginTransaction();
        $after = $snapshot($pdo);
        $settingsUnchanged = shopPacketaSettings($pdo) === $settings;
        $pdo->rollBack();
        $result = ['ok' => $before === $after && $settingsUnchanged, 'target' => 'kis.kovopraha.cz',
            'shipping_enabled' => false, 'simulation_enabled' => false, 'price_minor' => 15000,
            'public_key_length' => strlen($settings['widget_api_key']), 'read_only_session' => true,
            'disabled_checkout_rejected' => $blocked, 'personal_pickup_valid' => true,
            'published_goods' => ['product_id' => 93, 'variant_id' => 576,
                'packeta_eligible' => true, 'amount_minor' => (int) $variant['amount_minor']],
            'live_box' => $box, 'rejected_points' => $rejected,
            'before' => $before, 'after' => $after, 'settings_unchanged' => $settingsUnchanged,
            'full_order_created' => false, 'enabled_checkout_uat' => false];
        $exitCode = $result['ok'] ? 0 : 3;
    } catch (Throwable) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $result = ['ok' => false, 'stage' => $stage, 'target' => 'kis.kovopraha.cz'];
        $exitCode = 2;
    } finally {
        while (ob_get_level() > $bufferLevel) ob_end_clean();
        restore_error_handler();
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    return $exitCode;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(diagnoseProductionPacketa());
}
