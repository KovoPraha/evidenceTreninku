<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Pristup odepren.');
}

require_once __DIR__ . '/report_tyden_lib.php';

function cli_arg(string $key): ?string {
    global $argv;
    foreach ($argv as $a) {
        if (strpos($a, "--{$key}=") === 0) return substr($a, strlen($key) + 3);
    }
    return null;
}

$date = cli_arg('date') ?: date('Y-m-d');
$group = cli_arg('group');
$subgroup = cli_arg('subgroup');

$baseDir = __DIR__ . '/reports/weekly';

if ($group) {
    $skupina_id = (int)$group;
    $pod_id = $subgroup ? (int)$subgroup : null;

    $ctx = build_report_context($pdo, $skupina_id, $pod_id, $date);
    $saved = save_week_report($pdo, $ctx, $baseDir);

    echo "OK: skupina {$skupina_id} ulozeno: {$saved['pdf']}\n";
    exit;
}

$groups = load_groups($pdo);
$ok = 0; $fail = 0;

foreach ($groups as $g) {
    $skupina_id = (int)$g['id'];
    try {
        $ctx = build_report_context($pdo, $skupina_id, null, $date);
        $saved = save_week_report($pdo, $ctx, $baseDir);
        $ok++;
        echo "OK: skupina {$skupina_id} ({$g['nazev']}) -> {$saved['pdf']}\n";
    } catch (Throwable $e) {
        $fail++;
        echo "FAIL: skupina {$skupina_id} ({$g['nazev']}): {$e->getMessage()}\n";
    }
}

echo "DONE: ok={$ok}, fail={$fail}\n";
