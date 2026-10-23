<?php
declare(strict_types=1);
require_once __DIR__.'/accounting_ledger.php';
require_once __DIR__.'/shop_inventory.php';
require_once __DIR__.'/club_expenses.php';
require_once __DIR__.'/club_rewards.php';
/** Typed, additive rehearsal adapters. No historical payment/status or invoice writer exists here. */
function clubMigrationFinanceAdapters():array {
 $out=[];foreach(['finance_account_opening','finance_stock_opening','finance_order_link','finance_document_link','finance_member_charge_link','finance_expense_link','finance_advance_link','finance_reward_link'] as $type)$out[$type]=[
  'authorize'=>static function(PDO $p,int $actor):void{clubMigrationActor($p,$actor,['finance_manager']);if(!$p->inTransaction())throw new LogicException('Migration authorization requires transaction.');$p->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');accountingLedgerActor($p,$actor);},
  'preview'=>static fn(PDO $p,array $b,array $i,array $d,array $proposal):array=>clubMigrationFinancePreview($p,$type,$b,$i,$d,$proposal),
  'apply'=>static fn(PDO $p,array $b,array $i,array $d,array $proposal,int $actor):array=>clubMigrationFinanceApply($p,$type,$b,$i,$d,$proposal,$actor),
  'target'=>static fn(PDO $p,string $targetType,int $id):array=>clubMigrationFinanceTarget($p,$targetType,$id),
 ];return $out;
}
function clubMigrationFinanceRows(PDO $p,string $sql,array $args=[]):array {$q=$p->prepare($sql.($p->inTransaction()?accountingLedgerLock($p):''));$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function clubMigrationFinanceTypes():array{return ['finance_order_link'=>'shop_orders','finance_document_link'=>'accounting_documents','finance_member_charge_link'=>'club_member_charges','finance_expense_link'=>'club_expense_claims','finance_advance_link'=>'club_expense_advances','finance_reward_link'=>'club_reward_claims','finance_account'=>'accounting_accounts','finance_stock'=>'shop_variants'];}
function clubMigrationFinanceTarget(PDO $p,string $type,int $id):array {
 $table=clubMigrationFinanceTypes()[$type]??throw new DomainException('Neznámý finanční cíl.');$rows=clubMigrationFinanceRows($p,'SELECT * FROM '.$table.' WHERE id=?',[$id]);$r=$rows[0]??null;if(!$r)throw new DomainException('Ověřený cílový záznam neexistuje.');$out=['type'=>$type,'row'=>$r];
 if($type==='finance_account'){$out['movements']=clubMigrationFinanceRows($p,'SELECT * FROM accounting_movements WHERE account_id=? ORDER BY id',[$id]);$out['closings']=clubMigrationFinanceRows($p,'SELECT * FROM accounting_reconciliations WHERE account_id=? ORDER BY id',[$id]);}
 elseif($type==='finance_stock'){$out['balance']=shopInventoryBalance($p,$id,$p->inTransaction());$out['open_items']=clubMigrationFinanceRows($p,"SELECT i.*,o.status AS order_status,o.payment_status FROM shop_order_items i JOIN shop_orders o ON o.id=i.order_id WHERE i.variant_id=? AND o.status IN ('placed','processing','ready') ORDER BY i.id",[$id]);$out['reservations']=clubMigrationFinanceRows($p,'SELECT * FROM shop_stock_reservations WHERE variant_id=? ORDER BY id',[$id]);$out['policy']=shopInventoryPolicy($p);}
 elseif($type==='finance_order_link'){$out['items']=clubMigrationFinanceRows($p,'SELECT * FROM shop_order_items WHERE order_id=? ORDER BY id',[$id]);$out['reservations']=clubMigrationFinanceRows($p,'SELECT * FROM shop_stock_reservations WHERE order_id=? ORDER BY id',[$id]);$out['payments']=clubMigrationFinanceRows($p,"SELECT * FROM payments WHERE payable_type='shop_order' AND payable_id=? ORDER BY id",[$id]);$out['allocations']=clubMigrationFinanceRows($p,"SELECT * FROM accounting_allocations WHERE case_type='shop_order' AND case_id=? ORDER BY id",[$id]);}
 elseif($type==='finance_member_charge_link'){$out['payments']=clubMigrationFinanceRows($p,"SELECT * FROM payments WHERE payable_type='member_charge' AND payable_id=? ORDER BY id",[$id]);$out['allocations']=clubMigrationFinanceRows($p,"SELECT * FROM accounting_allocations WHERE case_type='member_charge' AND case_id=? ORDER BY id",[$id]);}
 elseif($type==='finance_expense_link'){$root=(int)$r['root_id'];$out['versions']=clubMigrationFinanceRows($p,'SELECT * FROM club_expense_claims WHERE root_id=? ORDER BY version_no',[$root]);$out['balance']=clubExpenseClaimBalance($p,$root);$out['allocations']=clubMigrationFinanceRows($p,"SELECT * FROM accounting_allocations WHERE case_type IN ('expense_claim','expense_claim_return') AND case_id=? ORDER BY id",[$root]);$out['consumptions']=clubMigrationFinanceRows($p,'SELECT * FROM club_expense_consumptions WHERE claim_root_id=? ORDER BY id',[$root]);}
 elseif($type==='finance_advance_link'){$out['balance']=clubExpenseAdvanceBalance($p,$id);$out['allocations']=clubMigrationFinanceRows($p,"SELECT * FROM accounting_allocations WHERE case_type IN ('expense_advance_out','expense_advance_in') AND case_id=? ORDER BY id",[$id]);$out['consumptions']=clubMigrationFinanceRows($p,'SELECT * FROM club_expense_consumptions WHERE advance_id=? ORDER BY id',[$id]);$out['transfers']=clubMigrationFinanceRows($p,'SELECT * FROM club_expense_transfers WHERE from_advance_id=? OR to_advance_id=? ORDER BY id',[$id,$id]);}
 elseif($type==='finance_reward_link'){$out['versions']=clubMigrationFinanceRows($p,'SELECT * FROM club_reward_versions WHERE claim_id=? ORDER BY version',[$id]);$out['payouts']=clubMigrationFinanceRows($p,'SELECT * FROM club_reward_payouts WHERE claim_id=? ORDER BY id',[$id]);$v=$r['approved_version_id']?clubRewardVersion($p,(int)$r['approved_version_id']):null;$out['approved_recipient']=$v?['kind'=>$v['recipient_kind'],'id'=>(int)$v['recipient_id']]:null;$out['balance']=['approved_minor'=>$v?(int)$v['amount_minor']:0,'paid_minor'=>clubRewardPaid($p,$id)];$out['balance']['remaining_minor']=$out['balance']['approved_minor']-$out['balance']['paid_minor'];}
 elseif($type==='finance_document_link'){
  $key=(string)($r['pdf_storage_key']??'');if($key!==''){require_once __DIR__.'/private_storage.php';$path=privateStorageResolve($key);$out['actual_pdf_sha256']=$path&&is_file($path)?hash_file('sha256',$path):null;}else $out['actual_pdf_sha256']=null;
 }
 $out['counterparties']=clubMigrationFinanceCounterparties($p,$out);return $out;
}
function clubMigrationFinanceSummary(array $target):array {
 $r=$target['row'];$out=$r;
 if(isset($target['payments'])){$symbols=array_values(array_unique(array_map(static fn($p)=>(string)$p['variable_symbol'],$target['payments'])));$out['variable_symbols']=$symbols;}
 if(isset($target['balance'])&&is_array($target['balance']))$out=array_merge($out,$target['balance']);
 if(isset($target['items']))$out['items_hash']=clubMigrationHash($target['items']);
 if(isset($target['reservations']))$out['reservations_hash']=clubMigrationHash($target['reservations']);
 return $out;
}
function clubMigrationFinanceCounterparties(PDO $p,array $target):array {
 $r=$target['row'];$roles=match($target['type']){
  'finance_order_link'=>['buyer'=>['kind'=>'account','id'=>$r['account_id']]],
  'finance_member_charge_link'=>['beneficiary'=>['kind'=>'person','id'=>$r['sportovec_id']]]+(!empty($r['payer_account_id'])?['payer'=>['kind'=>'account','id'=>$r['payer_account_id']]]:[]),
  'finance_expense_link'=>['payer'=>['kind'=>$r['payer_kind'],'id'=>$r['payer_kind']==='supplier'?$r['payer_label']:$r['payer_id']],'creditor'=>['kind'=>$r['creditor_kind'],'id'=>$r['creditor_kind']==='supplier'?$r['creditor_label']:$r['creditor_id']]],
  'finance_advance_link'=>['recipient'=>['kind'=>$r['recipient_kind'],'id'=>$r['recipient_id']]],
  'finance_reward_link'=>['subject'=>['kind'=>$r['kind']==='coach'?'trainer':'person','id'=>$r['subject_id']]]+(!empty($target['approved_recipient'])?['recipient'=>$target['approved_recipient']]:[]),
  default=>[]};
 foreach($roles as &$role){if($role['kind']==='supplier'){$role['identity']=['supplier_label'=>$role['id']];continue;}$table=match($role['kind']){'trainer'=>'treneri','person'=>'sportovci','account'=>'verejni_uzivatele',default=>throw new DomainException('Neznámý druh protistrany.')};$rows=clubMigrationFinanceRows($p,'SELECT * FROM '.$table.' WHERE id=?',[(int)$role['id']]);if(!$rows)throw new DomainException('Cílová protistrana nebyla nalezena.');$row=$rows[0];foreach(['heslo','password_hash','reset_token','activation_token','email_verification_token','totp_secret','remember_token'] as $secret)unset($row[$secret]);$role['identity_hash']=clubMigrationHash($row);}
 unset($role);return $roles;
}
function clubMigrationFinancePreview(PDO $p,string $type,array $batch,array $item,array $data,array $proposal):array {
 $issues=[];$target=null;$targetId=(int)($proposal['target_id']??0);$targetType=$type;$normalized=[];
 foreach(['mapping_evidence','counterparty_evidence'] as $k)if(mb_strlen(trim((string)($proposal[$k]??'')))<10)$issues[]='Chybí doložené ověření: '.$k;
 if(!empty($data['conflicts'])&&mb_strlen(trim((string)($proposal['resolution_evidence']??'')))<10)$issues[]='Zdroj obsahuje finanční rozpor (např. Harrachov); hospodář jej musí doložit a rozhodnout.';
 foreach($proposal['identity_maps']??[] as $m){$map=clubMigrationMap($p,$batch['source_namespace'],(string)($m['source_type']??''),(string)($m['source_id']??''));if(!$map||$map['target_type']!==($m['target_type']??'')||(int)$map['target_id']!==(int)($m['target_id']??0))$issues[]='Chybí platná schválená mapa protistrany.';else{$adapter=clubMigrationAdapter($map['entity_type']);$mapped=($adapter['target'])($p,$map['target_type'],(int)$map['target_id']);if(!hash_equals($map['target_hash'],clubMigrationHash($mapped)))$issues[]='Mapovaná identita nebo oprávnění se od ověření změnily.';$normalized['identity_maps'][]=$map;}}
 $existingMap=clubMigrationMap($p,(string)$batch['source_namespace'],$type,(string)$item['source_id']);
 if($existingMap){if(!hash_equals((string)$existingMap['source_hash'],(string)$item['source_hash']))$issues[]='Stejné zdrojové ID změnilo obsah; změnová dávka vyžaduje individuální posouzení.';try{$current=clubMigrationFinanceTarget($p,$existingMap['target_type'],(int)$existingMap['target_id']);$currentHash=clubMigrationHash($current);if(!hash_equals($existingMap['target_hash'],$currentHash))$issues[]='Dříve převzatý cílový stav byl změněn.';}catch(Throwable $e){$currentHash=null;$issues[]=$e->getMessage();}return ['target_type'=>$existingMap['target_type'],'target_id'=>(int)$existingMap['target_id'],'target_hash'=>$currentHash,'normalized'=>['replay'=>true],'issues'=>$issues];}
 if($type==='finance_account_opening'){
  $targetType='finance_account';$code=trim((string)($data['code']??''));$existing=clubMigrationFinanceRows($p,'SELECT * FROM accounting_accounts WHERE code=?',[$code]);$target=['code'=>$code,'existing'=>$existing];
  foreach(['code','name','kind','currency','opened_on','opening_minor','evidence'] as $k)if(!array_key_exists($k,$data))$issues[]='Chybí počáteční stav účtu: '.$k;
  if(!is_int($data['opening_minor']??null)||abs((int)($data['opening_minor']??0))>1000000000000||($data['currency']??'')!=='CZK'||!in_array($data['kind']??'', ['cash','bank','provider'],true))$issues[]='Neplatná měna, druh nebo přesná částka počátečního stavu.';
  if(mb_strlen(trim((string)($proposal['opening_approval_evidence']??'')))<10)$issues[]='Počáteční stav vyžaduje výslovné odsouhlasení inventury/výpisu.';
  if($existing)$issues[]='Účet již existuje; počáteční stav se nesmí přepsat ani přidat jako další příjem.';
  foreach(['opened_on'] as $k)try{accountingLedgerDate((string)($data[$k]??''));}catch(Throwable){$issues[]='Neplatné doložené datum účtu.';}
  $normalized['opening']=array_intersect_key($data,array_flip(['code','name','kind','currency','opened_on','opening_minor','evidence']));$targetId=0;
 }elseif($type==='finance_stock_opening'){
  $targetType='finance_stock';$targetId=(int)($proposal['variant_id']??0);
  try{$target=clubMigrationFinanceTarget($p,$targetType,$targetId);}catch(Throwable $e){$issues[]=$e->getMessage();}
  $bindings=$proposal['reservations']??[];if(!is_array($bindings)){$issues[]='Chybí soupis rezervací.';$bindings=[];}
  $physical=$data['physical_quantity']??null;$blocked=$data['blocked_quantity']??null;
  if(!is_int($physical)||!is_int($blocked)||$physical<0||$blocked<0||$physical>1000000||$physical<$blocked)$issues[]='Počátek musí vycházet z fyzické inventury, odděleně blokovaných kusů.';
  if(($target['row']['sku']??null)!==($data['sku']??null))$issues[]='Ověřené SKU se liší.';
  if(($target['balance']??null)!==null)$issues[]='Varianta již má fyzický počátek; nelze importovat podruhé.';
  if(empty($target['policy']))$issues[]='Sklad nemá odsouhlasenou metodu ocenění.';
  $open=[];foreach($target['open_items']??[] as $i)$open[(string)$i['id']]=(int)$i['quantity'];ksort($open);$clean=[];foreach($bindings as $id=>$n){if(!ctype_digit((string)$id)||!is_int($n)||$n<1)$issues[]='Rezervace vyžaduje explicitní cílovou položku a počet.';$clean[(string)$id]=(int)$n;}ksort($clean);if($open!==$clean)$issues[]='Soupis nezahrnuje právě všechny ověřené otevřené objednávky varianty.';
  if((int)$physical<(int)$blocked+array_sum($clean))$issues[]='Fyzický počet nestačí na blokace a rezervace.';
  if(($data['reserved_quantity']??null)!==array_sum($clean))$issues[]='Součet zdrojových rezervací není vysvětlen cílovými položkami. Objednávky dobíhající v původním systému vyžadují doloženou blokaci nebo aktivní cílovou rezervaci.';
  if(mb_strlen(trim((string)($proposal['inventory_approval_evidence']??'')))<10)$issues[]='Chybí potvrzení fyzické inventury a mapy rezervací.';
  try{shopInventoryDate((string)($data['effective_on']??''));shopInventoryCost($data['unit_cost_minor']??null);}catch(Throwable $e){$issues[]=$e->getMessage();}
  $normalized['opening']=['physical_quantity'=>$physical,'blocked_quantity'=>$blocked,'unit_cost_minor'=>$data['unit_cost_minor']??null,'effective_on'=>(string)($data['effective_on']??''),'evidence'=>(string)($data['evidence']??''),'reservations'=>$clean];
 }else{
  if($targetId<1){$legacy=['system'=>(string)($proposal['completion_system']??''),'owner_trainer_id'=>(int)($proposal['completion_owner_trainer_id']??0),'source_id'=>$item['source_id'],'variable_symbols'=>$data['expected']['variable_symbols']??[],'reservations'=>$data['expected']['reservations_hash']??null,'stop_flag'=>true];$normalized['legacy_completion']=$legacy;$issues[]='Aktivní případ nemá schválený cílový záznam. Uložte vlastníka doběhu, původní systém, ID/VS a rezervace; převzetí agendy je blokované.';}
  else try{$target=clubMigrationFinanceTarget($p,$type,$targetId);}catch(Throwable $e){$issues[]=$e->getMessage();}
  $required=match($type){
   'finance_order_link'=>['account_id','total_minor','currency','status','payment_status','variable_symbols','items_hash','reservations_hash'],
   'finance_document_link'=>['number','total_minor','currency','snapshot_sha256','pdf_sha256'],
   'finance_member_charge_link'=>['sportovec_id','payer_account_id','amount_minor','currency','public_code','status','variable_symbols'],
   'finance_expense_link'=>['creditor_kind','creditor_id','creditor_label','payer_kind','payer_id','payer_label','document_number','currency','accepted_minor','net_paid_minor','remaining_minor'],
   'finance_advance_link'=>['recipient_kind','recipient_id','recipient_label','currency','limit_minor','out_minor','returned_minor','consumed_minor','transferred_minor','balance_minor'],
   'finance_reward_link'=>['currency','kind','subject_id','date_from','date_to','approved_minor','paid_minor','remaining_minor'],
   default=>throw new DomainException('Neznámý převodník.')};
  $expected=$data['expected']??[];if(!is_array($expected))$expected=[];$summary=$target?clubMigrationFinanceSummary($target):[];
  foreach($required as $key){if(!array_key_exists($key,$expected)){$issues[]='Chybí zdrojová kontrolní hodnota '.$key;continue;}$v=$expected[$key];$actual=$summary[$key]??null;if(str_ends_with($key,'_minor')){if(!is_int($v)||!is_numeric($actual)||(int)$actual!==$v)$issues[]='Nevysvětlený finanční rozdíl '.$key;}elseif(is_array($v)?$actual!==$v:(string)$actual!==(string)$v)$issues[]='Rozdílná hodnota '.$key;}
  if($type==='finance_document_link'&&$target&&(($target['row']['status']??'')!=='issued'||!hash_equals((string)$target['row']['snapshot_sha256'],hash('sha256',(string)$target['row']['snapshot_json']))||!hash_equals((string)($target['row']['pdf_sha256']??''),(string)($target['actual_pdf_sha256']??''))||empty($target['actual_pdf_sha256'])))$issues[]='Původní doklad nemá ověřený neměnný PDF originál; nesmí se vystavit znovu.';
  if($type==='finance_order_link'&&!in_array($proposal['completion_system']??'', ['kis','legacy'],true))$issues[]='Otevřená objednávka vyžaduje výslovného vlastníka systému dokončení.';
  if($type==='finance_order_link'){$owner=(int)($proposal['completion_owner_trainer_id']??0);$q=$p->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1');$q->execute([$owner]);if(!$q->fetchColumn())$issues[]='Chybí aktivní konkrétní odpovědná osoba doběhu objednávky.';}
  if($target){$bindings=clubMigrationFinanceCounterparties($p,$target);foreach($bindings as $role=>$binding){$choice=$proposal['counterparties'][$role]??[];$sourceId=(string)($data['counterparties'][$role]??'');if($sourceId===''||$sourceId!==(string)($choice['source_id']??'')||$binding['kind']!==($choice['target_kind']??null)||(string)$binding['id']!==(string)($choice['target_id']??'')||mb_strlen(trim((string)($choice['evidence']??'')))<10)$issues[]='Chybí přesná schválená mapa protistrany '.$role;$normalized['counterparties'][$role]=$binding;}}
  $normalized['expected']=$expected;
 }
 return ['target_type'=>$targetType,'target_id'=>$targetId?:null,'target_hash'=>clubMigrationHash($target),'normalized'=>$normalized,'issues'=>array_values(array_unique($issues))];
}
function clubMigrationFinanceApply(PDO $p,string $type,array $batch,array $item,array $data,array $proposal,int $actor):array {
 clubMigrationGuard($p);if(!$p->inTransaction())throw new LogicException('Import musí proběhnout uvnitř schválené atomické operace.');$p->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');accountingLedgerActor($p,$actor);
 $preview=clubMigrationFinancePreview($p,$type,$batch,$item,$data,$proposal);$hash=clubMigrationHash([$item['source_hash'],$proposal,$preview,clubMigrationCodeHash()]);if($preview['issues']||!hash_equals((string)$item['review_hash'],$hash))throw new DomainException('Finanční cíl nebo kontrolní rozdíl se změnil pod zámkem; import nic nepřepsal.');
 if($type==='finance_account_opening'){$row=accountingLedgerCreateAccount($p,$preview['normalized']['opening'],$actor,'Odsouhlasený syntetický počáteční stav: '.$proposal['opening_approval_evidence'],true);return ['target_type'=>'finance_account','target_id'=>(int)$row['id']];}
 if($type==='finance_stock_opening'){shopInventoryActor($p,$actor);$key='mig-stock-'.hash('sha256',$batch['source_namespace'].':'.$type.':'.$item['source_id']);shopInventoryOpen($p,$actor,(int)$proposal['variant_id'],$preview['normalized']['opening'],'Odsouhlasená syntetická inventura: '.$proposal['inventory_approval_evidence'],true,$key);return ['target_type'=>'finance_stock','target_id'=>(int)$proposal['variant_id']];}
 return ['target_type'=>$type,'target_id'=>(int)$proposal['target_id'],'linked'=>true];
}
