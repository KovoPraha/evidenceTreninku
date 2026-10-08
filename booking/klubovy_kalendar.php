<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/club_calendar.php';
require_once dirname(__DIR__) . '/includes/calendar_ui.php';

function memberCalendarH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accountId = isset($_SESSION['verejny_uzivatel_id']) ? (int)$_SESSION['verejny_uzivatel_id'] : null;
$view = (string)($_GET['zobrazeni'] ?? 'calendar');
if (!in_array($view, ['calendar', 'register'], true)) $view = 'calendar';
$month = calendarUiMonth((string)($_GET['mesic'] ?? date('Y-m')));
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $returnUrl = 'klubovy_kalendar.php?zobrazeni=register#akce-' . $eventId;
    if ($accountId === null) {
        header('Location: prihlaseni.php?redirect=' . rawurlencode($returnUrl), true, 303);
        exit;
    }
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $result = clubCalendarFamilyRegister($pdo, $eventId, $accountId, (int)($_POST['sportovec_id'] ?? 0), (string)($_POST['consented'] ?? '') === '1');
            $_SESSION['member_calendar_flash'] = $result['status'] === 'waitlisted'
                ? 'Kapacita je naplněná. Účastník byl zařazen jako náhradník.'
                : 'Účast byla potvrzena.';
            header('Location: ' . $returnUrl, true, 303);
            exit;
        } catch (InvalidArgumentException | ClubCalendarException $exception) {
            $errors[] = $exception->getMessage();
            $view = 'register';
        } catch (Throwable $exception) {
            error_log('booking/klubovy_kalendar.php: ' . $exception->getMessage());
            $errors[] = 'Přihlášení se nyní nepodařilo uložit. Nevznikl částečný zápis.';
            $view = 'register';
        }
    }
}

