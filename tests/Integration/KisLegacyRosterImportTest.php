<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/kis_legacy_rosters.php';

final class KisLegacyRosterImportTest extends TestCase
{
    public function testHistoricalImportSeparatesExactLinksFromReviewAndKeepsCurrentSeasonUntouched(): void
    {
        [$pdo,$config]=$this->database();
        $plan=\kisLegacyRosterPreview($pdo,$config);
        self::assertSame(2,$plan['counts']['matched']);
        self::assertSame(3,$plan['counts']['review']);
        self::assertSame(3,count($plan['teams']));
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM kis_legacy_roster_runs')->fetchColumn());
        $result=\kisLegacyRosterApply($pdo,7,$plan['fingerprint'],$config);
        self::assertFalse($result['idempotent']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE status="active"')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.season_id=1')->fetchColumn());
        self::assertSame(3,(int)$pdo->query("SELECT COUNT(*) FROM kis_legacy_roster_claims WHERE status='review'")->fetchColumn());
        self::assertSame(['pouze_trener','rocnik_mimo_rozsah','rozpor_sloucenych_aliasu'],
            $pdo->query("SELECT reason_code FROM kis_legacy_roster_claims WHERE status='review' ORDER BY reason_code")->fetchAll(PDO::FETCH_COLUMN));
        self::assertTrue(\kisLegacyRosterApply($pdo,7,$plan['fingerprint'],$config)['idempotent']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $claimId=(int)$pdo->query("SELECT id FROM kis_legacy_roster_claims WHERE reason_code='rozpor_sloucenych_aliasu'")->fetchColumn();
        \kisLegacyRosterDecide($pdo,$claimId,7,'approve','Potvrzeno podle dvou zdrojových účtů.');
        self::assertSame('approved',$pdo->query('SELECT status FROM kis_legacy_roster_claims WHERE id='.$claimId)->fetchColumn());
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $this->expectException(\RuntimeException::class);
        \kisLegacyRosterDecide($pdo,$claimId,7,'approve','Druhé rozhodnutí.');
    }

    public function testWrongFingerprintLeavesDatabaseUntouched(): void
    {
        [$pdo,$config]=$this->database();
        $this->expectException(\RuntimeException::class);
        try {\kisLegacyRosterApply($pdo,7,str_repeat('0',64),$config);}
        finally {self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());}
    }

    private function database(): array
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Dítě','Jedno','2019-04-01',NULL,'aktivni'),(11,'Trenér','Dva','1980-01-01',NULL,'aktivni'),(12,'Dítě','Tři','2016-01-01',NULL,'aktivni'),(13,'Dítě','Čtyři','2018-01-01',NULL,'aktivni')");
        foreach(['20260804090000_kis_teams_rosters','20260804110000_kis_roster_policies','20261020180000_kis_full_person_import','20261020210000_kis_legacy_roster_links'] as $name) {
            $migration=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        }
        $pdo->exec("INSERT INTO club_seasons(code,name,season_type,starts_on,ends_on,status,created_by_trainer_id) VALUES('SCHOOL-2026-27','Současná sezona','school_year','2026-09-01','2027-08-31','active',7)");
        $config=['keys'=>['v1'=>str_repeat('k',32)],'active_version'=>'v1','index_key'=>str_repeat('i',32)];
        $j='Úterý 17:00 Jezevci (2019/2020) (2025/2026)';$m='Rexonix Kovo Praha - oddíl (2025/2026)';$x='BIKROS (2025/2026)';
        $archive=['source_namespace'=>'servis.kovopraha.cz/2026-10-20','source_archive'=>['people'=>[
            ['id_hash'=>'a1','Soupisky'=>$j,'je_hrac_dle_KIS'=>'ANO'],
            ['id_hash'=>'a2','Soupisky'=>$m,'je_hrac_dle_KIS'=>'NE'],
            ['id_hash'=>'a3','Soupisky'=>$j,'je_hrac_dle_KIS'=>'ANO'],
            ['id_hash'=>'a4','Soupisky'=>$m.', '.$x,'je_hrac_dle_KIS'=>'ANO'],
            ['id_hash'=>'a5','Soupisky'=>$m,'je_hrac_dle_KIS'=>'ANO'],
        ]]];
        $json=\kisPersonJson($archive);$fingerprint=hash('sha256',$json);$encrypted=\kisPersonEncryptArchive($json,$fingerprint,$config);
        $pdo->prepare('INSERT INTO kis_person_batches(fingerprint,source_namespace,actor_trainer_id,payload_ciphertext,key_version,summary_json) VALUES (?,?,?,?,?,?)')
            ->execute([$fingerprint,$archive['source_namespace'],7,$encrypted['ciphertext'],$encrypted['key_version'],'{}']);
        $insert=$pdo->prepare('INSERT INTO kis_person_aliases(source_key,sportovec_id,source_row,legacy_username) VALUES (?,?,?,?)');
        foreach([10,11,12,13,13] as $index=>$id)$insert->execute(['a'.($index+1),$id,$index+1,'source'.($index+1)]);
        return [$pdo,$config];
    }
}
