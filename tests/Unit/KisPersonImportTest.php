<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/kis_person_import.php';
require_once dirname(__DIR__).'/Support/KisPersonImportFixture.php';
final class KisPersonImportTest extends TestCase
{
    public function testUnknownDatesAndEverySourceRelationshipSurvive(): void
    {
        $p=kisPersonReadPayload(gzencode(kisPersonJson(kisPersonImportFixture())));
        self::assertNull($p['data']['people'][0]['birth_date']);
        self::assertSame(2,kisPersonImportSummary($p['data'])['links']);
        self::assertSame(2,kisPersonImportSummary($p['data'])['aliases']);
    }
    public function testMissingArchiveRelationshipFailsClosed(): void
    {
        $p=kisPersonImportFixture();array_pop($p['source_archive']['guardians']);
        $this->expectException(InvalidArgumentException::class);
        kisPersonReadPayload(kisPersonJson($p));
    }
    public function testWrongPersonRelationshipFailsClosed(): void
    {
        $p=kisPersonImportFixture();$p['links'][0]['person_key']='O9999';
        $this->expectException(InvalidArgumentException::class);
        kisPersonReadPayload(kisPersonJson($p));
    }
    public function testSharedEmailAndExistingLoginNeverGrantAccess(): void
    {
        $pdo=new PDO('sqlite::memory:');$pdo->exec('CREATE TABLE verejni_uzivatele(id INT,email TEXT)');
        $p=kisPersonImportFixture();$second=$p['contacts'][0];$second['key']='Z0004.1';$p['contacts'][]=$second;
        $plan=kisPersonAccountPlan($pdo,$p);
        self::assertSame(['review_shared_email','review_shared_email'],array_column($plan,'state'));
        $p=kisPersonImportFixture();$pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test')");
        $plan=kisPersonAccountPlan($pdo,$p);
        self::assertSame('review_existing_account',$plan['Z0002.1']['state']);
        self::assertNull($plan['Z0002.1']['account_id']);
    }
    public function testArchiveIsAuthenticatedAndBoundToFingerprint(): void
    {
        $config=['keys'=>['v1'=>str_repeat('k',32)],'active_version'=>'v1','index_key'=>str_repeat('i',32)];
        $source=kisPersonJson(kisPersonImportFixture());$fp=hash('sha256',$source);
        $encrypted=kisPersonEncryptArchive($source,$fp,$config);
        $row=['fingerprint'=>$fp,'payload_ciphertext'=>$encrypted['ciphertext'],'key_version'=>$encrypted['key_version']];
        self::assertSame($source,kisPersonDecryptArchive($row,$config));
        $row['fingerprint']=str_repeat('0',64);
        $this->expectException(RuntimeException::class);kisPersonDecryptArchive($row,$config);
    }
}
