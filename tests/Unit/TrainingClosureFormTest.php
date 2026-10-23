<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/training_closure_form.php';

final class TrainingClosureFormTest extends TestCase
{
    public function testInvalidIntervalKeepsAllEnteredFactsAndExplicitlyEmptyAttendance(): void
    {
        $post=['version'=>'2','actual_started_at'=>'2026-10-23T10:00','actual_ended_at'=>'2026-10-23T09:00',
            'participant_ids'=>[],'coach_ids'=>['326'],'record_source'=>'paper','guest_reason'=>'LOCALHOST guest explanation',
            'reason'=>'LOCALHOST paper attendance transcription','confirm_action'=>'1'];
        $form=\trainingClosureFormState($post,['version'=>2,'actual_started_at'=>'2026-10-22 08:00:00'],[91,92],[327]);
        self::assertSame($post['actual_started_at'],$form['actual_started_at']);self::assertSame($post['actual_ended_at'],$form['actual_ended_at']);
        self::assertSame([],$form['participant_ids']);self::assertSame([326],$form['coach_ids']);
        self::assertSame('paper',$form['record_source']);self::assertSame($post['reason'],$form['reason']);
        self::assertSame($post['guest_reason'],$form['guest_reason']);self::assertTrue($form['confirm_action']);
    }

    public function testFailedStaleWriteDoesNotSilentlyUpgradeConcurrencyVersion(): void
    {
        $form=\trainingClosureFormState(['version'=>'1','participant_ids'=>['91']],['version'=>2],[92],[327]);
        self::assertSame(1,$form['version']);self::assertSame([91],$form['participant_ids']);
        self::assertSame([],$form['coach_ids']);self::assertFalse($form['confirm_action']);
    }

    public function testFreshGetShowsCanonicalStateAndRequiresNewReasonAndConfirmation(): void
    {
        $form=\trainingClosureFormState(null,['version'=>3,'actual_started_at'=>'2026-10-23 10:00:00','actual_ended_at'=>'2026-10-23 11:00:00'],[92],[327]);
        self::assertSame(3,$form['version']);self::assertSame('2026-10-23T10:00',$form['actual_started_at']);
        self::assertSame('2026-10-23T11:00',$form['actual_ended_at']);self::assertSame([92],$form['participant_ids']);
        self::assertSame('',$form['reason']);self::assertFalse($form['confirm_action']);
    }

    public function testPageEscapesDraftAndRedirectsOnlySuccessfulHtmlSubmission(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/training_closure_admin.php');
        foreach(['actual_started_at','actual_ended_at','guest_reason','reason'] as $field)self::assertStringContainsString('tcH($form[\''.$field.'\'])',$source);
        self::assertStringContainsString("if(\$error===''){",$source);
        self::assertStringContainsString('true,303)', $source);
        self::assertStringContainsString('trainingClosureFormState($failedClosure,$current,$selectedPeople,$selectedCoaches)', $source);
        self::assertStringContainsString("\$form['version']!==(int)(\$current['version']??0)",$source);
    }
}
