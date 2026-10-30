<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/QueryProfilePdo.php';
$database = $argv[2] ?? '';
if (!preg_match('/^w74_profile_[a-f0-9]{16}$/D', $database)) {
    throw new RuntimeException('Invalid owned schema.');
}
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
define('APP_BASE_URL', 'http://localhost');
$mode = $argv[3];
$instrument = ($argv[4] ?? '') === 'profile';
$pdo = w72Connection($database, $instrument);
$root = $argv[6];
$_SERVER['REQUEST_METHOD'] = ($argv[5] ?? '') === 'POST' ? 'POST' : 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/booking/sportovni_prehled.php';
require_once $root.'/includes/session_security.php';
session_id('w72'.bin2hex(random_bytes(8)));
app_session_start();
$_SESSION = ['verejny_uzivatel_id' => 10,'verejny_uzivatel_session_version' => 1,'csrf_token' => str_repeat(
    'a',
    64
)];
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
if ($instrument) {
    $pdo->profile = [];
    $pdo->profiling = true;
    $pdo->traceCallers = true;
    $pdo->section = 'settings';
    $markers = [
        '$overview = familyPortalOverview' => 'profiles',
        '    foreach ($overview as $profile) {' => 'finance',
        "    if (shopBeneficiaryColumnExists(" => 'orders',
        'if (clubMakeupReady($pdo))' => 'makeup',
        '    $familyAgenda = familyCalendarAgenda' => 'agenda',
        '    $trainingRsvps = trainingRsvpUpcomingForAccount' => 'rsvp',
        '    $weeklySummary = familyWeeklySummaryPreview' => 'weekly',
        '    $annualPaidOverview = familyAnnualPaidOverview(' => 'annual',
        '$familyTasks' => 'tasks',
        '$roleLabels = [' => 'render',
    ];
    foreach ($markers as $anchor => $section) {
        $position = strpos($source, $anchor);
        if ($position === false) {
            throw new RuntimeException('Missing section anchor: '.$section);
        }
        $source = substr_replace(
            $source,
            '$pdo->section = '.var_export($section, true).';'.PHP_EOL,
            $position,
            0
        );
    }
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
$outsideReads = 0;
$outsideRead = static function () use (&$outsideReads): bool {
    $outsideReads++;
    return true;
};
FamilySchemaReadScope::remember($pdo, 'scope-cleanup-check', $outsideRead);
FamilySchemaReadScope::remember($pdo, 'scope-cleanup-check', $outsideRead);
if ($outsideReads !== 2) {
    throw new RuntimeException('The page leaked its schema scope after rendering.');
}
restore_error_handler();
$data = compact(
    'overview',
    'financeBalances',
    'familyOrderItems',
    'familyAgenda',
    'trainingRsvps',
    'weeklySummary',
    'annualPaidOverview',
    'makeupProfileSummaries',
    'familyTasks'
);
foreach (['loadError','agendaError','weeklyError','annualPaidOverviewError','familyTasksError'] as $key) {
    if ($$key !== '') {
        throw new RuntimeException($key.': '.$$key);
    }
}
if (count($financeBalances) !== count($overview) || count($makeupProfileSummaries) !== count($overview)) {
    throw new RuntimeException('A profile section failed silently.');
}
if (str_contains($html, 'FOREIGN_SENTINEL') || str_contains(
    json_encode($data, JSON_THROW_ON_ERROR),
    'FOREIGN_SENTINEL'
)) {
    throw new RuntimeException('Foreign family leak.');
}
// Each balance carries its observation timestamp; it is not business data.
foreach ($data['financeBalances'] as &$balance) {
    $stamp = htmlspecialchars(
        uiFormatDateTime((string) $balance['as_of']),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
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

    'data_sha256' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),'people' => count($overview),
'queries' => $instrument ? $pdo->profile : []];
session_destroy();
echo json_encode($result, JSON_THROW_ON_ERROR);
exit;
