<?php
declare(strict_types=1);

require_once __DIR__.'/shop_category.php';

final class ShopNumberSeriesException extends RuntimeException {}

function shopNumberSeriesLockSuffix(PDO $pdo): string
{
    return $pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
}

function shopNumberSeriesInteger(mixed $value, int $min, int $max): int
{
    $number=filter_var($value,FILTER_VALIDATE_INT);
    if ($number===false||$number<$min||$number>$max) throw new InvalidArgumentException('Zadejte celé číslo v rozsahu '.$min.'–'.$max.'.');
    return $number;
}

function shopNumberSeriesTableExists(PDO $pdo, string $table): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
        $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else $s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $s->execute([$table]);return (bool)$s->fetchColumn();
}

function shopNumberSeriesLock(PDO $pdo): int
{
    if (!$pdo->inTransaction()) throw new LogicException('Číselná řada vyžaduje transakci.');
    $s=$pdo->query('SELECT revision FROM shop_number_series_settings WHERE id=1'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
    $revision=$s->fetchColumn();
    if ($revision===false) throw new ShopNumberSeriesException('Chybí nastavení číselných řad.');
    return (int)$revision;
}

function shopNumberSeriesCategories(PDO $pdo): array
{
    return $pdo->query('SELECT c.*,g.name AS group_name FROM shop_accounting_categories c JOIN shop_accounting_groups g ON g.code=c.group_code ORDER BY c.group_code,c.category_code')->fetchAll(PDO::FETCH_ASSOC);
}

function shopNumberSeriesCategory(PDO $pdo, int $categoryId): array
{
    $s=$pdo->prepare('SELECT c.*,g.name AS group_name FROM shop_accounting_categories c JOIN shop_accounting_groups g ON g.code=c.group_code WHERE c.id=?'.shopNumberSeriesLockSuffix($pdo));$s->execute([$categoryId]);
    $row=$s->fetch(PDO::FETCH_ASSOC);
    if (!$row || !(int)$row['active']) throw new ShopNumberSeriesException('Účetní kategorie chybí nebo je neaktivní. Opravte mapování v číselných řadách.');
    return $row;
}

function shopNumberSeriesFormat(int $year, int $group, string $category, int $sequence): array
{
    if ($sequence>99999) throw new ShopNumberSeriesException('Číselná řada pro tento rok je vyčerpaná (99999 čísel). Obraťte se na hospodáře.');
    if ($year<2000||$year>2099||$group<1||$group>9||preg_match('/^[0-9]{2}$/D',$category)!==1||$sequence<1||$sequence>99999) throw new ShopNumberSeriesException('Řada vyžaduje rok 2000–2099, nadkategorii 1–9, kategorii 00–99 a pořadí 1–99999.');
    return ['variable_symbol'=>sprintf('%02d%d%s%05d',$year%100,$group,$category,$sequence), 'number_snapshot'=>sprintf('%04d-%d-%s-%05d',$year,$group,$category,$sequence)];
}

function shopNumberSeriesMapping(PDO $pdo, string $type, string $key): ?int
{
    $s=$pdo->prepare('SELECT category_id FROM shop_accounting_mappings WHERE mapping_type=? AND mapping_key=?'.shopNumberSeriesLockSuffix($pdo));$s->execute([$type,$key]);$id=$s->fetchColumn();
    return $id===false?null:(int)$id;
}

/** Explicit product > default catalogue category and nearest ancestor > kind. */
function shopNumberSeriesClassify(PDO $pdo, ?int $productId, string $kind): array
{
    $id=null;$path=null;
    if ($productId!==null) {
        $id=shopNumberSeriesMapping($pdo,'product',(string)$productId);
        if (shopNumberSeriesTableExists($pdo,'shop_product_categories')) {
            $s=$pdo->prepare('SELECT category_path,is_default FROM shop_product_categories WHERE product_id=? ORDER BY is_default DESC,sort_order,id'.shopNumberSeriesLockSuffix($pdo));$s->execute([$productId]);$paths=$s->fetchAll(PDO::FETCH_ASSOC);
            $defaults=array_values(array_filter($paths,static fn(array $p):bool=>(int)$p['is_default']===1));
            if ($id===null&&(count($defaults)>1||(count($paths)>1&&$defaults===[]))) throw new ShopNumberSeriesException('Produkt nemá jednoznačnou výchozí kategorii. Nastavte jeho účetní kategorii.');
            $path=$defaults[0]['category_path']??($paths[0]['category_path']??null);
        }
        if ($id===null&&$path!==null) {
            $cursor=(string)$path;$seen=[];
            while ($cursor!=='') {
                if (isset($seen[$cursor])) throw new ShopNumberSeriesException('Kategorie obsahují cyklus.');
                $seen[$cursor]=true;$id=shopNumberSeriesMapping($pdo,'catalog',$cursor);
                if ($id!==null) break;
                $parent=shopCategoryDerivedParent($cursor);
                if (shopNumberSeriesTableExists($pdo,'shop_category_meta')) {
                    $s=$pdo->prepare('SELECT parent_path FROM shop_category_meta WHERE category_path=?'.shopNumberSeriesLockSuffix($pdo));$s->execute([$cursor]);$meta=$s->fetch(PDO::FETCH_ASSOC);
                    if ($meta) $parent=$meta['parent_path'];
                }
                $cursor=(string)($parent??'');
            }
        }
    }
    $id??=shopNumberSeriesMapping($pdo,'kind',$kind);
    if ($id===null) throw new ShopNumberSeriesException('Chybí výchozí účetní zařazení položky.');
    $category=shopNumberSeriesCategory($pdo,$id);
    if ((int)$category['group_code']===9) throw new ShopNumberSeriesException('Smíšená řada je vyhrazena celé objednávce, nikoli jednotlivé položce.');
    return $category+['catalog_path'=>$path];
}

/** Called after all three kinds of order items exist, before payment/QR creation. */
function shopOrderAssignVariableSymbol(PDO $pdo, int $orderId, ?DateTimeImmutable $now=null): string
{
    if (!shopNumberSeriesTableExists($pdo,'shop_number_series_settings')) return shopPaymentVariableSymbol($orderId);
    shopNumberSeriesLock($pdo);
    // Existing payments always win, including pre-migration orders.
    $s=$pdo->prepare("SELECT variable_symbol FROM payments WHERE payable_type='shop_order' AND payable_id=?".shopNumberSeriesLockSuffix($pdo));$s->execute([$orderId]);$existing=$s->fetchColumn();
    if ($existing!==false) return (string)$existing;
    $s=$pdo->prepare('SELECT variable_symbol FROM shop_order_number_assignments WHERE order_id=?'.shopNumberSeriesLockSuffix($pdo));$s->execute([$orderId]);$existing=$s->fetchColumn();
    if ($existing!==false) return (string)$existing;
    $lines=[];
    $s=$pdo->prepare('SELECT i.id,i.product_id,p.offer_type FROM shop_order_items i JOIN shop_products p ON p.id=i.product_id WHERE i.order_id=? ORDER BY i.id');$s->execute([$orderId]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $item) $lines[]=['type'=>'product','id'=>(int)$item['id'],'category'=>shopNumberSeriesClassify($pdo,(int)$item['product_id'],$item['offer_type']==='program'?'program':'goods')];
    foreach (['event'=>'club_event_order_items','velodrome'=>'public_velodrome_order_items'] as $type=>$table) {
        if (!shopNumberSeriesTableExists($pdo,$table)) continue;
        $s=$pdo->prepare('SELECT id FROM '.$table.' WHERE order_id=? ORDER BY id');$s->execute([$orderId]);
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $itemId) $lines[]=['type'=>$type,'id'=>(int)$itemId,'category'=>shopNumberSeriesClassify($pdo,null,$type)];
    }
    if ($lines===[]) throw new ShopNumberSeriesException('Objednávka nemá položky pro přidělení číselné řady.');
    $categoryIds=array_unique(array_map(static fn(array $line):int=>(int)$line['category']['id'],$lines));
    if (count($categoryIds)===1) $category=$lines[0]['category'];
    else { $mixed=$pdo->query("SELECT id FROM shop_accounting_categories WHERE group_code=9 AND category_code='00'")->fetchColumn();$category=shopNumberSeriesCategory($pdo,(int)$mixed); }
    $year=(int)shopCheckoutPragueNow($now)->format('Y');
    $s=$pdo->prepare('SELECT next_number FROM shop_number_series_counters WHERE category_id=? AND series_year=?'.shopNumberSeriesLockSuffix($pdo));$s->execute([$category['id'],$year]);$next=$s->fetchColumn();
    if ($next===false) {$next=1;$pdo->prepare('INSERT INTO shop_number_series_counters(category_id,series_year,next_number) VALUES(?,?,1)')->execute([$category['id'],$year]);}
    do {
        $number=shopNumberSeriesFormat($year,(int)$category['group_code'],(string)$category['category_code'],(int)$next);
        $used=false;
        foreach (['payments','shop_order_number_assignments','member_fee_standing_orders','member_payer_variable_symbols'] as $table) {
            if (!shopNumberSeriesTableExists($pdo,$table)) continue;
            $s=$pdo->prepare('SELECT 1 FROM '.$table.' WHERE variable_symbol=? LIMIT 1'.shopNumberSeriesLockSuffix($pdo));$s->execute([$number['variable_symbol']]);
            if ($s->fetchColumn()) {$used=true;break;}
        }
        $sequence=(int)$next++;
    } while ($used);
    $pdo->prepare('UPDATE shop_number_series_counters SET next_number=? WHERE category_id=? AND series_year=?')->execute([$next,$category['id'],$year]);
    $pdo->prepare('INSERT INTO shop_order_number_assignments(order_id,category_id,series_year,sequence_number,variable_symbol,number_snapshot,group_code,category_code,group_name_snapshot,category_name_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute([$orderId,$category['id'],$year,$sequence,$number['variable_symbol'],$number['number_snapshot'],$category['group_code'],$category['category_code'],$category['group_name'],$category['name']]);
    foreach ($lines as $line) {
        $c=$line['category'];
        $pdo->prepare('INSERT INTO shop_order_accounting_items(order_id,source_type,source_item_id,category_id,group_code,category_code,group_name_snapshot,category_name_snapshot,catalog_path_snapshot) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$orderId,$line['type'],$line['id'],$c['id'],$c['group_code'],$c['category_code'],$c['group_name'],$c['name'],$c['catalog_path']]);
    }
    return $number['variable_symbol'];
}

/** Optimistic revision prevents an old admin form from overwriting newer settings. */
function shopNumberSeriesSave(PDO $pdo, int $actorId, array $input, string $reason, bool $confirmed): void
{
    $reason=trim($reason);
    if ($actorId<1||!$confirmed||mb_strlen($reason)<3||mb_strlen($reason)>1000) throw new InvalidArgumentException('Vyplňte důvod (3–1000 znaků) a potvrďte změnu.');
    if ($pdo->inTransaction()) throw new LogicException('Správa řad vyžaduje vlastní transakci.');
    $pdo->beginTransaction();
    try {
        $revision=shopNumberSeriesLock($pdo);
        if (!isset($input['revision'])||(string)$revision!==(string)$input['revision']) throw new ShopNumberSeriesException('Nastavení mezitím změnil jiný správce. Obnovte stránku.');
        $action=(string)($input['action']??'');$before=[];$after=[];
        if ($action==='group') {
            $code=filter_var($input['group_code']??null,FILTER_VALIDATE_INT);$name=trim((string)($input['name']??''));
            if ($code===false||$code<1||$code>8||$name===''||mb_strlen($name)>120) throw new InvalidArgumentException('Nadkategorie vyžaduje kód 1–8 a název do 120 znaků. Kód 9 je vyhrazen.');
            $s=$pdo->prepare('SELECT * FROM shop_accounting_groups WHERE code=?');$s->execute([$code]);$before=$s->fetch(PDO::FETCH_ASSOC)?:[];
            if ($before) $pdo->prepare('UPDATE shop_accounting_groups SET name=? WHERE code=?')->execute([$name,$code]);
            else $pdo->prepare('INSERT INTO shop_accounting_groups(code,name) VALUES(?,?)')->execute([$code,$name]);
            $after=['code'=>$code,'name'=>$name];
        } elseif ($action==='category') {
            $id=shopNumberSeriesInteger($input['category_id']??0,0,PHP_INT_MAX);$group=filter_var($input['group_code']??null,FILTER_VALIDATE_INT);$code=(string)($input['category_code']??'');$name=trim((string)($input['name']??''));$active=($input['active']??'')==='1'?1:0;
            if ($group===false||$group<1||$group>8||preg_match('/^[0-9]{2}$/D',$code)!==1||$name===''||mb_strlen($name)>120) throw new InvalidArgumentException('Kategorie vyžaduje nadkategorii 1–8, dvoumístný kód a název do 120 znaků.');
            $s=$pdo->prepare('SELECT 1 FROM shop_accounting_groups WHERE code=?');$s->execute([$group]);if (!$s->fetchColumn()) throw new InvalidArgumentException('Nejprve vytvořte nadkategorii.');
            if ($id>0) {
                $s=$pdo->prepare('SELECT * FROM shop_accounting_categories WHERE id=?');$s->execute([$id]);$before=$s->fetch(PDO::FETCH_ASSOC)?:[];
                if (!$before||(int)$before['group_code']!==$group||$before['category_code']!==$code) throw new InvalidArgumentException('Kódy existující účetní kategorie nelze měnit. Vytvořte novou kategorii.');
                $s=$pdo->prepare('SELECT 1 FROM shop_accounting_mappings WHERE category_id=? LIMIT 1');$s->execute([$id]);
                if (!$active&&$s->fetchColumn()) throw new InvalidArgumentException('Nejprve změňte mapování používané kategorie.');
                $pdo->prepare('UPDATE shop_accounting_categories SET name=?,active=? WHERE id=?')->execute([$name,$active,$id]);
            } else {$pdo->prepare('INSERT INTO shop_accounting_categories(group_code,category_code,name,active) VALUES(?,?,?,?)')->execute([$group,$code,$name,$active]);$id=(int)$pdo->lastInsertId();}
            $after=['id'=>$id,'group_code'=>$group,'category_code'=>$code,'name'=>$name,'active'=>$active];
        } elseif ($action==='mapping') {
            $type=(string)($input['mapping_type']??'');$key=trim((string)($input['mapping_key']??''));$id=shopNumberSeriesInteger($input['category_id']??0,0,PHP_INT_MAX);
            if ($type==='kind') {if (!in_array($key,['goods','program','event','velodrome'],true)||$id<1) throw new InvalidArgumentException('Výchozí zařazení musí zůstat nastavené.');}
            elseif ($type==='product') {$s=$pdo->prepare('SELECT 1 FROM shop_products WHERE id=?');$s->execute([$key]);if (!ctype_digit($key)||(int)$key<1||!$s->fetchColumn()) throw new InvalidArgumentException('Vyberte existující produkt.');$key=(string)(int)$key;}
            elseif ($type==='catalog') {$key=shopCategoryPath($key);$nodes=shopCategoryNodes($pdo);if (!isset($nodes[$key])) throw new InvalidArgumentException('Vyberte existující katalogovou kategorii.');}
            else throw new InvalidArgumentException('Neplatný typ mapování.');
            if ($id>0&&((int)shopNumberSeriesCategory($pdo,$id)['group_code']===9)) throw new InvalidArgumentException('Smíšená řada se jednotlivým položkám nepřiřazuje.');
            $before=['type'=>$type,'key'=>$key,'category_id'=>shopNumberSeriesMapping($pdo,$type,$key)];
            $pdo->prepare('DELETE FROM shop_accounting_mappings WHERE mapping_type=? AND mapping_key=?')->execute([$type,$key]);
            if ($id>0) $pdo->prepare('INSERT INTO shop_accounting_mappings(mapping_type,mapping_key,category_id) VALUES(?,?,?)')->execute([$type,$key,$id]);
            $after=['type'=>$type,'key'=>$key,'category_id'=>$id?:null];
        } elseif ($action==='counter') {
            $id=shopNumberSeriesInteger($input['category_id']??0,1,PHP_INT_MAX);$c=shopNumberSeriesCategory($pdo,$id);$year=shopNumberSeriesInteger($input['series_year']??0,2000,2099);$next=shopNumberSeriesInteger($input['next_number']??0,1,99999);
            shopNumberSeriesFormat($year,(int)$c['group_code'],(string)$c['category_code'],$next);
            $s=$pdo->prepare('SELECT next_number FROM shop_number_series_counters WHERE category_id=? AND series_year=?');$s->execute([$id,$year]);$old=$s->fetchColumn();
            if ($old!==false&&$next<(int)$old) throw new InvalidArgumentException('Čítač nelze snížit ani znovu použít již přidělené číslo.');
            $before=['category_id'=>$id,'year'=>$year,'next_number'=>$old];$after=['category_id'=>$id,'year'=>$year,'next_number'=>$next];
            if ($old===false) $pdo->prepare('INSERT INTO shop_number_series_counters(category_id,series_year,next_number) VALUES(?,?,?)')->execute([$id,$year,$next]);
            else $pdo->prepare('UPDATE shop_number_series_counters SET next_number=? WHERE category_id=? AND series_year=?')->execute([$next,$id,$year]);
        } else throw new InvalidArgumentException('Neznámá změna číselných řad.');
        $pdo->prepare('INSERT INTO shop_number_series_events(actor_id,action,before_json,after_json,reason) VALUES(?,?,?,?,?)')->execute([$actorId,$action,json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$reason]);
        $pdo->exec('UPDATE shop_number_series_settings SET revision=revision+1 WHERE id=1');
        $pdo->commit();
    } catch (Throwable $e) {if ($pdo->inTransaction()) $pdo->rollBack();throw $e;}
}
