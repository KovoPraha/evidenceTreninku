<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/public_profile_token.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Platnost stránky vypršela. Otevřete odkaz znovu.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }
    $sportovecId = public_profile_access_resolve($pdo, trim((string)($_POST['token'] ?? '')));
    if ($sportovecId === null) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Odkaz je neplatný, odvolaný nebo mu skončila platnost.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }
    public_profile_access_grant_session($sportovecId);
    app_session_mark_authenticated();
    echo json_encode(['ok' => true, 'redirect' => 'sportovec_treninky.php'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Bezpečné otevření profilu</title>
  <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<main class="container py-5" style="max-width:42rem">
  <section class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h1 class="h4">Otevírám profil sportovce</h1>
      <p id="profile-access-status" class="text-muted mb-0">Ověřuji bezpečnost odkazu…</p>
      <noscript><div class="alert alert-warning mt-3 mb-0">Pro bezpečné otevření odkazu je nutné povolit JavaScript.</div></noscript>
      <input id="profile-access-csrf" type="hidden" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    </div>
  </section>
</main>
<script src="assets/public-profile-access.js" defer></script>
</body>
</html>
