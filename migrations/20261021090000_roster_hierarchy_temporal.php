<?php
declare(strict_types=1);

/* A SQLite table rebuild is needed for the original inline UNIQUE constraint. */
$rebuildSqlite = static function (PDO $pdo, string $table, Closure $rewrite): void {
    if ($pdo->inTransaction()) throw new RuntimeException('Temporal roster migration needs a standalone SQLite migration.');
    $statement = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    $definition = (string)$statement->fetchColumn();
    $statement->closeCursor();
    if ($definition === '') throw new RuntimeException('Missing roster table: '.$table);
    $new = $table.'__temporal_next';
    $definition = preg_replace('/^CREATE\s+TABLE\s+(?:`?'.$table.'`?|"'.$table.'")/i', 'CREATE TABLE '.$new, $definition, 1, $count);
    if ($count !== 1) throw new RuntimeException('Cannot rewrite SQLite roster table: '.$table);
    $definition = $rewrite($definition);
    $columns = $pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
    $names = implode(',', array_map(static fn(array $column): string => '"'.$column['name'].'"', $columns));
    $objects = $pdo->prepare("SELECT sql FROM sqlite_master WHERE tbl_name=? AND type IN ('index','trigger') AND sql IS NOT NULL");
    $objects->execute([$table]);
    $objectSql = $objects->fetchAll(PDO::FETCH_COLUMN);
    $objects->closeCursor();
    $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
    $pdo->exec('PRAGMA foreign_keys=OFF');
    try {
        $pdo->beginTransaction();
        $pdo->exec($definition);
        $pdo->exec('INSERT INTO '.$new.'('.$names.') SELECT '.$names.' FROM '.$table);
        $pdo->exec('DROP TABLE '.$table);
        $pdo->exec('ALTER TABLE '.$new.' RENAME TO '.$table);
        foreach ($objectSql as $object) $pdo->exec((string)$object);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    } finally {
        $pdo->exec('PRAGMA foreign_keys='.($foreignKeys ? 'ON' : 'OFF'));
    }
    if ($pdo->query('PRAGMA foreign_key_check')->fetchColumn() !== false) throw new RuntimeException('Roster foreign-key check failed after rebuild.');
};

