<?php

declare(strict_types=1);

/** Migration/diagnostic helper only. Web readers never invoke index creation. */
final class MedicalClearanceReadIndex
{
    public const NAME = 'idx_medical_clearance_read';
    public const COLUMNS = ['watch_id', 'id', 'state', 'issued_on', 'valid_until', 'replaces_id'];

    public static function definitions(PDO $pdo): array
    {
        $indexes = [];
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            foreach (self::rows($pdo, 'SHOW INDEX FROM medical_clearance_records') as $row) {
                $key = (string) $row['key_name'];
                $n = (int) $row['seq_in_index'];
                $indexes[$key]['unique'] = (int) $row['non_unique'] === 0;
                $indexes[$key]['type'] = strtoupper((string) $row['index_type']);
                $indexes[$key]['usable'] = strtoupper((string) ($row['ignored'] ?? 'NO')) !== 'YES'
                    && strtoupper((string) ($row['visible'] ?? 'YES')) !== 'NO';
                $indexes[$key]['columns'][$n] = strtolower((string) ($row['column_name'] ?? ''));
                $indexes[$key]['prefix_lengths'][$n] = isset($row['sub_part']) ? (int) $row['sub_part'] : null;
            }
        } else {
            foreach (self::rows($pdo, 'PRAGMA index_list(medical_clearance_records)') as $row) {
                $key = (string) $row['name'];
                $indexes[$key] = ['unique' => (bool) $row['unique'], 'type' => 'BTREE',
                    'usable' => !(bool) ($row['partial'] ?? false), 'columns' => [], 'prefix_lengths' => []];
                foreach (self::rows($pdo, 'PRAGMA index_info("' . str_replace('"', '""', $key) . '")') as $column) {
                    $n = (int) $column['seqno'];
                    $indexes[$key]['columns'][$n] = strtolower((string) ($column['name'] ?? ''));
                    $indexes[$key]['prefix_lengths'][$n] = null;
                }
            }
        }
        foreach ($indexes as &$index) {
            ksort($index['columns']);
            ksort($index['prefix_lengths']);
            $index['columns'] = array_values($index['columns']);
            $index['prefix_lengths'] = array_values($index['prefix_lengths']);
        }
        unset($index);
        return $indexes;
    }

    public static function coveringName(array $indexes): ?string
    {
        foreach ($indexes as $name => $index) {
            if ($index['usable'] && $index['type'] === 'BTREE'
                && array_slice($index['columns'], 0, count(self::COLUMNS)) === self::COLUMNS
                && !array_filter(array_slice($index['prefix_lengths'], 0, count(self::COLUMNS)))) {
                return (string) $name;
            }
        }
        return null;
    }

    public static function ensure(PDO $pdo): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Medical read indexes are changed only by CLI migrations.');
        }
        $indexes = self::definitions($pdo);
        if (self::coveringName($indexes) !== null) {
            return;
        }
        $name = self::NAME;
        $occupied = array_map('strtolower', array_keys($indexes));
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $occupied = array_map(
                static fn (array $r): string => strtolower((string) $r['name']),
                self::rows($pdo, "SELECT name FROM sqlite_master WHERE type='index'")
            );
        }
        for ($n = 2; in_array(strtolower($name), $occupied, true); $n++) {
            $name = self::NAME . '_' . $n;
        }
        $columns = implode(',', self::COLUMNS);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $engine = self::rows($pdo, "SELECT ENGINE engine FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medical_clearance_records'");
            if (strtoupper((string) ($engine[0]['engine'] ?? '')) !== 'INNODB') {
                throw new RuntimeException('Online medical read index requires InnoDB.');
            }
            $pdo->exec('ALTER TABLE medical_clearance_records ADD INDEX `' . $name . '` (' . $columns . '), ALGORITHM=INPLACE, LOCK=NONE');
        } else {
            $pdo->exec('CREATE INDEX "' . $name . '" ON medical_clearance_records (' . $columns . ')');
        }
        if (!self::verify($pdo)) {
            throw new RuntimeException('Medical read covering index verification failed.');
        }
    }

    public static function verify(PDO $pdo): bool
    {
        try {
            return self::coveringName(self::definitions($pdo)) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private static function rows(PDO $pdo, string $sql): array
    {
        $query = $pdo->query($sql);
        try {
            return array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $query->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $query->closeCursor();
        }
    }
}
