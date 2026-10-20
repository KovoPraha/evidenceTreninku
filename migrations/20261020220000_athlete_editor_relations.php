<?php
declare(strict_types=1);

$columns = static function (PDO $pdo, string $table): array {
    return $pdo->query($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'SHOW COLUMNS FROM ' . $table : 'PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
};
$nullable = static function (PDO $pdo, string $table, string $column, string $mysqlType) use ($columns): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $info = array_column($columns($pdo, $table), null, $mysql ? 'Field' : 'name')[$column] ?? null;
    if (!$info) throw new RuntimeException('Required editor column is missing.');
    if ($mysql) {
        if ($info['Null'] !== 'YES') $pdo->exec('ALTER TABLE ' . $table . ' MODIFY ' . $column . ' ' . $mysqlType . ' NULL');
        return;
    }
    if (!(int)$info['notnull']) return;
    if ($pdo->inTransaction()) throw new RuntimeException('Editor migration requires its own transaction.');
    $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    $sql = (string)$stmt->fetchColumn();
    $stmt->closeCursor();
    $sql = preg_replace('/(\b' . $column . '\s+\w+(?:\([^)]*\))?)\s+NOT\s+NULL/i', '$1', $sql, 1, $changed);
    if ($changed !== 1) throw new RuntimeException('Editor column definition cannot be rewritten.');
    $temporary = $table . '__editor_next';
    $sql = preg_replace('/^CREATE\s+TABLE\s+(?:"' . $table . '"|`' . $table . '`|' . $table . ')/i', 'CREATE TABLE ' . $temporary, $sql, 1, $renamed);
    if ($renamed !== 1) throw new RuntimeException('Editor table definition cannot be rewritten.');
    $names = implode(',', array_map(static fn($row) => '"' . $row['name'] . '"', $columns($pdo, $table)));
    $objects = $pdo->prepare("SELECT sql FROM sqlite_master WHERE tbl_name=? AND type IN ('index','trigger') AND sql IS NOT NULL");
    $objects->execute([$table]);
    $objects = $objects->fetchAll(PDO::FETCH_COLUMN);
    $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
    $pdo->exec('PRAGMA foreign_keys=OFF');
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $pdo->exec('INSERT INTO ' . $temporary . '(' . $names . ') SELECT ' . $names . ' FROM ' . $table);
        $pdo->exec('DROP TABLE ' . $table);
        $pdo->exec('ALTER TABLE ' . $temporary . ' RENAME TO ' . $table);
        foreach ($objects as $object) $pdo->exec($object);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    } finally { $pdo->exec('PRAGMA foreign_keys=' . ($foreignKeys ? 'ON' : 'OFF')); }
    if ($pdo->query('PRAGMA foreign_key_check')->fetchColumn() !== false) throw new RuntimeException('Editor foreign-key verification failed.');
};

return [
    'id' => '20261020220000_athlete_editor_relations',
    'up' => static function (PDO $pdo) use ($columns, $nullable): void {
        // Administrative RČ records belong to a person and need no fictitious registration request.
        $nullable($pdo, 'osoba_citlive_udaje', 'request_id', 'BIGINT UNSIGNED');
        $nullable($pdo, 'kis_person_contacts', 'batch_fingerprint', 'VARCHAR(64)');
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $names = array_column($columns($pdo, 'kis_person_links'), $mysql ? 'Field' : 'name');
        if (!in_array('ended_at', $names, true)) $pdo->exec('ALTER TABLE kis_person_links ADD COLUMN ended_at ' . ($mysql ? 'DATETIME' : 'TEXT') . ' NULL');
    },
    'verify' => static function (PDO $pdo) use ($columns): bool {
        try {
            $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            foreach (['osoba_citlive_udaje' => 'request_id', 'kis_person_contacts' => 'batch_fingerprint'] as $table => $column) {
                $row = array_column($columns($pdo, $table), null, $mysql ? 'Field' : 'name')[$column] ?? [];
                if (!$row || ($mysql ? $row['Null'] !== 'YES' : (int)$row['notnull'] !== 0)) return false;
            }
            return in_array('ended_at', array_column($columns($pdo, 'kis_person_links'), $mysql ? 'Field' : 'name'), true);
        } catch (PDOException) { return false; }
    },
];
