<?php

declare(strict_types=1);

// Public playback deliberately does not start a staff/customer session.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/tv_program.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$program = $id === false ? null : tvPublicProgram($pdo, (int)$id);
if ($program === null) {
    http_response_code(404);
}
$boot = [
    'revision' => $program['revision'] ?? 0,
    'document' => $program['document'] ?? null,
    'source' => appUiUrl('tv.php?id=' . (int)$id . '&format=json'),
    'image_base' => appUiUrl('tv_image.php?id='),
];
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo tvJson(['revision' => $boot['revision'], 'document' => $boot['document']]);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>TV program</title>
    <link rel="stylesheet" href="<?=publicShellH(appUiUrl('assets/tv.css'))?>?v=1">
    <script nonce="<?=publicShellH(app_csp_nonce())?>" defer src="<?=publicShellH(appUiUrl('assets/tv-canvas.js'))?>?v=1"></script>
    <script nonce="<?=publicShellH(app_csp_nonce())?>" defer src="<?=publicShellH(appUiUrl('assets/tv-player.js'))?>?v=1"></script>
</head>
<body class="tv-player">
    <main id="tv-screen" class="tv-viewport" aria-label="TV vysílání"></main>
    <button id="tv-fullscreen" class="tv-fullscreen" type="button">Celá obrazovka</button>
    <span id="tv-fullscreen-message" class="tv-fullscreen-message" role="status"></span>
    <script type="application/json" id="tv-boot" nonce="<?=publicShellH(app_csp_nonce())?>"><?=
        json_encode($boot, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
?></script>
</body>
</html>
