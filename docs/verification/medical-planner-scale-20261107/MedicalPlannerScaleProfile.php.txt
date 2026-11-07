<?php

declare(strict_types=1);

// This command owns only synthetic localhost fixtures; it is never an application endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$action = $argv[1] ?? '';
$output = realpath($argv[2] ?? '');
$level = $argv[3] ?? '';
if ($output === false || !in_array($action, ['seed', 'measure', 'verify', 'diagnose', 'project', 'restrict', 'allow-login', 'checkpoint'], true)) {
    throw new RuntimeException('seed/measure/verify/diagnose/project OWN_OUTPUT LEVEL required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
$declaredRuntime = json_decode((string)file_get_contents($output . '/runtime.json'), true, 512, JSON_THROW_ON_ERROR);
if (($declaredRuntime['port'] ?? null) !== 33479 || ($declaredRuntime['http_port'] ?? null) !== 18115
    || !preg_match('~^C:/xampp/tmp/kis-medical-planner-scale-20261107-[a-f0-9]{8}/data$~D', (string)($declaredRuntime['datadir'] ?? ''))) {
    throw new RuntimeException('Only the explicitly owned scale-study runtime is allowed.');
}
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$levels = [
    'baseline' => [10000, 52, 20, 1, true, true],
    'medical50k' => [50000, 52, 20, 1, true, false],
    'medical100k' => [100000, 52, 20, 1, true, false],
    'planner260' => [10000, 260, 20, 1, false, true],
    'planner520' => [10000, 520, 20, 1, false, true],
    'roster200' => [10000, 52, 200, 10, false, true],
    'roster1000' => [10000, 52, 1000, 50, false, true],
];
if (!isset($levels[$level])) {
    throw new RuntimeException('Unknown fixed scale level.');
}
[$watches, $weeks, $members, $children, $medical, $planner] = $levels[$level];
$directory = $output . '/' . $level;
if (!is_dir($directory)) {
    mkdir($directory);
}
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=33479;dbname=' . $runtime['schema'] . ';charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);
date_default_timezone_set('Europe/Prague');
$pdo->exec("SET time_zone='" . date('P') . "'");

if (in_array($action, ['restrict', 'allow-login'], true)) {
    scaleAssert(preg_match('/^http_[a-f0-9]{10}$/D', $runtime['web_user']) === 1, 'Unexpected owned database user.');
    if ($action === 'allow-login') {
        scaleAssert(empty($runtime['web_process_id']), 'Only prepare fresh logins between stopped web phases.');
        $admin->exec('GRANT INSERT,UPDATE,DELETE ON `' . $runtime['schema'] . '`.* TO '
            . $admin->quote($runtime['web_user']) . "@'127.0.0.1'");
        echo "Owned synthetic DB account temporarily permits standard login. Restrict before any measurement.\n";
        exit;
    }
    $admin->exec('REVOKE INSERT,UPDATE,DELETE ON `' . $runtime['schema'] . '`.* FROM '
        . $admin->quote($runtime['web_user']) . "@'127.0.0.1'");
    scaleWrite($output . '/web-select-only.json', ['complete' => true, 'standard_logins_done_before_revocation' => true,
        'only_schema_select_granted' => true, 'application_roles_unchanged' => true]);
    echo "Owned web DB account now SELECT only.\n";
    exit;
}

if ($action !== 'seed') {
    $grant = $admin->prepare('SELECT Select_priv,Insert_priv,Update_priv,Delete_priv,Create_priv,Drop_priv,Alter_priv,Grant_priv
        FROM mysql.db WHERE User=? AND Host=\'127.0.0.1\' AND Db=?');
    $grant->execute([$runtime['web_user'], $runtime['schema']]);
    $privileges = $grant->fetch(PDO::FETCH_ASSOC);
    scaleAssert(is_array($privileges) && array_shift($privileges) === 'Y'
        && count(array_unique(array_values($privileges))) === 1 && reset($privileges) === 'N', 'Web database account must be SELECT only.');
}

function scaleAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function scaleWrite(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function scaleChecksums(PDO $pdo): array
{
    $tables = ['sportovci', 'coach_scope_grants', 'planovane_treninky', 'training_roster_links', 'club_teams',
        'club_roster_members', 'club_team_edges', 'treninky', 'trenink_sportovec', 'trenink_trener',
        'training_closures', 'training_closure_revisions', 'training_personal_excuses', 'training_rsvps',
        'club_makeup_credits', 'medical_clearance_watches', 'medical_clearance_records', 'medical_clearance_policies',
        'treneri', 'staff_user_positions', 'staff_superadmins'];
    $sums = $pdo->query('CHECKSUM TABLE ' . implode(',', array_map(static fn (string $t): string => '`' . $t . '`', $tables))
        . ' EXTENDED')->fetchAll(PDO::FETCH_KEY_PAIR);
    scaleAssert(count($sums) === count($tables) && !in_array(null, $sums, true), 'Incomplete domain checksum.');
    return $sums;
}

/** Same three-record business fixture as p11SeedMedical, with batched inserts outside all measurements. */
function scaleSeedMedical(PDO $pdo, int $people): void
{
    p11SeedMedical($pdo, 0);
    $policy = (int)$pdo->query('SELECT MAX(id) FROM medical_clearance_policies')->fetchColumn();
    $pdo->beginTransaction();
    try {
        for ($offset = 0; $offset < $people; $offset += 1000) {
            $rows = [];
            for ($i = $offset; $i < min($people, $offset + 1000); $i++) {
                $rows[] = '(' . (10001 + $i) . ",'Synthetic medical','Synthetic','2017-01-01')";
            }
            $pdo->exec('INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES' . implode(',', $rows));
        }
        $pdo->exec("INSERT INTO medical_clearance_watches(sportovec_id,purpose,policy_id,next_review_on,created_by)
            SELECT id,'synthetic-purpose'," . $policy . ',CURRENT_DATE,7 FROM sportovci WHERE id>=10001 ORDER BY id');
        foreach (['superseded', 'pending', 'verified'] as $state) {
            $pdo->exec("INSERT INTO medical_clearance_records(watch_id,issued_on,valid_until,original_location,state,revision,recorded_by,verified_by,verified_at)
                SELECT id,DATE_SUB(CURRENT_DATE,INTERVAL 30 DAY),DATE_ADD(CURRENT_DATE,INTERVAL 30 DAY),
                'Synthetic fixture, no document','" . $state . "',1,7,"
                . ($state === 'pending' ? 'NULL,NULL' : '7,CURRENT_TIMESTAMP') . ' FROM medical_clearance_watches ORDER BY id');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $pdo->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
}

function scaleCases(bool $medical, bool $planner): array
{
    $cases = [];
    $add = static function (string $id, string $role, string $path, int $status = 200) use (&$cases): void {
        $cases[] = compact('id', 'role', 'path', 'status');
    };
    if ($medical) {
        foreach (['all' => '', 'valid' => '?medical_state=valid', 'revoked' => '?medical_state=revoked',
            'empty' => '?medical_state=expired', 'person' => '?person_id=10001', 'page2' => '?medical_page=2',
            'last' => '?medical_page=999999', 'search' => '?medical_q=Synthetic',
            'picker' => '?medical_person_q=Synthetic&medical_person_page=2'] as $mode => $query) {
            $add('medical-' . $mode, 'registrar', '/medical_clearance_admin.php' . $query);
        }
    }
    if ($planner) {
        foreach ([
            ['coach-own', 'coach', ''], ['coach-root', 'coach', '?team_id=1'], ['coach-child', 'coach', '?team_id=10000'],
            ['coach-trainer', 'coach', '?jen_moje=0&trener_id=8'], ['coach-group', 'coach', '?jen_moje=0&skupina_id=1'],
            ['coach-empty', 'coach', '?team_id=999999'], ['lead-all', 'sports_lead', '?jen_moje=0'],
            ['lead-root', 'sports_lead', '?jen_moje=0&team_id=1'], ['lead-own', 'sports_lead', ''],
        ] as [$mode, $role, $query]) {
            $add('planner-' . $mode, $role, '/planovac.php' . $query);
        }
    }
    // Different roles and anonymous routes are checked at every level without repeated timing.
    $add('medical-coach-denied', 'coach', '/medical_clearance_admin.php', 403);
    $add('medical-anonymous-denied', 'anonymous', '/medical_clearance_admin.php', 403);
    $add('planner-registrar-denied', 'registrar', '/planovac.php', 403);
    $add('planner-anonymous-denied', 'anonymous', '/planovac.php', 302);
    return $cases;
}

if ($action === 'seed') {
    // No HTTP process may read while this fixed level is being seeded.
    scaleAssert(empty($runtime['web_process_id']), 'Stop the owned web process before seeding.');
    scaleAssert(!file_exists($directory . '/seed.json'), 'Refusing to overwrite an already seeded level.');
    putenv('EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY');
    putenv('EVIDENCE_MARIADB_SMOKE_PORT=33479');
    putenv('EVIDENCE_PERFORMANCE_DATADIR=' . $runtime['datadir']);
    // SyntheticReadPagesProfile defines localhost only if not already defined; no app config is loaded here.
    require_once __DIR__ . '/SyntheticReadPagesProfile.php';
    p11Session('registrar', (int)$credentials['registrar']['id']);
    medicalClearanceRegistrar($pdo);
    scaleSeedMedical($pdo, $watches);
    $pdo->exec("UPDATE medical_clearance_records SET state='revoked' WHERE state='verified' AND watch_id % 10=0");
    p11SeedPlanner($pdo, $weeks);
    $pdo->exec('DELETE FROM training_rsvps; DELETE FROM club_roster_members; DELETE FROM club_team_edges');
    // Extra names stay below the medical ID range. Every level resets exactly this owned expansion.
    $pdo->exec('DELETE FROM sportovci WHERE id BETWEEN 2001 AND 2999');
    // Existing migrated demo teams can be referenced by offers; preserve them.
    scaleAssert(
        (int)$pdo->query("SELECT COUNT(*) FROM club_teams WHERE id BETWEEN 10000 AND 10049 AND code NOT LIKE 'SCALE-%'")->fetchColumn() === 0,
        'Unexpected team in the owned expansion ID range.'
    );
    $pdo->exec('DELETE FROM club_teams WHERE id BETWEEN 10000 AND 10049');
    $team = $pdo->prepare("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id)
        VALUES(?,1,?,?, 'cycling','synthetic','active',7)");
    $edge = $pdo->prepare("INSERT INTO club_team_edges(parent_team_id,child_team_id,valid_from,actor_trainer_id,reason)
        VALUES(1,?,'2010-01-01',7,'LOCALHOST SYNTHETIC SCALE')");
    $person = $pdo->prepare("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(?,'Synthetic roster','Synthetic','2017-01-01')");
    $member = $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id)
        VALUES(?,?,'active','manual','2010-01-01',7)");
    $rsvp = $pdo->prepare('INSERT INTO training_rsvps(plan_id,sportovec_id,response,responded_at)
        VALUES(?,?,?,CURRENT_TIMESTAMP)');
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < $children; $i++) {
            $team->execute([$i + 10000, 'SCALE-' . $i, 'Synthetic roster ' . $i]);
            $edge->execute([$i + 10000]);
        }
        for ($i = 0; $i < $members; $i++) {
            $id = $i < 20 ? 1001 + $i : 2001 + $i - 20;
            if ($i >= 20) {
                $person->execute([$id]);
            }
            $member->execute([10000 + ($i % $children), $id]);
            if ($i % 4 !== 3) {
                for ($plan = 50000; $plan < 50014; $plan++) {
                    $rsvp->execute([$plan, $id, $i % 4 === 2 ? 'not_going' : 'going']);
                }
            }
        }
        // Irrelevant person's response must never inflate summaries.
        $rsvp->execute([50000, 999, 'going']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $pdo->query('ANALYZE TABLE planovane_treninky,training_roster_links,club_teams,club_roster_members,club_team_edges,training_rsvps')->fetchAll();
    $counts = [];
    foreach (['sportovci', 'planovane_treninky', 'training_roster_links', 'club_teams', 'club_roster_members', 'club_team_edges',
        'training_rsvps', 'medical_clearance_watches', 'medical_clearance_records'] as $tableName) {
        $counts[$tableName] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $tableName . '`')->fetchColumn();
    }
    scaleAssert($counts['medical_clearance_watches'] === $watches && $counts['medical_clearance_records'] === 3 * $watches
        && $counts['planovane_treninky'] === 14 * $weeks && $counts['club_roster_members'] === $members
        && $counts['club_team_edges'] === $children, 'Wrong scale seed.');
    scaleWrite($directory . '/seed.json', ['complete' => true, 'level' => $level, 'dimensions' => $levels[$level], 'counts' => $counts,
        'checksums' => scaleChecksums($pdo), 'medical_first_watch' => (int)$pdo->query('SELECT MIN(id) FROM medical_clearance_watches')->fetchColumn(),
        'today' => date('Y-m-d'), 'monday' => date('Y-m-d', strtotime('monday this week'))]);
    echo 'Seeded ' . $level . ': ' . json_encode($counts, JSON_THROW_ON_ERROR) . "\n";
    exit;
}

$seed = json_decode((string)file_get_contents($directory . '/seed.json'), true, 512, JSON_THROW_ON_ERROR);
scaleAssert($seed['today'] === date('Y-m-d') && $seed['checksums'] === scaleChecksums($pdo), 'Fixture changed or day boundary crossed.');
if ($action === 'checkpoint') {
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $checkpoint = ['complete' => true, 'level' => $level, 'checksums' => scaleChecksums($pdo),
        'server' => $identity, 'tmpdir' => $pdo->query('SELECT @@tmpdir')->fetchColumn(), 'domain_unchanged' => true];
    scaleWrite($directory . '/checkpoint-' . date('His') . '.json', $checkpoint);
    echo "All 21 owned domain checksums still match the seed.\n";
    exit;
}
$cases = scaleCases($medical, $planner);

if ($action === 'measure') {
    scaleAssert(!file_exists($directory . '/http.json'), 'A complete or partial measurement already exists.');
    $report = ['complete' => false, 'level' => $level, 'server' => $identity['version'], 'php' => PHP_VERSION,
        'method' => 'plain full HTTP, standard login, five warmups, 30 observations, no prepend or query log', 'cases' => []];
    foreach ($cases as $case) {
        $samples = [];
        $hash = null;
        for ($i = 0; $i < ($case['status'] === 200 ? 35 : 1); $i++) {
            $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
            scaleAssert(
                $response['status'] === $case['status'] && !preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $response['body']),
                'Unexpected response ' . $case['id'] . ': ' . $response['status']
            );
            $current = hash('sha256', fullHttpNormalizedHtml($response));
            scaleAssert($hash === null || $hash === $current, 'Unstable complete HTML: ' . $case['id']);
            $hash = $current;
            if ($case['status'] === 200) {
                scaleAssert(str_contains($response['body'], 'app-staff-topbar'), 'Missing full authenticated navigation.');
            }
            if ($case['status'] !== 200 || $i >= 5) {
                $samples[] = array_intersect_key($response, array_flip(['status', 'ttfb_ms', 'total_ms', 'bytes']));
            }
        }
        $report['cases'][] = $case + ['html_sha256_lf_nonce' => $hash, 'samples' => $samples];
        scaleWrite($directory . '/http.json', $report);
        echo $level . '/' . $case['id'] . " timed.\n";
    }
    $report['complete'] = true;
    $report['checksums_unchanged'] = $seed['checksums'] === scaleChecksums($pdo);
    scaleAssert($report['checksums_unchanged'], 'Domain data changed during HTTP timing.');
    scaleWrite($directory . '/http.json', $report);
    exit;
}

if ($action === 'diagnose') {
    $timing = json_decode((string)file_get_contents($directory . '/http.json'), true, 512, JSON_THROW_ON_ERROR);
    scaleAssert($timing['complete'], 'Plain timing must finish first.');
    $hashes = array_column($timing['cases'], 'html_sha256_lf_nonce', 'id');
    $report = ['complete' => false, 'level' => $level, 'method' => 'separate prepend/general log/slow log, owned server only',
        'rows_examined_source' => 'actual MariaDB mysql.slow_log.rows_examined, not EXPLAIN estimates', 'cases' => []];
    $old = $admin->query('SELECT @@GLOBAL.long_query_time long_query_time,@@GLOBAL.slow_query_log slow_query_log,
        @@GLOBAL.general_log general_log,@@GLOBAL.log_output log_output,@@GLOBAL.min_examined_row_limit min_examined_row_limit')->fetch();
    scaleAssert(!(bool)$old['general_log'] && !(bool)$old['slow_query_log'], 'Unexpected existing diagnostic logging.');
    try {
        $admin->exec("SET GLOBAL log_output='TABLE'; SET GLOBAL long_query_time=0; SET GLOBAL min_examined_row_limit=0");
        foreach ($cases as $case) {
            $admin->exec('TRUNCATE TABLE mysql.general_log; TRUNCATE TABLE mysql.slow_log');
            $admin->exec('SET GLOBAL general_log=ON; SET GLOBAL slow_query_log=ON');
            try {
                $response = fullHttpRequest($runtime, $output, $case['role'], $case['path'], headers: ['X-P11-Measure: scale-' . $level . '-' . $case['id']]);
            } finally {
                $admin->exec('SET GLOBAL general_log=OFF; SET GLOBAL slow_query_log=OFF');
            }
            scaleAssert($response['status'] === $case['status'] && hash('sha256', fullHttpNormalizedHtml($response)) === $hashes[$case['id']], 'Diagnostic HTML differs.');
            $metricPath = $runtime['runtime'] . '/metrics/scale-' . $level . '-' . $case['id'] . '.json';
            $metric = json_decode((string)file_get_contents($metricPath), true, 512, JSON_THROW_ON_ERROR);
            scaleAssert(!$metric['fatal'] && ($case['status'] !== 200 || ($metric['db_bootstrap_included'] && $metric['navigation_included'])), 'Incomplete PHP request metric.');
            $q = $admin->prepare("SELECT argument FROM mysql.general_log WHERE command_type IN ('Query','Execute') AND user_host LIKE ? ORDER BY event_time");
            $q->execute(['%' . $runtime['web_user'] . '%']);
            $queries = $q->fetchAll(PDO::FETCH_COLUMN);
            $unique = [];
            foreach ($queries as $sql) {
                $sql = trim((string)preg_replace('/\s+/', ' ', $sql));
                scaleAssert(!preg_match('/^(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql), 'Read attempted domain write.');
                $key = hash('sha256', $sql);
                $unique[$key] ??= ['sql' => $sql, 'count' => 0];
                $unique[$key]['count']++;
            }
            $q = $admin->prepare('SELECT sql_text,query_time,lock_time,rows_sent,rows_examined FROM mysql.slow_log WHERE user_host LIKE ? ORDER BY start_time');
            $q->execute(['%' . $runtime['web_user'] . '%']);
            $slow = $q->fetchAll();
            scaleAssert($case['status'] !== 200 || count($slow) > 0, 'Missing actual server statement statistics.');
            $report['cases'][] = $case + ['html_parity' => true, 'php' => $metric, 'sql_count' => count($queries),
                'rows_examined' => array_sum(array_column($slow, 'rows_examined')), 'rows_sent' => array_sum(array_column($slow, 'rows_sent')),
                'logged_statement_count' => count($slow), 'queries' => array_values($unique), 'server_statements' => $slow, 'write_commands' => []];
            scaleWrite($directory . '/diagnostics.json', $report);
            echo $level . '/' . $case['id'] . ': ' . count($queries) . ' SQL, ' . array_sum(array_column($slow, 'rows_examined')) . " examined.\n";
        }
    } finally {
        $admin->exec('SET GLOBAL general_log=OFF; SET GLOBAL slow_query_log=OFF');
        $admin->exec('SET GLOBAL long_query_time=' . (float)$old['long_query_time'] . '; SET GLOBAL min_examined_row_limit=' . (int)$old['min_examined_row_limit']);
        $admin->exec('SET GLOBAL log_output=' . $admin->quote($old['log_output']));
    }
    $report['complete'] = true;
    $report['checksums_unchanged'] = $seed['checksums'] === scaleChecksums($pdo);
    scaleAssert($report['checksums_unchanged'], 'Domain data changed during diagnostics.');
    scaleWrite($directory . '/diagnostics.json', $report);
    exit;
}

// Verification/projection loads helpers, never production config, and uses explicit synthetic session context.
putenv('EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY');
putenv('EVIDENCE_MARIADB_SMOKE_PORT=33479');
putenv('EVIDENCE_PERFORMANCE_DATADIR=' . $runtime['datadir']);
require_once __DIR__ . '/SyntheticReadPagesProfile.php';
require_once __DIR__ . '/MedicalClearanceListBaseline.php';
require_once __DIR__ . '/PlannerReadBaseline.php';
$pdo = p11Connection($runtime['schema'], true);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$report = ['complete' => false, 'level' => $level, 'read_only' => (bool)$pdo->query('SELECT @@tx_read_only')->fetchColumn(), 'cases' => []];
foreach ($cases as $case) {
    if ($case['status'] !== 200) {
        continue;
    }
    parse_str((string)parse_url($case['path'], PHP_URL_QUERY), $input);
    p11Session($case['role'], (int)$credentials[$case['role']]['id']);
    if (str_starts_with($case['id'], 'medical-')) {
        $pdo->profile = [];
        $pdo->profiling = true;
        $actual = medicalClearanceList($pdo, $input);
        $choices = medicalClearancePersonChoices($pdo, $input, (int)($input['person_id'] ?? 0));
        $pdo->profiling = false;
        $queries = $pdo->profile;
        if ($action === 'verify') {
            $expected = Tests\Support\MedicalClearanceListBaseline::read($pdo, $input);
            scaleAssert($actual === $expected, 'Medical complete domain parity failed: ' . $case['id']);
            scaleAssert(count($choices['rows']) <= 26, 'Medical picker is unbounded.');
            if (isset($input['person_id'])) {
                scaleAssert((int)$choices['selected']['id'] === (int)$input['person_id'] && $actual['total'] === 1, 'Selected context or person lost.');
            }
            $expectedIds = array_map('intval', array_column($expected['rows'], 'id'));
            $expectedPlans = null;
            $domain = ['watch_ids' => $expectedIds, 'total' => $actual['total'], 'page' => $actual['page'], 'pages' => $actual['pages'],
                'statuses' => array_column(array_column($actual['rows'], 'evaluated_status'), 'status'), 'choices' => count($choices['rows'])];
        }
    } else {
        $mine = !isset($input['jen_moje']) || (int)$input['jen_moje'] === 1;
        $trainer = (int)($input['trener_id'] ?? 0);
        $group = (int)($input['skupina_id'] ?? 0);
        $team = (int)($input['team_id'] ?? 0);
        $actor = $case['role'] === 'sports_lead'
            ? (int)json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR)['sports_lead']['id'] : 7;
        p11Session($case['role'], $actor);
        $pdo->profile = [];
        $pdo->profiling = true;
        $pending = trainingPlannerPendingSummary($pdo, date('Y-m-d'), $mine, $actor, $trainer, $group, $team);
        $pdo->profiling = false;
        $queries = $pdo->profile;
        if ($action === 'verify') {
            scaleAssert($pending === Tests\Support\PlannerReadBaseline::pending($pdo, date('Y-m-d'), $mine, $actor, $trainer, $group, $team), 'Pending complete domain parity failed.');
            $expectedPlans = $mine && $actor !== 7 || $team === 999999 ? 0 : ($mine || $trainer > 0 ? 7 : 14);
            $ids = [];
            for ($id = 50000; $id < 50014; $id++) {
                if ($expectedPlans > 0 && (!$mine && !$trainer || $id % 2 === ($trainer === 8 ? 1 : 0))) {
                    $ids[] = $id;
                }
            }
            scaleAssert(count($ids) === $expectedPlans, 'Seed expectation error.');
            $summaries = trainingPlannerRsvpSummaries($pdo, $ids);
            scaleAssert($summaries === trainingRsvpPlanSummaries($pdo, $ids), 'RSVP canonical parity failed.');
            foreach ($summaries as $summary) {
                scaleAssert($summary === ['expected' => $members, 'going' => $members / 2, 'not_going' => $members / 4, 'pending' => $members / 4], 'Seed RSVP numbers differ.');
            }
            $domain = ['plan_ids' => $ids, 'pending' => $pending, 'rsvp' => $summaries];
            $expectedIds = null;
        }
    }
    if ($action === 'project') {
        $report['cases'][] = ['id' => $case['id'], 'scope' => str_starts_with($case['id'], 'medical-') ? 'list plus person picker' : 'historical pending summary only',
            'sql_count' => count($queries), 'fetched_rows' => array_sum(array_column($queries, 'fetched_rows')), 'queries' => $queries];
        continue;
    }
    $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
    scaleAssert($response['status'] === 200, 'DOM HTTP failed.');
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body'], LIBXML_NONET);
    libxml_clear_errors();
    $x = new DOMXPath($dom);
    scaleAssert($x->query('//nav[contains(@class,"app-staff-topbar")]')->length === 1, 'Missing full navigation.');
    if ($expectedPlans !== null) {
        scaleAssert($x->query('//*[contains(concat(" ",normalize-space(@class)," ")," plan-card ")]')->length === $expectedPlans, 'Plan DOM count differs.');
        $foundIds = [];
        foreach ($x->query('//*[contains(concat(" ",normalize-space(@class)," ")," plan-card ")]') as $card) {
            $foundIds[] = (int)$card->getAttribute('data-plan-id');
        }
        sort($foundIds);
        $sorted = $domain['plan_ids'];
        sort($sorted);
        scaleAssert($foundIds === $sorted, 'Plan DOM IDs differ.');
    } else {
        $links = $x->query('//table[thead/tr/th[normalize-space(.)="Osoba"] and thead/tr/th[normalize-space(.)="Účel"]]/tbody/tr//a[starts-with(@href,"?id=")]');
        $foundIds = [];
        foreach ($links as $link) {
            parse_str((string)parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $foundIds[] = (int)($query['id'] ?? 0);
        }
        scaleAssert($foundIds === $expectedIds, 'Medical DOM IDs differ: ' . $case['id']);
        scaleAssert($x->query('//select[@name="sportovec_id"]/option[@value!=""]')->length === $domain['choices'], 'Picker DOM count differs.');
    }
    $report['cases'][] = ['id' => $case['id'], 'domain' => $domain, 'full_domain_parity' => true, 'dom_parity' => true];
    echo $level . '/' . $case['id'] . " verified.\n";
    scaleWrite($directory . '/verification.json', $report);
}
$report['complete'] = true;
$report['checksums_unchanged'] = $seed['checksums'] === scaleChecksums($pdo);
scaleAssert($report['checksums_unchanged'], 'Domain checksums changed during verification/projection.');
scaleWrite($directory . '/' . ($action === 'project' ? 'projections' : 'verification') . '.json', $report);
echo $level . ': ' . count($report['cases']) . ' ' . $action . " cases complete.\n";
