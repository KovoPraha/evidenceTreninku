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
require_once dirname(__DIR__).'/includes/webhook_request.php';

if(!stripeIsEnabled()){
    http_response_code(404);echo '{"ok":false}';exit;
}

$signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
try{
    $declared=isset($_SERVER['CONTENT_LENGTH'])&&ctype_digit((string)$_SERVER['CONTENT_LENGTH'])?(int)$_SERVER['CONTENT_LENGTH']:null;
    $payload=webhookReadBoundedBody('php://input',262144,$declared);
    $client=new StripeSdkGatewayClient((string)STRIPE_SECRET_KEY);
    $result=stripeHandleWebhook($pdo,$payload,$signature,$client);
    http_response_code(200);echo json_encode(['ok'=>true,'status'=>$result['status']],JSON_THROW_ON_ERROR);
}catch(WebhookRequestTooLargeException $exception){
    error_log('stripe_webhook: rejected oversized body');http_response_code(413);echo '{"ok":false}';
}catch(StripeWebhookSignatureException $exception){
    error_log('stripe_webhook: rejected signature');http_response_code(400);echo '{"ok":false}';
}catch(StripeGatewayDisabledException $exception){
    http_response_code(404);echo '{"ok":false}';
}catch(Throwable $exception){
    error_log('stripe_webhook: '.$exception->getMessage());http_response_code(500);echo '{"ok":false}';
}
