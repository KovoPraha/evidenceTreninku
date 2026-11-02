<?php

declare(strict_types=1);

/** Offline operational helpers: no web entry point and no credentials in evidence. */
const MEMBER_EVIDENCE_ACTOR_NAME = 'Převod evidence členů';
const MEMBER_EVIDENCE_ACTOR_MARKER = 'member_evidence_offline_actor_20261102';

function memberEvidenceOperationAuthorize(array $operation): void
{
    if (PHP_SAPI !== 'cli' || ($operation['confirmation'] ?? '') !== 'PREVEST-CLENSTVI'
        || ($operation['workflow_ref'] ?? '') !== 'KovoPraha/evidenceTreninku/.github/workflows/member-evidence-production.yml@refs/heads/main'
        || !preg_match('/^[A-Za-z0-9-]{1,39}$/D', (string) ($operation['actor'] ?? ''))
        || !preg_match('/^[1-9][0-9]{0,19}$/D', (string) ($operation['run_id'] ?? ''))) {
        throw new RuntimeException('Invalid protected operation.');
    }
}

function memberEvidenceOperationActor(PDO $pdo, array $operation): int
{
    memberEvidenceOperationAuthorize($operation);
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT hodnota FROM nastaveni WHERE klic=?');
        $q->execute([MEMBER_EVIDENCE_ACTOR_MARKER]);
        if ($q->fetchColumn() !== false) {
            throw new RuntimeException('A previous technical actor exists; inspect or recover that operation.');
        }
        $q = $pdo->prepare('SELECT id FROM treneri WHERE LOWER(TRIM(jmeno))=LOWER(?)');
        $q->execute([MEMBER_EVIDENCE_ACTOR_NAME]);
        if ($q->fetchColumn()) {
            throw new RuntimeException('Technical identity name collision.');
        }
        // The login verifier rejects every non-hash value; empty email prevents password-reset delivery.
        $pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version) VALUES(?,'','!offline-member-cutover!','trener',1,1)")
            ->execute([MEMBER_EVIDENCE_ACTOR_NAME]);
        $actor = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,'registrar',1,?)")
            ->execute([$actor, $actor]);
        $record = ['trainer_id' => $actor, 'operation' => $operation];
        $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')
            ->execute([MEMBER_EVIDENCE_ACTOR_MARKER, json_encode($record, JSON_THROW_ON_ERROR)]);
        memberEvidenceOperationActorEvent($pdo, $actor, 'cutover_actor_activated', $operation);
        $pdo->commit();
        return $actor;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function memberEvidenceOperationActorEvent(PDO $pdo, int $actor, string $action, array $operation): void
{
    $pdo->prepare('INSERT INTO staff_account_events(trainer_id,actor_trainer_id,action,reason,before_json,after_json) VALUES(?,?,?,?,NULL,?)')
        ->execute([$actor, $actor, $action, 'Vlastníkem schválená technická identita jednorázového převodu, 2. 11. 2026.',
            json_encode(['operation' => $operation, 'login_disabled' => true], JSON_THROW_ON_ERROR)]);
}

