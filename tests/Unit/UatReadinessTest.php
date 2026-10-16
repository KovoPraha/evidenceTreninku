<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/uat_readiness.php';

final class UatReadinessTest extends TestCase
{
    /** @var list<string> */
    private array $environmentNames = [];

    protected function tearDown(): void
    {
        foreach ($this->environmentNames as $name) putenv($name);
        parent::tearDown();
    }

    public function testRuntimeEnvironmentCanSupplyDeploymentOwnedUatSettings(): void
    {
        $boolName = 'KIS_TEST_UAT_READY_' . bin2hex(random_bytes(4));
        $stringName = 'KIS_TEST_UAT_OWNER_' . bin2hex(random_bytes(4));
        $this->environmentNames = [$boolName, $stringName];
        putenv($boolName . '=1');
        putenv($stringName . '=Marek');

        self::assertTrue(\uatReadinessBoolSetting('KIS_TEST_UNDEFINED_BOOL', $boolName));
        self::assertSame('Marek', \uatReadinessStringSetting('KIS_TEST_UNDEFINED_STRING', $stringName));
    }

    public function testEveryRequiredGateMustBeGreen(): void
    {
        $snapshot = [
            'release' => [
                'sha' => str_repeat('a', 40),
                'run_id' => '123456',
                'deployed_at' => '2026-09-08T10:00:00+00:00',
                'uat_approved' => true,
            ],
            'parents' => 2,
            'children' => 2,
            'family_links' => 2,
            'makeup_rules' => 1,
            'fresh_paid_participants' => 1,
            'staff_id' => 10,
            'positions' => 8,
            'superadmin' => true,
            'fixtures' => [
                'goods' => 1, 'program' => 1, 'free_event' => 1, 'paid_event' => 1,
                'velodrome' => 2, 'lesson' => 1, 'training' => 1, 'calendar_event' => 1,
            ],
            'technical_public_without_prefix' => 0,
            'stripe' => ['enabled' => true, 'test_pair' => true, 'live_pair' => false, 'webhook' => true],
            'bank_ready' => true,
            'bank_reconciliation_ready' => true,
            'inbox_ready' => true,
            'queue_available' => true,
            'queue_failed' => 0,
            'owner' => 'Vedoucí UAT',
            'window_end' => '2026-09-10 18:00:00',
        ];
        $now = new DateTimeImmutable('2026-09-08 12:00:00', new \DateTimeZone('Europe/Prague'));

        $ready = \uatReadinessEvaluate($snapshot, $now);
        self::assertTrue($ready['ready']);
        self::assertCount(13, $ready['checks']);

        $snapshot['family_links'] = -1;
        self::assertFalse(\uatReadinessEvaluate($snapshot, $now)['ready']);
        $snapshot['family_links'] = 2;
        $snapshot['makeup_rules'] = 0;
        self::assertFalse(\uatReadinessEvaluate($snapshot, $now)['ready']);
        $snapshot['makeup_rules'] = 1;
        $snapshot['fresh_paid_participants'] = 0;
        self::assertFalse(\uatReadinessEvaluate($snapshot, $now)['ready']);
        $snapshot['fresh_paid_participants'] = 1;

        $snapshot['stripe']['live_pair'] = true;
        $blocked = \uatReadinessEvaluate($snapshot, $now);
        self::assertFalse($blocked['ready']);
        $stripe = array_values(array_filter($blocked['checks'], static fn(array $check): bool => $check['key'] === 'stripe'))[0];
        self::assertFalse($stripe['ok']);
        self::assertStringContainsString('STOP', $stripe['detail']);
    }

    public function testTrainingFixtureCountsOnlyRosterLinkedTermInsideUatWindow(): void
    {
        if (defined('KIS_UAT_WINDOW_END')) $this->markTestSkipped('UAT window is defined by the host configuration.');
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,nazev TEXT,datum TEXT,cas_do TEXT,je_verejny INTEGER,stav TEXT)');
        $pdo->exec('CREATE TABLE training_roster_links(plan_id INTEGER,team_id INTEGER)');
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $inside = $now->modify('+2 days')->format('Y-m-d');
        $outside = $now->modify('+4 days')->format('Y-m-d');
        $close = $now->modify('+3 days')->setTime(18, 30);
        $insert = $pdo->prepare("INSERT INTO planovane_treninky VALUES(?,'TEST - Trénink nového dítěte',?,'18:00:00',1,'planovany')");
        $insert->execute([1,$inside]);
        $insert->execute([2,$outside]);
        $insert->execute([3,$inside]);
        $pdo->exec('INSERT INTO training_roster_links VALUES(1,10),(2,10)');
        $before = getenv('KIS_UAT_WINDOW_END');
        putenv('KIS_UAT_WINDOW_END=' . $close->format('Y-m-d H:i:s'));
        try {
            $snapshot = \uatReadinessSnapshot($pdo);
            self::assertSame(1, $snapshot['fixtures']['training']);
        } finally {
            $before === false ? putenv('KIS_UAT_WINDOW_END') : putenv('KIS_UAT_WINDOW_END=' . $before);
        }
    }

    public function testPaidFixtureRequiresUnusedParticipantInTargetRoster(): void
    {
        if (defined('KIS_UAT_WINDOW_END')) $this->markTestSkipped('UAT window is defined by the host configuration.');
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,aktivni INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(account_id INTEGER,sportovec_id INTEGER,status TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE club_event_roster_targets(event_id INTEGER,team_id INTEGER)');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT,status TEXT,pricing_policy TEXT,registration_ends_at TEXT)');
        $pdo->exec('CREATE TABLE club_event_sessions(event_id INTEGER,status TEXT,ends_at TEXT)');
        $pdo->exec('CREATE TABLE club_event_registrations(event_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'tester.karel@velocota.com',1)");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Platba261016-1','Tester')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,10,'approved',NULL)");
        $pdo->exec("INSERT INTO club_roster_members VALUES(5,10,'active')");
        $pdo->exec('INSERT INTO club_event_roster_targets VALUES(7,5)');
        $end=(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->modify('+5 days')->format('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO club_events VALUES(7,'TEST - Příměstský den','open','product_variants',?)")->execute([$end]);
        $pdo->prepare("INSERT INTO club_event_sessions VALUES(7,'scheduled',?)")->execute([$end]);
        $previous=getenv('KIS_UAT_WINDOW_END');
        putenv('KIS_UAT_WINDOW_END='.$end);
        try {
            self::assertSame(1,\uatReadinessSnapshot($pdo)['fresh_paid_participants']);
            $pdo->exec('INSERT INTO club_event_registrations VALUES(7,10)');
            self::assertSame(0,\uatReadinessSnapshot($pdo)['fresh_paid_participants']);
        } finally {
            $previous===false ? putenv('KIS_UAT_WINDOW_END') : putenv('KIS_UAT_WINDOW_END='.$previous);
        }
    }
}
