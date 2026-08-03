<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=moje_osoby.php');
    exit;
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/account_person_claim.php';

function claimPageH(mixed $value): string
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
            if ($action === 'submit') {
                $result = accountPersonClaimSubmit(
                    $pdo,
                    $accountId,
                    (string)($_POST['requested_role'] ?? ''),
                    (string)($_POST['jmeno'] ?? ''),
                    (string)($_POST['prijmeni'] ?? ''),
                    (string)($_POST['narozeni'] ?? ''),
                    (string)($_POST['message'] ?? '')
                );
                $_SESSION['flash_claim_success'] = $result['created']
                    ? 'Žádost byla odeslána ke kontrole administrátorovi.'
                    : 'Stejná žádost již čeká na vyřízení.';
            } elseif ($action === 'cancel') {
                $result = accountPersonClaimCancel($pdo, (int)($_POST['request_id'] ?? 0), $accountId);
                $_SESSION['flash_claim_success'] = $result['changed']
                    ? 'Žádost byla zrušena.'
                    : 'Žádost už byla zrušena.';
            } else {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            header('Location: moje_osoby.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('booking/moje_osoby.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala. Nebyla uložena částečná změna.';
        } catch (InvalidArgumentException | AccountPersonClaimException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_claim_success'] ?? '');
unset($_SESSION['flash_claim_success']);
$claims = accountPersonClaimListForAccount($pdo, $accountId);
$participants = accountPersonEligibleParticipants($pdo, $accountId);
$statusLabels = [
    'pending' => ['warning', 'Čeká na kontrolu'],
    'approved' => ['success', 'Schváleno'],
    'rejected' => ['danger', 'Zamítnuto'],
    'cancelled' => ['secondary', 'Zrušeno'],
];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje osoby — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm"><div class="container">
    <a class="navbar-brand fw-bold" href="kalendar.php"><i class="bi bi-bicycle me-2 text-primary"></i>Rezervace Kovopraha</a>
    <div class="d-flex gap-2 flex-wrap"><a href="sportovni_prehled.php" class="btn btn-outline-primary btn-sm">Sportovní přehled</a><a href="eshop.php" class="btn btn-outline-success btn-sm">E-shop</a><a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Moje objednávky</a><a href="kalendar.php" class="btn btn-outline-primary btn-sm">Kalendář</a><a href="krouzky.php" class="btn btn-outline-secondary btn-sm">Kroužky</a><a href="moje_rezervace.php" class="btn btn-outline-secondary btn-sm">Moje rezervace</a></div>
</div></nav>
<main class="container py-4" style="max-width:900px">
    <h1 class="h4 mb-1"><i class="bi bi-people me-2 text-primary"></i>Moje osoby</h1>
    <p class="text-muted">Děti a sportovci, které budete moci přihlašovat na kroužky, kurzy a akce.</p>
    <div class="alert alert-info">Kvůli ochraně osobních údajů se osoby nehledají ani nepřipojují automaticky. Zadejte údaje osoby a administrátor vazbu ověří.</div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= claimPageH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= claimPageH($success) ?></div><?php endif; ?>

    <section class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Schválené osoby</div><div class="card-body">
        <?php if ($participants === []): ?><div class="text-muted">Zatím nemáte žádnou schválenou osobu.</div><?php endif; ?>
        <?php foreach ($participants as $person): ?><div class="d-flex justify-content-between border-bottom py-2"><span><strong><?= claimPageH($person['prijmeni'] . ' ' . $person['jmeno']) ?></strong><span class="text-muted ms-2"><?= claimPageH($person['narozeni']) ?></span></span><span class="badge bg-success"><?= $person['relation_role'] === 'guardian' ? 'rodič / zástupce' : 'vlastní profil' ?></span></div><?php endforeach; ?>
    </div></section>

    <section class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Požádat o propojení osoby</div><div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="submit">
            <div class="col-md-4"><label class="form-label">Vztah</label><select class="form-select" name="requested_role" required><option value="guardian">Jsem rodič / zástupce</option><option value="self">Je to můj vlastní profil</option></select></div>
            <div class="col-md-4"><label class="form-label">Jméno osoby</label><input class="form-control" name="jmeno" maxlength="100" required></div>
            <div class="col-md-4"><label class="form-label">Příjmení osoby</label><input class="form-control" name="prijmeni" maxlength="100" required></div>
            <div class="col-md-4"><label class="form-label">Datum narození</label><input class="form-control" type="date" name="narozeni" max="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-8"><label class="form-label">Poznámka pro ověření <span class="text-muted">(nepovinná)</span></label><input class="form-control" name="message" maxlength="1000" placeholder="Např. kroužek nebo skupina, kam dítě chodí"></div>
            <div class="col-12"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Odeslat žádost</button></div>
        </form>
    </div></section>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Historie žádostí</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Osoba</th><th>Vztah</th><th>Stav</th><th>Rozhodnutí</th><th></th></tr></thead><tbody>
    <?php foreach ($claims as $claim): [$color, $label] = $statusLabels[$claim['status']] ?? ['secondary', $claim['status']]; ?><tr><td><?= claimPageH($claim['claimed_prijmeni'] . ' ' . $claim['claimed_jmeno']) ?><div class="small text-muted"><?= claimPageH($claim['claimed_narozeni']) ?></div></td><td><?= $claim['requested_role'] === 'guardian' ? 'rodič / zástupce' : 'vlastní profil' ?></td><td><span class="badge bg-<?= $color ?>"><?= claimPageH($label) ?></span><div class="small text-muted"><?= claimPageH($claim['created_at']) ?></div></td><td class="small"><?= claimPageH($claim['decision_note'] ?? '') ?><?php if ($claim['status'] === 'approved' && $claim['matched_sportovec_id']): ?><div class="text-success">Propojeno: <?= claimPageH($claim['matched_prijmeni'] . ' ' . $claim['matched_jmeno']) ?></div><?php endif; ?></td><td><?php if ($claim['status'] === 'pending'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>"><button class="btn btn-sm btn-outline-danger">Zrušit</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if ($claims === []): ?><tr><td colspan="5" class="text-center text-muted py-3">Zatím jste žádnou žádost neposlali.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
