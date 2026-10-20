<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/kis_legacy_rosters.php';
$port=getenv('EVIDENCE_MARIADB_SMOKE_PORT')?:'3306';
if(!ctype_digit($port))throw new RuntimeException('Invalid MariaDB port.');
$server=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='evidence_kis_legacy_roster_smoke_test';
$server->exec("DROP DATABASE IF EXISTS `$database`");
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$database.';charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(255),narozeni DATE NOT NULL,uciid VARCHAR(80),stav_clenstvi VARCHAR(24)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO treneri VALUES(7)');
    $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Test','2019-01-01',NULL,'aktivni'),(11,'Karel','Trenér','1981-01-01',NULL,'aktivni')");
    foreach(['20260804090000_kis_teams_rosters','20260804110000_kis_roster_policies','20261020180000_kis_full_person_import','20261020210000_kis_legacy_roster_links'] as $name){
        $migration=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$migration['up']($pdo);
        if(!$migration['verify']($pdo))throw new RuntimeException('Migration verification failed: '.$name);
    }
    $pdo->exec("INSERT INTO club_seasons(code,name,season_type,starts_on,ends_on,status,created_by_trainer_id) VALUES('CURRENT-26','Současná sezona','school_year','2026-09-01','2027-08-31','active',7)");
    $archive=['source_namespace'=>'servis.kovopraha.cz/2026-10-20','source_archive'=>['people'=>[
        ['id_hash'=>'a1','Soupisky'=>'Úterý Jezevci (2019/2020) (2025/2026)','je_hrac_dle_KIS'=>'ANO'],
        ['id_hash'=>'a2','Soupisky'=>'Rexonix Kovo Praha - oddíl (2025/2026)','je_hrac_dle_KIS'=>'NE'],
    ]]];
    $json=kisPersonJson($archive);$fingerprint=hash('sha256',$json);
    $config=['keys'=>['v1'=>str_repeat('k',32)],'active_version'=>'v1','index_key'=>str_repeat('i',32)];
    $encrypted=kisPersonEncryptArchive($json,$fingerprint,$config);
    $pdo->prepare('INSERT INTO kis_person_batches(fingerprint,source_namespace,actor_trainer_id,payload_ciphertext,key_version,summary_json) VALUES (?,?,?,?,?,?)')
        ->execute([$fingerprint,$archive['source_namespace'],7,$encrypted['ciphertext'],$encrypted['key_version'],'{}']);
    $pdo->exec("INSERT INTO kis_person_aliases(source_key,sportovec_id,source_row,legacy_username) VALUES('a1',10,1,'anna'),('a2',11,2,'karel')");
    $plan=kisLegacyRosterPreview($pdo,$config);
    if($plan['counts']!==['matched'=>1,'review'=>1])throw new RuntimeException('Wrong preview classification.');
    $result=kisLegacyRosterApply($pdo,7,$plan['fingerprint'],$config);
    if($result['matched']!==1 || $result['review']!==1 || $result['teams']!==2)throw new RuntimeException('Wrong apply result.');
    if(!kisLegacyRosterApply($pdo,7,$plan['fingerprint'],$config)['idempotent'])throw new RuntimeException('Apply is not idempotent.');
    if((int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE status='removed' AND source='kis_shadow'")->fetchColumn()!==1)
        throw new RuntimeException('Historical member was not created.');
    if((int)$pdo->query("SELECT COUNT(*) FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.season_id=1")->fetchColumn()!==0)
        throw new RuntimeException('Current season was changed.');
    $claimId=(int)$pdo->query("SELECT id FROM kis_legacy_roster_claims WHERE status='review'")->fetchColumn();
    kisLegacyRosterDecide($pdo,$claimId,7,'skip','V KIS jde o trenéra.');
    if((int)$pdo->query("SELECT COUNT(*) FROM kis_legacy_roster_claims WHERE status='skipped'")->fetchColumn()!==1)
        throw new RuntimeException('Review decision was not stored.');
    echo "KIS legacy roster MariaDB smoke passed\n";
}finally{$server->exec("DROP DATABASE IF EXISTS `$database`");}