$success = (string)($_SESSION['member_calendar_flash'] ?? '');
unset($_SESSION['member_calendar_flash']);
$today = new DateTimeImmutable('today');
$from = $view === 'calendar' ? $month : $today;
$to = $view === 'calendar' ? $month->modify('last day of this month') : $today->modify('+1 year');
$events = clubCalendarEvents($pdo, $from->format('Y-m-d'), $to->format('Y-m-d'), $accountId, false);
$participants = $accountId === null ? [] : accountPersonEligibleParticipants($pdo, $accountId);
$eligible = [];
foreach ($events as $event) {
    foreach ($participants as $person) {
        if (clubEventRosterEligibility($pdo, (int)$event['id'], (int)$person['sportovec_id'])) {
            $eligible[(int)$event['id']][] = $person;
        }
    }
}
$active = [];
if ($accountId !== null) {
    $statement = $pdo->prepare("SELECT event_id,sportovec_id,status FROM club_event_planned_participants WHERE account_id=? AND status<>'cancelled'");
    $statement->execute([$accountId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $active[(int)$row['event_id']][(int)$row['sportovec_id']] = (string)$row['status'];
    }
}
$calendarItems = [];
foreach ($events as $event) {
    $planned = (string)$event['planning_status'] === 'planned';
    $time = uiFormatTime((string)$event['starts_at']) . '–' . uiFormatTime((string)$event['ends_at']);
    $meta = implode(' · ', array_filter([(string)$event['location'], clubCalendarKinds()[(string)$event['activity_kind']] ?? 'Akce']));
    $calendarItems[] = [
        'date' => substr((string)$event['starts_at'], 0, 10),
        'title' => (string)$event['name'],
        'time' => $time,
        'popover' => implode(' · ', array_filter([$time, $meta, $planned ? 'Předběžný plán' : 'Potvrzeno'])),
        'tone' => $planned ? 'warning' : 'success',
        'href' => '?zobrazeni=register#akce-' . (int)$event['id'],
    ];
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Akce a kalendář — Kovopraha</title><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav('calendar'); ?>
<main class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h1 class="h3 mb-1">Akce a klubový kalendář</h1><p class="text-muted mb-0">Závody, soustředění, školení a klubové schůze na jednom místě.</p></div><a class="btn btn-outline-primary" href="verejny_kalendar.php"><i class="bi bi-calendar-plus me-1"></i>Veřejný kalendář (.ics)</a></div>
  <nav class="nav nav-pills gap-2 mb-3" aria-label="Akce a kalendář"><a class="nav-link<?=$view==='calendar'?' active':''?>" href="?zobrazeni=calendar&amp;mesic=<?=memberCalendarH($month->format('Y-m'))?>"><i class="bi bi-calendar3 me-1"></i>Kalendář</a><a class="nav-link<?=$view==='register'?' active':''?>" href="?zobrazeni=register"><i class="bi bi-check2-square me-1"></i>K přihlášení</a></nav>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=memberCalendarH($error)?></div><?php endforeach; ?>
  <?php if ($success !== ''): ?><div class="alert alert-success"><?=memberCalendarH($success)?></div><?php endif; ?>
  <?php if ($accountId === null): ?><div class="alert alert-info">Bez přihlášení vidíte veřejné akce. <a href="prihlaseni.php?redirect=<?=rawurlencode('klubovy_kalendar.php?zobrazeni='.$view)?>">Přihlaste se</a> pro klubový a soupiskový plán.</div><?php endif; ?>

  <?php if ($view === 'calendar'): ?>
    <div class="d-flex justify-content-between align-items-center bg-white border rounded p-2 mb-3"><a class="btn btn-outline-secondary btn-sm" href="?zobrazeni=calendar&amp;mesic=<?=$month->modify('-1 month')->format('Y-m')?>"><i class="bi bi-chevron-left"></i><span class="visually-hidden">Předchozí měsíc</span></a><strong class="text-capitalize"><?=memberCalendarH(uiFormatMonthName($month))?></strong><a class="btn btn-outline-secondary btn-sm" href="?zobrazeni=calendar&amp;mesic=<?=$month->modify('+1 month')->format('Y-m')?>"><span class="visually-hidden">Další měsíc</span><i class="bi bi-chevron-right"></i></a></div>
    <?=calendarUiRender($month, $calendarItems, 'Bez klubové akce.')?>
    <?php if ($events === []): ?><div class="alert alert-light border mt-3">V tomto měsíci nejsou viditelné žádné klubové akce.</div><?php else: ?><p class="small text-muted mt-2">Kliknutím na akci přejdete na detail a přihlášení. Doplňující údaje se zobrazí také při najetí nebo zaměření klávesnicí.</p><?php endif; ?>
  <?php else: ?>
    <div class="row g-3">
    <?php foreach ($events as $event): $planned=(string)$event['planning_status']==='planned'; $canRegister=!$planned&&(string)$event['planning_status']==='confirmed'&&(string)$event['status']==='open'; ?>
      <div class="col-12" id="akce-<?=(int)$event['id']?>"><article class="card border-0 shadow-sm<?= $planned ? ' border-start border-warning border-5' : '' ?>"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5 mb-1"><?=memberCalendarH($event['name'])?></h2><div class="text-muted"><i class="bi bi-calendar3 me-1"></i><?=memberCalendarH(uiFormatDateTimeRange((string)$event['starts_at'], (string)$event['ends_at']))?><?=trim((string)$event['location'])!==''?' · '.memberCalendarH($event['location']):''?></div></div><div class="d-flex gap-2 align-items-start"><span class="badge text-bg-secondary"><?=memberCalendarH(clubCalendarKinds()[(string)$event['activity_kind']] ?? 'Akce')?></span><span class="badge <?=$planned?'text-bg-warning':'text-bg-success'?>"><?=memberCalendarH(clubCalendarPlanningStatuses()[(string)$event['planning_status']] ?? $event['planning_status'])?></span></div></div>
        <?php if ($planned): ?><div class="alert alert-warning py-2 mt-3 mb-2"><strong>Předběžný plán.</strong> Termín se může změnit a přihlašování zatím není otevřené.</div><?php endif; ?>
        <?php if ((string)$event['public_description_plain'] !== ''): ?><p class="mt-3 mb-2"><?=nl2br(memberCalendarH($event['public_description_plain']))?></p><?php endif; ?>
        <?php if ($event['teams'] !== []): ?><div class="small mb-2"><strong>Soupisky:</strong> <?=memberCalendarH(implode(', ', array_column($event['teams'], 'team_name')))?></div><?php endif; ?>
        <?php if ($event['people'] !== []): ?><div class="small mb-2"><strong>Zajišťují:</strong> <?=memberCalendarH(implode(', ', array_map(static fn(array $person): string => (string)($person['trainer_name'] ?: $person['external_name']), $event['people'])))?></div><?php endif; ?>
        <?php if ($event['links'] !== []): ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($event['links'] as $link): ?><a class="btn btn-outline-secondary btn-sm" href="<?=memberCalendarH($link['url'])?>" rel="noopener noreferrer" target="_blank"><?=memberCalendarH($link['label'])?></a><?php endforeach; ?></div><?php endif; ?>
        <div class="d-flex flex-wrap gap-3 small"><span><strong>Kapacita:</strong> <?=(int)$event['registration_count']?> / <?=(int)$event['capacity']?></span><?php if ((int)$event['participant_fee_minor'] > 0): ?><span><strong>Cena za účast:</strong> <?=memberCalendarH(number_format((int)$event['participant_fee_minor'] / 100, 2, ',', ' '))?> Kč</span><?php endif; ?></div>
        <?php if ($canRegister): ?>
          <?php if ($accountId === null): ?><a class="btn btn-primary btn-sm mt-3" href="prihlaseni.php?redirect=<?=rawurlencode('klubovy_kalendar.php?zobrazeni=register#akce-'.(int)$event['id'])?>">Přihlásit se k účasti</a>
          <?php elseif (($eligible[(int)$event['id']] ?? []) === []): ?><div class="alert alert-light border py-2 mt-3 mb-0">V tomto účtu není osoba oprávněná pro tuto akci.</div>
          <?php else: ?><form method="post" action="?zobrazeni=register#akce-<?=(int)$event['id']?>" class="row g-2 mt-2"><?=csrf_field()?><input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><div class="col-md-8"><label class="visually-hidden" for="event-person-<?=(int)$event['id']?>">Účastník</label><select id="event-person-<?=(int)$event['id']?>" class="form-select" name="sportovec_id" required><option value="">Vyberte účastníka</option><?php foreach ($eligible[(int)$event['id']] as $person): $state=$active[(int)$event['id']][(int)$person['sportovec_id']]??''; ?><option value="<?=(int)$person['sportovec_id']?>"<?=$state!==''?' disabled':''?>><?=memberCalendarH($person['prijmeni'].' '.$person['jmeno'].($state!==''?' — již evidováno':''))?></option><?php endforeach; ?></select></div><div class="col-md-4 d-grid"><button class="btn btn-primary">Potvrdit účast</button></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="consented" value="1" id="consent-<?=(int)$event['id']?>" required><label class="form-check-label small" for="consent-<?=(int)$event['id']?>">Souhlasím s účastí a případným vytvořením platebního předpisu v uvedené výši.</label></div></form><?php endif; ?>
        <?php elseif (!$planned): ?><div class="text-muted small mt-3">Přihlašování není otevřené.</div><?php endif; ?>
      </div></article></div>
    <?php endforeach; ?>
    <?php if ($events === []): ?><div class="col-12"><div class="alert alert-light border"><p class="mb-2">V příštím roce nejsou žádné klubové akce, které můžete vidět.</p><?php if ($accountId === null): ?><a class="btn btn-primary" href="prihlaseni.php?redirect=<?=rawurlencode('klubovy_kalendar.php?zobrazeni=register')?>">Přihlásit se pro klubové akce</a><?php endif; ?></div></div><?php endif; ?>
    </div>
  <?php endif; ?>
</main><?php publicShellFooter(); ?></body></html>
