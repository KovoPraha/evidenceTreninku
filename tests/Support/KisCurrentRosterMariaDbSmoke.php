<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/kis_current_rosters.php';
$port=getenv('EVIDENCE_MARIADB_SMOKE_PORT')?:'3306';
if(!ctype_digit($port))throw new RuntimeException('Invalid MariaDB port.');
$server=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='evidence_kis_current_roster_smoke_test';
$server->exec("DROP DATABASE IF EXISTS `$database`");
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$database.';charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(255),narozeni DATE NULL,uciid VARCHAR(80),stav_clenstvi VARCHAR(24)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO treneri VALUES(7)');
    $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Test','2019-01-01',NULL,'aktivni'),(11,'Ben','Test','2016-01-01',NULL,'aktivni'),(12,'Cyril','Test','2018-01-01',NULL,'aktivni'),(13,'Dana','Test','2019-01-01',NULL,'aktivni'),(14,'Eva','Test','2005-01-01',NULL,'aktivni')");
    foreach(['20260804090000_kis_teams_rosters','20260804110000_kis_roster_policies','20261020180000_kis_full_person_import','20261020210000_kis_legacy_roster_links','20261020220000_kis_current_roster_links'] as $name){
        $migration=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$migration['up']($pdo);
        if(!$migration['verify']($pdo))throw new RuntimeException('Migration verification failed: '.$name);
    }
    $pdo->exec("INSERT INTO club_seasons(code,name,season_type,starts_on,ends_on,status,created_by_trainer_id) VALUES
        ('LEGACY-KIS-2025-26','Starý KIS','school_year','2025-09-01','2026-08-31','closed',7),
        ('SCHOOL-2026-27','Aktuální','school_year','2026-09-01','2027-08-31','active',7)");
    $pdo->exec("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES
        (1,'OLD-JEZEVCI','Úterý 17:00 Jezevci (2019/2020) (2025/2026)','Historický','', 'closed',7),
        (1,'OLD-TOCNACI','Točňáci 1x týdně (úterý NEBO čtvrtek) 16:00-17:00 (2012-2017) (2025/2026)','Historický','', 'closed',7),
        (1,'OLD-ODDIL','Rexonix Kovo Praha - oddíl (2025/2026)','Historický','', 'closed',7),
        (2,'KR-2627-JEZEVCI','Jezevci 2026/27','Cyklistika','6–7 let','active',7),
        (2,'KR-2627-TOCNACI-UTERY','Točňáci úterý','Cyklistika','6–10 let','active',7)");
    $pdo->exec('CREATE TABLE club_program_offers(team_id BIGINT UNSIGNED,season_id BIGINT UNSIGNED,birth_year_from SMALLINT,birth_year_to SMALLINT,status VARCHAR(24)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO club_program_offers VALUES(4,2,2019,2020,'active'),(5,2,2012,2017,'active')");
    $fingerprint=str_repeat('a',64);
    $pdo->prepare('INSERT INTO kis_person_batches(fingerprint,source_namespace,actor_trainer_id,payload_ciphertext,key_version,summary_json) VALUES (?,?,?,?,?,?)')
        ->execute([$fingerprint,'servis.kovopraha.cz/2026-10-20',7,'fixture','v1','{}']);
    $pdo->prepare('INSERT INTO kis_legacy_roster_runs(batch_fingerprint,plan_fingerprint,season_id,summary_json,actor_trainer_id) VALUES (?,?,?,?,?)')
        ->execute([$fingerprint,str_repeat('b',64),1,'{}',7]);
    $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES
        (1,10,'removed','kis_shadow','2025-09-01','2026-08-31',7),
        (1,11,'removed','kis_shadow','2025-09-01','2026-08-31',7),
        (2,12,'removed','kis_shadow','2025-09-01','2026-08-31',7),
        (3,14,'removed','kis_shadow','2025-09-01','2026-08-31',7)");
    $insert=$pdo->prepare('INSERT INTO kis_legacy_roster_claims(batch_fingerprint,sportovec_id,team_id,legacy_label,source_keys_json,reason_code,status,member_id) VALUES (?,?,?,?,?,?,?,?)');
    foreach([[10,1,'matched'],[11,1,'matched'],[12,2,'matched'],[13,1,'review'],[14,3,'matched']] as [$person,$team,$status]) {
        $label=match($team){1=>'Úterý 17:00 Jezevci (2019/2020) (2025/2026)',2=>'Točňáci 1x týdně (úterý NEBO čtvrtek) 16:00-17:00 (2012-2017) (2025/2026)',3=>'Rexonix Kovo Praha - oddíl (2025/2026)'};
        $memberId=null;if($status==='matched'){$s=$pdo->prepare('SELECT id FROM club_roster_members WHERE team_id=? AND sportovec_id=?');$s->execute([$team,$person]);$memberId=(int)$s->fetchColumn();}
        $insert->execute([$fingerprint,$person,$team,$label,'[]',$status==='review'?'pouze_trener':'',$status,$memberId]);
    }
    $plan=kisCurrentRosterPreview($pdo);
    if($plan['counts']!==['matched'=>2,'already_present'=>0,'review'=>3])throw new RuntimeException('Wrong current roster preview: '.json_encode($plan['counts']));
    $result=kisCurrentRosterApply($pdo,7,$plan['fingerprint']);
    if($result['matched']!==2 || $result['review']!==3 || $result['created_teams']!==1)throw new RuntimeException('Wrong apply result.');
    if(!kisCurrentRosterApply($pdo,7,$plan['fingerprint'])['idempotent'])throw new RuntimeException('Apply is not idempotent.');
    if((int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE team_id=4 AND status='active' AND source='kis_shadow'")->fetchColumn()!==1)
        throw new RuntimeException('Current member was not created.');
    if((int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE team_id=5")->fetchColumn()!==0)
        throw new RuntimeException('Ambiguous split group was assigned.');
    if((int)$pdo->query("SELECT COUNT(*) FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.code='LEG26-REXONIX-ODDIL' AND m.status='active'")->fetchColumn()!==1)
        throw new RuntimeException('Missing club roster was not created and populated.');
    if((int)$pdo->query("SELECT COUNT(*) FROM kis_current_roster_claims WHERE status='review'")->fetchColumn()!==3)
        throw new RuntimeException('Review queue is incomplete.');
    $reviewId=(int)$pdo->query("SELECT id FROM kis_current_roster_claims WHERE reason_code='no_unique_target'")->fetchColumn();
    kisCurrentRosterDecide($pdo,$reviewId,7,'approve',5,'Potvrzený úterní termín.');
    if((int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE team_id=5 AND status='active'")->fetchColumn()!==1)
        throw new RuntimeException('Manual review did not create membership.');
    echo "KIS current roster MariaDB smoke passed\n";
}finally{$server->exec("DROP DATABASE IF EXISTS `$database`");}
