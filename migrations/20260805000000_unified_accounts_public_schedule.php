<?php
declare(strict_types=1);

$unifiedColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((string)$row['name'] === $column) return true;
        }
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
    );
    $statement->execute([$table, $column]);
    return (bool)$statement->fetchColumn();
};

$unifiedIndexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=?");
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME=?'
    );
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260805000000_unified_accounts_public_schedule',
    'up' => static function (PDO $pdo) use ($unifiedColumnExists, $unifiedIndexExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$unifiedColumnExists($pdo, 'verejni_uzivatele', 'trener_id')) {
            $pdo->exec(
                'ALTER TABLE verejni_uzivatele ADD COLUMN trener_id '
                . ($mysql ? 'INT NULL' : 'INTEGER NULL')
            );
        }
        if (!$unifiedIndexExists($pdo, 'uq_verejni_uzivatele_trener')) {
            $pdo->exec(
                'CREATE UNIQUE INDEX uq_verejni_uzivatele_trener '
                . 'ON verejni_uzivatele (trener_id)'
            );
        }
        if (!$unifiedColumnExists($pdo, 'planovane_treninky', 'je_verejny')) {
            $pdo->exec(
                'ALTER TABLE planovane_treninky ADD COLUMN je_verejny '
                . ($mysql ? 'TINYINT(1)' : 'INTEGER') . ' NOT NULL DEFAULT 0'
            );
        }
        if (!$unifiedIndexExists($pdo, 'idx_planovane_treninky_verejne')) {
            $pdo->exec(
                'CREATE INDEX idx_planovane_treninky_verejne '
                . 'ON planovane_treninky (je_verejny, datum, stav)'
            );
        }
    },
    'verify' => static fn(PDO $pdo): bool =>
        $unifiedColumnExists($pdo, 'verejni_uzivatele', 'trener_id')
        && $unifiedIndexExists($pdo, 'uq_verejni_uzivatele_trener')
        && $unifiedColumnExists($pdo, 'planovane_treninky', 'je_verejny')
        && $unifiedIndexExists($pdo, 'idx_planovane_treninky_verejne'),
];
