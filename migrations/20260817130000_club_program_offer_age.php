<?php
declare(strict_types=1);

/** @return array<string,mixed>|null */
$clubProgramAgeColumn = static function (PDO $pdo, string $column): ?array {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
        $statement=$pdo->prepare(
            'SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'club_program_offers\' AND COLUMN_NAME=?'
        );
        $statement->execute([$column]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    foreach($pdo->query('PRAGMA table_info(club_program_offers)')->fetchAll(PDO::FETCH_ASSOC) as$row){
        if((string)$row['name']===$column)return$row;
    }
    return null;
};

return [
    'id'=>'20260817130000_club_program_offer_age',
    'up'=>static function(PDO $pdo)use($clubProgramAgeColumn):void{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if($clubProgramAgeColumn($pdo,'birth_year_from')===null){
            $pdo->exec($mysql
                ? 'ALTER TABLE club_program_offers ADD COLUMN birth_year_from SMALLINT NULL AFTER capacity'
                : 'ALTER TABLE club_program_offers ADD COLUMN birth_year_from INTEGER NULL');
        }
        if($clubProgramAgeColumn($pdo,'birth_year_to')===null){
            $pdo->exec($mysql
                ? 'ALTER TABLE club_program_offers ADD COLUMN birth_year_to SMALLINT NULL AFTER birth_year_from'
                : 'ALTER TABLE club_program_offers ADD COLUMN birth_year_to INTEGER NULL');
        }
    },
    'verify'=>static function(PDO $pdo)use($clubProgramAgeColumn):bool{
        foreach(['birth_year_from','birth_year_to']as$column){
            $definition=$clubProgramAgeColumn($pdo,$column);
            if($definition===null)return false;
            if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                && ((string)($definition['DATA_TYPE']??'')!=='smallint'||(string)($definition['IS_NULLABLE']??'')!=='YES'))return false;
            if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'&&(int)($definition['notnull']??1)!==0)return false;
        }
        return true;
    },
];
