<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';

class StripeGatewayException extends RuntimeException
{
}

final class StripeWebhookSignatureException extends StripeGatewayException
{
}

final class StripeGatewayDisabledException extends StripeGatewayException
{
}

interface StripeGatewayClient
{
    /** @param array<string,mixed> $parameters @return array<string,mixed> */
    public function createCheckoutSession(array $parameters, string $idempotencyKey): array;

    /** @return array<string,mixed> */
    public function constructWebhookEvent(string $payload, string $signature, string $secret): array;
}

final class StripeSdkGatewayClient implements StripeGatewayClient
{
    private object $client;

    public function __construct(string $secretKey)
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) throw new StripeGatewayException('Stripe SDK není dostupné.');
        require_once $autoload;
        $this->client = new \Stripe\StripeClient($secretKey);
    }

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        $session = $this->client->checkout->sessions->create($parameters, ['idempotency_key' => $idempotencyKey]);
        return $session->toArray();
    }

    public function constructWebhookEvent(string $payload, string $signature, string $secret): array
    {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $exception) {
            throw new StripeWebhookSignatureException('Stripe podpis není platný.', 0, $exception);
        }
        return $event->toArray();
    }
}

/** @return array{enabled:bool,secret_key:string,publishable_key:string,webhook_secret:string,base_url:string} */
function stripeSettingsFromConfig(): array
{
    return [
        'enabled' => defined('STRIPE_ENABLED') && STRIPE_ENABLED === true,
        'secret_key' => defined('STRIPE_SECRET_KEY') ? trim((string)STRIPE_SECRET_KEY) : '',
        'publishable_key' => defined('STRIPE_PUBLISHABLE_KEY') ? trim((string)STRIPE_PUBLISHABLE_KEY) : '',
        'webhook_secret' => defined('STRIPE_WEBHOOK_SECRET') ? trim((string)STRIPE_WEBHOOK_SECRET) : '',
        'base_url' => defined('APP_BASE_URL') ? rtrim((string)APP_BASE_URL, '/') : '',
    ];
}

/** @param array<string,mixed>|null $settings */
function stripeIsEnabled(?array $settings = null): bool
{
    $settings ??= stripeSettingsFromConfig();
    $secretMatches=[];$publishableMatches=[];
    return ($settings['enabled'] ?? false) === true
        && preg_match('/^sk_(test|live)_\S+$/D', (string)($settings['secret_key'] ?? ''),$secretMatches) === 1
        && preg_match('/^pk_(test|live)_\S+$/D', (string)($settings['publishable_key'] ?? ''),$publishableMatches) === 1
        && ($secretMatches[1]??null)===($publishableMatches[1]??null)
        && preg_match('/^whsec_\S+$/D', (string)($settings['webhook_secret'] ?? '')) === 1
        && filter_var((string)($settings['base_url'] ?? ''), FILTER_VALIDATE_URL) !== false;
}

/**
 * Creates a hosted card checkout exclusively from the immutable order/payment snapshot.
 *
 * @param array<string,mixed>|null $settings
 * @return array{id:string,url:string,order_id:int,payment_id:int,amount_total:int,currency:string}
 */
