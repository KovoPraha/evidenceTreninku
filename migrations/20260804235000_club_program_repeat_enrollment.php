<?php
declare(strict_types=1);

$clubProgramRepeatColumnExists = static function (PDO $pdo, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_program_enrollments' AND COLUMN_NAME=? LIMIT 1");
        $statement->execute([$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(club_program_enrollments)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

$clubProgramRepeatIndexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_program_enrollments' AND INDEX_NAME=? LIMIT 1");
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804235000_club_program_repeat_enrollment',
    'up' => static function (PDO $pdo) use ($clubProgramRepeatColumnExists, $clubProgramRepeatIndexExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            if (!$clubProgramRepeatColumnExists($pdo, 'active_token')) {
                $pdo->exec("ALTER TABLE club_program_enrollments ADD COLUMN active_token VARCHAR(16) NULL DEFAULT 'active' AFTER status");
            }
            $pdo->exec("UPDATE club_program_enrollments SET active_token=CASE WHEN status='active' THEN 'active' ELSE NULL END");
            if (!$clubProgramRepeatIndexExists($pdo, 'idx_club_program_enrollment_offer')) {
                $pdo->exec('ALTER TABLE club_program_enrollments ADD INDEX idx_club_program_enrollment_offer (offer_id)');
            }
            if ($clubProgramRepeatIndexExists($pdo, 'uq_club_program_enrollment_offer_person')) {
                $pdo->exec('ALTER TABLE club_program_enrollments DROP INDEX uq_club_program_enrollment_offer_person');
            }
            if (!$clubProgramRepeatIndexExists($pdo, 'uq_club_program_enrollment_active_person')) {
                $pdo->exec('ALTER TABLE club_program_enrollments ADD UNIQUE KEY uq_club_program_enrollment_active_person (offer_id,sportovec_id,active_token)');
            }
            return;
        }

        if (!$clubProgramRepeatColumnExists($pdo, 'active_token')) {
            if ($pdo->inTransaction()) throw new RuntimeException('SQLite rebuild of club_program_enrollments requires no active transaction.');
            $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
            if ($foreignKeys === 1) $pdo->exec('PRAGMA foreign_keys=OFF');
            try {
                $pdo->beginTransaction();
                $pdo->exec(<<<'SQL'
                    CREATE TABLE club_program_enrollments_next (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        offer_id INTEGER NOT NULL,
                        sportovec_id INTEGER NOT NULL,
                        account_id INTEGER NOT NULL,
                        source_order_item_id INTEGER NOT NULL UNIQUE,
                        status TEXT NOT NULL DEFAULT 'active',
                        active_token TEXT NULL DEFAULT 'active',
                        valid_from TEXT NOT NULL,
                        valid_to TEXT NOT NULL,
                        activated_at TEXT NOT NULL,
                        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        ended_at TEXT NULL,
                        ended_reason TEXT NULL,
                        ended_by_trainer_id INTEGER NULL,
                        FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                        FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                        FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                        FOREIGN KEY(source_order_item_id) REFERENCES shop_order_items(id) ON DELETE RESTRICT,
                        FOREIGN KEY(ended_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                    )
                    SQL);
                $pdo->exec(<<<'SQL'
                    INSERT INTO club_program_enrollments_next
                        (id,offer_id,sportovec_id,account_id,source_order_item_id,status,active_token,valid_from,valid_to,activated_at,created_at,updated_at,ended_at,ended_reason,ended_by_trainer_id)
                    SELECT id,offer_id,sportovec_id,account_id,source_order_item_id,status,
                        CASE WHEN status='active' THEN 'active' ELSE NULL END,
                        valid_from,valid_to,activated_at,created_at,updated_at,ended_at,ended_reason,ended_by_trainer_id
                    FROM club_program_enrollments
                    SQL);
                $pdo->exec('DROP TABLE club_program_enrollments');
                $pdo->exec('ALTER TABLE club_program_enrollments_next RENAME TO club_program_enrollments');
                $pdo->exec('CREATE UNIQUE INDEX uq_club_program_enrollment_active_person ON club_program_enrollments(offer_id,sportovec_id,active_token)');
                $pdo->exec('CREATE INDEX idx_club_program_enrollment_offer ON club_program_enrollments(offer_id)');
                $pdo->exec('CREATE INDEX idx_club_program_enrollment_person ON club_program_enrollments(sportovec_id,status,valid_to)');
                $pdo->exec('CREATE INDEX idx_club_program_enrollment_order_status ON club_program_enrollments(source_order_item_id,status)');
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            } finally {
                if ($foreignKeys === 1) $pdo->exec('PRAGMA foreign_keys=ON');
            }
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_club_program_enrollment_active_person ON club_program_enrollments(offer_id,sportovec_id,active_token)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_enrollment_offer ON club_program_enrollments(offer_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_enrollment_person ON club_program_enrollments(sportovec_id,status,valid_to)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_enrollment_order_status ON club_program_enrollments(source_order_item_id,status)');
    },
    'verify' => static function (PDO $pdo) use ($clubProgramRepeatColumnExists, $clubProgramRepeatIndexExists): bool {
        if (!$clubProgramRepeatColumnExists($pdo, 'active_token')
            || !$clubProgramRepeatIndexExists($pdo, 'uq_club_program_enrollment_active_person')
            || !$clubProgramRepeatIndexExists($pdo, 'idx_club_program_enrollment_offer')) return false;
        $invalid = $pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE (status='active' AND active_token<>'active') OR (status<>'active' AND active_token IS NOT NULL)");
        return (int)$invalid->fetchColumn() === 0;
    },
];
