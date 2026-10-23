<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2).'/includes/training_submission.php';

final class TrainingSubmissionTest extends TestCase
{
    public function testIndependentOpenFormsHaveIndependentKeysAndRetriesKeepTheirKey(): void
    {
        $session=[];
        $first=\trainingSubmissionIssue($session, 10, 0);
        $second=\trainingSubmissionIssue($session, 10, 0);
        self::assertNotSame($first,$second);
        self::assertSame($first,\trainingSubmissionIssue($session,10,0,$first));
        \trainingSubmissionBegin($session,$first,10,0);
        self::assertSame('issued',\trainingSubmissionEntry($session,$second,10,0)['state']);
    }

    public function testCompletedReplayReturnsOriginalResultWithoutBeginningAnotherWrite(): void
    {
        $session=[];$key=\trainingSubmissionIssue($session,10,0);
        \trainingSubmissionBegin($session,$key,10,0);
        \trainingSubmissionComplete($session,$key,42,'index.php');
        $entry=\trainingSubmissionEntry($session,$key,10,0);
        self::assertSame('completed',$entry['state']);self::assertSame(42,$entry['training_id']);self::assertSame('index.php',$entry['redirect']);
        $this->expectException(\RuntimeException::class);
        \trainingSubmissionBegin($session,$key,10,0);
    }

    public function testUnknownPendingCannotBeReplayedOrReissued(): void
    {
        $session=[];$key=\trainingSubmissionIssue($session,10,0);
        \trainingSubmissionBegin($session,$key,10,0);
        try {\trainingSubmissionBegin($session,$key,10,0);self::fail('Pending replay accepted');}catch(\RuntimeException $e){self::assertStringContainsString('neopakuje',$e->getMessage());}
        $this->expectException(\RuntimeException::class);
        \trainingSubmissionIssue($session,10,0,$key);
    }

    public function testValidationFailureAndKnownRollbackAllowSameKeyRetry(): void
    {
        $session=[];$key=\trainingSubmissionIssue($session,10,3);
        self::assertSame($key,\trainingSubmissionIssue($session,10,3,$key));
        \trainingSubmissionBegin($session,$key,10,3);
        \trainingSubmissionRetryAfterRollback($session,$key);
        self::assertSame($key,\trainingSubmissionIssue($session,10,3,$key));
        \trainingSubmissionBegin($session,$key,10,3);
        self::assertSame('pending',\trainingSubmissionEntry($session,$key,10,3)['state']);
    }

    public function testWrongActorPlanMissingAndExpiredKeysAreDenied(): void
    {
        $session=[];$key=\trainingSubmissionIssue($session,10,3);
        foreach ([[$key,20,3,time()],[$key,10,0,time()],['',10,3,time()],[$key,10,3,time()+7201]] as [$candidate,$actor,$plan,$now]) {
            try {\trainingSubmissionEntry($session,$candidate,$actor,$plan,$now);self::fail('Unbound or expired key accepted');}catch(\RuntimeException $e){self::assertStringContainsString('Formulář',$e->getMessage());}
        }
    }

    public function testKeyStorageIsBoundedAndOldKeysCannotBecomeNewWrites(): void
    {
        $session=[];$old=\trainingSubmissionIssue($session,10,0,null,1000);
        for($i=0;$i<60;$i++) \trainingSubmissionIssue($session,10,0,null,1001+$i);
        self::assertCount(50,$session['training_submissions']);self::assertArrayNotHasKey($old,$session['training_submissions']);
        \trainingSubmissionIssue($session,10,0,null,10000);
        self::assertCount(1,$session['training_submissions']);
    }

    public function testGuardIsPersistedBeforeTransactionAndCompletionFollowsCommit(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/ulozit_trenink.php');
        self::assertLessThan(strpos($source,'$pdo->beginTransaction()'),strpos($source,'trainingSubmissionPersistPending('));
        self::assertGreaterThan(strpos($source,'$pdo->commit()'),strpos($source,'trainingSubmissionComplete('));
        self::assertStringContainsString('if ($submissionStarted && !$commitAttempted) trainingSubmissionRetryAfterRollback', $source);
        self::assertStringContainsString("header('Location: ' . \$submission['redirect'], true, 303)",$source);
    }

    public function testNoStoreBackReloadUsesOriginalKeyAndCannotIssueAnotherSubmission(): void
    {
        $session=[];$key=\trainingSubmissionIssue($session,10,15);
        $url=\trainingSubmissionFormUrl($key,['plan_id'=>'15','duplikat'=>'31','ignored'=>'anything']);
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        self::assertSame(['submission_key'=>$key,'plan_id'=>'15','duplikat'=>'31'],$query);
        // Following the initial 303 renders the form without allocating another key.
        self::assertSame($key,\trainingSubmissionIssue($session,10,15,$query['submission_key']));
        self::assertCount(1,$session['training_submissions']);
        \trainingSubmissionBegin($session,$key,10,15);
        \trainingSubmissionComplete($session,$key,42,'planovac.php');
        // A browser Back network GET reloads this same URL after success.
        try {\trainingSubmissionIssue($session,10,15,$query['submission_key']);self::fail('Back issued a fresh form');}catch(\RuntimeException $e){self::assertStringContainsString('již byl odeslán',$e->getMessage());}
        self::assertCount(1,$session['training_submissions']);
        // Explicitly opening a separate new form remains supported.
        self::assertNotSame($key,\trainingSubmissionIssue($session,10,0));
    }

    public function testFormRedirectsToStableKeyBeforeRenderingButAfterPlanAuthorization(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/formular.php');
        $redirect=strpos($source,'trainingSubmissionFormUrl($submissionKey, $_GET)');
        self::assertIsInt($redirect);
        self::assertLessThan(strpos($source,'<!DOCTYPE html>'),$redirect);
        self::assertGreaterThan(strpos($source,'coachScopeRequirePlan('),$redirect);
        self::assertStringContainsString("if (!isset(\$_GET['submission_key']))",$source);
    }
}
