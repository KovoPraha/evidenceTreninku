<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/account_person_role.php';
require_once __DIR__ . '/includes/account_person_claim.php';
require_once __DIR__ . '/includes/person_match.php';
require_once __DIR__ . '/includes/athlete_registration_admin.php';

if (!isset($_SESSION['trener_id']) || !staffActivePositionIs('registrar')) {
    header('Location: login.php');
    exit;
}
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

function identityAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array<string,mixed> */
function identityAdminClaim(PDO $pdo, int $requestId, bool $lock = false): array
{
    $sql = 'SELECT c.*,vu.email AS account_email FROM account_person_claim_requests c '
        . 'JOIN verejni_uzivatele vu ON vu.id=c.account_id WHERE c.id=?';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$requestId]);
    $claim = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$claim) {
        throw new AccountPersonClaimException('Žádost nebyla nalezena.');
    }
    return $claim;
}

$errors = [];
$claimPreviewId = 0;
$claimMatch = null;
$claimOverrideReason = '';
$registrationReview = null;
$assignmentContext = null;
$chargeContext = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $redirect = true;
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'review_charge') {
                $claimPreviewId = (int)($_POST['request_id'] ?? 0);
                $chargeContext = athleteRegistrationAdminChargeContext(
                    $pdo,
                    $claimPreviewId,
                    (int)($_POST['season_id'] ?? 0)
                );
                $redirect = false;
            } elseif ($action === 'create_registration_charge') {
                $charge = athleteRegistrationAdminCreateCharge(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)($_POST['season_id'] ?? 0),
                    (int)($_POST['payer_account_id'] ?? 0),
                    (string)($_POST['title'] ?? ''),
                    (string)($_POST['period_from'] ?? ''),
                    (string)($_POST['period_to'] ?? ''),
                    (string)($_POST['due_on'] ?? ''),
                    $_POST['amount_minor'] ?? null,
                    (string)($_POST['currency'] ?? ''),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['reason'] ?? ''),
                    isset($_POST['confirmed'])
                );
                $_SESSION['flash_success'] = $charge['created']
                    ? 'Členský předpis byl vystaven a auditován.'
                    : 'Stejný členský předpis už existoval; nevznikla duplicita.';
            } elseif ($action === 'review_assignment') {
                $claimPreviewId = (int)($_POST['request_id'] ?? 0);
                $assignmentContext = athleteRegistrationAdminAssignmentContext($pdo, $claimPreviewId);
                $redirect = false;
            } elseif ($action === 'assign_registration') {
                $assignment = athleteRegistrationAdminAssign(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)($_POST['group_id'] ?? 0),
                    (int)($_POST['subgroup_id'] ?? 0),
                    (int)($_POST['team_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['reason'] ?? '')
                );
                $_SESSION['flash_success'] = $assignment['changed']
                    ? 'Sportovec byl zařazen do skupiny, podskupiny a sezonní soupisky.'
                    : 'Vybrané zařazení už bylo aktivní; nevznikl duplicitní zápis.';
            } elseif ($action === 'review_registration') {
                $claimPreviewId = (int)($_POST['request_id'] ?? 0);
                $registrationReview = athleteRegistrationAdminReview(
                    $pdo,
                    $claimPreviewId,
                    null,
                    (string)($_SERVER['REMOTE_ADDR'] ?? '')
                );
                $claimMatch = $registrationReview['match'];
                personMatchV1Audit(
                    $pdo,
                    (int)$_SESSION['trener_id'],
                    'athlete_registration_review',
                    $claimMatch,
                    null,
                    '',
                    ['source'=>'eshop_identity_admin','request_id'=>$claimPreviewId]
                );
                $redirect = false;
            } elseif ($action === 'approve_registration_existing') {
                athleteRegistrationAdminApproveExisting(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)($_POST['sportovec_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = 'Registrace byla schválena a připojena k existující osobě.';
            } elseif ($action === 'create_registration_person') {
                athleteRegistrationAdminCreatePerson(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? ''),
                    (string)($_POST['create_confirmation'] ?? ''),
                    (string)($_POST['override_reason'] ?? '')
                );
                $_SESSION['flash_success'] = 'Nová osoba byla založena a registrační žádost schválena.';
            } elseif ($action === 'reject_registration') {
                athleteRegistrationAdminReject(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = 'Registrační žádost byla zamítnuta a citlivá data přešla do řízené retence.';
            } elseif ($action === 'approve') {
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
                $claim = identityAdminClaim($pdo, (int)($_POST['request_id'] ?? 0));
                if ((string)($claim['request_kind'] ?? 'person_link') !== 'person_link') {
                    throw new AccountPersonClaimException('Tato registrační žádost vyžaduje kontrolu všech podkladů.');
                }
                $match = personMatchV1($pdo, [
                    'jmeno' => $claim['claimed_jmeno'],
                    'prijmeni' => $claim['claimed_prijmeni'],
                    'narozeni' => $claim['claimed_narozeni'],
                ]);
                personMatchV1Audit(
                    $pdo,
                    (int)$_SESSION['trener_id'],
                    'claim_link',
                    $match,
                    null,
                    (string)($_POST['note'] ?? ''),
                    ['source'=>'eshop_identity_admin','request_id'=>(int)$claim['id'],'selected_person_id'=>(int)($_POST['sportovec_id'] ?? 0)]
                );
                accountPersonClaimApprove(
                    $pdo,
                    (int)($_POST['request_id'] ?? 0),
                    (int)($_POST['sportovec_id'] ?? 0),
                    (int)$_SESSION['trener_id'],
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_success'] = 'Žádost byla schválena a vazba je aktivní.';
            } elseif ($action === 'create_claim_person') {
                $requestId = (int)($_POST['request_id'] ?? 0);
                $note = trim((string)($_POST['note'] ?? ''));
                $confirmation = (string)($_POST['create_confirmation'] ?? '');
                $claimOverrideReason = trim((string)($_POST['override_reason'] ?? ''));
                if ($note === '') {
                    throw new InvalidArgumentException('Založení a schválení vyžaduje podklad ověření.');
                }
                $claim = identityAdminClaim($pdo, $requestId);
                if ((string)($claim['request_kind'] ?? 'person_link') !== 'person_link') {
                    throw new AccountPersonClaimException('Tato registrační žádost vyžaduje kontrolu všech podkladů.');
                }
                if ($claim['status'] !== 'pending') {
                    throw new AccountPersonClaimException('Vyřídit lze pouze čekající žádost.');
                }
                $input = [
                    'jmeno' => $claim['claimed_jmeno'],
                    'prijmeni' => $claim['claimed_prijmeni'],
                    'narozeni' => $claim['claimed_narozeni'],
                    'email' => '',
                ];
                $claimMatch = personMatchV1($pdo, $input);
                $claimPreviewId = $requestId;
                if ($claimMatch['level'] === PERSON_MATCH_EXACT && $confirmation !== 'exact_override') {
                    personMatchV1Audit($pdo, (int)$_SESSION['trener_id'], 'exact_discovery', $claimMatch, null, '', [
                        'source'=>'eshop_identity_admin','request_id'=>$requestId,
                    ]);
                    $errors[] = 'Nalezena přesná shoda. Připojte žádost k existující osobě, nebo zdůvodněte výjimku.';
                    $redirect = false;
                } elseif ($claimMatch['level'] === PERSON_MATCH_EXACT && mb_strlen($claimOverrideReason, 'UTF-8') < 10) {
                    $errors[] = 'Důvod výjimky musí mít alespoň 10 znaků.';
                    $redirect = false;
                } elseif ($claimMatch['level'] === PERSON_MATCH_SIMILARITY && $confirmation !== 'similarity') {
                    personMatchV1Audit($pdo, (int)$_SESSION['trener_id'], 'similarity_discovery', $claimMatch, null, '', [
                        'source'=>'eshop_identity_admin','request_id'=>$requestId,
                    ]);
                    $errors[] = 'Nalezeny podobné osoby. Zkontrolujte je a založení výslovně potvrďte.';
                    $redirect = false;
                } else {
                    $pdo->beginTransaction();
                    $lockedClaim = identityAdminClaim($pdo, $requestId, true);
                    if ($lockedClaim['status'] !== 'pending') {
                        throw new AccountPersonClaimException('Vyřídit lze pouze čekající žádost.');
                    }
                    $freshMatch = personMatchV1($pdo, $input);
                    if ($freshMatch['level'] === PERSON_MATCH_EXACT
                        && ($confirmation !== 'exact_override' || mb_strlen($claimOverrideReason, 'UTF-8') < 10)
                    ) {
                        throw new AccountPersonClaimException('Před založením se objevila přesná shoda. Obnovte kontrolu žádosti.');
                    }
                    if ($freshMatch['level'] === PERSON_MATCH_SIMILARITY && $confirmation !== 'similarity') {
                        throw new AccountPersonClaimException('Před založením se objevila podobná osoba. Obnovte kontrolu žádosti.');
                    }
                    $personId = personMatchV1CreateManual($pdo, $input);
                    accountPersonClaimApprove($pdo, $requestId, $personId, (int)$_SESSION['trener_id'], $note);
                    $auditAction = $freshMatch['level'] === PERSON_MATCH_EXACT
                        ? 'override_create_claim'
                        : ($freshMatch['level'] === PERSON_MATCH_SIMILARITY ? 'similarity_create_claim' : 'create_claim');
                    personMatchV1Audit(
                        $pdo,
                        (int)$_SESSION['trener_id'],
                        $auditAction,
                        $freshMatch,
                        $personId,
                        $claimOverrideReason,
                        ['source'=>'eshop_identity_admin','request_id'=>$requestId]
                    );
                    $pdo->commit();
                    $_SESSION['flash_success'] = 'Osoba byla založena a žádost schválena v jedné transakci.';
                }
            } elseif ($action === 'reject_claim') {
                $claim = identityAdminClaim($pdo, (int)($_POST['request_id'] ?? 0));
                if ((string)($claim['request_kind'] ?? 'person_link') !== 'person_link') {
                    throw new AccountPersonClaimException('Tato registrační žádost vyžaduje řízené zamítnutí.');
                }
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
            if ($redirect) {
                header('Location: eshop_identity_admin.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('eshop_identity_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala. Nebyla uložena částečná změna.';
        } catch (InvalidArgumentException | AccountPersonRoleException | AccountPersonClaimException | AthleteRegistrationAdminException | PersonSensitiveException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('eshop_identity_admin.php: ' . $exception->getMessage());
            $errors[] = 'Operaci se nepodařilo bezpečně dokončit.';
        }
    }
}

$success = (string)($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$accounts = $pdo->query(
    'SELECT id, jmeno, prijmeni, email, email_overeno, aktivni '
    . 'FROM verejni_uzivatele ORDER BY prijmeni, jmeno, email, id'
)->fetchAll(PDO::FETCH_ASSOC);
$peopleTotal = (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn();
$personSearch = trim((string)($_GET['person_q'] ?? ''));
$people = [];
if (mb_strlen($personSearch, 'UTF-8') >= 2) {
    $personSearchStatement = $pdo->prepare(
        'SELECT id,jmeno,prijmeni,narozeni,stav_clenstvi FROM sportovci '
        . 'WHERE jmeno LIKE :q OR prijmeni LIKE :q OR CONCAT(prijmeni,\' \',jmeno) LIKE :q '
        . 'OR CAST(id AS CHAR)=:id ORDER BY prijmeni,jmeno,narozeni,id LIMIT 100'
    );
    $personSearchStatement->execute([':q'=>'%' . $personSearch . '%', ':id'=>$personSearch]);
    $people = $personSearchStatement->fetchAll(PDO::FETCH_ASSOC);
}
$relations = accountPersonRoleList($pdo);
$events = accountPersonRoleEvents($pdo);
$claims = accountPersonClaimListForAdmin($pdo);
$pendingClaims = array_values(array_filter($claims, static fn (array $claim): bool => $claim['status'] === 'pending'));
$closedClaims = array_values(array_filter($claims, static fn (array $claim): bool => $claim['status'] !== 'pending'));
$chargeSeasons = athleteRegistrationAdminChargeSeasons($pdo);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container py-4" style="max-width:1300px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div><h1 class="h4 mb-0"><i class="bi bi-people me-2 text-primary"></i>Účty, rodiče a sportovci</h1><div class="text-muted small">Schválené osoby, které účet smí spravovat nebo přihlašovat.</div></div>
        <a href="pracovni_pozice.php" class="btn btn-outline-secondary btn-sm">Rozcestník členů</a>
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
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Sportovci v evidenci</div><div class="h3 mb-0"><?= $peopleTotal ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                <label class="form-label mb-0 fw-semibold" for="person-q">Vyhledat osobu pro schválení</label>
                <input id="person-q" class="form-control form-control-sm" style="max-width:360px" name="person_q" value="<?= identityAdminH($personSearch) ?>" placeholder="Alespoň 2 znaky jména, příjmení nebo celé ID">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Hledat</button>
                <?php if ($personSearch !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="eshop_identity_admin.php">Zrušit</a><?php endif; ?>
                <span class="text-muted small"><?= $personSearch === '' ? 'Seznam se nenačítá celý; hledání funguje i při desítkách tisíc osob.' : 'Nalezeno: ' . count($people) ?></span>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Žádosti čekající na ověření <span class="badge bg-warning text-dark ms-1"><?= count($pendingClaims) ?></span></div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark"><tr><th>Účet</th><th>Uvedená osoba</th><th>Vztah</th><th>Poznámka</th><th style="min-width:520px">Rozhodnutí administrátora</th></tr></thead>
            <tbody>
            <?php foreach ($pendingClaims as $claim): ?>
                <tr>
                    <td><?= identityAdminH($claim['account_prijmeni'] . ' ' . $claim['account_jmeno']) ?><div class="small text-muted"><?= identityAdminH($claim['account_email']) ?></div></td>
                    <td><strong><?= identityAdminH($claim['claimed_prijmeni'] . ' ' . $claim['claimed_jmeno']) ?></strong><div class="small text-muted">nar. <?= identityAdminH($claim['claimed_narozeni']) ?></div></td>
                    <td><code><?= identityAdminH($claim['requested_role']) ?></code><?php if (($claim['request_kind'] ?? 'person_link') === 'athlete_registration'): ?><div><span class="badge bg-primary mt-1">registrace sportovce</span></div><?php endif; ?></td>
                    <td><?= identityAdminH($claim['requester_message']) ?></td>
                    <td>
                        <?php if (($claim['request_kind'] ?? 'person_link') === 'athlete_registration'): ?>
                            <?php if ($claimPreviewId !== (int)$claim['id'] || !is_array($registrationReview)): ?>
                                <form method="post">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="review_registration"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-shield-check me-1"></i>Zkontrolovat registrační podklady</button>
                                </form>
                            <?php else: ?>
                                <div class="border rounded p-2 mb-2 bg-white">
                                    <div class="fw-semibold mb-2">Kontaktní a adresní údaje</div>
                                    <div class="row g-2 small">
                                        <div class="col-md-6"><span class="text-muted">Kontakt:</span> <?= identityAdminH($registrationReview['contact_email_snapshot']) ?> · <?= identityAdminH($registrationReview['contact_phone']) ?></div>
                                        <div class="col-md-6"><span class="text-muted">Občanství:</span> <?= identityAdminH($registrationReview['citizenship_country_code']) ?> · <?= (int)$registrationReview['has_czech_birth_number'] === 1 ? 'české RČ přiděleno' : 'cizinec bez přiděleného českého RČ' ?></div>
                                        <div class="col-12"><span class="text-muted">Adresa:</span> <?= identityAdminH($registrationReview['address_street'] . ' ' . $registrationReview['address_house_number'] . ($registrationReview['address_orientation_number'] ? '/' . $registrationReview['address_orientation_number'] : '') . ', ' . $registrationReview['address_postcode'] . ' ' . $registrationReview['address_city']) ?></div>
                                    </div>
                                    <div class="mt-2 small"><span class="text-muted">Snapshoty:</span> <?php foreach ($registrationReview['consents'] as $consent): ?><span class="badge <?= (int)$consent['accepted'] === 1 ? 'bg-success' : 'bg-secondary' ?> me-1"><?= identityAdminH($consent['purpose']) ?> <?= (int)$consent['accepted'] === 1 ? 'ano' : 'ne' ?> · <?= identityAdminH($consent['terms_version']) ?></span><?php endforeach; ?></div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                        <?php if ($registrationReview['sensitive_record_id'] !== null): ?>
                                            <span class="badge bg-dark">RČ <?= identityAdminH($registrationReview['birth_number_masked']) ?></span>
                                            <form method="post" action="athlete_sensitive_admin.php" class="d-flex gap-1 athlete-sensitive-reveal">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="reveal"><input type="hidden" name="record_id" value="<?= (int)$registrationReview['sensitive_record_id'] ?>">
                                                <input class="form-control form-control-sm" name="reason" minlength="10" maxlength="1000" required placeholder="Důvod odhalení RČ">
                                                <button class="btn btn-sm btn-outline-danger">Odhalit celé RČ</button>
                                            </form>
                                            <span class="small fw-semibold text-danger athlete-sensitive-result" aria-live="polite"></span>
                                        <?php else: ?><span class="badge bg-secondary">Bez českého RČ</span><?php endif; ?>
                                        <?php if ($registrationReview['private_photo_id'] !== null): ?><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer" href="private_download.php?kind=athlete-photo&amp;id=<?= (int)$registrationReview['private_photo_id'] ?>">Zobrazit interní fotografii</a><?php else: ?><span class="text-muted small">Fotografie nebyla přiložena.</span><?php endif; ?>
                                    </div>
                                </div>

                                <?php $registrationMatch = $registrationReview['match']; ?>
                                <div class="alert <?= $registrationMatch['level'] === PERSON_MATCH_EXACT ? 'alert-danger' : ($registrationMatch['level'] === PERSON_MATCH_SIMILARITY ? 'alert-warning' : 'alert-success') ?> py-2 mb-2">
                                    <div class="fw-semibold"><?= $registrationMatch['level'] === PERSON_MATCH_EXACT ? 'SHODA – novou osobu nezakládejte bez zdůvodněné výjimky' : ($registrationMatch['level'] === PERSON_MATCH_SIMILARITY ? 'PODOBNOST – zkontrolujte všechny kandidáty' : 'Person-match-v1 nenašel kandidáta') ?></div>
                                    <?php foreach ($registrationMatch['candidates'] as $candidate): ?>
                                        <form method="post" class="border-top pt-2 mt-2">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="approve_registration_existing"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>"><input type="hidden" name="sportovec_id" value="<?= (int)$candidate['id'] ?>">
                                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                                <span class="me-auto"><strong>#<?= (int)$candidate['id'] ?> · <?= identityAdminH($candidate['prijmeni'] . ' ' . $candidate['jmeno']) ?></strong> · nar. <?= identityAdminH($candidate['narozeni'] ?? 'neuvedeno') ?> · <?= identityAdminH(implode(', ', $candidate['rules'])) ?><?php if ($candidate['kis_external_id']): ?> <span class="badge bg-info text-dark">KIS</span><?php endif; ?><?php if ($candidate['birth_number_match']): ?> <span class="badge bg-success">shodný bezpečný otisk RČ</span><?php elseif ($candidate['birth_number_conflict']): ?> <span class="badge bg-danger">konflikt chráněného RČ</span><?php endif; ?></span>
                                                <input class="form-control form-control-sm" style="max-width:240px" name="note" minlength="10" maxlength="1000" required placeholder="Podklad ověření, min. 10 znaků">
                                                <button class="btn btn-sm btn-primary" <?= $candidate['birth_number_conflict'] ? 'disabled' : '' ?>>Připojit k této osobě</button>
                                            </div>
                                        </form>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($people !== []): ?>
                                    <form method="post" class="row g-1 mb-2">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="approve_registration_existing"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                        <div class="col-6"><select class="form-select form-select-sm" name="sportovec_id" required><option value="">Jiná vyhledaná osoba</option><?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>">#<?= (int)$person['id'] ?> · <?= identityAdminH($person['prijmeni'] . ' ' . $person['jmeno'] . ' – ' . $person['narozeni']) ?></option><?php endforeach; ?></select></div>
                                        <div class="col-4"><input class="form-control form-control-sm" name="note" minlength="10" maxlength="1000" required placeholder="Podklad ověření"></div><div class="col-2 d-grid"><button class="btn btn-sm btn-success">Připojit</button></div>
                                    </form>
                                <?php endif; ?>

                                <form method="post" class="row g-1 mb-2">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="create_registration_person"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                    <input type="hidden" name="create_confirmation" value="<?= $registrationMatch['level'] === PERSON_MATCH_EXACT ? 'exact_override' : ($registrationMatch['level'] === PERSON_MATCH_SIMILARITY ? 'similarity' : 'none') ?>">
                                    <div class="col-7"><input class="form-control form-control-sm" name="note" minlength="10" maxlength="1000" required placeholder="Podklad založení a schválení, min. 10 znaků"></div>
                                    <div class="col-5 d-grid"><button class="btn btn-sm <?= $registrationMatch['level'] === PERSON_MATCH_EXACT ? 'btn-danger' : 'btn-outline-success' ?>"><?= $registrationMatch['level'] === PERSON_MATCH_EXACT ? 'Přesto založit jako novou osobu' : 'Založit novou osobu a schválit' ?></button></div>
                                    <?php if ($registrationMatch['level'] === PERSON_MATCH_EXACT): ?><div class="col-12"><textarea class="form-control form-control-sm" name="override_reason" minlength="10" maxlength="1000" required placeholder="Povinný důvod výjimky, alespoň 10 znaků"></textarea></div><?php endif; ?>
                                </form>
                                <form method="post" class="d-flex gap-1">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reject_registration"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                    <input class="form-control form-control-sm" name="note" minlength="10" maxlength="1000" required placeholder="Důvod zamítnutí, min. 10 znaků"><button class="btn btn-sm btn-outline-danger">Zamítnout registraci</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                        <?php if ($claimPreviewId === (int)$claim['id'] && is_array($claimMatch)): ?>
                            <div class="alert <?= $claimMatch['level'] === PERSON_MATCH_EXACT ? 'alert-danger' : 'alert-warning' ?> py-2 mb-2">
                                <div class="fw-semibold"><?= $claimMatch['level'] === PERSON_MATCH_EXACT ? 'Přesná shoda – vyberte správnou osobu' : 'Podobné osoby – před založením je zkontrolujte' ?></div>
                                <?php foreach ($claimMatch['candidates'] as $candidate): ?>
                                    <form method="post" class="border-top pt-2 mt-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve_claim">
                                        <input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                        <input type="hidden" name="sportovec_id" value="<?= (int)$candidate['id'] ?>">
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="me-auto"><strong><?= identityAdminH($candidate['prijmeni'] . ' ' . $candidate['jmeno']) ?></strong> · nar. <?= identityAdminH($candidate['narozeni'] ?? 'neuvedeno') ?> · <?= identityAdminH(implode(', ', $candidate['rules'])) ?><?php if ($candidate['kis_external_id']): ?> <span class="badge bg-info text-dark">KIS</span><?php endif; ?></span>
                                            <input class="form-control form-control-sm" style="max-width:220px" name="note" maxlength="1000" required placeholder="Podklad ověření">
                                            <button class="btn btn-sm btn-primary">Připojit k této osobě</button>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($people !== []): ?>
                            <form method="post" class="row g-1 mb-2">
                                <?= csrf_field() ?><input type="hidden" name="action" value="approve_claim"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                <div class="col-6"><select class="form-select form-select-sm" name="sportovec_id" required><option value="">Výsledek hledání – vyberte osobu</option><?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>">#<?= (int)$person['id'] ?> · <?= identityAdminH($person['prijmeni'] . ' ' . $person['jmeno'] . ' – ' . $person['narozeni']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-4"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Podklad ověření"></div>
                                <div class="col-2 d-grid"><button class="btn btn-sm btn-success">Schválit</button></div>
                            </form>
                        <?php else: ?>
                            <div class="small text-muted mb-2">Pro připojení jiné existující osoby ji nejdřív vyhledejte nahoře na stránce.</div>
                        <?php endif; ?>

                        <form method="post" class="row g-1 mb-2">
                            <?= csrf_field() ?><input type="hidden" name="action" value="create_claim_person"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                            <?php if ($claimPreviewId === (int)$claim['id'] && is_array($claimMatch)): ?><input type="hidden" name="create_confirmation" value="<?= $claimMatch['level'] === PERSON_MATCH_EXACT ? 'exact_override' : 'similarity' ?>"><?php endif; ?>
                            <div class="col-7"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Podklad ověření pro založení a schválení"></div>
                            <div class="col-5 d-grid"><button class="btn btn-sm <?= $claimPreviewId === (int)$claim['id'] && is_array($claimMatch) && $claimMatch['level'] === PERSON_MATCH_EXACT ? 'btn-danger' : 'btn-outline-success' ?>"><?= $claimPreviewId === (int)$claim['id'] && is_array($claimMatch) && $claimMatch['level'] === PERSON_MATCH_EXACT ? 'Přesto založit jako novou osobu' : ($claimPreviewId === (int)$claim['id'] && is_array($claimMatch) ? 'Potvrdit založení a schválit' : 'Založit osobu z údajů žádosti a schválit') ?></button></div>
                            <?php if ($claimPreviewId === (int)$claim['id'] && is_array($claimMatch) && $claimMatch['level'] === PERSON_MATCH_EXACT): ?><div class="col-12"><textarea class="form-control form-control-sm" name="override_reason" minlength="10" maxlength="1000" required placeholder="Povinný důvod výjimky, alespoň 10 znaků"><?= identityAdminH($claimOverrideReason) ?></textarea></div><?php endif; ?>
                        </form>

                        <form method="post" class="d-flex gap-1">
                            <?= csrf_field() ?><input type="hidden" name="action" value="reject_claim"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                            <input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod zamítnutí"><button class="btn btn-sm btn-outline-danger">Zamítnout</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pendingClaims === []): ?><tr><td colspan="5" class="text-center text-muted py-4">Žádná žádost nyní nečeká na kontrolu.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

    <?php if ($closedClaims !== []): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Historie vyřízených žádostí</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Účet</th><th>Uvedená osoba</th><th>Stav</th><th>Propojená osoba</th><th>Rozhodnutí a další krok</th></tr></thead>
                <tbody>
                <?php foreach ($closedClaims as $claim): ?>
                    <tr>
                        <td><?= identityAdminH($claim['account_email']) ?></td>
                        <td><?= identityAdminH($claim['claimed_prijmeni'] . ' ' . $claim['claimed_jmeno']) ?><div class="small text-muted"><?= identityAdminH($claim['claimed_narozeni']) ?></div></td>
                        <td><span class="badge <?= $claim['status'] === 'approved' ? 'bg-success' : 'bg-secondary' ?>"><?= identityAdminH($claim['status']) ?></span><?php if (($claim['request_kind'] ?? 'person_link') === 'athlete_registration'): ?><div><span class="badge bg-primary mt-1">registrace sportovce</span></div><?php endif; ?></td>
                        <td><?= $claim['matched_sportovec_id'] ? identityAdminH($claim['matched_prijmeni'] . ' ' . $claim['matched_jmeno']) : '—' ?></td>
                        <td>
                            <?= identityAdminH($claim['decision_note']) ?><div class="small text-muted mb-1"><?= identityAdminH($claim['decider_name'] ?: $claim['decided_at']) ?></div>
                            <?php if (($claim['request_kind'] ?? 'person_link') === 'athlete_registration' && $claim['status'] === 'approved' && $claim['matched_sportovec_id']): ?>
                                <?php if ($claimPreviewId === (int)$claim['id'] && is_array($assignmentContext)): ?>
                                    <?php if ($assignmentContext['active_rosters'] !== []): ?><div class="small mb-2"><span class="text-muted">Aktivní soupisky:</span> <?= identityAdminH(implode(', ', array_map(static fn(array $row): string => $row['season_name'] . ' / ' . $row['team_name'], $assignmentContext['active_rosters']))) ?></div><?php endif; ?>
                                    <form method="post" class="row g-1 align-items-end">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="assign_registration"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                        <div class="col-lg-3"><label class="form-label small mb-1">Skupina</label><select class="form-select form-select-sm athlete-group-select" name="group_id" required><option value="">Vyberte</option><?php foreach ($assignmentContext['options']['groups'] as $group): ?><option value="<?= (int)$group['id'] ?>"><?= identityAdminH($group['nazev']) ?></option><?php endforeach; ?></select></div>
                                        <div class="col-lg-3"><label class="form-label small mb-1">Podskupina</label><select class="form-select form-select-sm athlete-subgroup-select" name="subgroup_id" required><option value="">Vyberte</option><?php foreach ($assignmentContext['options']['subgroups'] as $subgroup): ?><option value="<?= (int)$subgroup['id'] ?>" data-group-id="<?= (int)$subgroup['skupina_id'] ?>"><?= identityAdminH($subgroup['skupina_nazev'] . ' / ' . $subgroup['nazev']) ?></option><?php endforeach; ?></select></div>
                                        <div class="col-lg-4"><label class="form-label small mb-1">Aktivní sezona / tým</label><select class="form-select form-select-sm" name="team_id" required><option value="">Vyberte</option><?php foreach ($assignmentContext['options']['teams'] as $team): ?><option value="<?= (int)$team['id'] ?>"><?= identityAdminH($team['season_name'] . ' / ' . $team['name'] . ' (' . $team['starts_on'] . '–' . $team['ends_on'] . ')') ?></option><?php endforeach; ?></select></div>
                                        <div class="col-lg-8"><label class="form-label small mb-1">Důvod ručního zařazení</label><input class="form-control form-control-sm" name="reason" minlength="10" maxlength="1000" required placeholder="Alespoň 10 znaků"></div>
                                        <div class="col-lg-2 d-grid"><button class="btn btn-sm btn-primary">Zařadit sportovce</button></div>
                                    </form>
                                <?php else: ?>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_assignment"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>"><button class="btn btn-sm btn-outline-primary">Zařadit sportovce</button></form>
                                <?php endif; ?>
                                <div class="border-top mt-2 pt-2">
                                    <?php if ($claimPreviewId === (int)$claim['id'] && is_array($chargeContext)): ?>
                                        <div class="small fw-semibold mb-1">Připravenost členského předpisu · <?= identityAdminH($chargeContext['season']['name'] ?? 'neplatná sezona') ?></div>
                                        <?php foreach ([
                                            'approved_person' => 'Schválená registrace a osoba',
                                            'group' => 'Skupina',
                                            'subgroup' => 'Podskupina patřící do skupiny',
                                            'current_season_roster' => 'Aktivní soupiska v aktuální sezoně',
                                        ] as $readinessKey => $readinessLabel): ?>
                                            <span class="badge <?= $chargeContext['readiness'][$readinessKey] ? 'bg-success' : 'bg-danger' ?> me-1 mb-1"><?= identityAdminH($readinessLabel) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (is_array($chargeContext['existing_charge'])): ?>
                                            <div class="alert alert-info py-2 mt-2 mb-0">Pro tuto registraci a sezonu už existuje předpis <code><?= identityAdminH($chargeContext['existing_charge']['public_code']) ?></code>. Finanční stav ověřuje pozice Hospodář a platby.</div>
                                        <?php elseif ($chargeContext['ready']): ?>
                                            <form method="post" class="row g-2 align-items-end mt-1">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="create_registration_charge"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>"><input type="hidden" name="season_id" value="<?= (int)$chargeContext['season']['id'] ?>">
                                                <div class="col-lg-4"><label class="form-label small mb-1">Název předpisu</label><input class="form-control form-control-sm" name="title" maxlength="255" required value="Členský příspěvek <?= identityAdminH($chargeContext['season']['name']) ?>"></div>
                                                <div class="col-lg-2"><label class="form-label small mb-1">Od</label><input class="form-control form-control-sm" type="date" name="period_from" required value="<?= identityAdminH($chargeContext['season']['starts_on']) ?>"></div>
                                                <div class="col-lg-2"><label class="form-label small mb-1">Do</label><input class="form-control form-control-sm" type="date" name="period_to" required value="<?= identityAdminH($chargeContext['season']['ends_on']) ?>"></div>
                                                <div class="col-lg-2"><label class="form-label small mb-1">Splatnost</label><input class="form-control form-control-sm" type="date" name="due_on" required></div>
                                                <div class="col-lg-2"><label class="form-label small mb-1">Částka v haléřích</label><input class="form-control form-control-sm" name="amount_minor" inputmode="numeric" pattern="[0-9]+" required placeholder="250000"></div>
                                                <div class="col-lg-2"><label class="form-label small mb-1">Měna</label><input class="form-control form-control-sm text-uppercase" name="currency" minlength="3" maxlength="3" required value="CZK"></div>
                                                <div class="col-lg-5"><label class="form-label small mb-1">Plátce</label><select class="form-select form-select-sm" name="payer_account_id" required><option value="">Vyberte aktivní vazbu</option><?php foreach ($chargeContext['payers'] as $payer): ?><option value="<?= (int)$payer['account_id'] ?>"><?= identityAdminH($payer['prijmeni'] . ' ' . $payer['jmeno'] . ' – ' . $payer['relation_role'] . ' – ' . $payer['email']) ?></option><?php endforeach; ?></select></div>
                                                <div class="col-lg-5"><label class="form-label small mb-1">Důvod vystavení</label><input class="form-control form-control-sm" name="reason" minlength="10" maxlength="1000" required placeholder="Alespoň 10 znaků"></div>
                                                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirm-charge-<?= (int)$claim['id'] ?>" required><label class="form-check-label small" for="confirm-charge-<?= (int)$claim['id'] ?>">Výslovně potvrzuji vystavení tohoto členského předpisu.</label></div>
                                                <div class="col-lg-4 d-grid"><button class="btn btn-sm btn-success">Vystavit členský předpis</button></div>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-danger small mt-1">Předpis nyní nelze vystavit. Doplňte všechny čtyři podmínky; nic se nevytvořilo.</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="post" class="d-flex gap-1 align-items-center flex-wrap">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="review_charge"><input type="hidden" name="request_id" value="<?= (int)$claim['id'] ?>">
                                            <select class="form-select form-select-sm" style="max-width:300px" name="season_id" required><option value="">Aktuální sezona</option><?php foreach ($chargeSeasons as $season): ?><option value="<?= (int)$season['id'] ?>"><?= identityAdminH($season['name'] . ' (' . $season['starts_on'] . '–' . $season['ends_on'] . ')') ?></option><?php endforeach; ?></select>
                                            <button class="btn btn-sm btn-outline-success"<?= $chargeSeasons === [] ? ' disabled' : '' ?>>Prověřit členský předpis</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    <?php endif; ?>

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

<script>
document.querySelectorAll('.athlete-sensitive-reveal').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const result = form.parentElement.querySelector('.athlete-sensitive-result');
        result.textContent = 'Načítám…';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json();
            result.textContent = payload.ok ? payload.value : (payload.message || 'Údaj nelze zobrazit.');
            if (payload.ok) form.reset();
        } catch (error) {
            result.textContent = 'Údaj nelze zobrazit.';
        }
    });
});
document.querySelectorAll('.athlete-group-select').forEach(function (groupSelect) {
    const form = groupSelect.closest('form');
    const subgroupSelect = form ? form.querySelector('.athlete-subgroup-select') : null;
    if (!subgroupSelect) return;
    const filterSubgroups = function () {
        const groupId = groupSelect.value;
        Array.from(subgroupSelect.options).forEach(function (option, index) {
            option.hidden = index > 0 && option.dataset.groupId !== groupId;
            if (option.hidden && option.selected) subgroupSelect.value = '';
        });
    };
    groupSelect.addEventListener('change', filterSubgroups);
    filterSubgroups();
});
</script>
</body>
</html>
