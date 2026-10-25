<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';

// Keep old bookmarks while the canonical handler retains export authorization.
$query = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
header('Location: export_csv.php' . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
