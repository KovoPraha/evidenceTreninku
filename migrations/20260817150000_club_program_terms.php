<?php
declare(strict_types=1);

$programTermsColumnExists=static function(PDO $pdo,string $table,string $column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table,$column]);return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};
$programTermsIndexExists=static function(PDO $pdo,string $index):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME=?');
        $statement->execute([$index]);return(bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=?");$statement->execute([$index]);return(bool)$statement->fetchColumn();
};

return[
    'id'=>'20260817150000_club_program_terms',
    'up'=>static function(PDO $pdo)use($programTermsColumnExists,$programTermsIndexExists):void{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        foreach([
            'status'=>$mysql?"VARCHAR(16) NOT NULL DEFAULT 'active'":"TEXT NOT NULL DEFAULT 'active'",
            'archived_at'=>$mysql?'DATETIME NULL':'TEXT NULL',
            'archived_by_trainer_id'=>$mysql?'INT NULL':'INTEGER NULL REFERENCES treneri(id) ON DELETE RESTRICT',
        ]as$column=>$definition)if(!$programTermsColumnExists($pdo,'club_event_term_versions',$column))$pdo->exec('ALTER TABLE club_event_term_versions ADD COLUMN '.$column.' '.$definition);
        foreach([
            'shop_order_items'=>[
                'program_terms_snapshot_json'=>$mysql?'LONGTEXT NULL':'TEXT NULL',
                'program_terms_accepted_at'=>$mysql?'DATETIME NULL':'TEXT NULL',
                'program_terms_accepted_by_account_id'=>$mysql?'INT NULL':'INTEGER NULL REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT',
            ],
            'club_program_enrollments'=>[
                'terms_snapshot_json'=>$mysql?'LONGTEXT NULL':'TEXT NULL',
                'terms_accepted_at'=>$mysql?'DATETIME NULL':'TEXT NULL',
                'terms_accepted_by_account_id'=>$mysql?'INT NULL':'INTEGER NULL REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT',
            ],
        ]as$table=>$columns)foreach($columns as$column=>$definition)if(!$programTermsColumnExists($pdo,$table,$column))$pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$definition);
        if(!$programTermsIndexExists($pdo,'idx_terms_scope_current'))$pdo->exec('CREATE INDEX idx_terms_scope_current ON club_event_term_versions(scope_type,scope_key,consent_purpose,status,id)');
        if($mysql){
            $foreignKeys=[
                ['club_event_term_versions','fk_terms_archiver','archived_by_trainer_id','treneri'],
                ['shop_order_items','fk_shop_order_item_terms_account','program_terms_accepted_by_account_id','verejni_uzivatele'],
                ['club_program_enrollments','fk_program_enrollment_terms_account','terms_accepted_by_account_id','verejni_uzivatele'],
            ];
            foreach($foreignKeys as[$table,$constraint,$column,$target]){
                $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');$statement->execute([$table,$constraint]);
                if(!$statement->fetchColumn())$pdo->exec("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$target}(id) ON DELETE RESTRICT");
            }
        }
    },
    'verify'=>static function(PDO $pdo)use($programTermsColumnExists,$programTermsIndexExists):bool{
        foreach([
            'club_event_term_versions'=>['status','archived_at','archived_by_trainer_id'],
            'shop_order_items'=>['program_terms_snapshot_json','program_terms_accepted_at','program_terms_accepted_by_account_id'],
            'club_program_enrollments'=>['terms_snapshot_json','terms_accepted_at','terms_accepted_by_account_id'],
        ]as$table=>$columns)foreach($columns as$column)if(!$programTermsColumnExists($pdo,$table,$column))return false;
        return$programTermsIndexExists($pdo,'idx_terms_scope_current');
    },
];
