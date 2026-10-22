<?php
declare(strict_types=1);
return [
 'id'=>'20261023090000_club_cutover',
 'up'=>static function(PDO $pdo):void{$mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
  $pdo->exec('CREATE TABLE IF NOT EXISTS club_cutover_runs(id '.$id.',title VARCHAR(160) NOT NULL,scope_text TEXT NOT NULL,release_reference VARCHAR(160) NOT NULL,source_environment VARCHAR(200) NOT NULL,target_environment VARCHAR(200) NOT NULL,owner_trainer_id INT NOT NULL,revision INT NOT NULL DEFAULT 0,created_by INT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS club_cutover_entries(id '.$id.',run_id INT NOT NULL,entry_key VARCHAR(100) NOT NULL,kind VARCHAR(30) NOT NULL,revision INT NOT NULL,data_json TEXT NOT NULL,data_sha256 CHAR(64) NOT NULL,actor_trainer_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(run_id,entry_key,revision),FOREIGN KEY(run_id) REFERENCES club_cutover_runs(id))'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS club_cutover_checkpoints(id '.$id.',run_id INT NOT NULL,registry_revision INT NOT NULL,label VARCHAR(160) NOT NULL,snapshot_json LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,actor_trainer_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(run_id) REFERENCES club_cutover_runs(id))'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS club_cutover_reviews(id '.$id.',run_id INT NOT NULL,checkpoint_id INT NOT NULL,registry_revision INT NOT NULL,gate VARCHAR(2) NOT NULL,result_json LONGTEXT NOT NULL,result_sha256 CHAR(64) NOT NULL,actor_trainer_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(run_id) REFERENCES club_cutover_runs(id),FOREIGN KEY(checkpoint_id) REFERENCES club_cutover_checkpoints(id))'.$tail);
 },
 'verify'=>static function(PDO $pdo):bool{try{foreach(['club_cutover_runs','club_cutover_entries','club_cutover_checkpoints','club_cutover_reviews']as$t)$pdo->query('SELECT id FROM '.$t.' LIMIT 0');return true;}catch(PDOException){return false;}}
];
