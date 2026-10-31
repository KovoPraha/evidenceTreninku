<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
try {
    $host = (string)getenv('APP_HOST');
    if ($host !== 'kis.kovopraha.cz') {
        throw new RuntimeException('pilot_target_invalid');
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    $root = dirname(__DIR__);
    require $root . '/config.php';
    require $root . '/includes/trainer_reset_scheduler.php';
    require $root . '/includes/trainer_pilot.php';
    if (!trainerResetSchedulerIdentity($host, defined('APP_BASE_URL') ? APP_BASE_URL : null)) {
        throw new RuntimeException('pilot_target_invalid');
    }
    $paths = trainerResetSchedulerPaths($root, (string)getenv('TRAINER_RESET_OPERATIONS_DIR'));
    $input = json_decode((string)file_get_contents($paths['operations'] . '/trainer-pilot-input.json'), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($input['action'] === 'status') {
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $result = trainerPilotStatus($pdo, $input['owner_name'], $input['email']);
        } finally {
            $pdo->rollBack();
        }
    } elseif ($input['action'] === 'create' && $input['confirm'] === 'VYTVORIT-TEST-PILOT') {
        $result = trainerPilotCreate($pdo, $input['owner_name'], $input['email']);
    } else {
        throw new RuntimeException('pilot_action_invalid');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    $message = $error->getMessage();
    fwrite(STDERR, preg_match('/^pilot_[a-z_]+$/D', $message) ? $message . PHP_EOL : "pilot_operation_failed\n");
    exit(1);
}
