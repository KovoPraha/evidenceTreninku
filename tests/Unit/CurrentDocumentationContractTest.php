<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CurrentDocumentationContractTest extends TestCase
{
    public function testCurrentStateAndHandoffDescribeTheSameVerifiedRelease(): void
    {
        $root = dirname(__DIR__, 2);
        $state = (string)file_get_contents($root . '/docs/CURRENT_STATE.md');
        $handoff = (string)file_get_contents($root . '/docs/HANDOFF_CURRENT.md');
        foreach ([$state, $handoff] as $document) {
            self::assertStringContainsString('7ad2104652efcba978bd5661ebe0c6b02977ff4b', $document);
            self::assertStringContainsString('37766181968', $document);
            self::assertStringContainsString('37766211885', $document);
            self::assertStringContainsString('37766687136', $document);
            self::assertStringContainsString('37766630386', $document);
            self::assertStringContainsString('37701691548', $document);
            self::assertStringContainsString('37764312992', $document);
            self::assertStringContainsString('uat_schvaleno=true', $document);
        }
    }

    public function testDocumentationLinksTheCurrentDataFlowAuditAndPausedFioState(): void
    {
        $root = dirname(__DIR__, 2);
        $readme = (string)file_get_contents($root . '/README.md');
        $docsIndex = (string)file_get_contents($root . '/docs/README.md');
        $fio = (string)file_get_contents($root . '/docs/fio-readonly-import-k4.md');
        $workflow = (string)file_get_contents($root . '/.github/workflows/fio-import-production.yml');
        self::assertStringContainsString('outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_APLIKACE.xlsx', $readme);
        self::assertStringContainsString('../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_APLIKACE.xlsx', $docsIndex);
        self::assertStringContainsString('FIO_IMPORT_ENABLED=false', $fio);
        self::assertStringNotContainsString('schedule:', $workflow);
        self::assertStringContainsString('workflow_dispatch:', $workflow);
    }
}
