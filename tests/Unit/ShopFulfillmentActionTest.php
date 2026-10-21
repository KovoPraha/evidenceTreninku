<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/shop_order_customer_status.php';

final class ShopFulfillmentActionTest extends TestCase
{
    public function testCarriersNeverReceiveThePersonalPickupAction():void
    {
        foreach(['personal_pickup'=>'complete','ppl_box'=>'dispatch_ppl','packeta_zbox'=>'prepare_packeta'] as $method=>$expected) {
            $order=['status'=>'ready','fulfillment_method'=>$method];
            self::assertSame($expected,\shopOrderFulfillmentAction($order,false));
            self::assertNull(\shopOrderFulfillmentAction($order,true));
        }
        self::assertSame('deposit_packeta',\shopOrderFulfillmentAction(['status'=>'ready','fulfillment_method'=>'packeta_zbox','packeta_consignment_code'=>'123456789'],false));
        foreach(['placed','completed','cancelled'] as $status) self::assertNull(\shopOrderFulfillmentAction(['status'=>$status,'fulfillment_method'=>'packeta_zbox'],false));
        self::assertNull(\shopOrderFulfillmentAction(['status'=>'ready','fulfillment_method'=>'unknown'],false));
    }

    public function testCustomLabelCannotHideTheActualOrderLifecycle():void
    {
        $order=['status'=>'placed','customer_status_label'=>'Vyřízeno'];
        self::assertSame('Vytvořená objednávka',\shopOrderSystemStatusLabel($order));
        $order['status']='processing';self::assertSame('Připravuje se',\shopOrderSystemStatusLabel($order));
        $order['status']='completed';$order['fulfillment_method']='packeta_zbox';self::assertSame('Podáno do Z-BOXu',\shopOrderSystemStatusLabel($order));
    }
}
