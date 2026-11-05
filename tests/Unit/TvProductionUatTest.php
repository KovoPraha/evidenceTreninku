<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/tv-production-uat.php';
require_once dirname(__DIR__, 2) . '/includes/tv_program.php';
require_once dirname(__DIR__, 2) . '/includes/staff_account_lifecycle.php';

final class TvProductionUatTest extends TestCase
{
    private PDO $pdo;
    private string $directory;
    private string $state;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/kis-tv-uat-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        $this->state = $this->directory . '/credentials.json';
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER,session_version INTEGER)');
        $this->pdo->exec("INSERT INTO treneri VALUES(1,'Existing','existing@example.invalid','hash','admin',1,1)");
        $this->pdo->exec('CREATE TABLE staff_positions(code TEXT PRIMARY KEY,label TEXT,sort_order INTEGER,active INTEGER)');
        $this->pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT,is_default INTEGER,assigned_by_trainer_id INTEGER)');
        $this->pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $this->pdo->exec('CREATE TABLE staff_account_events(trainer_id INTEGER,actor_trainer_id INTEGER,action TEXT,reason TEXT,before_json TEXT,after_json TEXT)');
        $this->pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,trener_id INTEGER,aktivni INTEGER,session_version INTEGER)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261104180000_tv_programs.php';
        $migration['up']($this->pdo);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testIdentityIsNewTvOnlyAndPreparationIsIdempotentWithoutPasswordRotation(): void
    {
        $first = \tvUatPrepare($this->pdo, '41999999999', $this->state);
        $state = file_get_contents($this->state);
        $second = \tvUatPrepare($this->pdo, '41999999999', $this->state);
        self::assertSame($first['actor_id'], $second['actor_id']);
        self::assertSame($state, file_get_contents($this->state));
        self::assertSame(['tv_operator'], $this->pdo->query('SELECT position_code FROM staff_user_positions')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM staff_superadmins')->fetchColumn());
        self::assertSame('admin', $this->pdo->query('SELECT role FROM treneri WHERE id=1')->fetchColumn());
        self::assertStringNotContainsString('password', json_encode($first));
    }

    public function testCleanupArchivesOnlyOwnProgramRevokesBothSessionsAndKeepsOtherProgramIntact(): void
    {
        $other = \tvCreateProgram($this->pdo, 'Real programme', 1);
        $before = \tvProgram($this->pdo, $other);
        $prepared = \tvUatPrepare($this->pdo, '41999999999', $this->state);
        $id = $prepared['actor_id'];
        $program = \tvCreateProgram($this->pdo, 'TEST TV 41999999999', $id);
        \tvMutateProgram($this->pdo, $program, 1, $id, 'publish', ['name' => 'TEST TV 41999999999', 'slides' => []]);
        $this->pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,' . (int)$id . ',1,1)');
        $report = \tvUatCleanup($this->pdo, '41999999999', $this->state);
        self::assertTrue($report['ok']);
        self::assertSame([$program], $report['program_ids']);
        self::assertNull(\tvPublicProgram($this->pdo, $program));
        self::assertSame($before, \tvProgram($this->pdo, $other));
        self::assertSame(0, (int)$this->pdo->query('SELECT aktivni FROM treneri WHERE id=' . (int)$id)->fetchColumn());
        self::assertSame(2, (int)$this->pdo->query('SELECT session_version FROM treneri WHERE id=' . (int)$id)->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM staff_user_positions')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT aktivni FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertArrayNotHasKey('password', json_decode((string)file_get_contents($this->state), true));
        self::assertTrue(\tvUatCleanup($this->pdo, '41999999999', $this->state)['ok']);
    }

    public function testExplicitPublishedDemoRemainsPublicWhileIdentityIsRevoked(): void
    {
        $prepared = \tvUatPrepare($this->pdo, '41999999999', $this->state);
        $id = $prepared['actor_id'];
        $program = \tvCreateProgram($this->pdo, 'TEST TV 41999999999 – ukázka', $id);
        \tvMutateProgram($this->pdo, $program, 1, $id, 'publish', ['name' => 'TEST TV 41999999999 – ukázka', 'slides' => []]);
        $other = \tvCreateProgram($this->pdo, 'TEST TV 41999999999 – koncept', $id);
        $before = \tvPublicProgram($this->pdo, $program);
        $report = \tvUatCleanup($this->pdo, '41999999999', $this->state, $program);
        self::assertTrue($report['ok']);
        self::assertSame($program, $report['retained_program_id']);
        self::assertFalse($report['programs_archived']);
        self::assertSame($before, \tvPublicProgram($this->pdo, $program));
        self::assertSame(1, (int)\tvProgram($this->pdo, $other)['archived']);
        self::assertSame(0, (int)$this->pdo->query('SELECT aktivni FROM treneri WHERE id=' . (int)$id)->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM staff_user_positions')->fetchColumn());
    }

    public function testCannotRetainAnotherActorsProgramOrAnUnpublishedDraft(): void
    {
        $other = \tvCreateProgram($this->pdo, 'Existing public program', 1);
        \tvMutateProgram($this->pdo, $other, 1, 1, 'publish', ['name' => 'Existing public program', 'slides' => []]);
        $prepared = \tvUatPrepare($this->pdo, '41999999999', $this->state);
        $draft = \tvCreateProgram($this->pdo, 'TEST TV 41999999999', $prepared['actor_id']);
        $before = \tvUatProgramFingerprints($this->pdo);
        foreach ([$other, $draft, -1] as $invalidId) {
            try {
                \tvUatCleanup($this->pdo, '41999999999', $this->state, $invalidId);
                self::fail('Invalid retention must fail.');
            } catch (\RuntimeException $exception) {
                self::assertSame($before, \tvUatProgramFingerprints($this->pdo));
                self::assertSame(1, (int)$this->pdo->query('SELECT aktivni FROM treneri WHERE id=' . (int)$prepared['actor_id'])->fetchColumn());
            }
        }
    }

    public function testCollisionNeverResetsExistingCredentials(): void
    {
        $this->pdo->exec("UPDATE treneri SET email='tv-uat-41999999999@example.invalid' WHERE id=1");
        $before = $this->pdo->query('SELECT * FROM treneri')->fetchAll(PDO::FETCH_ASSOC);
        try {
            \tvUatPrepare($this->pdo, '41999999999', $this->state);
            self::fail('Collision must be refused.');
        } catch (\RuntimeException $exception) {
            self::assertSame($before, $this->pdo->query('SELECT * FROM treneri')->fetchAll(PDO::FETCH_ASSOC));
            self::assertFileDoesNotExist($this->state);
        }
    }

    public function testForgedCleanupIdentityIsRejectedBeforeChangingAnyAccount(): void
    {
        \tvUatPrepare($this->pdo, '41999999999', $this->state);
        $state = json_decode((string)file_get_contents($this->state), true);
        $state['actor_id'] = 1;
        \tvUatWriteState($this->state, $state);
        $before = $this->pdo->query('SELECT * FROM treneri')->fetchAll(PDO::FETCH_ASSOC);
        try {
            \tvUatCleanup($this->pdo, '41999999999', $this->state);
            self::fail('Forged identity must fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame($before, $this->pdo->query('SELECT * FROM treneri')->fetchAll(PDO::FETCH_ASSOC));
        }
    }

    public function testMainCannotRunAgainstArbitraryHostOrWithoutExplicitConfirmation(): void
    {
        $old = getenv('TV_UAT_CONFIRM');
        putenv('TV_UAT_CONFIRM');
        try {
            $this->expectException(\RuntimeException::class);
            \tvUatMain();
        } finally {
            $old === false ? putenv('TV_UAT_CONFIRM') : putenv('TV_UAT_CONFIRM=' . $old);
        }
    }
}
