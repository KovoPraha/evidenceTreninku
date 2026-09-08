<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ((string)getenv('KIS_UAT_READINESS_CONFIRM') !== 'OVERIT') {
    fwrite(STDERR, "production_uat_readiness_refused\n");
    exit(2);
}
$applicationRoot = realpath((string)getenv('APP_ROOT'));
if ($applicationRoot === false || !is_file($applicationRoot . '/config.php')) {
    fwrite(STDERR, "production_uat_readiness_missing_application\n");
    exit(2);
}

require $applicationRoot . '/config.php';
require $applicationRoot . '/db.php';
require $applicationRoot . '/includes/uat_readiness.php';

try {
    $result = uatReadiness($pdo, $applicationRoot);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($result['ready'] ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "production_uat_readiness_failed\n");
    exit(1);
}
