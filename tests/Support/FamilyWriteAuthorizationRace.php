<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/ProgramCapacityMigratedFixture.php';
require_once dirname(__DIR__, 2) . '/includes/training_rsvp.php';
require_once dirname(__DIR__, 2) . '/includes/account_person_role.php';

function authorizationRaceConnect(string $database = ''): PDO
{
    $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        throw new RuntimeException('Invalid localhost port.');
    }
    return new PDO(
        'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4' . ($database !== '' ? ';dbname=' . $database : ''),
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
}
function authorizationRacePublish(string $path, array $data): void
{
    file_put_contents($path . '.tmp', json_encode($data, JSON_THROW_ON_ERROR));
    if (!rename($path . '.tmp', $path)) {
        throw new RuntimeException('Cannot publish barrier.');
    }
}
function authorizationRaceWaitFile(string $path): array
{
    $deadline = microtime(true) + 25;
    do {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Worker barrier timeout: ' . basename($path));
}
function authorizationRaceWaitLock(PDO $monitor, int $connection): string
{
    $deadline = microtime(true) + 15;
    do {
        $q = $monitor->prepare("SELECT trx_query FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id=?");
        $q->execute([$connection]);
        $sql = $q->fetchColumn();
        if (is_string($sql)) {
            return $sql;
        }
        usleep(100000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Actual InnoDB lock wait was not observed.');
}
function authorizationRaceSnapshot(PDO $pdo): array
{
    $result = [];
    foreach (['training_rsvps', 'training_rsvp_events', 'club_makeup_credits', 'club_makeup_reservations', 'club_makeup_events'] as $table) {
        $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    }
    return $result;
}
function authorizationRaceWrite(PDO $pdo, string $operation, string $actor): void
{
    $actorId = $actor === 'account' ? 10 : 50;
    if ($operation === 'rsvp') {
        trainingRsvpSave($pdo, 100, 1, 'not_going', $actor, $actorId, 'LOCALHOST NEPLATIT');
    } elseif ($operation === 'reserve') {
        clubMakeupReserve($pdo, 1, 1, 200, $actor, $actorId);
    } elseif ($operation === 'cancel') {
        clubMakeupCancelReservation($pdo, 1, 1, $actor, $actorId);
    } elseif ($operation === 'revoke_role') {
        accountPersonRoleRevoke($pdo, 1, 7, 'LOCALHOST locked authorization');
    } elseif ($operation === 'revoke_account') {
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni=0 WHERE id=10');
    } elseif ($operation === 'revoke_email') {
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=10');
    } elseif ($operation === 'revoke_athlete') {
        childAccessSetActive($pdo, 50, false, 7, 'LOCALHOST locked authorization');
    } else {
        throw new RuntimeException('Unknown operation.');
    }
}
$worker = ($argv[1] ?? '') === '--worker';
$database = $worker ? ($argv[2] ?? '') : 'w71b_race_' . bin2hex(random_bytes(8));
if (preg_match('/^w71b_race_[a-f0-9]{16}$/D', $database) !== 1) {
    throw new RuntimeException('Invalid owned schema.');
}
if ($worker) {
    $pdo = authorizationRaceConnect($database);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
    $path = $argv[3];
    authorizationRacePublish($path . '.ready', ['connection' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
    try {
        authorizationRaceWrite($pdo, $argv[4], $argv[5]);
        $result = ['status' => 'ok'];
    } catch (Throwable $e) {
        $denied = ($e instanceof TrainingRsvpException || $e instanceof ClubMakeupException) && str_contains($e->getMessage(), 'oprávnění');
        $result = ['status' => $denied ? 'denied' : 'error', 'message' => $e->getMessage(), 'cause' => $e->getPrevious()?->getMessage()];
    }
    $result['transaction_open'] = $pdo->inTransaction();
    authorizationRacePublish($path . '.result', $result);
    exit;
}
$admin = authorizationRaceConnect();
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$directory = sys_get_temp_dir() . '/' . $database;
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create owned barrier directory.');
}
$results = [];
try {
    $pdo = authorizationRaceConnect($database);
    $revoker = authorizationRaceConnect($database);
    Tests\Support\programCapacityMigratedSchema($pdo);
    // Existing legacy fields absent from checkout's minimal bootstrap, not a production migration.
    $pdo->exec("ALTER TABLE sportovci ADD stav_clenstvi VARCHAR(20) NOT NULL DEFAULT 'aktivni'");
    $pdo->exec("ALTER TABLE planovane_treninky ADD nazev VARCHAR(100) NOT NULL DEFAULT 'Synthetic', ADD cas_od TIME NULL");
    Tests\Support\programCapacityMigratedSeed($pdo);
    Tests\Support\programCapacityMigratedLoad($pdo, 1, false, false);
    $pdo->exec("UPDATE sportovci SET narozeni='2017-01-01' WHERE id=1");
    $pdo->exec("UPDATE club_program_offers SET starts_on=DATE_SUB(CURRENT_DATE,INTERVAL 1 YEAR), ends_on=DATE_ADD(CURRENT_DATE,INTERVAL 1 YEAR)");
    $pdo->exec("INSERT INTO club_program_enrollments(id,offer_id,sportovec_id,account_id,source_order_item_id,status,valid_from,valid_to,activated_at)
        VALUES(1,1,1,10,1,'active',DATE_SUB(CURRENT_DATE,INTERVAL 1 YEAR),DATE_ADD(CURRENT_DATE,INTERVAL 1 YEAR),CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT INTO account_person_roles(id,account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note)
        VALUES(1,10,1,'guardian','approved','2020-01-01',7,'LOCALHOST NEPLATIT')");
    $pdo->exec("INSERT INTO child_access_accounts(id,sportovec_id,login_name,login_key,password_hash,password_changed_at,created_by_trainer_id)
        VALUES(50,1,'synthetic','synthetic','not-a-password',CURRENT_TIMESTAMP,7)");
    $pdo->exec("INSERT INTO planovane_treninky(id,datum,cas_od) VALUES(100,DATE_ADD(CURRENT_DATE,INTERVAL 5 DAY),'16:00:00'),
        (200,DATE_ADD(CURRENT_DATE,INTERVAL 6 DAY),'16:00:00')");
    $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) "
        . "VALUES(1,1,'active','manual','2020-01-01',7)");
    $pdo->exec("INSERT INTO training_roster_links(plan_id,team_id,target_date,team_code_snapshot,team_name_snapshot,created_by_trainer_id)
        VALUES(100,1,DATE_ADD(CURRENT_DATE,INTERVAL 5 DAY),'S1','Synthetic',7),(200,2,DATE_ADD(CURRENT_DATE,INTERVAL 6 DAY),'S2','Synthetic',7)");
    $pdo->exec("INSERT INTO club_makeup_policies(program_id,season_id,team_id,enabled,excuse_hours,updated_by_trainer_id) VALUES(2,1,2,1,12,7)");
    $pdo->exec('INSERT INTO club_makeup_slots(plan_id,offer_id,capacity,birth_year_from,birth_year_to,updated_by_trainer_id) VALUES(200,2,1,2016,2018,7)');
    $pdo->exec("INSERT INTO club_makeup_credits(id,source_plan_id,sportovec_id,source_enrollment_id,source_offer_id,expires_on,excused_at)
        VALUES(1,100,1,1,1,DATE_ADD(CURRENT_DATE,INTERVAL 1 YEAR),CURRENT_TIMESTAMP)");
    foreach (['rsvp', 'reserve', 'cancel'] as $operation) {
        foreach (['role', 'account', 'email', 'expired', 'athlete', 'rollback', 'unchanged', 'athlete_independent'] as $scenario) {
            foreach (['training_rsvp_events', 'training_rsvps', 'club_makeup_events', 'club_makeup_reservations', 'account_person_role_events'] as $table) {
                $pdo->exec('DELETE FROM ' . $table);
            }
            $pdo->exec("UPDATE club_makeup_credits SET status='available' WHERE id=1");
            $pdo->exec("UPDATE account_person_roles SET status='approved',valid_to=NULL WHERE id=1");
            $pdo->exec('UPDATE verejni_uzivatele SET aktivni=1,email_overeno=1 WHERE id=10');
            $pdo->exec('UPDATE child_access_accounts SET active=1 WHERE id=50');
            if ($operation === 'cancel') {
                $pdo->exec("INSERT INTO club_makeup_reservations(id,credit_id,target_plan_id,sportovec_id) VALUES(1,1,200,1)");
                $pdo->exec("UPDATE club_makeup_credits SET status='reserved' WHERE id=1");
            }
            // RSVP waits on the later RSVP lock after eligibility has established a repeatable-read snapshot.
            if ($operation === 'rsvp') {
                $pdo->exec("INSERT INTO training_rsvps(id,plan_id,sportovec_id,response,member_note) VALUES(1,100,1,'going','')");
            }
            $before = authorizationRaceSnapshot($pdo);
            $actor = in_array($scenario, ['athlete', 'athlete_independent'], true) ? 'athlete' : 'account';
            $table = match ($operation) {
                'rsvp' => 'training_rsvps', 'reserve' => 'club_makeup_slots', 'cancel' => 'club_makeup_reservations'
            };
            $key = $operation === 'reserve' ? 'plan_id=200' : 'id=1';
            $pdo->beginTransaction();
            $pdo->query('SELECT * FROM ' . $table . ' WHERE ' . $key . ' FOR UPDATE')->fetchAll();
            $path = $directory . '/' . $operation . '-' . $scenario;
            $process = proc_open(
                [PHP_BINARY, __FILE__, '--worker', $database, $path, $operation, $actor],
                [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                $pipes,
                dirname(__DIR__, 2)
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Worker start failed.');
            }
            fclose($pipes[0]);
            try {
                $ready = authorizationRaceWaitFile($path . '.ready');
                $waitingSql = authorizationRaceWaitLock($admin, $ready['connection']);
                if (!str_contains($waitingSql, $table)) {
                    throw new RuntimeException('Worker waited on an unexpected domain lock.');
                }
                if ($scenario !== 'athlete') {
                    $revoker->beginTransaction();
                }
                if (in_array($scenario, ['role', 'rollback', 'athlete_independent'], true)) {
                    accountPersonRoleRevoke($revoker, 1, 7, 'LOCALHOST concurrent revocation');
                } elseif ($scenario === 'account') {
                    $revoker->exec('UPDATE verejni_uzivatele SET aktivni=0 WHERE id=10');
                } elseif ($scenario === 'email') {
                    $revoker->exec('UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=10');
                } elseif ($scenario === 'expired') {
                    $revoker->exec('UPDATE account_person_roles SET valid_to=CURRENT_TIMESTAMP WHERE id=1');
                } elseif ($scenario === 'athlete') {
                    childAccessSetActive($revoker, 50, false, 7, 'LOCALHOST concurrent revocation');
                }
                if ($scenario === 'rollback') {
                    $revoker->rollBack();
                } elseif ($revoker->inTransaction()) {
                    $revoker->commit();
                }
                $pdo->commit();
                $result = authorizationRaceWaitFile($path . '.result');
                if (proc_close($process) !== 0) {
                    throw new RuntimeException('Worker failed: ' . file_get_contents($path . '.err'));
                }
                $after = authorizationRaceSnapshot($pdo);
                $allow = in_array($scenario, ['rollback', 'unchanged', 'athlete_independent'], true);
                $pass = $result['status'] === ($allow ? 'ok' : 'denied') && !$result['transaction_open'] && ($allow ? $after !== $before : $after === $before);
                $results[] = ['operation' => $operation, 'scenario' => $scenario, 'real_lock_wait' => true, 'waiting_sql' => $waitingSql,
                    'worker' => $result, 'domain_unchanged' => $after === $before, 'expected' => $allow ? 'ok' : 'denied', 'pass' => $pass];
            } finally {
                if ($revoker->inTransaction()) {
                    $revoker->rollBack();
                }
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }
    // The opposite serialization order: a writer's accepted authority stays locked through transaction end.
    foreach (['role', 'account', 'email', 'athlete'] as $scope) {
        foreach (['commit', 'rollback'] as $finish) {
            $pdo->exec("UPDATE account_person_roles SET status='approved',valid_to=NULL WHERE id=1");
            $pdo->exec('UPDATE verejni_uzivatele SET aktivni=1,email_overeno=1 WHERE id=10');
            $pdo->exec('UPDATE child_access_accounts SET active=1 WHERE id=50');
            $actor = $scope === 'athlete' ? 'athlete' : 'account';
            $pdo->beginTransaction();
            if (!participantWriteCanActFor($pdo, $actor, $actor === 'athlete' ? 50 : 10, 1)) {
                throw new RuntimeException('Positive locking authorization failed.');
            }
            $path = $directory . '/hold-' . $scope . '-' . $finish;
            $process = proc_open(
                [PHP_BINARY, __FILE__, '--worker', $database, $path, 'revoke_' . $scope, $actor],
                [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                $pipes,
                dirname(__DIR__, 2)
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Worker start failed.');
            }
            fclose($pipes[0]);
            try {
                $ready = authorizationRaceWaitFile($path . '.ready');
                $waitingSql = authorizationRaceWaitLock($admin, $ready['connection']);
                $before = authorizationRaceSnapshot($pdo);
                if ($finish === 'commit') {
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
                $result = authorizationRaceWaitFile($path . '.result');
                if (proc_close($process) !== 0) {
                    throw new RuntimeException('Revoker worker failed.');
                }
                $pdo->beginTransaction();
                $stillAllowed = participantWriteCanActFor($pdo, $actor, $actor === 'athlete' ? 50 : 10, 1);
                $pdo->rollBack();
                $unchanged = authorizationRaceSnapshot($pdo) === $before;
                $results[] = ['operation' => 'hold_authority', 'scenario' => $scope . '_' . $finish, 'real_lock_wait' => true,
                    'waiting_sql' => $waitingSql, 'worker' => $result, 'domain_unchanged' => $unchanged,
                    'expected' => 'revoker waits until transaction end, then access denied',
                    'pass' => $result['status'] === 'ok' && !$result['transaction_open'] && !$stillAllowed && $unchanged];
            } finally {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }
    $report = ['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'native_prepares' => true,
        'foreign_keys' => (int)$pdo->query('SELECT @@foreign_key_checks')->fetchColumn(), 'schema' => 'full migration catalog plus missing legacy bootstrap fields',
        'production_mutations' => false, 'checks' => count($results), 'failures' => count(array_filter($results, static fn (array $r): bool => !$r['pass'])), 'results' => $results];
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $database . '`');
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
exit($report['failures'] > 0 ? 1 : 0);
