<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/shop_returns.php';
require_once dirname(__DIR__,2).'/includes/shop_stock_documents.php';

/** Extends only the disposable mixed-family fixture; no production credentials or application bootstrap. */
function stockReturnAccountingAcceptance(PDO $pdo,array $order,int $invoiceId,int $accountId,array $movements,string $today,string $reason):void
{
    if(!str_starts_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'evidence_shop_checkout_smoke_test'))throw new RuntimeException('Disposable checkout database required.');
    $check=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
    $q=$pdo->prepare('SELECT i.* FROM shop_fulfillment_items i JOIN shop_fulfillments f ON f.id=i.fulfillment_id WHERE f.order_id=?');$q->execute([$order['id']]);$issued=$q->fetch(PDO::FETCH_ASSOC);
    $variant=(int)$issued['variant_id'];$before=shopInventoryBalance($pdo,$variant);$moneyCount=(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn();
    $input=['issue_movement_id'=>(int)$issued['inventory_movement_id'],'quantity'=>1,'request_type'=>'withdrawal','owner_trainer_id'=>7,'next_due_on'=>$today,'occurred_on'=>$today,'contact_path'=>$reason,'customer_request'=>'LOCALHOST one returned shirt','policy_evidence'=>$reason,'request_key'=>'stock-accounting-return'];
    $case=shopReturnOpen($pdo,$input,7,$reason,true);
    $check((int)$case['original_amount_minor']===12500,'Course-only discount incorrectly reduced goods return.');
    $received=shopReturnReceive($pdo,(int)$case['id'],(int)$case['revision'],'owned',$today,7,$reason,true,'stock-accounting-receive');
    $replay=shopReturnReceive($pdo,(int)$case['id'],(int)$case['revision'],'owned',$today,7,$reason,true,'stock-accounting-receive');
    $check($received['return_movement_id']===$replay['return_movement_id'],'Physical return repeated.');
    $after=shopInventoryBalance($pdo,$variant);
    $check($after['physical_quantity']===$before['physical_quantity']+1&&$after['blocked_quantity']===$before['blocked_quantity']+1&&$after['reserved_quantity']===$before['reserved_quantity']&&$after['available_quantity']===$before['available_quantity'],'Returned goods must be blocked until inspected.');
    $check($moneyCount===(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn(),'Physical return manufactured cash.');
    $case=shopReturnDispose($pdo,(int)$case['id'],(int)$received['revision'],'unblock',$today,7,$reason,true,'stock-accounting-unblock');
    $check(shopInventoryBalance($pdo,$variant)['available_quantity']===$before['available_quantity']+1,'Inspected return was not made available.');
    $line='item:'.$issued['order_item_id'];
    $correction=accountingDocumentCreateCorrection($pdo,$invoiceId,[['line_key'=>$line,'quantity'=>1,'amount_minor'=>12500]],'stock-accounting-correction',$reason);
    accountingDocumentIssue($pdo,$correction,$reason);
    $movement=$movements[2];$q=$pdo->prepare('SELECT id FROM accounting_allocations WHERE movement_id=? AND amount_minor>0');$q->execute([$movement['id']]);$allocation=(int)$q->fetchColumn();
    accountingLedgerAllocate($pdo,(int)$movement['id'],'shop_order',(int)$order['id'],-12500,'stock-accounting-release',7,$reason,true,$allocation,'','',$today);
    $decision=accountingRefundDecide($pdo,['original_movement_id'=>(int)$movement['id'],'original_document_id'=>$invoiceId,'correction_document_id'=>$correction,'amount_minor'=>12500,'decided_on'=>$today,'request_key'=>'stock-accounting-decision'],7,$reason,true);
    $case=shopReturnFinancialResolution($pdo,(int)$case['id'],(int)$case['revision'],'adjustment',$correction,(int)$decision['id'],$reason,7,$reason,true,'stock-accounting-finance');
    try{shopReturnClose($pdo,(int)$case['id'],(int)$case['revision'],7,$reason,true,'stock-accounting-close');throw new RuntimeException('Unpaid refund closed the return.');}
    catch(DomainException $e){$check(str_contains($e->getMessage(),'vyplacena'),'Unexpected close refusal.');}
    $payment=['account_id'=>$accountId,'amount_minor'=>12500,'effective_on'=>$today,'source_reference'=>'LOCALHOST-stock-refund','request_key'=>'stock-accounting-payout','evidence'=>$reason];
    $paid=accountingRefundPay($pdo,(int)$decision['id'],$payment,7,$reason,true);
    $check($paid['id']===accountingRefundPay($pdo,(int)$decision['id'],$payment,7,$reason,true)['id'],'Actual stock refund duplicated money.');
    $case=shopReturnClose($pdo,(int)$case['id'],(int)$case['revision'],7,$reason,true,'stock-accounting-close');
    $check($case['state']==='closed','Paid and inspected return did not close.');
    $check(shopFulfillmentRefundCommittedMinor($pdo,(int)$order['id'])===13500,'Receipt-linked refund commitment lost the goods or price correction.');
    $balance=accountingAdjustmentDocumentBalance($pdo,$invoiceId);$check($balance['claim_minor']===40499&&$balance['allocated_minor']===40499&&$balance['outstanding_minor']===0,'Goods return did not reconcile invoice and allocation.');
    $stockDoc=shopStockDocumentCreate($pdo,(int)$case['return_movement_id'],7,$reason,true);
    $export=accountingExportCreate($pdo,$today,$today,'stock-accounting-export',7,$reason,true);$snapshot=json_decode($export['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
    $check(array_sum(array_column($snapshot['movements'],'amount_minor'))===41000,'Stock data inflated cash in accounting export.');
    $check(array_sum(array_column($snapshot['documents'],'total_minor'))===40499,'Export lost the goods correction.');
    $stock=array_values(array_filter($snapshot['stock_balances'],static fn(array $row):bool=>(int)$row['id']===$variant))[0]['closing'];
    $check($stock['physical']===3&&$stock['reserved']===1&&$stock['blocked']===0&&$stock['available']===2&&!$stock['invalid'],'Historical stock export does not reconcile with handoff and return.');
    $zip=new ZipArchive();$file=accountingExportDownload($pdo,(int)$export['id'],7);$check($zip->open($file['path'],ZipArchive::RDONLY)===true,'Cannot inspect stock export.');
    try{$bytes=$zip->getFromName('sklad/SK-'.$case['return_movement_id'].'.pdf');$check(is_string($bytes)&&hash('sha256',$bytes)===$stockDoc['pdf_sha256'],'Private return PDF missing from accounting archive.');}finally{$zip->close();}
    $check((int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active'")->fetchColumn()===2,'Goods return changed either course enrollment.');
    echo "Stock issue -> owned blocked return -> inspection -> correction -> actual refund -> stock PDF/accounting ZIP acceptance OK\n";
}
