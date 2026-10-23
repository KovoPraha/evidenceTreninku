<?php
declare(strict_types=1);
namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/training_edit.php';

final class TrainingEditSafetyTest extends TestCase
{
    private function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE trenink_trener(trenink_id INTEGER, trener_id INTEGER)');
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY, obrazky TEXT)');
        $pdo->exec("INSERT INTO treninky VALUES(1, '[\"nahrane_obrazky/old.jpg\"]')");
        $pdo->exec('INSERT INTO trenink_trener VALUES(1,10),(1,20),(2,30)');
        $pdo->beginTransaction();
        return $pdo;
    }

    public function testCoachEditingNotesPreservesDisabledColleagueFields(): void
    {
        self::assertSame([10,20], \trainingEditCoachIds($this->fixture(), 1, 10, false, [10]));
    }

    public function testCoachCannotForgeAnotherCoachOrRemoveExistingOnes(): void
    {
        self::assertSame([10,20], \trainingEditCoachIds($this->fixture(), 1, 10, false, [99]));
    }

    public function testLeadMayReplaceCoachesButKeepsCurrentActorAsBefore(): void
    {
        self::assertSame([30,20,40], \trainingEditCoachIds($this->fixture(), 1, 30, true, [20,40,40,0,-1]));
    }

    public function testCoachResolutionRequiresTransaction(): void
    {
        $pdo = $this->fixture();
        $pdo->rollBack();
        $this->expectException(\LogicException::class);
        \trainingEditCoachIds($pdo, 1, 10, false, []);
    }

    public function testForeignAndUnsafeImagesCannotBeRetired(): void
    {
        $stored = ['nahrane_obrazky/own.jpg', 'nahrane_obrazky/../config.php', 'nahrane_obrazky/sub\\bad.jpg'];
        $submitted = array_merge($stored, ['nahrane_obrazky/foreign.jpg', '/nahrane_obrazky/own.jpg', 'nahrane_obrazky/own.jpg']);
        self::assertSame(['nahrane_obrazky/own.jpg'], \trainingEditOwnedImageRemovals($stored, $submitted));
        self::assertSame([], \trainingEditOwnedImageRemovals([], $submitted));
    }

    public function testWriterResolvesCoachesBeforeDeletingAndUsesOwnedImageIntersection(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/update_trenink.php');
        self::assertStringContainsString('trainingEditOwnedImageRemovals($dbImg, $remove)', $source);
        self::assertLessThan(strpos($source, "DELETE FROM trenink_trener"), strpos($source, 'trainingEditCoachIds($pdo'));
        self::assertLessThan(strpos($source, 'trainingEditCoachIds($pdo'), strpos($source, 'trainingClosureAssertLegacyMutable($pdo'));
    }

    public function testLegacyWritersRecheckLiveAuthorizationInsideTransaction(): void
    {
        foreach (['update_trenink.php','smazat_trenink.php'] as $file) {
            $source=file_get_contents(dirname(__DIR__,2).'/'.$file);
            self::assertGreaterThan(strpos($source, '$pdo->beginTransaction()'), strpos($source, 'trainingClosureAssertTraining($pdo'), $file);
            self::assertLessThan(strpos($source, 'trainingClosureAssertLegacyMutable($pdo'), strpos($source, 'trainingClosureAssertTraining($pdo'), $file);
        }
    }

    public function testDeletingTrainingCompensatesPhotoRemovalOnRollback(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/smazat_trenink.php');
        self::assertStringContainsString('trainingEditOwnedImageRemovals($obrazky, $obrazky)', $source);
        self::assertStringContainsString('fileMutationRetire($fileMutations', $source);
        self::assertStringContainsString('fileMutationRollback($fileMutations)', $source);
        self::assertGreaterThan(strpos($source, '$pdo->commit()'), strpos($source, 'fileMutationCommitted($fileMutations)'));
        self::assertStringNotContainsString('@rename(', $source);
    }

    public function testImageChangesUseTheLatestStoredSetNotTheEditorsOriginalSet(): void
    {
        $pdo=$this->fixture();
        // An earlier editor has added a photo since the second editor opened the page.
        $pdo->exec("UPDATE treninky SET obrazky='[\"nahrane_obrazky/old.jpg\",\"nahrane_obrazky/new.jpg\"]' WHERE id=1");
        $stored=\trainingEditStoredImages($pdo,1);
        $removed=\trainingEditOwnedImageRemovals($stored,['nahrane_obrazky/old.jpg']);
        self::assertSame(['nahrane_obrazky/new.jpg'],array_values(array_diff($stored,$removed)));
        $source=file_get_contents(dirname(__DIR__,2).'/update_trenink.php');
        self::assertLessThan(strpos($source, 'trainingEditStoredImages($pdo'), strpos($source, 'trainingClosureAssertLegacyMutable($pdo'));
        self::assertLessThan(strpos($source, 'trainingEditOwnedImageRemovals($dbImg'), strpos($source, 'trainingEditStoredImages($pdo'));
    }

    public function testImageReadCannotHappenOutsideLockingTransaction(): void
    {
        $pdo=$this->fixture();$pdo->rollBack();
        $this->expectException(\LogicException::class);
        \trainingEditStoredImages($pdo,1);
    }

    public function testEditFormRendersConsumedFeedbackAndLinksToVersionedCorrection(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/edit_trenink.php');
        $body=substr($source,strpos($source,'<body'));
        self::assertStringContainsString('<?= h($flashError) ?>',$body);
        self::assertStringContainsString('<?= h($flashSuccess) ?>',$body);
        self::assertStringContainsString('class="alert alert-danger" role="alert"',$body);
        self::assertStringContainsString('training_closure_admin.php?id=<?= $treninkId ?>',$body);
        self::assertLessThan(strpos($body,'<form id="treninkForm"'),strpos($body,'<?= h($flashError) ?>'));
    }
}
