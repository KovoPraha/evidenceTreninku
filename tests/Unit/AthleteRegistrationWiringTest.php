<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AthleteRegistrationWiringTest extends TestCase
{
    public function testPublicFlowUsesSingleClaimQueueAndNeverRunsPersonMatcher(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string)file_get_contents($root . '/includes/athlete_registration.php');
        $page = (string)file_get_contents($root . '/booking/registrace_sportovce.php');
        $people = (string)file_get_contents($root . '/booking/moje_osoby.php');

        self::assertStringContainsString('account_person_claim_requests', $service);
        self::assertStringContainsString("request_kind='athlete_registration'", $service);
        self::assertStringNotContainsString('personMatchV1', $service . $page);
        self::assertStringContainsString('Žádost jsme přijali.', $page);
        self::assertStringContainsString('Cache-Control: no-store', $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString('registrace_sportovce.php', $people);
        self::assertStringNotContainsString('birth_number\'] ??', $page);
    }

    public function testTermsAreScopedWithoutSyntheticEventOrTrainer(): void
    {
        $migration = (string)file_get_contents(
            dirname(__DIR__, 2) . '/migrations/20260816180000_registration_terms_scope.php'
        );
        self::assertStringContainsString('uq_terms_scope_version', $migration);
        self::assertStringContainsString("'athlete_registration','athlete-registration-v1'", $migration);
        self::assertStringContainsString("NULL,'system',0", $migration);
        self::assertStringNotContainsString("INSERT INTO club_events", $migration);
    }
}
