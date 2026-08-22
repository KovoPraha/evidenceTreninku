<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/staff_workspaces.php';

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

$definitions = staffPositionDefinitions();
$activeCode = staffActivePosition();
if ($activeCode === '' || !isset($definitions[$activeCode])) {
    http_response_code(403);
    exit('Účet nemá přiřazenou pracovní pozici. Obraťte se na správce systému.');
}
$active = $definitions[$activeCode];
$activeGroups = staffPositionMenuGroups($activeCode, defined('JE_LOKALNE') && JE_LOKALNE === true);
$available = staffAvailablePositions();
$trainerName = trim((string)($_SESSION['trener_jmeno'] ?? ''));
if ($trainerName === '') {
    $statement = $pdo->prepare('SELECT jmeno FROM treneri WHERE id=?');
    $statement->execute([(int)$_SESSION['trener_id']]);
    $trainerName = (string)$statement->fetchColumn();
}
function staffDashboardH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= staffDashboardH($active['label']) ?> – pracovní rozcestník</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{background:#f3f5f8}.workspace-hero{background:linear-gradient(135deg,#173b67,#156b63);color:#fff;border-radius:1rem}
        .workspace-link{display:block;height:100%;color:inherit;text-decoration:none}.workspace-link .card{height:100%;border:0;transition:transform .12s,box-shadow .12s}
        .workspace-link:hover .card{transform:translateY(-2px);box-shadow:0 .5rem 1.2rem rgba(0,0,0,.12)!important}
        .workspace-icon{width:2.5rem;height:2.5rem;display:grid;place-items:center;border-radius:.7rem;background:#e9f2ff;color:#0d6efd;font-size:1.2rem}
    </style>
</head>
<body>
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1240px">
    <section class="workspace-hero p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <div class="small text-white-50 mb-1">Pracovní rozcestník · <?= staffDashboardH($trainerName) ?></div>
                <h1 class="h2 mb-2"><i class="bi bi-<?= staffDashboardH($active['icon']) ?> me-2"></i><?= staffDashboardH($active['label']) ?></h1>
                <p class="mb-0 text-white-50"><?= staffDashboardH($active['description']) ?></p>
            </div>
            <?php if (staffIsSuperadmin()): ?>
                <span class="badge text-bg-warning text-dark fs-6 align-self-start align-self-lg-center"><i class="bi bi-shield-lock me-1"></i>Superadmin · aktivní kontext</span>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= staffDashboardH($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= staffDashboardH($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (count($available) > 1): ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
            <div class="me-lg-auto">
                <div class="fw-semibold">Přepnout pracovní pozici</div>
                <div class="small text-muted">Menu se neslučují. Po přepnutí uvidíte pouze vybranou agendu.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($available as $code): if ($code === $activeCode) continue; $position = $definitions[$code]; ?>
                <form method="post" action="prepnout_pracovni_pozici.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="position" value="<?= staffDashboardH($code) ?>">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-<?= staffDashboardH($position['icon']) ?> me-1"></i><?= staffDashboardH($position['short_label']) ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php foreach ($activeGroups as $group): ?>
    <section class="mb-4" aria-labelledby="group-<?= staffDashboardH(md5((string)$group['label'])) ?>">
        <h2 class="h5 mb-3" id="group-<?= staffDashboardH(md5((string)$group['label'])) ?>"><i class="bi bi-<?= staffDashboardH($group['icon']) ?> me-2 text-primary"></i><?= staffDashboardH($group['label']) ?></h2>
        <div class="row g-3">
            <?php foreach ($group['items'] as $item): ?>
            <div class="col-md-6 col-xl-3">
                <a class="workspace-link" href="<?= staffDashboardH($item['route']) ?>">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="workspace-icon mb-3"><i class="bi bi-<?= staffDashboardH($item['icon']) ?>"></i></div>
                            <h3 class="h6 mb-1"><?= staffDashboardH($item['label']) ?></h3>
                            <p class="small text-muted mb-0"><?= staffDashboardH($item['description']) ?></p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</main>
</body>
</html>
