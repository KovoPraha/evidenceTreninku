<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/password_security.php';
require_once dirname(__DIR__) . '/includes/public_profile_token.php';

$securityTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$securityColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};

$securityMarker = 'security_public_tokens_v1';

return [
    'id' => '20260804235500_public_profile_token_rotation',
    'up' => static function (PDO $pdo) use ($securityTableExists,$securityColumnExists,$securityMarker): void {
        if(!$securityTableExists($pdo,'nastaveni'))throw new RuntimeException('Security rotation requires nastaveni.');
        $marker=$pdo->prepare('SELECT hodnota FROM nastaveni WHERE klic=?');$marker->execute([$securityMarker]);
        if((string)$marker->fetchColumn()==='complete')return;
        $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
        try{
            if($securityTableExists($pdo,'sportovci')&&$securityColumnExists($pdo,'sportovci','hash')){
                $ids=$pdo->query('SELECT id FROM sportovci ORDER BY id'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetchAll(PDO::FETCH_COLUMN);
                $update=$pdo->prepare('UPDATE sportovci SET hash=? WHERE id=?');
                foreach($ids as$id)$update->execute([public_profile_token_generate(),(int)$id]);
            }
            if($securityTableExists($pdo,'treneri')&&$securityColumnExists($pdo,'treneri','heslo')){
                $rows=$pdo->query('SELECT id,heslo FROM treneri ORDER BY id'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetchAll(PDO::FETCH_ASSOC);
                $update=$pdo->prepare('UPDATE treneri SET heslo=? WHERE id=? AND heslo=?');
                foreach($rows as$row){$stored=(string)$row['heslo'];if(trainer_password_is_modern_hash($stored))continue;$update->execute([trainer_password_hash($stored),(int)$row['id'],$stored]);if($update->rowCount()!==1)throw new RuntimeException('Trainer password changed concurrently.');}
            }
            $driver=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql=$driver==='mysql'?'INSERT INTO nastaveni(klic,hodnota) VALUES(?,?) ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota)':'INSERT INTO nastaveni(klic,hodnota) VALUES(?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota';
            $pdo->prepare($sql)->execute([$securityMarker,'complete']);
            if($ownsTransaction)$pdo->commit();
        }catch(Throwable$exception){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$exception;}
    },
    'verify' => static function (PDO $pdo) use ($securityTableExists,$securityColumnExists,$securityMarker): bool {
        $marker=$pdo->prepare('SELECT hodnota FROM nastaveni WHERE klic=?');$marker->execute([$securityMarker]);if((string)$marker->fetchColumn()!=='complete')return false;
        if($securityTableExists($pdo,'sportovci')&&$securityColumnExists($pdo,'sportovci','hash')){
            $tokens=$pdo->query('SELECT hash FROM sportovci')->fetchAll(PDO::FETCH_COLUMN);$seen=[];
            foreach($tokens as$token){$token=(string)$token;if(!public_profile_token_is_strong($token)||isset($seen[$token]))return false;$seen[$token]=true;}
        }
        if($securityTableExists($pdo,'treneri')&&$securityColumnExists($pdo,'treneri','heslo'))foreach($pdo->query('SELECT heslo FROM treneri')->fetchAll(PDO::FETCH_COLUMN)as$password)if(!trainer_password_is_modern_hash((string)$password))return false;
        return true;
    },
];
