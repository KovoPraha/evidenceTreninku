<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';

$worker = ($argv[1] ?? '') === '--worker';
$schema = $worker ? ($argv[2] ?? '') : 'w85_booking_' . bin2hex(random_bytes(8));
if (!preg_match('/^w85_booking_[a-f0-9]{16}$/D', $schema)) {
    throw new RuntimeException('Invalid owned schema.');
}
$directory = sys_get_temp_dir() . '/' . $schema;
$pdo = w72Connection($worker ? $schema : '');

if ($worker) {
    $account = (int) $argv[3];
    $waitlist = $argv[4] === '1';
    $path = $argv[5];
    if (dirname($path) !== $directory || !preg_match('/^request-[0-9]+$/D', basename($path))) {
        throw new RuntimeException('Invalid worker output path.');
    }
    define('APP_BASE_URL', 'http://localhost');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/booking/rezervovat.php';
    require_once dirname(__DIR__, 2) . '/includes/session_security.php';
    session_save_path($directory);
    session_id('w85' . bin2hex(random_bytes(8)));
    app_session_start();
    $_SESSION = ['verejny_uzivatel_id' => $account, 'verejny_uzivatel_session_version' => 1, 'csrf_token' => str_repeat('a', 64)];
    require_once dirname(__DIR__, 2) . '/includes/auth_session.php';
    if (!auth_session_validate($pdo)) {
        throw new RuntimeException('Synthetic account not authenticated.');
    }
    $_GET = ['lekce_id' => '701', 'slot_od' => '10:00', 'slot_do' => '11:00', 'waitlist' => $waitlist ? '1' : '0'];
    $_POST = $_GET + ['csrf_token' => str_repeat('a', 64)];
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/booking/rezervovat.php');
    $bootstrap = "require_once __DIR__ . '/../db.php';";
    if (substr_count($source, $bootstrap) !== 1) {
        throw new RuntimeException('Controller bootstrap contract changed.');
    }
    // Execute the actual controller, replacing only the authenticated synthetic PDO bootstrap.
    $source = str_replace($bootstrap, '', $source);
    $source = str_replace('dirname(__DIR__)', var_export(dirname(__DIR__, 2), true), $source);
    $source = str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/booking', true), $source);
    $result = ['status' => 'completed'];
    ob_start();
    register_shutdown_function(static function () use (&$result): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
    });
    file_put_contents($path . '.ready', (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
    try {
        eval(substr($source, 5));
    } catch (Throwable $error) {
        $result = ['status' => 'error', 'class' => get_class($error), 'message' => $error->getMessage()];
    }
    exit;
}

$pdo->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
mkdir($directory, 0700);
$admin = $pdo;
$processes = [];
try {
    $pdo = w72Connection($schema);
    w72Seed($pdo);
    w72LegacyColumns($pdo, 'sportovist', ['je_verejne' => 'TINYINT NOT NULL DEFAULT 1']);
    w72LegacyColumns($pdo, 'individualni_lekce', ['trener_id' => 'INT NULL', 'typ' => "VARCHAR(20) NOT NULL DEFAULT 'zelena'",
        'max_osob' => 'INT NOT NULL DEFAULT 1', 'vyjimka_3_dny' => 'TINYINT NOT NULL DEFAULT 0']);
    w72LegacyColumns($pdo, 'verejne_rezervace', ['slot_cas_do' => 'TIME NULL', 'poznamka_klienta' => 'TEXT NULL']);
    $pdo->exec('ALTER TABLE verejne_rezervace MODIFY id INT NOT NULL AUTO_INCREMENT, MODIFY cas_rezervace DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    $pdo->exec("INSERT INTO sportovist(id,nazev,je_verejne) VALUES(701,'Synthetic',1)");
    $pdo->exec("INSERT INTO individualni_lekce(id,nazev,datum,cas_od,cas_do,sportoviste_id,trener_id,typ,max_osob,stav) "
        . "VALUES(701,'Synthetic',DATE_ADD(CURRENT_DATE,INTERVAL 10 DAY),'10:00','11:00',701,7,'zelena',1,'aktivni')");
    $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno,registrovan) "
        . "VALUES(12,'Synthetic','Second','second@example.test',1,1,CURRENT_TIMESTAMP)");
    // No VAPID material or subscriptions exist; no worker/transport is invoked.
    if ((int) $pdo->query("SELECT COUNT(*) FROM nastaveni WHERE klic LIKE 'push_vapid_%'")->fetchColumn() !== 0) {
        throw new RuntimeException('Unexpected transport configuration.');
    }
    $counter = 0;
    $run = static function (array $accounts, bool $waitlist) use ($pdo, $schema, $directory, &$processes, &$counter): array {
        $pdo->query("SELECT GET_LOCK('rez_701_1000',5)")->fetchColumn();
        $workers = [];
        try {
            foreach ($accounts as $account) {
                $path = $directory . '/request-' . (++$counter);
                $process = proc_open(
                    [PHP_BINARY, __FILE__, '--worker', $schema, (string) $account, $waitlist ? '1' : '0', $path],
                    [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                    $pipes
                );
                if (!is_resource($process)) {
                    throw new RuntimeException('Worker start failed.');
                }
                fclose($pipes[0]);
                $processes[] = $process;
                $workers[] = ['process' => $process, 'path' => $path];
            }
            $deadline = microtime(true) + 4;
            do {
                $waiting = 0;
                foreach ($workers as $worker) {
                    clearstatcache(true, $worker['path'] . '.ready');
                    if (!is_file($worker['path'] . '.ready')) {
                        continue;
                    }
                    $id = (int) file_get_contents($worker['path'] . '.ready');
                    $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=? AND STATE='User lock'");
                    $statement->execute([$id]);
                    $waiting += (int) $statement->fetchColumn();
                }
                if ($waiting === count($workers)) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            if ($waiting !== count($workers)) {
                $details = [];
                foreach ($workers as $worker) {
                    $details[] = file_get_contents($worker['path'] . '.out') . file_get_contents($worker['path'] . '.err');
                }
                throw new RuntimeException('Concurrent controller lock wait not observed: ' . json_encode($details));
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('rez_701_1000')")->fetchColumn();
        }
        $results = [];
        foreach ($workers as $worker) {
            $deadline = microtime(true) + 20;
            do {
                $state = proc_get_status($worker['process']);
                if (!$state['running']) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            if ($state['running']) {
                throw new RuntimeException('Controller worker timed out.');
            }
            proc_close($worker['process']);
            $result = json_decode((string) file_get_contents($worker['path'] . '.out'), true, 512, JSON_THROW_ON_ERROR);
            $stderr = trim((string) file_get_contents($worker['path'] . '.err'));
            $allowedErrors = ['', 'push_helper: VAPID klíče nejsou nastaveny v tabulce nastaveni.',
                'push_helper: minishlink/web-push není nainstalována. Spusťte: composer require minishlink/web-push'];
            if ($state['exitcode'] !== 0 || !in_array($stderr, $allowedErrors, true)) {
                throw new RuntimeException('Unexpected worker failure: ' . $stderr);
            }
            $results[] = $result;
        }
        return ['observed_waiters' => $waiting, 'workers' => $results];
    };
    $clear = static function () use ($pdo): void {
        $pdo->exec('DELETE FROM booking_notification_context');
        $pdo->exec("DELETE FROM club_event_notifications WHERE notification_type LIKE 'booking_%'");
        $pdo->exec('DELETE FROM verejne_rezervace WHERE lekce_id=701');
    };
    $results = [];
    foreach (['last_place' => [10, 12], 'duplicate' => [10, 10]] as $scenario => $accounts) {
        $clear();
        $results[$scenario] = $run($accounts, false);
        if ((int) $pdo->query('SELECT COUNT(*) FROM verejne_rezervace WHERE lekce_id=701')->fetchColumn() !== 1
            || (int) $pdo->query('SELECT COUNT(*) FROM booking_notification_context')->fetchColumn() !== 1
            || array_column($results[$scenario]['workers'], 'status') !== ['completed', 'completed']) {
            throw new RuntimeException('Reservation race failed: ' . $scenario . ' ' . json_encode($results[$scenario]));
        }
    }
    $clear();
    $pdo->exec("INSERT INTO verejne_rezervace(lekce_id,uzivatel_id,stav,slot_cas_od,slot_cas_do) VALUES(701,11,'potvrzena','10:00','11:00')");
    $results['waitlist'] = $run([10, 12], true);
    if ((int) $pdo->query("SELECT COUNT(*) FROM verejne_rezervace WHERE stav='cekaci_listina'")->fetchColumn() !== 2
        || (int) $pdo->query('SELECT COUNT(*) FROM booking_notification_context')->fetchColumn() !== 0) {
        throw new RuntimeException('Waitlist contract failed.');
    }
    $clear();
    $pdo->exec("CREATE TRIGGER w85_reject_notice BEFORE INSERT ON club_event_notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_outbox_failure'");
    $results['rollback'] = $run([10], false);
    $pdo->exec('DROP TRIGGER w85_reject_notice');
    if ((int) $pdo->query('SELECT COUNT(*) FROM verejne_rezervace WHERE lekce_id=701')->fetchColumn() !== 0
        || (int) $pdo->query('SELECT COUNT(*) FROM booking_notification_context')->fetchColumn() !== 0
        || !str_contains($results['rollback']['workers'][0]['message'] ?? '', 'synthetic_outbox_failure')) {
        throw new RuntimeException('Outbox failure did not roll back reservation.');
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'production_mutations' => false, 'external_sends' => 0, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    foreach ($processes as $process) {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
    foreach (glob($directory . '/*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}
