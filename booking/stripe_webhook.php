<?php
declare(strict_types=1);

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    header('Allow: POST');http_response_code(405);echo '{"ok":false}';exit;
}

require_once dirname(__DIR__).'/db.php';
require_once dirname(__DIR__).'/includes/stripe_gateway.php';

if(!stripeIsEnabled()){
    http_response_code(404);echo '{"ok":false}';exit;
}

$payload=file_get_contents('php://input');
$signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
try{
    $client=new StripeSdkGatewayClient((string)STRIPE_SECRET_KEY);
    $result=stripeHandleWebhook($pdo,is_string($payload)?$payload:'',$signature,$client);
    http_response_code(200);echo json_encode(['ok'=>true,'status'=>$result['status']],JSON_THROW_ON_ERROR);
}catch(StripeWebhookSignatureException $exception){
    error_log('stripe_webhook: rejected signature');http_response_code(400);echo '{"ok":false}';
}catch(StripeGatewayDisabledException $exception){
    http_response_code(404);echo '{"ok":false}';
}catch(Throwable $exception){
    error_log('stripe_webhook: '.$exception->getMessage());http_response_code(500);echo '{"ok":false}';
}
