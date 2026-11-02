<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once is_file(__DIR__ . '/member_evidence_operation.php')
    ? __DIR__ . '/member_evidence_operation.php' : dirname(__DIR__) . '/includes/member_evidence_operation.php';

function memberEvidenceProductionConnection(string $root): PDO
{
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    require_once $root . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('Production configuration required.');
    }
    return new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function memberEvidenceProductionHttpPaused(): void
{
    foreach (['/', '/kis_rosters_admin.php', '/booking/eshop.php'] as $path) {
        $curl = curl_init('https://kis.kovopraha.cz' . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($status !== 503 || !is_string($body) || !str_contains($body, 'Probíhá převod evidence členů')) {
            throw new RuntimeException('HTTP maintenance is not effective.');
        }
    }
}

function memberEvidenceProductionBackup(string $root, string $directory): array
{
    mkdir($directory . '/backup', 0700);
    $wrapper = "<?php\nputenv('APP_HOST=kis.kovopraha.cz');\n"
        . 'putenv(' . var_export('BACKUP_APP_ROOT=' . $root, true) . ");\n"
        . 'putenv(' . var_export('BACKUP_TARGET_DIR=' . $directory . '/backup', true) . ");\n"
        . "putenv('BACKUP_JSON=1');\nrequire " . var_export($root . '/bin/db-backup.php', true) . ";\n";
    memberEvidenceOperationWrite($directory . '/backup-run.php', $wrapper);
    $process = proc_open([PHP_BINARY, $directory . '/backup-run.php'], [0 => ['pipe', 'r'],
        1 => ['file', $directory . '/backup-result.json', 'w'], 2 => ['file', $directory . '/backup-error.log', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start backup.');
    }
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Fresh backup failed; private error log retained.');
    }
    $result = json_decode((string) file_get_contents($directory . '/backup-result.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!($result['ok'] ?? false) || basename($result['backup']) !== $result['backup']
        || !hash_equals($result['sha256'], (string) hash_file('sha256', $directory . '/backup/' . $result['backup']))) {
        throw new RuntimeException('Fresh backup verification failed.');
    }
    return $result;
}

function memberEvidenceProductionMain(): void
{
    umask(0077);
    $phase = 'target';
    $root = realpath((string) getenv('APP_ROOT'));
    $directory = realpath((string) getenv('MEMBER_OPERATION_DIRECTORY'));
    $pdo = null;
    $operation = [];
    $failure = null;
    $result = ['ok' => false, 'cutover_committed' => false];
    $lock = null;
    $locked = false;
    try {
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || $root === false || $directory === false
            || $directory === $root || str_starts_with($directory . '/', $root . '/')
            || !preg_match('/^member-cutover-[1-9][0-9]{0,19}$/D', basename($directory))) {
            throw new RuntimeException('Unexpected production operation target.');
        }
        $lock = fopen(dirname($directory) . '/member-cutover.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another member operation is still running.');
        }
        $locked = true;
        $operation = json_decode((string) file_get_contents($directory . '/operation.json'), true, 512, JSON_THROW_ON_ERROR);
        memberEvidenceOperationAuthorize($operation);
        if (basename($directory) !== 'member-cutover-' . $operation['run_id']) {
            throw new RuntimeException('Operation identity mismatch.');
        }
        $recover = getenv('MEMBER_OPERATION_MODE') === 'recover';
        if ($recover && is_file($directory . '/pause.json')) {
            $state = json_decode((string) file_get_contents($directory . '/pause.json'), true, 512, JSON_THROW_ON_ERROR);
            putenv('KIS_MEMBER_CUTOVER_TOKEN=' . $state['token']);
        }
        $pdo = memberEvidenceProductionConnection($root);
        if ($recover) {
            $result['recovery_only'] = true;
            $result['ok'] = true;
        } else {
            $phase = 'runtime';
            $deployment = json_decode((string) file_get_contents($root . '/var/deployment.json'), true, 512, JSON_THROW_ON_ERROR);
            $result['runtime'] = ['deployment_sha' => $deployment['sha'] ?? null,
                'proc_open' => function_exists('proc_open'), 'curl_init' => function_exists('curl_init')];
            if (!preg_match('/^[a-f0-9]{40}$/D', (string) ($operation['release_sha'] ?? ''))
                || ($deployment['sha'] ?? '') !== $operation['release_sha']) {
                throw new RuntimeException('Unexpected deployed application.');
            }
            $phase = 'runtime_capabilities';
            if (!$result['runtime']['proc_open'] || !$result['runtime']['curl_init']) {
                throw new RuntimeException('Required operational capability unavailable.');
            }
            $phase = 'runtime_hashes';
            $hashes = json_decode((string) file_get_contents($directory . '/runtime.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($hashes as $path => $hash) {
                if (!preg_match('~^(?:includes|bin)/[a-z_/-]+[.]php$~D', $path)
                    || !hash_equals($hash, (string) hash_file('sha256', $root . '/' . $path))) {
                    throw new RuntimeException('Cutover runtime does not match the reviewed source.');
                }
            }
            require_once $root . '/includes/member_evidence_cutover.php';
            require_once $root . '/includes/trainer_pilot.php';
            $phase = 'approved_mapping';
            if (!hash_equals('b4168c1bc94b824e4d70c167cdba795465607fc07f8ee3c71b9593b467fea0c9', (string) hash_file('sha256', $directory . '/mapping.json'))) {
                throw new RuntimeException('Mapping differs from the approved and rehearsed bytes.');
            }
            $config = json_decode((string) file_get_contents($directory . '/mapping.json'), true, 512, JSON_THROW_ON_ERROR);
            if (($config['scope'] ?? '') !== 'application_backup' || ($config['cutover_on'] ?? '') !== '2026-11-02'
                || count($config['teams'] ?? []) !== 44 || count($config['groups'] ?? []) !== 8
                || (int) $pdo->query('SELECT COUNT(*) FROM member_evidence_cutover_runs')->fetchColumn() !== 0) {
                throw new RuntimeException('Unexpected scope or already completed cutover.');
            }
            $pdo->exec('START TRANSACTION READ ONLY');
            memberEvidencePreview($pdo, memberEvidencePrepareOpening($pdo, $config, 435, 560));
            $pdo->exec('ROLLBACK');
            $phase = 'pause';
            memberEvidenceOperationPause($root, $directory);
            memberEvidenceProductionHttpPaused();
            // Protected GitHub workers share produkce-kis. New host CLI workers fail in config.php.
            // Drain old connections without killing another application on the shared database.
            $phase = 'drain';
            $idle = false;
            for ($attempt = 0; $attempt < 30; ++$attempt) {
                $q = $pdo->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID()');
                if ((int) $q->fetchColumn() === 0) {
                    $idle = true;
                    break;
                }
                usleep(1000000);
            }
            if (!$idle) {
                throw new RuntimeException('Existing database clients did not drain.');
            }
            $beforeBackup = memberEvidenceCutoverSnapshot($pdo, $config);
            $phase = 'backup';
            $backup = memberEvidenceProductionBackup($root, $directory);
            if ($beforeBackup !== memberEvidenceCutoverSnapshot($pdo, $config)) {
                throw new RuntimeException('Application data changed while writers should be paused.');
            }
            $result['backup'] = array_intersect_key($backup, array_flip(['sha256', 'tables', 'triggers', 'compressed_bytes']));
            $phase = 'technical_identity';
            $actor = memberEvidenceOperationActor($pdo, $operation);
            $phase = 'fresh_manifest';
            $manifest = memberEvidencePreview($pdo, memberEvidencePrepareOpening($pdo, $config, 435, 560));
            memberEvidenceOperationWrite($directory . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $phase = 'apply';
            $result['cutover'] = memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], $actor, $backup['sha256'], true);
            $result['cutover_committed'] = true;
            memberEvidenceOperationWrite($directory . '/committed.json', json_encode($result, JSON_THROW_ON_ERROR));
            $phase = 'verify';
            $result['idempotent'] = memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], $actor, $backup['sha256'], true)['idempotent'];
            $pilot = trainerPilotRecord($pdo);
            $result['existing_pilot_scope_preserved'] = empty($pilot['operational_authorization'])
                ? null : trainerPilotStatus($pdo, TRAINER_PILOT_ACTOR_NAME)['scope_ready'];
            if ($result['existing_pilot_scope_preserved'] === false) {
                throw new RuntimeException('Pilot scope verification failed after commit.');
            }
            $result['ok'] = true;
        }
    } catch (Throwable $error) {
        $failure = ['phase' => $phase, 'type' => get_class($error)];
        if ($error instanceof PDOException) {
            $failure['sqlstate'] = $error->errorInfo[0] ?? $error->getCode();
            $failure['driver_code'] = $error->errorInfo[1] ?? null;
        }
    } finally {
        // Never let revocation failure prevent restoring HTTP; record both recovery outcomes.
        if ($locked && $operation !== []) {
            try {
                if ($pdo instanceof PDO) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $result['technical_actor_revoked'] = memberEvidenceOperationRevoke($pdo, $operation);
                }
            } catch (Throwable $error) {
                $failure['revoke_error'] = get_class($error);
            }
            try {
                $result['maintenance_restored'] = memberEvidenceOperationResume($root, $directory);
            } catch (Throwable $error) {
                $failure['resume_error'] = get_class($error);
            }
        }
        if ($failure !== null) {
            $result['ok'] = false;
            $result['failure'] = $failure;
        }
        if (($result['recovery_only'] ?? false) && $failure === null && ($result['maintenance_restored'] ?? false)) {
            // Recovery verified the original bytes; do not retain redundant credential-bearing copies.
            foreach (['config.php.original', '.htaccess.original', 'pause.json'] as $name) {
                unlink($directory . '/' . $name);
            }
        }
        $result['finished_at'] = gmdate('c');
        if ($directory !== false && $locked) {
            $filename = getenv('MEMBER_OPERATION_MODE') === 'recover' ? 'recovery.json' : 'result.json';
            memberEvidenceOperationWrite($directory . '/' . $filename, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
    if (!$result['ok']) {
        exit(1);
    }
}

memberEvidenceProductionMain();
