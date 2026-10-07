<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/includes/funkce.php';
if (!canAccess('planovac')) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/training_rsvp.php';

function trainingRsvpAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$planId = (int)($_GET['plan_id'] ?? 0);
$statement = $pdo->prepare(
    'SELECT p.id,p.nazev,p.datum,p.cas_od,p.cas_do,p.stav,t.jmeno AS trener_jmeno '
    . 'FROM planovane_treninky p LEFT JOIN treneri t ON t.id=p.trener_id WHERE p.id=?'
);
$statement->execute([$planId]);
$plan = $statement->fetch(PDO::FETCH_ASSOC);
if (!$plan) {
    http_response_code(404);
    exit('Plánovaný trénink nebyl nalezen.');
}
$responses = trainingRsvpPlanOverview($pdo, $planId);
$summary = trainingRsvpPlanSummaries($pdo, [$planId])[$planId] ?? ['expected'=>0,'going'=>0,'not_going'=>0,'pending'=>0];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odpovědi k tréninku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1000px">
    <a class="btn btn-sm btn-outline-secondary mb-3" href="planovac.php?datum=<?= trainingRsvpAdminH($plan['datum']) ?>"><i class="bi bi-arrow-left me-1"></i>Zpět do plánovače</a>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-1"><?= trainingRsvpAdminH($plan['nazev']) ?></h1>
            <p class="text-muted mb-3"><?= trainingRsvpAdminH((new DateTimeImmutable((string)$plan['datum']))->format('j. n. Y')) ?><?= $plan['cas_od'] ? ' · ' . trainingRsvpAdminH(substr((string)$plan['cas_od'], 0, 5)) : '' ?><?= $plan['cas_do'] ? '–' . trainingRsvpAdminH(substr((string)$plan['cas_do'], 0, 5)) : '' ?> · trenér <?= trainingRsvpAdminH($plan['trener_jmeno'] ?: 'neuveden') ?></p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="badge text-bg-primary">Očekáváno <?= (int)$summary['expected'] ?></span>
                <span class="badge text-bg-success">Zúčastní se <?= (int)$summary['going'] ?></span>
                <span class="badge text-bg-secondary">Nezúčastní se <?= (int)$summary['not_going'] ?></span>
                <span class="badge text-bg-warning">Bez odpovědi <?= (int)$summary['pending'] ?></span>
            </div>
            <?php if ($responses === []): ?>
                <div class="alert alert-warning mb-0">Trénink nemá připojenou soupisku se sportovci. Odpovědi proto nelze sbírat.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>Sportovec</th><th>Soupiska</th><th>Odpověď</th><th>Změněno</th></tr></thead>
                    <tbody><?php foreach ($responses as $row): $response=(string)($row['response'] ?? ''); ?><tr>
                        <td><strong><?= trainingRsvpAdminH(trim((string)$row['prijmeni'] . ' ' . (string)$row['jmeno'])) ?></strong></td>
                        <td><?= trainingRsvpAdminH($row['team_name']) ?></td>
                        <td><span class="badge <?= $response === 'going' ? 'text-bg-success' : ($response === 'not_going' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= trainingRsvpAdminH(trainingRsvpLabel($response)) ?></span></td>
                        <td class="text-muted small"><?= $row['responded_at'] ? trainingRsvpAdminH((new DateTimeImmutable((string)$row['responded_at']))->format('j. n. Y H:i')) : '—' ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
            <p class="small text-muted mt-3 mb-0">Odpověď je orientační pro plánování. Skutečnou docházku zadává trenér samostatně v evidenci tréninku.</p>
        </div>
    </div>
</main>
</body>
</html>
