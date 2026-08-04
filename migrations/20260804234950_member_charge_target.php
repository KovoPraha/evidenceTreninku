<?php
declare(strict_types=1);

$tableExists = static function(PDO $pdo,string $table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return(bool)$s->fetchColumn();}
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return(bool)$s->fetchColumn();
};

return [
    'id'=>'20260804234950_member_charge_target',
    'up'=>static function(PDO $pdo)use($tableExists):void{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$tableExists($pdo,'club_member_charges')){
            $pdo->exec($mysql?<<<'SQL'
                CREATE TABLE club_member_charges (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sportovec_id INT NOT NULL,
                    payer_account_id INT NULL,
                    public_code VARCHAR(32) NOT NULL,
                    charge_type VARCHAR(32) NOT NULL,
                    title_snapshot VARCHAR(255) NOT NULL,
                    period_from DATE NULL,
                    period_to DATE NULL,
                    amount_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL,
                    due_on DATE NULL,
                    status VARCHAR(24) NOT NULL,
                    source_system VARCHAR(32) NOT NULL,
                    source_external_id VARCHAR(80) NULL,
                    source_import_run_id INT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_member_charge_public_code(public_code),
                    UNIQUE KEY uq_member_charge_source(source_system,source_external_id),
                    KEY idx_member_charge_beneficiary(sportovec_id,status,due_on,id),
                    KEY idx_member_charge_payer(payer_account_id,status,due_on,id),
                    CONSTRAINT fk_member_charge_sportovec FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_member_charge_payer FOREIGN KEY(payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_member_charge_import FOREIGN KEY(source_import_run_id) REFERENCES kis_import_runs(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL:<<<'SQL'
                CREATE TABLE club_member_charges (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sportovec_id INTEGER NOT NULL,
                    payer_account_id INTEGER NULL,
                    public_code TEXT NOT NULL UNIQUE,
                    charge_type TEXT NOT NULL,
                    title_snapshot TEXT NOT NULL,
                    period_from TEXT NULL,
                    period_to TEXT NULL,
                    amount_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    due_on TEXT NULL,
                    status TEXT NOT NULL,
                    source_system TEXT NOT NULL,
                    source_external_id TEXT NULL,
                    source_import_run_id INTEGER NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(source_system,source_external_id),
                    FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    FOREIGN KEY(payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    FOREIGN KEY(source_import_run_id) REFERENCES kis_import_runs(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if(!$tableExists($pdo,'club_member_charge_events')){
            $pdo->exec($mysql?<<<'SQL'
                CREATE TABLE club_member_charge_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    charge_id BIGINT UNSIGNED NOT NULL,
                    action VARCHAR(48) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id BIGINT NULL,
                    reason VARCHAR(1000) NOT NULL,
                    snapshot_json LONGTEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_member_charge_event(charge_id,id),
                    CONSTRAINT fk_member_charge_event_charge FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL:<<<'SQL'
                CREATE TABLE club_member_charge_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    charge_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NULL,
                    reason TEXT NOT NULL,
                    snapshot_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if(!$tableExists($pdo,'kis_import_payment_rows')){
            $pdo->exec($mysql?<<<'SQL'
                CREATE TABLE kis_import_payment_rows (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    run_id INT NOT NULL,
                    import_row_id INT NOT NULL,
                    source_ref VARCHAR(32) NOT NULL,
                    payment_external_id VARCHAR(80) NOT NULL,
                    status_snapshot VARCHAR(24) NOT NULL,
                    amount_minor BIGINT UNSIGNED NOT NULL,
                    outstanding_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL,
                    due_on DATE NULL,
                    paid_on DATE NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_kis_import_payment_external(run_id,payment_external_id),
                    UNIQUE KEY uq_kis_import_payment_ref(run_id,source_ref),
                    KEY idx_kis_import_payment_row(import_row_id,id),
                    CONSTRAINT fk_kis_import_payment_run FOREIGN KEY(run_id) REFERENCES kis_import_runs(id) ON DELETE CASCADE,
                    CONSTRAINT fk_kis_import_payment_person FOREIGN KEY(import_row_id) REFERENCES kis_import_rows(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL:<<<'SQL'
                CREATE TABLE kis_import_payment_rows (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id INTEGER NOT NULL,
                    import_row_id INTEGER NOT NULL,
                    source_ref TEXT NOT NULL,
                    payment_external_id TEXT NOT NULL,
                    status_snapshot TEXT NOT NULL,
                    amount_minor INTEGER NOT NULL,
                    outstanding_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    due_on TEXT NULL,
                    paid_on TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(run_id,payment_external_id),
                    UNIQUE(run_id,source_ref),
                    FOREIGN KEY(run_id) REFERENCES kis_import_runs(id) ON DELETE CASCADE,
                    FOREIGN KEY(import_row_id) REFERENCES kis_import_rows(id) ON DELETE CASCADE
                )
                SQL);
        }
    },
    'verify'=>static function(PDO $pdo)use($tableExists):bool{
        foreach(['club_member_charges','club_member_charge_events','kis_import_payment_rows']as$table){if(!$tableExists($pdo,$table))return false;}
        return true;
    },
];
