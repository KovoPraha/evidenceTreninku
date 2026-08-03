<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=sportovni_prehled.php');
    exit;
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';

function familyPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$overview = [];
$loadError = '';
try {
    $overview = familyPortalOverview($pdo, (int)$_SESSION['verejny_uzivatel_id']);
} catch (Throwable $exception) {
    error_log('booking/sportovni_prehled.php: ' . $exception->getMessage());
    $loadError = 'Sportovní přehled se nyní nepodařilo načíst.';
}

$roleLabels = ['guardian' => 'rodič / zástupce', 'self' => 'vlastní profil'];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sportovní přehled — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm"><div class="container">
    <a class="navbar-brand fw-bold" href="kalendar.php"><i class="bi bi-bicycle me-2 text-primary"></i>Kovopraha</a>
    <div class="d-flex gap-2 flex-wrap">
        <a href="moje_osoby.php" class="btn btn-outline-secondary btn-sm">Moje osoby</a>
        <a href="krouzky.php" class="btn btn-outline-secondary btn-sm">Kroužky</a>
        <a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Objednávky</a>
        <a href="kalendar.php" class="btn btn-outline-primary btn-sm">Kalendář</a>
        <form method="post" action="odhlaseni.php" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Odhlásit</button></form>
    </div>
</div></nav>
<main class="container py-4" style="max-width:1100px">
    <h1 class="h4 mb-1"><i class="bi bi-person-vcard me-2 text-primary"></i>Sportovní přehled</h1>
    <p class="text-muted">Soupisky, klubové události a zaznamenaná účast na trénincích pro vaše schválené profily.</p>

    <?php if ($loadError !== ''): ?><div class="alert alert-danger"><?= familyPageH($loadError) ?></div><?php endif; ?>
    <?php if ($loadError === '' && $overview === []): ?>
        <div class="alert alert-info">Nemáte žádný aktivní schválený profil. O propojení můžete požádat v části <a href="moje_osoby.php">Moje osoby</a>.</div>
    <?php endif; ?>

    <?php foreach ($overview as $profile): $person = $profile['person']; ?>
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div><strong class="fs-5"><?= familyPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></strong><div class="small text-muted">Datum narození: <?= familyPageH($person['narozeni'] ?: 'neuvedeno') ?></div></div>
                <div><?php foreach ($person['relation_roles'] as $role): ?><span class="badge text-bg-primary ms-1"><?= familyPageH($roleLabels[$role] ?? $role) ?></span><?php endforeach; ?></div>
            </div>
            <div class="card-body">
                <h2 class="h6">Soupisky</h2>
                <?php if ($profile['rosters'] === []): ?><p class="text-muted small">Žádná evidovaná soupiska.</p><?php else: ?>
                <div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Tým</th><th>Sezóna</th><th>Platnost</th><th>Stav</th></tr></thead><tbody>
                <?php foreach ($profile['rosters'] as $roster): ?><tr><td><strong><?= familyPageH($roster['team_name']) ?></strong><div class="small text-muted"><?= familyPageH(trim($roster['discipline'] . ' ' . $roster['age_label'])) ?></div></td><td><?= familyPageH($roster['season_name']) ?></td><td><?= familyPageH($roster['valid_from']) ?> – <?= familyPageH($roster['valid_to'] ?: 'dosud') ?></td><td><span class="badge <?= $roster['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= familyPageH($roster['status']) ?></span></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>

                <h2 class="h6">Klubové události</h2>
                <?php if ($profile['events'] === []): ?><p class="text-muted small">Žádná přihláška na klubovou událost.</p><?php else: ?>
                <div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Událost</th><th>Typ</th><th>Přihlášeno</th><th>Stav</th></tr></thead><tbody>
                <?php foreach ($profile['events'] as $event): ?><tr><td><?= familyPageH($event['event_name']) ?></td><td><?= familyPageH($event['event_type']) ?></td><td><?= familyPageH($event['registered_at']) ?></td><td><?= familyPageH($event['status']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>

                <h2 class="h6">Docházka na tréninky</h2>
                <?php if ($profile['trainings'] === []): ?><p class="text-muted small mb-0">Žádná zaznamenaná účast.</p><?php else: ?>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Datum</th><th>Náplň</th><th>Kategorie</th><th>Délka</th></tr></thead><tbody>
                <?php foreach ($profile['trainings'] as $training): ?><tr><td><?= familyPageH($training['datum']) ?></td><td><?= familyPageH($training['napln']) ?></td><td><?= familyPageH($training['kategorie']) ?></td><td><?= familyPageH($training['delka']) ?> h</td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
