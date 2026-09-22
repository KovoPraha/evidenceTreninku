<?php
declare(strict_types=1);

$tableExists=static function(PDO$pdo,string$table):bool{$sql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?':"SELECT 1 FROM sqlite_master WHERE type='table' AND name=?";$s=$pdo->prepare($sql);$s->execute([$table]);return(bool)$s->fetchColumn();};
return[
    'id'=>'20260922130000_roster_messages',
    'up'=>static function(PDO$pdo)use($tableExists):void{
        foreach(['club_teams','treneri','verejni_uzivatele']as$required)if(!$tableExists($pdo,$required))throw new RuntimeException('Required roster message table is missing: '.$required);$mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$tableExists($pdo,'roster_messages'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE roster_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                team_id BIGINT UNSIGNED NOT NULL,
                subject_plain VARCHAR(255) NOT NULL,
                body_plain TEXT NOT NULL,
                recipient_count INT UNSIGNED NOT NULL,
                preview_fingerprint CHAR(64) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'queued',
                created_by_trainer_id INT NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                KEY idx_roster_message_team(team_id,id),
                KEY idx_roster_message_status(status,id),
                CONSTRAINT fk_roster_message_team FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_roster_message_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE roster_messages(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,recipient_count INTEGER NOT NULL,preview_fingerprint TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'queued',created_by_trainer_id INTEGER NOT NULL,reason TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,completed_at TEXT NULL,FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
        if(!$tableExists($pdo,'roster_message_recipients'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE roster_message_recipients (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED NOT NULL,
                account_id INT NOT NULL,
                recipient_email VARCHAR(254) NOT NULL,
                recipient_name VARCHAR(255) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                claimed_at DATETIME NULL,
                claim_token CHAR(32) NULL,
                sent_at DATETIME NULL,
                last_error VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_roster_message_account(message_id,account_id),
                KEY idx_roster_message_delivery(status,available_at,id),
                CONSTRAINT fk_roster_recipient_message FOREIGN KEY(message_id) REFERENCES roster_messages(id) ON DELETE RESTRICT,
                CONSTRAINT fk_roster_recipient_account FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE roster_message_recipients(id INTEGER PRIMARY KEY AUTOINCREMENT,message_id INTEGER NOT NULL,account_id INTEGER NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(message_id,account_id),FOREIGN KEY(message_id) REFERENCES roster_messages(id) ON DELETE RESTRICT,FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT)
            SQL);
    },
    'verify'=>static function(PDO$pdo)use($tableExists):bool{return$tableExists($pdo,'roster_messages')&&$tableExists($pdo,'roster_message_recipients');},
];
