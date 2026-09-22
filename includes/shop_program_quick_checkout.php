<?php
declare(strict_types=1);

require_once __DIR__.'/shop_guest_checkout.php';
require_once __DIR__.'/athlete_registration.php';
require_once __DIR__.'/one_time_token.php';

final class ShopProgramQuickCheckoutException extends RuntimeException{}

/** @return array<string,string> */
function shopProgramQuickParentValidate(array $input):array
{
    $parent=shopGuestCustomerValidate([
        'first_name'=>$input['parent_first_name']??'',
        'last_name'=>$input['parent_last_name']??'',
        'email'=>$input['email']??'',
        'phone'=>$input['contact_phone']??'',
    ]);
    if(($parent['phone']??null)===null)throw new InvalidArgumentException('Zadejte kontaktní telefon.');
    return array_map(static fn($value):string=>(string)($value??''),$parent);
}

/**
 * Fast first-purchase flow: create an unverified account, a pending athlete
 * request and a capacity-holding program order atomically. Email verification
 * and registrar approval are still required before roster activation.
 *
 * @param array<string,mixed> $input
 * @param array<string,string> $submittedVersions
 * @param array{iban:string,bic:string,account_label:string,due_days:int} $bank
 * @return array<string,mixed>
 */
function shopProgramQuickCheckoutPlace(
    PDO $pdo,int $variantId,array $input,array $submittedVersions,string $idempotencyKey,string $accessToken,array $bank
):array{
    if($variantId<1||preg_match('/^[a-f0-9]{32}$/D',$idempotencyKey)!==1||preg_match('/^[a-f0-9]{64}$/D',$accessToken)!==1){
        throw new InvalidArgumentException('Rychlá přihláška má neplatné vstupní údaje.');
    }
    $parent=shopProgramQuickParentValidate($input);$bank=shopBankValidateSettings($bank);
    if(($input['program_terms_accepted']??'')!=='1')throw new InvalidArgumentException('Potvrďte storno podmínky a souhlas s přihlášením.');
    $keyHash=hash('sha256','quick_program:'.$idempotencyKey);$tokenHash=hash('sha256',$accessToken);$lockName=null;$orderId=0;
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $lockName='shop_quick_program:'.substr($keyHash,0,42);$lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute([$lockName]);
        if((int)$lock->fetchColumn()!==1)throw new ShopCheckoutException('Přihlášku právě zpracovává jiný požadavek. Zkuste to za okamžik.');
    }
    try{
        $pdo->beginTransaction();
        try{
            $existingOrder=$pdo->prepare("SELECT public_code FROM shop_orders WHERE idempotency_key_hash=? AND checkout_mode='quick_program'");$existingOrder->execute([$keyHash]);$existingCode=$existingOrder->fetchColumn();
            if($existingCode!==false){$pdo->commit();return shopGuestOrderByCode($pdo,(string)$existingCode,$accessToken)+['replayed'=>true];}
            $variant=shopCheckoutLockVariant($pdo,$variantId);
            if(!$variant||($variant['offer_type']??null)!=='program'||!shopCheckoutVariantIsSaleable($variant,$pdo,null,true))throw new ShopCheckoutException('Tento kroužek už není možné objednat.');
            $offer=clubProgramOfferForVariant($pdo,$variantId,null,true);if(!$offer)throw new ShopCheckoutException('Termín kroužku není dostupný.');
            $terms=clubProgramTermsEffective($pdo,(int)$offer['program_id'],(int)$offer['id'],true);
            if(!clubProgramTermsComplete($terms))throw new ShopCheckoutException('Kroužek nemá platné schválené podmínky.');
            $submittedProgramVersions=is_array($input['program_term_version']??null)?$input['program_term_version']:[];
            foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose)if(!isset($submittedProgramVersions[$purpose])||!hash_equals((string)$terms[$purpose]['terms_version'],(string)$submittedProgramVersions[$purpose]))throw new ShopCheckoutException('Podmínky kroužku se změnily. Obnovte stránku a potvrďte aktuální znění.');
            $existingAccount=$pdo->prepare('SELECT id FROM verejni_uzivatele WHERE LOWER(email)=? LIMIT 1');$existingAccount->execute([$parent['email']]);
            if($existingAccount->fetchColumn()!==false)throw new ShopProgramQuickCheckoutException('Tento e-mail už patří existujícímu účtu. Přihlaste se a údaje se bezpečně předvyplní.');
            $verification=one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION,86400);
            $pdo->prepare('INSERT INTO verejni_uzivatele(jmeno,prijmeni,email,heslo_hash,telefon,verifikacni_token,verifikacni_token_expires_at,email_overeno,aktivni) VALUES(?,?,?,?,?,?,?,0,1)')
                ->execute([$parent['first_name'],$parent['last_name'],$parent['email'],password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$parent['phone'],$verification['hash'],$verification['expires_at']]);
            $accountId=(int)$pdo->lastInsertId();
            $request=athleteRegistrationSubmit($pdo,$accountId,$input,$submittedVersions,null,null,true,true);$requestId=(int)$request['id'];
            $unit=(int)$variant['amount_minor'];$currency=(string)$variant['currency'];if($unit<1||$currency!=='CZK')throw new ShopCheckoutException('Kroužek má nepodporovanou cenu.');
            $publicCode='KP'.date('ymd').strtoupper(bin2hex(random_bytes(5)));$dueAt=(new DateTimeImmutable('now +'.$bank['due_days'].' days'))->setTime(23,59,59)->format('Y-m-d H:i:s');
            $street=trim((string)($input['address_street']??'').' '.(string)($input['address_house_number']??'').((string)($input['address_orientation_number']??'')!==''?'/'.(string)$input['address_orientation_number']:''));
            $columns='public_code,account_id,source_cart_id,checkout_mode,guest_access_token_hash,idempotency_key_hash,status,payment_status,fulfillment_method,customer_name_snapshot,customer_email_snapshot,customer_phone_snapshot,address_street_snapshot,address_city_snapshot,address_postcode_snapshot,subtotal_minor,discount_minor,total_minor,currency,placed_at';
            $values=[$publicCode,$accountId,null,'quick_program',$tokenHash,$keyHash,trim($parent['first_name'].' '.$parent['last_name']),$parent['email'],$parent['phone'],$street,(string)($input['address_city']??''),(string)($input['address_postcode']??''),$unit,0,$unit,$currency];
            if(shopOrderExpirationAvailable($pdo)){$insert=$pdo->prepare('INSERT INTO shop_orders('.$columns.',payment_expires_at) '."VALUES (?,?,?,?,?,?,'placed','pending','personal_pickup',?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?)");$values[]=$dueAt;}
            else$insert=$pdo->prepare('INSERT INTO shop_orders('.$columns.') '."VALUES (?,?,?,?,?,?,'placed','pending','personal_pickup',?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
            $insert->execute($values);$orderId=(int)$pdo->lastInsertId();$acceptedAt=(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
            $item=$pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,beneficiary_sportovec_id,athlete_registration_request_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,unit_amount_minor,line_amount_minor,currency,includes_vat_snapshot,vat_rate_basis_points_snapshot,program_terms_snapshot_json,program_terms_accepted_at,program_terms_accepted_by_account_id) VALUES(?,?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?)');
            $item->execute([$orderId,(int)$variant['product_id'],$variantId,null,$requestId,(string)$variant['public_name'],(string)$variant['sku'],(string)$variant['attributes_json'],$unit,$unit,$currency,$variant['includes_vat'],$variant['vat_rate_basis_points'],clubProgramTermsSnapshotJson($terms),$acceptedAt,$accountId]);
            $variableSymbol=shopPaymentVariableSymbol($orderId);$spd=shopPaymentSpdPayload($bank['iban'],$unit,$currency,$variableSymbol,'OBJEDNAVKA '.$publicCode);
            if(shopPaymentPolicyColumnExists($pdo,'payments','accepted_payment_methods'))$pdo->prepare("INSERT INTO payments(payable_type,payable_id,method,accepted_payment_methods,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) VALUES('shop_order',?,'bank_transfer',?,'pending',?,?,?,?,?,?,?,?)")
                ->execute([$orderId,SHOP_PAYMENT_POLICY_BANK_ONLY,$unit,$currency,$variableSymbol,$bank['iban'],$bank['bic']!==''?$bank['bic']:null,$bank['account_label'],$spd,$dueAt]);
            else$pdo->prepare("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) VALUES('shop_order',?,'bank_transfer','pending',?,?,?,?,?,?,?,?)")
                ->execute([$orderId,$unit,$currency,$variableSymbol,$bank['iban'],$bank['bic']!==''?$bank['bic']:null,$bank['account_label'],$spd,$dueAt]);
            $ageWarning=clubProgramBirthDateWarning($offer,(string)($input['narozeni']??''));
            $note='Objednávka vytvořena rychlou přihláškou sportovce.'.($ageWarning!==null?' Věk mimo doporučení; nákup povolen.':'');
            $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES(?,'guest',NULL,'place',NULL,'placed',?)")->execute([$orderId,$note]);
            if(shopPaymentNotificationTableExists($pdo,'club_event_notifications')){
                $orderUrl=appUrl('booking/objednavka.php?code='.rawurlencode($publicCode).'&access='.rawurlencode($accessToken));
                $verifyUrl=appUrl('booking/overeni.php').'#token='.rawurlencode($verification['token']).'&redirect='.rawurlencode('objednavka.php?code='.$publicCode.'&access='.$accessToken);
                $subject='Přihláška '.$publicCode.' – platební údaje';
                $body="Dobrý den,\n\npřihláška {$publicCode} byla přijata a místo je dočasně rezervované.\n"
                    .'Kroužek: '.(string)$variant['public_name']."\nČástka: ".number_format($unit/100,2,',',' ')." CZK\nVariabilní symbol: {$variableSymbol}\nSplatnost: {$dueAt}\n\n"
                    ."Objednávka a QR platba:\n{$orderUrl}\n\nOvěření e-mailu pro správu přihlášky:\n{$verifyUrl}\n\nKlub KOVO Praha";
                $pdo->prepare("INSERT INTO club_event_notifications(registration_id,registration_event_id,order_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) VALUES(NULL,NULL,?,'shop_quick_program_order_placed',?,?,?,?)")
                    ->execute([$orderId,$parent['email'],trim($parent['first_name'].' '.$parent['last_name']),$subject,$body]);
            }
            $pdo->commit();return shopGuestOrderByCode($pdo,$publicCode,$accessToken)+['replayed'=>false,'age_warning'=>$ageWarning,'verification_token'=>$verification['token']];
        }catch(Throwable$exception){
            if($pdo->inTransaction())$pdo->rollBack();
            if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException||$exception instanceof ShopProgramQuickCheckoutException||$exception instanceof AthleteRegistrationException||$exception instanceof PersonSensitiveException)throw$exception;
            $reference=shopCheckoutDiagnosticReference($keyHash,$orderId);error_log('shop_program_quick_checkout failed: '.$reference.' '.shopCheckoutDiagnosticTrace($exception));
            throw new ShopProgramQuickCheckoutException('Přihlášku se nepodařilo vytvořit bez částečného zápisu.',0,$exception);
        }
    }finally{
        if($lockName!==null)try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}catch(Throwable $releaseError){error_log('shop_program_quick_checkout lock release: '.get_class($releaseError));}
    }
}
