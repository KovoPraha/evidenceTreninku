<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/sumup_gateway.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false], JSON_THROW_ON_ERROR);
    exit;
}
if (!sumupIsEnabled()) {
    http_response_code(404);
    echo json_encode(['ok' => false], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $payload = file_get_contents('php://input');
    if (!is_string($payload)) throw new SumUpWebhookException('Tělo callbacku nelze načíst.');
    $client = new SumUpApiGatewayClient((string)(defined('SUMUP_API_KEY') ? SUMUP_API_KEY : ''));
    $result = sumupHandleWebhook($pdo, $payload, $client);
    http_response_code(204);
} catch (SumUpWebhookException $exception) {
    http_response_code(400);
    error_log('sumup_webhook: rejected callback: ' . $exception->getMessage());
    echo json_encode(['ok' => false], JSON_THROW_ON_ERROR);
} catch (SumUpGatewayException|ShopCheckoutException|InvalidArgumentException $exception) {
    http_response_code(500);
    error_log('sumup_webhook: processing failed: ' . $exception->getMessage());
    echo json_encode(['ok' => false], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('sumup_webhook: unexpected failure: ' . $exception->getMessage());
    echo json_encode(['ok' => false], JSON_THROW_ON_ERROR);
}
