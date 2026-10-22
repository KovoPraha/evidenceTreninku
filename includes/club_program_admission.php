<?php
declare(strict_types=1);
require_once __DIR__.'/shop_checkout.php';
require_once __DIR__.'/paper_registration.php';
require_once __DIR__.'/shop_bank_settings.php';

function clubAdmissionActor(PDO $pdo,int $actor):void {
 if(!$pdo->inTransaction())throw new LogicException('Přijetí vyžaduje transakci.');
 $q=$pdo->prepare("SELECT t.id FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='registrar') OR EXISTS(SELECT 1 FROM staff_superadmins s WHERE s.trainer_id=t.id))".shopInventoryLock($pdo));$q->execute([$actor]);
 if(!$q->fetchColumn())throw new DomainException('Přijetí vyžaduje aktuální oprávnění registráře.');
}
/** Paper evidence never impersonates online acceptance by the payer. */
function clubAdmissionPaperEvidence(PDO $pdo,int $person,int $program,array $terms,array $paperIds):array {
 $ids=array_values(array_unique(array_map('intval',$paperIds)));sort($ids);$papers=[];$covered=[];$application=false;
 foreach($ids as$id){$r=paperRegistrationGet($pdo,$id,true);
  if((int)$r['sportovec_id']!==$person||$r['scope_type']!=='program'||(int)$r['scope_id']!==$program||$r['state']!=='accepted'||!in_array($r['signer_relation'],['self','guardian'],true))throw new DomainException('Každý podklad musí být přijatý, patřit této osobě a programu a doložit oprávněného podepisujícího.');
  $papers[]=$r;if($r['kind']==='application')$application=true;if((int)$r['term_version_id']>0)$covered[(int)$r['term_version_id']]=true;
 }
 if(!$application)throw new DomainException('Chybí přijatá papírová přihláška pro tento program.');
 foreach($terms as$t)if(!isset($covered[(int)$t['id']]))throw new DomainException('Chybí přijatý papírový souhlas s aktuální verzí: '.($t['title_plain']??$t['consent_purpose']??'podmínky'));
 return$papers;
}
function clubAdmissionPlace(PDO $pdo,int $actor,array $input):array {
 $person=(int)($input['sportovec_id']??0);$payer=(int)($input['payer_account_id']??0);$offerId=(int)($input['offer_id']??0);$start=clubProgramDate((string)($input['starts_on']??''));$reason=clubProgramText((string)($input['reason']??''),1000,'Důvod přijetí');$key=(string)($input['request_key']??'');
 if(($input['confirmed']??'')!=='1'||min($person,$offerId)<1||$payer<0||preg_match('/^[a-zA-Z0-9_-]{16,100}$/D',$key)!==1)throw new InvalidArgumentException('Přijetí vyžaduje osobu, přesnou nabídku, plátce a potvrzení.');
 $paperIds=array_values(array_unique(array_map('intval',(array)($input['paper_ids']??[]))));sort($paperIds);$source=(int)($input['source_enrollment_id']??0);$resolution=trim((string)($input['financial_resolution']??''));
 $offlineName=trim((string)($input['offline_payer_name']??''));$offlineRelation=(string)($input['offline_payer_relation']??'');if($payer===0&&(mb_strlen($offlineName)<2||mb_strlen($offlineName)>200||!in_array($offlineRelation,['self','guardian'],true)))throw new InvalidArgumentException('U osobního přijetí bez účtu doložte jméno a vztah plátce podle přijaté přihlášky.');
 $fp=hash('sha256',json_encode([$offlineName,$offlineRelation,$person,$payer,$offerId,$start,$paperIds,$source,$resolution,$reason,(int)($input['expected_amount_minor']??-1)],JSON_THROW_ON_ERROR));
 return shopInventoryTransaction($pdo,static function()use($pdo,$actor,$input,$person,$payer,$offerId,$start,$reason,$key,$paperIds,$source,$resolution,$fp,$offlineName,$offlineRelation):array {
  clubAdmissionActor($pdo,$actor);
  $q=$pdo->prepare('SELECT * FROM club_program_admissions WHERE request_key=?'.shopInventoryLock($pdo));$q->execute([$key]);if($row=$q->fetch(PDO::FETCH_ASSOC)){if(!hash_equals($row['request_fingerprint'],$fp))throw new DomainException('Klíč již patří jinému přijetí.');return['id'=>(int)$row['id'],'order_id'=>(int)$row['order_id'],'replayed'=>true];}
  $q=$pdo->prepare('SELECT variant_id FROM club_program_offers WHERE id=?');$q->execute([$offerId]);$variantId=(int)$q->fetchColumn();$variant=shopCheckoutLockVariant($pdo,$variantId);$offer=clubProgramOfferForVariant($pdo,$variantId,null,true);
  if(!$variant||!$offer||(int)$offer['id']!==$offerId||!shopCheckoutVariantIsSaleable($variant,$pdo,null,true))throw new DomainException('Přesná nabídka není dostupná nebo nemá volnou kapacitu.');
  if($start<$offer['starts_on']||$start>$offer['ends_on'])throw new DomainException('Datum vstupu musí ležet v období nabídky.');
  if((int)($input['expected_amount_minor']??-1)!==(int)$variant['amount_minor']||(int)$variant['amount_minor']<1)throw new DomainException('Cena nabídky se změnila. Ověřte znovu dohodnutou cenu; krácení se neodhaduje.');
  clubFinanceAssertCanRegister($pdo,$person);
  if($payer>0){shopBeneficiaryAssertAccessible($pdo,$payer,$person,true);$q=$pdo->prepare('SELECT * FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1'.shopInventoryLock($pdo));$q->execute([$payer]);$account=$q->fetch(PDO::FETCH_ASSOC);if(!$account)throw new DomainException('Plátce nemá ověřený aktivní účet.');}
  else{$q=$pdo->prepare('SELECT id FROM sportovci WHERE id=?'.shopInventoryLock($pdo));$q->execute([$person]);if(!$q->fetchColumn())throw new DomainException('Osoba nebyla ověřena v evidenci.');$account=['jmeno'=>$offlineName,'prijmeni'=>'','email'=>''];}
  $q=$pdo->prepare("SELECT oi.id FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id WHERE oi.club_program_offer_id=? AND oi.beneficiary_sportovec_id=? AND o.status<>'cancelled' AND o.payment_status IN ('pending','paid')".shopInventoryLock($pdo));$q->execute([$offerId,$person]);if($q->fetchColumn())throw new DomainException('Osoba již má objednávku tohoto období. Dořešte původní přihlášku.');
  $terms=clubProgramTermsEffective($pdo,(int)$offer['program_id'],$offerId,true);if(!clubProgramTermsComplete($terms))throw new DomainException('Nabídka nemá schválené podmínky.');
  $required=$terms;foreach(clubConsentCurrent($pdo,false,true)as$t)if((int)$t['custom_required']===1)$required[]=$t;
  $papers=clubAdmissionPaperEvidence($pdo,$person,(int)$offer['program_id'],$required,$paperIds);
  if($payer===0){$matched=false;foreach($papers as$paper)if($paper['kind']==='application'&&$paper['signer_relation']===$offlineRelation&&mb_strtolower(trim($paper['signer_name']))===mb_strtolower($offlineName))$matched=true;if(!$matched)throw new DomainException('Plátce bez účtu musí odpovídat oprávněnému podepisujícímu na přijaté papírové přihlášce.');}
  if($source>0){$q=$pdo->prepare('SELECT e.*,oi.order_id FROM club_program_enrollments e JOIN shop_order_items oi ON oi.id=e.source_order_item_id WHERE e.id=?'.shopInventoryLock($pdo));$q->execute([$source]);$old=$q->fetch(PDO::FETCH_ASSOC);
   if(!$old||(int)$old['sportovec_id']!==$person||$old['status']!=='cancelled'||mb_strlen($resolution)<10||mb_strlen($resolution)>1000)throw new DomainException('Přestup vyžaduje doložené ukončení původní účasti a popis samostatného finančního vyrovnání. Nejdříve vyřešte původní objednávku v agendě plateb.');
  }
  $bank=shopBankValidateSettings(shopBankSettingsEffective($pdo));$unit=(int)$variant['amount_minor'];$currency=(string)$variant['currency'];if($currency!=='CZK')throw new DomainException('Podporována je pouze cena v Kč.');$code='KP'.shopCheckoutPublicCodeDate().strtoupper(bin2hex(random_bytes(5)));$due=shopCheckoutPaymentDueAt((int)$bank['due_days']);
  $pdo->prepare("INSERT INTO shop_orders(public_code,account_id,idempotency_key_hash,status,payment_status,fulfillment_method,customer_name_snapshot,customer_email_snapshot,subtotal_minor,discount_minor,total_minor,currency,placed_at,payment_expires_at,finance_sportovec_id) VALUES(?,?,?,'placed','pending','personal_pickup',?,?,?,0,?,?,CURRENT_TIMESTAMP,?,?)")->execute([$code,$payer?:null,hash('sha256','admission:'.$key),trim($account['jmeno'].' '.$account['prijmeni']),$account['email'],$unit,$unit,$currency,$due,$person]);$order=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,beneficiary_sportovec_id,club_program_offer_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,unit_amount_minor,line_amount_minor,currency,program_terms_snapshot_json,program_terms_accepted_at,program_terms_accepted_by_account_id) VALUES(?,?,?,?,?,?,?,?,1,?,?,?,?,NULL,NULL)')->execute([$order,$variant['product_id'],$variantId,$person,$offerId,$variant['public_name'],$variant['sku'],$variant['attributes_json'],$unit,$unit,$currency,clubProgramTermsSnapshotJson($terms)]);$item=(int)$pdo->lastInsertId();
  $vs=shopOrderAssignVariableSymbol($pdo,$order);$spd=shopPaymentSpdPayload($bank['iban'],$unit,$currency,$vs,'OBJEDNAVKA '.$code);
  $pdo->prepare("INSERT INTO payments(payable_type,payable_id,method,accepted_payment_methods,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) VALUES('shop_order',?,'bank_transfer',?,'pending',?,?,?,?,?,?,?,?)")->execute([$order,SHOP_PAYMENT_POLICY_BANK_ONLY,$unit,$currency,$vs,$bank['iban'],$bank['bic']?:null,$bank['account_label'],$spd,$due]);
  $pdo->prepare('INSERT INTO club_program_admissions(order_id,order_item_id,offer_id,sportovec_id,payer_account_id,starts_on,paper_snapshot_json,request_key,request_fingerprint,reason,actor_id,source_enrollment_id,financial_resolution,payer_name_snapshot,payer_relation_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$order,$item,$offerId,$person,$payer?:null,$start,json_encode($papers,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$key,$fp,$reason,$actor,$source?:null,$resolution?:null,$payer===0?$offlineName:trim($account['jmeno'].' '.$account['prijmeni']),$payer===0?$offlineRelation:null]);$id=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES(?,'trainer',?,'assisted_program_admission',NULL,'placed',?)")->execute([$order,$actor,$reason.' Papírové přijetí #'.$id.'; platba a aktivace čekají na samostatné potvrzení.']);
  return['id'=>$id,'order_id'=>$order,'replayed'=>false];
 });
}
function clubAdmissionForItem(PDO $pdo,int $item,int $person,int $payer):?array {
 if(!clubProgramRuntimeTableExists($pdo,'club_program_admissions'))return null;
 $q=$pdo->prepare('SELECT * FROM club_program_admissions WHERE order_item_id=? AND sportovec_id=? AND COALESCE(payer_account_id,0)=?');$q->execute([$item,$person,$payer]);$row=$q->fetch(PDO::FETCH_ASSOC);return$row?:null;
}
