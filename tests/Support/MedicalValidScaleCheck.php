<?php

declare(strict_types=1);

// Local verification only. Never included by an application route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$mode = $argv[1] ?? '';
$output = realpath($argv[2] ?? '');
$level = $argv[3] ?? '';
$tag = $argv[4] ?? '';
if ($output === false || !in_array($mode, ['settings', 'verify', 'measure', 'diagnose'], true)
    || !in_array($level, ['baseline', 'medical50k', 'medical100k'], true)
    || !preg_match('/^(before|after)-[1-4]$/D', $tag)) {
    throw new RuntimeException('Owned output, fixed level, mode and variant tag required.');
}
$declared = json_decode((string) file_get_contents($output . '/runtime.json'), true, 512, JSON_THROW_ON_ERROR);
if (($declared['port'] ?? null) !== 33479 || ($declared['http_port'] ?? null) !== 18115
    || !preg_match('~^C:/xampp/tmp/kis-medical-planner-scale-20261107-[a-f0-9]{8}/data$~D', (string) ($declared['datadir'] ?? ''))) {
    throw new RuntimeException('Wrong isolated verification runtime.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
putenv('EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY');
putenv('EVIDENCE_MARIADB_SMOKE_PORT=33479');
putenv('EVIDENCE_PERFORMANCE_DATADIR=' . $runtime['datadir']);
require_once __DIR__ . '/SyntheticReadPagesProfile.php';
require_once __DIR__ . '/MedicalStateBaseline.php';
$credentials = json_decode((string) file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = p11Connection($runtime['schema'], true);
$pdo->exec('SET SESSION query_cache_type=OFF');
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$directory = $output . '/' . $level;
$seed = json_decode((string) file_get_contents($directory . '/seed.json'), true, 512, JSON_THROW_ON_ERROR);
$save = static function (string $path, array $value): void {
    if (file_exists($path)) {
        throw new RuntimeException('Refusing to overwrite prior evidence.');
    }
    file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
};
$checksums = static function () use ($pdo, $seed): array {
    $tables = array_keys($seed['checksums']);
    foreach ($tables as &$table) {
        $table = substr($table, (int) strpos($table, '.') + 1);
        if (!preg_match('/^[a-z_]+$/D', $table)) {
            throw new RuntimeException('Unexpected checksum table.');
        }
        $table = '`' . $table . '`';
    }
    unset($table);
    return $pdo->query('CHECKSUM TABLE ' . implode(',', $tables) . ' EXTENDED')->fetchAll(PDO::FETCH_KEY_PAIR);
};
if ($seed['today'] !== date('Y-m-d') || $seed['checksums'] !== $checksums()) {
    throw new RuntimeException('Seed changed or day boundary crossed.');
}
$grant = $admin->prepare('SELECT Select_priv,Insert_priv,Update_priv,Delete_priv,Create_priv,Drop_priv,Alter_priv,Grant_priv
    FROM mysql.db WHERE User=? AND Host=\'127.0.0.1\' AND Db=?');
$grant->execute([$runtime['web_user'], $runtime['schema']]);
$privileges = $grant->fetch(PDO::FETCH_ASSOC);
if (!$privileges || array_shift($privileges) !== 'Y' || array_unique(array_values($privileges)) !== ['N']) {
    throw new RuntimeException('Application database account must be SELECT only.');
}
if ($mode === 'settings') {
    $admin->exec('SET GLOBAL query_cache_type=OFF; SET GLOBAL query_cache_size=0');
    $settings = $pdo->query('SELECT VERSION() version,@@query_cache_type query_cache_type,@@query_cache_size query_cache_size,
        @@innodb_buffer_pool_size buffer_pool_size,@@tmp_table_size tmp_table_size,@@max_heap_table_size max_heap_table_size,
        @@tmpdir tmpdir,@@tx_read_only tx_read_only')->fetch(PDO::FETCH_ASSOC);
    $save($directory . '/settings.json', $settings);
    exit;
}
$cases = [
    'valid' => ['medical_state' => 'valid'],
    'valid-page2' => ['medical_state' => 'valid', 'medical_page' => 2],
    'valid-last' => ['medical_state' => 'valid', 'medical_page' => 999999],
    'valid-search' => ['medical_state' => 'valid', 'medical_q' => 'Synthetic'],
    'valid-person' => ['medical_state' => 'valid', 'person_id' => 10001],
    'valid-date' => ['medical_state' => 'valid', 'medical_from' => date('Y-m-d'), 'medical_to' => date('Y-m-d')],
    'valid-empty' => ['medical_state' => 'valid', 'medical_q' => 'NOTFOUND'],
    'expired' => ['medical_state' => 'expired'],
    'revoked' => ['medical_state' => 'revoked'],
    'pending' => ['medical_state' => 'pending'],
    'unfiltered' => [],
];
$report = ['complete' => false, 'level' => $level, 'tag' => $tag, 'php_version' => PHP_VERSION, 'server' => $identity['version'],
    'read_only' => true, 'query_cache_disabled' => true, 'cases' => []];
if ($mode === 'measure') {
    $samples = [];
    $hash = null;
    for ($i = 0; $i < 20; $i++) {
        $response = fullHttpRequest($runtime, $output, 'registrar', '/medical_clearance_admin.php?medical_state=valid');
        $current = hash('sha256', fullHttpNormalizedHtml($response));
        if ($response['status'] !== 200 || ($hash !== null && $hash !== $current)
            || !str_contains($response['body'], 'app-staff-topbar')
            || preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $response['body'])) {
            throw new RuntimeException('Unexpected or unstable complete HTTP response.');
        }
        $hash = $current;
        if ($i >= 5) {
            $samples[] = array_intersect_key($response, array_flip(['status', 'ttfb_ms', 'total_ms', 'bytes']));
        }
    }
    $report += ['warmups' => 5, 'samples' => $samples, 'html_sha256_lf_nonce' => $hash];
} elseif ($mode === 'verify') {
    p11Session('registrar', (int) $credentials['registrar']['id']);
    foreach ($cases as $id => $input) {
        $expected = Tests\Support\medicalStateBaselineList($pdo, $input);
        $pdo->profile = [];
        $pdo->profiling = true;
        $actual = medicalClearanceList($pdo, $input);
        $pdo->profiling = false;
        if ($expected !== $actual) {
            throw new RuntimeException('Full canonical domain differs: ' . $id);
        }
        $queries = $pdo->profile;
        $response = fullHttpRequest($runtime, $output, 'registrar', '/medical_clearance_admin.php?' . http_build_query($input));
        if ($response['status'] !== 200 || !str_contains($response['body'], 'app-staff-topbar')) {
            throw new RuntimeException('Complete authenticated HTTP missing.');
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body'], LIBXML_NONET);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $ids = [];
        foreach ($xpath->query('//table[thead/tr/th[normalize-space(.)="Osoba"] and thead/tr/th[normalize-space(.)="Účel"]]/tbody/tr//a[starts-with(@href,"?id=")]') as $link) {
            parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $ids[] = (int) ($query['id'] ?? 0);
        }
        if ($ids !== array_map('intval', array_column($expected['rows'], 'id'))) {
            throw new RuntimeException('Rendered rows differ from canonical domain.');
        }
        $report['cases'][] = ['id' => $id, 'input' => $input, 'parity' => true, 'dom_parity' => true,
            'domain_sha256' => hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
            'html_sha256_lf_nonce' => hash('sha256', fullHttpNormalizedHtml($response)),
            'total' => $actual['total'], 'page' => $actual['page'], 'pages' => $actual['pages'],
            'sql_count' => count($queries), 'fetched_rows' => array_sum(array_column($queries, 'fetched_rows'))];
    }
    foreach (['anonymous', 'coach', 'sports_lead'] as $role) {
        $response = fullHttpRequest($runtime, $output, $role, '/medical_clearance_admin.php?medical_state=valid');
        if ($response['status'] !== 403) {
            throw new RuntimeException('Role denial changed.');
        }
        $report['negative'][] = ['role' => $role, 'status' => 403];
    }
} else {
    $old = $admin->query('SELECT @@GLOBAL.long_query_time long_query_time,@@GLOBAL.slow_query_log slow_query_log,
        @@GLOBAL.general_log general_log,@@GLOBAL.log_output log_output,@@GLOBAL.min_examined_row_limit min_examined_row_limit')->fetch(PDO::FETCH_ASSOC);
    if ($old['general_log'] || $old['slow_query_log']) {
        throw new RuntimeException('Diagnostic logs must be initially off.');
    }
    $id = 'valid-' . $level . '-' . $tag;
    try {
        $admin->exec("SET GLOBAL log_output='TABLE'; SET GLOBAL long_query_time=0; SET GLOBAL min_examined_row_limit=0;
            TRUNCATE TABLE mysql.general_log; TRUNCATE TABLE mysql.slow_log;
            SET GLOBAL general_log=ON; SET GLOBAL slow_query_log=ON");
        try {
            $response = fullHttpRequest($runtime, $output, 'registrar', '/medical_clearance_admin.php?medical_state=valid', headers: ['X-P11-Measure: ' . $id]);
        } finally {
            $admin->exec('SET GLOBAL general_log=OFF; SET GLOBAL slow_query_log=OFF');
        }
        $metric = json_decode((string) file_get_contents($runtime['runtime'] . '/metrics/' . $id . '.json'), true, 512, JSON_THROW_ON_ERROR);
        $reference = json_decode((string) file_get_contents($directory . '/measure-' . $tag . '.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($metric['fatal'] || !$metric['db_bootstrap_included'] || !$metric['navigation_included']
            || $response['status'] !== 200 || hash('sha256', fullHttpNormalizedHtml($response)) !== $reference['html_sha256_lf_nonce']) {
            throw new RuntimeException('Diagnostic/plain HTML or bootstrap parity failed.');
        }
        $q = $admin->prepare("SELECT argument FROM mysql.general_log WHERE command_type IN ('Query','Execute') AND user_host LIKE ? ORDER BY event_time");
        $q->execute(['%' . $runtime['web_user'] . '%']);
        $queries = $q->fetchAll(PDO::FETCH_COLUMN);
        foreach ($queries as $sql) {
            if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql)) {
                throw new RuntimeException('Application attempted a domain write.');
            }
        }
        $q = $admin->prepare('SELECT sql_text,query_time,lock_time,rows_sent,rows_examined FROM mysql.slow_log WHERE user_host LIKE ? ORDER BY start_time');
        $q->execute(['%' . $runtime['web_user'] . '%']);
        $slow = $q->fetchAll(PDO::FETCH_ASSOC);
        if ($slow === []) {
            throw new RuntimeException('Actual slow-log statement counters missing.');
        }
        $report += ['php' => $metric, 'html_parity' => true, 'sql_count' => count($queries),
            'rows_examined' => array_sum(array_column($slow, 'rows_examined')),
            'rows_sent' => array_sum(array_column($slow, 'rows_sent')), 'server_statements' => $slow, 'write_commands' => []];
    } finally {
        $admin->exec('SET GLOBAL general_log=OFF; SET GLOBAL slow_query_log=OFF');
        $admin->exec('SET GLOBAL long_query_time=' . (float) $old['long_query_time'] . '; SET GLOBAL min_examined_row_limit=' . (int) $old['min_examined_row_limit']);
        $admin->exec('SET GLOBAL log_output=' . $admin->quote($old['log_output']));
    }
}
$report['checksums_unchanged'] = $seed['checksums'] === $checksums();
if (!$report['checksums_unchanged']) {
    throw new RuntimeException('Domain checksums changed.');
}
$report['complete'] = true;
$save($directory . '/' . $mode . '-' . $tag . '.json', $report);
echo $level . '/' . $tag . '/' . $mode . " PASS\n";
