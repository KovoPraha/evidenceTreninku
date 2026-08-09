<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StripeWiringTest extends TestCase
{
    public function testWebhookAndCheckoutRemainBehindFailClosedFlag():void
    {
        $config=$this->source('config.example.php');$order=$this->source('booking/objednavka.php');$webhook=$this->source('booking/stripe_webhook.php');
        self::assertStringContainsString("define('STRIPE_ENABLED', is_string(\$stripeEnabled) && \$stripeEnabled === '1')",$config);
        self::assertStringContainsString('stripeIsEnabled()&&',$order);self::assertStringContainsString('Zaplatit kartou',$order);
        self::assertStringContainsString("REQUEST_METHOD']??'GET')!=='POST'",$webhook);self::assertStringContainsString("header('Cache-Control: no-store",$webhook);self::assertStringContainsString('HTTP_STRIPE_SIGNATURE',$webhook);
    }

    private function source(string $relative):string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$relative);self::assertIsString($source);return $source;
    }
}
