<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/child_access.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function childAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$success = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Neplatný bezpečnostní token.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $actorId = (int)$_SESSION['trener_id'];
            $reason = (string)($_POST['reason'] ?? '');
            if ($action === 'create') {
                childAccessCreate(
                    $pdo,
                    (int)($_POST['sportovec_id'] ?? 0),
                    (string)($_POST['login'] ?? ''),
                    (string)($_POST['password'] ?? ''),
                    $actorId,
                    $reason
                );
                $success = 'Omezený přístup sportovce byl vytvořen.';
            } elseif ($action === 'reset_password') {
                childAccessResetPassword(
                    $pdo,
                    (int)($_POST['access_account_id'] ?? 0),
                    (string)($_POST['password'] ?? ''),
                    $actorId,
                    $reason
                );
                $success = 'Heslo bylo změněno a všechny starší relace byly odvolány.';
            } elseif ($action === 'activate' || $action === 'deactivate') {
                childAccessSetActive(
                    $pdo,
                    (int)($_POST['access_account_id'] ?? 0),
                    $action === 'activate',
                    $actorId,
                    $reason
                );
                $success = $action === 'activate' ? 'Přístup byl aktivován.' : 'Přístup byl deaktivován.';
            } else {
                throw new InvalidArgumentException('Neplatná administrační akce.');
            }
        } catch (Throwable $exception) {
            $errors[] = $exception instanceof InvalidArgumentException || $exception instanceof ChildAccessException
                ? $exception->getMessage()
                : 'Změnu se nepodařilo bezpečně uložit.';
            if (!($exception instanceof InvalidArgumentException) && !($exception instanceof ChildAccessException)) {
                error_log('Child access admin failed: ' . $exception->getMessage());
            }
        }
    }
}

$accounts = childAccessAdminList($pdo);
$availablePeople = $pdo->query(
    'SELECT s.id,s.jmeno,s.prijmeni,s.narozeni FROM sportovci s '
    . 'LEFT JOIN child_access_accounts a ON a.sportovec_id=s.id '
    . 'WHERE a.id IS NULL ORDER BY s.prijmeni,s.jmeno,s.id'
)->fetchAll(PDO::FETCH_ASSOC);
$events = $pdo->query(
    'SELECT e.*,s.jmeno,s.prijmeni,t.jmeno AS trainer_name FROM child_access_events e '
    . 'JOIN child_access_accounts a ON a.id=e.access_account_id '
    . 'JOIN sportovci s ON s.id=a.sportovec_id '
    . "LEFT JOIN treneri t ON e.actor_type='trainer' AND t.id=e.actor_id "
    . 'ORDER BY e.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přístupy sportovců — KIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?>
</head>
<body class="bg-light">
<main class="container py-4" style="max-width:1250px">
    <div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h4 mb-1">Omezené přístupy sportovců</h1><p class="text-muted small mb-0">Jeden přístup je svázaný právě s jedním sportovcem. Umožňuje pouze čtení jeho vlastních dat.</p></div><a href="kis_rosters_admin.php" class="btn btn-outline-secondary btn-sm">Soupisky</a></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= childAdminH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= childAdminH($success) ?></div><?php endif; ?>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Vytvořit přístup</div><div class="card-body">
        <form method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="col-lg-4"><label class="form-label">Sportovec bez přístupu</label><select class="form-select" name="sportovec_id" required><option value="">Vyberte</option><?php foreach ($availablePeople as $person): ?><option value="<?= (int)$person['id'] ?>"><?= childAdminH($person['prijmeni'] . ' ' . $person['jmeno'] . ' (' . ($person['narozeni'] ?: 'bez data') . ')') ?></option><?php endforeach; ?></select></div>
            <div class="col-lg-2"><label class="form-label">Login</label><input class="form-control" name="login" autocomplete="off" required></div>
            <div class="col-lg-2"><label class="form-label">Počáteční heslo (12–200 znaků)</label><input class="form-control" type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required></div>
            <div class="col-lg-3"><label class="form-label">Důvod</label><input class="form-control" name="reason" value="Zřízení vlastního přístupu sportovce." required></div>
            <div class="col-lg-1"><button class="btn btn-primary">Vytvořit</button></div>
        </form>
    </div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Existující přístupy</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Sportovec</th><th>Login</th><th>Stav / verze</th><th>Poslední login</th><th style="min-width:390px">Bezpečnostní změna</th></tr></thead><tbody>
        <?php if ($accounts === []): ?><tr><td colspan="5" class="text-muted p-3">Zatím nebyl vytvořen žádný přístup.</td></tr><?php endif; ?>
        <?php foreach ($accounts as $account): ?><tr>
            <td><?= childAdminH($account['prijmeni'] . ' ' . $account['jmeno']) ?></td><td><code><?= childAdminH($account['login_name']) ?></code></td><td><span class="badge <?= (int)$account['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)$account['active'] === 1 ? 'aktivní' : 'vypnutý' ?></span> v<?= (int)$account['session_version'] ?></td><td><?= childAdminH($account['last_login_at'] ?: 'nikdy') ?></td>
            <td><form method="post" class="row g-1"><?= csrf_field() ?><input type="hidden" name="access_account_id" value="<?= (int)$account['id'] ?>"><div class="col-4"><select class="form-select form-select-sm" name="action"><option value="reset_password">Změnit heslo</option><option value="<?= (int)$account['active'] === 1 ? 'deactivate' : 'activate' ?>"><?= (int)$account['active'] === 1 ? 'Deaktivovat' : 'Aktivovat' ?></option></select></div><div class="col-3"><input class="form-control form-control-sm" type="password" name="password" minlength="12" maxlength="200" placeholder="nové heslo (12–200)"></div><div class="col-4"><input class="form-control form-control-sm" name="reason" placeholder="povinný důvod" required></div><div class="col-1"><button class="btn btn-outline-primary btn-sm">OK</button></div></form></td>
        </tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Audit</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Čas</th><th>Sportovec</th><th>Akce</th><th>Aktér</th><th>Poznámka</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= childAdminH($event['created_at']) ?></td><td><?= childAdminH($event['prijmeni'] . ' ' . $event['jmeno']) ?></td><td><code><?= childAdminH($event['action']) ?></code></td><td><?= childAdminH($event['actor_type'] === 'trainer' ? ($event['trainer_name'] ?: 'trenér #' . $event['actor_id']) : 'sportovec') ?></td><td><?= childAdminH($event['note']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</main>
</body>
</html>
