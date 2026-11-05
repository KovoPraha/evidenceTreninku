<?php

declare(strict_types=1);

/** Dedicated, short-lived TV-only identity. Never reuse an existing account. */
function tvUatRunId(string $run): string
{
    if (!preg_match('/^[0-9]{8,20}$/D', $run)) {
        throw new RuntimeException('Invalid TV UAT run identifier.');
    }
    return $run;
}

function tvUatWriteState(string $path, array $state): void
{
    $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($path . '.new', $json, LOCK_EX) === false) {
        throw new RuntimeException('Cannot persist TV UAT state.');
    }
    chmod($path . '.new', 0600);
    if (!rename($path . '.new', $path)) {
        throw new RuntimeException('Cannot finalize TV UAT state.');
    }
}

function tvUatProgramFingerprints(PDO $pdo): array
{
    $result = [];
    foreach ($pdo->query('SELECT * FROM tv_programs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[(int)$row['id']] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }
    return $result;
}

function tvUatIdentity(PDO $pdo, array $state): array
{
    $run = tvUatRunId((string)($state['run_id'] ?? ''));
    $query = $pdo->prepare('SELECT * FROM treneri WHERE id=?');
    $query->execute([(int)($state['actor_id'] ?? 0)]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['jmeno'] !== 'TV-UAT-' . $run || $row['email'] !== 'tv-uat-' . $run . '@example.invalid'
        || $row['role'] !== 'trener') {
        throw new RuntimeException('TV UAT identity changed; refusing to modify it.');
    }
    $super = $pdo->prepare('SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=?');
    $super->execute([$row['id']]);
    $positions = $pdo->prepare('SELECT position_code FROM staff_user_positions WHERE trainer_id=?');
    $positions->execute([$row['id']]);
    if ((int)$super->fetchColumn() !== 0 || array_diff($positions->fetchAll(PDO::FETCH_COLUMN), ['tv_operator']) !== []) {
        throw new RuntimeException('TV UAT identity has unexpected privileges.');
    }
    return $row;
}

function tvUatPrepare(PDO $pdo, string $run, string $stateFile): array
{
    $run = tvUatRunId($run);
    if (is_file($stateFile)) {
        $state = json_decode((string)file_get_contents($stateFile), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['run_id'] ?? '') !== $run || ($state['expires_at'] ?? 0) < time()
            || !is_string($state['password'] ?? null)) {
            throw new RuntimeException('TV UAT state is expired or already cleaned.');
        }
        tvUatIdentity($pdo, $state);
        return ['ok' => true, 'prepared' => true, 'actor_id' => $state['actor_id'], 'reused' => true];
    }
    $name = 'TV-UAT-' . $run;
    $email = 'tv-uat-' . $run . '@example.invalid';
    $existing = $pdo->prepare('SELECT id FROM treneri WHERE jmeno=? OR email=?');
    $existing->execute([$name, $email]);
    if ($existing->fetchColumn()) {
        throw new RuntimeException('TV UAT identity collision; no existing account will be reset.');
    }
    if (!$pdo->query("SELECT 1 FROM staff_positions WHERE code='tv_operator' AND active=1")->fetchColumn()) {
        throw new RuntimeException('TV operator position is not installed.');
    }
    $state = [
        'run_id' => $run, 'login' => $name, 'email' => $email, 'password' => bin2hex(random_bytes(24)) . 'Tv!',
        'expires_at' => time() + 3600, 'baseline_programs' => tvUatProgramFingerprints($pdo),
    ];
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version) VALUES(?,?,?,'trener',1,1)")
            ->execute([$name, $email, password_hash($state['password'], PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        $state['actor_id'] = $id;
        $pdo->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,?,1,?)')
            ->execute([$id, 'tv_operator', $id]);
        staffAccountEvent($pdo, $id, $id, 'tv_uat_created', 'Vyhrazený dočasný účet pro autorizovaný TV UAT', null, [
            'id' => $id, 'jmeno' => $name, 'email' => $email, 'role' => 'trener', 'aktivni' => 1, 'session_version' => 1,
        ]);
        // A durable private recovery record exists before the account is committed.
        tvUatWriteState($stateFile, $state);
        $pdo->commit();
        return ['ok' => true, 'prepared' => true, 'actor_id' => $id, 'reused' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_file($stateFile)) {
            unlink($stateFile);
        }
        throw $e;
    }
}

function tvUatCleanup(PDO $pdo, string $run, string $stateFile): array
{
    $run = tvUatRunId($run);
    if (!is_file($stateFile)) {
        return ['ok' => true, 'nothing_prepared' => true];
    }
    $state = json_decode((string)file_get_contents($stateFile), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($state) || ($state['run_id'] ?? '') !== $run) {
        throw new RuntimeException('TV UAT state does not belong to this run.');
    }
    $actor = tvUatIdentity($pdo, $state);
    $id = (int)$actor['id'];
    $programs = $pdo->prepare("SELECT DISTINCT p.* FROM tv_programs p JOIN tv_program_events e ON e.program_id=p.id "
        . "WHERE e.actor_id=? AND e.action='created'");
    $programs->execute([$id]);
    $ids = [];
    foreach ($programs->fetchAll(PDO::FETCH_ASSOC) as $program) {
        if (!str_starts_with($program['name'], 'TEST TV ' . $run)) {
            throw new RuntimeException('TV UAT program identity changed.');
        }
        if (!(int)$program['archived']) {
            tvMutateProgram($pdo, (int)$program['id'], (int)$program['lock_version'], $id, 'archive');
        }
        $ids[] = (int)$program['id'];
    }
    $assets = $pdo->prepare('SELECT id,storage_key,width_px,height_px,byte_size,sha256_hex FROM tv_assets WHERE created_by=? ORDER BY id');
    $assets->execute([$id]);
    $images = [];
    foreach ($assets->fetchAll(PDO::FETCH_ASSOC) as $asset) {
        $file = privateStorageResolve($asset['storage_key']);
        $valid = $file !== null && hash_file('sha256', $file) === $asset['sha256_hex']
            && filesize($file) === (int)$asset['byte_size'] && (new finfo(FILEINFO_MIME_TYPE))->file($file) === 'image/jpeg';
        $images[] = ['id' => (int)$asset['id'], 'valid_private_jpeg' => $valid,
            'width' => (int)$asset['width_px'], 'height' => (int)$asset['height_px'], 'sha256' => $asset['sha256_hex']];
    }
    $pdo->beginTransaction();
    try {
        tvUatIdentity($pdo, $state);
        $pdo->prepare('UPDATE treneri SET aktivni=0,session_version=session_version+1 WHERE id=? AND aktivni=1')->execute([$id]);
        $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code=?')->execute([$id, 'tv_operator']);
        $pdo->prepare('UPDATE verejni_uzivatele SET aktivni=0,session_version=session_version+1 WHERE trener_id=? AND aktivni=1')->execute([$id]);
        if ((int)$actor['aktivni'] === 1) {
            $after = $actor;
            $after['aktivni'] = 0;
            $after['session_version'] = (int)$actor['session_version'] + 1;
            staffAccountEvent($pdo, $id, $id, 'tv_uat_deactivated', 'Ukončení autorizovaného TV UAT a revokace relací', $actor, $after);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    unset($state['password']);
    $state['cleaned_at'] = time();
    tvUatWriteState($stateFile, $state);
    $current = tvUatProgramFingerprints($pdo);
    $unchanged = true;
    foreach ($state['baseline_programs'] as $programId => $hash) {
        $unchanged = $unchanged && ($current[$programId] ?? null) === $hash;
    }
    $publicAbsent = true;
    foreach ($ids as $programId) {
        $publicAbsent = $publicAbsent && tvPublicProgram($pdo, $programId) === null;
    }
    return ['ok' => $unchanged && $publicAbsent && !in_array(false, array_column($images, 'valid_private_jpeg'), true),
        'run_id' => $run, 'actor_id' => $id, 'program_ids' => $ids, 'programs_archived' => $publicAbsent,
        'actor_deactivated' => true, 'positions_revoked' => true, 'linked_customer_deactivated' => true,
        'other_programs_unchanged' => $unchanged, 'images' => $images];
}

function tvUatMain(): int
{
    if (PHP_SAPI !== 'cli' || getenv('APP_HOST') !== 'kis.kovopraha.cz'
        || getenv('TV_UAT_CONFIRM') !== 'OVERIT-TV-PRODUKCI') {
        throw new RuntimeException('Production TV UAT is not authorized.');
    }
    $run = tvUatRunId((string)getenv('TV_UAT_RUN_ID'));
    if (basename(__DIR__) !== 'tv-uat-' . $run || basename(dirname(__DIR__)) !== '.kis-deploy') {
        throw new RuntimeException('TV UAT must run in its own private operations directory.');
    }
    $root = realpath((string)getenv('APP_ROOT'));
    if ($root === false || basename($root) !== 'kis.kovopraha.cz') {
        throw new RuntimeException('Invalid production TV application root.');
    }
    require $root . '/config.php';
    $configuration = get_defined_constants();
    if (($configuration['JE_LOKALNE'] ?? null) !== false || ($configuration['APP_BASE_URL'] ?? null) !== 'https://kis.kovopraha.cz') {
        throw new RuntimeException('Unexpected production TV identity.');
    }
    require_once $root . '/includes/tv_program.php';
    require_once $root . '/includes/staff_account_lifecycle.php';
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $state = __DIR__ . '/credentials.json';
    $action = (string)getenv('TV_UAT_ACTION');
    $result = match ($action) {
        'prepare' => tvUatPrepare($pdo, $run, $state),
        'cleanup' => tvUatCleanup($pdo, $run, $state),
        default => throw new RuntimeException('Unknown TV UAT operation.'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    return $result['ok'] ? 0 : 1;
}
