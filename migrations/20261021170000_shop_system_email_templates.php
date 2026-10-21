<?php
declare(strict_types=1);

return [
    'id'=>'20261021170000_shop_system_email_templates',
    'up'=>static function(PDO $pdo):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_system_email_templates (
            template_key VARCHAR(64) PRIMARY KEY,
            version INT NOT NULL DEFAULT 0,
            subject_plain VARCHAR(180) NULL,
            body_plain TEXT NULL
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_system_email_versions (
            template_key VARCHAR(64) NOT NULL,
            version INT NOT NULL,
            subject_plain VARCHAR(180) NOT NULL,
            body_plain TEXT NOT NULL,
            created_by_trainer_id INT NOT NULL,
            reason VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(template_key,version),
            FOREIGN KEY(template_key) REFERENCES shop_system_email_templates(template_key),
            FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
        // Frozen event inventory; later changes require a new migration.
        foreach(['shop_payment_received','shop_order_ready','shop_ppl_dispatched','shop_packeta_dispatched','shop_guest_order_placed','shop_quick_program_order_placed'] as $key) {
            $q=$pdo->prepare('SELECT 1 FROM shop_system_email_templates WHERE template_key=?');$q->execute([$key]);
            if (!$q->fetchColumn()) $pdo->prepare('INSERT INTO shop_system_email_templates(template_key,version) VALUES(?,0)')->execute([$key]);
        }
        foreach(['system_template_key'=>'VARCHAR(64) NULL','system_template_version'=>'INT NULL'] as $column=>$type) {
            if ($mysql) {$q=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_event_notifications' AND COLUMN_NAME=?");$q->execute([$column]);$exists=(bool)$q->fetchColumn();}
            else $exists=in_array($column,array_column($pdo->query('PRAGMA table_info(club_event_notifications)')->fetchAll(PDO::FETCH_ASSOC),'name'),true);
            if (!$exists) $pdo->exec('ALTER TABLE club_event_notifications ADD COLUMN '.$column.' '.$type);
        }
    },
    'verify'=>static function(PDO $pdo):bool {
        try {
            $pdo->query('SELECT template_key,version,subject_plain,body_plain FROM shop_system_email_templates LIMIT 0');
            $pdo->query('SELECT template_key,version,subject_plain,body_plain,created_by_trainer_id,reason,created_at FROM shop_system_email_versions LIMIT 0');
            $pdo->query('SELECT system_template_key,system_template_version FROM club_event_notifications LIMIT 0');
            return (int)$pdo->query('SELECT COUNT(*) FROM shop_system_email_templates WHERE template_key IN (\'shop_payment_received\',\'shop_order_ready\',\'shop_ppl_dispatched\',\'shop_packeta_dispatched\',\'shop_guest_order_placed\',\'shop_quick_program_order_placed\')')->fetchColumn()===6;
        } catch(PDOException){return false;}
    },
];
