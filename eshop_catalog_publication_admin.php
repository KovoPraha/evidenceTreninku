<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/shop_catalog_publication.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function publicationAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function publicationAdminMoney(mixed $minor, mixed $currency): string
{
    return $minor === null
        ? '—'
        : number_format(((int)$minor) / 100, 2, ',', ' ') . ' ' . publicationAdminH($currency ?: '');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'activate') {
                $result = shopCatalogPublicationActivate(
                    $pdo,
                    (int)($_POST['product_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['public_name'] ?? ''),
                    (string)($_POST['public_summary'] ?? ''),
                    (string)($_POST['note'] ?? ''),
                    (string)($_POST['confirmed'] ?? '') === '1'
                );
                $_SESSION['flash_publication_success'] = $result['changed']
                    ? 'Produkt byl označen jako aktivní pro budoucí storefront.'
                    : 'Produkt už je aktivní se stejným veřejným obsahem.';
            } elseif ($action === 'deactivate') {
                $result = shopCatalogPublicationDeactivate(
                    $pdo,
                    (int)($_POST['product_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_publication_success'] = $result['changed']
                    ? 'Produkt byl deaktivován.'
                    : 'Produkt už byl deaktivován.';
            } else {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            header('Location: eshop_catalog_publication_admin.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('eshop_catalog_publication_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala. Nebyla uložena částečná změna.';
        } catch (InvalidArgumentException | ShopCatalogPublicationException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_publication_success'] ?? '');
unset($_SESSION['flash_publication_success']);
$search = mb_strtolower(trim((string)($_GET['q'] ?? '')), 'UTF-8');
$status = (string)($_GET['status'] ?? '');
$products = shopCatalogPublicationProducts($pdo);
$products = array_values(array_filter($products, static function (array $product) use ($search, $status): bool {
    if ($status !== '' && (string)$product['catalog_status'] !== $status) {
        return false;
    }
    if ($search === '') {
        return true;
    }
    return str_contains(
        mb_strtolower((string)$product['name'] . ' ' . (string)$product['external_product_key'], 'UTF-8'),
        $search
    );
}));
$events = shopCatalogPublicationEvents($pdo);
$activeCount = count(array_filter($products, static fn (array $product): bool => $product['catalog_status'] === 'active'));
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aktivace katalogu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container-fluid py-4" style="max-width:1500px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h1 class="h4 mb-0"><i class="bi bi-eye me-2 text-success"></i>Řízená aktivace katalogu</h1><div class="text-muted small">Správa jednotlivých produktů pro veřejný e-shop.</div></div><a href="eshop_admin.php" class="btn btn-outline-secondary btn-sm">Zpět na kontrolu katalogu</a></div>
    <div class="alert alert-warning"><strong>Aktivace zde produkt zveřejní v klubovém e-shopu.</strong> Běžné zboží <code>goods</code> musí splnit katalogovou kontrolu. Ruční kroužek <code>program</code> lze aktivovat až po navázání nabídky; po skončení nabídky zůstane aktivní, ale automaticky zmizí z prodeje. Ostatní služby a rezervace zůstávají blokované. Chybějící příznak viditelnosti u staršího importu se bere jako viditelný až po tomto ručním potvrzení.</div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= publicationAdminH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= publicationAdminH($success) ?></div><?php endif; ?>

    <form method="get" class="card border-0 shadow-sm mb-3"><div class="card-body row g-2 align-items-end"><div class="col-md-6"><label class="form-label">Hledat produkt</label><input class="form-control" name="q" value="<?= publicationAdminH($_GET['q'] ?? '') ?>"></div><div class="col-md-3"><label class="form-label">Stav</label><select class="form-select" name="status"><option value="">Všechny</option><?php foreach (['draft' => 'Draft', 'active' => 'Aktivní', 'inactive' => 'Neaktivní'] as $key => $label): ?><option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="col-md-3 d-grid"><button class="btn btn-primary">Filtrovat</button></div></div></form>

    <div class="row g-3 mb-3"><div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Zobrazené produkty</div><div class="h3 mb-0"><?= count($products) ?></div></div></div></div><div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Aktivní ve výběru</div><div class="h3 mb-0"><?= $activeCount ?></div></div></div></div></div>

    <div class="card border-0 shadow-sm mb-3"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Produkt</th><th>Typ / stav</th><th>Varianty / cena</th><th style="min-width:560px">Rozhodnutí</th></tr></thead><tbody>
    <?php foreach ($products as $product): $readiness = shopCatalogPublicationReadiness($pdo, (int)$product['id']); ?><tr><td><strong><?= publicationAdminH($product['name']) ?></strong><div class="small text-muted"><?= publicationAdminH($product['external_product_key']) ?></div><?php if($product['catalog_status']==='active'):?><a class="small" href="eshop_member_prices_admin.php?product_id=<?=(int)$product['id']?>">Nastavit klubovou cenu</a><?php endif;?></td><td><code><?= publicationAdminH($product['offer_type']) ?></code><div><span class="badge <?= $product['catalog_status'] === 'active' ? 'bg-success' : ($product['catalog_status'] === 'inactive' ? 'bg-secondary' : 'bg-warning text-dark') ?>"><?= publicationAdminH($product['catalog_status']) ?></span></div></td><td><?= (int)$product['visible_variant_count'] ?> viditelných / <?= (int)$product['variant_count'] ?><div class="small"><?= publicationAdminMoney($product['min_amount_minor'], $product['currency']) ?><?= $product['max_amount_minor'] !== $product['min_amount_minor'] ? ' – ' . publicationAdminMoney($product['max_amount_minor'], $product['currency']) : '' ?></div></td><td>
        <?php if ($product['catalog_status'] === 'active'): ?><div class="mb-2"><strong><?= publicationAdminH($product['public_name']) ?></strong><div class="small text-muted"><?= publicationAdminH($product['public_summary']) ?></div></div><?php if($product['offer_type']==='program' && $product['program_saleable']===false):?><div class="alert alert-warning py-2"><strong>Aktivní, ale bez platné nabídky.</strong> <?=publicationAdminH($product['program_sale_reason'])?></div><?php endif;?><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod deaktivace"><button class="btn btn-sm btn-outline-danger">Deaktivovat</button></form>
        <?php elseif (!$readiness['ready']): ?><div class="alert alert-secondary py-2 mb-0"><strong>Aktivace blokována:</strong> <?= publicationAdminH(implode(' ', $readiness['blockers'])) ?></div>
        <?php else: ?><form method="post" class="row g-1"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><div class="col-md-4"><input class="form-control form-control-sm" name="public_name" maxlength="255" required value="<?= publicationAdminH($product['public_name'] ?: $product['name']) ?>" placeholder="Veřejný název"></div><div class="col-md-5"><input class="form-control form-control-sm" name="public_summary" maxlength="1000" required value="<?= publicationAdminH($product['public_summary'] ?? '') ?>" placeholder="Bezpečný veřejný popis bez HTML"></div><div class="col-md-3"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod aktivace"></div><div class="col-12 d-flex justify-content-end align-items-center gap-2"><label class="form-check-label small"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required> Potvrzuji tento konkrétní produkt</label><button class="btn btn-sm btn-success">Aktivovat</button></div></form><?php endif; ?>
    </td></tr><?php endforeach; ?>
    <?php if ($products === []): ?><tr><td colspan="4" class="text-center text-muted py-4">Filtru neodpovídá žádný produkt.</td></tr><?php endif; ?>
    </tbody></table></div></div>

    <?php if ($events !== []): ?><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Audit aktivací</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Čas</th><th>Produkt</th><th>Akce</th><th>Stav</th><th>Kdo</th><th>Důvod</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= publicationAdminH($event['created_at']) ?></td><td><?= publicationAdminH($event['product_name']) ?></td><td><?= publicationAdminH($event['action']) ?></td><td><?= publicationAdminH(($event['from_status'] ?: 'nový') . ' → ' . $event['to_status']) ?></td><td><?= publicationAdminH($event['actor_name'] ?: '#' . $event['actor_trainer_id']) ?></td><td><?= publicationAdminH($event['note']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
</main>

</body>
</html>
