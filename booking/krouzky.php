<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=krouzky.php');
    exit;
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/club_event_registration.php';

function clubRegistrationH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accountId = (int)$_SESSION['verejny_uzivatel_id'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'register') {
                $result = clubEventRegisterParticipant(
                    $pdo,
                    (int)($_POST['event_id'] ?? 0),
                    $accountId,
                    (int)($_POST['sportovec_id'] ?? 0)
                );
                $_SESSION['flash_club_registration'] = $result['created']
                    ? 'Dítě bylo na kroužek přihlášeno.'
                    : 'Dítě už je na tento kroužek přihlášeno.';
            } elseif ($action === 'cancel') {
                $result = clubEventCancelRegistration(
                    $pdo,
                    (int)($_POST['registration_id'] ?? 0),
                    $accountId,
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_club_registration'] = $result['changed']
                    ? 'Přihláška byla zrušena a místo uvolněno.'
                    : 'Přihláška už byla zrušena.';
            } else {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            header('Location: krouzky.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('booking/krouzky.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala. Nebyla uložena částečná změna.';
        } catch (InvalidArgumentException | ClubEventRegistrationException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_club_registration'] ?? '');
unset($_SESSION['flash_club_registration']);
$participants = accountPersonEligibleParticipants($pdo, $accountId);
$events = clubEventOpenFreeList($pdo);
$registrations = clubEventMyRegistrations($pdo, $accountId);
$activeByEventAndPerson = [];
foreach ($registrations as $registration) {
    if ($registration['status'] === 'confirmed') {
        $activeByEventAndPerson[(int)$registration['event_id']][(int)$registration['sportovec_id']] = true;
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kroužky — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm"><div class="container">
    <a class="navbar-brand fw-bold" href="kalendar.php"><i class="bi bi-bicycle me-2 text-primary"></i>Rezervace Kovopraha</a>
    <div class="d-flex gap-2"><a href="kalendar.php" class="btn btn-outline-primary btn-sm">Kalendář</a><a href="moje_osoby.php" class="btn btn-outline-secondary btn-sm">Moje osoby</a><a href="moje_rezervace.php" class="btn btn-outline-secondary btn-sm">Moje rezervace</a></div>
</div></nav>
<main class="container py-4" style="max-width:1000px">
    <h1 class="h4 mb-1"><i class="bi bi-people-fill me-2 text-primary"></i>Bezplatné kroužky</h1>
    <p class="text-muted">Přihlásit lze pouze dítě, které máte schválené v části Moje osoby.</p>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= clubRegistrationH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= clubRegistrationH($success) ?></div><?php endif; ?>
    <?php if ($participants === []): ?><div class="alert alert-info">Nejprve si nechte schválit dítě v části <a href="moje_osoby.php">Moje osoby</a>.</div><?php endif; ?>

    <div class="row g-3 mb-4">
    <?php foreach ($events as $event): ?>
        <div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between gap-2"><div><h2 class="h5 mb-1"><?= clubRegistrationH($event['name']) ?></h2><div class="small text-muted"><?= clubRegistrationH($event['audience_label']) ?></div></div><span class="badge text-bg-success align-self-start">zdarma</span></div>
            <?php if ($event['description_plain'] !== ''): ?><p class="mt-3 mb-2"><?= nl2br(clubRegistrationH($event['description_plain'])) ?></p><?php endif; ?>
            <div class="small mb-3"><strong>Volná místa:</strong> <?= (int)$event['remaining_capacity'] ?> z <?= (int)$event['effective_capacity'] ?></div>
            <?php foreach ($event['sessions'] as $session): ?><div class="border-top py-2 small"><i class="bi bi-calendar3 me-1"></i><?= clubRegistrationH($session['starts_at']) ?>–<?= clubRegistrationH($session['ends_at']) ?><br><span class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= clubRegistrationH($session['location']) ?></span></div><?php endforeach; ?>
            <?php if ($participants !== []): ?><form method="post" class="row g-2 mt-2">
                <?= csrf_field() ?><input type="hidden" name="action" value="register"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                <div class="col-8"><label class="visually-hidden" for="person-<?= (int)$event['id'] ?>">Dítě</label><select class="form-select" id="person-<?= (int)$event['id'] ?>" name="sportovec_id" required><option value="">Vyberte dítě</option><?php foreach ($participants as $person): ?><option value="<?= (int)$person['sportovec_id'] ?>" <?= isset($activeByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]) ? 'disabled' : '' ?>><?= clubRegistrationH($person['prijmeni'] . ' ' . $person['jmeno']) ?><?= isset($activeByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]) ? ' — již přihlášeno' : '' ?></option><?php endforeach; ?></select></div>
                <div class="col-4 d-grid"><button class="btn btn-primary" <?= (int)$event['remaining_capacity'] < 1 ? 'disabled' : '' ?>>Přihlásit</button></div>
            </form><?php endif; ?>
        </div></section></div>
    <?php endforeach; ?>
    <?php if ($events === []): ?><div class="col-12"><div class="alert alert-secondary">Momentálně není otevřen žádný bezplatný kroužek.</div></div><?php endif; ?>
    </div>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Moje přihlášky na kroužky</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Kroužek</th><th>Dítě</th><th>Stav</th><th></th></tr></thead><tbody>
    <?php foreach ($registrations as $registration): ?><tr><td><strong><?= clubRegistrationH($registration['event_name']) ?></strong><div class="small text-muted"><?= clubRegistrationH($registration['registered_at']) ?></div></td><td><?= clubRegistrationH($registration['prijmeni'] . ' ' . $registration['jmeno']) ?></td><td><span class="badge <?= $registration['status'] === 'confirmed' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $registration['status'] === 'confirmed' ? 'přihlášeno' : 'zrušeno' ?></span></td><td><?php if ($registration['status'] === 'confirmed'): ?><form method="post" class="d-flex gap-2 justify-content-end"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="registration_id" value="<?= (int)$registration['id'] ?>"><input class="form-control form-control-sm" style="max-width:220px" name="note" maxlength="1000" required placeholder="Důvod zrušení"><button class="btn btn-sm btn-outline-danger">Zrušit</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if ($registrations === []): ?><tr><td colspan="4" class="text-center text-muted py-3">Zatím nemáte žádnou přihlášku.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
