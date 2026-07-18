<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/sportovec_history_lib.php';

if (!isset($_SESSION['trener_id']) || !canAccess('sprava_sportovcu')) {
    header('Location: login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný nebo chybějící požadavek.';
    header('Location: sprava_sportovcu.php');
    exit;
}

$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['sportovec_ids'] ?? [])))));
if (!$ids) {
    $_SESSION['flash_error'] = 'Nebyl vybrán žádný člen.';
    header('Location: sprava_sportovcu.php');
    exit;
}

$in = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT s.id, s.jmeno, s.prijmeni, s.stav_clenstvi, s.stav_manualni,
           GROUP_CONCAT(DISTINCT sk.nazev ORDER BY sk.nazev SEPARATOR ', ') AS skupiny
    FROM sportovci s
    LEFT JOIN sportovec_skupina ss ON ss.sportovec_id = s.id
    LEFT JOIN skupiny sk ON sk.id = ss.skupina_id
    WHERE s.id IN ($in)
    GROUP BY s.id
    ORDER BY s.prijmeni, s.jmeno
");
$stmt->execute($ids);
$sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);
$groups = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$previewOnly = ($_POST['confirm'] ?? '') !== '1';
$action = $_POST['bulk_action'] ?? 'set_status';
$message = null;

if (!$previewOnly) {
    $pdo->beginTransaction();
    try {
        foreach ($sportovci as $s) {
            $sid = (int)$s['id'];
            if ($action === 'set_status') {
                $stav = $_POST['stav_clenstvi'] ?? 'cekajici';
                if (!in_array($stav, ['aktivni','cekajici','neaktivni','archiv'], true)) $stav = 'cekajici';
                $duvod = trim((string)($_POST['stav_duvod'] ?? ''));
                $pdo->prepare("
                    UPDATE sportovci
                    SET stav_clenstvi = ?, stav_duvod = ?, stav_manualni = 1, stav_aktualizovan = CURRENT_TIMESTAMP
                    WHERE id = ? LIMIT 1
                ")->execute([$stav, $duvod ?: null, $sid]);
                sportovecLogEvent($pdo, $sid, 'bulk_status', 'Hromadná změna stavu', $s, ['stav_clenstvi' => $stav, 'stav_duvod' => $duvod], 'bulk_action', (int)$_SESSION['trener_id']);
            } elseif ($action === 'clear_manual') {
                $pdo->prepare("UPDATE sportovci SET stav_manualni = 0, stav_aktualizovan = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1")->execute([$sid]);
                sportovecLogEvent($pdo, $sid, 'bulk_clear_manual', 'Hromadné zrušení ručního stavu', $s, [], 'bulk_action', (int)$_SESSION['trener_id']);
            } elseif ($action === 'assign_group') {
                $groupId = (int)($_POST['skupina_id'] ?? 0);
                if ($groupId > 0) {
                    $pdo->prepare("INSERT IGNORE INTO sportovec_skupina (sportovec_id, skupina_id) VALUES (?, ?)")->execute([$sid, $groupId]);
                    sportovecLogEvent($pdo, $sid, 'bulk_group', 'Hromadné přiřazení skupiny', $s, ['skupina_id' => $groupId], 'bulk_action', (int)$_SESSION['trener_id']);
                }
            } elseif ($action === 'add_note') {
                $note = trim((string)($_POST['note'] ?? ''));
                if ($note !== '') {
                    $pdo->prepare("INSERT INTO sportovec_interni_poznamka (sportovec_id, trener_id, text) VALUES (?, ?, ?)")->execute([$sid, (int)$_SESSION['trener_id'], $note]);
                    sportovecLogEvent($pdo, $sid, 'bulk_note', 'Hromadná interní poznámka', [], ['text' => $note], 'bulk_action', (int)$_SESSION['trener_id'], $note);
                }
            }
        }
        $pdo->commit();
        $_SESSION['flash_success'] = 'Hromadná akce byla provedena pro ' . count($sportovci) . ' členů.';
        header('Location: sprava_sportovcu.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Chyba: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hromadné akce členů</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container py-4" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Hromadné akce členů</h1>
        <a href="sprava_sportovcu.php" class="btn btn-outline-secondary btn-sm">Zpět</a>
    </div>
    <?php if ($message): ?><div class="alert alert-danger"><?= h($message) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold">Akce</div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <?php foreach ($ids as $id): ?><input type="hidden" name="sportovec_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <input type="hidden" name="confirm" value="1">
                        <div class="mb-3">
                            <label class="form-label">Typ akce</label>
                            <select name="bulk_action" class="form-select" id="bulkAction">
                                <option value="set_status">Nastavit ruční stav</option>
                                <option value="clear_manual">Zrušit ruční stav</option>
                                <option value="assign_group">Přiřadit skupinu</option>
                                <option value="add_note">Přidat interní poznámku</option>
                            </select>
                        </div>
                        <div class="mb-3 action-field action-status">
                            <label class="form-label">Stav</label>
                            <select name="stav_clenstvi" class="form-select">
                                <option value="aktivni">Aktivní</option>
                                <option value="cekajici">Čekající</option>
                                <option value="neaktivni">Neaktivní</option>
                                <option value="archiv">Archiv</option>
                            </select>
                            <input name="stav_duvod" class="form-control mt-2" placeholder="Důvod">
                        </div>
                        <div class="mb-3 action-field action-group d-none">
                            <label class="form-label">Skupina</label>
                            <select name="skupina_id" class="form-select">
                                <option value="">-- vyberte --</option>
                                <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= h($g['nazev']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 action-field action-note d-none">
                            <label class="form-label">Poznámka</label>
                            <textarea name="note" class="form-control" rows="4"></textarea>
                        </div>
                        <button class="btn btn-primary" data-confirm="Provést hromadnou akci pro vybrané členy?">
                            <i class="bi bi-check2-all me-1"></i>Provést
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-semibold">Preview: <?= count($sportovci) ?> členů</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Člen</th><th>Stav</th><th>Skupiny</th></tr></thead>
                        <tbody>
                        <?php foreach ($sportovci as $s): ?>
                            <tr>
                                <td><a href="sportovec_karta.php?sportovec_id=<?= (int)$s['id'] ?>" title="Administrační karta člena"><?= h($s['prijmeni'] . ' ' . $s['jmeno']) ?></a></td>
                                <td><?= h($s['stav_clenstvi'] ?? 'cekajici') ?><?= !empty($s['stav_manualni']) ? ' ručně' : '' ?></td>
                                <td><?= $s['skupiny'] ? h($s['skupiny']) : '<span class="text-muted">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const action = document.getElementById('bulkAction');
function syncFields() {
    document.querySelectorAll('.action-field').forEach(el => el.classList.add('d-none'));
    if (action.value === 'set_status') document.querySelector('.action-status').classList.remove('d-none');
    if (action.value === 'assign_group') document.querySelector('.action-group').classList.remove('d-none');
    if (action.value === 'add_note') document.querySelector('.action-note').classList.remove('d-none');
}
action.addEventListener('change', syncFields);
syncFields();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
