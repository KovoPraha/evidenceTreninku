<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/password_security.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header("Location: login.php");
    exit;
}

// Zpracování POST požadavků
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header("Location: sprava_treneru.php");
        exit;
    }

    $akce = $_POST['akce'] ?? '';

    // Přidání / úprava trenéra
    if ($akce === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $jmeno = trim($_POST['jmeno'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $heslo = $_POST['heslo'] ?? '';
        $role  = $_POST['role'] ?? 'trener';

        if (!$jmeno || !$email) {
            $_SESSION['flash_error'] = 'Jméno a email jsou povinné.';
            header("Location: sprava_treneru.php");
            exit;
        }

        if ($id > 0) {
            // Úprava existujícího
            if ($heslo !== '') {
                $stmt = $pdo->prepare(
                    "UPDATE treneri SET jmeno = ?, email = ?, heslo = ?, role = ?, "
                    . "session_version = session_version + 1 WHERE id = ?"
                );
                $stmt->execute([$jmeno, $email, trainer_password_hash($heslo), $role, $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE treneri SET jmeno = ?, email = ?, "
                    . "session_version = session_version + CASE WHEN role <> ? THEN 1 ELSE 0 END, "
                    . "role = ? WHERE id = ?"
                );
                $stmt->execute([$jmeno, $email, $role, $role, $id]);
            }
            $_SESSION['flash_success'] = 'Trenér aktualizován.';
        } else {
            // Nový trenér
            if (!$heslo) {
                $_SESSION['flash_error'] = 'Heslo je povinné pro nového trenéra.';
                header("Location: sprava_treneru.php");
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO treneri (jmeno, email, heslo, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$jmeno, $email, trainer_password_hash($heslo), $role]);
            $_SESSION['flash_success'] = 'Trenér přidán.';
        }
        header("Location: sprava_treneru.php");
        exit;
    }

    // Smazání trenéra
    if ($akce === 'delete') {
        $del_id = (int)($_POST['delete_id'] ?? 0);
        if ($del_id > 0 && $del_id !== $_SESSION['trener_id']) {
            $pdo->prepare("DELETE FROM treneri WHERE id = ?")->execute([$del_id]);
            $_SESSION['flash_success'] = 'Trenér smazán.';
        } else {
            $_SESSION['flash_error'] = 'Nelze smazat sám sebe.';
        }
        header("Location: sprava_treneru.php");
        exit;
    }
}

// Načtení trenérů
$trenery = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM trenink_trener tt WHERE tt.trener_id = t.id) AS pocet_treninku
    FROM treneri t
    ORDER BY t.role DESC, t.jmeno
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Správa trenérů – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-person-gear fs-2 text-secondary"></i>
    <h1 class="h3 mb-0">Správa trenérů</h1>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <!-- Seznam trenérů -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      <i class="bi bi-people me-2"></i>Seznam trenérů
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Jméno</th>
            <th>Email</th>
            <th>Role</th>
            <th>Tréninky</th>
            <th>Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($trenery as $tr): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($tr['jmeno']) ?></strong>
                <?php if ($tr['id'] == $_SESSION['trener_id']): ?>
                  <span class="badge bg-info ms-1">Vy</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($tr['email']) ?></td>
              <td>
                <?php if ($tr['role'] === 'admin'): ?>
                  <span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i>Administrátor</span>
                <?php elseif ($tr['role'] === 'hlavni'): ?>
                  <span class="badge bg-primary"><i class="bi bi-key me-1"></i>Správce</span>
                <?php else: ?>
                  <span class="badge bg-success"><i class="bi bi-person me-1"></i>Trenér</span>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-light text-dark"><?= (int)$tr['pocet_treninku'] ?></span></td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-outline-primary edit-btn"
                          data-id="<?= $tr['id'] ?>"
                          data-jmeno="<?= htmlspecialchars($tr['jmeno'], ENT_QUOTES) ?>"
                          data-email="<?= htmlspecialchars($tr['email'], ENT_QUOTES) ?>"
                          data-role="<?= $tr['role'] ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?php if ($tr['id'] != $_SESSION['trener_id']): ?>
                    <form method="POST" class="m-0" data-confirm="Opravdu smazat trenéra?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="akce" value="delete">
                      <input type="hidden" name="delete_id" value="<?= $tr['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Formulář -->
  <div class="card shadow-sm">
    <div class="card-header">
      <i class="bi bi-plus-lg me-2"></i><span id="form-title">Přidat trenéra</span>
    </div>
    <div class="card-body">
      <form method="POST" id="trener-form">
        <?= csrf_field() ?>
        <input type="hidden" name="akce" value="save">
        <input type="hidden" name="id" id="trener-id" value="0">

        <div class="row g-3">
          <div class="col-md-4">
            <label for="trener-jmeno" class="form-label fw-semibold">
              <i class="bi bi-person me-1"></i>Jméno
            </label>
            <input type="text" name="jmeno" id="trener-jmeno" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label for="trener-email" class="form-label fw-semibold">
              <i class="bi bi-envelope me-1"></i>Email
            </label>
            <input type="email" name="email" id="trener-email" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label for="trener-heslo" class="form-label fw-semibold">
              <i class="bi bi-key me-1"></i>Heslo
            </label>
            <input type="password" name="heslo" id="trener-heslo" class="form-control" placeholder="Nové heslo">
            <div class="form-text" id="heslo-hint">Povinné pro nového trenéra</div>
          </div>
          <div class="col-md-2">
            <label for="trener-role" class="form-label fw-semibold">
              <i class="bi bi-shield me-1"></i>Role
            </label>
            <select name="role" id="trener-role" class="form-select">
              <option value="trener">Trenér</option>
              <option value="hlavni">Správce</option>
              <option value="admin">Administrátor</option>
            </select>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Uložit
          </button>
          <button type="button" id="form-reset" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Zrušit
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('form-title').innerText = 'Upravit trenéra #' + btn.dataset.id;
        document.getElementById('trener-id').value    = btn.dataset.id;
        document.getElementById('trener-jmeno').value = btn.dataset.jmeno;
        document.getElementById('trener-email').value = btn.dataset.email;
        document.getElementById('trener-role').value  = btn.dataset.role;
        document.getElementById('trener-heslo').value = '';
        document.getElementById('trener-heslo').removeAttribute('required');
        document.getElementById('heslo-hint').innerText = 'Prázdné = beze změny';
        document.getElementById('trener-form').scrollIntoView({ behavior: 'smooth' });
    });
});

document.getElementById('form-reset').addEventListener('click', () => {
    document.getElementById('form-title').innerText = 'Přidat trenéra';
    document.getElementById('trener-id').value    = 0;
    document.getElementById('trener-jmeno').value = '';
    document.getElementById('trener-email').value = '';
    document.getElementById('trener-role').value  = 'trener';
    document.getElementById('trener-heslo').value = '';
    document.getElementById('heslo-hint').innerText = 'Povinné pro nového trenéra';
});
</script>
</body>
</html>
