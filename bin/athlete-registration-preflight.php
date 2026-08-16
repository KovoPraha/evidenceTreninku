<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$appRoot = realpath((string)getenv('APP_ROOT'));
if ((string)getenv('KIS_ATHLETE_PREFLIGHT_CONFIRM') !== 'OVERIT'
    || $appRoot === false
    || !is_file($appRoot . '/config.php')
) {
    fwrite(STDERR, "athlete_registration_preflight_refused\n");
    exit(2);
}

require $appRoot . '/config.php';
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "athlete_registration_preflight_missing_configuration\n");
        exit(2);
    }
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $nonEmpty = (int)$pdo->query(
        "SELECT COUNT(*) FROM sportovci WHERE NULLIF(TRIM(rc), '') IS NOT NULL"
    )->fetchColumn();
    $permission = $pdo->prepare("SELECT min_role FROM opravneni WHERE klic='sync_evidence' LIMIT 1");
    $permission->execute();
    $minimumRole = (string)($permission->fetchColumn() ?: '');
    if (!in_array($minimumRole, ['trener', 'hlavni', 'admin'], true)) {
        throw new RuntimeException('invalid_permission_model');
    }
    $levels = ['trener' => 1, 'hlavni' => 2, 'admin' => 3];
    $eligibleRoles = array_keys(array_filter(
        $levels,
        static fn (int $level): bool => $level >= $levels[$minimumRole]
    ));
    $counts = array_fill_keys($eligibleRoles, 0);
    foreach ($pdo->query("SELECT role,COUNT(*) AS pocet FROM treneri WHERE aktivni=1 GROUP BY role") as $row) {
        $role = (string)($row['role'] ?? '');
        if (array_key_exists($role, $counts)) $counts[$role] = (int)($row['pocet'] ?? 0);
    }
    echo json_encode([
        'ok' => true,
        'query_mode' => 'select_only',
        'birth_number_values_exposed' => false,
        'non_empty_birth_number_count' => $nonEmpty,
        'sync_evidence' => [
            'authorization_model' => 'role_threshold',
            'minimum_role' => $minimumRole,
            'eligible_active_roles' => $eligibleRoles,
            'eligible_active_trainers_by_role' => $counts,
            'eligible_active_trainers_total' => array_sum($counts),
            'individual_overrides' => false,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "athlete_registration_preflight_failed\n");
    exit(1);
}
