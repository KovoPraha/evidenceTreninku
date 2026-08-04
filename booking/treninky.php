<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/public_training_schedule.php';

function publicTrainingH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$month = (string)($_GET['mesic'] ?? date('Y-m'));
if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) $month = date('Y-m');
$start = new DateTimeImmutable($month . '-01');
$end = $start->modify('last day of this month');
$trainings = publicTrainingSchedule($pdo, $start->format('Y-m-d'), $end->format('Y-m-d'));
$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$loggedIn = isset($_SESSION['verejny_uzivatel_id']);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Veřejný rozvrh tréninků</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-4" style="max-width:960px">
<nav class="d-flex flex-wrap gap-2 align-items-center mb-4" aria-label="Veřejný portál"><a class="btn btn-outline-secondary btn-sm" href="../index.php">Domů</a><a class="btn btn-outline-secondary btn-sm" href="eshop.php">E-shop</a><a class="btn btn-outline-secondary btn-sm" href="krouzky.php">Kroužky a události</a><a class="btn btn-outline-secondary btn-sm" href="velodrom.php">Velodrom</a><span class="ms-auto"><?php if ($loggedIn): ?><a class="btn btn-primary btn-sm" href="sportovni_prehled.php">Můj sportovní přehled</a><?php else: ?><a class="btn btn-primary btn-sm" href="prihlaseni.php?redirect=treninky.php">Přihlásit se</a><?php endif; ?></span></nav>
<div class="mb-3"><h1 class="h3 mb-1">Rozvrh tréninků</h1><p class="text-muted mb-0">Veřejné termíny klubu bez údajů o sportovcích a docházce.</p></div>
<div class="d-flex justify-content-between align-items-center bg-white border rounded p-2 mb-3"><a class="btn btn-outline-secondary btn-sm" href="?mesic=<?=publicTrainingH($previous)?>">Předchozí</a><strong><?=$start->format('m / Y')?></strong><a class="btn btn-outline-secondary btn-sm" href="?mesic=<?=publicTrainingH($next)?>">Další</a></div>
<?php if ($trainings === []): ?><div class="alert alert-light border">V tomto měsíci nejsou zveřejněné žádné tréninky.</div><?php else: ?><div class="vstack gap-2"><?php foreach ($trainings as $training): ?><article class="card border-0 shadow-sm"><div class="card-body d-flex flex-column flex-md-row justify-content-between gap-2"><div><div class="small text-muted"><?=publicTrainingH((string)$training['datum'])?><?= $training['cas_od'] ? ' · ' . publicTrainingH(substr((string)$training['cas_od'],0,5)) : '' ?><?= $training['cas_do'] ? '–' . publicTrainingH(substr((string)$training['cas_do'],0,5)) : '' ?></div><h2 class="h5 mb-1"><?=publicTrainingH($training['nazev'])?></h2><div class="text-muted"><?=publicTrainingH($training['skupina'] ?: 'Klubový trénink')?></div></div><div class="text-md-end"><?php if ($training['sportoviste']): ?><span class="badge text-bg-light border"><?=publicTrainingH($training['sportoviste'])?></span><?php endif; ?><?php if ($training['kategorie']): ?><span class="badge text-bg-primary"><?=publicTrainingH($training['kategorie'])?></span><?php endif; ?></div></div></article><?php endforeach; ?></div><?php endif; ?>
</main></body></html>
