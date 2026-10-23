<?php
declare(strict_types=1);
require_once __DIR__.'/shop_inventory.php';

function shopReturnLock(PDO $pdo):string {return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
function shopReturnJson(array $value):string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
function shopReturnActor(PDO $pdo,int $actor,string $reason,bool $confirmed,string $key,string $scope='operations'):void {
 if(!$confirmed||mb_strlen(trim($reason))<10||mb_strlen($reason)>1000||!preg_match('/^[A-Za-z0-9:_.-]{8,90}$/D',$key))throw new DomainException('Potvrďte úkon a uveďte interní důvod (10–1000 znaků).');
 $roles=$scope==='finance'?['finance_manager']:($scope==='stock'?['catalog_manager']:['catalog_manager','order_operator']);
 try{shopInventoryAssertActorRoles($pdo,$actor,$roles);}catch(ShopInventoryException $e){throw new DomainException('Nemáte aktuální oprávnění k tomuto úkonu.',0,$e);}
}
function shopReturnOwner(PDO $pdo,int $owner):void {
 $s=$pdo->prepare("SELECT t.id FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code IN ('catalog_manager','order_operator')) OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id))");$s->execute([$owner]);if(!$s->fetchColumn())throw new DomainException('Vyberte aktivní obsluhu odpovědnou za případ.');
}
function shopReturnDate(string $date,bool $futureAllowed=false):void {$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date||(!$futureAllowed&&$date>date('Y-m-d')))throw new DomainException('Zadejte platné datum; skutečný úkon nesmí být v budoucnu.');}
function shopReturnRow(PDO $pdo,int $id,bool $lock=false):array {$s=$pdo->prepare('SELECT * FROM shop_return_cases WHERE id=?'.($lock?shopReturnLock($pdo):''));$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Vratkový případ neexistuje.');return $r;}
/** Lock in canonical order: order, sorted variants, then case. */
function shopReturnLockContext(PDO $pdo,array $case,array $extraVariants=[]):void {
 $s=$pdo->prepare('SELECT id FROM shop_orders WHERE id=?'.shopReturnLock($pdo));$s->execute([(int)$case['order_id']]);if(!$s->fetchColumn())throw new DomainException('Původní objednávka neexistuje.');
 $ids=array_unique([(int)$case['variant_id'],...(isset($case['exchange_variant_id'])?[(int)$case['exchange_variant_id']]:[]),...$extraVariants]);sort($ids,SORT_NUMERIC);foreach($ids as $id)if($id>0)shopInventoryLockBalance($pdo,$id);
}
function shopReturnEvent(PDO $pdo,int $id,string $event,int $actor,string $reason,string $key,string $hash,string $on):array {
 $row=shopReturnRow($pdo,$id);$pdo->prepare('INSERT INTO shop_return_events(case_id,event_type,actor_id,reason,request_key,input_hash,snapshot_json,occurred_on) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$event,$actor,trim($reason),$key,$hash,shopReturnJson($row),$on]);return $row;
}
function shopReturnReplay(PDO $pdo,string $key,string $hash):?array {
 $s=$pdo->prepare('SELECT * FROM shop_return_events WHERE request_key=?');$s->execute([$key]);$event=$s->fetch(PDO::FETCH_ASSOC);if(!$event)return null;if(!hash_equals($event['input_hash'],$hash))throw new DomainException('Identifikátor požadavku patří jinému úkonu.');return json_decode($event['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
}
function shopReturnChange(PDO $pdo,int $id,int $revision,string $action,array $input,int $actor,string $reason,bool $confirmed,string $key,callable $operation,string $scope='operations'):array {
 return shopInventoryTransaction($pdo,static function()use($pdo,$id,$revision,$action,$input,$actor,$reason,$confirmed,$key,$operation,$scope):array {
  $pdo->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');shopReturnActor($pdo,$actor,$reason,$confirmed,$key,$scope);$hash=hash('sha256',shopReturnJson([$id,$revision,$action,$input,trim($reason)]));
  $row=shopReturnRow($pdo,$id);shopReturnLockContext($pdo,$row,isset($input['variant_id'])?[(int)$input['variant_id']]:[]);$row=shopReturnRow($pdo,$id,true);
  if($old=shopReturnReplay($pdo,$key,$hash))return $old;
  if($row['state']!=='open'||(int)$row['revision']!==$revision)throw new DomainException('Případ je uzavřený nebo se změnil. Obnovte stránku.');
  $on=(string)($input['occurred_on']??date('Y-m-d'));shopReturnDate($on);$q=$pdo->prepare('SELECT MAX(occurred_on) FROM shop_return_events WHERE case_id=?');$q->execute([$id]);if($on<(string)$q->fetchColumn())throw new DomainException('Datum úkonu nesmí předcházet již doloženému kroku případu.');$operation($row,$on);
  $pdo->prepare('UPDATE shop_return_cases SET revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);return shopReturnEvent($pdo,$id,$action,$actor,$reason,$key,$hash,$on);
 });
}
/** A request only holds returnable quantity; it does not receive goods or accept entitlement. */
function shopReturnOpen(PDO $pdo,array $input,int $actor,string $reason,bool $confirmed):array {
 return shopInventoryTransaction($pdo,static function()use($pdo,$input,$actor,$reason,$confirmed):array {
  $pdo->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');$key=(string)($input['request_key']??'');shopReturnActor($pdo,$actor,$reason,$confirmed,$key);
  $issueId=(int)($input['issue_movement_id']??0);$qty=$input['quantity']??0;$type=(string)($input['request_type']??'');$owner=(int)($input['owner_trainer_id']??0);$due=(string)($input['next_due_on']??'');$on=(string)($input['occurred_on']??'');
  if(!is_int($qty)||$qty<1||!in_array($type,['withdrawal','voluntary_exchange','claim'],true))throw new DomainException('Vyberte druh požadavku a kladné celé množství.');shopReturnDate($due,true);shopReturnDate($on);shopReturnOwner($pdo,$owner);
  $contact=trim((string)($input['contact_path']??''));$request=trim((string)($input['customer_request']??''));$policy=trim((string)($input['policy_evidence']??''));
  if($contact===''||mb_strlen($contact)>300||$request===''||mb_strlen($request)>4000||mb_strlen($policy)<5||mb_strlen($policy)>4000)throw new DomainException('Vyplňte kontakt, požadované řešení a použitou metodiku. Důvod zákazníkova odstoupení se nevyžaduje.');
  $hash=hash('sha256',shopReturnJson([$issueId,$qty,$type,$owner,$due,$on,$contact,$request,$policy,trim($reason)]));
  $issue=shopReturnIssueSource($pdo,$issueId);
  if(!$issue||(int)$issue['physical_delta']>=0||$issue['kind']!=='issue')throw new DomainException('Vyberte doložený fyzický výdej objednávky nebo výměny.');
  shopReturnLockContext($pdo,$issue);if($old=shopReturnReplay($pdo,$key,$hash))return $old;
  if($on<$issue['effective_on'])throw new DomainException('Požadavek nemůže předcházet původnímu výdeji.');
  $s=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM shop_return_cases WHERE issue_movement_id=? AND state<>'cancelled' AND NOT(state='closed' AND physical_state='custody_returned')");$s->execute([$issueId]);if((int)$s->fetchColumn()+$qty>-(int)$issue['physical_delta'])throw new DomainException('Množství překračuje vydané kusy po odečtení předchozích vratek a otevřených případů.');
  // Proportional line value preserves discounts; final residual belongs to the last returned piece.
  $sourceCase=(int)($issue['source_exchange_case_id']??0);$s=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) AS q,COALESCE(SUM(original_amount_minor),0) AS a FROM shop_return_cases WHERE order_item_id=? AND ".($sourceCase>0?'source_exchange_case_id=?':'source_exchange_case_id IS NULL')." AND state<>'cancelled' AND NOT(state='closed' AND physical_state='custody_returned')");$s->execute($sourceCase>0?[$issue['order_item_id'],$sourceCase]:[$issue['order_item_id']]);$used=$s->fetch(PDO::FETCH_ASSOC);
  $amount=intdiv((int)$issue['line_amount_minor']*((int)$used['q']+$qty),(int)$issue['ordered_quantity'])-(int)$used['a'];
  $snapshot=['issue_movement_id'=>$issueId,'order_id'=>(int)$issue['order_id'],'order_item_id'=>(int)$issue['order_item_id'],'variant_id'=>(int)$issue['variant_id'],'sku'=>$issue['sku_snapshot'],'label'=>$issue['product_name_snapshot'],'attributes_json'=>$issue['attributes_json_snapshot'],'unit_amount_minor'=>(int)$issue['unit_amount_minor'],'line_amount_minor'=>(int)$issue['line_amount_minor'],'quantity'=>$qty,'return_amount_minor'=>$amount,'coupon_allocation'=>$issue['coupon_allocation']??null];
  $pdo->prepare('INSERT INTO shop_return_cases(issue_movement_id,order_id,order_item_id,variant_id,quantity,request_type,owner_trainer_id,next_due_on,contact_path,customer_request,policy_evidence,original_snapshot_json,original_amount_minor,currency,request_key,input_hash,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$issueId,$issue['order_id'],$issue['order_item_id'],$issue['variant_id'],$qty,$type,$owner,$due,$contact,$request,$policy,shopReturnJson($snapshot),$amount,$issue['currency'],$key,$hash,$actor]);
  $newId=(int)$pdo->lastInsertId();if(!empty($issue['source_exchange_case_id']))$pdo->prepare('UPDATE shop_return_cases SET source_exchange_case_id=? WHERE id=?')->execute([$issue['source_exchange_case_id'],$newId]);
  return shopReturnEvent($pdo,$newId,'opened',$actor,$reason,$key,$hash,$on);
 });
}
function shopReturnReceive(PDO $pdo,int $id,int $revision,string $ownership,string $on,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'received',['ownership'=>$ownership,'occurred_on'=>$on],$actor,$reason,$confirmed,$key,static function(array $r,string $date)use($pdo,$ownership,$actor,$reason,$key):void {
  if(!in_array($ownership,['owned','custody'],true)||$r['physical_state']!=='requested')throw new DomainException('Převzetí je možné jen jednou; vyberte vlastnictví kusu.');
  if($ownership==='custody'&&$r['request_type']!=='claim')throw new DomainException('Cizí věc do péče lze převzít u reklamačního případu.');
  $movement=null;if($ownership==='owned'){$m=shopInventoryReturnInTransaction($pdo,(int)$r['issue_movement_id'],(int)$r['quantity'],$key.':stock',$actor,$reason,['return_case_id'=>(int)$r['id'],'effective_on'=>$date]);$movement=(int)$m['id'];}
  $pdo->prepare('UPDATE shop_return_cases SET physical_state=?,return_movement_id=? WHERE id=?')->execute([$ownership==='owned'?'owned_blocked':'custody',$movement,$r['id']]);
 });
}
function shopReturnDispose(PDO $pdo,int $id,int $revision,string $action,string $on,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'disposition',['disposition'=>$action,'occurred_on'=>$on],$actor,$reason,$confirmed,$key,static function(array $r,string $date)use($pdo,$action,$actor,$reason,$key):void {
  if($r['physical_state']!=='owned_blocked'||$r['disposition']!=='pending'||!in_array($action,['unblock','writeoff'],true))throw new DomainException('Posoudit lze jen převzaté vlastní blokované kusy.');
  shopInventoryDispositionInTransaction($pdo,$actor,(int)$r['variant_id'],(int)$r['quantity'],$action,$reason,$key.':stock',['return_movement_id'=>(int)$r['return_movement_id'],'return_case_id'=>(int)$r['id'],'effective_on'=>$date]);
  $pdo->prepare('UPDATE shop_return_cases SET disposition=? WHERE id=?')->execute([$action==='unblock'?'saleable':'written_off',$r['id']]);
 },'stock');
}
function shopReturnPhysicalResolution(PDO $pdo,int $id,int $revision,string $action,string $on,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'physical_resolution',['resolution'=>$action,'occurred_on'=>$on],$actor,$reason,$confirmed,$key,static function(array $r,string $date)use($pdo,$action,$actor,$reason,$key):void {
  if($action==='custody_returned'&&$r['physical_state']==='custody'){$pdo->prepare("UPDATE shop_return_cases SET physical_state='custody_returned',disposition='not_owned' WHERE id=?")->execute([$r['id']]);return;}
  if($action==='ownership_accepted'&&$r['physical_state']==='custody'){$m=shopInventoryReturnInTransaction($pdo,(int)$r['issue_movement_id'],(int)$r['quantity'],$key.':stock',$actor,$reason,['return_case_id'=>(int)$r['id'],'effective_on'=>$date]);$pdo->prepare("UPDATE shop_return_cases SET physical_state='owned_blocked',return_movement_id=? WHERE id=?")->execute([(int)$m['id'],$r['id']]);return;}
  if($action==='return_waived'&&$r['physical_state']==='requested'){$pdo->prepare("UPDATE shop_return_cases SET physical_state='return_waived',disposition='not_received' WHERE id=?")->execute([$r['id']]);return;}
  throw new DomainException('Tento fyzický přechod není v aktuálním stavu případu možný.');
 });
}
function shopReturnAssign(PDO $pdo,int $id,int $revision,int $owner,string $due,string $contact,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'assigned',['owner'=>$owner,'due'=>$due,'contact'=>$contact],$actor,$reason,$confirmed,$key,static function(array $r)use($pdo,$owner,$due,$contact):void {shopReturnOwner($pdo,$owner);shopReturnDate($due,true);if(trim($contact)===''||mb_strlen($contact)>300)throw new DomainException('Vyplňte kontaktní cestu.');$pdo->prepare('UPDATE shop_return_cases SET owner_trainer_id=?,next_due_on=?,contact_path=? WHERE id=?')->execute([$owner,$due,trim($contact),$r['id']]);});
}

/** Allocate the immutable coupon snapshot by categories and item order; do not read today's catalog rules. */
function shopReturnNetLine(PDO $pdo,array $item):array {
 $gross=(int)$item['line_amount_minor'];$q=$pdo->prepare('SELECT * FROM shop_orders WHERE id=?');$q->execute([$item['order_id']]);$order=$q->fetch(PDO::FETCH_ASSOC);$discount=(int)($order['discount_minor']??0);if($discount===0)return ['net_minor'=>$gross,'gross_minor'=>$gross,'discount_minor'=>0];
 $fail='Chybí jednoznačný původní rozpis slevy. Nejdříve hospodář ověří historické podklady; nelze odhadnout hodnotu vratky.';
 try{$q=$pdo->prepare('SELECT * FROM shop_coupon_redemptions WHERE order_id=?');$q->execute([$item['order_id']]);$coupon=$q->fetch(PDO::FETCH_ASSOC);}catch(PDOException){throw new DomainException($fail);}
 if(!$coupon||(int)$coupon['discount_minor']!==$discount||(int)$coupon['eligible_subtotal_minor']<=0)throw new DomainException($fail);
 $mask=(int)$coupon['applicability_mask_snapshot'];if($mask<1||($mask&~15)!==0)throw new DomainException($fail);if(($mask&1)===0)return ['net_minor'=>$gross,'gross_minor'=>$gross,'discount_minor'=>0,'coupon_redemption_id'=>(int)$coupon['id']];
 $q=$pdo->prepare('SELECT * FROM shop_order_items WHERE order_id=? ORDER BY id');$q->execute([$item['order_id']]);$goods=[];$programs=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$i){if((int)($i['club_program_offer_id']??0)>0||!empty($i['program_terms_snapshot_json']))$programs[]=$i;else$goods[]=$i;}
 $eligible=0;$prefix=0;$found=false;foreach($goods as$i){$n=(int)$i['line_amount_minor'];if((int)$i['id']===(int)$item['id'])$found=true;elseif(!$found)$prefix+=$n;$eligible+=$n;}
 if(!$found)throw new DomainException($fail);if($mask&2)foreach($programs as$i)$eligible+=(int)$i['line_amount_minor'];
 foreach([4=>'club_event_order_items',8=>'public_velodrome_order_items']as$flag=>$table)if($mask&$flag){try{$q=$pdo->prepare('SELECT COALESCE(SUM(line_amount_minor),0) FROM '.$table.' WHERE order_id=?');$q->execute([$item['order_id']]);$eligible+=(int)$q->fetchColumn();}catch(PDOException){throw new DomainException($fail);}}
 if($eligible!==(int)$coupon['eligible_subtotal_minor']||$discount>$eligible||$prefix+$gross>intdiv(PHP_INT_MAX,max(1,$discount)))throw new DomainException($fail);
 $share=intdiv(($prefix+$gross)*$discount,$eligible)-intdiv($prefix*$discount,$eligible);
 return ['net_minor'=>$gross-$share,'gross_minor'=>$gross,'discount_minor'=>$share,'coupon_redemption_id'=>(int)$coupon['id'],'allocation_method'=>'eligible_category_then_item_cumulative_v1'];
}

