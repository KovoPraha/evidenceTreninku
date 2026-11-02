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
    'id' => '20261102171000_offer_makeup_scopes',
    'up' => static function (PDO $pdo) use ($nullableSeason): void {
        $nullableSeason($pdo, 'club_makeup_policies');
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS club_makeup_offer_policies (
                offer_id BIGINT UNSIGNED PRIMARY KEY,
                policy_id BIGINT UNSIGNED NOT NULL,
                CONSTRAINT fk_makeup_offer_policy_offer FOREIGN KEY (offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_makeup_offer_policy_policy FOREIGN KEY (policy_id) REFERENCES club_makeup_policies(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS club_makeup_offer_policies (
                offer_id INTEGER PRIMARY KEY REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                policy_id INTEGER NOT NULL REFERENCES club_makeup_policies(id) ON DELETE RESTRICT
            )
            SQL);
        // Each offer gets its own policy; preserve the existing per-plan cutoff snapshots.
        $legacy = $pdo->query('SELECT o.id AS offer_id,p.* FROM club_program_offers o JOIN club_makeup_policies p '
            . 'ON p.program_id=o.program_id AND p.team_id=o.team_id AND p.season_id=o.season_id '
            . 'WHERE NOT EXISTS(SELECT 1 FROM club_makeup_offer_policies m WHERE m.offer_id=o.id)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($legacy as $row) {
            $pdo->prepare('INSERT INTO club_makeup_policies(program_id,season_id,team_id,enabled,excuse_hours,updated_by_trainer_id) '
                . 'VALUES(?,NULL,?,?,?,?)')->execute([$row['program_id'],$row['team_id'],$row['enabled'],

    $row['excuse_hours'],$row['updated_by_trainer_id']]);
            $policyId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO club_makeup_offer_policies(offer_id,policy_id) VALUES(?,?)')->execute([$row['offer_id'],

    $policyId]);
            $pdo->prepare('INSERT INTO club_makeup_excuse_rules(plan_id,policy_id,excuse_hours) '
                . 'SELECT plan_id,?,excuse_hours FROM club_makeup_excuse_rules WHERE policy_id=?')->execute([$policyId,

    $row['id']]);
        }
        $exists = $mysql
            ? (bool) $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='club_makeup_offer_routes'")->fetchColumn()
            : (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='club_makeup_offer_routes'")->fetchColumn();
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS club_makeup_offer_routes (
                source_offer_id BIGINT UNSIGNED NOT NULL,
                target_offer_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY(source_offer_id,target_offer_id),
                FOREIGN KEY(source_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                FOREIGN KEY(target_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS club_makeup_offer_routes (
                source_offer_id INTEGER NOT NULL REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                target_offer_id INTEGER NOT NULL REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                PRIMARY KEY(source_offer_id,target_offer_id)
            )
            SQL);
        if (!$exists) {
            // Freeze only existing eligibility. New offers never inherit access from an old year.
            $pdo->exec('INSERT INTO club_makeup_offer_routes(source_offer_id,target_offer_id) '
                . 'SELECT a.id,b.id FROM club_program_offers a JOIN club_program_offers b ON b.season_id=a.season_id WHERE a.id<>b.id');
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT offer_id,policy_id FROM club_makeup_offer_policies WHERE 1=0');
        $pdo->query('SELECT source_offer_id,target_offer_id FROM club_makeup_offer_routes WHERE 1=0');
        foreach (['club_makeup_policies'] as $table) {
            $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            $columns = $pdo->query($mysql ? 'SHOW COLUMNS FROM ' . $table : 'PRAGMA table_info(' . $table .

     ')')->fetchAll(PDO::FETCH_ASSOC);
            $column = array_column($columns, null, $mysql ? 'Field' : 'name')['season_id'];
            if ($mysql ? $column['Null'] !== 'YES' : (bool) $column['notnull']) {
                return false;
            }
        }
        return true;
    },
];
