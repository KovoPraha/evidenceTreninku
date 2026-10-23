<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

final class SportovecEditorWiringTest extends TestCase
{
    public function testSingleEditNavigatesWithoutSubmittingTheBulkSelection(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/sprava_sportovcu.php');
        self::assertMatchesRegularExpression('/<a class="[^"]*edit-btn"\s+href="[^\n]+edit_id[^\n]+#edit-card"/', $source);
        self::assertDoesNotMatchRegularExpression('/<button[^>]*edit-btn/', $source);
        self::assertStringNotContainsString('btn.dataset.email', $source);
        self::assertStringContainsString('Cache-Control: no-store', $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString("$" . "editInput['profile_revision'] = is_string", $source);
    }

    public function testEditorAndMemberCardExposeSavedRosterAssignmentAndDetails(): void
    {
        $root = dirname(__DIR__, 2);
        $editor = file_get_contents($root . '/sprava_sportovcu.php');
        foreach (['sportovecEditorSaveBirthNumber','sportovecEditorSaveParent','sportovecEditorUnlinkParent','sportovecEditorGuardianAccount','sportovecEditorRevokeAccount','sportovecEditorAddRoster'] as $handler) {
            self::assertStringContainsString($handler, $editor);
        }
        $card = file_get_contents($root . '/sportovec_karta.php');
        self::assertStringContainsString('csrf_verify', $card);
        self::assertStringContainsString('sportovecEditorAddRoster($pdo, $sportovecId', $card);
        self::assertStringContainsString('sportovec_editor_rosters_view.php', $card);
        self::assertStringContainsString('sportovec_editor_payments_view.php', $card);
        self::assertStringContainsString('&tab=kis#rosters-title', $card);
        $view = file_get_contents($root . '/includes/sportovec_editor_view.php');
        foreach (['name="birth_number"','sportovec_editor_parents_view.php','sportovec_editor_rosters_view.php','sportovec_editor_payments_view.php'] as $control) self::assertStringContainsString($control, $view);
        self::assertStringContainsString('name="profile_revision"', $view);
        self::assertStringContainsString("$" . "editInput['profile_revision'] ?? sportovecEditorRevision", $view);
        self::assertStringContainsString("'reload' => bin2hex(random_bytes(8))", $view);
        self::assertStringContainsString('sportovecInternalNoteAdd($pdo, $sportovecId', $card);
        self::assertStringContainsString('&tab=poznamky#poznamky', $card);
    }
}
