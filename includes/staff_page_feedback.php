<?php
// Included in the page scope after authentication; no data is changed here.
/** @var int $feedbackStatus HTTP status supplied by the authenticated caller. */
/** @var string $feedbackTitle Plain text supplied by the authenticated caller. */
/** @var string $feedbackMessage Plain text supplied by the authenticated caller. */
http_response_code($feedbackStatus);
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($feedbackTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
</head>
<body>
<?php include __DIR__ . '/../hlavicka.php'; ?>
<main class="container py-4">
    <h1><?= htmlspecialchars($feedbackTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="alert alert-info" role="status"><?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($feedbackStatus === 404): ?>
        <a class="btn btn-primary me-2" href="sprava_zavodu.php">Zpět na seznam závodů</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="pracovni_pozice.php">Zpět na pracovní rozcestník</a>
</main>
</body>
</html>
