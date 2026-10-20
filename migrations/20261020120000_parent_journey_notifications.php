<?php
declare(strict_types=1);

$columnExists = static function (PDO $pdo): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return (bool)$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_event_notifications' AND COLUMN_NAME='dedupe_key'")->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(club_event_notifications)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['name'] === 'dedupe_key') return true;
    }
    return false;
};
$indexExists = static function (PDO $pdo): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return (bool)$pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_event_notifications' AND INDEX_NAME='uq_parent_journey_notification' AND NON_UNIQUE=0")->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA index_list(club_event_notifications)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['name'] === 'uq_parent_journey_notification' && (int)$row['unique'] === 1) return true;
    }
    return false;
};

return [
    'id' => '20261020120000_parent_journey_notifications',
    'up' => static function (PDO $pdo) use ($columnExists, $indexExists): void {
        if (!$columnExists($pdo)) {
            $pdo->exec('ALTER TABLE club_event_notifications ADD COLUMN dedupe_key VARCHAR(128) NULL');
        }
        if (!$indexExists($pdo)) {
            $pdo->exec('CREATE UNIQUE INDEX uq_parent_journey_notification ON club_event_notifications(dedupe_key)');
        }
    },
    'verify' => static fn(PDO $pdo): bool => $columnExists($pdo) && $indexExists($pdo),
];
