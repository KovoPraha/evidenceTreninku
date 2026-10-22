<?php
declare(strict_types=1);
require_once __DIR__.'/shop_inventory.php';
require_once __DIR__.'/accounting_ledger.php';
require_once __DIR__.'/private_storage.php';

/** Reconstruct historical stock from immutable events, never from today's cached balance. */
function accountingStockSnapshot(PDO $pdo,string $from,string $to):array
{
    $result=['stock_balances'=>[],'stock_movements'=>[],'stock_cost_revisions'=>[],'stock_lots'=>[],'stock_lot_uses'=>[],'stock_documents'=>[],'stock_exceptions'=>[]];
    if(!shopInventoryTable($pdo)){$result['stock_exceptions'][]='Skladová evidence dosud není dostupná.';return $result;}
    $rows=static function(string $sql,array $args=[])use($pdo):array{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);};
    $variants=$rows("SELECT v.id,v.sku,v.unit_code,p.name FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='goods' ORDER BY v.id");
    $moves=$rows('SELECT * FROM shop_stock_movements WHERE effective_on<=? ORDER BY effective_on,id',[$to]);
    $lots=$rows('SELECT * FROM shop_stock_lots WHERE effective_on<=? ORDER BY id',[$to]);
    $uses=$rows('SELECT u.*,m.effective_on FROM shop_stock_lot_uses u JOIN shop_stock_movements m ON m.id=u.movement_id WHERE m.effective_on<=? ORDER BY u.id',[$to]);
    // Later revisions are needed to recover the initial price from their previous value.
    $revisions=$rows('SELECT * FROM shop_stock_cost_revisions ORDER BY id');
    $before=(new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
    foreach($variants as $v){
        $id=(int)$v['id'];$vm=array_values(array_filter($moves,static fn($m)=>(int)$m['variant_id']===$id));
        $at=static function(string $date)use($vm,$lots,$uses,$revisions,$id):array{
            $p=0;$r=0;$b=0;$known=false;$invalid=false;
            foreach($vm as $m)if($m['effective_on']<=$date){if($m['kind']==='opening')$known=true;$p+=(int)$m['physical_delta'];$r+=(int)$m['reserved_delta'];$b+=(int)$m['blocked_delta'];if(min($p,$r,$b,$p-$r-$b)<0)$invalid=true;}
            if(!$known)return ['physical'=>null,'reserved'=>null,'blocked'=>null,'available'=>null,'value_minor'=>null,'unknown_cost_quantity'=>null,'invalid'=>false];
            $value=0;$unknown=0;$lotQty=0;
            foreach($lots as $lot)if((int)$lot['variant_id']===$id&&$lot['effective_on']<=$date){
                $qty=(int)$lot['original_quantity'];foreach($uses as $use)if((int)$use['lot_id']===(int)$lot['id']&&$use['effective_on']<=$date)$qty-=(int)$use['quantity'];
                if($qty<0)$invalid=true;$lotQty+=$qty;$cost=$lot['unit_cost_minor'];$lr=array_values(array_filter($revisions,static fn($rev)=>(int)$rev['lot_id']===(int)$lot['id']));
                if($lr)$cost=$lr[0]['previous_unit_cost_minor'];
                foreach($lr as $rev)if($rev['effective_on']<=$date)$cost=$rev['unit_cost_minor'];
                if($cost===null)$unknown+=$qty;else$value+=$qty*(int)$cost;
            }
            if($lotQty!==$p)$invalid=true;
            return ['physical'=>$p,'reserved'=>$r,'blocked'=>$b,'available'=>$p-$r-$b,'value_minor'=>$unknown>0||$invalid?null:$value,'unknown_cost_quantity'=>$unknown,'invalid'=>$invalid];
        };
        $opening=$at($before);$closing=$at($to);
        $result['stock_balances'][]=['id'=>$id,'sku'=>$v['sku'],'name'=>$v['name'],'unit'=>$v['unit_code'],'opening'=>$opening,'closing'=>$closing];
        if($closing['physical']===null)$result['stock_exceptions'][]='SKU '.$v['sku'].': chybí ověřený fyzický počátek ke konci období.';
        elseif($closing['value_minor']===null)$result['stock_exceptions'][]='SKU '.$v['sku'].': ocenění není úplné (neznámá cena nebo nesoulad historických šarží).';
        if($opening['invalid']||$closing['invalid'])$result['stock_exceptions'][]='SKU '.$v['sku'].': časová posloupnost pohybů nebo šarže vyžadují kontrolu.';
    }
    $result['stock_movements']=$moves;
    // Export immutable source quantities and original costs; mutable current lot balances are deliberately excluded.
    foreach($lots as $lot){$initial=$lot['unit_cost_minor'];foreach($revisions as $rev)if((int)$rev['lot_id']===(int)$lot['id']){$initial=$rev['previous_unit_cost_minor'];break;}$result['stock_lots'][]=['id'=>(int)$lot['id'],'variant_id'=>(int)$lot['variant_id'],'source_movement_id'=>(int)$lot['source_movement_id'],'original_quantity'=>(int)$lot['original_quantity'],'original_unit_cost_minor'=>$initial===null?null:(int)$initial,'effective_on'=>$lot['effective_on'],'supplier'=>$lot['supplier'],'supplier_reference'=>$lot['supplier_reference']];}
    $result['stock_lot_uses']=$uses;
    $result['stock_cost_revisions']=array_values(array_filter($revisions,static fn($r)=>$r['effective_on']<=$to));
    if(accountingLedgerTable($pdo,'shop_stock_documents')){
        $result['stock_documents']=$rows('SELECT d.*,d.movement_id AS id,d.storage_key AS pdf_storage_key FROM shop_stock_documents d JOIN shop_stock_movements m ON m.id=d.movement_id WHERE m.effective_on<=? ORDER BY d.movement_id',[$to]);
        foreach($result['stock_documents'] as &$doc){$path=privateStorageResolve($doc['storage_key']);$valid=$path!==null&&hash_equals($doc['pdf_sha256'],hash_file('sha256',$path))&&hash_equals($doc['snapshot_sha256'],hash('sha256',$doc['snapshot_json']));$doc['archive_path']=$valid?'sklad/SK-'.$doc['id'].'.pdf':null;if(!$valid)$result['stock_exceptions'][]='Chybějící nebo poškozený skladový doklad SK-'.$doc['id'].'.';}unset($doc);
    }
    return $result;
}

