<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/shop_product_interest.php';
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
function interestAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$status = (string)($_GET['status'] ?? '');
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $result = shopProductInterestAdminTransition(
                $pdo,
                (int)($_POST['interest_id'] ?? 0),
                (int)$_SESSION['trener_id'],
                (string)($_POST['to_status'] ?? ''),
                (string)($_POST['note'] ?? ''),
                ($_POST['confirm_action'] ?? '') === '1'
            );
            $_SESSION['flash_interest_admin'] = $result['changed'] ? 'Stav kontaktu byl uložen.' : 'Kontakt už byl v tomto stavu.';
            header('Location: shop_product_interests_admin.php' . ($status !== '' ? '?status=' . rawurlencode($status) : ''), true, 303);
            exit;
        } catch (InvalidArgumentException | ShopProductInterestException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('shop_product_interests_admin.php: ' . get_class($exception));
            $errors[] = 'Databázová operace selhala bez částečného zápisu.';
        }
    }
}
$success = (string)($_SESSION['flash_interest_admin'] ?? '');
unset($_SESSION['flash_interest_admin']);
try {
    $rows = shopProductInterestAdminList($pdo, $status);
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
    $status = '';
    $rows = shopProductInterestAdminList($pdo);
}
$labels = ['new' => ['warning','Nový'], 'contacted' => ['info','Kontaktován'], 'closed' => ['secondary','Uzavřen']];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zájemci o produkty</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?><main class="container-fluid py-4" style="max-width:1500px">
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h1 class="h4 mb-1"><i class="bi bi-person-heart me-2 text-primary"></i>Zájemci o produkty</h1><p class="text-muted mb-0">Lidé, kterým nevyhovoval termín, velikost nebo aktuální nabídka.</p></div><a class="btn btn-outline-secondary btn-sm" href="pracovni_pozice.php">Zpět na rozcestník</a></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=interestAdminH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=interestAdminH($success)?></div><?php endif;?>
<nav class="nav nav-pills gap-2 mb-3"><a class="nav-link <?=$status===''?'active':''?>" href="shop_product_interests_admin.php">Vše</a><?php foreach(['new'=>'Nové','contacted'=>'Kontaktované','closed'=>'Uzavřené'] as$key=>$label):?><a class="nav-link <?=$status===$key?'active':''?>" href="?status=<?=$key?>"><?=interestAdminH($label)?></a><?php endforeach;?></nav>
<div class="alert alert-info small">Kontakt se neodesílá automaticky do marketingu. Je uložen jen jako konkrétní žádost o odpověď k vybranému produktu.</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Stav</th><th>Produkt</th><th>E-mail</th><th>Vznik</th><th>Poslední záznam</th><th style="min-width:330px">Akce</th></tr></thead><tbody><?php foreach($rows as$row):?><?php [$color,$label]=$labels[$row['status']]??['secondary',(string)$row['status']];?><tr><td><span class="badge text-bg-<?=interestAdminH($color)?>"><?=interestAdminH($label)?></span></td><td><strong><?=interestAdminH($row['public_name'])?></strong><?php if($row['sku']!==null):?><div class="small text-muted">SKU <?=interestAdminH($row['sku'])?></div><?php endif;?><a class="small" href="booking/produkt.php?id=<?=(int)$row['product_id']?>" target="_blank" rel="noopener">Otevřít produkt</a></td><td><a href="mailto:<?=interestAdminH((string)$row['email'])?>"><?=interestAdminH($row['email'])?></a></td><td class="small"><?=interestAdminH($row['created_at'])?></td><td class="small"><strong><?=interestAdminH($row['last_action'])?></strong><div><?=interestAdminH($row['last_note'])?></div><div class="text-muted"><?=interestAdminH($row['last_event_at'])?></div></td><td><?php if($row['status']!=='closed'):?><form method="post" class="border rounded p-2"><?=csrf_field()?><input type="hidden" name="interest_id" value="<?=(int)$row['id']?>"><label class="form-label small fw-semibold" for="interest-note-<?=(int)$row['id']?>">Provozní poznámka</label><input id="interest-note-<?=(int)$row['id']?>" class="form-control form-control-sm mb-2" name="note" maxlength="1000" required placeholder="Kdy a jak jsme odpověděli"><div class="d-flex flex-wrap gap-2"><label class="small"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji zápis</label><button class="btn btn-sm btn-outline-primary" name="to_status" value="contacted">Označit kontaktování</button><button class="btn btn-sm btn-outline-secondary" name="to_status" value="closed">Uzavřít</button></div></form><?php else:?><span class="text-muted small">Vyřízeno</span><?php endif;?></td></tr><?php endforeach;?><?php if($rows===[]):?><tr><td colspan="6" class="text-center text-muted py-4">V tomto filtru nejsou žádné kontakty.</td></tr><?php endif;?></tbody></table></div></div>
</main></body></html>
