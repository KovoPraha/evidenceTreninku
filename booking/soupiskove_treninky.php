<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/child_access.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';
require_once dirname(__DIR__) . '/includes/athlete_training_timeline.php';
require_once dirname(__DIR__) . '/includes/calendar_ui.php';

function rosterTrainingPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$year = (int)($_GET['rok'] ?? date('Y'));
if ($year < 2000 || $year > (int)date('Y') + 2) $year = (int)date('Y');
$isChild = isset($_SESSION['sportovec_pristup_id']);
$isFamily = isset($_SESSION['verejny_uzivatel_id']);
if (!$isChild && !$isFamily) {
    header('Location: prihlaseni.php?redirect=soupiskove_treninky.php', true, 303);
    exit;
}
try {
    if ($isChild) {
        $overview = childAccessOverview($pdo, (int)$_SESSION['sportovec_pristup_id']);
        $people = [$overview['person']];
        $back = 'muj_sport.php';
    } else {
        $people = familyPortalAuthorizedPeople($pdo, (int)$_SESSION['verejny_uzivatel_id']);
        $back = 'sportovni_prehled.php';
    }
    $requestedId = (int)($_GET['sportovec_id'] ?? 0);
    $person = null;
    foreach ($people as $candidate) {
        if ($requestedId === 0 || (int)$candidate['sportovec_id'] === $requestedId) {
            $person = $candidate;
            break;
        }
    }
    if ($person === null || ($isChild && $requestedId > 0 && (int)$person['sportovec_id'] !== $requestedId)) {
        http_response_code(403);
        throw new RuntimeException('K tomuto profilu nemáte přístup.');
    }
    $items = athleteTrainingTimeline($pdo, (int)$person['sportovec_id'], $year);
} catch (Throwable $exception) {
    if (http_response_code() !== 403) http_response_code(500);
    error_log('booking/soupiskove_treninky.php: ' . $exception->getMessage());
    $error = http_response_code() === 403 ? 'K tomuto profilu nemáte přístup.' : 'Tréninky se nyní nepodařilo načíst.';
}
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tréninky ze soupisek — Kovo Praha</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?></head><body class="bg-light"><?php publicShellNav(); ?>
<main class="container py-4" style="max-width:1050px"><a href="<?= rosterTrainingPageH($back ?? 'sportovni_prehled.php') ?>" class="small">← Zpět na přehled</a>
<?php if (isset($error)): ?><div class="alert alert-danger mt-3"><?= rosterTrainingPageH($error) ?></div>
<?php else: ?>
<h1 class="h3 mt-3">Tréninky: <?= rosterTrainingPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></h1>
<p class="text-muted">Plánované i zaznamenané tréninky všech soupisek, do kterých byl sportovec zařazen v den tréninku. Skutečná účast je uvedena zvlášť.</p>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Výběr sportovce a roku">
<?php foreach ($people as $candidate): ?><a class="btn btn-sm <?= (int)$candidate['sportovec_id'] === (int)$person['sportovec_id'] ? 'btn-primary' : 'btn-outline-primary' ?>" href="?sportovec_id=<?= (int)$candidate['sportovec_id'] ?>&amp;rok=<?= $year ?>"><?= rosterTrainingPageH($candidate['jmeno'] . ' ' . $candidate['prijmeni']) ?></a><?php endforeach; ?>
</nav>
<nav class="d-flex align-items-center gap-3 mb-3" aria-label="Výběr roku"><a href="?sportovec_id=<?= (int)$person['sportovec_id'] ?>&amp;rok=<?= $year - 1 ?>">← <?= $year - 1 ?></a><strong><?= $year ?></strong><?php if ($year < (int)date('Y') + 2): ?><a href="?sportovec_id=<?= (int)$person['sportovec_id'] ?>&amp;rok=<?= $year + 1 ?>"><?= $year + 1 ?> →</a><?php endif; ?></nav>
<?php if ($items === []): ?><div class="alert alert-info">V tomto roce nejsou žádné tréninky z evidovaných soupisek.</div><?php else: ?>
<div class="accordion" id="training-history">
<?php foreach ($items as $index => $item): $itemId = 'training-' . $index; ?>
<details class="card mb-2"><summary class="card-header d-flex flex-wrap gap-2 justify-content-between"><span><strong><?= rosterTrainingPageH((new DateTimeImmutable((string)$item['datum']))->format('j. n. Y')) ?></strong> · <?= rosterTrainingPageH($item['title']) ?></span><span><?= !empty($item['attended']) ? 'Účast zaznamenána' : (!empty($item['response']) ? rosterTrainingPageH((string)$item['response']) : 'Účast nepotvrzena') ?></span></summary>
<div class="card-body"><div><?= rosterTrainingPageH((string)$item['cas_od'] !== '' ? substr((string)$item['cas_od'], 0, 5) . '–' . substr((string)$item['cas_do'], 0, 5) : 'Čas neuveden') ?></div><div><strong>Skupina / soupiska:</strong> <?= rosterTrainingPageH($item['targets'] === [] ? 'Historický trénink bez vazby na soupisku' : implode(', ', $item['targets'])) ?></div><?php if ((string)$item['description'] !== ''): ?><p class="mt-2 mb-0"><?= nl2br(rosterTrainingPageH($item['description'])) ?></p><?php endif; ?></div></details>
<?php endforeach; ?></div><?php endif; ?>
<?php endif; ?></main></body></html>
