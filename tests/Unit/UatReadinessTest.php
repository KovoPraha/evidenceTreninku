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
        self::assertCount(10, $ready['checks']);

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
}