/** Revocation is idempotent and only ever touches the marker-owned identity from this run. */
function memberEvidenceOperationRevoke(PDO $pdo, array $operation): bool
{
    memberEvidenceOperationAuthorize($operation);
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT hodnota FROM nastaveni WHERE klic=?');
        $q->execute([MEMBER_EVIDENCE_ACTOR_MARKER]);
        $raw = $q->fetchColumn();
        if ($raw === false) {
            $pdo->rollBack();
            return false;
        }
        $record = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        if ($record['operation'] !== $operation) {
            throw new RuntimeException('Technical identity belongs to another operation.');
        }
        $actor = (int) $record['trainer_id'];
        $q = $pdo->prepare('SELECT jmeno,email,heslo,role,aktivni FROM treneri WHERE id=?');
        $q->execute([$actor]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['jmeno'] !== MEMBER_EVIDENCE_ACTOR_NAME || $row['email'] !== ''
            || $row['heslo'] !== '!offline-member-cutover!' || $row['role'] !== 'trener') {
            throw new RuntimeException('Technical identity has unexpected changes.');
        }
        $q = $pdo->prepare('SELECT position_code FROM staff_user_positions WHERE trainer_id=?');
        $q->execute([$actor]);
        $positions = $q->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($positions, ['registrar'])) {
            throw new RuntimeException('Unexpected technical identity privileges.');
        }
        $pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='registrar'")->execute([$actor]);
        if ((int) $row['aktivni'] !== 0 || $positions !== []) {
            $pdo->prepare('UPDATE treneri SET aktivni=0,session_version=session_version+1 WHERE id=?')->execute([$actor]);
            memberEvidenceOperationActorEvent($pdo, $actor, 'cutover_actor_revoked', $operation);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function memberEvidenceOperationWrite(string $path, string $contents, int $mode = 0600): void
{
    $temporary = $path . '.member-' . bin2hex(random_bytes(8));
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)
            || !chmod($temporary, $mode) || !rename($temporary, $path)) {
            throw new RuntimeException('Cannot atomically write operation file.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

function memberEvidenceOperationPause(string $root, string $directory): array
{
    if (file_exists($directory . '/pause.json')) {
        throw new RuntimeException('Operation directory already contains maintenance state.');
    }
    $config = (string) file_get_contents($root . '/config.php');
    if (!preg_match('/\A<\?php\s*(?:declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?/', $config, $match)) {
        throw new RuntimeException('Unsupported configuration header.');
    }
    $token = bin2hex(random_bytes(32));
    $guard = "\n// Temporary member-evidence maintenance; restored byte-for-byte by the protected operation.\n"
        . "if (PHP_SAPI !== 'cli' || !hash_equals('" . $token . "', (string) getenv('KIS_MEMBER_CUTOVER_TOKEN'))) {\n"
        . "    http_response_code(503); header('Retry-After: 120'); exit('Probíhá převod evidence členů. Zkuste to prosím za chvíli.');\n}\n";
    $patched = substr($config, 0, strlen($match[0])) . $guard . substr($config, strlen($match[0]));
    $htaccess = (string) file_get_contents($root . '/.htaccess');
    $blocked = "# Temporary member-evidence maintenance\nRewriteEngine On\nRewriteRule ^ - [R=503,L]\n"
        . "ErrorDocument 503 \"Probíhá převod evidence členů. Zkuste to prosím za chvíli.\"\n" . $htaccess;
    $state = ['token' => $token, 'files' => []];
    foreach (['config.php' => [$config, $patched], '.htaccess' => [$htaccess, $blocked]] as $name => [$original, $replacement]) {
        $state['files'][$name] = ['original_sha256' => hash('sha256', $original), 'paused_sha256' => hash('sha256', $replacement),
            'mode' => fileperms($root . '/' . $name) & 0777];
        memberEvidenceOperationWrite($directory . '/' . $name . '.original', $original);
    }
    memberEvidenceOperationWrite($directory . '/pause.json', json_encode($state, JSON_THROW_ON_ERROR));
    // Web is blocked first; CLI configurations then reject every process except this operation and its backup child.
    memberEvidenceOperationWrite($root . '/.htaccess', $blocked, $state['files']['.htaccess']['mode']);
    memberEvidenceOperationWrite($root . '/config.php', $patched, $state['files']['config.php']['mode']);
    putenv('KIS_MEMBER_CUTOVER_TOKEN=' . $token);
    return $state;
}

/** Restore only our own exact patches; never overwrite a concurrent external configuration edit. */
function memberEvidenceOperationResume(string $root, string $directory): bool
{
    if (!is_file($directory . '/pause.json')) {
        return false;
    }
    $state = json_decode((string) file_get_contents($directory . '/pause.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($state['files'] as $name => $file) {
        if (!in_array($name, ['config.php', '.htaccess'], true)) {
            throw new RuntimeException('Unexpected recovery file.');
        }
        $original = (string) file_get_contents($directory . '/' . $name . '.original');
        $current = hash_file('sha256', $root . '/' . $name);
        if (!hash_equals($file['original_sha256'], hash('sha256', $original))
            || !in_array($current, [$file['original_sha256'], $file['paused_sha256']], true)) {
            throw new RuntimeException('Maintenance file drift; manual recovery required.');
        }
    }
    foreach ($state['files'] as $name => $file) {
        memberEvidenceOperationWrite($root . '/' . $name, (string) file_get_contents($directory . '/' . $name . '.original'), $file['mode']);
    }
    return true;
}
