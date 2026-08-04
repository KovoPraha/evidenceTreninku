<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return(bool)$s->fetchColumn();
    }
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return(bool)$s->fetchColumn();
};
$columnExists = static function (PDO $pdo, string $table, string $column) use ($tableExists): bool {
    if(!$tableExists($pdo,$table))return false;
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$d){if((string)$d['name']===$column)return true;}return false;}
    $s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);return(bool)$s->fetchColumn();
};

return [
    'id' => '20260804234900_kis_import_parity_report',
    'up' => static function (PDO $pdo) use ($tableExists,$columnExists): void {
        $driver=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if($tableExists($pdo,'kis_import_rows')){
            foreach([
                'kis_roster_count'=>$driver==='mysql'?'INT UNSIGNED NOT NULL DEFAULT 0':'INTEGER NOT NULL DEFAULT 0',
                'kis_payment_paid_count'=>$driver==='mysql'?'INT UNSIGNED NOT NULL DEFAULT 0':'INTEGER NOT NULL DEFAULT 0',
                'kis_payment_open_count'=>$driver==='mysql'?'INT UNSIGNED NOT NULL DEFAULT 0':'INTEGER NOT NULL DEFAULT 0',
            ]as$name=>$definition){if(!$columnExists($pdo,'kis_import_rows',$name))$pdo->exec('ALTER TABLE kis_import_rows ADD COLUMN '.$name.' '.$definition);}
        }
        if($tableExists($pdo,'kis_import_runs')){
            foreach([
                'parity_contract_version'=>$driver==='mysql'?'VARCHAR(64) NULL':'TEXT NULL',
                'parity_fingerprint'=>$driver==='mysql'?'CHAR(64) NULL':'TEXT NULL',
                'parity_report_json'=>$driver==='mysql'?'LONGTEXT NULL':'TEXT NULL',
                'parity_blockers'=>$driver==='mysql'?'INT UNSIGNED NOT NULL DEFAULT 0':'INTEGER NOT NULL DEFAULT 0',
                'parity_generated_at'=>$driver==='mysql'?'DATETIME NULL':'TEXT NULL',
            ]as$name=>$definition){if(!$columnExists($pdo,'kis_import_runs',$name))$pdo->exec('ALTER TABLE kis_import_runs ADD COLUMN '.$name.' '.$definition);}
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists,$columnExists): bool {
        foreach(['kis_roster_count','kis_payment_paid_count','kis_payment_open_count']as$c){if($tableExists($pdo,'kis_import_rows')&&!$columnExists($pdo,'kis_import_rows',$c))return false;}
        foreach(['parity_contract_version','parity_fingerprint','parity_report_json','parity_blockers','parity_generated_at']as$c){if($tableExists($pdo,'kis_import_runs')&&!$columnExists($pdo,'kis_import_runs',$c))return false;}
        return true;
    },
];
