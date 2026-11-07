<?php

declare(strict_types=1);

/** No web entry point: called only by the protected production drill's private CLI wrapper. */
function staffAccessUatSnapshot(PDO $pdo, ?array $ids = null): array
{
    $accounts = $pdo->query('SELECT id,role,aktivni,session_version FROM treneri ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $ids ??= array_map('intval', array_column($accounts, 'id'));
    $allowed = array_fill_keys($ids, true);
    $rows = ['accounts' => array_values(array_filter($accounts, static fn (array $r): bool => isset($allowed[(int)$r['id']])))];
    foreach (['staff_user_positions', 'staff_superadmins', 'kis_bar_permissions'] as $table) {
        $rows[$table] = array_values(array_filter(
            $pdo->query('SELECT * FROM ' . $table . ' ORDER BY trainer_id')->fetchAll(PDO::FETCH_ASSOC),
            static fn (array $r): bool => isset($allowed[(int)$r['trainer_id']])
        ));
        usort($rows[$table], static fn (array $a, array $b): int => strcmp(
            json_encode($a, JSON_THROW_ON_ERROR),
            json_encode($b, JSON_THROW_ON_ERROR)
        ));
    }
    return ['ids' => $ids, 'counts' => array_map('count', $rows),
        'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
}

function staffAccessUatWriteState(string $file, array $state): void
{
    if (file_put_contents($file . '.new', json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Cannot persist access UAT recovery state.');
    }
    chmod($file . '.new', 0600);
    if (!rename($file . '.new', $file)) {
        throw new RuntimeException('Cannot finalize access UAT recovery state.');
    }
}

function staffAccessUatIdentity(PDO $pdo, int $id, string $run, string $kind): array
{
    $query = $pdo->prepare('SELECT id,jmeno,email,role,aktivni,session_version FROM treneri WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['jmeno'] !== 'ACCESS-UAT-' . $run . '-' . $kind
        || $row['email'] !== 'access-uat-' . $run . '-' . strtolower($kind) . '@example.invalid'
        || $row['role'] !== 'trener') {
        throw new RuntimeException('Access UAT identity mismatch; existing accounts will not be modified.');
    }
    return $row;
}

function staffAccessUatCreate(PDO $pdo, array &$state, string $file): array
{
    if (!empty($state['actors']) || ($state['expires_at'] ?? 0) < time()) {
        throw new RuntimeException('Access UAT is expired or was already attempted; clean up first.');
    }
    $credentials = [];
    $pdo->beginTransaction();
    try {
        foreach (['ADMIN' => 'system_admin', 'TARGET' => 'coach'] as $kind => $position) {
            $name = 'ACCESS-UAT-' . $state['run_id'] . '-' . $kind;
            $email = 'access-uat-' . $state['run_id'] . '-' . strtolower($kind) . '@example.invalid';
            $query = $pdo->prepare('SELECT id FROM treneri WHERE jmeno=? OR email=?');
            $query->execute([$name, $email]);
            if ($query->fetchColumn()) {
                throw new RuntimeException('Access UAT identity collision; no existing account will be reused.');
            }
            $password = bin2hex(random_bytes(24)) . 'Qa!';
            $pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version,technical_email) VALUES(?,?,?,'trener',1,1,1)")
                ->execute([$name, $email, trainer_password_hash($password)]);
            $id = (int)$pdo->lastInsertId();
            $state['actors'][$kind] = $id;
            $pdo->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,?,1,?)')
                ->execute([$id, $position, $id]);
            staffAccountEvent(
                $pdo,
                $id,
                $id,
                'access_uat_created',
                'Autorizované dočasné ověření přístupů',
                null,
                ['id' => $id, 'role' => 'trener', 'aktivni' => 1]
            );
            $credentials[$kind] = ['id' => $id, 'login' => $name, 'password' => $password];
        }
        // IDs are durable before commit; passwords stay only in memory.
        staffAccessUatWriteState($file, $state);
        $pdo->commit();
        return $credentials;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function staffAccessUatCleanup(PDO $pdo, array &$state, string $file): array
{
    $pdo->beginTransaction();
    try {
        foreach ($state['actors'] ?? [] as $kind => $id) {
            if (in_array((int)$id, $state['baseline']['ids'], true)) {
                throw new RuntimeException('Refusing to clean up an original account.');
            }
            $before = staffAccessUatIdentity($pdo, (int)$id, (string)$state['run_id'], (string)$kind);
            $pdo->prepare('UPDATE treneri SET aktivni=0,session_version=session_version+1 WHERE id=? AND aktivni=1')->execute([$id]);
            $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM staff_superadmins WHERE trainer_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM kis_bar_permissions WHERE trainer_id=?')->execute([$id]);
            if ((int)$before['aktivni'] === 1) {
                staffAccountEvent(
                    $pdo,
                    (int)$id,
                    (int)$id,
                    'access_uat_deactivated',
                    'Ukončení testu a odvolání všech testovacích přístupů',
                    $before,
                    ['id' => (int)$id, 'aktivni' => 0]
                );
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    $state['cleaned_at'] = time();
    staffAccessUatWriteState($file, $state);
    foreach (['admin.cookies', 'target.cookies'] as $cookie) {
        if (is_file(dirname($file) . '/' . $cookie)) {
            unlink(dirname($file) . '/' . $cookie);
        }
    }
    $after = staffAccessUatSnapshot($pdo, $state['baseline']['ids']);
    return ['accounts_deactivated' => count($state['actors'] ?? []), 'test_positions_revoked' => true,
        'original_roles_unchanged' => hash_equals($state['baseline']['sha256'], $after['sha256']),
        'before' => $state['baseline'], 'after' => $after];
}

function staffAccessUatRequest(string $cookie, string $path, ?array $post = null): array
{
    if (!preg_match('~^[a-z_/-]+[.]php(?:[?][^\r\n]*)?$~D', $path)) {
        throw new RuntimeException('Unexpected UAT request path.');
    }
    $location = '';
    $curl = curl_init('https://kis.kovopraha.cz/' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$location): int {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
            }
            return strlen($line);
        }]);
    if ($post !== null) {
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($body)) {
        throw new RuntimeException('HTTPS request failed; no mutation will be blindly retried.');
    }
    return ['status' => $status, 'body' => $body, 'location' => $location];
}

function staffAccessUatForm(string $html, string $action = ''): array
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);
    $form = $action === '' ? '//form[.//input[@name="csrf_token"]]'
        : '//form[.//input[@name="action" and @value="' . $action . '"]]';
    $fields = [];
    foreach ($xpath->query($form . '//input[@type="hidden"]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $fields[$node->getAttribute('name')] = $node->getAttribute('value');
    }
    if (!is_string($fields['csrf_token'] ?? null) || $fields['csrf_token'] === '') {
        throw new RuntimeException('Expected CSRF-protected form is missing.');
    }
    return $fields;
}

function staffAccessUatAssert(bool $condition, string $label, array &$checks): void
{
    if (!$condition) {
        throw new RuntimeException('Access UAT failed: ' . $label);
    }
    $checks[] = $label;
}

function staffAccessUatLogin(string $cookie, array $account): void
{
    $page = staffAccessUatRequest($cookie, 'login.php');
    $fields = staffAccessUatForm($page['body']);
    $response = staffAccessUatRequest($cookie, 'login.php', array_merge(
        $fields,
        ['jmeno' => $account['login'], 'heslo' => $account['password']]
    ));
    if ($response['status'] !== 303 || !str_ends_with($response['location'], 'pracovni_pozice.php')) {
        throw new RuntimeException('Dedicated UAT login failed.');
    }
}

function staffAccessUatVerify(PDO $pdo, array &$state, string $file): array
{
    $checks = [];
    $credentials = staffAccessUatCreate($pdo, $state, $file);
    $adminCookie = dirname($file) . '/admin.cookies';
    $targetCookie = dirname($file) . '/target.cookies';
    $target = $credentials['TARGET'];
    $targetId = (int)$target['id'];
    $error = null;
    try {
        staffAccessUatLogin($adminCookie, $credentials['ADMIN']);
        $page = staffAccessUatRequest($adminCookie, 'sprava_pracovnich_pozic.php');
        staffAccessUatAssert($page['status'] === 200 && str_contains($page['body'], 'Lidé a přístupy'), 'overview_authenticated', $checks);
        $query = 'sprava_pracovnich_pozic.php?trainer_id=' . $targetId;
        $page = staffAccessUatRequest($adminCookie, $query);
        $fields = staffAccessUatForm($page['body'], 'save_positions');
        staffAccessUatAssert((int)($fields['trainer_id'] ?? 0) === $targetId, 'dedicated_target_form', $checks);
        $post = array_merge($fields, ['positions' => ['coach', 'bar_operator', 'order_operator'], 'default_position' => 'bar_operator',
            'reason' => 'TEST přístupy ' . $state['run_id'] . ': přidělení rolí']);
        $invalid = staffAccessUatRequest($adminCookie, $query, array_merge($post, ['csrf_token' => 'invalid-test-token']));
        staffAccessUatAssert(str_contains($invalid['body'], 'Formulář vypršel.'), 'csrf_rejected', $checks);
        $badDefault = staffAccessUatRequest($adminCookie, $query, array_merge($post, ['default_position' => 'system_admin']));
        staffAccessUatAssert(str_contains($badDefault['body'], 'Výchozí pozice musí být mezi přiřazenými.'), 'unassigned_default_rejected', $checks);
        $saved = staffAccessUatRequest($adminCookie, $query, $post);
        staffAccessUatAssert($saved['status'] === 303, 'roles_form_saved', $checks);
        $positions = $pdo->prepare('SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=? ORDER BY position_code');
        $positions->execute([$targetId]);
        $assigned = $positions->fetchAll(PDO::FETCH_ASSOC);
        staffAccessUatAssert(array_column($assigned, 'position_code') === ['bar_operator', 'coach', 'order_operator'], 'roles_persisted', $checks);
        staffAccessUatAssert((int)$assigned[0]['is_default'] === 1, 'default_persisted', $checks);
        $filtered = staffAccessUatRequest($adminCookie, 'sprava_pracovnich_pozic.php?' . http_build_query([
            'q' => $target['login'], 'access' => 'bar', 'status' => 'active']));
        staffAccessUatAssert(str_contains($filtered['body'], 'Zobrazeno 1 z '), 'combined_filter', $checks);
        staffAccessUatLogin($targetCookie, $target);
        staffAccessUatAssert(staffAccessUatRequest($targetCookie, 'bar/index.php')['status'] === 200, 'bar_role_effective', $checks);
        staffAccessUatAssert(staffAccessUatRequest($targetCookie, 'sprava_pracovnich_pozic.php')['status'] === 403, 'system_access_denied', $checks);
        foreach (['1', '0'] as $enabled) {
            $page = staffAccessUatRequest($adminCookie, $query);
            $form = staffAccessUatForm($page['body'], 'set_consumption');
            $result = staffAccessUatRequest($adminCookie, $query, array_merge(
                $form,
                ['enabled' => $enabled, 'reason' => 'TEST přístupy ' . $state['run_id'] . ': vlastní spotřeba']
            ));
            $grant = $pdo->prepare("SELECT COUNT(*) FROM kis_bar_permissions WHERE trainer_id=? AND permission_code='own_consumption'");
            $grant->execute([$targetId]);
            staffAccessUatAssert(
                $result['status'] === 303 && (int)$grant->fetchColumn() === (int)$enabled,
                $enabled === '1' ? 'consumption_granted' : 'consumption_revoked',
                $checks
            );
        }
        $page = staffAccessUatRequest($adminCookie, $query);
        $form = staffAccessUatForm($page['body'], 'save_positions');
        $saved = staffAccessUatRequest($adminCookie, $query, array_merge(
            $form,
            ['positions' => ['coach'], 'default_position' => 'coach', 'reason' => 'TEST přístupy ' . $state['run_id'] . ': odebrání rolí']
        ));
        $positions->execute([$targetId]);
        staffAccessUatAssert($saved['status'] === 303 && $positions->fetchAll(PDO::FETCH_COLUMN) === ['coach'], 'roles_removed', $checks);
        staffAccessUatAssert(staffAccessUatRequest($targetCookie, 'bar/index.php')['status'] === 403, 'revoked_bar_access_denied', $checks);
        $audit = $pdo->prepare("SELECT COUNT(*) FROM staff_position_assignment_events WHERE trainer_id=? AND action='positions_changed'");
        $audit->execute([$targetId]);
        staffAccessUatAssert((int)$audit->fetchColumn() === 2, 'role_changes_audited', $checks);
        $audit = $pdo->prepare("SELECT COUNT(*) FROM kis_bar_events WHERE entity_type='permission' AND entity_id=?");
        $audit->execute([$targetId]);
        staffAccessUatAssert((int)$audit->fetchColumn() === 2, 'consumption_changes_audited', $checks);
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
    } finally {
        $cleanup = staffAccessUatCleanup($pdo, $state, $file);
        unset($credentials);
    }
    return ['ok' => $error === null && $cleanup['original_roles_unchanged'], 'checks' => $checks, 'error' => $error,
        'test_account_ids' => array_values($state['actors']), 'cleanup' => $cleanup];
}

function staffAccessUatMain(): int
{
    if (PHP_SAPI !== 'cli' || getenv('APP_HOST') !== 'kis.kovopraha.cz'
        || getenv('STAFF_ACCESS_UAT_CONFIRM') !== 'OVERIT-PRISTUPY') {
        throw new RuntimeException('Production access UAT is not authorized.');
    }
    $run = (string)getenv('STAFF_ACCESS_UAT_RUN_ID');
    if (!preg_match('/^[0-9]{8,20}$/D', $run) || basename(__DIR__) !== 'staff-access-' . $run
        || basename(dirname(__DIR__)) !== '.kis-deploy') {
        throw new RuntimeException('Access UAT requires its dedicated private operations directory.');
    }
    $root = realpath((string)getenv('APP_ROOT'));
    if ($root === false || basename($root) !== 'kis.kovopraha.cz') {
        throw new RuntimeException('Invalid production application root.');
    }
    require $root . '/config.php';
    $configuration = get_defined_constants();
    if (($configuration['JE_LOKALNE'] ?? null) !== false || ($configuration['APP_BASE_URL'] ?? null) !== 'https://kis.kovopraha.cz') {
        throw new RuntimeException('Unexpected production configuration identity.');
    }
    require_once $root . '/includes/staff_workspaces.php';
    require_once $root . '/includes/staff_account_lifecycle.php';
    require_once $root . '/includes/password_security.php';
    $pdo = new PDO(
        'mysql:host=' . $configuration['DB_HOST'] . ';dbname=' . $configuration['DB_NAME'] . ';charset=utf8mb4',
        $configuration['DB_USER'],
        $configuration['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $file = __DIR__ . '/state.json';
    $phase = (string)getenv('STAFF_ACCESS_UAT_PHASE');
    if ($phase === 'baseline') {
        if (is_file($file)) {
            throw new RuntimeException('Baseline already exists; refusing to overwrite it.');
        }
        $snapshot = staffAccessUatSnapshot($pdo);
        staffAccessUatWriteState($file, ['run_id' => $run, 'expires_at' => time() + 7200, 'baseline' => $snapshot, 'actors' => []]);
        $result = ['ok' => true, 'baseline' => $snapshot];
    } else {
        $state = json_decode((string)file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['run_id'] ?? '') !== $run || !is_array($state['baseline'] ?? null)) {
            throw new RuntimeException('Access UAT recovery state is invalid.');
        }
        $result = match ($phase) {
            'verify' => staffAccessUatVerify($pdo, $state, $file),
            'cleanup' => staffAccessUatCleanupResult($pdo, $state, $file),
            default => throw new RuntimeException('Unknown access UAT phase.'),
        };
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    return $result['ok'] ? 0 : 1;
}

function staffAccessUatCleanupResult(PDO $pdo, array &$state, string $file): array
{
    $cleanup = staffAccessUatCleanup($pdo, $state, $file);
    return ['ok' => $cleanup['original_roles_unchanged'], 'cleanup' => $cleanup];
}
