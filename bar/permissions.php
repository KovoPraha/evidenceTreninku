<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/bar/web.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
barRequireWebActor($pdo, 'manage_permissions');
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !csrf_verify($_POST['csrf_token'])) {
            throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        }
        $id = filter_var($_POST['trainer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = $_POST['action'] ?? null;
        if ($id === false || !in_array($action, ['grant', 'revoke'], true) || !is_string($_POST['reason'] ?? null)) {
            throw new InvalidArgumentException('Vyberte účet, operaci a důvod.');
        }
        barSetConsumptionPermission($pdo, $id, $action === 'grant', $_POST['reason']);
        header('Location: ' . appUiUrl('bar/permissions.php'), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code(400);
        $error = $exception->getMessage();
    }
}
$accounts = $pdo->query('SELECT t.id,t.jmeno,t.aktivni,p.trainer_id AS consumption_allowed FROM treneri t '
    . "LEFT JOIN kis_bar_permissions p ON p.trainer_id=t.id AND p.permission_code='own_consumption' ORDER BY t.jmeno,t.id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Vlastní spotřeba baru | KIS</title>
    <?php appUiAssets(); ?>
</head>
<body>
<?php require dirname(__DIR__) . '/hlavicka.php'; ?>
<main class="container py-4">
    <h1>Oprávnění vlastní spotřeby baru</h1>
    <p>Povoluje odběr bez tržby. Samotná administrace baru toto právo nedává. K odběru musí mít účet také aktivní barovou pozici.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= barH($error) ?></div><?php endif; ?>
    <form method="post" class="card card-body mb-4">
        <?= csrf_field() ?>
        <label class="form-label" for="bar-account">Pracovní účet</label>
        <select class="form-select mb-3" name="trainer_id" id="bar-account" required>
            <?php foreach ($accounts as $account): ?>
                <option value="<?= (int)$account['id'] ?>"><?= barH($account['jmeno']) ?> · #<?= (int)$account['id'] ?>
                    <?= (int)$account['aktivni'] === 0 ? ' · neaktivní' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <label class="form-label" for="bar-reason">Důvod změny</label>
        <input class="form-control mb-3" id="bar-reason" name="reason" minlength="5" maxlength="1000" required>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" name="action" value="grant">Povolit vlastní spotřebu</button>
            <button class="btn btn-outline-danger" name="action" value="revoke">Odebrat oprávnění</button>
        </div>
    </form>
    <h2 class="h4">Přidělená oprávnění</h2>
    <ul>
    <?php foreach ($accounts as $account): ?>
        <?php if ($account['consumption_allowed'] !== null): ?>
            <li><?= barH($account['jmeno']) ?> · #<?= (int)$account['id'] ?></li>
        <?php endif; ?>
    <?php endforeach; ?>
    </ul>
</main>
</body>
</html>
