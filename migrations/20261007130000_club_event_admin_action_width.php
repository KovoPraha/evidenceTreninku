<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    }
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20261007130000_club_event_admin_action_width',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        if (!$tableExists($pdo, 'club_event_admin_events')) {
            throw new RuntimeException('Required club event audit table is missing: club_event_admin_events');
        }
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec('ALTER TABLE club_event_admin_events MODIFY action VARCHAR(64) NOT NULL');
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        if (!$tableExists($pdo, 'club_event_admin_events')) return false;
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return true;
        $statement = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_event_admin_events' AND COLUMN_NAME='action'");
        return (int)$statement->fetchColumn() >= 64;
    },
];
