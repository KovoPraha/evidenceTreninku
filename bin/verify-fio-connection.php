<?php
declare(strict_types=1);

/** Read-only GitHub Actions probe. Never print the Fio token or transaction data. */
function fioConnectionNormalizeIban(string $value): string
{
    return strtoupper((string)preg_replace('/\s+/', '', $value));
}

function fioConnectionProductionAccount(): void
{
    $root = rtrim((string)getenv('APP_ROOT'), '/');
    $host = (string)getenv('APP_HOST');
    if ($root === '' || $host === '' || !is_file($root . '/config.php')) {
        throw new RuntimeException('Production configuration is unavailable.');
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    require $root . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('Expected the production environment.');
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    require_once $root . '/includes/shop_bank_settings.php';
    $iban = fioConnectionNormalizeIban((string)shopBankSettingsEffective($pdo)['iban']);
    if ($iban === '') throw new RuntimeException('Production bank IBAN is missing.');
    echo json_encode([
        'iban' => $iban,
        'fio_enabled' => defined('FIO_IMPORT_ENABLED') && FIO_IMPORT_ENABLED === true,
        'auto_confirm_enabled' => defined('FIO_AUTO_CONFIRM_ENABLED') && FIO_AUTO_CONFIRM_ENABLED === true,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
}

function fioConnectionVerifyToken(): void
{
    $token = (string)getenv('FIO_API_TOKEN_VALUE');
    $expected = fioConnectionNormalizeIban((string)getenv('EXPECTED_IBAN'));
    if (preg_match('/^[A-Za-z0-9_-]{20,128}$/D', $token) !== 1 || $expected === '') {
        throw new RuntimeException('Fio token or expected IBAN is missing or invalid.');
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    $url = 'https://fioapi.fio.cz/v1/rest/periods/' . rawurlencode($token)
        . '/' . $today . '/' . $today . '/transactions.json';
    $handle = curl_init($url);
    if ($handle === false) throw new RuntimeException('Cannot initialize Fio request.');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: KovoPrahaEvidence/1.0'],
    ]);
    try {
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    } finally {
        curl_close($handle);
    }
    if (!is_string($body) || $status !== 200) {
        throw new RuntimeException('Fio read-only request failed with HTTP ' . $status . '.');
    }
    $document = json_decode($body, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    $actual = fioConnectionNormalizeIban((string)($document['accountStatement']['info']['iban'] ?? ''));
    if ($actual === '') {
        throw new RuntimeException('Fio response has no accountStatement.info.iban.');
    }
    if (!hash_equals($expected, $actual)) {
        throw new RuntimeException(sprintf(
            'Fio token belongs to a different account (KIS ends %s; Fio ends %s).',
            substr($expected, -4),
            substr($actual, -4)
        ));
    }
    echo json_encode(['ok' => true, 'fio_http_status' => $status, 'account_match' => true], JSON_THROW_ON_ERROR) . PHP_EOL;
}

try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI only.');
    match ($argv[1] ?? '') {
        'production-account' => fioConnectionProductionAccount(),
        'verify-token' => fioConnectionVerifyToken(),
        default => throw new RuntimeException('Unsupported probe mode.'),
    };
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fio connection probe failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
