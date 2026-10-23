<?php
declare(strict_types=1);

$exists = static function(PDO $pdo,string $table):bool {
    $s=$pdo->prepare($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
        ? 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        : "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $s->execute([$table]);return (bool)$s->fetchColumn();
};
return [
 'id'=>'20261023020000_training_closure',
 'up'=>static function(PDO $pdo)use($exists):void {
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text=$mysql?'LONGTEXT':'TEXT';$date=$mysql?'DATETIME':'TEXT';
    $tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $schemas=[
      'training_closures'=>"training_id INTEGER PRIMARY KEY,current_revision_id BIGINT NULL,version INTEGER NOT NULL DEFAULT 0,status VARCHAR(16) NOT NULL DEFAULT 'draft'",
      'training_closure_revisions'=>"id $id,training_id INTEGER NOT NULL,version INTEGER NOT NULL,status VARCHAR(16) NOT NULL,actual_date VARCHAR(10) NOT NULL,actual_started_at $date NOT NULL,actual_ended_at $date NOT NULL,duration_minutes INTEGER NOT NULL,snapshot_json $text NOT NULL,reason VARCHAR(1000) NOT NULL,closed_by INTEGER NOT NULL,closed_at $date NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(training_id,version)",
      'training_closure_reviews'=>"id $id,training_id INTEGER NOT NULL,revision_id BIGINT NOT NULL,reference_kind VARCHAR(32) NOT NULL,reference_id BIGINT NOT NULL,created_at $date NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(revision_id,reference_kind,reference_id)",
      'training_schedule_mutex'=>"id INTEGER PRIMARY KEY",
      'training_schedule_events'=>"id $id,plan_id INTEGER NOT NULL,replacement_plan_id INTEGER NULL,action VARCHAR(32) NOT NULL,reason VARCHAR(1000) NOT NULL,before_json $text NOT NULL,actor_id INTEGER NOT NULL,created_at $date NOT NULL DEFAULT CURRENT_TIMESTAMP",
      'training_personal_excuses'=>"id $id,plan_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,received_at $date NOT NULL,recorded_at $date NOT NULL DEFAULT CURRENT_TIMESTAMP,recorded_by INTEGER NOT NULL,source VARCHAR(32) NOT NULL,reason VARCHAR(1000) NOT NULL,entitlement_json $text NULL,UNIQUE(plan_id,sportovec_id)"
    ];
    foreach($schemas as $name=>$columns)if(!$exists($pdo,$name))$pdo->exec("CREATE TABLE $name($columns)$tail");
    if(!$pdo->query('SELECT id FROM training_schedule_mutex WHERE id=1')->fetchColumn())$pdo->exec('INSERT INTO training_schedule_mutex(id) VALUES(1)');
 },
 'verify'=>static function(PDO $pdo)use($exists):bool {
    foreach(['training_closures','training_closure_revisions','training_closure_reviews','training_schedule_mutex','training_schedule_events','training_personal_excuses'] as $table)if(!$exists($pdo,$table))return false;
    return true;
 },
];
