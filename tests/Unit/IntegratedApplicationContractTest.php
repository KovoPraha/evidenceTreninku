<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IntegratedApplicationContractTest extends TestCase
{
    public function testEvidenceShopAndKisShareIdentityPeopleAndTrainingData(): void
    {
        $root = dirname(__DIR__, 2);
        $database = $this->source($root, 'db.php');
        $customerLogin = $this->source($root, 'booking/prihlaseni.php');
        $trainerLogin = $this->source($root, 'login.php');
        $family = $this->source($root, 'includes/family_portal.php');
        $checkout = $this->source($root, 'includes/shop_checkout.php');
        $program = $this->source($root, 'includes/club_program.php');
        $training = $this->source($root, 'includes/training_roster_bridge.php');

        self::assertStringContainsString("require_once __DIR__ . '/includes/ui_shell.php'", $database);
        self::assertStringContainsString("require_once __DIR__ . '/includes/auto_migrace.php'", $database);
        self::assertStringContainsString('unifiedAccountAuthenticate', $customerLogin);
        self::assertStringContainsString('auth_session_bind_trainer', $customerLogin);
        self::assertStringContainsString('unifiedAccountEnsureTrainerCustomer', $trainerLogin);
        self::assertStringContainsString('JOIN sportovci s ON s.id=r.sportovec_id', $family);
        self::assertStringContainsString('club_roster_members', $family);
        self::assertStringContainsString('beneficiary_sportovec_id', $checkout);
        self::assertStringContainsString('clubProgramActivatePaidOrderInTransaction', $checkout);
        self::assertStringContainsString('INSERT INTO club_roster_members', $program);
        self::assertStringContainsString('JOIN sportovci sp ON sp.id=e.sportovec_id', $training);
        self::assertStringContainsString('FROM trenink_sportovec ts JOIN sportovci sp', $training);
    }

    private function source(string $root, string $relative): string
    {
        $source = file_get_contents($root . '/' . $relative);
        self::assertIsString($source);
        return $source;
    }
}