$hasColumn = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $rows = $pdo->query($driver === 'mysql' ? 'SHOW COLUMNS FROM '.$table : 'PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
    return in_array($column, array_column($rows, $driver === 'mysql' ? 'Field' : 'name'), true);
};
$hasIndex = static function (PDO $pdo, string $table, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
        $stmt->execute([$table,$index]);
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=? LIMIT 1");
        $stmt->execute([$table,$index]);
    }
    return (bool)$stmt->fetchColumn();
};
$hasTable = static function (PDO $pdo, string $table): bool {
    $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $stmt = $pdo->prepare($mysql
        ? 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        : "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
};

return [
    'id' => '20261021090000_roster_hierarchy_temporal',
    'up' => static function (PDO $pdo) use ($rebuildSqlite, $hasColumn, $hasIndex, $hasTable): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $seasonColumns = $pdo->query('SHOW COLUMNS FROM club_teams')->fetchAll(PDO::FETCH_ASSOC);
            $seasonColumn = array_column($seasonColumns,null,'Field')['season_id'] ?? null;
            if (($seasonColumn['Null'] ?? null) !== 'YES') $pdo->exec('ALTER TABLE club_teams MODIFY season_id BIGINT UNSIGNED NULL');
            if ($hasIndex($pdo,'club_roster_members','uq_club_roster_team_person')) $pdo->exec('ALTER TABLE club_roster_members DROP INDEX uq_club_roster_team_person');
        } else {
            $seasonColumns = $pdo->query('PRAGMA table_info(club_teams)')->fetchAll(PDO::FETCH_ASSOC);
            $seasonColumn = array_column($seasonColumns,null,'name')['season_id'] ?? null;
            if (($seasonColumn['notnull'] ?? 1) !== 0) {
                $rebuildSqlite($pdo, 'club_teams', static function (string $sql): string {
                    $sql = preg_replace('/(\bseason_id\s+\w+(?:\([^)]*\))?)\s+NOT\s+NULL/i', '$1', $sql, 1, $changed);
                    if ($changed !== 1) throw new RuntimeException('Cannot make roster season optional.');
                    return $sql;
                });
            }
            $definition = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='club_roster_members'")->fetchColumn();
            if (preg_match('/UNIQUE\s*\(\s*team_id\s*,\s*sportovec_id\s*\)/i',$definition)) {
                $rebuildSqlite($pdo, 'club_roster_members', static function (string $sql): string {
                    $sql = preg_replace('/,\s*UNIQUE\s*\(\s*team_id\s*,\s*sportovec_id\s*\)/i', '', $sql, 1, $changed);
                    if ($changed !== 1) throw new RuntimeException('Cannot remove old roster uniqueness.');
                    return $sql;
                });
            }
        }
        if (!$hasColumn($pdo, 'club_teams', 'team_kind')) $pdo->exec("ALTER TABLE club_teams ADD COLUMN team_kind VARCHAR(16) NOT NULL DEFAULT 'roster'");
        if (!$hasColumn($pdo, 'club_teams', 'standing_code')) $pdo->exec('ALTER TABLE club_teams ADD COLUMN standing_code VARCHAR(48) NULL');
        if (!$hasIndex($pdo,'club_teams','uq_club_team_standing_code')) $pdo->exec('CREATE UNIQUE INDEX uq_club_team_standing_code ON club_teams(standing_code)');
        if (!$hasColumn($pdo, 'club_roster_members', 'source_ref')) $pdo->exec('ALTER TABLE club_roster_members ADD COLUMN source_ref VARCHAR(120) NULL');
        if (!$hasIndex($pdo,'club_roster_members','idx_club_roster_member_period')) $pdo->exec('CREATE INDEX idx_club_roster_member_period ON club_roster_members(team_id,sportovec_id,valid_from,valid_to)');
        if (!$hasIndex($pdo,'club_roster_members','idx_club_roster_source_ref')) $pdo->exec('CREATE INDEX idx_club_roster_source_ref ON club_roster_members(source,source_ref)');
        if (!$hasIndex($pdo,'club_roster_members','uq_club_roster_source_grant')) $pdo->exec('CREATE UNIQUE INDEX uq_club_roster_source_grant ON club_roster_members(source,source_ref,team_id,sportovec_id)');
        if (!$hasTable($pdo,'club_team_edges')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_team_edges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 parent_team_id BIGINT UNSIGNED NOT NULL,
 child_team_id BIGINT UNSIGNED NOT NULL,
 valid_from DATE NOT NULL,
 valid_to DATE NULL,
 actor_trainer_id INT NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_club_team_edge_parent_period(parent_team_id,valid_from,valid_to),
 KEY idx_club_team_edge_child_period(child_team_id,valid_from,valid_to),
 UNIQUE KEY uq_club_team_edge_start(parent_team_id,child_team_id,valid_from),
 CONSTRAINT fk_club_team_edge_parent FOREIGN KEY(parent_team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
 CONSTRAINT fk_club_team_edge_child FOREIGN KEY(child_team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
 CONSTRAINT fk_club_team_edge_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_team_edges (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 parent_team_id INTEGER NOT NULL REFERENCES club_teams(id) ON DELETE RESTRICT,
 child_team_id INTEGER NOT NULL REFERENCES club_teams(id) ON DELETE RESTRICT,
 valid_from TEXT NOT NULL,
 valid_to TEXT NULL,
 actor_trainer_id INTEGER NOT NULL REFERENCES treneri(id) ON DELETE RESTRICT,
 reason TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        if (!$mysql) {
            if (!$hasIndex($pdo,'club_team_edges','idx_club_team_edge_parent_period')) $pdo->exec('CREATE INDEX idx_club_team_edge_parent_period ON club_team_edges(parent_team_id,valid_from,valid_to)');
            if (!$hasIndex($pdo,'club_team_edges','idx_club_team_edge_child_period')) $pdo->exec('CREATE INDEX idx_club_team_edge_child_period ON club_team_edges(child_team_id,valid_from,valid_to)');
            if (!$hasIndex($pdo,'club_team_edges','uq_club_team_edge_start')) $pdo->exec('CREATE UNIQUE INDEX uq_club_team_edge_start ON club_team_edges(parent_team_id,child_team_id,valid_from)');
        }
    },
    'verify' => static function (PDO $pdo) use ($hasColumn): bool {
        try {
            if (!$hasColumn($pdo, 'club_teams', 'team_kind') || !$hasColumn($pdo, 'club_teams', 'standing_code') || !$hasColumn($pdo, 'club_roster_members', 'source_ref')) return false;
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $statement = $pdo->prepare($driver === 'mysql'
                ? 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
                : "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
            $statement->execute(['club_team_edges']);
            return (bool)$statement->fetchColumn();
        } catch (Throwable) { return false; }
    },
];
