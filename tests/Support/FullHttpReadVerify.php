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
[$runtime] = fullHttpEnvironment($output);
$matrix = json_decode((string)file_get_contents($output . '/matrix.json'), true, 512, JSON_THROW_ON_ERROR);
$report = ['complete' => false, 'method' => 'outside timing: DOM checks on full authenticated HTTP', 'cases' => []];
foreach ($matrix as $case) {
    if ($case['status'] !== 200) {
        continue;
    }
    $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body'], LIBXML_NONET);
    libxml_clear_errors();
    $x = new DOMXPath($dom);
    $id = $case['id'];
    $data = ['navigation' => $x->query('//nav[contains(@class,"app-staff-topbar")]')->length];
    if (str_starts_with($id, 'planner-')) {
        $data['plans'] = $x->query('//*[contains(concat(" ",normalize-space(@class)," ")," plan-card ")]')->length;
        $expected = match ($id) {
            'planner-coach-all', 'planner-coach-group', 'planner-lead-all', 'planner-lead-root' => 14,
            'planner-coach-empty', 'planner-lead-own' => 0,
            default => 7,
        };
        if ($data['plans'] !== $expected) {
            throw new RuntimeException('Plan filter differs: ' . $id . ' / ' . $data['plans']);
        }
    } elseif (str_starts_with($id, 'closure-')) {
        $data['selected_people'] = $x->query('//select[@name="participant_ids[]"]/option[@selected]')->length;
        $data['available_people'] = $x->query('//select[@name="participant_ids[]"]/option')->length;
        $data['lesson_choices'] = $x->query('//*[@id="lesson-work"]//ul/li')->length;
        $data['record_source'] = $x->evaluate('string(//select[@name="record_source"]/option[@selected]/@value)');
        $expected = in_array($id, ['closure-coach-training', 'closure-coach-plan', 'closure-coach-search',
            'closure-coach-empty-search', 'closure-lead-training'], true) ? 200 : 0;
        if ($data['selected_people'] !== $expected || ($expected === 200 && $data['record_source'] !== 'paper')
            || ($id === 'closure-coach-search' && $data['lesson_choices'] !== 50)
            || ($id === 'closure-coach-empty-search' && $data['lesson_choices'] !== 0)) {
            throw new RuntimeException('Attendance filter, selected participants or provenance differs: ' . $id);
        }
    } else {
        $data['watch_rows'] = $x->query('//table[thead/tr/th[normalize-space(.)="Osoba"] and thead/tr/th[normalize-space(.)="Účel"]]/tbody/tr')->length;
        $data['person_choices'] = $x->query('//select[@name="sportovec_id"]/option[@value!=""]')->length;
        $expected = match ($id) {
            'medical-registrar-empty', 'medical-registrar-unknown-person', 'medical-registrar-pending' => 0,
            'medical-registrar-person' => 1,
            default => 25,
        };
        if ($data['watch_rows'] !== $expected || $data['person_choices'] > 26) {
            throw new RuntimeException('Medical filtering/pagination/picker bound differs: ' . $id . ' / ' . $data['watch_rows']);
        }
    }
    if ($response['status'] !== 200 || $data['navigation'] !== 1) {
        throw new RuntimeException('Full authenticated page missing: ' . $id);
    }
    $report['cases'][] = ['id' => $id, 'data' => $data, 'pass' => true];
}
$report['complete'] = true;
file_put_contents($output . '/http-dom-verification.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo count($report['cases']) . " full DOM checks passed.\n";
