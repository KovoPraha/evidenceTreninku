<?php
declare(strict_types=1);

/** Only the legacy people and the history reachable from them are reset.
 * Catalogues, trainers, login accounts and system configuration are not roots.
 */
function kisPersonCutoverPlan(PDO $pdo, bool $lock = false): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('Cutover requires MariaDB.');
    }
    $columns = $pdo->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
    $schema = [];
    foreach ($columns as $c) {
        $t = $c['TABLE_NAME']; $n = $c['COLUMN_NAME'];
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $t.$n)) throw new RuntimeException('Unsupported database identifier.');
        $schema[$t]['columns'][] = $n;
        if ($c['COLUMN_KEY'] === 'PRI') $schema[$t]['pk'][] = $n;
    }
    $foreign = $pdo->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
    // This database uses single-column foreign keys. Fail closed on future composite keys.
    $constraintCounts = [];
    foreach ($foreign as $f) $constraintCounts[$f['TABLE_NAME'].'.'.$f['CONSTRAINT_NAME']] = ($constraintCounts[$f['TABLE_NAME'].'.'.$f['CONSTRAINT_NAME']] ?? 0) + 1;
    if ($constraintCounts && max($constraintCounts) > 1) throw new RuntimeException('Composite foreign key requires cutover review.');
    $tables = []; $selected = [];
    $load = static function(string $table) use (&$tables, $pdo, $lock, $schema): array {
        if (!isset($schema[$table])) return [];
        if (!isset($tables[$table])) {
            $pk = $schema[$table]['pk'] ?? [];
            $order = $pk ? ' ORDER BY `'.implode('`,`',$pk).'`' : '';
            $tables[$table] = $pdo->query("SELECT * FROM `$table`".$order.($lock ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
        }
        return $tables[$table];
    };
    $roots = $load('sportovci');
    foreach ($roots as $i => $_) $selected['sportovci'][$i] = true;
    $personIds = array_fill_keys(array_column($roots,'id'), true);
    // Legacy tables without declared foreign keys also belong to this cutover.
    foreach ($schema as $table => $s) {
        if (str_starts_with($table,'kis_person_')) continue;
        $personColumns = array_filter($s['columns'], static fn($c) => $c === 'sportovec_id' || str_ends_with($c,'_sportovec_id'));
        if (!$personColumns) continue;
        foreach ($load($table) as $i => $row) {
            foreach ($personColumns as $c) if (isset($personIds[$row[$c] ?? ''])) $selected[$table][$i] = true;
        }
    }
    // Remove whole affected orders, not just lines leaving false order totals.
    $parentRules = [
        ['club_event_order_items','order_id','shop_orders','id'],
        ['public_velodrome_order_items','order_id','shop_orders','id'],
        ['shop_order_items','order_id','shop_orders','id'],
    ];
    for ($iteration=0; $iteration<100; $iteration++) {
        $before = array_sum(array_map('count',$selected));
        foreach ($parentRules as [$child,$column,$parent,$parentColumn]) {
            if (empty($selected[$child]) || !in_array($column,$schema[$child]['columns'] ?? [],true)) continue;
            $values=[];
            foreach ($selected[$child] as $i=>$_) $values[(string)$tables[$child][$i][$column]]=true;
            foreach ($load($parent) as $i=>$row) if (isset($values[(string)$row[$parentColumn]])) $selected[$parent][$i]=true;
        }
        foreach ($foreign as $f) {
            $parent=$f['REFERENCED_TABLE_NAME']; $child=$f['TABLE_NAME'];
            if (empty($selected[$parent])) continue;
            $values=[];
            foreach($selected[$parent] as $i=>$_) $values[(string)$tables[$parent][$i][$f['REFERENCED_COLUMN_NAME']]]=true;
            foreach($load($child) as $i=>$row) {
                if ($row[$f['COLUMN_NAME']] !== null && isset($values[(string)$row[$f['COLUMN_NAME']]])) $selected[$child][$i]=true;
            }
        }
        if (array_sum(array_map('count',$selected)) === $before) break;
        if ($iteration===99) throw new RuntimeException('Cutover graph did not converge.');
    }
    // Never remove accounts or configuration through an unexpected future FK.
    foreach (['treneri','verejni_uzivatele','shop_products','shop_variants','club_teams','club_program_offers','kis_person_batches'] as $protected) {
        if (!empty($selected[$protected])) throw new RuntimeException('Cutover reached protected table: '.$protected);
    }
    $remaining=array_keys(array_filter($selected)); $order=[];
    while ($remaining) {
        $ready=[];
        foreach ($remaining as $table) {
            $hasChild=false;
            foreach ($foreign as $f) {
                if ($f['REFERENCED_TABLE_NAME']===$table && $f['TABLE_NAME']!==$table && in_array($f['TABLE_NAME'],$remaining,true)) $hasChild=true;
                if ($f['REFERENCED_TABLE_NAME']===$table && $f['TABLE_NAME']===$table) throw new RuntimeException('Self-referencing history requires cutover review.');
            }
            if (!$hasChild) $ready[]=$table;
        }
        if (!$ready) throw new RuntimeException('Cyclic history requires cutover review.');
        sort($ready);$order=array_merge($order,$ready);$remaining=array_values(array_diff($remaining,$ready));
    }
    $plan=[]; $digest=hash_init('sha256');
    foreach ($order as $table) {
        $pk=$schema[$table]['pk'] ?? [];
        if (!$pk) throw new RuntimeException('History table has no primary key: '.$table);
        $keys=[];
        foreach ($selected[$table] as $i=>$_) {
            $row=$tables[$table][$i];
            hash_update($digest,$table."\0".serialize($row)."\n");
            $keys[]=array_intersect_key($row,array_fill_keys($pk,true));
        }
        $plan[]=['table'=>$table,'keys'=>$keys,'count'=>count($keys)];
    }
    return ['fingerprint'=>hash_final($digest),'tables'=>$plan,'people'=>count($roots),
        'rows'=>array_sum(array_column($plan,'count'))];
}

function kisPersonCutoverDelete(PDO $pdo, array $plan): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Cutover requires transaction.');
    foreach ($plan['tables'] as $table) {
        $keys=$table['keys']; if (!$keys) continue;
        $columns=array_keys($keys[0]);
        $sql='DELETE FROM `'.$table['table'].'` WHERE '.implode(' AND ',array_map(static fn($c)=>"`$c`=?",$columns));
        $delete=$pdo->prepare($sql);
        foreach ($keys as $key) {
            $delete->execute(array_values($key));
            if ($delete->rowCount()!==1) throw new RuntimeException('Cutover row changed.');
        }
    }
}
