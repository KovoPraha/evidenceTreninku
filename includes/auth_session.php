<?php
declare(strict_types=1);

require_once __DIR__ . '/session_security.php';

const AUTH_SESSION_TRAINER_VERSION_KEY = 'trener_session_version';
const AUTH_SESSION_PUBLIC_VERSION_KEY = 'verejny_uzivatel_session_version';
const AUTH_SESSION_CHILD_VERSION_KEY = 'sportovec_session_version';

/** @return array{table:string,id_key:string,version_key:string,active_column:string,clear_keys:list<string>} */
function auth_session_identity_definition(string $identity): array
{
    return match ($identity) {
        'trainer' => [
            'table' => 'treneri',
            'id_key' => 'trener_id',
            'version_key' => AUTH_SESSION_TRAINER_VERSION_KEY,
            'active_column' => 'aktivni',
            'clear_keys' => [
                'trener_id',
                'trener_jmeno',
                'role',
                'login_time',
                'opravneni',
                'velo_user_id_cached',
                AUTH_SESSION_TRAINER_VERSION_KEY,
            ],
        ],
        'public' => [
            'table' => 'verejni_uzivatele',
            'id_key' => 'verejny_uzivatel_id',
            'version_key' => AUTH_SESSION_PUBLIC_VERSION_KEY,
            'active_column' => 'aktivni',
            'clear_keys' => [
                'verejny_uzivatel_id',
                'verejny_uzivatel_jmeno',
                AUTH_SESSION_PUBLIC_VERSION_KEY,
            ],
        ],
        'child' => [
            'table' => 'child_access_accounts',
            'id_key' => 'sportovec_pristup_id',
            'version_key' => AUTH_SESSION_CHILD_VERSION_KEY,
            'active_column' => 'active',
            'clear_keys' => [
                'sportovec_pristup_id',
                'sportovec_pristup_jmeno',
                AUTH_SESSION_CHILD_VERSION_KEY,
            ],
        ],
        default => throw new InvalidArgumentException('Unknown authentication identity.'),
    };
}

function auth_session_active_version(PDO $pdo, string $identity, int $id): ?int
{
    if ($id < 1) {
        return null;
    }

    $definition = auth_session_identity_definition($identity);
    $statement = $pdo->prepare(
        'SELECT session_version FROM ' . $definition['table']
        . ' WHERE id = :id AND ' . $definition['active_column'] . ' = 1 LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $version = $statement->fetchColumn();

    if ($version === false || (int)$version < 1) {
        return null;
    }

    return (int)$version;
}

function auth_session_bind_trainer(int $trainerId, int $sessionVersion): void
{
    if ($trainerId < 1 || $sessionVersion < 1) {
        throw new InvalidArgumentException('Invalid trainer authentication binding.');
    }

    // Athlete mode is exclusive in both directions. A later trainer login must
    // not leave the restricted athlete identity attached to the same session.
    auth_session_clear_identity('child');
    $_SESSION['trener_id'] = $trainerId;
    $_SESSION[AUTH_SESSION_TRAINER_VERSION_KEY] = $sessionVersion;
}

function auth_session_bind_public_user(int $userId, int $sessionVersion): void
{
    if ($userId < 1 || $sessionVersion < 1) {
        throw new InvalidArgumentException('Invalid public authentication binding.');
    }

    // Parent/public and trainer identities may coexist by the legacy contract,
    // but neither may coexist with the restricted athlete identity.
    auth_session_clear_identity('child');
    $_SESSION['verejny_uzivatel_id'] = $userId;
    $_SESSION[AUTH_SESSION_PUBLIC_VERSION_KEY] = $sessionVersion;
}

function auth_session_bind_child(int $accessAccountId, int $sessionVersion): void
{
    if ($accessAccountId < 1 || $sessionVersion < 1) {
        throw new InvalidArgumentException('Invalid athlete authentication binding.');
    }

    // Athlete access is an intentionally isolated security boundary. Binding
    // it can never retain a public-family or trainer identity in the session.
    auth_session_clear_identity('trainer');
    auth_session_clear_identity('public');
    auth_session_clear_identity('child');
    $_SESSION['sportovec_pristup_id'] = $accessAccountId;
    $_SESSION[AUTH_SESSION_CHILD_VERSION_KEY] = $sessionVersion;
}

function auth_session_clear_identity(string $identity): void
{
    $definition = auth_session_identity_definition($identity);
    foreach ($definition['clear_keys'] as $key) {
        unset($_SESSION[$key]);
    }
}

function auth_session_version_value(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || !ctype_digit($value)) {
        return null;
    }

    $version = (int)$value;
    return $version > 0 ? $version : null;
}

/** Refresh the request-scoped trainer authorization snapshot from live DB state. */
function auth_session_refresh_trainer_authorization(PDO $pdo, int $trainerId): void
{
    $trainer = $pdo->prepare('SELECT role FROM treneri WHERE id=? AND aktivni=1 LIMIT 1');
    $trainer->execute([$trainerId]);
    $role = $trainer->fetchColumn();
    if (!is_string($role) || !in_array($role, ['trener', 'hlavni', 'admin'], true)) {
        throw new RuntimeException('Trainer authorization role is invalid.');
    }

    $permissions = [];
    foreach ($pdo->query('SELECT klic,min_role FROM opravneni')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string)($row['klic'] ?? '');
        $minimumRole = (string)($row['min_role'] ?? '');
        if ($key === '' || !in_array($minimumRole, ['trener', 'hlavni', 'admin'], true)) {
            throw new RuntimeException('Trainer permission configuration is invalid.');
        }
        $permissions[$key] = $minimumRole;
    }

    $_SESSION['role'] = $role;
    $_SESSION['opravneni'] = $permissions;
}

/**
 * Validate every identity currently carried by the PHP session.
 * Invalid identities are cleared and false instructs the caller to terminate
 * the current request, because some legacy endpoints authorize before db.php.
 */
function auth_session_validate(PDO $pdo): bool
{
    $present = [];
    foreach (['trainer', 'public', 'child'] as $identity) {
        $definition = auth_session_identity_definition($identity);
        if (isset($_SESSION[$definition['id_key']])) {
            $present[] = $identity;
        }
    }

    if ($present === []) {
        return true;
    }

    $invalid = [];
    try {
        foreach ($present as $identity) {
            $definition = auth_session_identity_definition($identity);
            $id = filter_var($_SESSION[$definition['id_key']], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $storedVersion = auth_session_version_value(
                $_SESSION[$definition['version_key']] ?? null
            );
            $activeVersion = $id === false
                ? null
                : auth_session_active_version($pdo, $identity, (int)$id);

            if ($storedVersion === null
                || $activeVersion === null
                || !hash_equals((string)$activeVersion, (string)$storedVersion)
            ) {
                $invalid[] = $identity;
            }
        }
    } catch (Throwable $exception) {
        error_log('Authentication session validation failed: ' . $exception->getMessage());
        $invalid = $present;
    }

    if ($invalid === [] && in_array('trainer', $present, true)) {
        try {
            auth_session_refresh_trainer_authorization($pdo, (int)$_SESSION['trener_id']);
        } catch (Throwable $exception) {
            error_log('Trainer authorization refresh failed: ' . $exception->getMessage());
            $invalid[] = 'trainer';
        }
    }

    if ($invalid === []) {
        return true;
    }

    foreach (array_unique($invalid) as $identity) {
        auth_session_clear_identity($identity);
    }
    app_session_mark_identity_changed();

    return false;
}
