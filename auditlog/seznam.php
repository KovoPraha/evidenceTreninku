<?php
// Autorizace PŘED jakýmkoli výstupem — jinak nepřihlášený/neoprávněný vidí celou stránku
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: ../login.php'); exit; }
if (!canAccess('auditlog')) { header('Location: ../index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Tréninková evidence – úvod</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .feature-icon { font-size: 1.5rem; vertical-align: middle; margin-right: .5rem; }
    .section-title { margin-top: 2rem; margin-bottom: .5rem; font-weight: 500; color: #444; }
    .list-group-item-action:hover { background-color: #f8f9fa; }
  </style>
</head>
<body class="bg-light">

<?php
require_once '../hlavicka.php';

// zpracování filtrů
$filter_trener = $_GET['trener_id'] ?? '';
$filter_akce = $_GET['akce'] ?? '';

// výběr trenérů a typů akcí pro select boxy
$trenery = $pdo->query("
    SELECT DISTINCT l.uzivatel_id AS trener_id, t.jmeno
    FROM ucto_audit_log l
    LEFT JOIN treneri t ON l.uzivatel_id = t.id 
    WHERE t.id IS NOT NULL
    ORDER BY t.jmeno
")->fetchAll(PDO::FETCH_ASSOC);

$akce_typy = $pdo->query("SELECT DISTINCT akce FROM ucto_audit_log ORDER BY akce")->fetchAll(PDO::FETCH_COLUMN);

// dynamický dotaz
$where = [];
$params = [];

if ($filter_trener !== '') {
    $where[] = 'l.uzivatel_id = ?';
    $params[] = $filter_trener;
}
if ($filter_akce !== '') {
    $where[] = 'l.akce = ?';
    $params[] = $filter_akce;
}

$sql = "SELECT l.*, t.jmeno
        FROM ucto_audit_log l
        LEFT JOIN treneri t ON l.uzivatel_id = t.id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY l.datum DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logy = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-5">
<h1>🕵️ Audit log</h1>

<form method="get" class="row g-3 mb-4">
    <div class="col-md-4">
        <label class="form-label">Trenér</label>
        <select name="trener_id" class="form-select">
            <option value="">-- všichni --</option>
            <?php foreach ($trenery as $t): ?>
                <option value="<?= $t['trener_id'] ?>" <?= $filter_trener == $t['trener_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['jmeno']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Typ akce</label>
        <select name="akce" class="form-select">
            <option value="">-- všechny --</option>
            <?php foreach ($akce_typy as $akce): ?>
                <option value="<?= htmlspecialchars($akce) ?>" <?= $filter_akce == $akce ? 'selected' : '' ?>>
                    <?= htmlspecialchars($akce) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">🔍 Filtrovat</button>
        <a href="seznam.php" class="btn btn-secondary">❌ Zrušit filtr</a>
    </div>
</form>

<div class="table-responsive">
<table class="table table-sm table-bordered table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>Čas</th>
      <th>Trenér</th>
      <th>Akce</th>
      <th>Entita</th>
      <th>ID</th>
      <th>Data</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($logy as $z): ?>
      <tr>
        <td><?= date('d.m.Y H:i:s', strtotime((string)$z['datum'])) ?></td>
        <td><?= htmlspecialchars((string)($z['jmeno'] ?? ('#' . $z['uzivatel_id']))) ?></td>
        <td><?= htmlspecialchars($z['akce']) ?></td>
        <td><?= htmlspecialchars((string)$z['tabulka']) ?></td>
        <td><?= (int)$z['zaznam_id'] ?></td>
        <td><pre class="mb-0 text-wrap" style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= htmlspecialchars((string)$z['detail']) ?></pre></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
