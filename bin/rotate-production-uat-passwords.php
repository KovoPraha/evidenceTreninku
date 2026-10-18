<?php
declare(strict_types=1);

$libraryRoot = realpath((string)getenv('APP_ROOT')) ?: dirname(__DIR__);
require_once $libraryRoot . '/includes/password_security.php';

/** Rotate only the four already-existing, uniquely identified UAT accounts. */
function kisUatRotatePasswords(PDO $pdo, string $password): array
{
    passwordPolicyValidate($password);
    $lock = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $parents = [
        ['tester.karel@velocota.com', 'Tester', 'Karel'],
        ['tester.petra@velocota.com', 'Tester', 'Petra'],
    ];
    $children = [
        ['tester.ema', 'Ema', 'Tester', '2018-04-12'],
        ['tester.adam', 'Adam', 'Tester', '2017-09-18'],
    ];
    $pdo->beginTransaction();
    try {
        $parentIds = [];
        $parentSelect = $pdo->prepare(
            'SELECT id,jmeno,prijmeni,trener_id FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?' . $lock
        );
        foreach ($parents as [$email, $firstName, $lastName]) {
            $parentSelect->execute([$email]);
            $rows = $parentSelect->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1 || (string)$rows[0]['jmeno'] !== $firstName
                || (string)$rows[0]['prijmeni'] !== $lastName || $rows[0]['trener_id'] !== null) {
                throw new RuntimeException('The dedicated TEST parent is missing or its identity changed.');
            }
            $parentIds[] = (int)$rows[0]['id'];
        }

        $childIds = [];
        $childSelect = $pdo->prepare(
            'SELECT a.id,s.jmeno,s.prijmeni,s.narozeni FROM child_access_accounts a '
            . 'JOIN sportovci s ON s.id=a.sportovec_id WHERE a.login_key=?' . $lock
        );
        foreach ($children as [$login, $firstName, $lastName, $birthDate]) {
            $childSelect->execute([$login]);
            $rows = $childSelect->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1 || (string)$rows[0]['jmeno'] !== $firstName
                || (string)$rows[0]['prijmeni'] !== $lastName
                || (string)$rows[0]['narozeni'] !== $birthDate) {
                throw new RuntimeException('The dedicated TEST child is missing or its identity changed.');
            }
            $childIds[] = (int)$rows[0]['id'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || !password_verify($password, $hash)) {
            throw new RuntimeException('Password hashing failed.');
        }
        $parentUpdate = $pdo->prepare(
            'UPDATE verejni_uzivatele SET heslo_hash=?,email_overeno=1,aktivni=1,'
            . 'verifikacni_token=NULL,verifikacni_token_expires_at=NULL,'
            . 'session_version=session_version+1 WHERE id=?'
        );
        $resetTokens = $pdo->prepare(
            'UPDATE password_reset_tokens SET consumed_at=COALESCE(consumed_at,CURRENT_TIMESTAMP) '
            . 'WHERE delivery_account_id=?'
        );
        foreach ($parentIds as $id) {
            $parentUpdate->execute([$hash, $id]);
            $resetTokens->execute([$id]);
        }

        $childUpdate = $pdo->prepare(
            'UPDATE child_access_accounts SET password_hash=?,active=1,'
            . 'password_changed_at=CURRENT_TIMESTAMP,session_version=session_version+1,'
            . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $childAudit = $pdo->prepare(
            "INSERT INTO child_access_events(access_account_id,actor_type,actor_id,action,note) "
            . "VALUES (?,'system',NULL,'password_reset','Obnovení hesla vyhrazeného produkčního TEST účtu.')"
        );
        foreach ($childIds as $id) {
            $childUpdate->execute([$hash, $id]);
            $childAudit->execute([$id]);
        }
        $pdo->commit();
        return ['parents_rotated' => count($parentIds), 'children_rotated' => count($childIds)];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function kisUatRotatePasswordsMain(): void
{
    if (PHP_SAPI !== 'cli' || (string)getenv('KIS_UAT_ROTATE_CONFIRM') !== 'OBNOVIT-TEST-HESLA') {
        throw new RuntimeException('Explicit TEST password rotation confirmation is required.');
    }
    $appRoot = realpath((string)getenv('APP_ROOT'));
    $settingsFile = realpath((string)getenv('KIS_UAT_SETTINGS_FILE'));
    if ((string)getenv('APP_HOST') !== 'kis.kovopraha.cz' || $appRoot === false
        || !is_file($appRoot . '/config.php') || $settingsFile === false) {
        throw new RuntimeException('Production target or protected settings are unavailable.');
    }
    $settings = json_decode((string)file_get_contents($settingsFile), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($settings)) {
        throw new RuntimeException('Protected settings are invalid.');
    }
    $password = (string)($settings['password'] ?? '');
    passwordPolicyValidate($password);
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $windowEnd = new DateTimeImmutable((string)($settings['window_end'] ?? ''), new DateTimeZone('Europe/Prague'));
    if ($windowEnd <= $now || $windowEnd > $now->modify('+31 days')) {
        throw new RuntimeException('The approved UAT window is not active.');
    }

    $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
    $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    require $appRoot . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('The target is not production.');
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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false]
    );
    echo json_encode(['ok' => true] + kisUatRotatePasswords($pdo, $password), JSON_THROW_ON_ERROR) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisUatRotatePasswordsMain();
    } catch (Throwable $exception) {
        fwrite(STDERR, "production_uat_password_rotation_failed\n");
        exit(1);
    }
}
