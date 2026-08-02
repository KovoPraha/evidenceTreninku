<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];

// ─── POST: uložení oprávnění ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $roles = $_POST['min_role'] ?? [];
            $validRoles = ['trener', 'hlavni', 'admin'];
            $updated = 0;

            $stmt = $pdo->prepare("UPDATE opravneni SET min_role = ? WHERE klic = ? LIMIT 1");
            foreach ($roles as $klic => $role) {
                if (in_array($role, $validRoles, true)) {
                    $stmt->execute([$role, $klic]);
                    $updated++;
                }
            }

            // Refresh current session permissions
            try {
                $_SESSION['opravneni'] = $pdo->query("SELECT klic, min_role FROM opravneni")->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {}

            $_SESSION['flash_success'] = "Oprávnění uložena ($updated položek). Změny se projeví po příštím přihlášení ostatních uživatelů.";
            header('Location: nastaveni_opravneni.php');
            exit;
        }

        if ($action === 'reset') {
            // Reset all to defaults — re-run the defaults
            $defaults = [
                'formular'=>'trener','duplikovat_trenink'=>'trener','nova_cinnost'=>'trener','zatezovy_test'=>'trener',
                'moje_treninky'=>'trener','moje_skupiny'=>'trener','prehled_trenera'=>'trener','prehled_sportovcu'=>'trener',
                'kalendar'=>'trener','vypis_vykazu'=>'trener','exporty'=>'trener','skupiny_podskupiny'=>'trener',
                'google_sheets'=>'trener','oznameni'=>'trener','cviky'=>'trener','odmeny'=>'trener',
                'segmenty'=>'hlavni','sprava_sportovcu'=>'hlavni','sprava_treninku'=>'hlavni','sprava_skupin'=>'hlavni',
                'sprava_podskupin'=>'hlavni','sprava_zavodu'=>'hlavni','verejny_prehled'=>'hlavni','vsechny_vykazy'=>'hlavni',
                'odeslat_emaily'=>'hlavni','prehled_kreditu'=>'hlavni','kreditni_obdobi'=>'hlavni','sync_evidence'=>'hlavni',
                'prehled_popisu'=>'trener',
                'nastaveni_zadavani'=>'admin','auditlog'=>'admin',
                'vozidla'=>'admin','jizdy'=>'admin','servis'=>'admin','uctenky'=>'admin','udalosti'=>'admin',
            ];
            $stmt = $pdo->prepare("UPDATE opravneni SET min_role = ? WHERE klic = ?");
            foreach ($defaults as $k => $r) {
                $stmt->execute([$r, $k]);
            }

            try {
                $_SESSION['opravneni'] = $pdo->query("SELECT klic, min_role FROM opravneni")->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {}

            $_SESSION['flash_success'] = 'Oprávnění resetována na výchozí hodnoty.';
            header('Location: nastaveni_opravneni.php');
            exit;
        }
    }
}

// ─── Načtení dat ─────────────────────────────────────────────────────────────
$opravneni = $pdo->query("SELECT * FROM opravneni ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);

// Group by skupina
$groups = [];
foreach ($opravneni as $o) {
    $sk = $o['skupina'] ?: 'Ostatní';
    $groups[$sk][] = $o;
}

$roleLabels = ['trener' => 'Trenér', 'hlavni' => 'Správce', 'admin' => 'Administrátor'];
$roleColors = ['trener' => 'success', 'hlavni' => 'primary', 'admin' => 'danger'];

// Stats
$stats = ['trener' => 0, 'hlavni' => 0, 'admin' => 0];
foreach ($opravneni as $o) {
    $stats[$o['min_role']] = ($stats[$o['min_role']] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nastavení oprávnění</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .role-select-trener  { border-left: 3px solid #198754 !important; }
        .role-select-hlavni  { border-left: 3px solid #0d6efd !important; }
        .role-select-admin   { border-left: 3px solid #dc3545 !important; }
        .group-header { background: #f8f9fa; font-weight: 600; font-size: .9rem; padding: .5rem .75rem; border-bottom: 2px solid #dee2e6; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="mb-0"><i class="bi bi-sliders me-2 text-danger"></i>Nastavení oprávnění</h1>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-3">
        <div class="col-sm-4">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-success"><?= $stats['trener'] ?></div>
                    <div class="text-muted small"><i class="bi bi-person me-1"></i>Trenér (všichni)</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-primary"><?= $stats['hlavni'] ?></div>
                    <div class="text-muted small"><i class="bi bi-key me-1"></i>Správce+</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-danger"><?= $stats['admin'] ?></div>
                    <div class="text-muted small"><i class="bi bi-shield-lock me-1"></i>Admin</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="alert alert-info small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Nastavte minimální roli potřebnou pro přístup ke každé funkci.
        Vyšší role automaticky dědí přístup nižších (Admin vidí vše, Správce vidí vše co Trenér).
        <br><strong>Změny se projeví po příštím přihlášení uživatelů</strong> (pro vás ihned).
    </div>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="card shadow-sm mb-3">
            <div class="card-body p-0">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:30%;">Funkce</th>
                            <th style="width:35%;">Popis</th>
                            <th style="width:20%;">Minimální role</th>
                            <th style="width:15%;" class="text-center">Aktuální</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($groups as $skupina => $items): ?>
                        <tr>
                            <td colspan="4" class="group-header">
                                <i class="bi bi-folder2 me-1"></i><?= h($skupina) ?>
                                <span class="badge bg-secondary fw-normal ms-1"><?= count($items) ?></span>
                            </td>
                        </tr>
                        <?php foreach ($items as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= h($o['nazev']) ?></td>
                            <td class="text-muted small"><?= h($o['popis'] ?? '') ?></td>
                            <td>
                                <select name="min_role[<?= h($o['klic']) ?>]"
                                        class="form-select form-select-sm role-select-<?= h($o['min_role']) ?>"
                                        onchange="this.className='form-select form-select-sm role-select-'+this.value;">
                                    <?php foreach ($roleLabels as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= $o['min_role'] === $val ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $roleColors[$o['min_role']] ?? 'secondary' ?>">
                                    <?= h($roleLabels[$o['min_role']] ?? $o['min_role']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Uložit oprávnění
            </button>
            <button type="submit" name="action" value="reset" class="btn btn-outline-warning"
                    data-confirm="Opravdu resetovat všechna oprávnění na výchozí hodnoty?">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Obnovit výchozí
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
