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
    }
}
