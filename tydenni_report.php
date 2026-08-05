<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('exporty')) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/report_tyden_lib.php';

$today = date('Y-m-d');
$rng = week_range_from_any_date($today);
$defaultMonday = $rng['start'];

$skupina_id = isset($_GET['skupina_id']) ? (int)$_GET['skupina_id'] : 0;
$podskupina_id = isset($_GET['podskupina_id']) ? (int)$_GET['podskupina_id'] : 0;
$datum = $_GET['datum'] ?? $defaultMonday;

$groups = load_groups($pdo);
$subgroups = $skupina_id ? load_subgroups($pdo, $skupina_id) : [];

$ctx = null;
$error = null;

if ($skupina_id > 0) {
    try {
        $ctx = build_report_context($pdo, $skupina_id, $podskupina_id > 0 ? $podskupina_id : null, $datum);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$saved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_save']) && $skupina_id > 0) {
    try {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Neplatny CSRF token.');
        }
        $ctx = build_report_context($pdo, $skupina_id, $podskupina_id > 0 ? $podskupina_id : null, $datum);
        $saved = save_week_report($pdo, $ctx, __DIR__ . '/reports/weekly');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Týdenní report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?php appUiAssets(); ?>
  <style>.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}</style>
</head>
<body class="bg-light">
<div class="container my-4">

  <h1 class="h3 mb-3">Týdenní report</h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($saved): ?>
    <div class="alert alert-success">
      Uloženo ✅<br>
      <div class="mono small">HTML: <?= h($saved['html']) ?></div>
      <div class="mono small">PDF: <?= h($saved['pdf']) ?></div>
    </div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Skupina</label>
          <select name="skupina_id" class="form-select" onchange="this.form.submit()">
            <option value="0">— vyber skupinu —</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= $skupina_id === (int)$g['id'] ? 'selected' : '' ?>>
                <?= h($g['nazev']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Podskupina (volitelné)</label>
          <select name="podskupina_id" class="form-select" <?= $skupina_id ? '' : 'disabled' ?> onchange="this.form.submit()">
            <option value="0">— všechny podskupiny skupiny —</option>
            <?php foreach ($subgroups as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $podskupina_id === (int)$p['id'] ? 'selected' : '' ?>>
                <?= h($p['nazev']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Týden (vyber datum – vezmeme Po–Ne)</label>
          <input type="date" name="datum" value="<?= h($datum) ?>" class="form-control" onchange="this.form.submit()">
        </div>
      </form>

      <?php if ($ctx): ?>
        <form method="post" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="do_save" value="1">
          <button class="btn btn-primary">Vygenerovat PDF a uložit na server (verze)</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($ctx): ?>
    <div class="card">
      <div class="card-body">
        <?= build_week_report_html($ctx) ?>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-info">Vyber skupinu a datum týdne.</div>
  <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
