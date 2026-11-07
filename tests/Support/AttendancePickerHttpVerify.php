<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$tables = ['treninky', 'trenink_sportovec', 'trenink_trener', 'planovane_treninky', 'training_closures',
    'training_closure_revisions', 'training_personal_excuses', 'training_rsvps', 'club_makeup_credits'];
$checksum = static fn (): array => $admin->query('CHECKSUM TABLE ' . implode(',', array_map(static fn (string $t): string => '`' . $t . '`', $tables)) . ' EXTENDED')->fetchAll(PDO::FETCH_KEY_PAIR);
$before = $checksum();
$checks = [];
$assert = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($name);
    }
    $checks[] = ['name' => $name, 'pass' => true];
};
$form = static function (array $response, string $section) use ($assert): array {
    $assert(!preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $response['body']), 'No PHP error in ' . $section);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body'], LIBXML_NONET);
    libxml_clear_errors();
    $x = new DOMXPath($dom);
    $node = $x->query('//*[@id="' . $section . '"]//form')->item(0);
    if (!$node) {
        throw new RuntimeException('Missing form: ' . $section);
    }
    $post = [];
    foreach ($x->query('.//input[@name]', $node) as $input) {
        if (in_array($input->getAttribute('type'), ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
            continue;
        }
        $post[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    foreach ($x->query('.//select[@name]', $node) as $select) {
        $selected = $x->query('option[@selected]', $select);
        if (!$selected->length && !$select->hasAttribute('multiple')) {
            $selected = $x->query('option[1]', $select);
        }
        $values = array_map(static fn (DOMElement $n): string => $n->getAttribute('value'), iterator_to_array($selected));
        $name = $select->getAttribute('name');
        $post[str_ends_with($name, '[]') ? substr($name, 0, -2) : $name] = $select->hasAttribute('multiple') ? $values : ($values[0] ?? '');
    }
    foreach ($x->query('.//textarea[@name]', $node) as $textarea) {
        $post[$textarea->getAttribute('name')] = $textarea->textContent;
    }
    return $post;
};
$request = static fn (array $post): array => fullHttpRequest($runtime, $output, 'coach', '/training_closure_admin.php?id=101', $post);
$initial = fullHttpRequest($runtime, $output, 'coach', '/training_closure_admin.php?id=101');
$draft = $form($initial, 'actual-attendance');
$version = (int)$draft['version'];
$host = (int)$admin->query('SELECT MAX(id) FROM sportovci')->fetchColumn();
$trainingDate = (string)$admin->query('SELECT datum FROM treninky WHERE id=101')->fetchColumn();
$draft += ['action' => 'find_participants'];
$draft['version'] = (string)($version - 1);
$draft['reason'] = 'LOCALHOST rozpracovany <script> nesmi byt HTML.';
$draft['guest_reason'] = 'LOCALHOST dolozeny host mimo soupisku.';
$draft['confirm_action'] = '1';
$draft['actual_started_at'] = $trainingDate . 'T08:00';
$draft['actual_ended_at'] = $trainingDate . 'T09:00';
$draft['participant_q'] = (string)$host;
$response = $request($draft);
$searched = $form($response, 'actual-attendance');
$assert($response['status'] === 200, 'Search does not require valid write or current version');
foreach (['version', 'participant_ids', 'coach_ids', 'record_source', 'reason', 'guest_reason', 'confirm_action', 'actual_started_at', 'actual_ended_at'] as $key) {
    $left = $draft[$key];
    $right = $searched[$key];
    if (is_array($left)) {
        sort($left);
        sort($right);
    }
    $assert($left === $right, 'Search preserves draft ' . $key);
}
$assert(!str_contains($response['body'], '<script> nesmi byt HTML'), 'Draft text escaped');
$searched['action'] = 'find_participants';
$searched['participant_ids'][] = (string)$host;
$searched['participant_q'] = 'NOT FOUND';
$response = $request($searched);
$pinned = $form($response, 'actual-attendance');
$assert(count($pinned['participant_ids']) === 201 && in_array((string)$host, $pinned['participant_ids'], true), 'Searched guest remains selected outside results');
$pinned['action'] = 'find_participants';
$pinned['participant_ids'] = [];
$response = $request($pinned);
$empty = $form($response, 'actual-attendance');
$assert($empty['participant_ids'] === [], 'Intentionally empty selection is not restored from database');
$empty['action'] = 'find_participants';
$empty['participant_q'] = '';
$response = $request($empty);
$page1 = $form($response, 'actual-attendance');
$page1['action'] = 'find_participants_2';
$response = $request($page1);
$assert($response['status'] === 200 && str_contains($response['body'], 'Strana 2'), 'Native next page retains empty draft');
$invalidCsrf = $draft;
$invalidCsrf['csrf_token'] = 'invalid';
$assert($request($invalidCsrf)['status'] === 403, 'Search CSRF required');
$foreign = $draft;
$foreign['training_id'] = '102';
$assert($request($foreign)['status'] === 403, 'Search cannot inspect foreign training');
$excuse = $form($initial, 'personal-excuse');
$excuse['action'] = 'find_excuse_person';
$excuse['person_id'] = (string)$host;
$excuse['received_at'] = date('Y-m-d') . 'T06:00';
$excuse['source'] = 'paper';
$excuse['reason'] = 'LOCALHOST osobni omluva z papiroveho podkladu.';
$excuse['excuse_q'] = 'NOT FOUND';
$response = $request($excuse);
$foundExcuse = $form($response, 'personal-excuse');
foreach (['person_id', 'received_at', 'source', 'reason'] as $key) {
    $assert($foundExcuse[$key] === $excuse[$key], 'Excuse search preserves ' . $key);
}
$badWrite = $draft;
$badWrite['version'] = (string)$version;
$badWrite['action'] = 'close';
$badWrite['reason'] = '';
$response = $request($badWrite);
$failed = $form($response, 'actual-attendance');
$assert($response['status'] === 422 && $failed['record_source'] === 'paper' && $failed['version'] === (string)$version, 'Failed closure keeps paper source and version');
$assert($before === $checksum() && !in_array(null, $before, true), 'All search, paging and rejected writes leave domain data unchanged');
$readOnlyChecks = count($checks);

// Explicitly separate synthetic write UAT from the measured read-only before/after fixture.
$write = $form($initial, 'actual-attendance');
$write['participant_ids'][] = (string)$host;
$write['action'] = 'close';
$write['record_source'] = 'paper';
$write['actual_started_at'] = $trainingDate . 'T08:00';
$write['actual_ended_at'] = $trainingDate . 'T09:00';
$write['guest_reason'] = 'LOCALHOST prijaty host mimo ocekavanou soupisku.';
$write['reason'] = 'LOCALHOST skutecne potvrzeni synteticke dochazky.';
$write['confirm_action'] = '1';
$response = $request($write);
$assert($response['status'] === 303, 'Canonical HTML write redirects normally');
$row = $admin->query('SELECT r.* FROM training_closures c JOIN training_closure_revisions r ON r.id=c.current_revision_id WHERE c.training_id=101')->fetch(PDO::FETCH_ASSOC);
$snapshot = json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
$assert((int)$row['version'] === $version + 1 && $snapshot['record_source'] === 'paper', 'Canonical revision preserves paper provenance');
$assert(count($snapshot['participant_ids']) === 201 && in_array($host, $snapshot['participant_ids'], true), 'Canonical write includes searched off-page guest');
$afterWrite = $checksum();
$response = $request($write);
$stale = $form($response, 'actual-attendance');
$assert($response['status'] === 422 && $stale['version'] === (string)$version && $afterWrite === $checksum(), 'Stale write retains original version and cannot overwrite');
$report = ['complete' => true, 'synthetic_only' => true, 'read_only_checks' => $readOnlyChecks,
    'checks' => $checks, 'read_only_domain_checksums' => $before, 'synthetic_write_uat_after_measurement' => true,
    'saved_version' => (int)$row['version'], 'saved_participants' => count($snapshot['participant_ids']), 'record_source' => $snapshot['record_source']];
file_put_contents($output . '/http-functional-verification.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo count($checks) . " native HTTP form checks passed; separate synthetic revision saved.\n";
