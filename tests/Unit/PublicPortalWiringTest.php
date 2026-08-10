<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PublicPortalWiringTest extends TestCase
{
    public function testCatalogAndSchedulesArePublicButMutationsRequireLogin(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['eshop.php','produkt.php','krouzky.php','velodrom.php'] as $file) {
            $source = (string)file_get_contents($root . '/booking/' . $file);
            self::assertStringContainsString('isLoggedIn', $source);
            self::assertStringContainsString("REQUEST_METHOD'] === 'POST'", $source);
        }
        $training = (string)file_get_contents($root . '/booking/treninky.php');
        self::assertStringContainsString('publicTrainingSchedule', $training);
        self::assertStringNotContainsString("header('Location: prihlaseni.php')", $training);
    }

    public function testBothLoginEntrypointsBindTrainerAndCustomerRoles(): void
    {
        $root = dirname(__DIR__, 2);
        $trainerLogin = (string)file_get_contents($root . '/login.php');
        $shopLogin = (string)file_get_contents($root . '/booking/prihlaseni.php');
        self::assertStringContainsString('unifiedAccountEnsureTrainerCustomer', $trainerLogin);
        self::assertStringContainsString('auth_session_bind_public_user', $trainerLogin);
        self::assertStringContainsString('unifiedAccountAuthenticate', $shopLogin);
        self::assertStringContainsString('auth_session_bind_trainer', $shopLogin);
    }

    public function testCustomerLoginAndRegistrationExposeOnePrimaryHeading(): void
    {
        $root = dirname(__DIR__, 2) . '/booking/';
        foreach (['prihlaseni.php' => 'Přihlášení', 'registrace.php' => 'Registrace'] as $file => $heading) {
            $source = (string)file_get_contents($root . $file);
            self::assertStringContainsString('<h1 class="h4', $source, $file);
            self::assertStringContainsString($heading . '</h1>', $source, $file);
        }
        self::assertStringContainsString('for="login-email"', (string)file_get_contents($root . 'prihlaseni.php'));
        self::assertStringContainsString('for="registration-email"', (string)file_get_contents($root . 'registrace.php'));
    }
}
