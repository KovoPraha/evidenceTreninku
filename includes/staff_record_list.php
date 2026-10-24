<?php
declare(strict_types=1);

/** Read-only list helpers. SQL expressions are supplied by the page, never by request data. */
function staffListText(array $input, string $key): string
{
    return is_scalar($input[$key] ?? null) ? trim((string)$input[$key]) : '';
}

function staffListFilters(array $input, string $prefix, array $states): array
{
    $f = ['prefix'=>$prefix, 'states'=>$states, 'q'=>mb_substr(staffListText($input,$prefix.'q'),0,100),
        'state'=>staffListText($input,$prefix.'state'), 'from'=>staffListText($input,$prefix.'from'),
        'to'=>staffListText($input,$prefix.'to'), 'page'=>max(1,min(1000000,(int)staffListText($input,$prefix.'page'))), 'error'=>''];
    if ($f['state'] !== '' && !array_key_exists($f['state'],$states)) $f['error']='Vyberte stav z nabídky.';
    foreach (['from','to'] as $key) {
        if ($f[$key] === '') continue;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$f[$key]);
        if (!$date || $date->format('Y-m-d') !== $f[$key]) $f['error']='Zadejte platné datum filtru.';
    }
    if ($f['from']!=='' && $f['to']!=='' && $f['from']>$f['to']) $f['error']='Začátek filtru musí být před koncem.';
    return $f;
}

function staffListWhere(array $f, array $searchColumns, string $dateColumn, ?string $stateColumn, array &$params): string
{
    if ($f['error']!=='') return '0=1';
    $where=['1=1'];
    foreach (preg_split('/\s+/u',$f['q'],-1,PREG_SPLIT_NO_EMPTY) as $word) {
        $or=[];
        foreach ($searchColumns as $column) { $or[]="$column LIKE ? ESCAPE '!'"; $params[]='%'.strtr($word,['!'=>'!!','%'=>'!%','_'=>'!_']).'%'; }
        if ($or) $where[]='('.implode(' OR ',$or).')';
    }
    if ($f['from']!=='') { $where[]="$dateColumn>=?"; $params[]=$f['from']; }
    if ($f['to']!=='') { $where[]="$dateColumn<=?"; $params[]=$f['to']; }
    if ($stateColumn!==null && $f['state']!=='') { $where[]="$stateColumn=?"; $params[]=$f['state']; }
    return implode(' AND ',$where);
}

function staffListPage(PDO $pdo, string $select, string $fromWhere, string $order, array $params, int $page): array
{
    $q=$pdo->prepare('SELECT COUNT(*) '.$fromWhere);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/25));$page=max(1,min($page,$pages));
    $q=$pdo->prepare('SELECT '.$select.' '.$fromWhere.' ORDER BY '.$order.' LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);
    return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages];
}

function staffListH(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

function staffListControls(array $f, array $result, string $dateLabel, array $context=[]): void
{
    $prefix=$f['prefix'];
    ?><form method="get" class="row g-2 mb-3" aria-label="Filtry přehledu">
    <?php foreach ($context as $key=>$value): ?><input type="hidden" name="<?=staffListH($key)?>" value="<?=staffListH($value)?>"><?php endforeach;?>
    <label class="col-md-4">Hledat jméno, označení nebo číslo<input class="form-control" name="<?=$prefix?>q" maxlength="100" value="<?=staffListH($f['q'])?>"></label>
    <label class="col-md-2">Stav<select class="form-select" name="<?=$prefix?>state"><option value="">Všechny stavy</option><?php foreach($f['states'] as $key=>$label):?><option value="<?=staffListH($key)?>" <?=$key===$f['state']?'selected':''?>><?=staffListH($label)?></option><?php endforeach;?></select></label>
    <label class="col-md-2"><?=staffListH($dateLabel)?> od<input class="form-control" type="date" name="<?=$prefix?>from" value="<?=staffListH($f['from'])?>"></label>
    <label class="col-md-2">Do<input class="form-control" type="date" name="<?=$prefix?>to" value="<?=staffListH($f['to'])?>"></label>
    <div class="col-md-2 align-self-end"><button class="btn btn-primary">Vyhledat</button></div></form>
    <?php if($f['error']!==''):?><p class="alert alert-warning"><?=staffListH($f['error'])?></p><?php endif;?>
    <p role="status">Nalezeno: <?=(int)$result['total']?> · strana <?=(int)$result['page']?> z <?=(int)$result['pages']?></p>
    <?php
    $base=$context;foreach(['q','state','from','to'] as $key)$base[$prefix.$key]=$f[$key];
    foreach(['Předchozí'=>$result['page']-1,'Další'=>$result['page']+1] as $label=>$page) {
        if($page<1||$page>$result['pages'])continue;
        echo '<a class="btn btn-outline-secondary btn-sm mb-3 me-2" href="?'.staffListH(http_build_query($base+[$prefix.'page'=>$page])).'">'.$label.'</a>';
    }
    if($result['total']===0)echo '<p>Pro vybrané filtry není žádný záznam. Změňte hledání nebo období.</p>';
}
