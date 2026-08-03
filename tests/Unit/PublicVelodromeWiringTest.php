<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PublicVelodromeWiringTest extends TestCase
{
    public function testMigrationHelpersAndPagesExposeRequiredSafetyContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string)file_get_contents($root . '/migrations/20260804180000_public_velodrome.php');
        $profile = (string)file_get_contents($root . '/includes/public_profile.php');
        $booking = (string)file_get_contents($root . '/includes/public_velodrome.php');
        $profilePage = (string)file_get_contents($root . '/booking/verejny_profil.php');
        $bookingPage = (string)file_get_contents($root . '/booking/velodrom.php');
        $admin = (string)file_get_contents($root . '/verejny_velodrom_admin.php');
        $registration = (string)file_get_contents($root . '/booking/registrace.php');
        $people = (string)file_get_contents($root . '/booking/moje_osoby.php');

        self::assertStringContainsString('public_self_profiles', $migration);
        self::assertStringContainsString('sportovec_id', $migration);
        self::assertStringContainsString('uq_public_booking_active_person', $migration);
        self::assertStringContainsString('public_velodrome_reservation_events', $migration);
        self::assertStringContainsString('trg_public_velodrome_legacy_close', $migration);
        self::assertStringContainsString('Public profile system trainer identity conflict.', $migration);
        self::assertStringContainsString('function publicProfileSave', $profile);
        self::assertStringContainsString("'public-account:'", $profile);
        self::assertStringContainsString('function publicVelodromeReserve', $booking);
        self::assertStringContainsString('FOR UPDATE', $booking);
        self::assertStringContainsString('function publicVelodromeCancel', $booking);
        self::assertStringContainsString('function publicVelodromeManualConfirm', $booking);
        self::assertStringContainsString('csrf_verify', $profilePage);
        self::assertStringContainsString('csrf_verify', $bookingPage);
        self::assertStringContainsString("roleAtLeast('admin')", $admin);
        self::assertStringContainsString('confirm_payment', $admin);
        self::assertStringContainsString('manual_confirm', $admin);
        self::assertStringContainsString("name=\"narozeni\"", $registration);
        self::assertStringContainsString('publicProfileSave(', $registration);
        self::assertStringContainsString('$pdo->beginTransaction()', $registration);
        self::assertStringContainsString('verejny_profil.php', $people);
        self::assertStringContainsString('velodrom.php', $people);
        self::assertStringNotContainsString('shop_checkout.php', $booking);
    }
}