function stripeCreateCheckoutSession(PDO $pdo,int $orderId,int $accountId,StripeGatewayClient $client,?array $settings=null):array
{
    $settings ??= stripeSettingsFromConfig();
    if(!stripeIsEnabled($settings))throw new StripeGatewayDisabledException('Stripe je vypnutý nebo neúplně nakonfigurovaný.');
    if($orderId<1||$accountId<1)throw new InvalidArgumentException('Stripe checkout vyžaduje platnou objednávku a účet.');
    $statement=$pdo->prepare("SELECT o.id,o.public_code,o.account_id,o.status,o.payment_status,o.total_minor,o.currency,p.id AS payment_id,p.payable_type,p.payable_id,p.status AS payment_record_status,p.amount_minor,p.currency AS payment_currency,p.stripe_checkout_session_id FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE o.id=? AND o.account_id=?");
    $statement->execute([$orderId,$accountId]);$snapshot=$statement->fetch(PDO::FETCH_ASSOC);
    stripeAssertPendingOrderSnapshot($snapshot);
    $publicCode=rawurlencode((string)$snapshot['public_code']);$base=rtrim((string)$settings['base_url'],'/');
    $parameters=[
        'mode'=>'payment',
        'client_reference_id'=>(string)$snapshot['public_code'],
        'success_url'=>$base.'/booking/objednavka.php?code='.$publicCode.'&stripe=success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'=>$base.'/booking/objednavka.php?code='.$publicCode.'&stripe=cancelled',
        'line_items'=>[0=>[
            'quantity'=>1,
            'price_data'=>[
                'currency'=>strtolower((string)$snapshot['currency']),
                'unit_amount'=>(int)$snapshot['total_minor'],
                'product_data'=>['name'=>'Objednávka '.(string)$snapshot['public_code']],
            ],
        ]],
        'metadata'=>[
            'shop_order_id'=>(string)$snapshot['id'],
            'payment_id'=>(string)$snapshot['payment_id'],
            'public_code'=>(string)$snapshot['public_code'],
        ],
    ];
    $session=$client->createCheckoutSession($parameters,'shop-order-'.$snapshot['id'].'-payment-'.$snapshot['payment_id']);
    $sessionId=(string)($session['id']??'');$url=(string)($session['url']??'');
    if(preg_match('/^cs_(?:test_|live_)?[A-Za-z0-9_]+$/D',$sessionId)!==1||filter_var($url,FILTER_VALIDATE_URL)===false)throw new StripeGatewayException('Stripe vrátil neplatnou Checkout Session.');
    $pdo->beginTransaction();
    try{
        $lockSql="SELECT o.id,o.account_id,o.status,o.payment_status,o.total_minor,o.currency,p.id AS payment_id,p.payable_type,p.payable_id,p.status AS payment_record_status,p.amount_minor,p.currency AS payment_currency,p.stripe_checkout_session_id FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE o.id=? AND o.account_id=?";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$lockSql.=' FOR UPDATE';
        $lock=$pdo->prepare($lockSql);$lock->execute([$orderId,$accountId]);$current=$lock->fetch(PDO::FETCH_ASSOC);
        stripeAssertPendingOrderSnapshot($current);
        if((int)$current['total_minor']!==(int)$snapshot['total_minor']||(string)$current['currency']!==(string)$snapshot['currency'])throw new StripeGatewayException('Snapshot objednávky se během Stripe checkoutu změnil.');
        $stored=(string)($current['stripe_checkout_session_id']??'');
        if($stored!==''&&!hash_equals($stored,$sessionId))throw new StripeGatewayException('Objednávka už má jinou Stripe Checkout Session.');
        if($stored==='')$pdo->prepare('UPDATE payments SET stripe_checkout_session_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$sessionId,(int)$current['payment_id']]);
        $pdo->commit();
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof StripeGatewayException||$exception instanceof ShopCheckoutException)throw $exception;throw new StripeGatewayException('Stripe Checkout Session se nepodařilo bezpečně navázat.',0,$exception);}
    return ['id'=>$sessionId,'url'=>$url,'order_id'=>$orderId,'payment_id'=>(int)$snapshot['payment_id'],'amount_total'=>(int)$snapshot['total_minor'],'currency'=>(string)$snapshot['currency']];
}

/** @param array<string,mixed>|false $snapshot */
function stripeAssertPendingOrderSnapshot(array|false $snapshot):void
{
    if(!$snapshot||$snapshot['payable_type']!=='shop_order'||(int)$snapshot['payable_id']!==(int)$snapshot['id'])throw new StripeGatewayException('Objednávka nebo její platba nebyla nalezena.');
    if($snapshot['status']!=='placed'||$snapshot['payment_status']!=='pending'||$snapshot['payment_record_status']!=='pending')throw new StripeGatewayException('Stripe Session lze vytvořit pouze pro čekající objednávku.');
    if((int)$snapshot['total_minor']<1||(int)$snapshot['amount_minor']!==(int)$snapshot['total_minor']||(string)$snapshot['currency']!==(string)$snapshot['payment_currency']||preg_match('/^[A-Z]{3}$/D',(string)$snapshot['currency'])!==1)throw new StripeGatewayException('Částka nebo měna platebního snapshotu není konzistentní.');
}

/**
 * Verifies and processes one Stripe webhook. No raw payload is persisted.
 *
 * @param array<string,mixed>|null $settings
 * @return array{status:string,event_id:string,event_type:string,duplicate:bool,changed:bool}
 */
