<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';

// Načteme seznam sportovců pro výběr v select (vše kromě archivu)
$sportovci = $pdo->query("
    SELECT id, jmeno, prijmeni
    FROM sportovci
    WHERE id NOT IN (
        SELECT ss.sportovec_id FROM sportovec_skupina ss
        JOIN skupiny sk ON ss.skupina_id = sk.id
        WHERE LOWER(sk.nazev) = 'archiv'
    )
    ORDER BY prijmeni, jmeno
")->fetchAll(PDO::FETCH_ASSOC);

$sportovecId = isset($_GET['sportovec_id']) ? (int)$_GET['sportovec_id'] : 0;
$vybranySportovec = null;

if ($sportovecId > 0) {
    $stmt = $pdo->prepare("SELECT id, jmeno, prijmeni FROM sportovci WHERE id = ?");
    $stmt->execute([$sportovecId]);
    $vybranySportovec = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Zátěžový test – nový záznam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1>Zátěžový test – nový záznam</h1>

    <form id="zatezovyTestForm" action="ulozit_zatezovy_test.php" method="post" enctype="multipart/form-data" class="card p-4 shadow-sm mt-3">
        <?= csrf_field() ?>

        <!-- Sportovec -->
        <div class="mb-3">
            <label class="form-label req">Sportovec</label>
            <?php if ($vybranySportovec): ?>
                <input type="hidden" name="sportovec_id" value="<?= (int)$vybranySportovec['id'] ?>">
                <input type="text" class="form-control" value="<?= htmlspecialchars($vybranySportovec['jmeno'] . ' ' . $vybranySportovec['prijmeni']) ?>" disabled>
            <?php else: ?>
                <select name="sportovec_id" class="form-select" required>
                    <option value="">-- Vyberte sportovce --</option>
                    <?php foreach ($sportovci as $sp): ?>
                        <option value="<?= (int)$sp['id'] ?>">
                            <?php echo htmlspecialchars($sp['jmeno'] . ' ' . $sp['prijmeni']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <!-- Datum -->
        <div class="mb-3">
            <label for="datum" class="form-label req">Datum testu</label>
            <input type="date" id="datum" name="datum" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <!-- Základní parametry -->
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label" for="vek">Věk (roky)</label>
                <input type="number" id="vek" name="vek" class="form-control" min="0" max="120">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vaha_kg">Váha (kg)</label>
                <input type="number" id="vaha_kg" name="vaha_kg" class="form-control" step="0.1" min="0">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vyska_cm">Výška (cm)</label>
                <input type="number" id="vyska_cm" name="vyska_cm" class="form-control" step="0.1" min="0">
            </div>
        </div>

        <!-- Interní popis -->
        <div class="mb-3">
            <label for="popis_interni" class="form-label">Interní popis (pro trenéry)</label>
            <textarea id="popis_interni" name="popis_interni" rows="4" class="form-control"></textarea>
        </div>

        <!-- Popis pro sportovce -->
        <div class="mb-3">
            <label for="popis_sportovec" class="form-label">Popis pro sportovce (lidsky, srozumitelně)</label>
            <textarea id="popis_sportovec" name="popis_sportovec" rows="4" class="form-control"></textarea>
        </div>

        <!-- Soubory pro sportovce (veřejné obrázky) -->
        <div class="mb-3">
            <label class="form-label">Obrázky pro sportovce (JPG/PNG/WebP)</label>
            <input type="file" name="public_img[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
            <div class="form-text">
                Tyto obrázky se zobrazí na kartě sportovce (veřejnější pohled).
            </div>
        </div>

        <!-- Soubory pro interní zobrazení (obrázky) -->
        <div class="mb-3">
            <label class="form-label">Interní obrázky (JPG/PNG/WebP)</label>
            <input type="file" name="internal_img[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
            <div class="form-text">
                Jen pro trenéry – např. screenshoty z testovacího SW, grafy s citlivějšími daty.
            </div>
        </div>

        <!-- Ostatní soubory -->
        <div class="mb-3">
            <label class="form-label">Další soubory (libovolný typ – .xls, .fit, .pdf, ...)</label>
            <input type="file" name="other_files[]" class="form-control" multiple>
            <div class="form-text">
                Bez omezení typu – výsledkové soubory, exporty z ergometru, PDF reporty atd.
            </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 Uložit zátěžový test</button>
        <a href="prehled_sportovcu.php" class="btn btn-secondary">Zpět na seznam sportovců</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Varování na neuložené změny + Bootstrap validace
(() => {
    const form = document.getElementById('zatezovyTestForm');
    if (!form) return;
    let dirty = false, submitting = false;
    form.addEventListener('input',  () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => {
        submitting = true;
        form.classList.add('was-validated');
    });
    window.addEventListener('beforeunload', e => {
        if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
</body>
</html>
