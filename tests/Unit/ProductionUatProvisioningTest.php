<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/provision-production-uat.php';

final class ProductionUatProvisioningTest extends TestCase
{
    public function testSettingsRequireShortLivedWindowAndStrongPassword(): void
    {
        $now = new DateTimeImmutable('2026-09-22 12:00:00', new DateTimeZone('Europe/Prague'));
        $settings = \kisUatValidateSettings([
            'password' => 'Dlouhe!Uat2026-Heslo',
            'window_end' => '2026-10-06 20:00:00',
        ], $now);
        self::assertSame('2026-10-06 20:00:00', $settings['window_end']->format('Y-m-d H:i:s'));

        $this->expectException(InvalidArgumentException::class);
        \kisUatValidateSettings([
            'password' => 'Dlouhe!Uat2026-Heslo',
            'window_end' => '2027-01-01 00:00:00',
        ], $now);
    }

    public function testProductionScriptAndWorkflowKeepIdentitiesAndSecretAllowlisted(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string)file_get_contents($root . '/bin/provision-production-uat.php');
        $workflow = (string)file_get_contents($root . '/.github/workflows/production-drills.yml');
        $cleanup = (string)file_get_contents($root . '/bin/production-test-cleanup.php');

        foreach (['tester.karel@velocota.com','tester.petra@velocota.com','tester.ema','tester.adam','TEST -'] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
        self::assertStringContainsString("KIS_UAT_PROVISION_CONFIRM')!=='VYTVORIT-UAT-DATA'", $script);
        self::assertStringContainsString('secrets.KIS_UAT_TEST_PASSWORD', $workflow);
        self::assertStringNotContainsString('Dlouhe!Uat2026-Heslo', $workflow);
        self::assertStringContainsString('pripravit-uat-ucty-a-data', $workflow);
        self::assertStringContainsString("'KP-TEST-UAT-LAHEV'", $script);
        self::assertStringContainsString("'KP-TEST-UAT-KROUZEK'", $script);
        self::assertStringContainsString("'KP-TEST-UAT-PRIMESTSKY-DEN'", $script);
        self::assertStringContainsString('clubEventOpenPaidRegistration', $script);
        self::assertStringContainsString('clubEventRosterReplaceTargets', $script);
        self::assertStringContainsString('shopCatalogPublicationActivate(', $script);
        self::assertLessThan(strpos($script,'clubEventOpenPaidRegistration',strpos($script,'function kisUatUpsertPaidShopEvent')),strpos($script,'shopCatalogPublicationActivate(',strpos($script,'function kisUatUpsertPaidShopEvent')));
        self::assertStringContainsString("(.products|length) == 3", $workflow);
        self::assertStringContainsString("pub.public_name LIKE 'TEST -%'", $cleanup);
        self::assertStringContainsString('KP-TEST-UAT-PRIMESTSKY-DEN', $cleanup);

        $registration = (string)file_get_contents($root . '/includes/club_event_registration.php');
        $paidList = (string)file_get_contents($root . '/includes/club_event_shop.php');
        self::assertStringContainsString("visibility='public'", $registration);
        self::assertStringContainsString("e.visibility='public'", $registration);
        self::assertStringContainsString("e.visibility='public'", $paidList);
        self::assertStringContainsString("s.ends_at>=CURRENT_TIMESTAMP", $paidList);
    }

    public function testExistingUatSeasonIsExpandedForARepeatedRun(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,status TEXT,created_by_trainer_id INTEGER,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY AUTOINCREMENT,season_id INTEGER,code TEXT,name TEXT,discipline TEXT,age_label TEXT,status TEXT,created_by_trainer_id INTEGER)');
        $pdo->exec("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('TEST-UAT-2026','TEST - UAT období','2026-09-22','2026-11-21','draft',7)");
        $seasonId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES($seasonId,'TEST-UAT-DETI','TEST - UAT děti','vše','8–10 let','active',7)");
        $teamId = (int)$pdo->lastInsertId();

        $result = \kisUatSeasonAndTeam($pdo, 7, '2026-10-07', '2026-12-06');

        self::assertSame(['season_id'=>$seasonId,'team_id'=>$teamId], $result);
        $season = $pdo->query("SELECT starts_on,ends_on,status FROM club_seasons WHERE code='TEST-UAT-2026'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026-09-22', $season['starts_on']);
        self::assertSame('2026-12-06', $season['ends_on']);
        self::assertSame('active', $season['status']);
    }
}
