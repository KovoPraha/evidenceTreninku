<?php
declare(strict_types=1);

const KIS_STRIPE_BLOCK_BEGIN = '// BEGIN KIS MANAGED STRIPE SANDBOX';
const KIS_STRIPE_BLOCK_END = '// END KIS MANAGED STRIPE SANDBOX';

/** @param array<string,mixed> $input @return array{secret_key:string,publishable_key:string,webhook_secret:string,base_url:string} */
function kisProductionStripeValidate(array $input, string $expectedHost): array
{
    $secret = trim((string)($input['secret_key'] ?? ''));
    $publishable = trim((string)($input['publishable_key'] ?? ''));
    $webhook = trim((string)($input['webhook_secret'] ?? ''));
    $baseUrl = rtrim(trim((string)($input['base_url'] ?? '')), '/');

    if (preg_match('/^sk_test_[A-Za-z0-9_]+$/D', $secret) !== 1) {
        throw new RuntimeException('Stripe sandbox secret key is missing or invalid.');
    }
    if (preg_match('/^pk_test_[A-Za-z0-9_]+$/D', $publishable) !== 1) {
        throw new RuntimeException('Stripe sandbox publishable key is missing or invalid.');
    }
    if (preg_match('/^whsec_[A-Za-z0-9_]+$/D', $webhook) !== 1) {
        throw new RuntimeException('Stripe sandbox webhook secret is missing or invalid.');
    }

    $parts = parse_url($baseUrl);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string)($parts['host'] ?? '')) !== strtolower($expectedHost)
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        throw new RuntimeException('Stripe base URL must be the expected production HTTPS host.');
    }

    return [
        'secret_key' => $secret,
        'publishable_key' => $publishable,
        'webhook_secret' => $webhook,
        'base_url' => $baseUrl,
    ];
}

/** @param array{secret_key:string,publishable_key:string,webhook_secret:string,base_url:string} $settings */
function kisProductionStripeManagedBlock(array $settings): string
{
    return KIS_STRIPE_BLOCK_BEGIN . "\n"
        . "defined('STRIPE_ENABLED') || define('STRIPE_ENABLED', true);\n"
        . "defined('STRIPE_SECRET_KEY') || define('STRIPE_SECRET_KEY', " . var_export($settings['secret_key'], true) . ");\n"
        . "defined('STRIPE_PUBLISHABLE_KEY') || define('STRIPE_PUBLISHABLE_KEY', " . var_export($settings['publishable_key'], true) . ");\n"
        . "defined('STRIPE_WEBHOOK_SECRET') || define('STRIPE_WEBHOOK_SECRET', " . var_export($settings['webhook_secret'], true) . ");\n"
        . KIS_STRIPE_BLOCK_END;
}

function kisProductionStripeMergeConfig(string $config, string $block): string
{
    if (!str_starts_with(ltrim($config), '<?php')) {
        throw new RuntimeException('Production config.php is not a PHP file.');
    }

    $pattern = '/\R*' . preg_quote(KIS_STRIPE_BLOCK_BEGIN, '/') . '.*?'
        . preg_quote(KIS_STRIPE_BLOCK_END, '/') . '\R*/s';
    $withoutManaged = preg_replace($pattern, "\n", $config);
    if (!is_string($withoutManaged)) {
        throw new RuntimeException('Managed Stripe block could not be replaced.');
    }

    $openingPattern = '/\A(<\?php(?:\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?)/';
    $merged = preg_replace($openingPattern, "$1\n\n" . $block, $withoutManaged, 1, $count);
    if (!is_string($merged) || $count !== 1) {
        throw new RuntimeException('Managed Stripe block could not be inserted safely.');
    }

    token_get_all($merged, TOKEN_PARSE);
    return $merged;
}

