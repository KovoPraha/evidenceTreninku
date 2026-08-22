<?php
declare(strict_types=1);

$tableExists=static function(PDO$pdo,string$table):bool{if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return(bool)$s->fetchColumn();}$s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return(bool)$s->fetchColumn();};
return['id'=>'20260822170000_club_roster_structure_events','up'=>static function(PDO$pdo)use($tableExists):void{if($tableExists($pdo,'club_roster_structure_events'))return;$mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$pdo->exec($mysql?<<<'SQL'
CREATE TABLE club_roster_structure_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,subject_type VARCHAR(24) NOT NULL,subject_id BIGINT UNSIGNED NOT NULL,actor_trainer_id INT NOT NULL,action VARCHAR(48) NOT NULL,reason VARCHAR(1000) NOT NULL,before_json LONGTEXT NOT NULL,after_json LONGTEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_roster_structure_subject(subject_type,subject_id,id),CONSTRAINT fk_roster_structure_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL:<<<'SQL'
CREATE TABLE club_roster_structure_events (id INTEGER PRIMARY KEY AUTOINCREMENT,subject_type TEXT NOT NULL,subject_id INTEGER NOT NULL,actor_trainer_id INTEGER NOT NULL,action TEXT NOT NULL,reason TEXT NOT NULL,before_json TEXT NOT NULL,after_json TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
SQL);},'verify'=>static fn(PDO$pdo):bool=>$tableExists($pdo,'club_roster_structure_events')];
