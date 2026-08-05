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

function childPageStatusLabel(string $status, string $context): string
{
    $labels = [
        'membership' => [
            'aktivni' => 'Aktivní členství',
            'cekajici' => 'Čeká na potvrzení',
            'neaktivni' => 'Neaktivní',
            'archiv' => 'Archivováno',
        ],
        'roster' => [
            'active' => 'Aktivní',
            'ended' => 'Ukončeno',
            'cancelled' => 'Zrušeno',
        ],
        'event' => [
            'confirmed' => 'Přihlášeno',
            'waitlisted' => 'Čekací listina',
            'payment_pending' => 'Čeká na úhradu',
            'cancelled' => 'Zrušeno',
            'refunded' => 'Vráceno',
        ],
        'payment' => [
            'paid' => 'Uhrazeno',
            'pending' => 'Čeká na úhradu',
            'unpaid' => 'Neuhrazeno',
            'refund_required' => 'Čeká na vrácení',
            'refunded' => 'Vráceno',
            'cancelled' => 'Zrušeno',
        ],
    ];

    return $labels[$context][$status] ?? $status;
}

function childPageStatusClass(string $status): string
{
    return match ($status) {
        'aktivni', 'active', 'confirmed', 'paid', 'refunded' => 'text-bg-success',
        'cekajici', 'waitlisted', 'payment_pending', 'pending', 'refund_required' => 'text-bg-warning',
        'cancelled', 'ended', 'neaktivni', 'archiv', 'unpaid' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function childPageEventTypeLabel(string $eventType): string
{
    return match ($eventType) {
        'race' => 'Závod',
        'camp' => 'Soustředění',
        'trip' => 'Výjezd',
        'club_event' => 'Klubová událost',
        default => $eventType,
    };
}

function childPageDateLabel(?string $value, bool $withTime = false): string
{
    if ($value === null || trim($value) === '') {
        return 'neuvedeno';
    }
    try {
        return (new DateTimeImmutable($value))->format($withTime ? 'j. n. Y H:i' : 'j. n. Y');
    } catch (Throwable) {
        return $value;
    }
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
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-4" style="max-width:1050px">
    <div class="alert alert-info small">Omezený režim sportovce: vidíte výhradně údaje profilu <strong><?= childPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></strong>. Změny přihlášek, rodiny a objednávek provádí rodič nebo klub.</div>

    <section class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h1 class="h4 mb-1"><?= childPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></h1>
        <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
            <span>Datum narození: <?= childPageH(childPageDateLabel($person['narozeni'] ?: null)) ?></span>
            <span class="badge <?= childPageH(childPageStatusClass((string)$person['stav_clenstvi'])) ?>"><?= childPageH(childPageStatusLabel((string)$person['stav_clenstvi'], 'membership')) ?></span>
        </div>
    </div></section>

    <div class="row g-3 mb-4" aria-label="Souhrn sportovce">
        <?php foreach ([
            ['label' => 'Tréninky', 'value' => count($overview['trainings'])],
            ['label' => 'Soupisky', 'value' => count($overview['rosters'])],
            ['label' => 'Události', 'value' => count($overview['events'])],
            ['label' => 'Platby', 'value' => count($overview['payments']) + count($overview['member_charges'])],
        ] as $summary): ?>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small"><?= childPageH($summary['label']) ?></div>
                <div class="fs-3 fw-semibold"><?= childPageH($summary['value']) ?></div>
            </div></div></div>
        <?php endforeach; ?>
    </div>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje tréninky</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Datum</th><th>Náplň</th><th>Kategorie</th><th>Délka</th></tr></thead><tbody>
        <?php if ($overview['trainings'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná zaznamenaná účast.</td></tr><?php endif; ?>
        <?php foreach ($overview['trainings'] as $row): ?><tr><td><?= childPageH(childPageDateLabel((string)$row['datum'])) ?></td><td><?= childPageH($row['napln']) ?></td><td><?= childPageH($row['kategorie']) ?></td><td><?= childPageH($row['delka']) ?> h</td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje soupisky</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tým</th><th>Sezóna</th><th>Platnost</th><th>Stav</th></tr></thead><tbody>
        <?php if ($overview['rosters'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná soupiska.</td></tr><?php endif; ?>
        <?php foreach ($overview['rosters'] as $row): ?><tr><td><?= childPageH($row['team_name']) ?><div class="small text-muted"><?= childPageH(trim($row['discipline'] . ' ' . $row['age_label'])) ?></div></td><td><?= childPageH($row['season_name']) ?></td><td><?= childPageH(childPageDateLabel((string)$row['valid_from'])) ?> – <?= childPageH($row['valid_to'] ? childPageDateLabel((string)$row['valid_to']) : 'dosud') ?></td><td><span class="badge <?= childPageH(childPageStatusClass((string)$row['status'])) ?>"><?= childPageH(childPageStatusLabel((string)$row['status'], 'roster')) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje události</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Událost</th><th>Typ</th><th>Přihlášeno</th><th>Stav</th></tr></thead><tbody>
        <?php if ($overview['events'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná přihláška na událost.</td></tr><?php endif; ?>
        <?php foreach ($overview['events'] as $row): ?><tr><td><?= childPageH($row['event_name']) ?></td><td><?= childPageH(childPageEventTypeLabel((string)$row['event_type'])) ?></td><td><?= childPageH(childPageDateLabel((string)$row['registered_at'], true)) ?></td><td><span class="badge <?= childPageH(childPageStatusClass((string)$row['status'])) ?>"><?= childPageH(childPageStatusLabel((string)$row['status'], 'event')) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Moje členské předpisy</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Předpis</th><th>Částka</th><th>Splatnost</th><th>Stav</th></tr></thead><tbody>
        <?php if ($overview['member_charges'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádný členský předpis.</td></tr><?php endif; ?>
        <?php foreach ($overview['member_charges'] as $row): ?><tr><td><strong><?= childPageH($row['title_snapshot']) ?></strong><div class="small text-muted"><code><?= childPageH($row['public_code']) ?></code></div></td><td><?= childPageH(number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . $row['currency']) ?></td><td><?= childPageH(childPageDateLabel($row['due_on'] ?: null)) ?></td><td><span class="badge <?= childPageH(childPageStatusClass((string)$row['status'])) ?>"><?= childPageH(childPageStatusLabel((string)$row['status'], 'payment')) ?></span><?php if ($row['paid_at']): ?><div class="small text-muted">Uhrazeno <?= childPageH(childPageDateLabel((string)$row['paid_at'])) ?></div><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Moje objednávkové platby</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Objednávka</th><th>Položka</th><th>Částka</th><th>Platba</th></tr></thead><tbody>
        <?php if ($overview['payments'] === []): ?><tr><td colspan="4" class="text-muted p-3">Zatím žádná objednávková položka přiřazená tomuto profilu.</td></tr><?php endif; ?>
        <?php foreach ($overview['payments'] as $row): ?><tr><td><code><?= childPageH($row['public_code']) ?></code></td><td><?= childPageH($row['product_name_snapshot']) ?></td><td><?= childPageH(number_format(((int)$row['line_amount_minor']) / 100, 2, ',', ' ') . ' ' . $row['currency']) ?></td><td><span class="badge <?= childPageH(childPageStatusClass((string)$row['payment_status'])) ?>"><?= childPageH(childPageStatusLabel((string)$row['payment_status'], 'payment')) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
