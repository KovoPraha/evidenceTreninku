<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/account_person_role.php';
require_once __DIR__ . '/includes/account_person_claim.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function identityAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'approve') {
                $result = accountPersonRoleApprove(
                    $pdo,
                    (int)($_POST['account_id'] ?? 0),
                    (int)($_POST['sportovec_id'] ?? 0),
                    (string)($_POST['relation_role'] ?? ''),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = $result['created']
                    ? 'Vazba účtu a osoby byla schválena.'
                    : 'Vazba je již schválená nebo byla znovu aktivována.';
            } elseif ($action === 'revoke') {
                $result = accountPersonRoleRevoke(
                    $pdo,
                    (int)($_POST['relation_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = $result['changed']
                    ? 'Vazba byla zrušena.'
                    : 'Vazba už byla zrušena.';
            } elseif ($action === 'approve_claim') {
                accountPersonClaimApprove(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)($_POST['sportovec_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = 'Žádost byla schválena a vazba je aktivní.';
            } elseif ($action === 'reject_claim') {
                accountPersonClaimReject(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = 'Žádost byla zamítnuta.';
            } else {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            header('Location: eshop_identity_admin.php');
            exit;
        } catch (PDOException $exception) {
            error_log('eshop_identity_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala. Nebyla uložena částečná změna.';
        } catch (InvalidArgumentException | AccountPersonRoleException | AccountPersonClaimException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$accounts = $pdo->query(
    'SELECT id, jmeno, prijmeni, email, email_overeno, aktivni '
    . 'FROM verejni_uzivatele ORDER BY prijmeni, jmeno, email, id'
)->fetchAll(PDO::FETCH_ASSOC);
$people = $pdo->query(
    'SELECT id, jmeno, prijmeni, narozeni, stav_clenstvi '
    . 'FROM sportovci ORDER BY prijmeni, jmeno, narozeni, id LIMIT 1000'
)->fetchAll(PDO::FETCH_ASSOC);
$relations = accountPersonRoleList($pdo);
$events = accountPersonRoleEvents($pdo);
$claims = accountPersonClaimListForAdmin($pdo);
$pendingClaims = array_values(array_filter($claims, static fn (array $claim): bool => $claim['status'] === 'pending'));
$closedClaims = array_values(array_filter($claims, static fn (array $claim): bool => $claim['status'] !== 'pending'));
$activeRelations = count(array_filter(
    $relations,
    static fn (array $relation): bool => $relation['status'] === 'approved' && $relation['valid_to'] === null
));
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Účty, rodiče a sportovci</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container py-4" style="max-width:1300px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div><h1 class="h4 mb-0"><i class="bi bi-people me-2 text-primary"></i>Účty, rodiče a sportovci</h1><div class="text-muted small">Schválené osoby, které účet smí spravovat nebo přihlašovat.</div></div>
        <a href="eshop_admin.php" class="btn btn-outline-secondary btn-sm">Administrace e-shopu</a>
    </div>

    <div class="alert alert-warning">
        Vazby se <strong>nikdy nevytvářejí automaticky podle e-mailu, jména ani KIS importu</strong>.
        V této etapě je může po ověření podkladů schválit pouze administrátor.
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= identityAdminH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= identityAdminH($success) ?></div><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Veřejné účty</div><div class="h3 mb-0"><?= count($accounts) ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Aktivní schválené vazby</div><div class="h3 mb-0"><?= $activeRelations ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Sportovci v evidenci</div><div class="h3 mb-0"><?= count($people) ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Žádosti čekající na ověření <span class="badge bg-warning text-dark ms-1"><?= count($pendingClaims) ?></span></div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Účet</th><th>Uvedená osoba</th><th>Vztah</th><th>Poznámka</th><th style="min-width:470px">Rozhodnutí administrátora</th></tr></thead><tbody>
        <?php foreach ($pendingClaims as $claim): ?><tr><td><?= identityAdminH($claim['account_prijmeni'] . ' ' . $claim['account_jmeno']) ?><div class="small text-muted"><?= identityAdminH($claim['account_email']) ?></div></td><td><strong><?= identityAdminH($claim['claimed_prijmeni'] . ' ' . $claim['claimed_jmeno']) ?></strong><div class="small text-muted">nar. <?= identityAdminH($claim['claimed_narozeni']) ?></div></td><td><code><?= identityAdminH($claim['requested_role']) ?></code></td><td><?= identityAdminH($claim['requester_message']) ?></td><td><form method="post" class="row g-1 mb-1"><?= csrf_field() ?><input type="hidden" name="action" value="approve_claim"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>"><div class="col-6"><select class="form-select form-select-sm" name="sportovec_id" required><option value="">Ručně ověřený sportovec</option><?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>"><?= identityAdminH($person['prijmeni'] . ' ' . $person['jmeno'] . ' – ' . $person['narozeni']) ?></option><?php endforeach; ?></select></div><div class="col-4"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Podklad ověření"></div><div class="col-2 d-grid"><button class="btn btn-sm btn-success">Schválit</button></div></form><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="reject_claim"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod zamítnutí"><button class="btn btn-sm btn-outline-danger">Zamítnout</button></form></td></tr><?php endforeach; ?>
        <?php if ($pendingClaims === []): ?><tr><td colspan="5" class="text-center text-muted py-4">Žádná žádost nyní nečeká na kontrolu.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <?php if ($closedClaims !== []): ?><div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Historie vyřízených žádostí</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Účet</th><th>Uvedená osoba</th><th>Stav</th><th>Propojená osoba</th><th>Rozhodnutí</th></tr></thead><tbody><?php foreach ($closedClaims as $claim): ?><tr><td><?= identityAdminH($claim['account_email']) ?></td><td><?= identityAdminH($claim['claimed_prijmeni'] . ' ' . $claim['claimed_jmeno']) ?><div class="small text-muted"><?= identityAdminH($claim['claimed_narozeni']) ?></div></td><td><span class="badge <?= $claim['status'] === 'approved' ? 'bg-success' : 'bg-secondary' ?>"><?= identityAdminH($claim['status']) ?></span></td><td><?= $claim['matched_sportovec_id'] ? identityAdminH($claim['matched_prijmeni'] . ' ' . $claim['matched_jmeno']) : '—' ?></td><td><?= identityAdminH($claim['decision_note']) ?><div class="small text-muted"><?= identityAdminH($claim['decider_name'] ?: $claim['decided_at']) ?></div></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Schválit vazbu</div>
        <div class="card-body">
            <?php if ($accounts === []): ?>
                <div class="text-muted">Zatím neexistuje žádný veřejný účet. Vazbu bude možné založit po registraci prvního uživatele.</div>
            <?php else: ?>
                <form method="post" class="row g-3 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <div class="col-lg-4"><label class="form-label req">Veřejný účet</label><select class="form-select" name="account_id" required><option value="">Vyberte účet</option><?php foreach ($accounts as $account): ?><option value="<?= (int)$account['id'] ?>"><?= identityAdminH($account['prijmeni'] . ' ' . $account['jmeno'] . ' – ' . $account['email']) ?><?= !$account['email_overeno'] ? ' (e-mail neověřen)' : '' ?><?= !$account['aktivni'] ? ' (neaktivní)' : '' ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-4"><label class="form-label req">Sportovec / dítě</label><select class="form-select" name="sportovec_id" required><option value="">Vyberte osobu</option><?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>"><?= identityAdminH($person['prijmeni'] . ' ' . $person['jmeno'] . ' – ' . $person['narozeni'] . ' – ' . $person['stav_clenstvi']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-2"><label class="form-label req">Vztah</label><select class="form-select" name="relation_role" required><option value="guardian">Rodič / zástupce</option><option value="self">Vlastní profil</option></select></div>
                    <div class="col-lg-8"><label class="form-label req">Podklad a důvod schválení</label><input class="form-control" name="note" maxlength="1000" required placeholder="Např. ověřeno administrátorem podle přihlášky a kontaktu rodiče"></div>
                    <div class="col-lg-2 d-grid"><button class="btn btn-primary"><i class="bi bi-person-check me-1"></i>Schválit vazbu</button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Vazby účtů a osob</div><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Účet</th><th>Osoba</th><th>Vztah</th><th>Stav</th><th>Platnost</th><th>Rozhodnutí</th><th style="min-width:320px">Akce</th></tr></thead><tbody>
    <?php foreach ($relations as $relation): ?><tr><td><?= identityAdminH($relation['account_prijmeni'] . ' ' . $relation['account_jmeno']) ?><div class="small text-muted"><?= identityAdminH($relation['account_email']) ?></div></td><td><?= identityAdminH($relation['person_prijmeni'] . ' ' . $relation['person_jmeno']) ?><div class="small text-muted"><?= identityAdminH($relation['narozeni']) ?></div></td><td><code><?= identityAdminH($relation['relation_role']) ?></code></td><td><span class="badge <?= $relation['status'] === 'approved' ? 'bg-success' : 'bg-secondary' ?>"><?= identityAdminH($relation['status']) ?></span></td><td class="small"><?= identityAdminH($relation['valid_from']) ?><br><?= $relation['valid_to'] ? 'do ' . identityAdminH($relation['valid_to']) : 'bez konce' ?></td><td class="small"><?= identityAdminH($relation['decision_note']) ?><div class="text-muted"><?= identityAdminH($relation['approver_name'] ?: $relation['creator_name']) ?></div></td><td><?php if ($relation['status'] !== 'revoked'): ?><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="relation_id" value="<?= (int)$relation['id'] ?>"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod zrušení"><button class="btn btn-sm btn-outline-danger">Zrušit</button></form><?php else: ?><span class="text-muted small">Vazba není aktivní</span><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if ($relations === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Zatím nejsou založené žádné vazby.</td></tr><?php endif; ?>
    </tbody></table></div></div>

    <?php if ($events !== []): ?><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Audit rozhodnutí</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Čas</th><th>Účet</th><th>Osoba</th><th>Akce</th><th>Změna</th><th>Kdo</th><th>Důvod</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= identityAdminH($event['created_at']) ?></td><td><?= identityAdminH($event['account_email']) ?></td><td><?= identityAdminH($event['person_prijmeni'] . ' ' . $event['person_jmeno']) ?></td><td><?= identityAdminH($event['action']) ?></td><td><?= identityAdminH(($event['from_status'] ?: 'nová') . ' → ' . $event['to_status']) ?></td><td><?= identityAdminH($event['actor_name'] ?: '#' . $event['actor_trainer_id']) ?></td><td><?= identityAdminH($event['note']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
</div>

</body>
</html>
