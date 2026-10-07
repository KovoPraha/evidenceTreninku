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
            self::assertStringContainsString('f5619222402436d7a00e7e720318886bcaffb97a', $document);
            self::assertStringContainsString('49c38c72c6704826182aff7f59855f47b5818bb8', $document);
            self::assertStringContainsString('37636084395', $document);
            self::assertStringContainsString('37636447561', $document);
            self::assertStringContainsString('37636671525', $document);
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
