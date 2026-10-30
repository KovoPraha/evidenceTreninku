<?php

declare(strict_types=1);

// Launch explicitly with php -S 127.0.0.1:18413; never include from the application.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['SERVER_NAME'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/assets/[a-zA-Z0-9_./-]+$~D', $path) === 1 && !str_contains($path, '..')) {
    return false;
}
if ($path === '/submitted' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Synthetic submission received.';
    return;
}
if (preg_match('~^/(public|family|athlete|trainer)\.html$~D', $path) === 1) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__, 2) . '/output/playwright' . $path);
    return;
}
http_response_code(404);
