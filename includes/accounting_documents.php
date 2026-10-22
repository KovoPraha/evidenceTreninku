<?php
declare(strict_types=1);
require_once __DIR__.'/private_storage.php';

function accountingDocumentRequireFinance(?PDO $pdo=null):int {
 $actor=(int)($_SESSION['trener_id']??0);
 if($actor<1||!function_exists('staffActivePositionIs')||!staffActivePositionIs('finance_manager'))throw new DomainException('Přístup má pouze Hospodář a platby.');
 if($pdo!==null){
  $suffix=$pdo->inTransaction()?accountingDocumentLock($pdo):'';
  $q=$pdo->prepare('SELECT aktivni FROM treneri WHERE id=?'.$suffix);$q->execute([$actor]);if((int)$q->fetchColumn()!==1)throw new DomainException('Pracovník není aktivní.');
  $q=$pdo->prepare("SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'".$suffix);$q->execute([$actor]);$allowed=(bool)$q->fetchColumn();
  $q=$pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?'.$suffix);$q->execute([$actor]);
  if(!$allowed&&!$q->fetchColumn())throw new DomainException('Finanční oprávnění již není platné.');
 }
 return $actor;
}
function accountingDocumentText(mixed $v,int $max=1000,bool $required=true):string {
 if(!is_string($v)||mb_strlen(trim($v))>$max||($required&&trim($v)===''))throw new InvalidArgumentException('Doplňte platné textové údaje.');
 return trim($v);
}
function accountingDocumentDate(mixed $v):string {
 $v=accountingDocumentText($v,10);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
 if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Neplatné datum.');return $v;
}
function accountingDocumentJson(array $v):string {return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
function accountingDocumentLock(PDO $pdo):string {return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
function accountingDocumentEvent(PDO $pdo,int $id,int $actor,string $action,string $reason):void {$pdo->prepare('INSERT INTO accounting_document_events(document_id,actor_id,action,reason) VALUES(?,?,?,?)')->execute([$id,$actor,$action,$reason]);}
function accountingDocumentGet(PDO $pdo,int $id,bool $lock=false):array {
 $q=$pdo->prepare('SELECT * FROM accounting_documents WHERE id=?'.($lock?accountingDocumentLock($pdo):''));$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
 if(!$r)throw new DomainException('Doklad neexistuje.');return $r;
}
function accountingDocumentParty(array $a,bool $required=true):array {
 return ['name'=>accountingDocumentText($a['name']??'',200,$required),'address'=>accountingDocumentText($a['address']??'',500,$required),'registration_id'=>accountingDocumentText($a['registration_id']??'',40,false)];
}
/** New immutable approved version; changing settings never changes old documents. */
function accountingDocumentConfigureTransaction(PDO $pdo,callable $operation):int {
 $pdo->beginTransaction();try{$result=$operation();$pdo->commit();return $result;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accountingDocumentConfigureIssuer(PDO $pdo,array $input):int {
 if(!$pdo->inTransaction())return accountingDocumentConfigureTransaction($pdo,static fn():int=>accountingDocumentConfigureIssuer($pdo,$input));
 $actor=accountingDocumentRequireFinance($pdo);$party=accountingDocumentParty($input);
 $party['registration_id']=accountingDocumentText($input['registration_id']??'',40);
 $party['bank_account']=accountingDocumentText($input['bank_account']??'',80,false);$party['vat_payer']=false;
 if(($input['confirmed']??'')!=='1'||($input['vat_payer']??'0')!=='0')throw new DomainException('Potvrďte údaje vystavovatele v režimu neplátce DPH.');
 $evidence=accountingDocumentText($input['approval_evidence']??'');
 $pdo->prepare('INSERT INTO accounting_issuer_versions(snapshot_json,approved_by,approval_evidence) VALUES(?,?,?)')->execute([accountingDocumentJson($party),$actor,$evidence]);return (int)$pdo->lastInsertId();
}
function accountingDocumentConfigureRule(PDO $pdo,array $input):int {
 if(!$pdo->inTransaction())return accountingDocumentConfigureTransaction($pdo,static fn():int=>accountingDocumentConfigureRule($pdo,$input));
 $actor=accountingDocumentRequireFinance($pdo);$source=(string)($input['source_type']??'');$kind=(string)($input['document_kind']??'');
 if(!in_array($source,['order','member_charge','movement'],true)||!in_array($kind,['invoice','contribution_confirmation','cash_receipt','cash_disbursement'],true)||($input['confirmed']??'')!=='1')throw new InvalidArgumentException('Potvrďte účel a schválenou metodiku dokladu.');
 $args=[accountingDocumentText($input['category_code']??'',40),accountingDocumentText($input['activity']??'',100),accountingDocumentText($input['title']??'',200),$source,$kind,accountingDocumentDate($input['valid_from']??''),$actor,accountingDocumentText($input['approval_evidence']??'')];
 $pdo->prepare('INSERT INTO accounting_rules(category_code,activity,title,source_type,document_kind,valid_from,approved_by,approval_evidence) VALUES(?,?,?,?,?,?,?,?)')->execute($args);return (int)$pdo->lastInsertId();
}
function accountingDocumentConfigureSeries(PDO $pdo,array $input):int {
 if(!$pdo->inTransaction())return accountingDocumentConfigureTransaction($pdo,static fn():int=>accountingDocumentConfigureSeries($pdo,$input));
 $actor=accountingDocumentRequireFinance($pdo);$kind=(string)($input['kind']??'');$year=(int)($input['series_year']??0);$next=(int)($input['next_number']??0);$prefix=accountingDocumentText($input['prefix']??'',20);
 if(!in_array($kind,['invoice','contribution_confirmation','correction','cash_receipt','cash_disbursement'],true)||$year<2000||$year>2200||$next<1||$next>99999999||preg_match('/^[A-Z0-9-]+$/D',$prefix)!==1||($input['confirmed']??'')!=='1')throw new InvalidArgumentException('Neplatná nebo nepotvrzená číselná řada.');
 $pdo->prepare('INSERT INTO accounting_document_series(kind,series_year,prefix,next_number,configured_by,reason) VALUES(?,?,?,?,?,?)')->execute([$kind,$year,$prefix,$next,$actor,accountingDocumentText($input['reason']??'')]);return (int)$pdo->lastInsertId();
}
function accountingDocumentSource(PDO $pdo,string $type,int $id,bool $lock=false):array {
 $suffix=$lock?accountingDocumentLock($pdo):'';
 if($type==='order') {
  $q=$pdo->prepare('SELECT * FROM shop_orders WHERE id=?'.$suffix);$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Objednávka neexistuje.');
  if(in_array($r['status'],['cancelled','expired'],true))throw new DomainException('Zrušenou objednávku nelze nově fakturovat.');
  $q=$pdo->prepare('SELECT i.*,s.jmeno,s.prijmeni FROM shop_order_items i LEFT JOIN sportovci s ON s.id=i.beneficiary_sportovec_id WHERE order_id=? ORDER BY i.id');$q->execute([$id]);$lines=[];$sum=0;
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $i){$amount=(int)$i['line_amount_minor'];$sum+=$amount;$period=null;
   if((int)($i['club_program_offer_id']??0)>0){$periodQuery=$pdo->prepare('SELECT starts_on,ends_on FROM club_program_offers WHERE id=? AND product_id=? AND variant_id=?');$periodQuery->execute([$i['club_program_offer_id'],$i['product_id'],$i['variant_id']]);$offer=$periodQuery->fetch(PDO::FETCH_ASSOC);if(!$offer)throw new DomainException('Chybí původní nabídka kurzu. Nejprve vyřešte období služby.');$period=[$offer['starts_on'],$offer['ends_on']];}
   $lines[]=['line_key'=>'item:'.$i['id'],'label'=>$i['product_name_snapshot'],'quantity'=>(int)$i['quantity'],'amount_minor'=>$amount,'beneficiary'=>['person_id'=>$i['beneficiary_sportovec_id'],'name'=>trim(($i['jmeno']??'').' '.($i['prijmeni']??''))],'service_period'=>$period];}
  foreach(['club_event_order_items'=>['event','event_name_snapshot'],'public_velodrome_order_items'=>['velodrome','lesson_name_snapshot']] as $table=>[$prefix,$label]) {
   $q=$pdo->prepare('SELECT i.*,s.jmeno,s.prijmeni FROM '.$table.' i LEFT JOIN sportovci s ON s.id=i.beneficiary_sportovec_id WHERE order_id=? ORDER BY i.id');$q->execute([$id]);
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $i){$amount=(int)$i['line_amount_minor'];$sum+=$amount;$lines[]=['line_key'=>$prefix.':'.$i['id'],'label'=>$i[$label],'quantity'=>(int)$i['quantity'],'amount_minor'=>$amount,'beneficiary'=>['person_id'=>$i['beneficiary_sportovec_id'],'name'=>trim(($i['jmeno']??'').' '.($i['prijmeni']??''))],'service_period'=>$i['lesson_date_snapshot']??null];}
  }
  $shipping=(int)($r['shipping_amount_minor']??0);$discount=(int)$r['discount_minor'];
  if($shipping>0)$lines[]=['line_key'=>'shipping','label'=>'Doprava','quantity'=>1,'amount_minor'=>$shipping,'beneficiary'=>null];
  if($discount>0)$lines[]=['line_key'=>'discount','label'=>'Sleva objednávky','quantity'=>1,'amount_minor'=>-$discount,'beneficiary'=>null];
  if($sum+$shipping-$discount!==(int)$r['total_minor'])throw new DomainException('Položky, doprava a sleva nesouhlasí s cenou objednávky. Vyřešte výjimku před vystavením.');
  $result=['source_type'=>$type,'source_id'=>$id,'reference'=>$r['public_code'],'total_minor'=>(int)$r['total_minor'],'currency'=>$r['currency'],'buyer_suggestion'=>$r['customer_name_snapshot'],'buyer_account_id'=>$r['account_id']??null,'lines'=>$lines];
 } elseif($type==='member_charge') {
  $q=$pdo->prepare('SELECT c.*,s.jmeno,s.prijmeni FROM club_member_charges c JOIN sportovci s ON s.id=c.sportovec_id WHERE c.id=?'.$suffix);$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||$r['status']==='cancelled')throw new DomainException('Platný předpis neexistuje.');
  $result=['source_type'=>$type,'source_id'=>$id,'reference'=>$r['public_code'],'total_minor'=>(int)$r['amount_minor'],'currency'=>$r['currency'],'buyer_suggestion'=>'','lines'=>[['line_key'=>'charge:'.$id,'label'=>$r['title_snapshot'],'quantity'=>1,'amount_minor'=>(int)$r['amount_minor'],'beneficiary'=>['person_id'=>(int)$r['sportovec_id'],'name'=>trim($r['jmeno'].' '.$r['prijmeni'])],'service_period'=>[$r['period_from'],$r['period_to']]]]];
 } elseif($type==='movement') {
  $q=$pdo->prepare('SELECT m.*,a.kind AS account_kind,a.name AS account_name FROM accounting_movements m JOIN accounting_accounts a ON a.id=m.account_id WHERE m.id=?'.$suffix);$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r||$r['account_kind']!=='cash')throw new DomainException('Pokladní doklad vyžaduje doložený pohyb hotovosti.');
  $amount=abs((int)$r['amount_minor']);$result=['source_type'=>$type,'source_id'=>$id,'reference'=>$r['source_reference'],'total_minor'=>$amount,'currency'=>$r['currency'],'buyer_suggestion'=>'','movement_effective_on'=>$r['effective_on'],'movement_kind'=>(int)$r['amount_minor']>0?'cash_receipt':'cash_disbursement','lines'=>[['line_key'=>'movement:'.$id,'label'=>$r['account_name'].' - '.$r['evidence'],'quantity'=>1,'amount_minor'=>$amount,'beneficiary'=>null]]];
 } else throw new InvalidArgumentException('Nepodporovaný obchodní případ.');
 if($type!=='movement'){$q=$pdo->prepare('SELECT variable_symbol,iban_snapshot,due_at FROM payments WHERE payable_type=? AND payable_id=?');$q->execute([$type==='order'?'shop_order':'member_charge',$id]);$payment=$q->fetch(PDO::FETCH_ASSOC);$result['variable_symbol']=$payment?$payment['variable_symbol']:null;$result['payment_bank_account']=$payment?$payment['iban_snapshot']:null;$result['due_on_suggestion']=$payment?substr($payment['due_at'],0,10):null;}
 if($result['currency']!=='CZK'||$result['total_minor']<1||$result['lines']===[])throw new DomainException('První verze dokladů vyžaduje kladný případ v CZK.');
 return $result;
}
/** Creating a draft takes no document number and cannot create a receivable or money. */
function accountingDocumentCreateDraft(PDO $pdo,array $input):int {
 $actor=accountingDocumentRequireFinance($pdo);$reason=accountingDocumentText($input['reason']??'');
 $type=(string)($input['source_type']??'');$sourceId=(int)($input['source_id']??0);$kind=(string)($input['kind']??'');
 if(!in_array($kind,['invoice','contribution_confirmation','cash_receipt','cash_disbursement'],true))throw new InvalidArgumentException('Vyberte druh dokladu.');
 $effective=accountingDocumentDate($input['effective_on']??'');$due=trim((string)($input['due_on']??''));$due=$due!==''?accountingDocumentDate($due):null;if($kind==='invoice'&&$due===null)throw new DomainException('Faktura vyžaduje výslovně ověřené datum splatnosti.');$buyer=accountingDocumentParty((array)($input['buyer']??[]));$payer=accountingDocumentParty((array)($input['payer']??[]),false);
 $issuerId=(int)($input['issuer_id']??0);$request=accountingDocumentText($input['request_key']??'',120);$rules=(array)($input['line_rules']??[]);
 if($pdo->inTransaction())throw new LogicException('Doklad vyžaduje vlastní transakci.');$pdo->beginTransaction();
 try {
  accountingDocumentRequireFinance($pdo);
  $source=accountingDocumentSource($pdo,$type,$sourceId,true);$unique=$type.':'.$sourceId.':'.$kind;
  if(($type==='movement'&&($kind!==$source['movement_kind']||$effective!==$source['movement_effective_on']))||($type!=='movement'&&str_starts_with($kind,'cash_')))throw new DomainException('Druh a datum pokladního dokladu musí odpovídat skutečnému pohybu.');
  $q=$pdo->prepare('SELECT * FROM accounting_documents WHERE source_unique_key=? OR idempotency_key=?'.accountingDocumentLock($pdo));$q->execute([$unique,$request]);$matches=$q->fetchAll(PDO::FETCH_ASSOC);if(count($matches)>1)throw new DomainException('Klíč požadavku koliduje s jiným dokladem.');$old=$matches[0]??false;$existingId=null;
  if($old){if($old['source_unique_key']!==$unique)throw new DomainException('Klíč požadavku již patří jinému případu.');if($old['status']!=='draft'||$old['idempotency_key']===$request){$pdo->commit();return (int)$old['id'];}$existingId=(int)$old['id'];}
  $sourceHash=hash('sha256',accountingDocumentJson($source));
  $q=$pdo->prepare('SELECT * FROM accounting_issuer_versions WHERE id=?');$q->execute([$issuerId]);$issuer=$q->fetch(PDO::FETCH_ASSOC);if(!$issuer)throw new DomainException('Nejprve schvalte údaje vystavovatele.');
  foreach($source['lines'] as &$line){$ruleId=(int)($rules[$line['line_key']]??0);$q=$pdo->prepare('SELECT * FROM accounting_rules WHERE id=? AND source_type=? AND document_kind=? AND valid_from<=?');$q->execute([$ruleId,$type,$kind,$effective]);$rule=$q->fetch(PDO::FETCH_ASSOC);if(!$rule)throw new DomainException('Každá položka potřebuje platné schválené účetní členění.');$line['rule_id']=$ruleId;$line['category_code']=$rule['category_code'];$line['activity']=$rule['activity'];$line['rule_title']=$rule['title'];}unset($line);
  $snapshot=$source+['template_version'=>'nonvat-v1','source_sha256'=>$sourceHash,'issuer_id'=>$issuerId,'issuer'=>json_decode($issuer['snapshot_json'],true,512,JSON_THROW_ON_ERROR),'buyer'=>$buyer,'payer'=>$payer,'effective_on'=>$effective,'due_on'=>$due,'kind'=>$kind];
  $json=accountingDocumentJson($snapshot);
  if($existingId!==null){$id=$existingId;$pdo->prepare('UPDATE accounting_documents SET snapshot_json=?,snapshot_sha256=?,total_minor=?,effective_on=? WHERE id=?')->execute([$json,hash('sha256',$json),$source['total_minor'],$effective,$id]);accountingDocumentEvent($pdo,$id,$actor,'draft_rebuilt',$reason);}
  else{$pdo->prepare('INSERT INTO accounting_documents(kind,source_type,source_id,idempotency_key,source_unique_key,status,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$kind,$type,$sourceId,$request,$unique,'draft',$json,hash('sha256',$json),$source['total_minor'],$source['currency'],$effective,$actor]);$id=(int)$pdo->lastInsertId();accountingDocumentEvent($pdo,$id,$actor,'drafted',$reason);}$pdo->commit();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accountingDocumentCreateCorrection(PDO $pdo,int $originalId,array $lines,string $requestKey,string $reason):int {
 $actor=accountingDocumentRequireFinance($pdo);$requestKey=accountingDocumentText($requestKey,120);$reason=accountingDocumentText($reason);
 if($pdo->inTransaction())throw new LogicException('Oprava vyžaduje vlastní transakci.');$pdo->beginTransaction();
 try {
  accountingDocumentRequireFinance($pdo);
  $original=accountingDocumentGet($pdo,$originalId,true);if($original['status']!=='issued'||!in_array($original['kind'],['invoice','contribution_confirmation'],true))throw new DomainException('Oprava vyžaduje vydaný původní prodejní nebo členský doklad.');
  $q=$pdo->prepare('SELECT * FROM accounting_documents WHERE idempotency_key=?');$q->execute([$requestKey]);$existing=$q->fetch(PDO::FETCH_ASSOC);if($existing){if((int)$existing['original_document_id']!==$originalId)throw new DomainException('Klíč opravy již patří jinému dokladu.');$pdo->commit();return (int)$existing['id'];}
  $snapshot=json_decode($original['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$available=[];
  foreach($snapshot['lines'] as $l)$available[$l['line_key']]=$l;
  $q=$pdo->prepare("SELECT snapshot_json FROM accounting_documents WHERE original_document_id=? AND status<>'void'");$q->execute([$originalId]);
  foreach($q->fetchAll(PDO::FETCH_COLUMN) as $json)foreach(json_decode($json,true,512,JSON_THROW_ON_ERROR)['lines'] as $l){$available[$l['line_key']]['amount_minor']+=(int)$l['amount_minor'];$available[$l['line_key']]['quantity']-=(int)$l['quantity'];}
  if($lines===[]||count($lines)>200)throw new InvalidArgumentException('Vyberte opravované položky.');$corrected=[];$sum=0;$seen=[];
  foreach($lines as $l){$key=(string)($l['line_key']??'');$amount=filter_var($l['amount_minor']??null,FILTER_VALIDATE_INT);$qty=filter_var($l['quantity']??null,FILTER_VALIDATE_INT);
   if(isset($seen[$key])||!isset($available[$key])||$amount===false||$qty===false||$amount<1||$qty<0||$amount>(int)$available[$key]['amount_minor']||$qty>(int)$available[$key]['quantity'])throw new DomainException('Oprava překračuje zbývající částku nebo množství položky.');
   $seen[$key]=true;$row=$available[$key];$row['quantity']=$qty;$row['amount_minor']=-$amount;$corrected[]=$row;$sum-=$amount;
  }
  // Price adjustments may not reverse more than the original net total, even with a separate discount line.
  $q=$pdo->prepare("SELECT COALESCE(SUM(total_minor),0) FROM accounting_documents WHERE original_document_id=? AND status<>'void'");$q->execute([$originalId]);
  if(-$sum>(int)$original['total_minor']+(int)$q->fetchColumn())throw new DomainException('Oprava překračuje zbývající cenu dokladu.');
  $snapshot['lines']=$corrected;$snapshot['total_minor']=$sum;$snapshot['kind']='correction';$snapshot['original_document_id']=$originalId;$snapshot['original_number']=$original['number'];$snapshot['correction_reason']=$reason;$snapshot['effective_on']=date('Y-m-d');$snapshot['due_on']=null;unset($snapshot['issued_on']);$json=accountingDocumentJson($snapshot);
  $pdo->prepare('INSERT INTO accounting_documents(kind,source_type,source_id,original_document_id,idempotency_key,status,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute(['correction',$original['source_type'],$original['source_id'],$originalId,$requestKey,'draft',$json,hash('sha256',$json),$sum,$original['currency'],$snapshot['effective_on'],$actor]);$id=(int)$pdo->lastInsertId();accountingDocumentEvent($pdo,$id,$actor,'drafted',$reason);$pdo->commit();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accountingDocumentHtml(array $row,string $nonce=""):string {
 $s=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$money=static fn(int $v):string=>number_format($v/100,2,',',' ').' Kč';
 $titles=['invoice'=>'Faktura','contribution_confirmation'=>'Doklad k členskému příspěvku','correction'=>'Opravný doklad','cash_receipt'=>'Příjmový pokladní doklad','cash_disbursement'=>'Výdajový pokladní doklad'];
 $html='<!doctype html><html lang="cs"><head><meta charset="UTF-8"><style nonce="'.$h($nonce).'">@page{margin:38pt}body{font-family:DejaVu Sans,sans-serif;font-size:10pt;color:#172b40}h1{font-size:21pt}h2{font-size:12pt}table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{padding:7pt 5pt;border-bottom:1px solid #ccd4db;word-wrap:break-word}th{text-align:left}.amount{text-align:right}.meta{color:#526171;font-size:9pt}.total{text-align:right;font-size:15pt;margin-top:15pt}.party{vertical-align:top;width:50%}</style></head><body><h1>'.$h($titles[$row['kind']]??'Doklad').'</h1><p><strong>'.$h($row['number']??'NÁHLED').'</strong></p>';
 if(empty($row['number']))$html.='<p><strong>NÁHLED - číslo není přiděleno</strong></p>';
 $html.='<p class="meta">Datum vystavení: '.$h($s['issued_on']??'dosud nevystaveno').(($s['due_on']??null)!==null?' | Splatnost: '.$h($s['due_on']):'').'</p>';
 if(!empty($s['variable_symbol']))$html.='<p>Variabilní symbol: <strong>'.$h($s['variable_symbol']).'</strong></p>';
 $bank=trim((string)($s['issuer']['bank_account']??''))?:trim((string)($s['payment_bank_account']??''));if($bank!=='')$html.='<p>Bankovní účet: '.$h($bank).'</p>';
 $html.='<p class="meta">Datum dokladu: '.$h($s['effective_on']).' | Případ: '.$h($s['reference']).'</p><table><tr><td class="party"><h2>Vystavovatel</h2>'.$h($s['issuer']['name']).'<br>'.$h($s['issuer']['address']).'<br>IČ: '.$h($s['issuer']['registration_id']).'<br>Nejsme plátci DPH.</td><td class="party"><h2>Odběratel</h2>'.$h($s['buyer']['name']).'<br>'.$h($s['buyer']['address']).'<br>'.$h($s['buyer']['registration_id']).'</td></tr></table>';
 if(($s['payer']['name']??'')!=='')$html.='<p>Plátce uvedený při přípravě: '.$h($s['payer']['name']).'. Skutečné peníze dokládá samostatná evidence pohybů.</p>';
 if(isset($s['original_number']))$html.='<p>Oprava dokladu '.$h($s['original_number']).': '.$h($s['correction_reason']).'</p>';
 $html.='<table><thead><tr><th style="width:55%">Položka / účastník</th><th style="width:12%">Počet</th><th class="amount" style="width:33%">Částka</th></tr></thead><tbody>';
 foreach($s['lines'] as $l){$period=$l['service_period']??null;$periodLabel=is_array($period)?implode(' - ',array_filter($period,static fn($v)=>$v!==null)):($period??'');$html.='<tr><td>'.$h($l['label']).'<br><span class="meta">'.$h($l['beneficiary']['name']??'').($periodLabel!==''?'<br>Období: '.$h($periodLabel):'').'</span></td><td>'.$h($l['quantity']).'</td><td class="amount">'.$money((int)$l['amount_minor']).'</td></tr>';}
 $html.='</tbody></table><p class="total">Celkem '.$money((int)$row['total_minor']).'</p><p class="meta">Vystavení dokladu nepotvrzuje přijetí ani vrácení peněz. Původní platební reference / VS se nemění.</p><p class="meta">Šablona '.$h($s['template_version']).' | Otisk obsahu '.$h(substr($row['snapshot_sha256'],0,20)).'</p></body></html>';return $html;
}
function accountingDocumentPdfBytes(array $row):string {
 require_once dirname(__DIR__).'/vendor/autoload.php';$options=new Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('isJavascriptEnabled',false);$options->set('defaultFont','DejaVu Sans');$options->set('chroot',dirname(__DIR__).'/vendor/dompdf');
 $dompdf=new Dompdf\Dompdf($options);$dompdf->loadHtml(accountingDocumentHtml($row),'UTF-8');$dompdf->setPaper('A4');$dompdf->render();$dompdf->getCanvas()->page_text(38,818,(string)($row['number']??'NÁHLED').' | {PAGE_NUM} / {PAGE_COUNT}',$dompdf->getFontMetrics()->getFont('DejaVu Sans','normal'),8,[0.32,0.38,0.44]);return $dompdf->output();
}
/** Number reservation is committed before rendering: failure/retry keeps the same number forever. */
function accountingDocumentIssue(PDO $pdo,int $id,string $reason):array {
 $actor=accountingDocumentRequireFinance($pdo);$reason=accountingDocumentText($reason);
 if($pdo->inTransaction())throw new LogicException('Vydání vyžaduje vlastní transakci.');$pdo->beginTransaction();
 try {
  accountingDocumentRequireFinance($pdo);
  $r=accountingDocumentGet($pdo,$id,true);if($r['status']==='issued'){$pdo->commit();return $r;}
  if(!in_array($r['status'],['draft','rendering','failed'],true))throw new DomainException('Zrušený koncept nelze vydat.');
  if(!hash_equals($r['snapshot_sha256'],hash('sha256',$r['snapshot_json'])))throw new DomainException('Nesouhlasí otisk obsahu.');
  if($r['number']===null){
   if($r['kind']!=='correction'){$s=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$source=accountingDocumentSource($pdo,$r['source_type'],(int)$r['source_id'],true);if(!hash_equals($s['source_sha256'],hash('sha256',accountingDocumentJson($source))))throw new DomainException('Zdroj případu se od náhledu změnil. Koncept nelze vydat bez nového ověření.');}
   $q=$pdo->prepare('SELECT * FROM accounting_document_series WHERE kind=? AND series_year=?'.accountingDocumentLock($pdo));$q->execute([$r['kind'],(int)substr($r['effective_on'],0,4)]);$series=$q->fetch(PDO::FETCH_ASSOC);if(!$series)throw new DomainException('Nejprve nastavte samostatnou číselnou řadu dokladů.');
   if((int)$series['next_number']>99999999)throw new DomainException('Číselná řada je vyčerpána.');$number=$series['prefix'].'-'.$series['series_year'].'-'.str_pad((string)$series['next_number'],6,'0',STR_PAD_LEFT);
   $pdo->prepare('UPDATE accounting_document_series SET next_number=next_number+1 WHERE id=?')->execute([$series['id']]);$pdo->prepare("UPDATE accounting_documents SET number=?,series_id=?,status='rendering' WHERE id=?")->execute([$number,$series['id'],$id]);accountingDocumentEvent($pdo,$id,$actor,'number_reserved',$reason);
  }$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 $key=null;$temp=null;$pdo->beginTransaction();
 try {
  accountingDocumentRequireFinance($pdo);
  $r=accountingDocumentGet($pdo,$id,true);if($r['status']==='issued'){$pdo->commit();return $r;}
  if(!hash_equals($r['snapshot_sha256'],hash('sha256',$r['snapshot_json'])))throw new DomainException('Nesouhlasí otisk obsahu.');
  $snapshot=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$snapshot['issued_on']=date('Y-m-d');$r['snapshot_json']=accountingDocumentJson($snapshot);$r['snapshot_sha256']=hash('sha256',$r['snapshot_json']);
  $bytes=accountingDocumentPdfBytes($r);$dir=privateStorageEnsureDirectory(PRIVATE_STORAGE_ACCOUNTING_DOCUMENTS);$temp=tempnam($dir,'render-');if($temp===false||file_put_contents($temp,$bytes)!==strlen($bytes))throw new RuntimeException('Uložení PDF selhalo.');
  $key=privateStorageStore($temp,PRIVATE_STORAGE_ACCOUNTING_DOCUMENTS,false);$hash=hash('sha256',$bytes);
  $pdo->prepare("UPDATE accounting_documents SET status='issued',issued_at=CURRENT_TIMESTAMP,pdf_storage_key=?,pdf_sha256=?,snapshot_json=?,snapshot_sha256=? WHERE id=?")->execute([$key,$hash,$r['snapshot_json'],$r['snapshot_sha256'],$id]);accountingDocumentEvent($pdo,$id,$actor,'issued',$reason);$pdo->commit();return accountingDocumentGet($pdo,$id);
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($temp!==null&&is_file($temp))unlink($temp);if($key!==null&&($path=privateStorageResolve($key))!==null)unlink($path);
  // Never overwrite a successful concurrent retry with a failed marker.
  $pdo->prepare("UPDATE accounting_documents SET status='failed' WHERE id=? AND status<>'issued'")->execute([$id]);accountingDocumentEvent($pdo,$id,$actor,'render_failed','Generování nebo uložení PDF selhalo; opakujte stejný doklad.');throw $e;
 }
}
function accountingDocumentDownload(PDO $pdo,int $id):string {
 $actor=accountingDocumentRequireFinance($pdo);$r=accountingDocumentGet($pdo,$id);$path=privateStorageResolve((string)$r['pdf_storage_key']);
 if($r['status']!=='issued'||$path===null||!hash_equals((string)$r['pdf_sha256'],(string)hash_file('sha256',$path))||!hash_equals($r['snapshot_sha256'],hash('sha256',$r['snapshot_json'])))throw new DomainException('Doklad není vydaný nebo nesouhlasí jeho otisk.');accountingDocumentEvent($pdo,$id,$actor,'downloaded','Stažení soukromého PDF.');return $path;
}

function accountingDocumentReviseParties(PDO $pdo,int $id,array $buyer,array $payer,string $reason):void {
 $actor=accountingDocumentRequireFinance($pdo);$reason=accountingDocumentText($reason);$buyer=accountingDocumentParty($buyer);$payer=accountingDocumentParty($payer,false);
 if($pdo->inTransaction())throw new LogicException('Oprava konceptu vyžaduje vlastní transakci.');$pdo->beginTransaction();
 try{accountingDocumentRequireFinance($pdo);$r=accountingDocumentGet($pdo,$id,true);if($r['status']!=='draft'||$r['number']!==null||$r['kind']==='correction')throw new DomainException('Měnit lze pouze nevydaný koncept bez přiděleného čísla.');
 $s=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$s['buyer']=$buyer;$s['payer']=$payer;$json=accountingDocumentJson($s);$pdo->prepare('UPDATE accounting_documents SET snapshot_json=?,snapshot_sha256=? WHERE id=?')->execute([$json,hash('sha256',$json),$id]);accountingDocumentEvent($pdo,$id,$actor,'parties_revised',$reason);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Only an unnumbered draft can be abandoned; originals and reserved numbers stay permanent. */
function accountingDocumentVoidDraft(PDO $pdo,int $id,string $reason):void {
 $actor=accountingDocumentRequireFinance($pdo);$reason=accountingDocumentText($reason);if($pdo->inTransaction())throw new LogicException('Zrušení konceptu vyžaduje vlastní transakci.');$pdo->beginTransaction();
 try{accountingDocumentRequireFinance($pdo);$r=accountingDocumentGet($pdo,$id);if($r['original_document_id']!==null)accountingDocumentGet($pdo,(int)$r['original_document_id'],true);$r=accountingDocumentGet($pdo,$id,true);if($r['status']!=='draft'||$r['number']!==null)throw new DomainException('Zrušit lze pouze koncept bez přiděleného čísla.');$pdo->prepare("UPDATE accounting_documents SET status='void',source_unique_key=NULL WHERE id=?")->execute([$id]);accountingDocumentEvent($pdo,$id,$actor,'draft_voided',$reason);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
