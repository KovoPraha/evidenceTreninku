<?php

declare(strict_types=1);

// Transitional schema: dated offers and explicit policy links no longer require a season.
$nullableSeason = static function (PDO $pdo, string $table): void {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        $column = array_column($rows, null, 'Field')['season_id'];
        if ($column['Null'] !== 'YES') {
            $pdo->exec('ALTER TABLE ' . $table . ' MODIFY season_id BIGINT UNSIGNED NULL');
        }
        return;
    }
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    if (!(array_column($columns, null, 'name')['season_id']['notnull'] ?? false)) {
        return;
    }
    if ($pdo->inTransaction()) {
        throw new RuntimeException('Offer scope migration requires a standalone transaction.');
    }
    $query = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
    $query->execute([$table]);
    $definition = (string) $query->fetchColumn();
    $query->closeCursor();
    $next = $table . '__scope_next';
    $definition = preg_replace(
        '/^CREATE\s+TABLE\s+' . $table . '/i',
        'CREATE TABLE ' . $next,
        $definition,
        1,
        $changed
    );
    if ($changed !== 1) {
        throw new RuntimeException('Cannot rewrite table ' . $table);
    }
    $definition = preg_replace('/(\bseason_id\s+\w+)\s+NOT\s+NULL/i', '$1', $definition, 1, $changed);
    if ($changed !== 1) {
        throw new RuntimeException('Cannot make offer scope optional.');
    }
    $query = $pdo->prepare("SELECT sql FROM sqlite_master WHERE tbl_name=? AND type IN ('index','trigger') AND sql IS NOT NULL");
    $query->execute([$table]);
    $objects = $query->fetchAll(PDO::FETCH_COLUMN);
    $query->closeCursor();
    $names = implode(',', array_map(static fn (array $row): string => '"' . $row['name'] . '"', $columns));
    $foreignKeys = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
    $pdo->exec('PRAGMA foreign_keys=OFF');
    try {
        $pdo->beginTransaction();
        $pdo->exec($definition);
        $pdo->exec('INSERT INTO ' . $next . '(' . $names . ') SELECT ' . $names . ' FROM ' . $table);
        $pdo->exec('DROP TABLE ' . $table);
        $pdo->exec('ALTER TABLE ' . $next . ' RENAME TO ' . $table);
        foreach ($objects as $sql) {
            $pdo->exec($sql);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    } finally {
        $pdo->exec('PRAGMA foreign_keys=' . $foreignKeys);
    }
    if ($pdo->query('PRAGMA foreign_key_check')->fetchColumn() !== false) {
        throw new RuntimeException('Foreign key mismatch after offer scope migration.');
    }
};

return [
    'id' => '20261102170500_dated_program_offers',
    'up' => static function (PDO $pdo) use ($nullableSeason): void {
        $nullableSeason($pdo, 'club_program_offers');
        $nullableSeason($pdo, 'club_program_schedule_slots');
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $rows = $pdo->query($mysql ? 'SHOW COLUMNS FROM club_program_schedule_slots' : 'PRAGMA table_info(club_program_schedule_slots)')->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($rows, $mysql ? 'Field' : 'name');
        foreach (['valid_from', 'valid_to'] as $column) {
            if (!in_array($column, $names, true)) {
                $pdo->exec('ALTER TABLE club_program_schedule_slots ADD COLUMN ' . $column . ' DATE NULL');
            }
        }
        $pdo->exec("UPDATE club_program_schedule_slots SET valid_from=(SELECT starts_on FROM club_seasons WHERE id=season_id), "
            . "valid_to=(SELECT ends_on FROM club_seasons WHERE id=season_id) WHERE valid_from IS NULL AND valid_to IS NULL "
            . "AND season_id IN (SELECT id FROM club_seasons WHERE code<>'KIS-STANDING')");
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT valid_from,valid_to FROM club_program_schedule_slots WHERE 1=0');
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach (['club_program_offers', 'club_program_schedule_slots'] as $table) {
            $rows = $pdo->query($mysql ? 'SHOW COLUMNS FROM ' . $table : 'PRAGMA table_info(' . $table .

     ')')->fetchAll(PDO::FETCH_ASSOC);
            $column = array_column($rows, null, $mysql ? 'Field' : 'name')['season_id'];
            if ($mysql ? $column['Null'] !== 'YES' : (bool) $column['notnull']) {
                return false;
            }
        }
        return true;
    },
];
