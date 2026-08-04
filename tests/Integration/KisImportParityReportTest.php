<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_import_run_lib.php';

final class KisImportParityReportTest extends TestCase
{
    public function testStoredReportComparesDomainsAndExposesMissingPaymentTargetWithoutPii(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,kis_external_id,kis_aktivni,kis_platebne_aktivni,kis_neuhrazeno,kis_posledni_uhrada,kis_soupisky) VALUES(10,'Existing','Secret','2012-01-01','KIS-10',1,1,0,NULL,'U15')");
        $artifactId = $this->artifact($pdo);
        $people = [
            ['kis_external_id'=>'KIS-10','_kis_external_id_raw'=>'KIS-10','jmeno'=>'Existing','prijmeni'=>'Secret','narozeni'=>'2012-01-01','kis_aktivni'=>1,'kis_platebne_aktivni'=>1,'kis_neuhrazeno'=>0,'kis_posledni_uhrada'=>null,'kis_soupisky'=>'U15','_soupisky_parsed'=>['U15'],'_kis_payment'=>['paid_rows'=>1,'open_rows'=>0]],
            ['kis_external_id'=>'KIS-20','_kis_external_id_raw'=>'KIS-20','jmeno'=>'New','prijmeni'=>'Private','narozeni'=>'2013-02-02','kis_aktivni'=>1,'kis_platebne_aktivni'=>1,'kis_neuhrazeno'=>0,'kis_posledni_uhrada'=>null,'kis_soupisky'=>'U13','_soupisky_parsed'=>['U13'],'_kis_payment'=>['paid_rows'=>1,'open_rows'=>0]],
        ];
        $runId = \kisImportCreateRun($pdo,$people,$this->meta(),[],['users'=>'users.xlsx'],7,['users'=>$artifactId]);
        $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id='.$runId)->fetch(PDO::FETCH_ASSOC);
        $report = \kisImportStoredParityReport($run);

        self::assertSame('blocked',$report['status']);
        self::assertFalse($report['cutover_ready']);
        self::assertSame(2,$report['summary']['total_blockers']);
        self::assertSame(1,$report['domains']['persons']['exact_same']);
        self::assertSame(1,$report['domains']['persons']['creates']);
        self::assertSame(2,$report['domains']['rosters']['assignment_count']);
        self::assertSame(2,$report['domains']['payment_signals']['paid_rows']);
        self::assertSame(['payment_prescription_target_contract_missing'],$report['coverage_blockers']);
        self::assertSame(['matched_same','new'],array_column($report['rows'],'category'));
        $json=(string)$run['parity_report_json'];
        self::assertStringNotContainsString('Existing',$json);
        self::assertStringNotContainsString('Secret',$json);
        self::assertStringNotContainsString('KIS-10',$json);
    }

    public function testReportFingerprintIsStableForEquivalentProjection(): void
    {
        $pdo=$this->database();
        $artifactId=$this->artifact($pdo);
        $person=['kis_external_id'=>'KIS-30','_kis_external_id_raw'=>'KIS-30','jmeno'=>'Stable','prijmeni'=>'Projection','narozeni'=>'2014-01-01','kis_aktivni'=>0,'kis_platebne_aktivni'=>0,'kis_neuhrazeno'=>0,'kis_soupisky'=>'','_soupisky_parsed'=>[],'_kis_payment'=>['paid_rows'=>0,'open_rows'=>0]];
        $first=\kisImportCreateRun($pdo,[$person],$this->meta(),[],['users'=>'users.xlsx'],7,['users'=>$artifactId]);
        $second=\kisImportCreateRun($pdo,[$person],$this->meta(),[],['users'=>'users.xlsx'],7,['users'=>$artifactId]);
        $a=$pdo->query('SELECT * FROM kis_import_runs WHERE id='.$first)->fetch(PDO::FETCH_ASSOC);
        $b=$pdo->query('SELECT * FROM kis_import_runs WHERE id='.$second)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(\kisImportStoredParityReport($a)['fingerprint'],\kisImportStoredParityReport($b)['fingerprint']);
    }

    private function meta(): array
    {
        return ['users'=>['headers'=>['kisid','jmeno','prijmeni','datumnarozeni'],'rows'=>2],'payments'=>['headers'=>['kisid','stav'],'rows'=>2],'soupisky'=>['headers'=>['kisid','soupiska','jmeno','prijmeni'],'rows'=>2]];
    }

    private function artifact(PDO $pdo): int
    {
        $hash=str_repeat('c',64);
        $pdo->exec("INSERT OR IGNORE INTO kis_import_source_artifacts(source_kind,contract_version,sha256,byte_size,original_filename,storage_key,archived_by) VALUES('users','parity-v1','$hash',123,'users.xlsx','users.raw',7)");
        return(int)$pdo->query("SELECT id FROM kis_import_source_artifacts WHERE source_kind='users'")->fetchColumn();
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,first_name_norm TEXT,last_name_norm TEXT,narozeni TEXT,email TEXT,uciid TEXT,kis_aktivni INTEGER DEFAULT 0,kis_platebne_aktivni INTEGER DEFAULT 0,kis_neuhrazeno REAL DEFAULT 0,kis_posledni_uhrada TEXT,kis_soupisky TEXT);
            CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_by INTEGER,status TEXT,source_users TEXT,source_payments TEXT,source_rosters TEXT,stats_json TEXT,warnings_json TEXT);
            CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,person_key TEXT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT,oddil TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,kis_posledni_uhrada TEXT,kis_soupisky TEXT,raw_json TEXT);
            CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,row_id INTEGER,sportovec_id INTEGER,match_status TEXT,confidence INTEGER,reason TEXT,candidate_json TEXT);
            SQL);
        foreach(['20260804233000_kis_import_source_artifacts.php','20260804234500_kis_import_preview_integrity.php','20260804234800_kis_import_field_contract.php','20260804234900_kis_import_parity_report.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        return $pdo;
    }
}
