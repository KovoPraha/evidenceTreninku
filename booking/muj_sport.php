<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['sportovec_pristup_id'])) {
    header('Location: sportovec_prihlaseni.php');
    exit;
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/child_access.php';

function childPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    // No sportovec_id is accepted from GET/POST. The DB derives the only
    // visible person from the revocable access-account id stored in session.
    $overview = childAccessOverview($pdo, (int)$_SESSION['sportovec_pristup_id']);
} catch (Throwable $exception) {
    error_log('Athlete dashboard failed: ' . $exception->getMessage());
    auth_session_clear_identity('child');
    app_session_mark_identity_changed();
    header('Location: sportovec_prihlaseni.php', true, 303);
    exit;
}
$person = $overview['person'];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Můj sport — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar bg-white border-bottom shadow-sm"><div class="container">
    <span class="navbar-brand fw-bold">Můj sport</span>
    <form method="post" action="sportovec_odhlaseni.php"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Odhlásit</button></form>
</div></nav>
<main class="container py-4" style="max-width:1050px">
    <div class="alert alert-info small">Omezený režim sportovce: vidíte výhradně údaje profilu <strong><?= childPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></strong>. Změny přihlášek, rodiny a objednávek provádí rodič nebo klub.</div>

    <section class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h1 class="h4 mb-1"><?= childPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></h1>
        <div class="text-muted small">Datum narození: <?= childPageH($person['narozeni'] ?: 'neuvedeno') ?> · členství: <?= childPageH($person['stav_clenstvi'] ?: 'neuvedeno') ?></div>
    </div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje tréninky</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Datum</th><th>Náplň</th><th>Kategorie</th><th>Délka</th></tr></thead><tbody>
        <?php if ($overview['trainings'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná zaznamenaná účast.</td></tr><?php endif; ?>
        <?php foreach ($overview['trainings'] as $row): ?><tr><td><?= childPageH($row['datum']) ?></td><td><?= childPageH($row['napln']) ?></td><td><?= childPageH($row['kategorie']) ?></td><td><?= childPageH($row['delka']) ?> h</td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje soupisky</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tým</th><th>Sezóna</th><th>Platnost</th><th>Stav</th></tr></thead><tbody>
        <?php if ($overview['rosters'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná soupiska.</td></tr><?php endif; ?>
        <?php foreach ($overview['rosters'] as $row): ?><tr><td><?= childPageH($row['team_name']) ?><div class="small text-muted"><?= childPageH(trim($row['discipline'] . ' ' . $row['age_label'])) ?></div></td><td><?= childPageH($row['season_name']) ?></td><td><?= childPageH($row['valid_from']) ?> – <?= childPageH($row['valid_to'] ?: 'dosud') ?></td><td><?= childPageH($row['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje události</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Událost</th><th>Typ</th><th>Přihlášeno</th><th>Stav</th></tr></thead><tbody>
        <?php if ($overview['events'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná přihláška na událost.</td></tr><?php endif; ?>
        <?php foreach ($overview['events'] as $row): ?><tr><td><?= childPageH($row['event_name']) ?></td><td><?= childPageH($row['event_type']) ?></td><td><?= childPageH($row['registered_at']) ?></td><td><?= childPageH($row['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Moje platby</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Objednávka</th><th>Položka</th><th>Částka</th><th>Platba</th></tr></thead><tbody>
        <?php if ($overview['payments'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná objednávková položka přiřazená tomuto profilu.</td></tr><?php endif; ?>
        <?php foreach ($overview['payments'] as $row): ?><tr><td><code><?= childPageH($row['public_code']) ?></code></td><td><?= childPageH($row['product_name_snapshot']) ?></td><td><?= childPageH(number_format(((int)$row['line_amount_minor']) / 100, 2, ',', ' ') . ' ' . $row['currency']) ?></td><td><?= childPageH($row['payment_status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
</main>
</body>
</html>
