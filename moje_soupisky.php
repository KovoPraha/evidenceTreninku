<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/coach_roster_read.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('Databázové připojení není dostupné.');
}

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('Operace není povolena.');
}

function coachRosterHtml(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
$selectedId = filter_var($_GET['team_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($selectedId === false) {
    http_response_code(400);
    exit('Neplatná soupiska.');
}
$searchInput = $_GET['q'] ?? '';
if (!is_string($searchInput)) {
    http_response_code(400);
    exit('Neplatný hledaný výraz.');
}
$search = mb_substr(trim($searchInput), 0, 100);
try {
    $teams = coachRosterAssignedTeams($pdo, $today);
    $selected = $selectedId > 0 ? coachRosterTeam($pdo, $selectedId, $today) : null;
    $members = $selected !== null ? coachRosterMembers($pdo, $selectedId, $today, $search) : [];
} catch (DomainException $error) {
    http_response_code(403);
    exit('Soupiska není ve vašem aktuálním pracovním rozsahu.');
}

$selectedSummary = null;
foreach ($teams as $team) {
    if ((int)$team['id'] === $selectedId) {
        $selectedSummary = $team;
        break;
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje skupiny a soupisky</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1100px">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Moje skupiny a soupisky</h1>
            <p class="text-muted mb-0">Aktuálně svěřené soupisky a děti k <?= coachRosterHtml($today) ?>.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="moje_skupiny.php">Historie tréninků skupin</a>
    </div>

    <?php if ($teams === []): ?>
        <div class="alert alert-info">Nemáte aktuálně přidělenou žádnou skupinu ani soupisku. Přidělení spravuje vedoucí sportu.</div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($teams as $team): ?>
                <div class="col-12 col-md-6">
                    <section class="card h-100 border-0 shadow-sm<?= (int)$team['id'] === $selectedId ? ' border border-primary' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h2 class="h5 mb-1"><?= coachRosterHtml($team['name']) ?></h2>
                                <span class="badge text-bg-light border"><?= ($team['team_kind'] ?? 'roster') === 'group' ? 'Skupina' : 'Soupiska' ?></span>
                            </div>
                            <div class="small text-muted mb-2"><?= coachRosterHtml($team['season_name'] ?? 'Bez období') ?></div>
                            <div><strong><?= (int)$team['member_count'] ?></strong> aktivních dětí<?php if (($team['team_kind'] ?? '') === 'group'): ?> přímo ve skupině<?php endif; ?></div>
                            <?php if ($team['offer']['capacity'] !== null): ?>
                                <div class="small">Kapacita: <?= (int)$team['offer']['capacity'] ?> ·
                                    Volná místa: <?= $team['offer']['available'] === null
                                        ? '—' : (int)$team['offer']['available'] ?></div>
                            <?php endif; ?>
                            <?php foreach ($team['offer']['place_time'] as $slot): ?>
                                <div class="small text-muted"><?= coachRosterHtml($slot) ?></div>
                            <?php endforeach; ?>
                            <a class="btn btn-outline-primary btn-sm mt-3" href="moje_soupisky.php?team_id=<?= (int)$team['id'] ?>#clenove">Zobrazit děti</a>
                        </div>
                    </section>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($selected !== null): ?>
        <section id="clenove" class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h4 mb-1"><?= coachRosterHtml($selected['name']) ?></h2>
                <p class="text-muted small mb-3"><?= coachRosterHtml($selected['season_name'] ?? 'Bez období') ?> · <?= (int)($selectedSummary['member_count'] ?? 0) ?> aktivních dětí</p>
                <form method="get" class="row g-2 mb-3" role="search">
                    <input type="hidden" name="team_id" value="<?= (int)$selectedId ?>">
                    <div class="col-12 col-sm"><label class="form-label" for="child-search">Najít dítě v této soupisce</label>
                        <input class="form-control" id="child-search" name="q" value="<?= coachRosterHtml($search) ?>" maxlength="100" placeholder="Jméno nebo příjmení">
                    </div>
                    <div class="col-12 col-sm-auto align-self-end"><button class="btn btn-primary w-100">Hledat</button></div>
                </form>
                <?php if ($members === []): ?>
                    <p class="text-muted mb-0"><?= $search === '' ? 'V této soupisce nyní nejsou aktivní děti.' : 'Žádné aktivní dítě neodpovídá hledání.' ?></p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($members as $member): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 px-0">
                                <span><?= coachRosterHtml($member['prijmeni'] . ' ' . $member['jmeno']) ?></span>
                                <span class="badge text-bg-success">Aktivní</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="small text-muted mt-3 mb-0">Čekající žádosti a ukončená členství nejsou v trenérském přehledu jmen zobrazována. Docházku otevřete v samostatné trenérské agendě.</p>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
