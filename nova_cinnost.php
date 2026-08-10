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

$errors = [];
$nazev = trim((string)($_POST['nazev'] ?? ''));
$delkaInput = trim((string)($_POST['delka'] ?? ''));
$poznamka = trim((string)($_POST['poznamka'] ?? ''));
$datum = (string)($_POST['datum'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.');
    }
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if ($nazev === '') {
        $errors[] = 'Doplňte název činnosti.';
    }
    if (!is_numeric($delkaInput) || (float)$delkaInput <= 0) {
        $errors[] = 'Délka musí být číslo větší než nula.';
    }
    if (!$dateObject || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $dateObject->format('Y-m-d') !== $datum) {
        $errors[] = 'Vyberte platné datum činnosti.';
    }
    $delka = (float)$delkaInput;
    $trener_id = $_SESSION['trener_id'];

    if ($errors === []) {
        $stmt = $pdo->prepare("INSERT INTO dalsi_cinnosti (trener_id, nazev, delka, poznamka, datum) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$trener_id, $nazev, $delka, $poznamka, $datum]);
        $_SESSION['flash_success'] = 'Činnost byla uložena a je zahrnuta ve výkazu.';
        header('Location: vypis_vykazu.php?mesic=' . rawurlencode(substr($datum, 0, 7)), true, 303);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zadat další činnost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<main class="container py-4" style="max-width:760px">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h3 mb-1">Zadat další činnost</h1><p class="text-muted mb-0">Zapište práci mimo tréninky; uložený čas se objeví ve výkazu zvoleného měsíce.</p></div><a href="vypis_vykazu.php" class="btn btn-outline-secondary btn-sm">Zpět na výkaz</a></div>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><strong>Činnost se nepodařilo uložit.</strong><ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="POST" class="card border-0 shadow-sm p-4">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="datum" class="form-label">Datum</label>
            <input type="date" name="datum" id="datum" class="form-control" value="<?= htmlspecialchars($datum, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>
        <div class="mb-3">
            <label for="nazev" class="form-label">Název činnosti</label>
            <input type="text" name="nazev" id="nazev" class="form-control" value="<?= htmlspecialchars($nazev, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" maxlength="255" required>
        </div>
        <div class="mb-3">
            <label for="delka" class="form-label">Délka v hodinách</label>
            <input type="number" step="0.1" min="0.1" name="delka" id="delka" class="form-control" value="<?= htmlspecialchars($delkaInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
            <div class="form-text">Například 1,5 hodiny zadejte jako 1.5.</div>
        </div>
        <div class="mb-3">
            <label for="poznamka" class="form-label">Poznámka <span class="text-muted fw-normal">(nepovinná)</span></label>
            <textarea name="poznamka" id="poznamka" class="form-control" rows="3"><?= htmlspecialchars($poznamka, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">Uložit činnost</button>
    </form>
</main>
</body>
</html>
