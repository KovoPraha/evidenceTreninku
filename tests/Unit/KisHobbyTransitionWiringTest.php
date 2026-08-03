<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class KisHobbyTransitionWiringTest extends TestCase
{
    public function testAdminWizardIsPreviewFirstAndProtected(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/kis_transition_admin.php');
        $service = (string)file_get_contents($root . '/includes/kis_hobby_transition.php');
        foreach (["roleAtLeast('admin')", 'csrf_verify', 'confirm_transition', 'preview_fingerprint', 'reason', 'kisHobbyTransitionPreview', 'kisHobbyTransitionExecute'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
        foreach (['club_roster_rollover_runs', 'club_roster_rollover_run_items', 'hobby_to_race_add_member', 'hash_equals', 'GET_LOCK', 'SELECT DATABASE()', 'FOR UPDATE'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringNotContainsString('INSERT INTO club_roster_members', $this->functionSource($service, 'kisHobbyTransitionPreview', 'kisHobbyTransitionExecute'));
    }

    private function functionSource(string $source, string $start, string $next): string
    {
        $from = strpos($source, 'function ' . $start);
        $to = strpos($source, 'function ' . $next);
        self::assertNotFalse($from); self::assertNotFalse($to);
        return substr($source, (int)$from, (int)$to - (int)$from);
    }
}
