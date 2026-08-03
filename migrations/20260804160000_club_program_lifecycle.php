<?php
declare(strict_types=1);

$clubProgramLifecycleColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) if ((string)$row['name'] === $column) return true;
    return false;
};

return [
    'id' => '20260804160000_club_program_lifecycle',
    'up' => static function (PDO $pdo) use ($clubProgramLifecycleColumnExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach ([
            'ended_at' => $mysql ? 'DATETIME NULL' : 'TEXT NULL',
            'ended_reason' => $mysql ? 'VARCHAR(1000) NULL' : 'TEXT NULL',
            'ended_by_trainer_id' => $mysql ? 'INT NULL' : 'INTEGER NULL REFERENCES treneri(id) ON DELETE RESTRICT',
        ] as $column => $definition) {
            if (!$clubProgramLifecycleColumnExists($pdo,'club_program_enrollments',$column)) {
                $pdo->exec('ALTER TABLE club_program_enrollments ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        if ($mysql) {
            $index = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_program_enrollments' AND INDEX_NAME='idx_club_program_enrollment_order_status' LIMIT 1")->fetchColumn();
            if (!$index) $pdo->exec('ALTER TABLE club_program_enrollments ADD INDEX idx_club_program_enrollment_order_status (source_order_item_id,status)');
            $fk = $pdo->query("SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='club_program_enrollments' AND CONSTRAINT_NAME='fk_club_program_enrollment_ender' LIMIT 1")->fetchColumn();
            if (!$fk) $pdo->exec('ALTER TABLE club_program_enrollments ADD CONSTRAINT fk_club_program_enrollment_ender FOREIGN KEY (ended_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT');
        } else {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_enrollment_order_status ON club_program_enrollments(source_order_item_id,status)');
        }
    },
    'verify' => static function (PDO $pdo) use ($clubProgramLifecycleColumnExists): bool {
        foreach (['ended_at','ended_reason','ended_by_trainer_id'] as $column) if (!$clubProgramLifecycleColumnExists($pdo,'club_program_enrollments',$column)) return false;
        return true;
    },
];
