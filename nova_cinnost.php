<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Nepodporovana metoda.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Neplatny bezpecnostni token.');
    }
    $nazev = trim((string)($_POST['nazev'] ?? ''));
    $delka = floatval($_POST['delka'] ?? 0);
    $poznamka = $_POST['poznamka'] ?? '';
    $datum = date('Y-m-d');
    $trener_id = $_SESSION['trener_id'];

    if ($nazev && $delka > 0) {
        $stmt = $pdo->prepare("INSERT INTO dalsi_cinnosti (trener_id, nazev, delka, poznamka, datum) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$trener_id, $nazev, $delka, $poznamka, $datum]);
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Zadat další činnost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'hlavicka.php'; ?>
<div class="container mt-5">
    <h1>Zadat další činnost</h1>
    <form method="POST" class="card p-4">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="nazev" class="form-label">Název činnosti:</label>
            <input type="text" name="nazev" id="nazev" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="delka" class="form-label">Délka (v hodinách):</label>
            <input type="number" step="0.1" name="delka" id="delka" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="poznamka" class="form-label">Poznámka:</label>
            <textarea name="poznamka" id="poznamka" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-success">Uložit činnost</button>
    </form>
</div>
</body>
</html>
