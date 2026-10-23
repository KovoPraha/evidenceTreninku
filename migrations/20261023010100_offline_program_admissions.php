<?php
declare(strict_types=1);
$nullable=static function(PDO $pdo,string $table,string $column):void {
 if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$pdo->exec('ALTER TABLE '.$table.' MODIFY '.$column.' INT NULL');return;}
 $columns=$pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);$needed=false;foreach($columns as$c)if($c['name']===$column)$needed=(bool)$c['notnull'];if(!$needed)return;
 if($pdo->inTransaction())throw new RuntimeException('Offline admission SQLite rebuild requires standalone migration.');
 $q=$pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);$definition=(string)$q->fetchColumn();$q->closeCursor();$new=$table.'__offline_next';
 $definition=preg_replace('/^CREATE\s+TABLE\s+(?:`?'.$table.'`?|"'.$table.'")/i','CREATE TABLE '.$new,$definition,1,$count);if($count!==1)throw new RuntimeException('Cannot rewrite table.');
 $definition=preg_replace('/\b'.$column.'\s+(?:INTEGER|INT)\s+NOT\s+NULL\b/i',$column.' INTEGER NULL',$definition,1,$count);if($count!==1)throw new RuntimeException('Cannot make payer nullable.');
 $q=$pdo->prepare("SELECT sql FROM sqlite_master WHERE tbl_name=? AND type IN ('index','trigger') AND sql IS NOT NULL");$q->execute([$table]);$objects=$q->fetchAll(PDO::FETCH_COLUMN);$q->closeCursor();$names=implode(',',array_map(static fn($c)=>'"'.$c['name'].'"',$columns));$fk=(int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();$pdo->exec('PRAGMA foreign_keys=OFF');
 try{$pdo->beginTransaction();$pdo->exec($definition);$pdo->exec('INSERT INTO '.$new.'('.$names.') SELECT '.$names.' FROM '.$table);$pdo->exec('DROP TABLE '.$table);$pdo->exec('ALTER TABLE '.$new.' RENAME TO '.$table);foreach($objects as$sql)$pdo->exec($sql);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}finally{$pdo->exec('PRAGMA foreign_keys='.($fk?'ON':'OFF'));}
 if($pdo->query('PRAGMA foreign_key_check')->fetchColumn()!==false)throw new RuntimeException('Offline admission foreign key check failed.');
};
return [
 'id'=>'20261023010100_offline_program_admissions',
 'up'=>static function(PDO $pdo)use($nullable):void {
  $nullable($pdo,'club_program_admissions','payer_account_id');$nullable($pdo,'club_program_enrollments','account_id');
  foreach(['payer_name_snapshot'=>'VARCHAR(200) NULL','payer_relation_snapshot'=>'VARCHAR(20) NULL']as$name=>$type){$rows=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?$pdo->query('SHOW COLUMNS FROM club_program_admissions')->fetchAll(PDO::FETCH_ASSOC):$pdo->query('PRAGMA table_info(club_program_admissions)')->fetchAll(PDO::FETCH_ASSOC);$exists=false;foreach($rows as$r)if(($r['Field']??$r['name'])===$name)$exists=true;if(!$exists)$pdo->exec('ALTER TABLE club_program_admissions ADD COLUMN '.$name.' '.$type);}
 },
 'verify'=>static function(PDO $pdo):bool {try{$pdo->query('SELECT payer_name_snapshot,payer_relation_snapshot FROM club_program_admissions LIMIT 0');foreach(['club_program_admissions'=>'payer_account_id','club_program_enrollments'=>'account_id']as$table=>$column){$rows=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?$pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC):$pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);foreach($rows as$r)if(($r['Field']??$r['name'])===$column&&(($r['Null']??null)==='NO'||($r['notnull']??0)))return false;}return true;}catch(PDOException){return false;}}
];
