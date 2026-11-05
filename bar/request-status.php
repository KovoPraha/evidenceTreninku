<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/request_status.php';
barRequireWebActor($pdo, 'request_status');
header('Content-Type: application/json; charset=UTF-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}
try {
    $token = barSourceText($_SERVER['HTTP_X_BAR_REQUEST_TOKEN'] ?? '', 64);
    $status = barRequestStatus($pdo, $token);
    echo json_encode(['state' => $status['state'], 'result_url' => $status['result_route'] === null ? null : appUiUrl($status['result_route'])], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}
