<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/category_management.php';
barRequireWebActor($pdo, 'catalog_manage');
$error = '';
$rejectedVersion = null;
$version = null;
$id = (int)filter_var($_POST['id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !csrf_verify($_POST['csrf_token'])) {
            throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        }
        $id = barSourceInteger($_POST['id'] ?? null, 0, 2147483647);
        $version = barSourceInteger($_POST['version'] ?? null, 0, 2147483647);
        $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
        if (($_POST['action'] ?? null) === 'save') {
            $id = barSaveCategory($pdo, $id, $version, $_POST, $reason);
        } elseif (($_POST['action'] ?? null) === 'delete') {
            barDeleteEmptyCategory($pdo, $id, $version, $reason);
            $id = 0;
        } else {
            throw new InvalidArgumentException('Neplatná operace kategorie.');
        }
        header('Location: ' . appUiUrl('bar/categories.php' . ($id > 0 ? '?id=' . $id : '')), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
        if ($exception instanceof BarConflictException) {
            $rejectedVersion = $version;
        }
    }
}
$rows = barCategories($pdo);
$current = null;
foreach ($rows as $row) {
    if ((int)$row['id'] === $id) {
        $current = $row;
    }
}
if ($id > 0 && $current === null) {
    http_response_code(404);
    exit('Kategorie nebyla nalezena.');
}
$input = $current ?? ['id' => 0, 'lock_version' => 0, 'name' => '', 'sort_order' => 0, 'color_index' => 0, 'product_count' => 0];
if ($rejectedVersion !== null) {
    $input['lock_version'] = $rejectedVersion;
}
if ($error !== '') {
    foreach (['name', 'sort_order', 'color_index'] as $key) {
        if (is_string($_POST[$key] ?? null)) {
            $input[$key] = $_POST[$key];
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kategorie baru | KIS</title>
    <?php appUiAssets(); ?>
</head>
<body>
<?php require dirname(__DIR__) . '/hlavicka.php'; ?>
<main class="container py-4">
    <?php barRenderRequestRecoveryMount(); ?>
    <h1>Kategorie baru</h1>
    <p><a href="<?= barH(appUiUrl('bar/catalog.php')) ?>">Katalog produktů</a> · <a href="<?= barH(appUiUrl('bar/categories.php')) ?>">Nová kategorie</a></p>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= barH($error) ?></div><?php endif; ?>
    <section class="card card-body mb-4">
        <h2 class="h4"><?= $current === null ? 'Nová kategorie' : 'Upravit kategorii' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$input['id'] ?>">
            <input type="hidden" name="version" value="<?= (int)$input['lock_version'] ?>">
            <input type="hidden" name="color_index" value="<?= (int)$input['color_index'] ?>">
            <label class="form-label" for="bar-category-name">Název kategorie</label>
            <input class="form-control mb-2" id="bar-category-name" name="name" value="<?= barH($input['name']) ?>" maxlength="255" required>
            <label class="form-label" for="bar-category-order">Pořadí v nabídce</label>
            <input class="form-control mb-2" id="bar-category-order" name="sort_order" type="number" min="-2147483648" max="2147483647"
                   value="<?= barH($input['sort_order']) ?>" required>
            <label class="form-label" for="bar-category-reason">Důvod změny</label>
            <input class="form-control mb-2" id="bar-category-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary" name="action" value="save">Uložit kategorii</button>
            <?php if ($current !== null && (int)$current['product_count'] === 0): ?>
                <button class="btn btn-outline-danger" name="action" value="delete">Odstranit prázdnou kategorii</button>
            <?php endif; ?>
        </form>
    </section>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Kategorie</th><th>Pořadí</th><th>Počet produktů včetně archivovaných</th><th>Úprava</th></tr></thead>
        <tbody><?php foreach ($rows as $row): ?>
            <tr><td><?= barH($row['name']) ?></td><td><?= (int)$row['sort_order'] ?></td><td><?= (int)$row['product_count'] ?></td>
                <td><a href="<?= barH(appUiUrl('bar/categories.php?id=' . (int)$row['id'])) ?>">Upravit</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</main>
</body>
</html>