/** @return array<string,mixed> */
function kisProductionStripeProbe(string $appRoot, string $appHost): array
{
    $_SERVER['HTTP_HOST'] = $appHost;
    $_SERVER['SERVER_NAME'] = $appHost;
    require $appRoot . '/config.php';
    require_once $appRoot . '/includes/shop_checkout.php';
    require_once $appRoot . '/includes/stripe_gateway.php';

    $settings = stripeSettingsFromConfig();
    try {
        shopBankSettingsFromConfig();
        $bankValid = true;
    } catch (Throwable) {
        $bankValid = false;
    }

    return [
        'ok' => true,
        'environment' => defined('JE_LOKALNE') && JE_LOKALNE === false ? 'production' : 'invalid',
        'bank_valid' => $bankValid,
        'stripe_enabled' => stripeIsEnabled($settings),
        'stripe_test_pair' => str_starts_with($settings['secret_key'], 'sk_test_')
            && str_starts_with($settings['publishable_key'], 'pk_test_'),
        'stripe_live_pair' => str_starts_with($settings['secret_key'], 'sk_live_')
            && str_starts_with($settings['publishable_key'], 'pk_live_'),
        'webhook_secret' => str_starts_with($settings['webhook_secret'], 'whsec_'),
        'base_url_ok' => $settings['base_url'] === 'https://' . $appHost,
    ];
}

function kisProductionStripeMain(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    if ((string)getenv('CONFIGURE_PRODUCTION_STRIPE') !== '1') {
        throw new RuntimeException('CONFIGURE_PRODUCTION_STRIPE=1 is required.');
    }

    $action = trim((string)getenv('STRIPE_CONFIG_ACTION'));
    $appRoot = rtrim(trim((string)getenv('APP_ROOT')), '/\\');
    $appHost = strtolower(trim((string)getenv('APP_HOST')));
    if ($appRoot === '' || $appHost === '' || !is_file($appRoot . '/config.php')) {
        throw new RuntimeException('Production app root, host, or config.php is unavailable.');
    }

    if ($action === 'probe') {
        echo json_encode(kisProductionStripeProbe($appRoot, $appHost), JSON_THROW_ON_ERROR) . PHP_EOL;
        return;
    }
    if ($action !== 'configure_test') {
        throw new RuntimeException('Unsupported Stripe configuration action.');
    }

    $secretFile = trim((string)getenv('STRIPE_SECRETS_FILE'));
    $backupDir = rtrim(trim((string)getenv('CONFIG_BACKUP_DIR')), '/\\');
    if ($secretFile === '' || !is_file($secretFile) || $backupDir === '') {
        throw new RuntimeException('Stripe secret bundle or backup directory is unavailable.');
    }
    $decoded = json_decode((string)file_get_contents($secretFile), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe secret bundle is invalid.');
    }
    $settings = kisProductionStripeValidate($decoded, $appHost);
    $block = kisProductionStripeManagedBlock($settings);
    $configPath = $appRoot . '/config.php';
    $oldConfig = (string)file_get_contents($configPath);
    $newConfig = kisProductionStripeMergeConfig($oldConfig, $block);
    $changed = !hash_equals(hash('sha256', $oldConfig), hash('sha256', $newConfig));

    if ($changed) {
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Production config backup directory could not be created.');
        }
        $backupPath = $backupDir . '/config-stripe-' . gmdate('Ymd-His') . '-'
            . substr(hash('sha256', $oldConfig), 0, 12) . '.php';
        if (file_put_contents($backupPath, $oldConfig, LOCK_EX) === false) {
            throw new RuntimeException('Production config backup could not be written.');
        }
        chmod($backupPath, 0600);

        $temporaryPath = $configPath . '.stripe-' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporaryPath, $newConfig, LOCK_EX) === false) {
            throw new RuntimeException('Temporary production config could not be written.');
        }
        chmod($temporaryPath, 0600);
        if (!rename($temporaryPath, $configPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Production config could not be activated atomically.');
        }
        chmod($configPath, 0600);
    }

    echo json_encode(['ok' => true, 'changed' => $changed, 'mode' => 'test'], JSON_THROW_ON_ERROR) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisProductionStripeMain();
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Stripe production configuration failed: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
