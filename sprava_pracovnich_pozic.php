<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/staff_workspaces.php';
require_once __DIR__ . '/includes/staff_account_lifecycle.php';

staffRequireActivePosition('system_admin');

function staffAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function staffAdminSnapshot(PDO $pdo, int $trainerId): array
{
    $statement = $pdo->prepare('SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=? ORDER BY position_code');
    $statement->execute([$trainerId]);
    $positions = $statement->fetchAll(PDO::FETCH_ASSOC);
    $super = $pdo->prepare('SELECT 1 FROM staff_superadmins WHERE trainer_id=?');
    $super->execute([$trainerId]);
    return ['positions' => $positions, 'superadmin' => (bool)$super->fetchColumn()];
}
function staffAdminRecordEvent(PDO $pdo, int $targetId, int $actorId, string $action, array $before, array $after, string $reason): void
{
    $statement = $pdo->prepare('INSERT INTO staff_position_assignment_events(trainer_id,actor_trainer_id,action,before_json,after_json,reason) VALUES(?,?,?,?,?,?)');
    $statement->execute([
        $targetId,
        $actorId,
        $action,
        json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        mb_substr(trim($reason), 0, 1000, 'UTF-8'),
    ]);
}

$definitions = staffPositionDefinitions();
$validCodes = array_fill_keys(staffPositionCodes(), true);
$actorId = (int)$_SESSION['trener_id'];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $targetId = filter_var($_POST['trainer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($targetId === false) throw new InvalidArgumentException('Vyberte platný pracovní účet.');
            $target = $pdo->prepare('SELECT id,jmeno,aktivni FROM treneri WHERE id=? LIMIT 1');
            $target->execute([(int)$targetId]);
            $targetRow = $target->fetch(PDO::FETCH_ASSOC);
            if (!$targetRow) throw new InvalidArgumentException('Pracovní účet nebyl nalezen.');
            if (mb_strlen($reason, 'UTF-8') < 5) throw new InvalidArgumentException('Uveďte stručný důvod změny (alespoň 5 znaků).');

            if ($action === 'save_positions') {
                $positions = array_values(array_unique(array_map('strval', (array)($_POST['positions'] ?? []))));
                if ($positions === [] || array_filter($positions, static fn(string $code): bool => !isset($validCodes[$code])) !== []) {
                    throw new InvalidArgumentException('Účet musí mít alespoň jednu platnou pracovní pozici.');
                }
                $default = (string)($_POST['default_position'] ?? '');
                if (!in_array($default, $positions, true)) throw new InvalidArgumentException('Výchozí pozice musí být mezi přiřazenými.');

                $before = staffAdminSnapshot($pdo, (int)$targetId);
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([(int)$targetId]);
                $insert = $pdo->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,?,?,?)');
                foreach ($positions as $code) $insert->execute([(int)$targetId, $code, $code === $default ? 1 : 0, $actorId]);
                $after = staffAdminSnapshot($pdo, (int)$targetId);
                staffAdminRecordEvent($pdo, (int)$targetId, $actorId, 'positions_changed', $before, $after, $reason);
                $pdo->commit();
                if ((int)$targetId === $actorId) staffWorkspaceRefreshSession($pdo, $actorId);
                $_SESSION['flash_success'] = 'Pracovní pozice byly auditovaně uloženy.';
                header('Location: sprava_pracovnich_pozic.php?trainer_id=' . (int)$targetId, true, 303);
                exit;
            }

            if ($action === 'remove_position') {
                $positionCode = (string)($_POST['position_code'] ?? '');
                if (!isset($validCodes[$positionCode])) throw new InvalidArgumentException('Vyberte platnou pracovní pozici.');
                $before = staffAdminSnapshot($pdo, (int)$targetId);
                $assignedCodes = array_column($before['positions'], 'position_code');
                if (!in_array($positionCode, $assignedCodes, true)) throw new InvalidArgumentException('Tato pracovní pozice už není přiřazená.');
                if (count($assignedCodes) <= 1) throw new InvalidArgumentException('Poslední pracovní pozici nelze odebrat. Pokud už člověk nemá mít přístup, deaktivujte celý pracovní účet níže.');
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code=?')->execute([(int)$targetId, $positionCode]);
                $remaining = array_values(array_filter($before['positions'], static fn(array $row): bool => (string)$row['position_code'] !== $positionCode));
                $hasDefault = false;
                foreach ($remaining as $row) if ((int)$row['is_default'] === 1) $hasDefault = true;
                if (!$hasDefault) {
                    $pdo->prepare('UPDATE staff_user_positions SET is_default=CASE WHEN position_code=? THEN 1 ELSE 0 END WHERE trainer_id=?')
                        ->execute([(string)$remaining[0]['position_code'], (int)$targetId]);
                }
                $after = staffAdminSnapshot($pdo, (int)$targetId);
                staffAdminRecordEvent($pdo, (int)$targetId, $actorId, 'position_removed', $before, $after, $reason);
                $pdo->commit();
                if ((int)$targetId === $actorId) staffWorkspaceRefreshSession($pdo, $actorId);
                $_SESSION['flash_success'] = 'Pracovní pozice „' . ($definitions[$positionCode]['label'] ?? $positionCode) . '“ byla odebrána. Historie zůstala zachována.';
                header('Location: sprava_pracovnich_pozic.php?trainer_id=' . (int)$targetId, true, 303);
                exit;
            }

            if ($action === 'set_active') {
                $active = ($_POST['active'] ?? '') === '1';
                $result = staffAccountSetActive($pdo, (int)$targetId, $actorId, $active, $reason, ($_POST['confirmed'] ?? '') === '1');
                $_SESSION['flash_success'] = $result['changed']
                    ? ($active ? 'Pracovní účet byl znovu aktivován.' : 'Pracovní účet byl deaktivován. Pozice, historie a audit zůstaly zachované.')
                    : 'Stav pracovního účtu se nezměnil.';
                header('Location: sprava_pracovnich_pozic.php?trainer_id=' . (int)$targetId, true, 303);
                exit;
            }

            if ($action === 'set_superadmin') {
                if (!staffIsSuperadmin()) throw new InvalidArgumentException('Superadmina smí měnit pouze jiný superadmin.');
                if (($_POST['confirm_superadmin'] ?? '') !== '1') throw new InvalidArgumentException('Citlivou změnu je nutné výslovně potvrdit.');
                $enabled = ($_POST['enabled'] ?? '') === '1';
                $before = staffAdminSnapshot($pdo, (int)$targetId);
                if (!$enabled && $before['superadmin']) {
                    $count = (int)$pdo->query('SELECT COUNT(*) FROM staff_superadmins')->fetchColumn();
                    if ($count <= 1) throw new InvalidArgumentException('Nelze odebrat posledního superadmina.');
                }
                $pdo->beginTransaction();
                if ($enabled) {
                    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $statement = $pdo->prepare($driver === 'mysql'
                        ? 'INSERT INTO staff_superadmins(trainer_id,granted_by_trainer_id,reason) VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted_by_trainer_id=VALUES(granted_by_trainer_id),granted_at=CURRENT_TIMESTAMP,reason=VALUES(reason)'
                        : 'INSERT INTO staff_superadmins(trainer_id,granted_by_trainer_id,reason) VALUES(?,?,?) ON CONFLICT(trainer_id) DO UPDATE SET granted_by_trainer_id=excluded.granted_by_trainer_id,granted_at=CURRENT_TIMESTAMP,reason=excluded.reason');
                    $statement->execute([(int)$targetId, $actorId, $reason]);
                } else {
                    $pdo->prepare('DELETE FROM staff_superadmins WHERE trainer_id=?')->execute([(int)$targetId]);
                }
                $after = staffAdminSnapshot($pdo, (int)$targetId);
                staffAdminRecordEvent($pdo, (int)$targetId, $actorId, $enabled ? 'superadmin_granted' : 'superadmin_revoked', $before, $after, $reason);
                $pdo->commit();
                if ((int)$targetId === $actorId) staffWorkspaceRefreshSession($pdo, $actorId);
                $_SESSION['flash_success'] = $enabled ? 'Superadmin byl auditovaně udělen.' : 'Superadmin byl auditovaně odebrán.';
                header('Location: sprava_pracovnich_pozic.php?trainer_id=' . (int)$targetId, true, 303);
                exit;
            }
            throw new InvalidArgumentException('Neznámá změna pracovních pozic.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $known = $exception instanceof InvalidArgumentException || $exception instanceof StaffAccountLifecycleException;
            $errors[] = $known ? $exception->getMessage() : 'Změnu se nepodařilo bezpečně uložit.';
            if (!$known) error_log('Staff position management failed: ' . $exception->getMessage());
        }
    }
}