function accountingStockWorkbookRows(array $snapshot):array
{
    $rows=[];
    foreach($snapshot['stock_balances']??[] as $v)foreach(['opening'=>'POČÁTEK','closing'=>'KONEC'] as $key=>$label){$b=$v[$key];$rows[]=[$label,(string)$v['id'],$v['sku'],$v['name'],$key==='opening'?$snapshot['period_from']:$snapshot['period_to'],$b['physical'],$b['reserved'],$b['blocked'],$b['available'],$b['value_minor']===null?null:$b['value_minor']/100,$b['unknown_cost_quantity'],'Stav; počátek před prvním dnem období, nevstupuje do peněžního obratu'];}
    foreach($snapshot['stock_movements']??[] as $m)$rows[]=['POHYB',(string)$m['id'],(string)$m['variant_id'],$m['kind'],$m['effective_on'],(int)$m['physical_delta'],(int)$m['reserved_delta'],(int)$m['blocked_delta'],(int)$m['physical_delta']-(int)$m['reserved_delta']-(int)$m['blocked_delta'],$m['cost_minor']===null?null:(int)$m['cost_minor']/100,null,($m['effective_on']<$snapshot['period_from']?'MIMO OBRAT — ':'V OBDOBÍ — ').$m['reason'].'; '.$m['meta_json']];
    foreach($snapshot['stock_cost_revisions']??[] as $r)$rows[]=['OPRAVA CENY',(string)$r['id'],(string)$r['lot_id'],'Cena za kus',$r['effective_on'],null,null,null,null,(int)$r['unit_cost_minor']/100,null,'Původně '.($r['previous_unit_cost_minor']===null?'neznámé':(string)$r['previous_unit_cost_minor']).' hal.; '.$r['evidence'].'; nejde o peněžní výdaj'];
    foreach($snapshot['stock_lots']??[] as $l)$rows[]=['DÁVKA',(string)$l['id'],(string)$l['variant_id'],'Příjem #'.$l['source_movement_id'],$l['effective_on'],$l['original_quantity'],null,null,null,$l['original_unit_cost_minor']===null?null:$l['original_unit_cost_minor']/100,null,'Původní cena za kus; dodavatel '.$l['supplier'].' / '.$l['supplier_reference'].'; analytická vazba, nesčítat se stavy'];
    foreach($snapshot['stock_lot_uses']??[] as $u)$rows[]=['ČERPÁNÍ DÁVKY',(string)$u['id'],(string)$u['lot_id'],'Výdej #'.$u['movement_id'],$u['effective_on'],(int)$u['quantity'],null,null,null,$u['unit_cost_minor']===null?null:(int)$u['unit_cost_minor']/100,null,'Původní cena vydaného kusu; případnou pozdější opravu sledujte podle ID dávky; nesčítat se stavy'];
    return $rows?:[['NEZAPOJENO',null,null,'Chybí ověřený sklad',null,null,null,null,null,null,null,'Neznámý stav není nula.']];
}
