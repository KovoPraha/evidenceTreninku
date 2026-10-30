<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('APP_BASE_URL', 'http://127.0.0.1:18413');
require_once dirname(__DIR__, 2) . '/includes/ui_shell.php';
$role = $argv[1] ?? 'public';
$_SESSION = match ($role) {
    'family' => ['verejny_uzivatel_id' => 10],
    'athlete' => ['sportovec_pristup_id' => 20],
    'trainer' => ['trener_id' => 7],
    'public' => [],
    default => throw new InvalidArgumentException('Unknown fixture role.'),
};
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Syntetické ověření rozhraní</title><?php appUiAssets(); ?></head>
<body class="app-admin-shell"><?php publicShellNav('shop'); ?>
<main class="container py-4"><h1>Syntetické ověření rozhraní</h1>
<p><?= publicShellH('<img src=x onerror=alert(1)>') ?></p>
<p class="alert alert-info">Zatím žádné výsledky.</p>
<div class="alert alert-danger" role="alert">Vyplňte povinná pole.</div>
<form method="get"><label>Hledat</label><input class="form-control" name="q" required>
<button class="btn btn-primary mt-2" type="submit">Hledat</button></form>
<div id="app-admin-field-1"></div><div id="app-admin-field-3"></div>
<form method="post" action="/submitted"><label>Poznámka</label><textarea class="form-control" name="note"></textarea>
<button class="btn btn-primary mt-2" type="submit">Odeslat</button></form>
<div id="toastContainer"></div></main><?php publicShellFooter(); ?></body></html>
