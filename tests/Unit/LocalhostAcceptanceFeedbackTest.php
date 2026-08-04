<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/localhost_acceptance_feedback.php';

final class LocalhostAcceptanceFeedbackTest extends TestCase
{
    public function testFeedbackRoundTripAndMarkdownExportStayScenarioScoped(): void
    {
        $root = $this->temporaryRoot();
        try {
            $saved = \localhostAcceptanceFeedbackSave($root, 'A07', [
                'result' => 'partial',
                'importance' => 'important',
                'observed' => "Docházka funguje\nchybí vysvětlení | test",
                'expected' => 'Jednodušší popis.',
            ], 43, ['A01', 'A07']);

            self::assertSame('partial', $saved['result']);
            $loaded = \localhostAcceptanceFeedbackLoad($root);
            self::assertSame(43, $loaded['scenarios']['A07']['actor_trainer_id']);
            self::assertArrayNotHasKey('A01', $loaded['scenarios']);

            $markdown = \localhostAcceptanceFeedbackMarkdown([
                ['id' => 'A01'], ['id' => 'A07'],
            ], $loaded['scenarios']);
            self::assertStringContainsString('| A01 | NOT TESTED |', $markdown);
            self::assertStringContainsString('| A07 | PARTIAL | důležité |', $markdown);
            self::assertStringContainsString('Docházka funguje<br>chybí vysvětlení \\| test', $markdown);
            self::assertStringNotContainsString('actor_trainer_id', $markdown);
        } finally {
            $this->removeTemporaryRoot($root);
        }
    }

    public function testInvalidScenarioResultAndOversizedTextAreRejectedBeforeWrite(): void
    {
        $root = $this->temporaryRoot();
        try {
            foreach ([
                ['A99', ['result' => 'pass']],
                ['A01', ['result' => 'unknown']],
                ['A01', ['result' => 'fail', 'observed' => str_repeat('x', 4001)]],
            ] as [$scenarioId, $input]) {
                try {
                    \localhostAcceptanceFeedbackSave($root, $scenarioId, $input, 1, ['A01']);
                    self::fail('Neplatný vstup měl být odmítnut.');
                } catch (InvalidArgumentException) {
                    self::assertFileDoesNotExist(\localhostAcceptanceFeedbackPath($root));
                }
            }
        } finally {
            $this->removeTemporaryRoot($root);
        }
    }

    private function temporaryRoot(): string
    {
        $root = sys_get_temp_dir() . '/acceptance-feedback-' . bin2hex(random_bytes(6));
        mkdir($root);
        return $root;
    }

    private function removeTemporaryRoot(string $root): void
    {
        $path = \localhostAcceptanceFeedbackPath($root);
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($root . '/var')) {
            rmdir($root . '/var');
        }
        if (is_dir($root)) {
            rmdir($root);
        }
    }
}
