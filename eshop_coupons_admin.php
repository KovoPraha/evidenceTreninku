<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/shop_coupon.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function couponAdminH(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function couponAdminDate(string $value): string { $value = trim($value); return $value === '' ? '' : str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : ''); }
function couponAdminDiscount(array $coupon): string { return $coupon['discount_type'] === 'fixed' ? number_format((int)$coupon['value_minor_or_basis_points'] / 100, 2, ',', ' ') . ' Kč' : number_format((int)$coupon['value_minor_or_basis_points'] / 100, 2, ',', ' ') . ' %'; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $actor = (int)$_SESSION['trener_id'];
            $confirmed = ($_POST['confirm_action'] ?? '') === '1';
            if ($action === 'create') {
                $type = (string)($_POST['discount_type'] ?? '');
                $value = $type === 'percentage' ? (int)($_POST['percentage'] ?? 0) * 100 : (int)couponAdminMoneyInput((string)($_POST['fixed_amount'] ?? ''));
                $scope = 0;
                foreach (['scope_goods' => SHOP_COUPON_GOODS, 'scope_program' => SHOP_COUPON_CLUB_PROGRAM, 'scope_event' => SHOP_COUPON_CLUB_EVENT, 'scope_velodrome' => SHOP_COUPON_VELODROME] as $field => $flag) {
                    if (($_POST[$field] ?? '') === '1') $scope |= $flag;
                }
                $coupon = shopCouponAdminCreate(
                    $pdo,
                    $actor,
                    (string)($_POST['code'] ?? ''),
                    $type,
                    $value,
                    (int)couponAdminMoneyInput((string)($_POST['minimum_order'] ?? '0')),
                    couponAdminMoneyInput((string)($_POST['maximum_discount'] ?? ''), true),
                    trim((string)($_POST['usage_limit'] ?? '')) === '' ? null : (int)$_POST['usage_limit'],
                    couponAdminDate((string)($_POST['valid_from'] ?? '')),
                    couponAdminDate((string)($_POST['valid_until'] ?? '')),
                    (string)($_POST['note'] ?? ''),
                    $confirmed,
                    $scope
                );
                $message = 'Kupón ' . $coupon['code'] . ' byl vytvořen s neměnnými ekonomickými pravidly a rozsahem.';
            } elseif ($action === 'set_active') {
                $result = shopCouponAdminSetActive($pdo, (int)($_POST['coupon_id'] ?? 0), $actor, ($_POST['target_active'] ?? '') === '1', (string)($_POST['note'] ?? ''), $confirmed);
                $message = $result['changed'] ? 'Stav kupónu byl auditovaně změněn.' : 'Kupón už měl požadovaný stav.';
            } else {
                throw new InvalidArgumentException('Neznámá akce kupónu.');
            }
            $_SESSION['flash_coupon_admin'] = $message;
            header('Location: eshop_coupons_admin.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('eshop_coupons_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala bez částečného zápisu.';
        } catch (InvalidArgumentException|ShopCouponException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
$success = (string)($_SESSION['flash_coupon_admin'] ?? '');
unset($_SESSION['flash_coupon_admin']);
$coupons = shopCouponAdminList($pdo);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Slevové kupóny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1200px">
    <div class="d-flex justify-content-between mb-3"><div><h1 class="h4 mb-0">Slevové kupóny K4</h1><div class="text-muted small">Pravidla i rozsah vytvořeného kupónu jsou neměnné; změnit lze jen aktivní stav.</div></div><a href="eshop_admin.php" class="btn btn-outline-secondary btn-sm">Administrace e-shopu</a></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= couponAdminH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= couponAdminH($success) ?></div><?php endif; ?>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Vytvořit nový kupón</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="create">
                <div class="col-md-3"><label class="form-label">Kód</label><input class="form-control text-uppercase" name="code" minlength="4" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9_-]{3,31}" required></div>
                <div class="col-md-3"><label class="form-label">Typ</label><select class="form-select" name="discount_type" required><option value="fixed">Pevná částka</option><option value="percentage">Procenta</option></select></div>
                <div class="col-md-3"><label class="form-label">Pevná sleva Kč</label><input class="form-control" name="fixed_amount" value="100,00"></div>
                <div class="col-md-3"><label class="form-label">Procentní sleva</label><input class="form-control" type="number" name="percentage" min="1" max="99" value="10"></div>
                <div class="col-md-3"><label class="form-label">Minimální povolené položky Kč</label><input class="form-control" name="minimum_order" value="0"></div>
                <div class="col-md-3"><label class="form-label">Max. sleva Kč <span class="text-muted">(jen %)</span></label><input class="form-control" name="maximum_discount"></div>
                <div class="col-md-2"><label class="form-label">Celkový limit</label><input class="form-control" type="number" name="usage_limit" min="1" max="1000000" placeholder="bez limitu"></div>
                <div class="col-md-2"><label class="form-label">Platí od</label><input class="form-control" type="datetime-local" name="valid_from"></div>
                <div class="col-md-2"><label class="form-label">Platí do</label><input class="form-control" type="datetime-local" name="valid_until"></div>
                <div class="col-12"><label class="form-label d-block">Kupón platí na</label><div class="d-flex flex-wrap gap-3"><label><input type="checkbox" name="scope_goods" value="1" checked> zboží</label><label><input type="checkbox" name="scope_program" value="1"> kroužky</label><label><input type="checkbox" name="scope_event" value="1"> placené události</label><label><input type="checkbox" name="scope_velodrome" value="1"> velodrom</label></div><div class="form-text">Nezaškrtnutá služba se do minima ani výpočtu slevy nezapočítá.</div></div>
                <div class="col-md-8"><label class="form-label">Auditní poznámka</label><input class="form-control" name="note" maxlength="1000" required placeholder="Účel a kdo kupón schválil"></div>
                <div class="col-md-4 d-flex align-items-end"><div><label class="d-block small"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji neměnná pravidla i rozsah kupónu</label><button class="btn btn-primary mt-2">Vytvořit kupón</button></div></div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Kód</th><th>Sleva</th><th>Podmínky a rozsah</th><th>Použití</th><th>Stav</th><th>Poslední audit</th><th>Akce</th></tr></thead>
        <tbody>
        <?php foreach ($coupons as $coupon): ?>
            <tr>
                <td><code><?= couponAdminH($coupon['code']) ?></code></td>
                <td><?= couponAdminH(couponAdminDiscount($coupon)) ?></td>
                <td class="small">Minimum <?= number_format((int)$coupon['minimum_order_minor'] / 100, 2, ',', ' ') ?> Kč<?php if ($coupon['maximum_discount_minor'] !== null): ?><br>maximum <?= number_format((int)$coupon['maximum_discount_minor'] / 100, 2, ',', ' ') ?> Kč<?php endif; ?><br><strong>Platí na:</strong> <?= couponAdminH(implode(', ', shopCouponApplicabilityLabels((int)($coupon['applicability_mask'] ?? SHOP_COUPON_GOODS)))) ?><br><?= couponAdminH($coupon['valid_from'] ?? 'od vytvoření') ?> – <?= couponAdminH($coupon['valid_until'] ?? 'bez konce') ?></td>
                <td><?= (int)$coupon['usage_count'] ?> / <?= couponAdminH($coupon['usage_limit_total'] ?? '∞') ?></td>
                <td><span class="badge text-bg-<?= (int)$coupon['active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$coupon['active'] === 1 ? 'aktivní' : 'vypnutý' ?></span></td>
                <td class="small"><code><?= couponAdminH($coupon['last_action']) ?></code> · admin #<?= (int)$coupon['last_actor_id'] ?><div><?= couponAdminH($coupon['last_note']) ?></div></td>
                <td><form method="post" style="min-width:220px"><?= csrf_field() ?><input type="hidden" name="action" value="set_active"><input type="hidden" name="coupon_id" value="<?= (int)$coupon['id'] ?>"><input type="hidden" name="target_active" value="<?= (int)$coupon['active'] === 1 ? '0' : '1' ?>"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod změny"><label class="small"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji změnu</label><button class="btn btn-sm btn-outline-<?= (int)$coupon['active'] === 1 ? 'danger' : 'success' ?>"><?= (int)$coupon['active'] === 1 ? 'Vypnout' : 'Aktivovat' ?></button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($coupons === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Zatím není žádný kupón.</td></tr><?php endif; ?>
        </tbody>
    </table></div></section>
</main>
</body>
</html>
