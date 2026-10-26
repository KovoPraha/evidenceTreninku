<?php
declare(strict_types=1);
return [
 'id'=>'20261025231000_kis_review_handoff',
 'up'=>static function(PDO $pdo):void {
  $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
  $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
  $text=$mysql?'LONGTEXT':'TEXT';$tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
  $pdo->exec("CREATE TABLE IF NOT EXISTS kis_review_plans(id $id, run_id INTEGER NOT NULL, fingerprint VARCHAR(64) NOT NULL, plan_json $text NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'preview', created_by INTEGER NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$tail");
  $pdo->exec("CREATE TABLE IF NOT EXISTS kis_review_events(id $id, plan_id BIGINT NOT NULL, action VARCHAR(20) NOT NULL, actor_id INTEGER NOT NULL, reason VARCHAR(500) NOT NULL, changes_json $text NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)$tail");
  $pdo->exec("CREATE TABLE IF NOT EXISTS kis_review_mutex(id INTEGER PRIMARY KEY, version INTEGER NOT NULL)$tail");
  if(!$pdo->query('SELECT id FROM kis_review_mutex WHERE id=1')->fetchColumn())$pdo->exec('INSERT INTO kis_review_mutex VALUES(1,0)');
 },
 'verify'=>static function(PDO $pdo):bool {foreach(['kis_review_plans','kis_review_events','kis_review_mutex']as$t)$pdo->query("SELECT * FROM $t WHERE 1=0");return true;},
];
