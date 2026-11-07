<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!preg_match('/^[a-z-]{3,40}$/D', $argv[2] ?? '')) {
    throw new RuntimeException('CLI owned output and snapshot label required.');
}
$label = $argv[2];
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('SET SESSION TRANSACTION READ ONLY');
$tables = ['sportovci', 'coach_scope_grants', 'planovane_treninky', 'training_roster_links',
    'club_roster_members', 'club_team_edges', 'treninky', 'trenink_sportovec', 'trenink_trener',
    'training_closures', 'training_closure_revisions', 'training_personal_excuses', 'training_rsvps',
    'club_makeup_credits', 'medical_clearance_watches', 'medical_clearance_records', 'medical_clearance_policies',
    'treneri', 'staff_user_positions', 'staff_superadmins'];
$names = array_map(static fn (string $name): string => '`' . $runtime['schema'] . '`.`' . $name . '`', $tables);
$checksums = $admin->query('CHECKSUM TABLE ' . implode(',', $names) . ' EXTENDED')->fetchAll(PDO::FETCH_KEY_PAIR);
if (in_array(null, $checksums, true) || count($checksums) !== count($tables)) {
    throw new RuntimeException('Incomplete domain snapshot.');
}
$counts = [];
foreach ($tables as $name) {
    $counts[$name] = (int)$admin->query('SELECT COUNT(*) FROM `' . $runtime['schema'] . '`.`' . $name . '`')->fetchColumn();
}
$sources = [];
foreach (['training_closure_admin.php', 'includes/training_closure_people.php', 'includes/coach_training_access.php',
    'includes/training_closure.php', 'includes/training_closure_form.php', 'includes/training_day.php',
    'includes/staff_workspaces.php', 'assets/app-ui.js'] as $name) {
    $path = $runtime['web_root'] . '/' . $name;
    $sources[$name] = is_file($path) ? hash('sha256', str_replace("\r\n", "\n", (string)file_get_contents($path))) : null;
}
$report = ['complete' => true, 'synthetic_only' => true, 'label' => $label,
    'checksums' => $checksums, 'rows' => $counts, 'live_root_sources_sha256_lf' => $sources];
file_put_contents($output . '/core-' . $label . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo count($tables) . " domain table checksums and live web-root sources recorded.\n";
