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
        self::assertStringContainsString('allowed_active_product_keys:',$workflow);
        self::assertStringContainsString("putenv('SHOPTET_ALLOWED_ACTIVE_KEYS=\$ALLOWED_ACTIVE_KEYS');",$workflow);
        self::assertStringContainsString('.verification.active_products == $active',$workflow);
        self::assertStringContainsString('.verification.active_variants <= .active_imported_variants',$workflow);
        self::assertStringContainsString("inputs.mode == 'apply'",$workflow);
        self::assertStringContainsString('Vytvořit a ověřit bezprostřední produkční zálohu',$workflow);
        self::assertStringContainsString('gh release delete "$SOURCE_TAG" --yes',$workflow);
        self::assertStringNotContainsString('--cleanup-tag',$workflow);
        self::assertStringNotContainsString('production_retry_capture "${SSH[@]}" "php \'$IMPORT_WORK/run-apply.php\'"',$workflow);
        self::assertStringNotContainsString('set -x',$workflow);
        $command=(string)file_get_contents(dirname(__DIR__,2).'/bin/sync-shoptet-catalog-once.php');
        self::assertStringContainsString("set_time_limit(900)",$command);
        self::assertStringContainsString("'failure_type'=>get_debug_type(\$cause)",$command);
    }
}
