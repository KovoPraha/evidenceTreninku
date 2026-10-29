<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/ProgramCapacityMigratedFixture.php';
require_once __DIR__.'/QueryProfilePdo.php';
require_once dirname(__DIR__, 2).'/includes/family_portal.php';
require_once __DIR__.'/FamilyOverviewBaseline.php';
function w72Connection(string $database = '', bool $profile = false): PDO
{
    $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        throw new RuntimeException('Invalid localhost port.');
    }
    $dsn = 'mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4'.($database !== '' ? ';dbname='.$database : '');
    $pdo = $profile ? new Tests\Support\QueryProfilePdo($dsn, 'root', '') : new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}
function w72LegacyColumns(PDO $pdo, string $table, array $columns): void
{
    $present = $pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $type) {
        if (!in_array($name, $present, true)) {
            $pdo->exec('ALTER TABLE '.$table.' ADD '.$name.' '.$type);
        }
    }
}
function w72Seed(PDO $pdo): void
{
    Tests\Support\programCapacityMigratedSchema($pdo);
    w72LegacyColumns($pdo, 'sportovci', ['stav_clenstvi' => "VARCHAR(20) DEFAULT 'aktivni'",'stav_manualni' => 'INT DEFAULT 1','stav_duvod' => "TEXT NULL",'stav_aktualizovan' => 'DATETIME NULL']);
    w72LegacyColumns(
        $pdo,
        'planovane_treninky',
        ['nazev' => "VARCHAR(100) DEFAULT 'Synthetic training'",
        'cas_od' => "TIME DEFAULT '16:00:00'",
        'cas_do' => "TIME DEFAULT '17:00:00'",
        'popis' => 'TEXT NULL',
        'trener_id' => 'INT NULL',
        'serie_id' => 'INT NULL',
        'trenink_id' => 'INT NULL']
    );
    w72LegacyColumns($pdo, 'treninky', ['datum' => 'DATE NULL','napln' => "TEXT NULL",'delka' => 'DECIMAL(5,2) NULL','kategorie' => "VARCHAR(30) DEFAULT 'draha'"]);
    w72LegacyColumns($pdo, 'mereni_zaznamy', ['sportovec_id' => 'INT NULL','typ' => 'VARCHAR(100) NULL','vzdalenost' => 'VARCHAR(100) NULL','cas' => 'VARCHAR(100) NULL','rpe' => 'VARCHAR(100) NULL']);
    w72LegacyColumns($pdo, 'sportovist', ['nazev' => 'VARCHAR(100) NULL']);
    w72LegacyColumns($pdo, 'individualni_lekce', ['sportoviste_id' => 'INT NULL','stav' => "VARCHAR(30) DEFAULT 'aktivni'"]);
    w72LegacyColumns($pdo, 'verejne_rezervace', ['uzivatel_id' => 'INT NULL']);
    foreach (['trenink_sportovec(trenink_id INT NOT NULL,sportovec_id INT NOT NULL,PRIMARY KEY(trenink_id,sportovec_id))',
        'trenink_mereni(trenink_id INT NOT NULL,mereni_id INT NOT NULL,PRIMARY KEY(trenink_id,mereni_id))'] as $definition) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS '.$definition.' ENGINE=InnoDB');
    }
    Tests\Support\programCapacityMigratedSeed($pdo);
    Tests\Support\programCapacityMigratedLoad($pdo, 20, false, false);
    $pdo->exec("UPDATE sportovci SET narozeni='2017-01-01',stav_duvod='LOCALHOST NEPLATIT'");
    $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(999,'FOREIGN_SENTINEL','PRIVATE_OTHER_FAMILY','2017-01-01')");
    $pdo->exec("INSERT INTO verejni_uzivatele(id,aktivni,email_overeno,registrovan) VALUES(11,1,1,CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT INTO "
        . "account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
        . "VALUES(11,999,'guardian','approved','2020-01-01',7,'Synthetic')");
    $pdo->exec("INSERT INTO planovane_treninky(id,datum,trener_id,popis) VALUES(100,DATE_ADD(CURRENT_DATE,INTERVAL 2 DAY),7,'Synthetic plan')");
    $pdo->exec("INSERT INTO treninky(id,datum,napln,delka) VALUES(101,CURRENT_DATE,'Synthetic history',1.5)");
    $pdo->exec("INSERT INTO "
        . "training_roster_links(plan_id,team_id,target_date,team_code_snapshot,team_name_snapshot,created_by_trainer_id) "
        . "VALUES(100,1,DATE_ADD(CURRENT_DATE,INTERVAL 2 DAY),'SYN','Synthetic roster',7)");
    $member = $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) "
        . "VALUES(1,?,'active','manual','2020-01-01',7)");
    for ($i = 1;$i <= 20;$i++) {
        $member->execute([$i]);
        $pdo->prepare('INSERT INTO trenink_sportovec VALUES(101,?)')->execute([$i]);
    }
}
$worker = ($argv[1] ?? '') === '--worker';
$database = $worker ? ($argv[2] ?? '') : 'w72_profile_'.bin2hex(random_bytes(8));
if (!preg_match('/^w72_profile_[a-f0-9]{16}$/D', $database)) {
    throw new RuntimeException('Invalid owned schema.');
}
if ($worker) {
    define('APP_BASE_URL', 'http://localhost');
    $mode = $argv[3];
    $instrument = ($argv[4] ?? '') === 'profile';
    $pdo = w72Connection($database, $instrument);
    $root = dirname(__DIR__, 2);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/booking/sportovni_prehled.php';
    require_once $root.'/includes/session_security.php';
    session_id('w72'.bin2hex(random_bytes(8)));
    app_session_start();
    $_SESSION = ['verejny_uzivatel_id' => 10,'verejny_uzivatel_session_version' => 1,'csrf_token' => str_repeat('a', 64)];
    require_once $root.'/includes/ui_shell.php';
    require_once $root.'/includes/auth_session.php';
    if (!auth_session_validate($pdo)) {
        throw new RuntimeException('Synthetic session invalid.');
    }
    $source = (string)file_get_contents($root.'/booking/sportovni_prehled.php');
    if (substr_count($source, "require_once dirname(__DIR__) . '/db.php';") !== 1
        || substr_count($source, 'familyPortalOverview($pdo, $accountId)') !== 1) {
        throw new RuntimeException('Page profile substitution contract changed.');
    }
    // Execute the actual GET controller and template, substituting only an already authenticated synthetic PDO bootstrap.
    $source = str_replace("require_once dirname(__DIR__) . '/db.php';", '', $source);
    $source = str_replace('dirname(__DIR__)', var_export($root, true), $source);
    if ($mode === 'baseline') {
        $source = str_replace('familyPortalOverview($pdo, $accountId)', 'w72BaselineFamilyPortalOverview($pdo, $accountId)', $source);
    }
    if ($instrument) {
        $pdo->profile = [];
        $pdo->profiling = true;
    }
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    ob_start();
    eval(substr($source, 5));
    $html = (string)ob_get_clean();
    $ms = (hrtime(true) - $start) / 1e6;
    $peak = memory_get_peak_usage() - $memory;
    restore_error_handler();
    $data = compact('overview', 'financeBalances', 'familyOrderItems', 'familyAgenda', 'trainingRsvps', 'weeklySummary', 'annualPaidOverview', 'makeupProfileSummaries', 'familyTasks');
    foreach (['loadError','agendaError','weeklyError','annualPaidOverviewError','familyTasksError'] as $key) {
        if ($$key !== '') {
            throw new RuntimeException($key.': '.$$key);
        }
    }
    if (count($financeBalances) !== count($overview) || count($makeupProfileSummaries) !== count($overview)) {
        throw new RuntimeException('A profile section failed silently.');
    }
    if (str_contains($html, 'FOREIGN_SENTINEL') || str_contains(json_encode($data, JSON_THROW_ON_ERROR), 'FOREIGN_SENTINEL')) {
        throw new RuntimeException('Foreign family leak.');
    }
    // Each balance carries its observation timestamp; it is not business data.
    foreach ($data['financeBalances'] as &$balance) {
        $stamp = htmlspecialchars(uiFormatDateTime((string) $balance['as_of']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = str_replace('Stav k ' . $stamp . '.', 'Stav k SYNTHETIC_OBSERVATION.', $html);
        unset($balance['as_of']);
    }unset($balance);
    $result = ['mode' => $mode,
        'render_ms' => $ms,
        'incremental_peak_bytes' => $peak,
        'html_bytes' => strlen($html),
        'html_sha256' => hash(
            'sha256',
            str_replace(
                app_csp_nonce(),
                'SYNTHETIC_CSP_NONCE',
                $html
            )
        ),

        'data_sha256' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),'people' => count($overview),'queries' => $instrument ? $pdo->profile : []];
    session_destroy();
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}
$admin = w72Connection();
$admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$dir = sys_get_temp_dir().'/'.$database;
mkdir($dir, 0700);
$before = in_array('--before', $argv, true);
$results = [];
try {
    $pdo = w72Connection($database);
    w72Seed($pdo);
    $relation = $pdo->prepare("INSERT INTO "
        . "account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
        . "VALUES(10,?,'guardian','approved','2020-01-01',7,'Synthetic')");
    foreach (in_array('--family-size=20', $argv, true) ? [20] : [0,1,5,20] as $size) {
        $pdo->exec('DELETE FROM account_person_roles WHERE account_id=10');
        for ($i = 1;$i <= $size;$i++) {
            $relation->execute([$i]);
        }
        $samples = ['baseline' => [],'current' => []];
        $queries = [];
        $hash = null;
        $htmlHash = null;
        for ($iteration = -1;$iteration < ($before ? 1 : 5);$iteration++) {
            foreach ($iteration % 2 === 0 ? ['baseline','current'] : ['current','baseline'] as $mode) {
                $instrument = $iteration === -1;
                $path = $dir.'/'.$size.'-'.$iteration.'-'.$mode;
                $proc = proc_open(
                    [PHP_BINARY,
                    __FILE__,
                    '--worker',
                    $database,
                    $mode,
                    $instrument ? 'profile' : 'plain'],
                    [0 => ['pipe',
                    'r'],
                    1 => ['file',
                    $path.'.out',
                    'w'],
                    2 => ['file',
                    $path.'.err',
                    'w']],
                    $pipes
                );
                if (!is_resource($proc)) {
                    throw new RuntimeException('Cannot launch isolated page worker.');
                }fclose($pipes[0]);
                $deadline = microtime(true) + 60;
                do {
                    $status = proc_get_status($proc);
                    if (!$status['running']) {
                        break;
                    }usleep(20000);
                } while (microtime(true) < $deadline);
                if ($status['running']) {
                    proc_terminate($proc);
                    proc_close($proc);
                    throw new RuntimeException('Page worker timed out.');
                }proc_close($proc);
                $errors = (string)file_get_contents($path.'.err');
                if ($status['exitcode'] !== 0 || trim($errors) !== '') {
                    throw new RuntimeException('Page worker failed: '.$errors);
                }
                $row = json_decode((string)file_get_contents($path.'.out'), true, 512, JSON_THROW_ON_ERROR);
                $hash ??= $row['data_sha256'];
                $htmlHash ??= $row['html_sha256'];
                if ($row['data_sha256'] !== $hash || $row['html_sha256'] !== $htmlHash || $row['people'] !== $size) {
                    throw new RuntimeException('Page data or HTML changed size='.$size.' mode='.$mode);
                }
                if ($instrument) {
                    $queries[$mode] = $row['queries'];
                } else {
                    $row['queries'] = [];
                    $samples[$mode][] = $row;
                }
            }
        }
        $membership = static fn (array $qs): int => count(array_filter($qs, static fn (array $q): bool => $q['sql'] === 'SELECT * FROM sportovci WHERE id=?'));
        $saved = $membership($queries['baseline']) - $membership($queries['current']);
        if (!$before && $saved !== $size * max(0, $size - 1)) {
            throw new RuntimeException('Whole-page query reduction budget failed.');
        }
        if (!$before && (count($queries['baseline']) - count($queries['current']) !== $saved
            || array_sum(array_column($queries['baseline'], 'fetched_rows')) - array_sum(array_column($queries['current'], 'fetched_rows')) !== 2 * $saved)) {
            throw new RuntimeException('Whole-page SQL or row reduction differs from the targeted hydration change.');
        }
        $median = static function (array $v): float {
            sort($v);
            return $v[intdiv(count($v), 2)];
        };
        $groups = [];
        $plans = [];
        foreach ($queries as $mode => $entries) {
            $groups[$mode] = [];
            foreach ($entries as $entry) {
                $sql = $entry['sql'];
                $groups[$mode][$sql] ??= ['sql' => $sql, 'count' => 0, 'fetched_rows' => 0];
                $groups[$mode][$sql]['count']++;
                $groups[$mode][$sql]['fetched_rows'] += $entry['fetched_rows'];
                if (str_starts_with($sql, 'SELECT s.id AS sportovec_id') && (!isset($plans[$mode]) || str_contains($sql, 'AND s.id=?'))) {
                    $q = $pdo->prepare('EXPLAIN ' . $sql);
                    $q->execute($entry['parameters']);
                    $plans[$mode] = ['sql' => $sql, 'plan' => $q->fetchAll()];
                }
            }
            $groups[$mode] = array_values($groups[$mode]);
        }
        $results[] = ['family_size' => $size,
            'same_data_and_html' => true,
            'samples' => $samples,
            'median_ms' => array_map(
                static fn (array $s): float => $median(array_column(
                    $s,
                    'render_ms'
                )),
                $samples
            ),

            'median_peak_bytes' => array_map(static fn (array $s): float => $median(array_column($s, 'incremental_peak_bytes')), $samples),
            'sql_count' => array_map('count', $queries),'fetched_rows' => array_map(static fn (array $q): int => array_sum(array_column($q, 'fetched_rows')), $queries),
            'membership_queries' => array_map($membership, $queries),'query_groups' => $groups,'authorization_explain' => $plans];
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),'native_prepares' => true,'foreign_keys' => (int)$pdo->query('SELECT @@foreign_key_checks')->fetchColumn(),
        'scope' => 'Actual GET controller and HTML after synthetic authenticated PDO bootstrap; no network/browser assets or production data',
        'normalized_volatile_fields' => ['CSP nonce', 'finance as_of and its displayed observation time'],
        'production_mutations' => false,'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `'.$database.'`');
    foreach (glob($dir.'/*') ?: [] as $file) {
        unlink($file);
    }rmdir($dir);
}
