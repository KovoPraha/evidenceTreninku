<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/localhost_acceptance_hub.php';

if (!localhostAcceptanceRequestIsAllowed($_SERVER, getenv('APP_HOST'))) {
    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    exit('Nenalezeno.');
}

require_once __DIR__ . '/includes/init.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/csrf_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = 'Obnovu se nepodařilo spustit.';
    $messageType = 'danger';
    if (!localhostAcceptanceRequestIsAllowed($_SERVER, getenv('APP_HOST'))) {
        http_response_code(404);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        exit('Nenalezeno.');
    } elseif (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } elseif (($_POST['action'] ?? '') !== 'reset_local_demo' || ($_POST['confirm_reset'] ?? '') !== '1') {
        $message = 'Obnova nebyla potvrzena.';
    } else {
        $result = localhostAcceptanceRunSeedReset(__DIR__);
        $message = $result['reason'];
        $messageType = $result['ok'] ? 'success' : 'danger';
    }
    $_SESSION['localhost_acceptance_flash'] = ['type' => $messageType, 'message' => $message];
    header('Location: testovaci_scenare.php', true, 303);
    exit;
}

function acceptanceHubH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$scenarios = localhostAcceptanceScenarios(__DIR__);
$resetAvailability = localhostAcceptanceSeedResetAvailability(__DIR__);
$flash = $_SESSION['localhost_acceptance_flash'] ?? null;
unset($_SESSION['localhost_acceptance_flash']);
$statusLabels = [
    'ready' => ['Připraveno', 'success'],
    'partial' => ['Částečně připraveno', 'warning'],
    'unavailable' => ['Nedostupné', 'danger'],
];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Testovací scénáře M1</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4" style="max-width:1180px">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Testovací scénáře M1</h1>
            <p class="text-muted mb-0">Lokální akceptační průchod Evidence + e-shop + KIS. Tato stránka je mimo loopback nedostupná.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Administrace</a>
    </div>

    <div class="alert alert-info">
        <strong>Přihlášení:</strong> údaje vezměte z výstupu lokálního seedu nebo ze svého správce hesel.
        Rozcestník hesla úmyslně nezobrazuje. Zákaznickou a administrátorskou část otevírejte v oddělených profilech či anonymních oknech.
    </div>
    <?php if (is_array($flash) && isset($flash['type'], $flash['message'])): ?>
        <div class="alert alert-<?= acceptanceHubH($flash['type']) ?>"><?= acceptanceHubH($flash['message']) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($scenarios as $scenario): $status = $statusLabels[$scenario['status']]; ?>
            <div class="col-12">
                <article class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                            <div>
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                    <span class="badge text-bg-dark"><?= acceptanceHubH($scenario['id']) ?></span>
                                    <span class="badge text-bg-<?= $scenario['area'] === 'Administrace' ? 'primary' : 'secondary' ?>"><?= acceptanceHubH($scenario['area']) ?></span>
                                    <span class="badge text-bg-light border text-dark"><?= acceptanceHubH($scenario['role']) ?></span>
                                </div>
                                <h2 class="h5 mb-0"><?= acceptanceHubH($scenario['expected']) ?></h2>
                            </div>
                            <span class="badge text-bg-<?= acceptanceHubH($status[1]) ?>"><?= acceptanceHubH($status[0]) ?></span>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-lg-7">
                                <h3 class="h6">Postup</h3>
                                <ol class="mb-2">
                                    <?php foreach ($scenario['steps'] as $step): ?><li><?= acceptanceHubH($step) ?></li><?php endforeach; ?>
                                </ol>
                                <p class="small text-muted mb-0"><strong>Poznámka:</strong> <?= acceptanceHubH($scenario['note']) ?></p>
                            </div>
                            <div class="col-lg-5">
                                <h3 class="h6">Odkazy</h3>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($scenario['links'] as $link): ?>
                                        <a class="btn btn-sm btn-outline-<?= $link['scope'] === 'admin' ? 'primary' : 'secondary' ?>"
                                           href="<?= acceptanceHubH($link['path']) ?>">
                                            <?= acceptanceHubH($link['label']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5">Jak zapsat připomínku</h2>
            <p class="mb-0">Uveďte ID scénáře, roli, pozorované chování, očekávané chování a důležitost:
                <code>blokuje</code>, <code>důležité</code> nebo <code>námět</code>. Rozcestník nic automaticky nemaže ani neobnovuje.</p>
        </div>
    </section>
    <section class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                <div>
                    <h2 class="h5 mb-1">Obnovit localhost demo</h2>
                    <p class="text-muted mb-0"><?= acceptanceHubH($resetAvailability['reason']) ?> Seed je opakovatelný a nemaže cizí legacy data.</p>
                </div>
                <?php if ($resetAvailability['available']): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_local_demo">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="confirm-reset" name="confirm_reset" value="1" required>
                            <label class="form-check-label" for="confirm-reset">Potvrzuji obnovu pouze testovacích dat LOCALHOST.</label>
                        </div>
                        <button class="btn btn-outline-danger">Obnovit demo data</button>
                    </form>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Reset nedostupný</span>
                <?php endif; ?>
            </div>
            <p class="small text-muted mt-3 mb-0">Výstup seedu může obsahovat přihlašovací údaje, proto jej stránka vždy zahodí a ukáže pouze obecný výsledek.</p>
        </div>
    </section>
</main>
</body>
</html>
