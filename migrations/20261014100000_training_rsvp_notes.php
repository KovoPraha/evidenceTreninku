<?php
declare(strict_types=1);

return [
    'id' => '20261014100000_training_rsvp_notes',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $column = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
            );
            $column->execute(['training_rsvps', 'member_note']);
            if (!$column->fetchColumn()) {
                $pdo->exec("ALTER TABLE training_rsvps ADD COLUMN member_note VARCHAR(500) NOT NULL DEFAULT ''");
            }
            return;
        }
        $columns = $pdo->query('PRAGMA table_info(training_rsvps)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ((string)$column['name'] === 'member_note') return;
        }
        $pdo->exec("ALTER TABLE training_rsvps ADD COLUMN member_note TEXT NOT NULL DEFAULT ''");
    },
    'verify' => static function (PDO $pdo): bool {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $column = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
            );
            $column->execute(['training_rsvps', 'member_note']);
            return (bool)$column->fetchColumn();
        }
        foreach ($pdo->query('PRAGMA table_info(training_rsvps)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ((string)$column['name'] === 'member_note') return true;
        }
        return false;
    },
];
