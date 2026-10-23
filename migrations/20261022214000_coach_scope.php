<?php
declare(strict_types=1);
return [
    'id'=>'20261022214000_coach_scope',
    'up'=>static function(PDO $pdo):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $pdo->exec('CREATE TABLE IF NOT EXISTS coach_scope_grants (
            id '.$id.',trainer_id INT NOT NULL,scope_kind VARCHAR(20) NOT NULL,scope_id INT NOT NULL,
            valid_from DATETIME NOT NULL,valid_to DATETIME NULL,reason TEXT NOT NULL,
            created_by_trainer_id INT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME NULL,revoked_by_trainer_id INT NULL,revoke_reason TEXT NULL,
            FOREIGN KEY(trainer_id) REFERENCES treneri(id),
            FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id),
            FOREIGN KEY(revoked_by_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS coach_scope_events (
            id '.$id.',grant_id INT NOT NULL,actor_trainer_id INT NOT NULL,action VARCHAR(20) NOT NULL,
            detail_json TEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(grant_id) REFERENCES coach_scope_grants(id),FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
    },
    'verify'=>static function(PDO $pdo):bool {
        try {foreach(['coach_scope_grants','coach_scope_events'] as $table)$pdo->query('SELECT id FROM '.$table.' LIMIT 0');return true;}
        catch(PDOException){return false;}
    },
];
