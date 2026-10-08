<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/public_training_schedule.php';
require_once dirname(__DIR__) . '/includes/calendar_ui.php';

function publicTrainingH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$start = calendarUiMonth((string)($_GET['mesic'] ?? date('Y-m')));
$end = $start->modify('last day of this month');
$trainings = publicTrainingSchedule($pdo, $start->format('Y-m-d'), $end->format('Y-m-d'));
$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$view = (string)($_GET['zobrazeni'] ?? 'calendar');
if (!in_array($view, ['calendar', 'list'], true)) $view = 'calendar';
$calendarItems = [];
foreach ($trainings as $training) {
    $time = $training['cas_od'] ? uiFormatTime((string)$training['cas_od']) : '';
    if ($training['cas_do']) $time .= ($time !== '' ? '–' : '') . uiFormatTime((string)$training['cas_do']);
    $meta = array_values(array_filter([(string)($training['skupina'] ?: 'Klubový trénink'), (string)$training['sportoviste'], (string)$training['kategorie']]));
    $calendarItems[] = [
        'date' => (string)$training['datum'],
        'title' => (string)$training['nazev'],
        'time' => $time,
        'meta' => implode(' · ', $meta),
        'popover' => implode(' · ', array_filter([$time, ...$meta])),
        'tone' => 'primary',
    ];
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Veřejný rozvrh tréninků</title><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav('training'); ?><main class="container py-4">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h3 mb-1">Rozvrh tréninků</h1><p class="text-muted mb-0">Termíny, časy a místa tréninků přehledně v kalendáři. Údaje o sportovcích a docházce nejsou veřejné.</p></div><a class="btn btn-outline-primary btn-sm" href="verejny_kalendar.php"><i class="bi bi-calendar-plus me-1"></i>Stáhnout kalendář (.ics)</a></div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 bg-white border rounded p-2 mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="?mesic=<?=publicTrainingH($previous)?>&amp;zobrazeni=<?=publicTrainingH($view)?>"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Předchozí měsíc</span></a>
  <strong class="text-capitalize"><?=publicTrainingH(uiFormatMonthName($start))?></strong>
  <div class="d-flex gap-2"><div class="btn-group btn-group-sm" role="group" aria-label="Způsob zobrazení"><a class="btn <?=$view==='calendar'?'btn-primary':'btn-outline-primary'?>" href="?mesic=<?=publicTrainingH($start->format('Y-m'))?>&amp;zobrazeni=calendar"><i class="bi bi-calendar3 me-1"></i>Kalendář</a><a class="btn <?=$view==='list'?'btn-primary':'btn-outline-primary'?>" href="?mesic=<?=publicTrainingH($start->format('Y-m'))?>&amp;zobrazeni=list"><i class="bi bi-list-ul me-1"></i>Seznam</a></div><a class="btn btn-outline-secondary btn-sm" href="?mesic=<?=publicTrainingH($next)?>&amp;zobrazeni=<?=publicTrainingH($view)?>"><span class="visually-hidden">Další měsíc</span><i class="bi bi-chevron-right" aria-hidden="true"></i></a></div>
</div>
<?php if ($view === 'calendar'): ?>
  <?=calendarUiRender($start, $calendarItems, 'Bez zveřejněného tréninku.')?>
  <p class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Na trénink najeďte myší nebo jej označte klávesou Tab pro zobrazení místa a skupiny.</p>
<?php endif; ?>
<?php if ($view === 'list' || $trainings === []): ?>
  <section class="mt-4" aria-labelledby="training-list-title"><h2 class="h5" id="training-list-title"><?=$trainings===[]?'V tomto měsíci nejsou tréninky':'Tréninky v tomto měsíci'?></h2>
  <?php if ($trainings === []): ?><div class="alert alert-light border"><p class="mb-2">V období <?=publicTrainingH(uiFormatDate($start->format('Y-m-d')))?>–<?=publicTrainingH(uiFormatDate($end->format('Y-m-d')))?> nejsou zveřejněné žádné tréninky.</p><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="?mesic=<?=publicTrainingH($next)?>">Zobrazit další měsíc</a><a class="btn btn-outline-secondary" href="kalendar.php">Individuální lekce</a></div></div>
  <?php else: ?><div class="vstack gap-2"><?php foreach ($trainings as $training): ?><article class="card border-0 shadow-sm"><div class="card-body d-flex flex-column flex-md-row justify-content-between gap-2"><div><div class="small text-muted"><?=publicTrainingH(uiFormatDayName(new DateTimeImmutable((string)$training['datum'])))?> <?=publicTrainingH(uiFormatDate((string)$training['datum']))?><?= $training['cas_od'] ? ' · ' . publicTrainingH(uiFormatTime((string)$training['cas_od'])) : '' ?><?= $training['cas_do'] ? '–' . publicTrainingH(uiFormatTime((string)$training['cas_do'])) : '' ?></div><h3 class="h5 mb-1"><?=publicTrainingH($training['nazev'])?></h3><div class="text-muted"><?=publicTrainingH($training['skupina'] ?: 'Klubový trénink')?></div></div><div class="text-md-end"><?php if ($training['sportoviste']): ?><span class="badge text-bg-light border"><?=publicTrainingH($training['sportoviste'])?></span><?php endif; ?><?php if ($training['kategorie']): ?><span class="badge text-bg-primary"><?=publicTrainingH($training['kategorie'])?></span><?php endif; ?></div></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endif; ?>
</main><?php publicShellFooter(); ?></body></html>
