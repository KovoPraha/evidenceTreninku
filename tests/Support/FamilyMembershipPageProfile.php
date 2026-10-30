<?php

declare(strict_types=1);
require_once __DIR__.'/FamilyOverviewPageFixture.php';
$database = 'w74_profile_'.bin2hex(random_bytes(8));

/** Mirror unchanged runtime sources, preserving asset versions; override only the frozen W7.3 reader. */
function w74Mirror(string $dir): string
{
    $root = dirname(__DIR__, 2);
    $target = $dir.'/baseline';
    mkdir($target, 0700);
    $paths = array_merge(glob($root.'/*.php') ?: [], glob($root.'/*.css') ?: [], glob($root.'/*.js') ?: []);
    foreach (['includes', 'assets', 'booking'] as $folder) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $paths[] = $file->getPathname();
            }
        }
    }
    foreach ($paths as $path) {
        $relative = substr($path, strlen($root) + 1);
        if (in_array($relative, ['config.php', 'db.php'], true)) {
            continue;
        }
        $destination = $target.'/'.$relative;
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0700, true);
        }
        if (!copy($path, $destination) || !touch($destination, (int) filemtime($path))) {
            throw new RuntimeException('Cannot mirror runtime source.');
        }
    }
    if (!copy(__DIR__.'/FamilyMembershipPortalW73.txt', $target.'/includes/family_portal.php')) {
        throw new RuntimeException('Cannot install frozen membership baseline.');
    }
    return $target;
}
function w74RemoveOwnedDirectory(string $dir, string $database): void
{
    $expected = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.$database;
    if (realpath($dir) !== $expected || !preg_match('/^w74_profile_[a-f0-9]{16}$/D', $database)) {
        throw new RuntimeException('Refusing cleanup outside owned temporary profile.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($dir);
}
$admin = w72Connection();
$admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$dir = sys_get_temp_dir().'/'.$database;
mkdir($dir, 0700);
$before = in_array('--before', $argv, true);
$results = [];
try {
    $baselineRoot = w74Mirror($dir);
    $pdo = w72Connection($database);
    w72Seed($pdo);
    if (in_array('--history', $argv, true)) {
        for ($h = 1; $h <= 50; $h++) {
            $pdo->exec("INSERT INTO treninky(id,datum,napln,delka,kategorie) VALUES(".(101 + $h).",DATE_SUB(CURRENT_DATE,INTERVAL $h DAY),'LOCALHOST history',60,'draha')");
            $pdo->exec('INSERT INTO trenink_sportovec SELECT '.(101 + $h).',id FROM sportovci WHERE id BETWEEN 1 AND 20');
        }
    }
    $relation = $pdo->prepare("INSERT INTO "
        . "account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
        . "VALUES(10,?,'guardian','approved','2020-01-01',7,'Synthetic')");
    foreach (in_array('--post', $argv, true) ? [1] : (in_array('--family-size=20', $argv, true) ? [20] : [0,1,5,20]) as $size) {
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
                    __DIR__.'/FamilyMembershipPageWorker.php',
                    '--worker',
                    $database,
                    $mode,
                    $instrument ? 'profile' : 'plain',
                    in_array('--post', $argv, true) ? 'POST' : 'GET',
                    $mode === 'baseline' ? $baselineRoot : dirname(__DIR__, 2)],
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
        $membership = static fn (array $qs): int => count(array_filter($qs, static fn (array $q): bool =>
     str_starts_with($q['sql'], 'SELECT * FROM sportovci WHERE id')));
        $metadata = static fn (array $qs): array => array_values(array_filter($qs, static fn (array $q): bool =>
     str_contains($q['sql'], 'information_schema.')));
        $business = static fn (array $qs): array => array_values(array_map(static fn (array $q): array =>
     [$q['sql'],$q['parameters'],$q['fetched_rows']], array_filter($qs, static fn (array $q): bool =>
     !str_starts_with($q['sql'], 'SELECT * FROM sportovci WHERE id'))));
        if ($business($queries['baseline']) !== $business($queries['current'])) {
            throw new RuntimeException('Business queries or fetched rows changed.');
        }
        $saved = $membership($queries['baseline']) - $membership($queries['current']);
        $expectedSavings = 7 * max(0, $size - 1);
        if ($saved !== $expectedSavings
            || count($queries['baseline']) - count($queries['current']) !== $expectedSavings
            || array_sum(array_column($queries['baseline'], 'fetched_rows')) !==
                array_sum(array_column($queries['current'], 'fetched_rows'))) {
            throw new RuntimeException('Membership batch SQL or row budget failed: ' . $saved);
        }
        $sections = [];
        $callers = [];
        foreach ($queries as $mode => $entries) {
            foreach ($entries as $entry) {
                $section = $entry['section'];
                $sections[$mode][$section] ??= ['sql' => 0, 'metadata' => 0, 'fetched_rows' => 0];
                $sections[$mode][$section]['sql']++;
                $sections[$mode][$section]['fetched_rows'] += $entry['fetched_rows'];
                if (str_contains($entry['sql'], 'information_schema.')) {
                    $sections[$mode][$section]['metadata']++;
                    $caller = $entry['caller'];
                    $callers[$mode][$caller] = ($callers[$mode][$caller] ?? 0) + 1;
                }
            }
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
                if (str_starts_with($sql, 'SELECT s.id AS sportovec_id') && (!isset($plans[$mode]) ||
     str_contains($sql, 'AND s.id=?'))) {
                    $q = $pdo->prepare('EXPLAIN ' . $sql);
                    $q->execute($entry['parameters']);
                    $plans[$mode] = ['sql' => $sql, 'plan' => $q->fetchAll()];
                }
            }
            $groups[$mode] = array_values($groups[$mode]);
        }
        $results[] = ['family_size' => $size,
            'same_data_and_html' => true, 'same_non_membership_queries' => true, 'sections' => $sections, 'metadata_callers' =>
     $callers,
            'samples' => $samples,
            'median_ms' => array_map(
                static fn (array $s): float => $median(array_column(
                    $s,
                    'render_ms'
                )),
                $samples
            ),

            'median_peak_bytes' => array_map(
                static fn (array $s): float => $median(array_column($s, 'incremental_peak_bytes')),
                $samples
            ),
            'sql_count' => array_map('count', $queries),'fetched_rows' => array_map(static fn (array $q): int =>
     array_sum(array_column($q, 'fetched_rows')), $queries),
            'membership_queries' => array_map($membership, $queries),'query_groups' => $groups,'authorization_explain' =>
     $plans];
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),'native_prepares' =>
     true,'foreign_keys' => (int)$pdo->query('SELECT @@foreign_key_checks')->fetchColumn(),
        'scope' => 'Actual GET controller and HTML after synthetic authenticated PDO bootstrap; no network/browser assets or production data',

        'normalized_volatile_fields' => ['CSP nonce', 'finance as_of and its displayed observation time'],

        'production_mutations' => false,'request_method' => in_array('--post', $argv, true) ? 'POST with invalid CSRF; no action' : 'GET',
        'history_trainings_added' => in_array('--history', $argv, true) ? 50 : 0,
    'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `'.$database.'`');
    w74RemoveOwnedDirectory($dir, $database);
}
