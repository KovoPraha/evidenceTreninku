<?php
declare(strict_types=1);

$waitlistColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(club_event_registrations)')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
    throw new RuntimeException('Unsupported database driver for club event waitlist migration.');
};

$waitlistIndexExists = static function (PDO $pdo, string $index): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1'
        );
        $statement->execute(['club_event_registrations', $index]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for club event waitlist migration.');
};

return [
    'id' => '20260803170000_club_event_waitlist',
    'up' => static function (PDO $pdo) use ($waitlistColumnExists, $waitlistIndexExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ([
            'waitlisted_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
            'promoted_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
        ] as $column => $definition) {
            if (!$waitlistColumnExists($pdo, 'club_event_registrations', $column)) {
                $pdo->exec(
                    'ALTER TABLE club_event_registrations ADD COLUMN ' . $column . ' ' . $definition
                );
            }
        }
        if (!$waitlistIndexExists($pdo, 'idx_club_registration_waitlist')) {
            $pdo->exec(
                'CREATE INDEX idx_club_registration_waitlist ON club_event_registrations '
                . '(event_id,status,waitlisted_at,id)'
            );
        }
    },
    'verify' => static function (PDO $pdo) use ($waitlistColumnExists, $waitlistIndexExists): bool {
        return $waitlistColumnExists($pdo, 'club_event_registrations', 'waitlisted_at')
            && $waitlistColumnExists($pdo, 'club_event_registrations', 'promoted_at')
            && $waitlistIndexExists($pdo, 'idx_club_registration_waitlist');
    },
];
