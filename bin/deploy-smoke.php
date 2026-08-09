<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * HTTP smoke test spouštěný ze serveru, když se GitHub runner k webu
 * nedostane přímo (hosting blokuje IP rozsahy datacenter).
 *
 * Hosting používá omezený shell bez `php -r`, bez argumentů skriptů a bez
 * průchodu externích proměnných prostředí; hodnoty proto nastavuje
 * vygenerovaný putenv() bootstrap a spouští se holé `php soubor.php`.
 * DEPLOY_PROBE=1 zároveň ověřuje, že env proměnné skutečně procházejí.
 *
 * Vstup: env SMOKE_URL (plná URL ke kontrole) a DEPLOY_PROBE=1.
 * Výstup: JSON {"ok":true,"http":<status>,"via":"curl|stream"} na STDOUT,
 * při problému nenulový exit kód a hláška na STDERR. Skript nic nemění.
 */

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if ((string)getenv('DEPLOY_PROBE') !== '1') {
    $fail('Env DEPLOY_PROBE=1 nedorazila — shell nepropustil proměnné prostředí.');
}

$url = trim((string)getenv('SMOKE_URL'));
if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || !str_starts_with($url, 'https://')) {
    $fail('SMOKE_URL musí být platná https:// URL.');
}

$status = null;
$via = null;

if (extension_loaded('curl')) {
    $handle = curl_init($url);
    if ($handle === false) {
        $fail('curl_init selhal.');
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
    ]);
    if (curl_exec($handle) === false) {
        $fail('curl: ' . curl_error($handle));
    }
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $via = 'curl';
} elseif (filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 60,
    ]]);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? null;
    if ($body === false && !is_array($headers)) {
        $fail('HTTP požadavek se ze serveru nepodařilo odeslat.');
    }
    foreach (is_array($headers) ? $headers : [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$line, $matches) === 1) {
            $status = (int)$matches[1];
            break;
        }
    }
    $via = 'stream';
} else {
    $fail('Server nemá curl extension ani allow_url_fopen; smoke test nelze provést.');
}

if (!is_int($status) || $status < 100 || $status > 599) {
    $fail('HTTP status se nepodařilo zjistit.');
}

echo json_encode(['ok' => true, 'http' => $status, 'via' => $via], JSON_THROW_ON_ERROR) . PHP_EOL;
