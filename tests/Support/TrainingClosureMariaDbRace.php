<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2).'/includes/training_closure.php';
require_once dirname(__DIR__, 2).'/includes/club_makeup.php';
require_once __DIR__ . '/SportRaceRuntime.php';
$worker = ($argv[1] ?? '') === '--worker';
$db = $worker ? ($argv[2] ?? '') : 'evidence_training_race_' . bin2hex(random_bytes(4));
[$root, $pdo, $runtime] = sportRaceRuntime('evidence_training_race_', $db, $worker);
$_SESSION = ['trener_id' => 1,'staff_positions' => ['sports_lead'],'staff_active_position' => 'sports_lead'];
function tcrPublish(
    string $path,
    array $data
): void {
    $tmp = $path.'.'.getmypid().'.tmp';
    file_put_contents(
        $tmp,
        json_encode(
            $data,
            JSON_THROW_ON_ERROR
        )
    );
    if (!rename(
        $tmp,
        $path
    )) {
        throw new RuntimeException('Barrier publish failed.');
    }
}
function tcrWait(string $path): array
{
    $end = microtime(true) + 20;
    do {
        clearstatcache(
            true,
            $path
        );
        if (is_file($path)) {
            return json_decode(
                (string)file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }usleep(20000);
    } while (microtime(true) < $end);
    throw new RuntimeException('Barrier timeout '.$path);
}
if ($worker) {
    $mode = $argv[3];
    $path = $argv[4];
    $item = (int)$argv[5];
    tcrPublish($path.'.ready', ['connection' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
    try {
        if ($mode === 'closure') {
            $r = trainingClosureClose(
                $pdo,
                100,
                0,
                ['actual_started_at' => '2026-01-02 15:00:00',
    'actual_ended_at' => '2026-01-02 16:00:00',
    'participant_ids' => [10],
    'coach_ids' => [1],
    'reason' => 'LOCALHOST simultaneous paper closure.',
    'confirmed' => true],
                1
            );
        } elseif ($mode === 'schedule') {
            $pdo->beginTransaction();
            trainingScheduleAssertAvailable(
                $pdo,
                1,
                1,
                '2098-01-01',
                '15:00',
                '16:00',
                null,
                []
            );
            $pdo->exec("INSERT INTO planovane_treninky(trener_id,datum,cas_od,cas_do,nazev,stav) "
                . "VALUES(1,'2098-01-01','15:00','16:00','LOCALHOST race','planovany')");
            $r = ['plan_id' => (int)$pdo->lastInsertId()];
            $pdo->commit();
        } elseif ($mode === 'void_vs_finalize') {
            clubMakeupFinalizeTarget($pdo, 200, 100, 1);
            $r = ['finalizer_completed' => true];
        } else {
            $person = 9 + $item;
            $r = ['reservation_id' => clubMakeupReserve($pdo, $person, $item, 200, 'athlete', $item)];
        }
        tcrPublish($path.'.result', ['status' => 'ok','result' => $r]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }tcrPublish(
            $path.'.result',
            ['status' => $e instanceof TrainingClosureException ||
            $e instanceof ClubMakeupException ? 'denied' : 'error',
            'message' => $e->getMessage()]
        );
    }exit;
}
foreach ([
    'treneri(id INT PRIMARY KEY,jmeno VARCHAR(100),aktivni INT)',
    'staff_user_positions(trainer_id INT,position_code VARCHAR(40))',
    'staff_superadmins(trainer_id INT)',
    'sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100),narozeni DATE,stav_clenstvi VARCHAR(20))',
    'treninky(id INT PRIMARY KEY,datum DATE,delka DECIMAL(5,2),napln TEXT,kategorie VARCHAR(20))',
    'trenink_trener(trenink_id INT,trener_id INT)',
    'trenink_sportovec(trenink_id INT,sportovec_id INT)',
    'planovane_treninky(id INT AUTO_INCREMENT PRIMARY KEY,trener_id INT,datum DATE,cas_od TIME,cas_do TIME,sportoviste_id '
        . 'INT NULL,stav VARCHAR(20),trenink_id INT NULL,nazev VARCHAR(100))',
    'training_roster_links(id INT PRIMARY KEY,plan_id INT,trenink_id INT,team_id BIGINT UNSIGNED)',
    'training_roster_expected(link_id INT,sportovec_id INT)',
    'club_teams(id BIGINT UNSIGNED PRIMARY KEY,status VARCHAR(20))',
    'club_roster_members(team_id BIGINT UNSIGNED,sportovec_id INT,status VARCHAR(20),valid_from DATE,valid_to DATE)',
    'club_programs(id BIGINT UNSIGNED PRIMARY KEY)',
    'club_seasons(id BIGINT UNSIGNED PRIMARY KEY)',
    'club_program_offers(id BIGINT UNSIGNED PRIMARY KEY,program_id BIGINT UNSIGNED,season_id BIGINT UNSIGNED,team_id BIGINT '
        . 'UNSIGNED,starts_on DATE,ends_on DATE)',
    'club_program_enrollments(id BIGINT UNSIGNED PRIMARY KEY,offer_id BIGINT UNSIGNED,sportovec_id INT,status '
        . 'VARCHAR(20),valid_from DATE,valid_to DATE)',
    'training_rsvps(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,plan_id INT,sportovec_id INT)',
    'child_access_accounts(id BIGINT UNSIGNED PRIMARY KEY,sportovec_id INT,login_name VARCHAR(100),session_version INT,active INT)',
] as $schema) {
    $pdo->exec('CREATE TABLE '.$schema.' ENGINE=InnoDB');
}
$pdo->exec("INSERT INTO treneri VALUES(1,'LOCALHOST Coach',1);INSERT INTO staff_user_positions VALUES(1,'sports_lead');INSERT INTO "
    . "sportovci VALUES(10,'LOCALHOST','One','2017-01-01','aktivni'),(11,'LOCALHOST','Two','2017-01-01','aktivni');INSERT INTO "
    . "treninky VALUES(100,'2026-01-02',1,'LOCALHOST Session','draha');INSERT INTO trenink_trener VALUES(100,1);INSERT INTO "
    . "trenink_sportovec VALUES(100,10);INSERT INTO club_teams VALUES(1,'active'),(2,'active');INSERT INTO club_programs "
    . "VALUES(1);INSERT INTO club_seasons VALUES(1)");
$m = require dirname(__DIR__, 2).'/migrations/20261023020000_training_closure.php';
$m['up']($pdo);
// Closure and schedule races run before the unrelated makeup fixture is enabled.
$results = [];
$processes = [];
try {
    foreach (['closure','schedule','makeup_last_slot','void_vs_finalize'] as $mode) {
        if ($mode === 'makeup_last_slot') {
            $m = require dirname(__DIR__, 2).'/migrations/20261014110000_club_makeup_lessons.php';
            $m['up']($pdo);
            $future = date('Y-m-d', strtotime('+5 days'));
            $end = date('Y-m-d', strtotime('+30 days'));
            $past = date('Y-m-d', strtotime('-10 days'));
            $pdo->exec("INSERT INTO club_program_offers VALUES(1,1,1,1,'$past','$end'),(2,1,1,2,'$past','$end');"
                . "INSERT INTO club_program_enrollments VALUES(1,1,10,'active','$past','$end'),(2,1,11,'active','$past','$end');"
                . "INSERT INTO child_access_accounts VALUES(1,10,'localone',1,1),(2,11,'localtwo',1,1)");
            $pdo->exec("INSERT INTO planovane_treninky(id,trener_id,datum,cas_od,cas_do,stav,nazev) VALUES(199,1,'$past','15:00','16:00','evidovany','LOCALHOST source'),"
                . "(200,1,'$future','15:00','16:00','planovany','LOCALHOST target');"
                . "INSERT INTO training_roster_links VALUES(1,200,NULL,2)");
            $pdo->exec("INSERT INTO club_makeup_policies(program_id,season_id,team_id,enabled,excuse_hours,updated_by_trainer_id) "
                . "VALUES(1,1,2,1,12,1);INSERT INTO "
                . "club_makeup_slots(plan_id,offer_id,capacity,accepting,birth_year_from,birth_year_to,updated_by_trainer_id) "
                . "VALUES(200,2,1,1,2016,2018,1)");
            foreach ([1 => 10,
            2 => 11] as $credit => $person) {
                $pdo->prepare("INSERT INTO "
                    . "club_makeup_credits(id,source_plan_id,sportovec_id,source_enrollment_id,source_offer_id,status,expires_on,excused_at) "
                    . "VALUES(?,199,?,?,1,'available',?,?)")->execute([$credit,
                $person,
                $credit,
                $end,
                $past.' 12:00:00']);
            }
        }
        if ($mode === 'void_vs_finalize') {
            $pdo->exec("UPDATE planovane_treninky SET trenink_id=100,datum='2026-01-02',stav='evidovany' WHERE id=200");
        }
        $pdo->beginTransaction();
        $pdo->query(match($mode) {
            'closure',
            'void_vs_finalize' => 'SELECT id FROM treninky WHERE id=100 FOR UPDATE',
            'schedule' => 'SELECT id FROM training_schedule_mutex WHERE id=1 FOR UPDATE',
            default => 'SELECT plan_id FROM club_makeup_slots WHERE plan_id=200 FOR UPDATE'
        })->fetchColumn();
        if ($mode === 'void_vs_finalize') {
            $pdo->query('SELECT id FROM planovane_treninky WHERE id=200 FOR UPDATE')->fetchColumn();
            $pdo->exec("UPDATE training_closures SET status='void' WHERE training_id=100;UPDATE club_makeup_reservations SET "
                . "status='cancelled',active_token=NULL WHERE target_plan_id=200;UPDATE club_makeup_credits SET status='available' WHERE "
                . "status='reserved'");
        }
        $jobs = [];
        foreach ([1,
           2] as $item) {
            $path = $runtime.'/'.$db.'-'.$mode.'-'.$item;
            $proc = proc_open(
                [PHP_BINARY,
    __FILE__,
    '--worker',
    $db,
    $mode,
    $path,
    (string)$item],
                [0 => ['pipe',
    'r'],
    1 => ['file',
    $path.'.out',
    'w'],
    2 => ['file',
    $path.'.err',
    'w']],
                $pipes,
                dirname(
                    __DIR__,
                    2
                )
            );
            if (!is_resource($proc)) {
                throw new RuntimeException('Worker start failed.');
            }fclose($pipes[0]);
            $processes[] = $proc;
            $ready = tcrWait($path.'.ready');
            $jobs[] = ['path' => $path,
            'proc' => $proc,
            'connection' => $ready['connection']];
        }
        $ids = array_column(
            $jobs,
            'connection'
        );
        $deadline = microtime(true) + 12;
        $observed = false;
        do {
            $wait = $root->query('SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state=\'LOCK WAIT\' AND trx_mysql_thread_id IN ('.implode(
                ',',
                $ids
            ).')')->fetchColumn();
            if ((int)$wait === 2) {
                $observed = true;
                break;
            }usleep(150000);
        } while (microtime(true) < $deadline);
        if (!$observed) {
            throw new RuntimeException('Two actual concurrent lock waits not observed for '.$mode);
        }$pdo->commit();
        $out = [];
        foreach ($jobs as $job) {
            $out[] = tcrWait($job['path'].'.result');
            if (proc_close($job['proc']) !== 0) {
                throw new RuntimeException('Worker failed.');
            }
        }
        if ($mode === 'void_vs_finalize') {
            if (count(array_filter(
                $out,
                static fn ($r) => $r['status'] === 'ok'
            )) !== 2 ||
            (int)$pdo->query("SELECT COUNT(*) FROM club_makeup_credits WHERE status='used'")->fetchColumn() !== 0 ||
            (int)$pdo->query("SELECT COUNT(*) FROM club_makeup_reservations WHERE status='cancelled'")->fetchColumn() !== 1) {
                throw new RuntimeException('Finalizer consumed a voided lesson '.json_encode($out));
            }
            $results[] = ['scenario' => $mode,'two_real_lock_waits' => true,'void_state_preserved' => true,'no_consumed_credit' => true,'results' => $out,'status' => 'PASS'];
            continue;
        }
        $ok = count(array_filter(
            $out,
            static fn ($r) => $r['status'] === 'ok'
        ));
        if ($ok !== 1 ||
           count(array_filter(
               $out,
               static fn ($r) => $r['status'] === 'denied'
           )) !== 1) {
            throw new RuntimeException('Expected one winner/one denial '.$mode.' '.json_encode($out));
        }
        $count = (int)$pdo->query(match($mode) {
            'closure' => 'SELECT COUNT(*) FROM training_closure_revisions',
            'schedule' => "SELECT COUNT(*) FROM planovane_treninky WHERE datum='2098-01-01'",
            default => "SELECT COUNT(*) FROM club_makeup_reservations WHERE status='booked'"
        })->fetchColumn();
        if ($count !== 1) {
            throw new RuntimeException('Duplicate mutation after race '.$mode);
        }
        $results[] = ['scenario' => $mode,'two_real_lock_waits' => true,'single_committed_record' => true,'results' => $out,'status' => 'PASS'];
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }foreach ($processes as $p) {
        if (is_resource($p)) {
            proc_terminate($p);
            proc_close($p);
        }
    }
}
$report = ['database' => $db,
    'checks' => 12,
    'results' => $results,
    'production_mutations' => false];
file_put_contents(
    $runtime.'/r26-r27-mariadb-race.json',
    json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
);
echo json_encode(
    $report,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
).PHP_EOL;
