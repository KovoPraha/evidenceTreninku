<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/shop_catalog_review.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function shopAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function shopAdminMoney(mixed $minor, ?string $currency): string
{
    if ($minor === null || $minor === '') {
        return '—';
    }
    return number_format(((int)$minor) / 100, 2, ',', ' ') . ' ' . shopAdminH($currency ?: '');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        $runId = (int)($_POST['run_id'] ?? 0);
        try {
            $result = shopCatalogReviewProduct(
                $pdo,
                $runId,
                (int)($_POST['product_id'] ?? 0),
                (int)$_SESSION['trener_id'],
                (string)($_POST['action'] ?? ''),
                isset($_POST['offer_type']) ? (string)$_POST['offer_type'] : null,
                (string)($_POST['note'] ?? '')
            );
            $_SESSION['flash_success'] = $result['status'] === 'approved'
                ? 'Klasifikace produktu byla schválena.'
                : 'Produkt byl vyřazen z budoucí publikace.';
            header('Location: eshop_admin.php?run_id=' . $runId);
            exit;
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$runs = shopCatalogReviewRuns($pdo);
$runId = max(0, (int)($_GET['run_id'] ?? 0));
if ($runId === 0 && $runs !== []) {
    $runId = (int)$runs[0]['id'];
}
$statusFilter = (string)($_GET['status'] ?? '');
$typeFilter = (string)($_GET['type'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
$detail = $runId > 0
    ? shopCatalogReviewDetail($pdo, $runId, $statusFilter, $typeFilter, $search)
    : ['run' => null, 'products' => [], 'events' => []];
$offerTypes = shopCatalogReviewOfferTypes();
$statusLabels = [
    'pending' => ['Čeká na kontrolu', 'bg-warning text-dark'],
    'auto_classified' => ['Automaticky zařazeno', 'bg-secondary'],
    'approved' => ['Schváleno', 'bg-success'],
    'excluded' => ['Vyřazeno', 'bg-dark'],
];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrace e-shopu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container-fluid py-4" style="max-width:1500px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-shop me-2 text-primary"></i>Administrace e-shopu</h1>
            <div class="text-muted small">Kontrola importovaného katalogu před budoucí publikací.</div>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Admin dashboard</a>
    </div>

    <div class="alert alert-info">
        <strong>Bezpečný staging:</strong> schválení zde ještě nevystaví produkt na webu,
        nevytvoří rezervaci, objednávku ani platbu. Publikační krok zatím neexistuje.
    </div>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?= shopAdminH($error) ?></div>
    <?php endforeach; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= shopAdminH($success) ?></div>
    <?php endif; ?>

    <?php if ($runs === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-5">
            <i class="bi bi-inbox display-4 text-muted"></i>
            <h2 class="h5 mt-3">Zatím není uložený žádný katalog</h2>
            <p class="text-muted mb-0">Nejprve spusťte ověření a explicitní staging Shoptet exportu.</p>
        </div></div>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <?php foreach ($runs as $run): ?>
                <div class="col-sm-6 col-xl-3">
                    <a class="text-decoration-none text-reset" href="eshop_admin.php?run_id=<?= (int)$run['id'] ?>">
                        <div class="card h-100 shadow-sm <?= (int)$run['id'] === $runId ? 'border-primary' : 'border-0' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between"><strong>Běh #<?= (int)$run['id'] ?></strong><span class="badge bg-light text-dark"><?= shopAdminH($run['status']) ?></span></div>
                                <div class="small text-muted text-truncate" title="<?= shopAdminH($run['source_filename']) ?>"><?= shopAdminH($run['source_filename']) ?></div>
                                <div class="mt-2"><?= (int)$run['product_count'] ?> produktů / <?= (int)$run['variant_count'] ?> variant</div>
                                <div class="small <?= (int)$run['pending_count'] > 0 ? 'text-warning-emphasis' : 'text-success' ?>">
                                    Čeká: <?= (int)$run['pending_count'] ?> · schváleno: <?= (int)$run['approved_count'] ?> · vyřazeno: <?= (int)$run['excluded_count'] ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($detail['run']): ?>
            <form method="get" class="card border-0 shadow-sm mb-3">
                <div class="card-body row g-2 align-items-end">
                    <input type="hidden" name="run_id" value="<?= $runId ?>">
                    <div class="col-md-4"><label class="form-label small">Hledat název, SKU nebo klíč</label><input class="form-control" name="q" value="<?= shopAdminH($search) ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Stav kontroly</label><select class="form-select" name="status"><option value="">Všechny stavy</option><?php foreach ($statusLabels as $key => $meta): ?><option value="<?= shopAdminH($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= shopAdminH($meta[0]) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label small">Výsledný typ</label><select class="form-select" name="type"><option value="">Všechny typy</option><?php foreach (ShopOfferClassifier::TYPES as $type): ?><option value="<?= shopAdminH($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= shopAdminH($type) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrovat</button></div>
                </div>
            </form>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Produkty (<?= count($detail['products']) ?>)</strong>
                    <span class="small text-muted">Kontrakt <?= shopAdminH($detail['run']['contract_version']) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-dark"><tr><th>Produkt</th><th>Návrh</th><th>Stav</th><th>Varianty / cena</th><th style="min-width:420px">Kontrola</th></tr></thead>
                        <tbody>
                        <?php foreach ($detail['products'] as $product):
                            $effectiveType = $product['reviewed_offer_type'] ?: $product['offer_type'];
                            $statusMeta = $statusLabels[$product['review_status']] ?? [$product['review_status'], 'bg-secondary'];
                        ?>
                            <tr>
                                <td><strong><?= shopAdminH($product['name']) ?></strong><div class="small text-muted"><?= shopAdminH($product['external_product_key']) ?></div></td>
                                <td><code><?= shopAdminH($product['offer_type']) ?></code><div class="small text-muted"><?= shopAdminH($product['classification_confidence']) ?></div></td>
                                <td><span class="badge <?= shopAdminH($statusMeta[1]) ?>"><?= shopAdminH($statusMeta[0]) ?></span><?php if ($product['reviewed_at']): ?><div class="small text-muted mt-1"><?= shopAdminH($product['reviewed_at']) ?></div><?php endif; ?></td>
                                <td><?= (int)$product['variant_count'] ?> ks<div class="small"><?= shopAdminMoney($product['min_amount_minor'], $product['currency']) ?><?= $product['max_amount_minor'] !== $product['min_amount_minor'] ? ' – ' . shopAdminMoney($product['max_amount_minor'], $product['currency']) : '' ?></div></td>
                                <td>
                                    <form method="post" class="row g-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="run_id" value="<?= $runId ?>">
                                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                        <div class="col-md-4"><select class="form-select form-select-sm" name="offer_type"><?php foreach ($offerTypes as $type): ?><option value="<?= shopAdminH($type) ?>" <?= $effectiveType === $type ? 'selected' : '' ?>><?= shopAdminH($type) ?></option><?php endforeach; ?></select></div>
                                        <div class="col-md-4"><input class="form-control form-control-sm" name="note" maxlength="1000" placeholder="Poznámka při změně/vyřazení"></div>
                                        <div class="col-md-4 d-flex gap-1"><button class="btn btn-sm btn-success flex-fill" name="action" value="approve">Schválit</button><button class="btn btn-sm btn-outline-danger" name="action" value="exclude">Vyřadit</button></div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($detail['products'] === []): ?><tr><td colspan="5" class="text-center text-muted py-4">Filtru neodpovídá žádný produkt.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($detail['events'] !== []): ?>
                <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Historie ručních kontrol</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Čas</th><th>Produkt</th><th>Akce</th><th>Změna</th><th>Kdo</th><th>Poznámka</th></tr></thead><tbody><?php foreach ($detail['events'] as $event): ?><tr><td><?= shopAdminH($event['created_at']) ?></td><td><?= shopAdminH($event['product_name']) ?></td><td><?= shopAdminH($event['action']) ?></td><td><?= shopAdminH(($event['from_offer_type'] ?: '—') . ' → ' . ($event['to_offer_type'] ?: 'vyřazeno')) ?></td><td><?= shopAdminH($event['actor_name'] ?: '#' . $event['actor_trainer_id']) ?></td><td><?= shopAdminH($event['note'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
