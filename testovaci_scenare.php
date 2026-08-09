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
require_once __DIR__ . '/includes/localhost_acceptance_feedback.php';
require_once __DIR__ . '/includes/m2_finalization.php';

$scenariosA = localhostAcceptanceScenarios(__DIR__);
$scenariosB = localhostAcceptanceScenariosB(__DIR__);
$scenarios = array_merge($scenariosA, $scenariosB);
$scenarioIds = array_column($scenarios, 'id');
$firstBScenarioId = $scenariosB === [] ? null : (string)$scenariosB[0]['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['export'] ?? '') === 'markdown') {
    try {
        $feedbackExport = localhostAcceptanceFeedbackLoad(__DIR__);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="acceptance-results-' . date('Y-m-d-His') . '.md"');
        header('Cache-Control: no-store');
        echo localhostAcceptanceFeedbackMarkdown($scenarios, $feedbackExport['scenarios']);
    } catch (Throwable $exception) {
        error_log('acceptance feedback export: ' . $exception->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Výsledky se nepodařilo bezpečně exportovat.';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = 'Požadavek se nepodařilo zpracovat.';
    $messageType = 'danger';
    $redirectAnchor = '';
    if (!localhostAcceptanceRequestIsAllowed($_SERVER, getenv('APP_HOST'))) {
        http_response_code(404);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        exit('Nenalezeno.');
    } elseif (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_feedback') {
            $scenarioId = (string)($_POST['scenario_id'] ?? '');
            try {
                localhostAcceptanceFeedbackSave(__DIR__, $scenarioId, $_POST, (int)$_SESSION['trener_id'], $scenarioIds);
                $message = 'Výsledek scénáře ' . $scenarioId . ' byl lokálně uložen.';
                $messageType = 'success';
                $redirectAnchor = '#' . rawurlencode($scenarioId);
            } catch (InvalidArgumentException | LocalhostAcceptanceFeedbackException $exception) {
                $message = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('acceptance feedback save: ' . $exception->getMessage());
                $message = 'Výsledek se nepodařilo bezpečně uložit.';
            }
        } elseif ($action === 'reset_local_demo' && ($_POST['confirm_reset'] ?? '') === '1') {
            $result = localhostAcceptanceRunSeedReset(__DIR__);
            $message = $result['reason'];
            $messageType = $result['ok'] ? 'success' : 'danger';
        } else {
            $message = 'Požadovaná operace nebyla potvrzena.';
        }
    }
    $_SESSION['localhost_acceptance_flash'] = ['type' => $messageType, 'message' => $message];
    header('Location: testovaci_scenare.php' . $redirectAnchor, true, 303);
    exit;
}

function acceptanceHubH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$resetAvailability = localhostAcceptanceSeedResetAvailability(__DIR__);
$flash = $_SESSION['localhost_acceptance_flash'] ?? null;
unset($_SESSION['localhost_acceptance_flash']);
$feedbackError = '';
try {
    $feedbackData = localhostAcceptanceFeedbackLoad(__DIR__);
} catch (LocalhostAcceptanceFeedbackException $exception) {
    $feedbackData = ['version' => 1, 'updated_at' => null, 'scenarios' => []];
    $feedbackError = $exception->getMessage();
}
$feedbackCounts = array_fill_keys(['not_tested', 'pass', 'partial', 'fail', 'blocked'], 0);
foreach ($scenarios as $scenario) {
    $feedbackResult = (string)($feedbackData['scenarios'][$scenario['id']]['result'] ?? 'not_tested');
    $feedbackCounts[isset($feedbackCounts[$feedbackResult]) ? $feedbackResult : 'not_tested']++;
}
// Závěrečná brána M2 zůstává vázaná výhradně na vlastnickou sadu A01–A10;
// rozšířená sada B je průběžná regresní prohlídka a bránu neovlivňuje.
$finalization = m2FinalizationStatus($pdo, __DIR__, $scenariosA, $feedbackData['scenarios']);
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
    <title>Finalizace a testovací scénáře M2</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?php appUiAssets(); ?>
</head>
<body class="bg-light">
<main class="container py-4" style="max-width:1180px">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Finalizace a testovací scénáře M2</h1>
            <p class="text-muted mb-0">Lokální akceptační průchod Evidence + e-shop + KIS. Tato stránka je mimo loopback nedostupná.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Administrace</a>
    </div>

    <div class="alert alert-info">
        <strong>Přihlášení:</strong> údaje vezměte z výstupu lokálního seedu nebo ze svého správce hesel.
        Rozcestník hesla úmyslně nezobrazuje. Zákaznickou a administrátorskou část otevírejte v oddělených profilech či anonymních oknech.
    </div>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body d-flex flex-wrap justify-content-between gap-3 align-items-center">
        <div><strong>Výsledky prohlídky:</strong>
            <span class="badge text-bg-success">PASS <?= (int)$feedbackCounts['pass'] ?></span>
            <span class="badge text-bg-warning">PARTIAL <?= (int)$feedbackCounts['partial'] ?></span>
            <span class="badge text-bg-danger">FAIL <?= (int)$feedbackCounts['fail'] ?></span>
            <span class="badge text-bg-dark">BLOCKED <?= (int)$feedbackCounts['blocked'] ?></span>
            <span class="badge text-bg-secondary">NETESTOVÁNO <?= (int)$feedbackCounts['not_tested'] ?></span>
            <?php if ($feedbackData['updated_at']): ?><div class="small text-muted mt-1">Naposledy uloženo <?= acceptanceHubH($feedbackData['updated_at']) ?></div><?php endif; ?>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="?export=markdown">Stáhnout výsledky pro GitHub / Cowork</a>
    </div></div>
    <section class="card border-0 shadow-sm mb-3" aria-labelledby="m2-finalization-title"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-3">
            <div><h2 class="h5 mb-1" id="m2-finalization-title">Závěrečná brána M2</h2><p class="text-muted mb-0">Technické kontroly se počítají automaticky. Uživatelskou akceptaci uzavřete uložením PASS u scénářů A01–A10.</p></div>
            <span class="badge fs-6 text-bg-<?= $finalization['close_ready'] ? 'success' : 'warning' ?>"><?= $finalization['close_ready'] ? 'M2 lze uzavřít' : 'M2 čeká na dokončení' ?></span>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Technická připravenost</div><div class="h3 mb-0"><?= (int)$finalization['technical_passed'] ?>/<?= (int)$finalization['technical_total'] ?></div></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Vlastníkem potvrzeno</div><div class="h3 mb-0"><?= (int)$finalization['accepted'] ?>/<?= (int)$finalization['scenario_total'] ?></div></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Blokující výsledky</div><div class="h3 mb-0"><?= (int)$finalization['blocking'] ?></div></div></div>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($finalization['checks'] as $check): ?><li class="list-group-item px-0 d-flex gap-3 align-items-start"><span class="badge text-bg-<?= $check['status'] === 'pass' ? 'success' : ($check['status'] === 'wait' ? 'warning' : 'danger') ?>"><?= $check['status'] === 'pass' ? 'OK' : ($check['status'] === 'wait' ? 'ČEKÁ' : 'CHYBA') ?></span><div><strong><?= acceptanceHubH($check['label']) ?></strong><div class="small text-muted"><?= acceptanceHubH($check['detail']) ?></div></div></li><?php endforeach; ?>
        </ul>
        <p class="small text-muted mt-3 mb-0">Tato brána nic nenasazuje na produkci a nespouští ostrý import, platby ani skutečné e-maily.</p>
    </div></section>
    <?php if ($feedbackError !== ''): ?><div class="alert alert-danger"><?= acceptanceHubH($feedbackError) ?></div><?php endif; ?>
    <?php if (is_array($flash) && isset($flash['type'], $flash['message'])): ?>
        <div class="alert alert-<?= acceptanceHubH($flash['type']) ?>"><?= acceptanceHubH($flash['message']) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($scenarios as $scenario): $status = $statusLabels[$scenario['status']]; ?>
            <?php if ($firstBScenarioId !== null && $scenario['id'] === $firstBScenarioId): ?>
            <div class="col-12">
                <div class="border-top pt-4 mt-2">
                    <h2 class="h4 mb-1">Rozšířená sada B — kompletní funkční prohlídka</h2>
                    <p class="text-muted mb-0">Scénáře B01–B<?= str_pad((string)count($scenariosB), 2, '0', STR_PAD_LEFT) ?> pokrývají vše, co systém umí: tréninky, závody, plánování, e-shop, kroužky, rodinné účty, KIS i administraci. Výsledky se ukládají stejně jako u sady A, ale závěrečnou bránu M2 neblokují.</p>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <article class="card shadow-sm border-0" id="<?= acceptanceHubH($scenario['id']) ?>">
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
                        <?php $savedFeedback = is_array($feedbackData['scenarios'][$scenario['id']] ?? null) ? $feedbackData['scenarios'][$scenario['id']] : []; ?>
                        <form method="post" class="border-top mt-3 pt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_feedback">
                            <input type="hidden" name="scenario_id" value="<?= acceptanceHubH($scenario['id']) ?>">
                            <div class="row g-2">
                                <div class="col-md-3"><label class="form-label" for="result-<?= acceptanceHubH($scenario['id']) ?>">Výsledek</label><select class="form-select" id="result-<?= acceptanceHubH($scenario['id']) ?>" name="result">
                                    <?php foreach (['not_tested'=>'Netestováno','pass'=>'PASS – funguje','partial'=>'PARTIAL – částečně','fail'=>'FAIL – chyba','blocked'=>'BLOCKED – nelze dokončit'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($savedFeedback['result'] ?? 'not_tested') === $value ? 'selected' : '' ?>><?= acceptanceHubH($label) ?></option><?php endforeach; ?>
                                </select></div>
                                <div class="col-md-3"><label class="form-label" for="importance-<?= acceptanceHubH($scenario['id']) ?>">Důležitost</label><select class="form-select" id="importance-<?= acceptanceHubH($scenario['id']) ?>" name="importance">
                                    <?php foreach (['none'=>'Bez připomínky','blocks'=>'Blokuje','important'=>'Důležité','idea'=>'Námět'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($savedFeedback['importance'] ?? 'none') === $value ? 'selected' : '' ?>><?= acceptanceHubH($label) ?></option><?php endforeach; ?>
                                </select></div>
                                <div class="col-md-6"><label class="form-label" for="observed-<?= acceptanceHubH($scenario['id']) ?>">Co jste pozoroval(a)</label><textarea class="form-control" id="observed-<?= acceptanceHubH($scenario['id']) ?>" name="observed" maxlength="4000" rows="2" placeholder="Co fungovalo nebo co se stalo?"><?= acceptanceHubH($savedFeedback['observed'] ?? '') ?></textarea></div>
                                <div class="col-md-9"><label class="form-label" for="expected-<?= acceptanceHubH($scenario['id']) ?>">Co jste očekával(a)</label><textarea class="form-control" id="expected-<?= acceptanceHubH($scenario['id']) ?>" name="expected" maxlength="4000" rows="2" placeholder="Vyplňte hlavně při chybě nebo námětu."><?= acceptanceHubH($savedFeedback['expected'] ?? '') ?></textarea></div>
                                <div class="col-md-3 d-grid align-self-end"><button class="btn btn-primary">Uložit výsledek <?= acceptanceHubH($scenario['id']) ?></button></div>
                            </div>
                            <div class="form-text">Nezadávejte hesla ani ostré osobní údaje. Výsledek zůstává na localhostu, dokud export vědomě nepřidáte do Gitu.</div>
                        </form>
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
