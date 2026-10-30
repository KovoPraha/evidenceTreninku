<?php

declare(strict_types=1);

$definition = static function (PDO $pdo): array {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->query("SELECT COLUMN_NAME AS name,NON_UNIQUE AS non_unique FROM information_schema.STATISTICS "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kis_import_matches' "
            . "AND INDEX_NAME='idx_kis_match_run_row' ORDER BY SEQ_IN_INDEX");
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($pdo->query('PRAGMA index_list(kis_import_matches)')->fetchAll(PDO::FETCH_ASSOC) as $index) {
        if ($index['name'] === 'idx_kis_match_run_row') {
            return array_map(
                static fn (array $row): array => ['name' => $row['name'],
                'non_unique' => 1 - (int) $index['unique']],
                $pdo->query('PRAGMA index_info(idx_kis_match_run_row)')->fetchAll(PDO::FETCH_ASSOC)
            );
        }
    }
    return [];
};
$verify = static function (PDO $pdo) use ($definition): bool {
    $rows = $definition($pdo);
    return array_column($rows, 'name') === ['run_id', 'row_id']
        && array_map('intval', array_column($rows, 'non_unique')) === [1, 1];
};

return [
    'id' => '20261030170000_kis_preview_match_index',
    'up' => static function (PDO $pdo) use ($definition, $verify): void {
        if ($definition($pdo) === []) {
            $pdo->exec('CREATE INDEX idx_kis_match_run_row ON kis_import_matches(run_id,row_id)');
        }
        if (!$verify($pdo)) {
            throw new RuntimeException('Incompatible KIS preview match index.');
        }
    },
    'verify' => $verify,
];
