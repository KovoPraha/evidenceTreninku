<?php
declare(strict_types=1);

const KIS_PRODUCTION_TEST_ADMIN_EMAIL = 'kis@velocota.com';

/** @param array<string,mixed> $input @return array{email:string,name:string,password:string} */
function kisProductionTestAdminValidate(array $input): array
{
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $name = trim((string)($input['name'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if (!hash_equals(KIS_PRODUCTION_TEST_ADMIN_EMAIL, $email)) {
        throw new RuntimeException('Only the dedicated KIS test administrator email is allowed.');
    }
    if ($name === '' || mb_strlen($name, 'UTF-8') > 100
        || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1
    ) {
        throw new RuntimeException('Test administrator name is invalid.');
    }
    if (strlen($password) < 12 || strlen($password) > 200
        || preg_match('/[a-z]/', $password) !== 1
        || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1
        || preg_match('/[^A-Za-z0-9]/', $password) !== 1
    ) {
        throw new RuntimeException('Test administrator password does not meet the production policy.');
    }

    return ['email' => $email, 'name' => $name, 'password' => $password];
}

/** @param array{email:string,name:string,password:string} $settings @return array{id:int,created:bool} */
function kisProductionTestAdminUpsert(PDO $pdo, array $settings): array
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $pdo->beginTransaction();
    try {
        $publicAccount = $pdo->prepare(
            'SELECT COUNT(*) FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?'
        );
        $publicAccount->execute([$settings['email']]);
        if ((int)$publicAccount->fetchColumn() !== 0) {
            throw new RuntimeException('The email is already used by a public account.');
        }

        $select = 'SELECT id FROM treneri WHERE LOWER(TRIM(email))=? ORDER BY id';
        if ($driver === 'mysql') {
            $select .= ' FOR UPDATE';
        }
        $existing = $pdo->prepare($select);
        $existing->execute([$settings['email']]);
        $ids = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) > 1) {
            throw new RuntimeException('More than one trainer account uses the requested email.');
        }

        $passwordHash = password_hash($settings['password'], PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('The password could not be hashed.');
        }

        $created = $ids === [];
        if ($created) {
            $insert = $pdo->prepare(
                "INSERT INTO treneri (jmeno,email,heslo,role,aktivni,session_version) "
                . "VALUES (?,?,?,'admin',1,1)"
            );
            $insert->execute([$settings['name'], $settings['email'], $passwordHash]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $id = $ids[0];
            $update = $pdo->prepare(
                "UPDATE treneri SET jmeno=?,email=?,heslo=?,role='admin',aktivni=1,"
                . 'session_version=session_version+1 WHERE id=?'
            );
            $update->execute([$settings['name'], $settings['email'], $passwordHash, $id]);
        }

        $verify = $pdo->prepare('SELECT email,heslo,role,aktivni FROM treneri WHERE id=?');
        $verify->execute([$id]);
        $row = $verify->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)
            || !hash_equals($settings['email'], strtolower(trim((string)$row['email'])))
            || (string)$row['role'] !== 'admin'
            || (int)$row['aktivni'] !== 1
            || !password_verify($settings['password'], (string)$row['heslo'])
        ) {
            throw new RuntimeException('The provisioned administrator account could not be verified.');
        }

        $pdo->commit();
        return ['id' => $id, 'created' => $created];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function kisProductionTestAdminMain(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    if ((string)getenv('KIS_TEST_ADMIN_CONFIRM') !== 'VYTVORIT-KIS-ADMINA') {
        throw new RuntimeException('Explicit production administrator confirmation is required.');
    }

    $appRoot = realpath((string)getenv('APP_ROOT'));
    $appHost = strtolower(trim((string)getenv('APP_HOST')));
    $settingsFile = realpath((string)getenv('KIS_TEST_ADMIN_SETTINGS_FILE'));
    if ($appHost !== 'kis.kovopraha.cz' || $appRoot === false
        || !is_file($appRoot . '/config.php') || $settingsFile === false
    ) {
        throw new RuntimeException('Production application or protected settings file is unavailable.');
    }

    $decoded = json_decode((string)file_get_contents($settingsFile), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Test administrator settings are invalid.');
    }
    $settings = kisProductionTestAdminValidate($decoded);

    $_SERVER['HTTP_HOST'] = $appHost;
    $_SERVER['SERVER_NAME'] = $appHost;
    require $appRoot . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('The target is not the production environment.');
    }
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('Production database configuration is incomplete.');
        }
    }

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
    $result = kisProductionTestAdminUpsert($pdo, $settings);
    echo json_encode([
        'ok' => true,
        'id' => $result['id'],
        'created' => $result['created'],
        'email' => $settings['email'],
        'role' => 'admin',
        'active' => true,
        'password_verified' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisProductionTestAdminMain();
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Production test administrator provisioning failed: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