function stripeHandleWebhook(PDO $pdo,string $payload,string $signature,StripeGatewayClient $client,?array $settings=null):array
{
    $settings ??= stripeSettingsFromConfig();
    if(!stripeIsEnabled($settings))throw new StripeGatewayDisabledException('Stripe je vypnutý nebo neúplně nakonfigurovaný.');
    if($payload===''||$signature==='')throw new StripeWebhookSignatureException('Stripe podpis nebo tělo požadavku chybí.');
    $event=$client->constructWebhookEvent($payload,$signature,(string)$settings['webhook_secret']);
    $eventId=(string)($event['id']??'');$eventType=(string)($event['type']??'');
    if(preg_match('/^evt_[A-Za-z0-9_]+$/D',$eventId)!==1||$eventType==='')throw new StripeGatewayException('Stripe event nemá platnou identitu.');
    $pdo->beginTransaction();
    try{
        try{$pdo->prepare("INSERT INTO stripe_webhook_events(event_id,event_type,payload_sha256,processing_status) VALUES (?,?,?,'processing')")->execute([$eventId,$eventType,hash('sha256',$payload)]);}
        catch(PDOException $exception){
            if((string)$exception->getCode()!=='23000')throw $exception;
            $pdo->rollBack();
            return ['status'=>'duplicate','event_id'=>$eventId,'event_type'=>$eventType,'duplicate'=>true,'changed'=>false];
        }
        if($eventType!=='checkout.session.completed'){
            $pdo->prepare("UPDATE stripe_webhook_events SET processing_status='ignored',processed_at=CURRENT_TIMESTAMP WHERE event_id=?")->execute([$eventId]);
            $pdo->commit();error_log('stripe_webhook: ignored event type '.$eventType);
            return ['status'=>'ignored','event_id'=>$eventId,'event_type'=>$eventType,'duplicate'=>false,'changed'=>false];
        }
        $object=$event['data']['object']??null;
        if(!is_array($object))throw new StripeGatewayException('Stripe Checkout Session v eventu chybí.');
        $sessionId=(string)($object['id']??'');$metadata=is_array($object['metadata']??null)?$object['metadata']:[];
        if(preg_match('/^cs_(?:test_|live_)?[A-Za-z0-9_]+$/D',$sessionId)!==1)throw new StripeGatewayException('Stripe Checkout Session nemá platnou identitu.');
        $paymentSql="SELECT p.*,o.id AS order_id,o.status AS order_status,o.payment_status AS order_payment_status,o.total_minor AS order_total_minor,o.currency AS order_currency FROM payments p JOIN shop_orders o ON o.id=p.payable_id AND p.payable_type='shop_order' WHERE p.stripe_checkout_session_id=?";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$paymentSql.=' FOR UPDATE';
        $statement=$pdo->prepare($paymentSql);$statement->execute([$sessionId]);$payment=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$payment)throw new StripeGatewayException('Stripe Session není navázána na žádnou platbu.');
        if((string)($metadata['shop_order_id']??'')!==(string)$payment['order_id']||(string)($metadata['payment_id']??'')!==(string)$payment['id'])throw new StripeGatewayException('Stripe metadata neodpovídají objednávce.');
        if((int)($object['amount_total']??-1)!==(int)$payment['amount_minor']||(int)$payment['amount_minor']!==(int)$payment['order_total_minor']||strtoupper((string)($object['currency']??''))!==(string)$payment['currency']||(string)$payment['currency']!==(string)$payment['order_currency'])throw new StripeGatewayException('Stripe částka nebo měna neodpovídá serverovému snapshotu.');
        if(($object['mode']??'payment')!=='payment'||($object['payment_status']??'')!=='paid'){
            $pdo->prepare("UPDATE stripe_webhook_events SET payment_id=?,processing_status='ignored',processed_at=CURRENT_TIMESTAMP WHERE event_id=?")->execute([(int)$payment['id'],$eventId]);
            $pdo->commit();error_log('stripe_webhook: ignored incomplete checkout session '.$eventId);
            return ['status'=>'ignored','event_id'=>$eventId,'event_type'=>$eventType,'duplicate'=>false,'changed'=>false];
        }
        $paymentIntent=is_string($object['payment_intent']??null)?trim((string)$object['payment_intent']):'';
        if($paymentIntent!=='')$pdo->prepare('UPDATE payments SET stripe_payment_intent_id=? WHERE id=?')->execute([$paymentIntent,(int)$payment['id']]);
        $transition=shopOrderConfirmPaymentInTransaction($pdo,(int)$payment['id'],'stripe','system',null,'Stripe webhook '.$eventId.' potvrdil zaplacenou Checkout Session '.$sessionId.'.');
        $pdo->prepare("UPDATE stripe_webhook_events SET payment_id=?,processing_status='processed',processed_at=CURRENT_TIMESTAMP WHERE event_id=?")->execute([(int)$payment['id'],$eventId]);
        $pdo->commit();
        return ['status'=>'processed','event_id'=>$eventId,'event_type'=>$eventType,'duplicate'=>false,'changed'=>(bool)$transition['changed']];
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof StripeGatewayException||$exception instanceof ShopCheckoutException||$exception instanceof InvalidArgumentException)throw $exception;throw new StripeGatewayException('Stripe webhook selhal bez částečného zápisu.',0,$exception);}
}
