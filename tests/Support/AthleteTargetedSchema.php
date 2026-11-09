<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
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
$inventory = json_decode(file_get_contents(dirname(__DIR__, 2)
    . '/docs/verification/athlete-profile-targeted-read-20261109/production-schema.json'), true, 512, JSON_THROW_ON_ERROR);
$report = ['synthetic_only' => true, 'shared_before_after' => true, 'production_ddl' => false,
    'physical_type_differences' => 'Adversarial synthetic VARCHAR/signed/null columns retained; not a production replica.',
    'tables' => []];
foreach ($inventory['tables'] as $table => $metadata) {
    if (!in_array($table, ['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka'], true)) {
        throw new RuntimeException('Unexpected inventory target.');
    }
    $groups = [];
    foreach ($metadata['indexes'] as $index) {
        $groups[$index['name']]['columns'][] = $index['column'];
        $groups[$index['name']]['non_unique'] = (int) $index['non_unique'];
    }
    $existing = [];
    foreach ($admin->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $index) {
        $existing[$index['Key_name']]['columns'][] = $index['Column_name'];
        $existing[$index['Key_name']]['non_unique'] = (int) $index['Non_unique'];
    }
    $added = [];
    foreach ($groups as $name => $definition) {
        if (in_array($definition, $existing, true)) {
            continue;
        }
        if ($name === 'PRIMARY' || isset($existing[$name])) {
            throw new RuntimeException('Synthetic key conflicts with inventory.');
        }
        $columns = [];
        foreach ([$name, ...$definition['columns']] as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
                throw new RuntimeException('Unexpected inventory identifier.');
            }
        }
        foreach ($definition['columns'] as $column) {
            $columns[] = '`' . $column . '`';
        }
        $admin->exec('CREATE ' . ($definition['non_unique'] === 0 ? 'UNIQUE ' : '')
            . 'INDEX `' . $name . '` ON `' . $table . '` (' . implode(',', $columns) . ')');
        $added[] = $name;
    }
    $report['tables'][$table] = ['added_synthetic_indexes' => $added,
        'indexes' => $admin->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC),
        'columns' => $admin->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC)];
}
file_put_contents($output . '/fixture-schema.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Owned synthetic inventory indexes aligned before both variants.' . PHP_EOL;
