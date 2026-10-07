<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TesterFeedbackWiringTest extends TestCase
{
    public function testPasswordFormsKeepPolicyButExplainAndRevealPassphrase(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['booking/registrace.php', 'booking/nove_heslo.php'] as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            self::assertStringContainsString('minlength="12"', $source);
            self::assertStringContainsString('Zobrazit', $source);
            self::assertStringContainsString('několik slov', $source);
        }
    }

    public function testCalendarPreflightExplainsMissingProgramTerm(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/club_calendar.php');
        $service = (string)file_get_contents($root . '/includes/club_calendar.php');
        self::assertStringContainsString('$registrationHasSession', $page);
        self::assertStringContainsString('doplňte začátek, konec a místo', $page);
        self::assertStringContainsString('začátek, konec a místo', $service);
        self::assertStringContainsString('referenční kód:', $service);
    }

    public function testCreationPaymentAndStaffActionsAreExplicitAndRecoverable(): void
    {
        $root = dirname(__DIR__, 2);
        $people = (string)file_get_contents($root . '/sprava_sportovcu.php');
        self::assertStringContainsString('1. Zkontrolovat možné shody', $people);
        self::assertStringContainsString('zatím nebyla založena', $people);
        $payment = (string)file_get_contents($root . '/booking/objednavka.php');
        self::assertStringContainsString('session_write_close()', $payment);
        self::assertStringContainsString('20000', $payment);
        self::assertStringContainsString('bankovním převodem', $payment);
        $staff = (string)file_get_contents($root . '/sprava_pracovnich_pozic.php');
        self::assertStringContainsString('remove_position', $staff);
        self::assertStringContainsString('set_active', $staff);
        self::assertStringContainsString('historie a audit zůstaly zachované', $staff);
    }

    public function testTrainingRosterVisibilityAndRsvpAreWiredEndToEnd(): void
    {
        $root = dirname(__DIR__, 2);
        $form = (string)file_get_contents($root . '/planovany_trenink_form.php');
        self::assertStringContainsString('name="roster_mode"', $form);
        self::assertStringContainsString('Interní trénink bez účastníků', $form);
        self::assertStringNotContainsString('no-roster-confirm-wrap', $form);
        $planner = (string)file_get_contents($root . '/planovac.php');
        self::assertStringContainsString('trainingRosterBridgePlanTeamIds', $planner);
        self::assertStringContainsString('trainingRsvpPlanSummaries', $planner);
        self::assertStringContainsString('training_rsvps_admin.php', $planner);
        foreach (['booking/sportovni_prehled.php', 'booking/muj_sport.php'] as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            self::assertStringContainsString('training_rsvp_save', $source);
            self::assertStringContainsString('Potvrzení účasti na trénincích', $source);
        }
    }

    public function testReportsAndLegacyRouteFailVisiblyAndUseCanonicalMeasurementLinks():void
    {
        $root=dirname(__DIR__,2);$report=(string)file_get_contents($root.'/prehled_vsech_vykazu.php');$legacy=(string)file_get_contents($root.'/vypis_vsech_vykazu.php');
        self::assertStringContainsString('trenink_mereni',$report);
        self::assertStringContainsString('vendor/autoload.php',$report);
        self::assertStringContainsString('Referenční kód:',$report);
        self::assertStringContainsString("(int)\$data['id']",$report);
        self::assertStringContainsString('prehled_vsech_vykazu.php',$legacy);
    }

    public function testRewardSectionHeadersHaveStableContrast():void
    {
        $root=dirname(__DIR__,2);$css=(string)file_get_contents($root.'/assets/app-ui.css');
        self::assertStringContainsString('.app-section-header-primary',$css);
        self::assertStringContainsString('color: #fff !important',$css);
        foreach(['hromadne_odmeny.php','sprava_sportovec_obdobi.php']as$file)self::assertStringContainsString('app-section-header-',(string)file_get_contents($root.'/'.$file),$file);
    }

    public function testMonthlyPlansAreDiscoverableFromIndividualCharges():void
    {
        $page=(string)file_get_contents(dirname(__DIR__,2).'/member_charges_admin.php');
        self::assertStringContainsString('member_fee_plans_admin.php',$page);
        self::assertStringContainsString('Pravidelné měsíční příspěvky',$page);
    }
}
