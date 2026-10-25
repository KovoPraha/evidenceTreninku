<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CurrentDocumentationContractTest extends TestCase
{
    public function testCurrentStateOwnsReleaseEvidenceAndHandoffOnlyLinksToIt(): void
    {
        $root = dirname(__DIR__, 2);
        $state = (string) file_get_contents($root . '/docs/CURRENT_STATE.md');
        $handoff = (string) file_get_contents($root . '/docs/HANDOFF_CURRENT.md');
        self::assertLessThanOrEqual(80, count(file($root . '/docs/CURRENT_STATE.md')));
        self::assertStringContainsString('](CURRENT_STATE.md)', $handoff);
        self::assertDoesNotMatchRegularExpression('/`[a-f0-9]{40}`/', $handoff);
        self::assertSame(1, preg_match('~\((verification/[a-z0-9/-]+/release-comparison[.]json)\)~', $state, $match));
        $release = json_decode((string) file_get_contents($root . '/docs/' . $match[1]), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString($release['live']['sha'], $state);
        self::assertStringContainsString((string) $release['live']['run_id'], $state);
        self::assertStringContainsString('CLUB_COMPLETION_DECISIONS.md', $state);
    }

    public function testDocumentationLinksTheCurrentDataFlowAuditAndGatedFioSchedule(): void
    {
        $root = dirname(__DIR__, 2);
        $readme = (string)file_get_contents($root . '/README.md');
        $docsIndex = (string)file_get_contents($root . '/docs/README.md');
        $fio = (string)file_get_contents($root . '/docs/fio-readonly-import-k4.md');
        $workflow = (string)file_get_contents($root . '/.github/workflows/fio-import-production.yml');
        self::assertStringContainsString('outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_APLIKACE.xlsx', $readme);
        self::assertStringContainsString('../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_APLIKACE.xlsx', $docsIndex);
        self::assertStringContainsString('KIS_FIO_SCHEDULE_ENABLED', $fio);
        self::assertStringContainsString('schedule:', $workflow);
        self::assertStringContainsString("vars.KIS_FIO_SCHEDULE_ENABLED == 'true'", $workflow);
        self::assertStringContainsString('workflow_dispatch:', $workflow);
    }
}
