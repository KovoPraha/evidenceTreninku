<?php
declare(strict_types=1);
return [
    'id'=>'20261022222000_accounting_adjustments',
    'up'=>static function(PDO $pdo):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_refund_decisions (
            id '.$id.', original_movement_id INT NOT NULL,
            original_document_id INT NULL, correction_document_id INT NULL,
            amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL,
            decided_on DATE NOT NULL, reason VARCHAR(1000) NOT NULL, actor_trainer_id INT NOT NULL,
            request_key VARCHAR(100) NOT NULL UNIQUE, input_hash VARCHAR(64) NOT NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(original_movement_id) REFERENCES accounting_movements(id),
            FOREIGN KEY(original_document_id) REFERENCES accounting_documents(id),
            FOREIGN KEY(correction_document_id) REFERENCES accounting_documents(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_refund_payments (
            id '.$id.', decision_id INT NOT NULL, movement_id INT NOT NULL UNIQUE,
            amount_minor BIGINT NOT NULL, effective_on DATE NOT NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(decision_id) REFERENCES accounting_refund_decisions(id),
            FOREIGN KEY(movement_id) REFERENCES accounting_movements(id)
        )'.$tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_adjustment_events (
            id '.$id.', decision_id INT NULL, document_id INT NULL,
            event_type VARCHAR(40) NOT NULL, line_key VARCHAR(100) NULL,
            quantity INT NOT NULL DEFAULT 0, reason VARCHAR(1000) NOT NULL,
            actor_trainer_id INT NOT NULL, request_key VARCHAR(100) NOT NULL UNIQUE,
            input_hash VARCHAR(64) NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(decision_id) REFERENCES accounting_refund_decisions(id),
            FOREIGN KEY(document_id) REFERENCES accounting_documents(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$tail);
    },
    'verify'=>static function(PDO $pdo):bool {
        try {
            $pdo->query('SELECT id,original_movement_id,original_document_id,correction_document_id,amount_minor,currency,decided_on,reason,actor_trainer_id,request_key,input_hash,recorded_at FROM accounting_refund_decisions LIMIT 0');
            $pdo->query('SELECT id,decision_id,movement_id,amount_minor,effective_on,recorded_at FROM accounting_refund_payments LIMIT 0');
            $pdo->query('SELECT id,decision_id,document_id,event_type,line_key,quantity,reason,actor_trainer_id,request_key,input_hash,recorded_at FROM accounting_adjustment_events LIMIT 0');
            return true;
        } catch(PDOException) {return false;}
    },
];
