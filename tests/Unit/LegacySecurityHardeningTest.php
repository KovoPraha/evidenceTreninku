<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/funkce.php';

final class LegacySecurityHardeningTest extends TestCase
{
    public function testAuditDetailRedactsNestedSecrets():void
    {
        $detail=\auditSanitizeDetail(['action'=>'save','csrf_token'=>'abc','heslo'=>'secret','nested'=>['authorization'=>'Bearer x','safe'=>'ok']]);
        self::assertStringNotContainsString('secret',$detail);self::assertStringNotContainsString('Bearer x',$detail);self::assertStringNotContainsString('abc',$detail);self::assertStringContainsString('ok',$detail);
    }

    public function testLegacyMutationAndDownloadContractsFailClosed():void
    {
        $root=dirname(__DIR__,2);$login=file_get_contents($root.'/login.php');$story=file_get_contents($root.'/generuj_story.php');$download=file_get_contents($root.'/download_import.php');$htaccess=file_get_contents($root.'/.htaccess');
        self::assertStringContainsString('csrf_verify',$login);self::assertStringContainsString('csrf_field()',$login);
        self::assertStringContainsString("REQUEST_METHOD'] !== 'POST'",$story);self::assertStringContainsString('csrf_verify',$story);self::assertStringNotContainsString("\$_GET['id']",$story);
        self::assertStringContainsString("canAccess('zavod_detail')",$download);self::assertStringContainsString('realpath',$download);
        self::assertStringContainsString('nahrane_obrazky|nahrane_zavody',$htaccess);self::assertStringContainsString('Content-Security-Policy',$htaccess);
    }
}
