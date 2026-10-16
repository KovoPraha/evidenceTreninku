<?php
declare(strict_types=1);

return [
    'id' => '20261016100000_training_rsvp_makeup_reason',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $column = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $column->execute(['training_rsvps', 'makeup_denial_reason']);
            if (!$column->fetchColumn()) $pdo->exec('ALTER TABLE training_rsvps ADD COLUMN makeup_denial_reason VARCHAR(32) NULL');
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(training_rsvps)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ((string)$column['name'] === 'makeup_denial_reason') return;
        }
        $pdo->exec('ALTER TABLE training_rsvps ADD COLUMN makeup_denial_reason TEXT NULL');
    },
    'verify' => static function (PDO $pdo): bool {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $column = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $column->execute(['training_rsvps', 'makeup_denial_reason']);
            return (bool)$column->fetchColumn();
        }
        foreach ($pdo->query('PRAGMA table_info(training_rsvps)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ((string)$column['name'] === 'makeup_denial_reason') return true;
        }
        return false;
    },
];
