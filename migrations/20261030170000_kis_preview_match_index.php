<?php

declare(strict_types=1);

$definition = static function (PDO $pdo, string $name = 'idx_kis_match_run_row'): array {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare("SELECT COLUMN_NAME AS name,NON_UNIQUE AS non_unique FROM information_schema.STATISTICS "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kis_import_matches' "
            . "AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX");
        $statement->execute([$name]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($pdo->query('PRAGMA index_list(kis_import_matches)')->fetchAll(PDO::FETCH_ASSOC) as $index) {
        if ($index['name'] === $name) {
            return array_map(
                static fn (array $row): array => ['name' => $row['name'],
                'non_unique' => 1 - (int) $index['unique']],
                $pdo->query('PRAGMA index_info(' . $pdo->quote($name) . ')')->fetchAll(PDO::FETCH_ASSOC)
            );
        }
    }
    return [];
};
$compoundValid = static function (PDO $pdo) use ($definition): bool {
    $rows = $definition($pdo);
    return array_column($rows, 'name') === ['run_id', 'row_id']
        && array_map('intval', array_column($rows, 'non_unique')) === [1, 1];
};
$legacyRedundant = static function (PDO $pdo) use ($definition): bool {
    $rows = $definition($pdo, 'idx_run_id');
    return array_column($rows, 'name') === ['run_id']
        && array_map('intval', array_column($rows, 'non_unique')) === [1];
};
$verify = static fn (PDO $pdo): bool => $compoundValid($pdo) && !$legacyRedundant($pdo);

return [
    'id' => '20261030170000_kis_preview_match_index',
    'up' => static function (PDO $pdo) use ($definition, $compoundValid, $legacyRedundant): void {
        if ($definition($pdo) === []) {
            $pdo->exec('CREATE INDEX idx_kis_match_run_row ON kis_import_matches(run_id,row_id)');
        }
        if (!$compoundValid($pdo)) {
            throw new RuntimeException('Incompatible KIS preview match index.');
        }
        // The compound index covers the old run_id prefix. Retaining both lets
        // cold statistics choose the single-column index for every imported row.
        // Preserve any custom index with this name and a different definition.
        if ($legacyRedundant($pdo)) {
            $pdo->exec($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? 'DROP INDEX idx_run_id ON kis_import_matches' : 'DROP INDEX idx_run_id');
        }
    },
    'verify' => $verify,
];
