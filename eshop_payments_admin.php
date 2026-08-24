<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/shop_checkout.php';

staffRequireActivePosition('finance_manager');

function paymentAdminH(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function paymentAdminMoney(int $minor, string $currency): string { return number_format($minor / 100, 2, ',', ' ') . ' ' . paymentAdminH($currency); }

$query = trim((string)($_GET['q'] ?? ''));
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $actor = (int)$_SESSION['trener_id'];
            $reason = (string)($_POST['reason'] ?? '');
            $confirmed = ($_POST['confirm_action'] ?? '') === '1';
            if ($action === 'confirm_payment') {
                $result = shopOrderAdminConfirmBankPayment($pdo, (int)($_POST['payment_id'] ?? 0), $actor, $reason, $confirmed);
                $message = $result['changed'] ? 'Platba byla potvrzena a objednávka byla předána k přípravě.' : 'Platba už byla potvrzena.';
            } elseif ($action === 'confirm_refund') {
                $result = shopOrderAdminConfirmRefund($pdo, (int)($_POST['order_id'] ?? 0), $actor, (string)($_POST['refund_reference'] ?? ''), $reason, $confirmed);
                $message = $result['changed'] ? 'Odeslaná vratka byla auditovaně potvrzena.' : 'Vratka už byla potvrzena.';
            } elseif ($action === 'confirm_velodrome_payment') {
                $result = publicVelodromeManualConfirm($pdo, (int)($_POST['reservation_id'] ?? 0), $actor, $reason, $confirmed);
                $message = $result['changed'] ? 'Platba rezervace velodromu byla potvrzena.' : 'Platba rezervace už byla potvrzena.';
            } else {
                throw new InvalidArgumentException('Neznámá finanční akce.');
            }
            $_SESSION['flash_payment_admin'] = $message;
            $fragment=isset($result['order_id'])?'#order-'.(int)$result['order_id']:'#velodrome-'.(int)$result['id'];
            header('Location: eshop_payments_admin.php' . ($query !== '' ? '?q=' . rawurlencode($query) : '') . $fragment, true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('eshop_payments_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala bez částečného zápisu.';
        } catch (InvalidArgumentException|ShopCheckoutException|PublicVelodromeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_payment_admin'] ?? '');
unset($_SESSION['flash_payment_admin']);
$orders = array_values(array_filter(
    shopOrderAdminList($pdo, 200, $query),
    static fn(array $order): bool => ($order['status'] === 'placed' && $order['payment_record_status'] === 'pending')
        || $order['payment_record_status'] === 'refund_required'
));
$velodromePayments=array_values(array_filter(publicVelodromeAdminReservations($pdo),static fn(array$r):bool=>$r['stav']==='ceka'&&!(int)$r['zaplaceno']&&(float)$r['cena_kc']>0&&!$r['shop_order_id']));
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Platby a vratky</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous"></head>
<body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container-fluid py-4" style="max-width:1350px">
<div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1"><i class="bi bi-cash-coin me-2 text-success"></i>Platby a vratky</h1><p class="text-muted mb-0">Zobrazují se jen úkoly, které právě čekají na ověření v bance.</p></div><details class="text-end"><summary class="btn btn-outline-secondary btn-sm">Pokročilé</summary><div class="card card-body position-absolute end-0 mt-2 shadow-sm" style="z-index:10"><a class="btn btn-sm btn-link text-start" href="eshop_fio_admin.php">Automatické párování Fio</a><a class="btn btn-sm btn-link text-start" href="eshop_bank_admin.php">Nastavení bankovního účtu</a></div></details></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= paymentAdminH($error) ?></div><?php endforeach; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= paymentAdminH($success) ?></div><?php endif; ?>
<form method="get" class="card card-body border-0 shadow-sm mb-3"><label class="form-label fw-semibold">Najít platbu</label><div class="input-group"><input class="form-control" name="q" value="<?= paymentAdminH($query) ?>" maxlength="100" placeholder="Objednávka, zákazník, e-mail nebo variabilní symbol"><button class="btn btn-primary">Hledat</button></div></form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Objednávka</th><th>Zákazník</th><th>Částka / VS</th><th>Finanční úkol</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?><tr id="order-<?= (int)$order['id'] ?>"><td><code><?= paymentAdminH($order['public_code']) ?></code><div class="small text-muted"><?= paymentAdminH($order['created_at']) ?></div></td><td><?= paymentAdminH($order['customer_name_snapshot']) ?><div class="small"><?= paymentAdminH($order['customer_email_snapshot']) ?></div></td><td><?= paymentAdminMoney((int)$order['total_minor'], (string)$order['currency']) ?><div><code>VS <?= paymentAdminH($order['variable_symbol']) ?></code></div></td><td style="min-width:360px">
<?php if ($order['status'] === 'placed' && $order['payment_record_status'] === 'pending'): ?><form method="post" class="border rounded p-2"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_payment"><input type="hidden" name="payment_id" value="<?= (int)$order['payment_id'] ?>"><label class="small fw-semibold">Potvrdit přijatou bankovní platbu</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Jak byla platba ověřena"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Ověřil/a jsem platbu v bance</label><button class="btn btn-sm btn-success mt-1">Potvrdit platbu</button></form><?php endif; ?>
<?php if ($order['payment_record_status'] === 'refund_required'): ?><form method="post" class="border border-warning rounded p-2"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_refund"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><label class="small fw-semibold">Potvrdit odeslanou vratku</label><input class="form-control form-control-sm my-1" name="refund_reference" maxlength="255" required placeholder="Reference bankovní transakce"><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Jak byla vratka ověřena"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Peníze byly skutečně odeslány</label><button class="btn btn-sm btn-warning mt-1">Potvrdit vratku</button></form><?php endif; ?>
</td></tr><?php endforeach; ?>
<?php if ($orders === []): ?><tr><td colspan="4" class="text-center text-muted py-5">Žádná platba ani vratka nyní nečeká na ruční potvrzení.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="card border-0 shadow-sm mt-4"><div class="card-header bg-white fw-semibold">Placené rezervace velodromu mimo objednávku</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Rezervace</th><th>Účastník</th><th>Částka</th><th>Finanční úkol</th></tr></thead><tbody><?php foreach($velodromePayments as$r):?><tr id="velodrome-<?=(int)$r['id']?>"><td><?=paymentAdminH($r['datum'].' '.substr((string)$r['cas_od'],0,5))?></td><td><?=paymentAdminH($r['prijmeni'].' '.$r['jmeno'])?><div class="small text-muted"><?=paymentAdminH($r['email'])?></div></td><td><?=number_format((float)$r['cena_kc'],2,',',' ')?> Kč</td><td><form method="post" class="border rounded p-2"><?=csrf_field()?><input type="hidden" name="action" value="confirm_velodrome_payment"><input type="hidden" name="reservation_id" value="<?=(int)$r['id']?>"><input class="form-control form-control-sm mb-1" name="reason" maxlength="1000" required placeholder="Jak byla platba ověřena a její reference"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Ověřil/a jsem platbu v bance</label><button class="btn btn-success btn-sm mt-1">Potvrdit platbu</button></form></td></tr><?php endforeach;?><?php if($velodromePayments===[]):?><tr><td colspan="4" class="text-center text-muted py-4">Žádná samostatná platba velodromu nečeká.</td></tr><?php endif;?></tbody></table></div></div>
</main></body></html>
