<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/kis_person_import.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    http_response_code(403); exit('Pouze pro administrátora.');
}
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
function kpiH(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$error='';$preview=null;$result=null;
$batch=$pdo->query('SELECT fingerprint,summary_json,created_at FROM kis_person_batches ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (($_GET['view']??'')==='protocol' && $batch) {
    $protocol=['batch'=>$batch,'aliases'=>$pdo->query('SELECT * FROM kis_person_aliases ORDER BY source_row')->fetchAll(PDO::FETCH_ASSOC),
        'profiles'=>$pdo->query('SELECT canonical_key,sportovec_id FROM kis_person_profiles ORDER BY canonical_key')->fetchAll(PDO::FETCH_ASSOC),
        'contacts'=>$pdo->query('SELECT contact_key,linked_sportovec_id,account_id,account_state FROM kis_person_contacts ORDER BY contact_key')->fetchAll(PDO::FETCH_ASSOC),
        'links'=>$pdo->query('SELECT * FROM kis_person_links ORDER BY source_key')->fetchAll(PDO::FETCH_ASSOC)];
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Protokol importu KIS</title><pre id="import-protocol">'.kpiH(kisPersonJson($protocol)).'</pre>';
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!csrf_verify($_POST['csrf_token']??'')) throw new RuntimeException('Neplatný bezpečnostní token.');
        if(($_FILES['payload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($_FILES['payload']['tmp_name'])
            || (int)$_FILES['payload']['size']>KIS_PERSON_IMPORT_LIMIT) throw new RuntimeException('Vyberte úplný importní soubor JSON nebo JSON.GZ.');
        $payload=kisPersonReadPayload((string)file_get_contents($_FILES['payload']['tmp_name']));
        personSensitiveConfig(); // Verify protected archive support before offering apply.
        $action=(string)($_POST['action']??'preview');
        if($action==='apply') {
            $saved=$_SESSION['kis_person_import_preview']??[];
            if (($_POST['confirmation']??'')!=='IMPORTOVAT A NAHRADIT'
                || !hash_equals((string)($saved['payload']??''),$payload['fingerprint'])
                || (int)($saved['time']??0)<time()-1800) throw new RuntimeException('Nejprve vytvořte aktuální náhled a potvrďte přesný rozsah.');
            // A disconnect must not interrupt an already confirmed transaction.
            ignore_user_abort(true);
            set_time_limit(900);
            $result=kisPersonImportApply($pdo,$payload,(int)$_SESSION['trener_id'],(string)$saved['database']);
            unset($_SESSION['kis_person_import_preview']);
            $batch=$pdo->query('SELECT fingerprint,summary_json,created_at FROM kis_person_batches ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        } elseif ($action==='preview') {
            if($batch) throw new RuntimeException('Import již byl proveden. Použijte protokol.');
            $plan=kisPersonCutoverPlan($pdo);
            $accounts=kisPersonAccountPlan($pdo,$payload['data']);
            $preview=kisPersonImportSummary($payload['data'])+['removed_people'=>$plan['people'],
                'removed_rows'=>$plan['rows'],'removed_by_table'=>array_column($plan['tables'],'count','table'),
                'account_states'=>array_count_values(array_column($accounts,'state'))];
            $_SESSION['kis_person_import_preview']=['payload'=>$payload['fingerprint'],'database'=>$plan['fingerprint'],'time'=>time()];
        } else throw new RuntimeException('Neznámá akce.');
    } catch(Throwable $e) {
        // Never log payload, SQL parameters, secrets, or unescaped database errors.
        error_log('kis_person_import_admin: '.get_class($e));
        $error=$e instanceof PDOException ? 'Databázová kontrola selhala. Zápis byl vrácen zpět; zkontrolujte schéma.' : $e->getMessage();
    }
}
$query=mb_substr(trim((string)($_GET['q']??'')),0,100);
$personId=max(0,(int)($_GET['person_id']??0));
$rows=[];
if($batch) {
    $sql="SELECT p.id,p.jmeno,p.prijmeni,p.narozeni,k.contact_address,l.source_key,l.relationship_label,c.contact_key,c.display_name,c.emails_json,c.phones_json,c.account_state
        FROM kis_person_profiles k JOIN sportovci p ON p.id=k.sportovec_id
        LEFT JOIN kis_person_links l ON l.sportovec_id=p.id LEFT JOIN kis_person_contacts c ON c.contact_key=l.contact_key";
    $params=[];$where=[];
    if($query!=='') {$where[]="(p.jmeno LIKE ? OR p.prijmeni LIKE ? OR c.display_name LIKE ?)";$params=array_fill(0,3,'%'.$query.'%');}
    if($personId>0) {$where[]='p.id=?';$params[]=$personId;}
    if($where) $sql.=' WHERE '.implode(' AND ',$where);
    $sql.=' ORDER BY p.prijmeni,p.jmeno,l.source_key LIMIT 200';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Osoby a kontakty ze starého KIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head><body>
<?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1>Osoby a kontakty ze starého KIS</h1>
<?php if($error): ?><div class="alert alert-danger"><?=kpiH($error)?></div><?php endif; ?>
<?php if($result): ?><div class="alert alert-success">Import byl dokončen a počty ověřeny. Soupisky nebyly přiřazeny, zprávy nebyly odeslány.</div><?php endif; ?>
<?php if($batch): $summary=json_decode($batch['summary_json'],true); ?>
<div class="alert alert-info">
<strong><?= (int)$summary['people'] ?> osob · <?= (int)$summary['contacts'] ?> identit zástupců · <?= (int)$summary['links'] ?> zdrojových vazeb</strong><br>
<?= (int)$summary['aliases'] ?> původních identifikátorů · <?= (int)$summary['accounts_created'] ?> nových účtů čekajících na aktivaci a reset hesla.
</div>
<p>Nové účty jsou neaktivní a jejich vazby čekají na ověření. Sdílené nebo chybějící e-maily se nezměnily v přístupová oprávnění.
Původní údaje jsou uchovány v šifrovaném archivu importu.</p>
<p><a href="?view=protocol">Protokol a převodní mapa</a> · <a href="sprava_sportovcu.php">Evidence osob</a> · <a href="eshop_identity_admin.php">Účty a ověření vazeb</a></p>
<form method="get" class="d-flex gap-2 mb-3"><input class="form-control" name="q" value="<?=kpiH($query)?>" placeholder="Jméno osoby nebo zástupce"><button class="btn btn-primary">Hledat</button></form>
<p class="text-muted">Zobrazeno nejvýše 200 vazeb. Pro další osoby použijte hledání.</p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Osoba</th><th>Narození</th><th>Kontaktní adresa</th><th>Zástupce a vztah</th><th>Kontakty</th><th>Zdroj</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr>
<td><?=kpiH($r['jmeno'].' '.$r['prijmeni'])?> <small>#<?=(int)$r['id']?></small></td><td><?=kpiH($r['narozeni']?:'Neuvedeno')?></td>
<td><?=kpiH($r['contact_address'])?></td><td><?=kpiH($r['display_name']?:($r['contact_key']?'Jméno neuvedeno':'Bez zástupce'))?><br><?=kpiH($r['relationship_label'])?></td>
<td><?=kpiH(implode(', ',json_decode($r['emails_json']?:'[]',true)))?><br><?=kpiH(implode(', ',json_decode($r['phones_json']?:'[]',true)))?></td>
<td><?=kpiH($r['source_key'])?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<p>Import úplných osob a zástupců ze schválených podkladů. Původní osoby a jejich navázaná historie budou nahrazeny. Administrátoři, přihlašovací účty a konfigurace zůstanou zachovány.</p>
<p>Nové účty dostanou neznámá náhodná hesla, zůstanou neaktivní a vazby k účtům budou čekat na ověření. Žádné zprávy se neodesílají. Soupisky se nepřiřazují.</p>
<?php if($preview): ?>
<h2>Náhled změny</h2><pre><?=kpiH(json_encode($preview,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
<p>Pro potvrzení vyberte znovu stejný soubor. Změna dat od náhledu import zastaví.</p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card card-body">
<?=csrf_field()?>
<label for="payload">Schválený importní balík JSON / JSON.GZ</label>
<input type="file" class="form-control mb-3" name="payload" id="payload" accept=".json,.gz" required>
<button class="btn btn-outline-primary mb-3" name="action" value="preview">Zkontrolovat náhled</button>
<?php if($preview): ?>
<label for="confirmation">Potvrzení: IMPORTOVAT A NAHRADIT</label><input class="form-control mb-3" name="confirmation" id="confirmation" autocomplete="off">
<button class="btn btn-danger" name="action" value="apply">Nahradit původní evidenci a importovat</button>
<?php endif; ?>
</form>
<?php endif; ?></main></body></html>
