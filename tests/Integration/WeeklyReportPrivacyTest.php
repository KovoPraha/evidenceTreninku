<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/includes/weekly_report_storage.php';

final class WeeklyReportPrivacyTest extends TestCase
{
    private PDO $pdo;
    private string $root;
    private string|false $oldRoot;
    private array $oldSession;
    protected function setUp(): void
    {
        $this->oldRoot = getenv('APP_PRIVATE_STORAGE_ROOT');
        $this->oldSession = $_SESSION ?? [];
        $this->root = sys_get_temp_dir() . '/weekly-private-' . bin2hex(random_bytes(8));
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->root);
        $_SESSION = ['trener_id'=>1,'staff_active_position'=>'sports_lead','staff_positions'=>['sports_lead']];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INTEGER);CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);CREATE TABLE sportovci(id INTEGER PRIMARY KEY);CREATE TABLE treninky(id INTEGER PRIMARY KEY);INSERT INTO treneri VALUES(1,1);INSERT INTO staff_user_positions VALUES(1,'sports_lead');INSERT INTO sportovci VALUES(3);INSERT INTO treninky VALUES(5)");
        $this->pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER PRIMARY KEY)');
    }
    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($this->root);
        }
        putenv($this->oldRoot === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $this->oldRoot);
        $_SESSION = $this->oldSession;
    }
    private function fixture(): string
    {
        $id=bin2hex(random_bytes(16));$dir=\weeklyReportDirectory().'/'.$id;mkdir($dir);
        $pdf='%PDF-1.4 LOCALHOST private report';file_put_contents($dir.'/report.pdf',$pdf);
        file_put_contents($dir.'/manifest.json',json_encode(['contract'=>'weekly-report-private-v1','id'=>$id,'training_ids'=>[5],'person_ids'=>[3],'pdf_sha256'=>hash('sha256',$pdf)]));
        return $id;
    }
    public function testPrivateFileRequiresExactManifestAndUnchangedBytes(): void
    {
        $id=$this->fixture();$file=\weeklyReportFile($this->pdo,$id);
        self::assertStringStartsWith(str_replace('\\','/',$this->root),str_replace('\\','/',$file['path']));
        self::assertSame([3],$file['manifest']['person_ids']);
        file_put_contents($file['path'],'corrupted');
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,$id);
    }
    public function testRevokedRoleCannotUseCachedSession(): void
    {
        $id=$this->fixture();$this->pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,$id);
    }
    public function testInactiveAccountCannotDownload(): void
    {
        $id=$this->fixture();$this->pdo->exec('UPDATE treneri SET aktivni=0');
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,$id);
    }
    public function testScopeCannotBeInferredFromPossessionOfReportId(): void
    {
        $id=$this->fixture();$this->pdo->exec("UPDATE staff_user_positions SET position_code='coach'");$_SESSION['staff_active_position']='coach';$_SESSION['staff_positions']=['coach'];
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,$id);
    }
    public function testTraversalNeverResolvesPrivateFile(): void
    {
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,'../config.php');
    }
    public function testLiveSuperadminCanUseSportsLeadButRevokedGrantDoesNotTrustSession(): void
    {
        $id=$this->fixture();$this->pdo->exec('DELETE FROM staff_user_positions;INSERT INTO staff_superadmins VALUES(1)');
        $_SESSION['staff_positions']=[];$_SESSION['staff_is_superadmin']=true;
        self::assertSame('sports_lead',\staffActivePosition());
        self::assertSame($id,\weeklyReportFile($this->pdo,$id)['manifest']['id']);
        $this->pdo->exec('DELETE FROM staff_superadmins');
        self::assertTrue(\staffIsSuperadmin()); // Stale cached state must not authorize the file.
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,$id);
    }
    public function testInactiveSuperadminCannotReadWeeklyReport(): void
    {
        $id=$this->fixture();$this->pdo->exec('DELETE FROM staff_user_positions;INSERT INTO staff_superadmins VALUES(1);UPDATE treneri SET aktivni=0');
        $_SESSION['staff_positions']=[];$_SESSION['staff_is_superadmin']=true;
        $this->expectException(\DomainException::class);\weeklyReportFile($this->pdo,$id);
    }
}
