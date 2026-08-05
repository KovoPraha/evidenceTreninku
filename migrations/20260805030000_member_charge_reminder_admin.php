<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite' && preg_match('/^[a-z0-9_]+$/D', $table) === 1) {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((string)$row['name'] === $column) return true;
        }
    }
    return false;
};

return [
    'id' => '20260805030000_member_charge_reminder_admin',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists): void {
        if (!$tableExists($pdo, 'member_charge_reminder_events')) {
            throw new RuntimeException('Member charge reminder admin prerequisite is missing.');
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$columnExists($pdo, 'member_charge_reminder_events', 'actor_type')) {
            $pdo->exec($mysql
                ? "ALTER TABLE member_charge_reminder_events ADD COLUMN actor_type VARCHAR(24) NOT NULL DEFAULT 'system' AFTER account_id"
                : "ALTER TABLE member_charge_reminder_events ADD COLUMN actor_type TEXT NOT NULL DEFAULT 'system'");
        }
        if (!$columnExists($pdo, 'member_charge_reminder_events', 'actor_id')) {
            $pdo->exec($mysql
                ? 'ALTER TABLE member_charge_reminder_events ADD COLUMN actor_id BIGINT NULL AFTER actor_type'
                : 'ALTER TABLE member_charge_reminder_events ADD COLUMN actor_id INTEGER NULL');
        }
    },
    'verify' => static fn (PDO $pdo): bool => $columnExists($pdo, 'member_charge_reminder_events', 'actor_type')
        && $columnExists($pdo, 'member_charge_reminder_events', 'actor_id'),
];
