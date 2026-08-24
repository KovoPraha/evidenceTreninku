<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * push_subscribe.php
 * Uloží/odstraní Web Push subscription pro přihlášeného trenéra.
 * POST JSON: { action: 'subscribe'|'unsubscribe', subscription: { endpoint, keys: { p256dh, auth } } }
 */
app_session_start();
if (!isset($_SESSION['trener_id'])) { http_response_code(403); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/push_subscription_security.php';

header('Content-Type: application/json; charset=utf-8');

$data  = json_decode(file_get_contents('php://input'), true) ?: [];

if (!csrf_verify($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Neplatný CSRF token']);
    exit;
}

$akce  = $data['action'] ?? 'subscribe';
$sub   = $data['subscription'] ?? null;
$tid   = (int)$_SESSION['trener_id'];

if (!is_array($sub) || empty($sub['endpoint'])) {
    echo json_encode(['ok'=>false,'msg'=>'Chybí subscription']); exit;
}

try {
    $endpoint = pushSubscriptionValidateEndpoint($sub['endpoint']);
    $p256dh = $akce === 'unsubscribe'
        ? ''
        : pushSubscriptionValidateKey($sub['keys']['p256dh'] ?? null, 'Push klíč', 200);
    $auth = $akce === 'unsubscribe'
        ? ''
        : pushSubscriptionValidateKey($sub['keys']['auth'] ?? null, 'Autorizační klíč', 100);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'msg'=>$exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
$ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

if ($akce === 'unsubscribe') {
    $pdo->prepare("DELETE FROM push_subscriptions WHERE trener_id=? AND endpoint=?")
        ->execute([$tid, $endpoint]);
    echo json_encode(['ok'=>true]);
    exit;
}

// Upsert — pokud endpoint existuje, aktualizuj klíče; jinak vlož nový
$existing = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint=?");
$existing->execute([$endpoint]);
if ($existing->fetchColumn()) {
    $pdo->prepare("UPDATE push_subscriptions SET p256dh=?, auth=?, trener_id=?, user_agent=? WHERE endpoint=?")
        ->execute([$p256dh, $auth, $tid, $ua, $endpoint]);
} else {
    $pdo->prepare("INSERT INTO push_subscriptions (trener_id, endpoint, p256dh, auth, user_agent) VALUES (?,?,?,?,?)")
        ->execute([$tid, $endpoint, $p256dh, $auth, $ua]);
}

echo json_encode(['ok'=>true]);
