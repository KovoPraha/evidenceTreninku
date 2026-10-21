<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

require_once dirname(__DIR__,2).'/includes/shop_packeta_local_demo.php';

final class ShopPacketaLocalDemoTest extends TestCase
{
    public function testExplicitFlagAndEveryLoopbackBoundaryAreRequired():void
    {
        $local=['HTTP_HOST'=>'localhost:80','SERVER_ADDR'=>'127.0.0.1','REMOTE_ADDR'=>'::1'];
        self::assertTrue(\shopPacketaLocalDemoRequestAllowed($local,'localhost',true));
        self::assertFalse(\shopPacketaLocalDemoRequestAllowed($local,'localhost',false));
        self::assertFalse(\shopPacketaLocalDemoRequestAllowed($local,'kis.kovopraha.cz',true));
        foreach(array_keys($local)as$key){
            self::assertFalse(\shopPacketaLocalDemoRequestAllowed(array_replace($local,[$key=>'192.0.2.1']),'localhost',true));
            self::assertFalse(\shopPacketaLocalDemoRequestAllowed(array_diff_key($local,[$key=>true]),'localhost',true));
        }
        self::assertFalse(\shopPacketaLocalDemoRequestAllowed(array_replace($local,['HTTP_HOST'=>'localhost.evil.test']),'localhost',true));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDefaultConfigurationStillRequiresRealWidgetConfiguration():void
    {
        require_once dirname(__DIR__,2).'/includes/shop_packeta.php';
        $_SERVER=['HTTP_HOST'=>'localhost','SERVER_ADDR'=>'127.0.0.1','REMOTE_ADDR'=>'::1'];
        self::assertFalse(\shopPacketaLocalDemoEnabled());
        self::assertFalse(\shopPacketaAvailable(['enabled'=>true,'price_minor'=>7900,'widget_api_key'=>'']));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDemoRendersWithoutCarrierScriptAndResolvesOnlyItsSyntheticPoint():void
    {
        require_once dirname(__DIR__,2).'/includes/shop_ppl_widget.php';
        require_once dirname(__DIR__,2).'/includes/session_security.php';
        define('SHOP_PACKETA_LOCAL_DEMO',true);
        putenv('APP_HOST=localhost');
        $_SERVER=['HTTP_HOST'=>'localhost','SERVER_ADDR'=>'127.0.0.1','REMOTE_ADDR'=>'::1'];
        $settings=['enabled'=>true,'price_minor'=>7900,'widget_api_key'=>''];
        self::assertTrue(\shopPacketaAvailable($settings));
        self::assertSame('999000001',\shopPacketaResolveBox('999000001')['id']);
        try{\shopPacketaResolveBox('12345');self::fail('Unknown demo point must fail closed.');}catch(\ShopPacketaException){}
        ob_start();
        try{\shopPplDeliveryFields('demo-form',['enabled'=>false],12500,'packeta_zbox','','',0,false,$settings);$html=ob_get_contents();}
        finally{ob_end_clean();}
        self::assertStringContainsString('LOCALHOST TEST:',$html);
        self::assertStringContainsString('name="packeta_point_id" class="form-select"',$html);
        self::assertStringNotContainsString('src="https://widget.packeta.com',$html);
        $_SERVER['REMOTE_ADDR']='192.0.2.1';
        self::assertFalse(\shopPacketaLocalDemoEnabled());
        self::assertFalse(\shopPacketaAvailable($settings));
        $this->expectException(\ShopPacketaException::class);
        \shopPacketaResolveBox('999000001');
    }
}
