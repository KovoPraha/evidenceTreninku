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
require_once __DIR__ . '/CheckoutAssetsComparison.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($mode === '--seed') {
    require_once dirname(__DIR__, 2) . '/includes/club_program_terms.php';
    $admin->exec("UPDATE club_event_term_versions SET consent_text_plain='LOCALHOST NEPLATIT synteticky schvalene informace.' "
        . "WHERE scope_type='athlete_registration'");
    foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
        clubProgramTermsConfigure($admin, 7, 'program', 1, $purpose, 'LOCALHOST NEPLATIT synteticke podminky programu.', true);
        clubProgramTermsConfigure($admin, 7, 'program', 2, $purpose, 'LOCALHOST NEPLATIT priprava podminek.', true);
    }
    [$missingType, $missingKey] = clubProgramTermsScope('program', 2);
    $admin->prepare('UPDATE club_event_term_versions SET consent_text_plain=? WHERE scope_type=? AND scope_key=?')
        ->execute([CLUB_PROGRAM_TERM_DRAFT_MARKER . ' LOCALHOST incomplete terms', $missingType, $missingKey]);
    $admin->exec("UPDATE club_program_offers SET status='active',capacity=20000,starts_on='2020-01-01',ends_on='2099-12-31',"
        . "birth_year_from=2014,birth_year_to=2020 WHERE id IN (1,2)");
    $admin->exec("INSERT INTO shop_catalog_product_candidates(id,run_id,external_product_key,name,offer_type,classification_confidence,needs_manual_review,payload_json) "
        . "VALUES(9001,1,'LOCALHOST-CSS-GOODS','LOCALHOST NEPLATIT zbozi','goods','high',0,'{}')");
    $admin->exec("INSERT INTO shop_products(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status) "
        . "VALUES(9001,9001,1,'LOCALHOST-CSS-GOODS','LOCALHOST NEPLATIT zbozi','goods','active')");
    $admin->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note) "
        . "VALUES(9001,'active','LOCALHOST NEPLATIT zbozi','Synteticky neverejny test','Approved synthetic fixture')");
    $variants = $admin->prepare('INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,'
        . 'includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(?,9001,NULL,?,\'{}\',\'fixed\',?,\'CZK\',1,2100,?,1,\'active\')');
    foreach ([[900101, 'LOCALHOST-CSS-IN', 12500, '5.000000'], [900102, 'LOCALHOST-CSS-EMPTY', 12500, '0.000000'],
        [900103, 'LOCALHOST-CSS-UNKNOWN', 25000, null]] as $variant) {
        $variants->execute($variant);
    }
    $credential = ['email' => 'checkout-assets@example.invalid', 'password' => 'LOCALHOST-Neplatit-' . bin2hex(random_bytes(16))];
    $admin->prepare('UPDATE verejni_uzivatele SET email=?,heslo_hash=?,aktivni=1,email_overeno=1 WHERE id=10')
        ->execute([$credential['email'], password_hash($credential['password'], PASSWORD_DEFAULT)]);
    file_put_contents($output . '/checkout-credential-private.json', json_encode($credential, JSON_THROW_ON_ERROR));
    exit;
}
$normalize = static function (array $response): string {
    $html = str_replace("\r\n", "\n", $response['body']);
    if (preg_match("/nonce-([^']+)/", $response['headers']['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
    }
    if (preg_match_all('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $tokens)) {
        foreach (array_unique($tokens[1]) as $token) {
            $html = str_replace($token, 'SYNTHETIC_CSRF', $html);
        }
    }
    return checkoutAssetsComparisonHtml($html);
};
$credential = json_decode((string) file_get_contents($output . '/checkout-credential-private.json'), true, 512, JSON_THROW_ON_ERROR);
$login = [];
foreach (['before', 'after'] as $variant) {
    $rt = $runtime;
    $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
    $form = fullHttpRequest($rt, $output, 'checkout-account-' . $variant, '/booking/prihlaseni.php');
    if ($form['status'] !== 200 || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $form['body'], $token)) {
        throw new RuntimeException('Synthetic customer login form failed.');
    }
    $post = fullHttpRequest($rt, $output, 'checkout-account-' . $variant, '/booking/prihlaseni.php', [
        'csrf_token' => $token[1], 'email' => $credential['email'], 'heslo' => $credential['password'],
    ]);
    if ($post['status'] !== 302) {
        file_put_contents($output . '/login-failure-private.html', $post['body']);
        throw new RuntimeException('Synthetic customer login failed: ' . $post['status']);
    }
    $login[$variant] = 302;
}
$checksums = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected owned table.');
        }
        $result[$table] = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $result;
};
$beforeData = $checksums();
$cases = [
    'guest' => ['/booking/rychly_nakup.php?product_id=9001&variant_id=900101', 200, false, true],
    'guest-unknown-stock' => ['/booking/rychly_nakup.php?product_id=9001&variant_id=900103', 404, false, false],
    'guest-empty-stock' => ['/booking/rychly_nakup.php?product_id=9001&variant_id=900102', 404, false, false],
    'guest-missing-variant' => ['/booking/rychly_nakup.php?product_id=9001&variant_id=999999', 404, false, false],
    'guest-club-guard' => ['/booking/rychly_nakup.php?product_id=501&variant_id=601', 303, false, false],
    'program' => ['/booking/rychla_prihlaska.php?product_id=501&variant_id=601', 200, false, true],
    'program-missing-terms' => ['/booking/rychla_prihlaska.php?product_id=501&variant_id=602', 404, false, false],
    'program-goods-guard' => ['/booking/rychla_prihlaska.php?product_id=9001&variant_id=900101', 404, false, false],
    'program-account-redirect' => ['/booking/rychla_prihlaska.php?product_id=501&variant_id=601', 303, true, false],
    'control' => ['/booking/produkt.php?id=9001', 200, false, false],
];
$report = ['complete' => false, 'samples' => [], 'signatures' => [], 'invalid_csrf_denials' => [], 'customer_login' => $login];
$referenceBodies = [];
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        foreach ($cases as $case => [$path, $status, $authenticated, $target]) {
            $role = ($authenticated ? 'checkout-account-' : 'checkout-') . $variant;
            $response = fullHttpRequest($rt, $output, $role, $path);
            $body = $response['body'];
            if ($response['status'] !== $status || preg_match('/Fatal error|Warning:/', $body)) {
                file_put_contents($output . '/failure-private.html', $body);
                throw new RuntimeException('Checkout route failed: ' . $case . ':' . $response['status']);
            }
            if ($status === 200) {
                $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}"~', $body);
                $cdn = substr_count($body, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
                if ($local !== 1 || $cdn !== ($variant === 'before' && $target ? 1 : 0)) {
                    throw new RuntimeException('Unexpected checkout CSS resources.');
                }
            }
            $normalized = $normalize($response);
            $signature = hash('sha256', $response['status'] . "\n" . ($response['headers']['location'] ?? '') . "\n" . $normalized);
            $report['signatures'][$case] ??= $signature;
            $referenceBodies[$case] ??= $normalized;
            if ($report['signatures'][$case] !== $signature) {
                $reference = $referenceBodies[$case];
                $offset = 0;
                while ($offset < min(strlen($reference), strlen($normalized)) && $reference[$offset] === $normalized[$offset]) {
                    $offset++;
                }
                $safe = static fn (string $value): string => (string) preg_replace('~[A-Za-z0-9+/_-]{32,}~', '[OPAQUE]', $value);
                file_put_contents($output . '/http-difference.json', json_encode([
                    'case' => $case, 'offset' => $offset, 'before_bytes' => strlen($reference), 'after_bytes' => strlen($normalized),
                    'before' => $safe(substr($reference, max(0, $offset - 80), 300)),
                    'after' => $safe(substr($normalized, max(0, $offset - 80), 300)),
                ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
                file_put_contents($output . '/difference-' . $variant . '-private.html', $normalized);
                throw new RuntimeException('Whole checkout HTML differs: ' . $case . ':' . $variant);
            }
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'], 'ttfb_ms' => $response['ttfb_ms']];
            }
        }
        if ($round === -4) {
            foreach (['guest', 'program'] as $case) {
                $denied = fullHttpRequest($rt, $output, 'checkout-' . $variant, $cases[$case][0], [
                    'product_id' => $case === 'guest' ? 9001 : 501, 'variant_id' => $case === 'guest' ? 900101 : 601,
                    'csrf_token' => 'invalid', 'email' => 'invalid@example.invalid',
                ]);
                if ($denied['status'] !== 200 || !str_contains($denied['body'], 'Formulář vypršel.')) {
                    throw new RuntimeException('Invalid checkout CSRF was not rejected.');
                }
                $report['invalid_csrf_denials'][$variant][$case] = hash('sha256', $normalize($denied));
            }
        }
    }
}
if ($report['invalid_csrf_denials']['before'] !== $report['invalid_csrf_denials']['after']) {
    throw new RuntimeException('Invalid CSRF whole HTML changed.');
}
// Public catalog filtering already hides a program without effective program
// terms (404). Missing mandatory athlete notices reach the separate 409 guard.
require_once dirname(__DIR__, 2) . '/includes/athlete_registration.php';
$terms = athleteRegistrationCurrentTerms($admin);
$notice = $terms['member_data_notice'];
$changeNotice = $admin->prepare('UPDATE club_event_term_versions SET consent_text_plain=? WHERE id=?');
$changeNotice->execute(['', $notice['id']]);
try {
    foreach (['before', 'after'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        $denied = fullHttpRequest($rt, $output, 'checkout-' . $variant, $cases['program'][0]);
        if ($denied['status'] !== 409 || !str_contains($denied['body'], 'Přihlášku nelze dokončit')) {
            throw new RuntimeException('Missing mandatory notice was not denied.');
        }
        $report['missing_notice_denial'][$variant] = hash('sha256', $normalize($denied));
    }
} finally {
    $changeNotice->execute([$notice['text'], $notice['id']]);
}
if ($report['missing_notice_denial']['before'] !== $report['missing_notice_denial']['after']) {
    throw new RuntimeException('Missing-notice whole HTTP changed.');
}
if ($checksums() !== $beforeData) {
    throw new RuntimeException('Checkout GET/denial changed canonical data.');
}
$report['tables_unchanged'] = count($beforeData);
$report['complete'] = true;
file_put_contents($output . '/checkout-http.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Checkout whole HTTP and CSRF/guard parity PASS.\n";