function shopReturnIssueSource(PDO $pdo,int $issueId):?array {
 $q=$pdo->prepare('SELECT * FROM shop_stock_movements WHERE id=?');$q->execute([$issueId]);$m=$q->fetch(PDO::FETCH_ASSOC);if(!$m)return null;
 if($m['order_item_id']){$q=$pdo->prepare('SELECT * FROM shop_order_items WHERE id=?');$q->execute([$m['order_item_id']]);$i=$q->fetch(PDO::FETCH_ASSOC);if(!$i)return null;$net=shopReturnNetLine($pdo,$i);return $m+['coupon_allocation'=>$net,'product_name_snapshot'=>$i['product_name_snapshot'],'sku_snapshot'=>$i['sku_snapshot'],'attributes_json_snapshot'=>$i['attributes_json_snapshot'],'unit_amount_minor'=>$i['unit_amount_minor'],'line_amount_minor'=>$net['net_minor'],'ordered_quantity'=>$i['quantity'],'currency'=>$i['currency'],'source_exchange_case_id'=>null];}
 $q=$pdo->prepare('SELECT * FROM shop_return_cases WHERE exchange_issue_movement_id=?');$q->execute([$issueId]);$c=$q->fetch(PDO::FETCH_ASSOC);if(!$c||!$c['exchange_snapshot_json'])return null;$s=json_decode($c['exchange_snapshot_json'],true,512,JSON_THROW_ON_ERROR);
 return array_replace($m,['order_id'=>$c['order_id'],'order_item_id'=>$c['order_item_id'],'product_name_snapshot'=>$s['label'],'sku_snapshot'=>$s['sku'],'attributes_json_snapshot'=>$s['attributes_json'],'unit_amount_minor'=>intdiv((int)$s['amount_minor'],(int)$s['quantity']),'line_amount_minor'=>(int)$s['amount_minor'],'ordered_quantity'=>(int)$s['quantity'],'currency'=>$s['currency'],'source_exchange_case_id'=>(int)$c['id']]);
}
function shopReturnCorrectionExpected(array $r):int {
 if($r['request_type']==='voluntary_exchange') {if($r['exchange_delta_minor']===null)throw new DomainException('Nejprve potvrďte variantu a cenu výměny.');return max(0,-(int)$r['exchange_delta_minor']);}
 return $r['physical_state']==='custody_returned'?0:(int)$r['original_amount_minor'];
}
/** Allowed document ancestry is traversed from immutable replacement links, never caller input. */
function shopReturnDocumentSources(PDO $pdo,array $r):array {
 $sources=['order:'.$r['order_id']=>'item:'.$r['order_item_id']];$seen=[];$id=(int)($r['source_exchange_case_id']??0);
 while($id>0){if(isset($seen[$id])||count($seen)>=100)throw new DomainException('Neplatný řetězec výměn.');$seen[$id]=true;$c=shopReturnRow($pdo,$id);$sources['shop_return:'.$id]='exchange:'.$id;$id=(int)($c['source_exchange_case_id']??0);}return $sources;
}
function shopReturnAttachCorrectionInTransaction(PDO $pdo,array $r,int $correctionId,int $refundId,int $amount,int $actor,string $reason):void {
 if($r['finance_state']!=='pending'||$amount<=0)throw new DomainException('Účetní podklady lze připojit jen k neuzavřenému finančnímu řešení a s kladnou částkou.');
 $q=$pdo->prepare("SELECT * FROM accounting_documents WHERE id=? AND status='issued' AND kind='correction'");$q->execute([$correctionId]);$doc=$q->fetch(PDO::FETCH_ASSOC);if(!$doc||$doc['currency']!==$r['currency'])throw new DomainException('Vyberte vydaný opravný doklad ve stejné měně.');
 $sources=shopReturnDocumentSources($pdo,$r);$lineKey=$sources[$doc['source_type'].':'.$doc['source_id']]??null;if(!$lineKey)throw new DomainException('Opravný doklad nepatří k původnímu prodeji ani doloženému řetězci výměn.');
 $line=null;foreach(json_decode($doc['snapshot_json'],true,512,JSON_THROW_ON_ERROR)['lines']??[]as$l)if(($l['line_key']??'')===$lineKey)$line=$l;if(!$line||(int)$line['amount_minor']>=0)throw new DomainException('Oprava neobsahuje správnou vracenou položku.');
 $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM shop_return_finance_links WHERE correction_document_id=? AND line_key=?');$q->execute([$correctionId,$lineKey]);if((int)$q->fetchColumn()+$amount>abs((int)$line['amount_minor']))throw new DomainException('Část opravného dokladu již kryje jiný případ.');
 $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM shop_return_finance_links WHERE case_id=?');$q->execute([$r['id']]);if((int)$q->fetchColumn()+$amount>shopReturnCorrectionExpected($r))throw new DomainException('Opravy překračují hodnotu vrácených kusů nebo rozdíl ceny.');
 if($refundId){$q=$pdo->prepare("SELECT d.*,EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled') AS cancelled FROM accounting_refund_decisions d WHERE id=?");$q->execute([$refundId]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d||$d['cancelled']||(int)$d['correction_document_id']!==$correctionId||(int)$d['amount_minor']>$amount)throw new DomainException('Rozhodnutí o vratce neodpovídá připojené části opravného dokladu.');$q=$pdo->prepare('SELECT COUNT(*) FROM shop_return_finance_links WHERE refund_decision_id=?');$q->execute([$refundId]);if($q->fetchColumn())throw new DomainException('Peněžní rozhodnutí již kryje jiný případ.');}
 $pdo->prepare('INSERT INTO shop_return_finance_links(case_id,correction_document_id,line_key,amount_minor,refund_decision_id,actor_id,reason) VALUES(?,?,?,?,?,?,?)')->execute([$r['id'],$correctionId,$lineKey,$amount,$refundId?:null,$actor,trim($reason)]);
 $pdo->prepare('UPDATE shop_return_cases SET correction_document_id=?,refund_decision_id=? WHERE id=?')->execute([$correctionId,$refundId?:null,$r['id']]);
}
function shopReturnAttachCorrection(PDO $pdo,int $id,int $revision,int $correctionId,int $refundId,int $amount,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'correction_linked',['correction_id'=>$correctionId,'refund_id'=>$refundId,'amount_minor'=>$amount],$actor,$reason,$confirmed,$key,static function(array $r)use($pdo,$correctionId,$refundId,$amount,$actor,$reason):void{shopReturnAttachCorrectionInTransaction($pdo,$r,$correctionId,$refundId,$amount,$actor,$reason);},'finance');
}
/** Finance confirms existing canonical evidence; neither an invoice nor an actual payment is created here. */
function shopReturnFinancialResolution(PDO $pdo,int $id,int $revision,string $resolution,int $correctionId,int $refundId,string $evidence,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'financial_resolution',['resolution'=>$resolution,'correction_id'=>$correctionId,'refund_id'=>$refundId,'evidence'=>$evidence],$actor,$reason,$confirmed,$key,static function(array $r)use($pdo,$resolution,$correctionId,$refundId,$evidence,$actor,$reason):void {
  if(!in_array($resolution,['no_adjustment','adjustment'],true)||mb_strlen(trim($evidence))<10||mb_strlen($evidence)>2000)throw new DomainException('Uveďte ověřené finanční řešení a jeho podklad.');
  if($r['finance_state']!=='pending')throw new DomainException('Finanční vazby již byly potvrzeny.');$expected=shopReturnCorrectionExpected($r);
  $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM shop_return_finance_links WHERE case_id=?');$q->execute([$r['id']]);$linked=(int)$q->fetchColumn();
  if($resolution==='no_adjustment'&&($expected!==0||$correctionId||$refundId||$linked||($r['exchange_delta_minor']!==null&&(int)$r['exchange_delta_minor']!==0)))throw new DomainException('Bez cenové opravy lze uzavřít jen vrácenou cizí věc nebo výměnu bez cenového rozdílu.');
  if($correctionId){shopReturnAttachCorrectionInTransaction($pdo,$r,$correctionId,$refundId,$expected-$linked,$actor,$reason);$linked=$expected;}elseif($refundId)throw new DomainException('Peněžní vratka potřebuje navázaný opravný doklad.');
  if($resolution==='adjustment'&&($linked!==$expected||($expected===0&&(int)($r['exchange_delta_minor']??0)<=0)))throw new DomainException('Připojené opravy musí přesně pokrýt vracenou hodnotu. Doplatek je samostatná povinnost.');
  if($expected>0){$q=$pdo->prepare('SELECT payment_status FROM shop_orders WHERE id=?');$q->execute([$r['order_id']]);$paid=in_array($q->fetchColumn(),['paid','refunded','refund_required'],true);if($paid){$q=$pdo->prepare('SELECT COALESCE(SUM(d.amount_minor),0) FROM shop_return_finance_links l JOIN accounting_refund_decisions d ON d.id=l.refund_decision_id WHERE l.case_id=?');$q->execute([$r['id']]);if((int)$q->fetchColumn()!==$expected)throw new DomainException('Uhrazené vrácené zboží vyžaduje rozhodnutí o peněžní vratce v celé opravované výši.');}}
  $pdo->prepare('UPDATE shop_return_cases SET finance_state=?,finance_evidence=? WHERE id=?')->execute([$resolution,trim($evidence),$r['id']]);
 },'finance');
}
function shopReturnCancel(PDO $pdo,int $id,int $revision,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'cancelled',[],$actor,$reason,$confirmed,$key,static function(array $r)use($pdo,$actor,$reason,$key):void {
  if($r['physical_state']!=='requested'||$r['finance_state']!=='pending'||$r['exchange_issue_movement_id']||$r['correction_document_id'])throw new DomainException('Zrušit lze jen neprovedený požadavek bez fyzického a účetního řešení.');
  $q=$pdo->prepare("SELECT COUNT(*) FROM accounting_documents WHERE source_type='shop_return' AND source_id=? AND (status='issued' OR number IS NOT NULL)");$q->execute([$r['id']]);if($q->fetchColumn())throw new DomainException('Výměna má vydaný nebo již číslovaný doklad; nejprve samostatně vypořádejte účetní povinnost.');$q=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE case_type='shop_return' AND case_id=?");$q->execute([$r['id']]);if((int)$q->fetchColumn()!==0)throw new DomainException('Výměna má přidělené peníze; nejprve vypořádejte úhradu.');
  // Unallocated receipts and released allocations still represent actual customer money.
  // Their cancellation belongs to the financial reversal workflow, not the operator shortcut.
  $q=$pdo->prepare("SELECT COUNT(*) FROM accounting_movements m WHERE m.kind='income' AND m.amount_minor>0 AND ((m.case_type='shop_return' AND m.case_id=?) OR EXISTS(SELECT 1 FROM accounting_allocations a WHERE a.movement_id=m.id AND a.case_type='shop_return' AND a.case_id=? AND a.amount_minor>0))");$q->execute([$r['id'],$r['id']]);if($q->fetchColumn())throw new DomainException('Výměna má doložený finanční příjem; zrušení musí dokončit hospodář po skutečném vypořádání peněz.');
  if($r['exchange_reservation_id'])shopInventoryReleaseExchangeInTransaction($pdo,(int)$r['exchange_reservation_id'],$key.':stock',$actor,$reason);
  $pdo->prepare("UPDATE shop_return_cases SET state='cancelled' WHERE id=?")->execute([$r['id']]);
 });
}
function shopReturnClose(PDO $pdo,int $id,int $revision,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'closed',[],$actor,$reason,$confirmed,$key,static function(array $r)use($pdo):void {
  if(in_array($r['physical_state'],['requested','custody'],true)||$r['disposition']==='pending')throw new DomainException('Nejprve dokončete fyzické převzetí, posouzení nebo vrácení cizí věci.');
  if($r['finance_state']==='pending')throw new DomainException('Hospodář musí potvrdit dokladové a finanční řešení.');
  if($r['request_type']==='voluntary_exchange'&&!$r['exchange_issue_movement_id'])throw new DomainException('Výměna ještě nemá doložený výdej nové varianty.');
  $q=$pdo->prepare('SELECT refund_decision_id FROM shop_return_finance_links WHERE case_id=? AND refund_decision_id IS NOT NULL');$q->execute([$r['id']]);foreach($q->fetchAll(PDO::FETCH_COLUMN)as$decisionId){
   $s=$pdo->prepare("SELECT d.amount_minor,COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0) AS paid,EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled') AS cancelled FROM accounting_refund_decisions d WHERE id=?");$s->execute([$decisionId]);$d=$s->fetch(PDO::FETCH_ASSOC);if(!$d||$d['cancelled']||(int)$d['paid']!==(int)$d['amount_minor'])throw new DomainException('Rozhodnutá peněžní vratka ještě není plně vyplacena.');
  }
  if((int)($r['exchange_delta_minor']??0)>0){$q=$pdo->prepare("SELECT COALESCE(SUM(total_minor),0) FROM accounting_documents WHERE source_type='shop_return' AND source_id=? AND kind='correction' AND status='issued'");$q->execute([$r['id']]);$expected=max(0,(int)$r['exchange_delta_minor']+(int)$q->fetchColumn());if(shopReturnExchangePaidMinor($pdo,(int)$r['id'])!==$expected)throw new DomainException('Doplatek výměny po opravách ještě nemá přesnou skutečnou úhradu.');}
  $pdo->prepare("UPDATE shop_return_cases SET state='closed' WHERE id=?")->execute([$r['id']]);
 });
}
function shopReturnExchangeReserve(PDO $pdo,int $id,int $revision,int $variant,int $replacementAmount,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'exchange_reserved',['variant_id'=>$variant,'replacement_amount_minor'=>$replacementAmount],$actor,$reason,$confirmed,$key,static function(array $r)use($pdo,$variant,$replacementAmount,$actor,$reason,$key):void {
  if($r['request_type']!=='voluntary_exchange'||$r['exchange_reservation_id']||$r['finance_state']!=='pending'||$variant===(int)$r['variant_id']||$replacementAmount<0||$replacementAmount>100000000000)throw new DomainException('Vyberte jinou variantu a dohodnutou celkovou cenu nové výměny.');
  $q=$pdo->prepare("SELECT v.currency,v.sku,v.attributes_json,p.name,p.offer_type FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.id=?");$q->execute([$variant]);$v=$q->fetch(PDO::FETCH_ASSOC);if(!$v||$v['offer_type']!=='goods'||$v['currency']!==$r['currency'])throw new DomainException('Nová varianta musí být zboží ve stejné měně.');
  $reservation=shopInventoryReserveExchangeInTransaction($pdo,(int)$r['id'],$variant,(int)$r['quantity'],$key.':stock',$actor,$reason);
  $pdo->prepare('UPDATE shop_return_cases SET exchange_variant_id=?,exchange_amount_minor=?,exchange_delta_minor=?,exchange_reservation_id=?,exchange_snapshot_json=? WHERE id=?')->execute([$variant,$replacementAmount,$replacementAmount-(int)$r['original_amount_minor'],(int)$reservation['id'],shopReturnJson(['label'=>$v['name'],'sku'=>$v['sku'],'attributes_json'=>$v['attributes_json'],'currency'=>$v['currency'],'amount_minor'=>$replacementAmount,'quantity'=>(int)$r['quantity']]),$r['id']]);
 });
}
function shopReturnExchangeIssue(PDO $pdo,int $id,int $revision,string $recipient,string $on,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'exchange_issued',['recipient'=>$recipient,'occurred_on'=>$on],$actor,$reason,$confirmed,$key,static function(array $r,string $date)use($pdo,$recipient,$actor,$reason,$key):void {
  if(!$r['exchange_reservation_id']||$r['exchange_issue_movement_id']||$r['physical_state']!=='owned_blocked'||$r['finance_state']==='pending'||mb_strlen(trim($recipient))<2||mb_strlen($recipient)>200)throw new DomainException('Výměna vyžaduje převzetí původního kusu do vlastnictví, rezervaci, potvrzenou cenu a příjemce.');
  if((int)$r['exchange_delta_minor']>0){if(shopReturnExchangePaidMinor($pdo,(int)$r['id'])!==(int)$r['exchange_delta_minor'])throw new DomainException('Nový kus lze vydat po doložené úhradě doplatku.');}
  $m=shopInventoryIssueExchangeInTransaction($pdo,(int)$r['exchange_reservation_id'],(int)$r['quantity'],$key.':stock',$actor,$reason,['return_case_id'=>(int)$r['id'],'recipient'=>trim($recipient),'effective_on'=>$date,'original_order_id'=>(int)$r['order_id'],'original_order_item_id'=>(int)$r['order_item_id']]);
  $pdo->prepare('UPDATE shop_return_cases SET exchange_issue_movement_id=? WHERE id=?')->execute([(int)$m['id'],$r['id']]);
 });
}
/** Only real positive income can fund an exchange; legacy/corrupt allocations cannot manufacture payment. */
function shopReturnExchangePaidMinor(PDO $pdo,int $caseId):int {
 $q=$pdo->prepare("SELECT m.id,m.amount_minor,SUM(a.amount_minor) AS own_minor FROM accounting_allocations a JOIN accounting_movements m ON m.id=a.movement_id WHERE a.case_type='shop_return' AND a.case_id=? AND m.kind='income' AND m.amount_minor>0 GROUP BY m.id,m.amount_minor");$q->execute([$caseId]);$paid=0;
 foreach($q->fetchAll(PDO::FETCH_ASSOC)as$m){$s=$pdo->prepare('SELECT COALESCE(SUM(-amount_minor),0) FROM accounting_movements WHERE reversal_of_id=? AND amount_minor<0');$s->execute([$m['id']]);$cash=(int)$m['amount_minor']-(int)$s->fetchColumn();$s=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE movement_id=? AND NOT(case_type='shop_return' AND case_id=?)");$s->execute([$m['id'],$caseId]);$cash-=(int)$s->fetchColumn();$paid+=min(max(0,(int)$m['own_minor']),max(0,$cash));}return$paid;
}
/** Cancelling an already invoiced supplement requires completed canonical financial reversals first. */
function shopReturnCancelAfterFinance(PDO $pdo,int $id,int $revision,int $actor,string $reason,bool $confirmed,string $key):array {
 return shopReturnChange($pdo,$id,$revision,'cancelled_after_finance',[],$actor,$reason,$confirmed,$key,static function(array$r)use($pdo,$actor,$reason,$key):void {
  if($r['request_type']!=='voluntary_exchange'||$r['physical_state']!=='requested'||$r['exchange_issue_movement_id']||!$r['exchange_reservation_id']||(int)$r['exchange_delta_minor']<=0||$r['correction_document_id'])throw new DomainException('Toto zrušení je určeno jen neprovedené výměně s kladným doplatkem; původní kus zůstává zákazníkovi.');
  $q=$pdo->prepare("SELECT * FROM accounting_documents WHERE source_type='shop_return' AND source_id=? AND (status='issued' OR number IS NOT NULL)");$q->execute([$r['id']]);$docs=$q->fetchAll(PDO::FETCH_ASSOC);$principal=0;$corrections=0;
  foreach($docs as$d){if($d['status']!=='issued')throw new DomainException('Nejprve dokončete již číslované účetní doklady.');if($d['kind']==='correction')$corrections+=(int)$d['total_minor'];elseif($d['kind']==='invoice')$principal+=(int)$d['total_minor'];}
  if($principal!==(int)$r['exchange_delta_minor']||$principal+$corrections!==0)throw new DomainException('Vydaný doplatek musí být celý opraven kanonickým opravným dokladem.');
  $q=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE case_type='shop_return' AND case_id=?");$q->execute([$r['id']]);if((int)$q->fetchColumn()!==0)throw new DomainException('Nejprve doloženě uvolněte všechny příděly doplatku.');
  $q=$pdo->prepare("SELECT m.* FROM accounting_movements m WHERE m.amount_minor>0 AND m.kind='income' AND ((m.case_type='shop_return' AND m.case_id=?) OR EXISTS(SELECT 1 FROM accounting_allocations a WHERE a.movement_id=m.id AND a.case_type='shop_return' AND a.case_id=? AND a.amount_minor>0))");$q->execute([$r['id'],$r['id']]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC)as$m){$s=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE movement_id=? AND case_type='shop_return' AND case_id=? AND amount_minor>0");$s->execute([$m['id'],$r['id']]);$received=$m['case_type']==='shop_return'&&(int)$m['case_id']===(int)$r['id']?(int)$m['amount_minor']:min((int)$r['exchange_delta_minor'],(int)$s->fetchColumn());
   $s=$pdo->prepare("SELECT COALESCE(SUM(p.amount_minor),0) FROM accounting_refund_payments p JOIN accounting_refund_decisions d ON d.id=p.decision_id JOIN accounting_documents c ON c.id=d.correction_document_id WHERE d.original_movement_id=? AND c.source_type='shop_return' AND c.source_id=? AND c.status='issued'");$s->execute([$m['id'],$r['id']]);if((int)$s->fetchColumn()!==$received)throw new DomainException('Přijatý doplatek ještě není skutečně vrácen podle příslušných opravných dokladů.');
  }
  $q=$pdo->prepare("SELECT COUNT(*) FROM accounting_refund_decisions d JOIN accounting_documents c ON c.id=d.correction_document_id WHERE c.source_type='shop_return' AND c.source_id=? AND NOT EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled') AND d.amount_minor<>COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0)");$q->execute([$r['id']]);if($q->fetchColumn())throw new DomainException('Nejprve dokončete všechna platná rozhodnutí o vrácení doplatku.');
  $reservation=shopInventoryExchangeReservation($pdo,(int)$r['exchange_reservation_id']);$balance=shopInventoryLockBalance($pdo,(int)$reservation['variant_id']);shopInventoryReleaseReservation($pdo,$balance,$reservation,$key.':stock',$actor,$reason);
  $pdo->prepare("UPDATE shop_return_cases SET state='cancelled',finance_state='adjustment',finance_evidence=? WHERE id=?")->execute(['Doplatek plně opraven; přijaté peníze doloženě vráceny. '.trim($reason),$r['id']]);
 },'finance');
}
