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
require_once dirname(__DIR__) . '/includes/shop_checkout.php';

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
                    (int)($_POST['sportovec_id'] ?? 0),
                    (string)($_POST['consent_version'] ?? ''),
                    ($_POST['consented'] ?? '') === '1'
                );
                $_SESSION['flash_club_registration'] = $result['status'] === 'waitlisted'
                    ? ($result['created'] ? 'Kapacita je plná. Dítě bylo zařazeno na čekací listinu.' : 'Dítě už je na čekací listině.')
                    : ($result['created'] ? 'Dítě bylo na kroužek přihlášeno.' : 'Dítě už je na tento kroužek přihlášeno.');
            } elseif ($action === 'add_paid') {
                $result=clubEventShopAddToCart($pdo,$accountId,(int)($_POST['event_id']??0),(int)($_POST['sportovec_id']??0),(int)($_POST['variant_id']??0),(string)($_POST['consent_version']??''),($_POST['consented']??'')==='1');
                $_SESSION['flash_club_registration']=$result['created']?'Placená událost byla přidána do košíku. Dokončete objednávku a platbu.':'Událost už v košíku je.';
            } elseif ($action === 'cancel') {
                $result = clubEventCancelRegistration(
                    $pdo,
                    (int)($_POST['registration_id'] ?? 0),
                    $accountId,
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_club_registration'] = $result['changed']
                    ? ($result['promoted_registration_id'] !== null
                        ? 'Přihláška byla zrušena a místo automaticky získala první oprávněná osoba z čekací listiny.'
                        : 'Přihláška nebo čekání byly zrušeny.')
                    : 'Přihláška už byla zrušena.';
            } else {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            header('Location: krouzky.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('booking/krouzky.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala. Nebyla uložena částečná změna.';
        } catch (InvalidArgumentException | ClubEventRegistrationException | ClubEventShopException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_club_registration'] ?? '');
unset($_SESSION['flash_club_registration']);
$participants = accountPersonEligibleParticipants($pdo, $accountId);
$events = clubEventOpenFreeList($pdo);
$paidEvents = clubEventOpenPaidList($pdo);
$registrations = clubEventMyRegistrations($pdo, $accountId);
$activeByEventAndPerson = [];
$eligibleByEventAndPerson = [];
foreach ($events as &$event) {
    $event['roster_targets'] = clubEventRosterTargets($pdo, (int)$event['id']);
    foreach ($participants as $person) {
        if (clubEventRosterEligibility($pdo, (int)$event['id'], (int)$person['sportovec_id'])) {
            $eligibleByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']] = true;
        }
    }
}
unset($event);
foreach ($paidEvents as &$event) {
    $event['roster_targets']=clubEventRosterTargets($pdo,(int)$event['id']);
    foreach($participants as $person)if(clubEventRosterEligibility($pdo,(int)$event['id'],(int)$person['sportovec_id']))$eligibleByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]=true;
}
unset($event);
foreach ($registrations as $registration) {
    if (in_array($registration['status'], ['confirmed', 'waitlisted'], true)) {
        $activeByEventAndPerson[(int)$registration['event_id']][(int)$registration['sportovec_id']] = true;
    }
}
$orderLinkedRegistrations=[];
if(clubEventShopAvailable($pdo)){
    $linkedStatement=$pdo->prepare('SELECT order_id FROM club_event_order_items WHERE registration_id=?');
    foreach($registrations as $registration){$linkedStatement->execute([(int)$registration['id']]);$linkedOrderId=(int)$linkedStatement->fetchColumn();if($linkedOrderId>0)$orderLinkedRegistrations[(int)$registration['id']]=$linkedOrderId;}
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
    <div class="d-flex gap-2"><a href="eshop.php" class="btn btn-outline-success btn-sm">E-shop</a><a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Moje objednávky</a><a href="kalendar.php" class="btn btn-outline-primary btn-sm">Kalendář</a><a href="moje_osoby.php" class="btn btn-outline-secondary btn-sm">Moje osoby</a><a href="moje_rezervace.php" class="btn btn-outline-secondary btn-sm">Moje rezervace</a></div>
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
            <div class="small mb-3"><strong>Volná místa:</strong> <?= (int)$event['remaining_capacity'] ?> z <?= (int)$event['effective_capacity'] ?> · <strong>čeká:</strong> <?= (int)$event['waitlist_count'] ?></div>
            <?php if ($event['roster_targets'] !== []): ?><div class="alert alert-info small py-2">Určeno pro soupisky: <?= clubRegistrationH(implode(', ', array_column($event['roster_targets'], 'team_name'))) ?></div><?php endif; ?>
            <?php foreach ($event['sessions'] as $session): ?><div class="border-top py-2 small"><i class="bi bi-calendar3 me-1"></i><?= clubRegistrationH($session['starts_at']) ?>–<?= clubRegistrationH($session['ends_at']) ?><br><span class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= clubRegistrationH($session['location']) ?></span></div><?php endforeach; ?>
            <?php if (!empty($eligibleByEventAndPerson[(int)$event['id']])): ?><form method="post" class="row g-2 mt-2">
                <?= csrf_field() ?><input type="hidden" name="action" value="register"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="consent_version" value="<?= clubRegistrationH($event['terms_version']) ?>">
                <div class="col-8"><label class="visually-hidden" for="person-<?= (int)$event['id'] ?>">Dítě</label><select class="form-select" id="person-<?= (int)$event['id'] ?>" name="sportovec_id" required><option value="">Vyberte dítě</option><?php foreach ($participants as $person):if(!isset($eligibleByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]))continue; ?><option value="<?= (int)$person['sportovec_id'] ?>" <?= isset($activeByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]) ? 'disabled' : '' ?>><?= clubRegistrationH($person['prijmeni'] . ' ' . $person['jmeno']) ?><?= isset($activeByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]) ? ' — již přihlášeno' : '' ?></option><?php endforeach; ?></select></div>
                <div class="col-4 d-grid"><button class="btn btn-primary"><?= (int)$event['remaining_capacity'] > 0 ? 'Přihlásit' : 'Zařadit do čekací listiny' ?></button></div>
                <div class="col-12"><div class="border rounded bg-light p-2 small"><strong>Souhlas <?= clubRegistrationH($event['terms_version']) ?></strong><br><?= nl2br(clubRegistrationH($event['consent_text_plain'])) ?><hr class="my-2"><strong>Bezplatné storno do <?= clubRegistrationH($event['cancellation_deadline_at']) ?></strong><br><?= nl2br(clubRegistrationH($event['cancellation_policy_plain'])) ?></div></div>
                <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="consented" value="1" id="consent-<?= (int)$event['id'] ?>" required><label class="form-check-label" for="consent-<?= (int)$event['id'] ?>">Potvrzuji souhlas a storno podmínky pro vybrané dítě.</label></div>
            </form><?php elseif($event['roster_targets'] !== []): ?><div class="alert alert-secondary small mb-0">Žádná z vašich schválených osob není v cílové soupisce.</div><?php endif; ?>
        </div></section></div>
    <?php endforeach; ?>
    <?php if ($events === []): ?><div class="col-12"><div class="alert alert-secondary">Momentálně není otevřen žádný bezplatný kroužek.</div></div><?php endif; ?>
    </div>

    <h2 class="h5 mb-3">Placené klubové události</h2>
    <div class="row g-3 mb-4">
    <?php foreach($paidEvents as $event): ?>
        <div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><div><h3 class="h5 mb-1"><?=clubRegistrationH($event['name'])?></h3><div class="small text-muted"><?=clubRegistrationH($event['audience_label'])?></div></div><span class="badge text-bg-primary align-self-start"><?=number_format((int)$event['amount_minor']/100,2,',',' ')?> Kč</span></div>
            <div class="small my-3"><strong>Volná místa:</strong> <?=(int)$event['remaining_capacity']?> z <?=(int)$event['effective_capacity']?><?php if($event['roster_targets']!==[]):?> · <strong>Soupisky:</strong> <?=clubRegistrationH(implode(', ',array_column($event['roster_targets'],'team_name')))?><?php endif;?></div>
            <?php foreach($event['sessions'] as $session):?><div class="border-top py-2 small"><?=clubRegistrationH($session['starts_at'].'–'.$session['ends_at'].' · '.$session['location'])?></div><?php endforeach;?>
            <?php if(!empty($eligibleByEventAndPerson[(int)$event['id']])&&(int)$event['remaining_capacity']>0):?><form method="post" class="row g-2 mt-2"><?=csrf_field()?><input type="hidden" name="action" value="add_paid"><input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input type="hidden" name="variant_id" value="<?=(int)$event['variant_id']?>"><input type="hidden" name="consent_version" value="<?=clubRegistrationH($event['terms_version'])?>"><div class="col-8"><select class="form-select" name="sportovec_id" required><option value="">Vyberte účastníka</option><?php foreach($participants as $person):if(!isset($eligibleByEventAndPerson[(int)$event['id']][(int)$person['sportovec_id']]))continue;?><option value="<?=(int)$person['sportovec_id']?>"><?=clubRegistrationH($person['prijmeni'].' '.$person['jmeno'])?></option><?php endforeach;?></select></div><div class="col-4 d-grid"><button class="btn btn-primary">Přidat do košíku</button></div><div class="col-12 small border rounded bg-light p-2"><strong>Souhlas <?=clubRegistrationH($event['terms_version'])?></strong><br><?=nl2br(clubRegistrationH($event['consent_text_plain']))?><hr class="my-2"><strong>Storno do <?=clubRegistrationH($event['cancellation_deadline_at'])?></strong><br><?=nl2br(clubRegistrationH($event['cancellation_policy_plain']))?></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="consented" value="1" id="paid-consent-<?=(int)$event['id']?>" required><label class="form-check-label" for="paid-consent-<?=(int)$event['id']?>">Potvrzuji podmínky pro vybraného účastníka.</label></div></form><?php elseif((int)$event['remaining_capacity']<1):?><div class="alert alert-warning mb-0">Kapacita je naplněna.</div><?php else:?><div class="alert alert-secondary mb-0">Žádná schválená osoba není v cílové soupisce.</div><?php endif;?>
        </div></section></div>
    <?php endforeach;?>
    <?php if($paidEvents===[]):?><div class="col-12"><div class="alert alert-secondary">Momentálně není otevřena žádná placená klubová událost.</div></div><?php endif;?>
    </div>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Moje přihlášky na kroužky</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Kroužek</th><th>Dítě</th><th>Stav</th><th></th></tr></thead><tbody>
    <?php foreach ($registrations as $registration):$isOrderLinked=isset($orderLinkedRegistrations[(int)$registration['id']]);$canCancel=!$isOrderLinked&&($registration['status']==='waitlisted'||($registration['status']==='confirmed'&&!empty($registration['cancellation_deadline_snapshot'])&&new DateTimeImmutable('now')<=new DateTimeImmutable((string)$registration['cancellation_deadline_snapshot'])));$statusLabel=$registration['status']==='payment_pending'?'čeká na platbu':($registration['status']==='confirmed'?'přihlášeno':($registration['status']==='waitlisted'?'čekací listina #'.(int)$registration['waitlist_position']:'zrušeno'));$statusColor=$registration['status']==='confirmed'?'success':(in_array($registration['status'],['waitlisted','payment_pending'],true)?'warning':'secondary');?><tr><td><strong><?= clubRegistrationH($registration['event_name']) ?></strong><div class="small text-muted"><?= clubRegistrationH($registration['registered_at']) ?><?=!empty($registration['promoted_at'])?' · povýšeno '.clubRegistrationH($registration['promoted_at']):''?></div><div class="small text-muted">Souhlas <?=clubRegistrationH($registration['consent_version_snapshot']??'')?> · storno do <?=clubRegistrationH($registration['cancellation_deadline_snapshot']??'')?></div></td><td><?= clubRegistrationH($registration['prijmeni'] . ' ' . $registration['jmeno']) ?></td><td><span class="badge text-bg-<?=$statusColor?>"><?=clubRegistrationH($statusLabel)?></span></td><td><?php if ($canCancel): ?><form method="post" class="d-flex gap-2 justify-content-end"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="registration_id" value="<?= (int)$registration['id'] ?>"><input class="form-control form-control-sm" style="max-width:220px" name="note" maxlength="1000" required placeholder="Důvod zrušení"><button class="btn btn-sm btn-outline-danger"><?= $registration['status']==='waitlisted'?'Opustit čekací listinu':'Zrušit' ?></button></form><?php elseif($isOrderLinked):?><a class="btn btn-sm btn-outline-secondary" href="moje_objednavky.php">Storno přes objednávku</a><?php elseif($registration['status']==='confirmed'):?><span class="small text-muted">Bezplatné storno skončilo</span><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if ($registrations === []): ?><tr><td colspan="4" class="text-center text-muted py-3">Zatím nemáte žádnou přihlášku.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