$trainers = $pdo->query('SELECT id,jmeno,email,role,aktivni FROM treneri ORDER BY aktivni DESC,jmeno,id')->fetchAll(PDO::FETCH_ASSOC);
$selectedId = max(0, (int)($_GET['trainer_id'] ?? 0));
if ($selectedId === 0 && $trainers !== []) $selectedId = (int)$trainers[0]['id'];
$selected = null;
foreach ($trainers as $trainer) if ((int)$trainer['id'] === $selectedId) $selected = $trainer;
$snapshot = $selected ? staffAdminSnapshot($pdo, $selectedId) : ['positions' => [], 'superadmin' => false];
$assigned = array_column($snapshot['positions'], 'position_code');
$default = '';
foreach ($snapshot['positions'] as $row) if ((int)$row['is_default'] === 1) $default = (string)$row['position_code'];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pracovní pozice</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous"></head>
<body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container-fluid py-4" style="max-width:1400px">
<div class="mb-4"><h1 class="h3 mb-1">Pracovní pozice a rozcestníky</h1><p class="text-muted mb-0">Pozice se neslučují. Každý uživatel pracuje vždy v jednom aktivním kontextu.</p></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= staffAdminH($error) ?></div><?php endforeach; ?>
<?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?= staffAdminH($_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
<div class="row g-4">
<aside class="col-lg-4"><div class="list-group shadow-sm">
<?php foreach ($trainers as $trainer): ?><a class="list-group-item list-group-item-action <?= (int)$trainer['id'] === $selectedId ? 'active' : '' ?>" href="?trainer_id=<?= (int)$trainer['id'] ?>"><strong><?= staffAdminH($trainer['jmeno']) ?></strong><div class="small <?= (int)$trainer['id'] === $selectedId ? 'text-white-50' : 'text-muted' ?>"><?= staffAdminH($trainer['email']) ?><?= (int)$trainer['aktivni'] === 1 ? '' : ' · neaktivní' ?></div></a><?php endforeach; ?>
</div></aside>
<section class="col-lg-8"><?php if ($selected): ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-start gap-2"><div><strong><?= staffAdminH($selected['jmeno']) ?></strong><div class="small text-muted">Legacy role <?= staffAdminH($selected['role']) ?> je pouze kompatibilní technický údaj.</div></div><span class="badge <?= (int)$selected['aktivni'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)$selected['aktivni'] === 1 ? 'Aktivní účet' : 'Deaktivovaný účet' ?></span></div><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="save_positions"><input type="hidden" name="trainer_id" value="<?= $selectedId ?>">
<?php foreach ($definitions as $code => $position): ?><div class="col-md-6"><label class="form-check border rounded p-3 h-100"><input class="form-check-input position-check" type="checkbox" name="positions[]" value="<?= staffAdminH($code) ?>" <?= in_array($code, $assigned, true) ? 'checked' : '' ?>><span class="form-check-label ms-1"><strong><?= staffAdminH($position['label']) ?></strong><span class="d-block small text-muted"><?= staffAdminH($position['description']) ?></span></span></label></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label">Výchozí pozice po přihlášení</label><select class="form-select" name="default_position" required><?php foreach ($definitions as $code => $position): ?><option value="<?= staffAdminH($code) ?>" <?= $default === $code ? 'selected' : '' ?>><?= staffAdminH($position['label']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Důvod změny</label><input class="form-control" name="reason" minlength="5" maxlength="1000" required></div>
<div class="col-12"><button class="btn btn-primary">Uložit pracovní pozice</button></div></form>
<hr class="my-4"><h2 class="h5">Odebrat konkrétní pracovní pozici</h2><p class="small text-muted">Pozici lze odebrat samostatně. Poslední pozice se nemaže; místo toho deaktivujte celý účet, aby zůstala zachována historie.</p>
<div class="list-group mb-4"><?php foreach ($snapshot['positions'] as $positionRow): $positionCode=(string)$positionRow['position_code']; ?><div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong><?= staffAdminH($definitions[$positionCode]['label'] ?? $positionCode) ?></strong><?= (int)$positionRow['is_default'] === 1 ? ' <span class="badge text-bg-primary">výchozí</span>' : '' ?></div><form method="post" class="d-flex flex-wrap gap-2 align-items-center" onsubmit="return confirm('Opravdu odebrat tuto pracovní pozici?');"><?= csrf_field() ?><input type="hidden" name="action" value="remove_position"><input type="hidden" name="trainer_id" value="<?= $selectedId ?>"><input type="hidden" name="position_code" value="<?= staffAdminH($positionCode) ?>"><input class="form-control form-control-sm" name="reason" minlength="5" maxlength="1000" required placeholder="Důvod odebrání"><button class="btn btn-sm btn-outline-danger" <?= count($snapshot['positions']) <= 1 ? 'disabled title="Poslední pozici nelze odebrat; deaktivujte účet."' : '' ?>><i class="bi bi-person-dash me-1"></i>Odebrat pozici</button></form></div><?php endforeach; ?></div>
<div class="border rounded p-3 <?= (int)$selected['aktivni'] === 1 ? 'border-danger-subtle bg-danger-subtle' : 'border-success-subtle bg-success-subtle' ?>"><h2 class="h5"><?= (int)$selected['aktivni'] === 1 ? 'Ukončit přístup do systému' : 'Obnovit přístup do systému' ?></h2><p class="small mb-3"><?= (int)$selected['aktivni'] === 1 ? 'Deaktivace člověka ihned odhlásí a zabrání dalšímu přihlášení. Účet, pozice i historické záznamy se nemažou.' : 'Aktivace znovu povolí přihlášení se zachovanými pracovními pozicemi.' ?></p><?php if ($selectedId === $actorId && (int)$selected['aktivni'] === 1): ?><div class="alert alert-secondary mb-0">Vlastní pracovní účet zde nelze deaktivovat.</div><?php else: ?><form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="set_active"><input type="hidden" name="trainer_id" value="<?= $selectedId ?>"><input type="hidden" name="active" value="<?= (int)$selected['aktivni'] === 1 ? '0' : '1' ?>"><div class="col-md-7"><label class="form-label">Důvod změny</label><input class="form-control" name="reason" minlength="5" maxlength="1000" required></div><div class="col-md-5"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required> Potvrzuji změnu přístupu</label><button class="btn <?= (int)$selected['aktivni'] === 1 ? 'btn-danger' : 'btn-success' ?> w-100"><?= (int)$selected['aktivni'] === 1 ? 'Deaktivovat pracovní účet' : 'Aktivovat pracovní účet' ?></button></div></form><?php endif; ?></div>
</div></div>
<div class="card <?= $snapshot['superadmin'] ? 'border-warning' : 'border-0' ?> shadow-sm"><div class="card-body"><h2 class="h5"><i class="bi bi-shield-lock me-2 text-warning"></i>Superadmin</h2><p class="small text-muted">Superadmin může přepnout do všech pozic, ale stále vidí vždy jen jeden rozcestník. Tuto schopnost smí změnit pouze jiný superadmin.</p>
<div class="alert <?= $snapshot['superadmin'] ? 'alert-warning' : 'alert-secondary' ?> py-2">Aktuální stav: <strong><?= $snapshot['superadmin'] ? 'superadmin' : 'běžný pracovní účet' ?></strong></div>
<?php if (staffIsSuperadmin()): ?><form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="set_superadmin"><input type="hidden" name="trainer_id" value="<?= $selectedId ?>"><input type="hidden" name="enabled" value="<?= $snapshot['superadmin'] ? '0' : '1' ?>"><div class="col-md-7"><label class="form-label">Důvod citlivé změny</label><input class="form-control" name="reason" minlength="5" maxlength="1000" required></div><div class="col-md-5"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm_superadmin" value="1" required> Potvrzuji citlivou změnu</label><button class="btn <?= $snapshot['superadmin'] ? 'btn-outline-danger' : 'btn-warning' ?> w-100"><?= $snapshot['superadmin'] ? 'Odebrat superadmina' : 'Udělit superadmina' ?></button></div></form><?php endif; ?>
</div></div>
<?php endif; ?></section></div></main></body></html>
