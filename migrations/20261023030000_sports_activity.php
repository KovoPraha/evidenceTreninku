<?php
declare(strict_types=1);
return [
    'id'=>'20261023030000_sports_activity',
    'up'=>static function(PDO $pdo):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $eventId=$mysql?'BIGINT UNSIGNED':'INTEGER';
        $pdo->exec('CREATE TABLE IF NOT EXISTS sports_event_participation (
            id '.$id.',event_id '.$eventId.' NOT NULL,sportovec_id INT NOT NULL,current_revision_id INT NULL,
            UNIQUE(event_id,sportovec_id),FOREIGN KEY(event_id) REFERENCES club_events(id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS sports_event_revisions (
            id '.$id.',participation_id INT NOT NULL,version INT NOT NULL,stage VARCHAR(30) NOT NULL,
            actual_date DATE NULL,result_status VARCHAR(20) NULL,snapshot_json TEXT NOT NULL,
            reason TEXT NOT NULL,source_reference VARCHAR(1000) NOT NULL,actor_trainer_id INT NOT NULL,
            request_key VARCHAR(100) NOT NULL UNIQUE,request_hash CHAR(64) NOT NULL,
            recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(participation_id,version),FOREIGN KEY(participation_id) REFERENCES sports_event_participation(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS sports_activity_statements (
            id '.$id.',sportovec_id INT NOT NULL,date_from DATE NOT NULL,date_to DATE NOT NULL,
            snapshot_json TEXT NOT NULL,snapshot_hash CHAR(64) NOT NULL,actor_trainer_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(sportovec_id,date_from,date_to,snapshot_hash),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
    },
    'verify'=>static function(PDO $pdo):bool {try{foreach(['sports_event_participation','sports_event_revisions','sports_activity_statements'] as $t)$pdo->query('SELECT id FROM '.$t.' LIMIT 0');return true;}catch(PDOException){return false;}},
];
