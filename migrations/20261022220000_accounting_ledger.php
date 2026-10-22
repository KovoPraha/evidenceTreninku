<?php
declare(strict_types=1);
return [
    'id'=>'20261022220000_accounting_ledger',
    'up'=>static function(PDO $pdo):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_ledger_mutex (id INT PRIMARY KEY,revision INT NOT NULL DEFAULT 0)'.$suffix);
        $pdo->exec($mysql?'INSERT IGNORE INTO accounting_ledger_mutex(id) VALUES(1)':'INSERT OR IGNORE INTO accounting_ledger_mutex(id) VALUES(1)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_accounts (
            id '.$id.',code VARCHAR(60) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,kind VARCHAR(20) NOT NULL,
            currency CHAR(3) NOT NULL,opened_on DATE NOT NULL,opening_minor BIGINT NOT NULL,
            evidence TEXT NOT NULL,reason TEXT NOT NULL,actor_trainer_id INT NOT NULL,
            recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_movements (
            id '.$id.',account_id INT NOT NULL,kind VARCHAR(24) NOT NULL,amount_minor BIGINT NOT NULL,
            currency CHAR(3) NOT NULL,effective_on DATE NOT NULL,recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            source_context VARCHAR(80) NOT NULL,source_reference VARCHAR(160) NOT NULL,request_key VARCHAR(100) NOT NULL UNIQUE,
            fingerprint CHAR(64) NOT NULL,reversal_of_id INT NULL,refund_decision_id INT NULL,transfer_key VARCHAR(100) NULL,
            case_type VARCHAR(40) NULL,case_id INT NULL,evidence TEXT NOT NULL,reason TEXT NOT NULL,actor_trainer_id INT NOT NULL,
            UNIQUE(source_context,source_reference),FOREIGN KEY(account_id) REFERENCES accounting_accounts(id),
            FOREIGN KEY(reversal_of_id) REFERENCES accounting_movements(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_movement_sources (
            id '.$id.',movement_id INT NOT NULL,source_type VARCHAR(40) NOT NULL,source_id INT NOT NULL,
            snapshot_json TEXT NOT NULL,recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(source_type,source_id),FOREIGN KEY(movement_id) REFERENCES accounting_movements(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_allocations (
            id '.$id.',movement_id INT NOT NULL,case_type VARCHAR(40) NOT NULL,case_id INT NOT NULL,
            amount_minor BIGINT NOT NULL,classification_code VARCHAR(60) NOT NULL,activity_code VARCHAR(60) NOT NULL,
            effective_on DATE NOT NULL,recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            request_key VARCHAR(100) NOT NULL UNIQUE,fingerprint CHAR(64) NOT NULL,reverses_id INT NULL,
            reason TEXT NOT NULL,actor_trainer_id INT NOT NULL,FOREIGN KEY(movement_id) REFERENCES accounting_movements(id),
            FOREIGN KEY(reverses_id) REFERENCES accounting_allocations(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_reconciliations (
            id '.$id.',account_id INT NOT NULL,as_of DATE NOT NULL,ledger_minor BIGINT NOT NULL,
            observed_minor BIGINT NOT NULL,difference_minor BIGINT NOT NULL,is_closed INT NOT NULL,
            evidence TEXT NOT NULL,reason TEXT NOT NULL,actor_trainer_id INT NOT NULL,
            recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(account_id) REFERENCES accounting_accounts(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_classifications (
            id '.$id.',movement_id INT NOT NULL,splits_json TEXT NOT NULL,fingerprint CHAR(64) NOT NULL,
            request_key VARCHAR(100) NOT NULL UNIQUE,replaces_id INT NULL,actor_trainer_id INT NOT NULL,
            reason TEXT NOT NULL,recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(movement_id) REFERENCES accounting_movements(id),FOREIGN KEY(replaces_id) REFERENCES accounting_classifications(id)
        )'.$suffix);
    },
    'verify'=>static function(PDO $pdo):bool {
        try {foreach(['accounting_ledger_mutex','accounting_accounts','accounting_movements','accounting_movement_sources','accounting_allocations','accounting_reconciliations','accounting_classifications'] as $table)$pdo->query('SELECT id FROM '.$table.' LIMIT 0');return true;}catch(PDOException){return false;}
    },
];
