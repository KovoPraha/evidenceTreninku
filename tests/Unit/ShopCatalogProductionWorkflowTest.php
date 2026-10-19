<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopCatalogProductionWorkflowTest extends TestCase
{
    public function testOneTimeWorkflowIsManualProtectedAndVerifiesDraftImport():void
    {
        $workflow=(string)file_get_contents(dirname(__DIR__,2).'/.github/workflows/import-shop-catalog-production.yml');
        self::assertStringContainsString('workflow_dispatch:',$workflow);
        self::assertStringNotContainsString('schedule:',$workflow);
        self::assertStringContainsString('environment: production',$workflow);
        self::assertStringContainsString('[[ "$REMOTE_DIR" =~ ^[A-Za-z0-9._/-]+$ ]]',$workflow);
        self::assertStringContainsString("test \"\$POTVRZENI\" = 'IMPORTOVAT-ESHOP-JEDNORAZOVE'",$workflow);
        self::assertStringContainsString(".database_writes == 0 and (.blockers|length) == 0",$workflow);
        self::assertStringContainsString("run-preview.php' || test \\$? -eq 2",$workflow);
        self::assertStringContainsString('.verification.remote_images == 0',$workflow);
        self::assertStringContainsString('.verification.active_products == 0',$workflow);
        self::assertStringContainsString('.verification.active_variants == 0',$workflow);
        self::assertStringContainsString("inputs.mode == 'apply'",$workflow);
        self::assertStringContainsString('Vytvořit a ověřit bezprostřední produkční zálohu',$workflow);
        self::assertStringContainsString('gh release delete "$SOURCE_TAG" --yes --cleanup-tag',$workflow);
        self::assertStringNotContainsString('set -x',$workflow);
    }
}
