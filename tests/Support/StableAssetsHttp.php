<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$mode = $argv[2] ?? '--compare';
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($mode === '--seed') {
    // Manual synthetic goods have no import-source FK and no time-dependent program sale window.
    foreach (range(10001, 10024) as $id) {
        $admin->exec("INSERT INTO shop_products(id,origin,external_product_key,name,offer_type,catalog_status)
            VALUES($id,'manual','LOCALHOST-$id','LOCALHOST item $id','goods','active')");
        $admin->exec("INSERT INTO shop_variants(product_id,origin,sku,attributes_json,price_mode,amount_minor,currency,
            stock_quantity_decimal,unit_code,visible,catalog_status)
            VALUES($id,'manual','LOCALHOST-$id','{}','fixed',$id,'CZK',10,'piece',1,'active')");
        $admin->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note)
            VALUES($id,'active','LOCALHOST item $id','SYNTHETIC ONLY','Synthetic fixture')");
        $admin->exec("INSERT INTO shop_product_images(product_id,image_url,sort_order)
            VALUES($id,'uploads/shop-products/00000000000000000000000000000001.jpg',0)");
    }
    exit;
}
$checksums = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected owned table.');
        }
        // Login/rate-limit metadata is intentionally outside business data parity.
        if (preg_match('/auth|session|login|rate_limit/', $table)) {
            continue;
        }
        $result[$table] = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $result;
};
$normalize = static function (array $response): string {
    $html = str_replace(["\r\n", 'http://127.0.0.1:18099', 'http://127.0.0.1:18100'], ["\n", 'OWN_ORIGIN', 'OWN_ORIGIN'], $response['body']);
    if (preg_match("/nonce-([^']+)/", $response['headers']['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'OWN_NONCE', $html);
    }
    $html = (string) preg_replace('/(name="csrf_token"\s+value=")[^"]*(")/', '$1OWN_CSRF$2', $html);
    foreach (array_keys(require dirname(__DIR__, 2) . '/includes/ui_asset_versions.php') as $path) {
        $html = (string) preg_replace('~(' . preg_quote('/' . $path, '~') . '\?v=)(?:[0-9]+|[a-f0-9]{64})(["\'])~', '$1OWN_VERSION$2', $html);
    }
    // Exactly the approved catalog marker repair; no product values or scripts are removed.
    return str_replace('data-app-csp-handler="' . "\n    OWN_NONCE\"", 'data-app-csp-handler="OWN_NONCE"', $html);
};
$cases = ['login' => '/booking/prihlaseni.php', 'registration' => '/booking/registrace.php',
    'athlete' => '/booking/sportovec_prihlaseni.php', 'reset' => '/booking/zapomenute_heslo.php',
    'catalog' => '/booking/eshop.php', 'price' => '/booking/eshop.php?razeni=price',
    'page2' => '/booking/eshop.php?strana=2', 'search' => '/booking/eshop.php?q=10010',
    'empty' => '/booking/eshop.php?q=NOTFOUND'];
$beforeData = $checksums();
$report = ['complete' => false, 'signatures' => [], 'samples' => [], 'warmups' => 4, 'pairs' => 17];
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        foreach ($cases as $case => $path) {
            $response = fullHttpRequest($rt, $output, 'stable-' . $variant, $path);
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $response['body'])) {
                file_put_contents($output . '/failure-private.html', $response['body']);
                throw new RuntimeException('HTTP case failed: ' . $case . ':' . $response['status']);
            }
            $signature = hash('sha256', $normalize($response));
            $report['signatures'][$case] ??= $signature;
            if ($report['signatures'][$case] !== $signature) {
                file_put_contents($output . '/failure-' . $variant . '-private.html', $normalize($response));
                throw new RuntimeException('Whole HTML differs: ' . $case . ':' . $variant);
            }
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'], 'ttfb_ms' => $response['ttfb_ms']];
            }
        }
    }
}
if ($checksums() !== $beforeData) {
    throw new RuntimeException('Canonical data changed.');
}
$report['tables_unchanged'] = count($beforeData);
$report['complete'] = true;
file_put_contents($output . '/stable-http.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Stable assets whole HTTP parity PASS.\n";
