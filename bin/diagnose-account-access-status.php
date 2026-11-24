<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Read only. Personal addresses are held only in memory; the report contains counts. */
function accountAccessStatusInventory(PDO $pdo): array
{
    $cutoff = 1795533666;
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $validAddress = static fn (string $value): ?string => filter_var($value = mb_strtolower(trim($value), 'UTF-8'), FILTER_VALIDATE_EMAIL) ? $value : null;
    $count = static fn (string $sql): int => (int)$pdo->query($sql)->fetchColumn();

    $public = ['total' => 0, 'cohort' => 0, 'cohort_valid' => 0, 'cohort_unverified' => 0,
        'newer' => 0, 'invalid_email' => 0, 'active' => 0, 'inactive' => 0];
    $dateSql = $mysql ? 'UNIX_TIMESTAMP(registrovan)' : "CAST(strftime('%s',registrovan) AS INTEGER)";
    foreach ($pdo->query("SELECT email,email_overeno,aktivni,{$dateSql} AS registered_epoch FROM verejni_uzivatele") as $row) {
        $public['total']++;
        $valid = $validAddress((string)$row['email']) !== null;
        if (!$valid) {
            $public['invalid_email']++;
        }
        $public[(int)$row['aktivni'] === 1 ? 'active' : 'inactive']++;
        if ($row['registered_epoch'] !== null && (int)$row['registered_epoch'] <= $cutoff) {
            $public['cohort']++;
            if ($valid) {
                $public['cohort_valid']++;
                if ((int)$row['email_overeno'] !== 1) {
                    $public['cohort_unverified']++;
                }
            }
        } else {
            $public['newer']++;
        }
    }

    $access = [];
    $childAccounts = ['total' => 0, 'active' => 0, 'disabled' => 0];
    foreach ($pdo->query('SELECT sportovec_id,active FROM child_access_accounts') as $row) {
        $access[(int)$row['sportovec_id']] = (int)$row['active'];
        $childAccounts['total']++;
        $childAccounts[(int)$row['active'] === 1 ? 'active' : 'disabled']++;
    }
    $athletes = ['total' => 0, 'valid_email' => 0, 'invalid_email' => 0,
        'missing_access' => 0, 'existing_disabled_access' => 0, 'email_shared_groups' => 0,
        'email_shared_people' => 0, 'states' => ['aktivni' => 0, 'neaktivni' => 0,
            'cekajici' => 0, 'archiv' => 0, 'other' => 0]];
    $addresses = [];
    foreach ($pdo->query('SELECT id,email,stav_clenstvi FROM sportovci') as $row) {
        $athletes['total']++;
        $email = $validAddress((string)$row['email']);
        if ($email === null) {
            $athletes['invalid_email']++;
        } else {
            $athletes['valid_email']++;
            $addresses[$email] = ($addresses[$email] ?? 0) + 1;
        }
        $id = (int)$row['id'];
        if (!array_key_exists($id, $access)) {
            $athletes['missing_access']++;
        } elseif ($access[$id] !== 1) {
            $athletes['existing_disabled_access']++;
        }
        $state = (string)$row['stav_clenstvi'];
        $athletes['states'][array_key_exists($state, $athletes['states']) ? $state : 'other']++;
    }
    foreach ($addresses as $people) {
        if ($people > 1) {
            $athletes['email_shared_groups']++;
            $athletes['email_shared_people'] += $people;
        }
    }

    $trainers = ['total' => 0, 'valid_email' => 0, 'invalid_email' => 0];
    foreach ($pdo->query('SELECT email FROM treneri') as $row) {
        $trainers['total']++;
        $trainers[$validAddress((string)$row['email']) === null ? 'invalid_email' : 'valid_email']++;
    }
    $contacts = ['total' => 0, 'invalid_json' => 0, 'valid_addresses' => 0, 'invalid_addresses' => 0];
    foreach ($pdo->query('SELECT emails_json FROM kis_person_contacts') as $row) {
        $contacts['total']++;
        $values = json_decode((string)$row['emails_json'], true);
        if (!is_array($values)) {
            $contacts['invalid_json']++;
            continue;
        }
        foreach ($values as $value) {
            $contacts[is_string($value) && $validAddress($value) !== null ? 'valid_addresses' : 'invalid_addresses']++;
        }
    }

    $schema = [];
    foreach (['existing_email_trust', 'athlete_email_trust', 'monthly_membership_overdue_alerts'] as $table) {
        $stmt = $pdo->prepare($mysql
            ? 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
            : "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        $schema[$table] = (int)$stmt->fetchColumn() === 1;
    }
    return ['cutoff_utc' => '2026-11-24T15:21:06Z', 'public_accounts' => $public,
        'athletes' => $athletes, 'child_accounts' => $childAccounts, 'trainers' => $trainers,
        'import_contacts' => $contacts,
        'relations' => ['approved' => $count("SELECT COUNT(*) FROM account_person_roles WHERE status='approved'"),
            'other' => $count("SELECT COUNT(*) FROM account_person_roles WHERE status<>'approved'")],
        'active_roster_rows' => $count("SELECT COUNT(*) FROM club_roster_members WHERE status='active' AND valid_from<=CURRENT_DATE AND (valid_to IS NULL OR valid_to>=CURRENT_DATE)"),
        'pending_charges' => $count("SELECT COUNT(*) FROM club_member_charges WHERE status='pending'"),
        'past_due_charges' => $count("SELECT COUNT(*) FROM club_member_charges WHERE status='pending' AND due_on<CURRENT_DATE"),
        'new_tables_already_present' => $schema];
}

function diagnoseAccountAccessStatusMain(): void
{
    $pdo = null;
    $step = 'target';
    try {
        $root = realpath((string)getenv('APP_ROOT'));
        $sha = (string)getenv('ACCESS_STATUS_SOURCE_SHA');
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || $root === false || !is_file($root . '/config.php')
            || preg_match('/^[a-f0-9]{40}$/D', $sha) !== 1) {
            throw new RuntimeException('Unexpected inventory target.');
        }
        $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
        $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        $step = 'configuration';
        ob_start();
        try {
            require $root . '/config.php';
        } finally {
            ob_end_clean();
        }
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
            throw new RuntimeException('Production configuration required.');
        }
        $step = 'connection';
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec('START TRANSACTION READ ONLY');
        $step = 'inventory';
        $summary = accountAccessStatusInventory($pdo);
        $pdo->rollBack();
        $ok = !in_array(true, $summary['new_tables_already_present'], true)
            && $summary['athletes']['total'] >= $summary['child_accounts']['total'];
        echo json_encode(
            ['contract' => 'account-access-predeploy-v1', 'ok' => $ok,
            'read_only' => true, 'personal_data_exported' => false, 'source_sha' => $sha,
            'at_utc' => gmdate('c'), 'probe_sha256' => hash_file('sha256', __FILE__)] + $summary,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        if (!$ok) {
            exit(1);
        }
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, json_encode(['ok' => false, 'read_only' => true, 'step' => $step,
            'error_type' => get_class($error)], JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    diagnoseAccountAccessStatusMain();
}
