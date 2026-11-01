<?php

declare(strict_types=1);

/** Exact address lookup only; never resolve, link or elevate an account automatically. */
function trainerOwnerLookup(PDO $pdo, string $email): array
{
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('lookup_email_invalid');
    }
    $query = $pdo->prepare('SELECT id,jmeno,aktivni FROM treneri WHERE LOWER(TRIM(email))=? ORDER BY id');
    $query->execute([$email]);
    $matches = [];
    $target = trainerPasswordResetTarget($pdo, $email);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $positions = $pdo->prepare('SELECT position_code FROM staff_user_positions WHERE trainer_id=? ORDER BY position_code');
        $positions->execute([$row['id']]);
        $codes = $positions->fetchAll(PDO::FETCH_COLUMN);
        $super = $pdo->prepare('SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=?');
        $super->execute([$row['id']]);
        $superadmin = (int)$super->fetchColumn() > 0;
        $names = $pdo->prepare('SELECT COUNT(*) FROM treneri WHERE LOWER(TRIM(jmeno))=LOWER(?)');
        $names->execute([trim((string)$row['jmeno'])]);
        $active = (int)$row['aktivni'] === 1;
        $matches[] = ['trainer_id' => (int)$row['id'], 'login_name' => $row['jmeno'], 'active' => $active,
            'positions' => $codes, 'superadmin' => $superadmin,
            'can_provision' => $active && ($superadmin || in_array('sports_lead', $codes, true)),
            'unique_login_name' => (int)$names->fetchColumn() === 1,
            'reset_eligible' => $target !== null && (int)$target['trainer_id'] === (int)$row['id']];
    }
    $public = $pdo->prepare('SELECT COUNT(*) FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?');
    $public->execute([$email]);
    $publicCount = (int)$public->fetchColumn();
    return ['read_only' => true, 'matches' => $matches, 'public_account_matches' => $publicCount,
        'email_available_for_separate_pilot' => $matches === [] && $publicCount === 0,
        'domain_mutations' => false, 'messages_sent' => 0];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
try {
    $input = json_decode((string)file_get_contents(__DIR__ . '/trainer-owner-lookup-input.json'), true, 512, JSON_THROW_ON_ERROR);
    $root = realpath((string)$input['root']);
    if ($root === false || basename($root) !== 'kis.kovopraha.cz'
        || realpath(dirname($root) . '/data/.kis-deploy') !== __DIR__) {
        throw new RuntimeException('lookup_target_invalid');
    }
    $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
    $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    putenv('APP_HOST=kis.kovopraha.cz');
    require $root . '/config.php';
    if (!defined('APP_BASE_URL') || rtrim(APP_BASE_URL, '/') !== 'https://kis.kovopraha.cz') {
        throw new RuntimeException('lookup_target_invalid');
    }
    require $root . '/includes/trainer_password_reset.php';
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('START TRANSACTION READ ONLY');
    try {
        $result = trainerOwnerLookup($pdo, (string)$input['email']);
    } finally {
        $pdo->rollBack();
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    $message = $error->getMessage();
    fwrite(STDERR, preg_match('/^lookup_[a-z_]+$/D', $message) ? $message . PHP_EOL : "lookup_failed\n");
    exit(1);
}
