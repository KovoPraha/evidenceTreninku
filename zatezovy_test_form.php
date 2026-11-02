<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/sportovec_search.php';

$sportovecId = isset($_GET['sportovec_id']) ? (int)$_GET['sportovec_id'] : 0;
$vybranySportovec = null;

if ($sportovecId > 0) {
    coachScopeRequirePerson($pdo, $sportovecId);
    $stmt = $pdo->prepare("SELECT id, jmeno, prijmeni, uci AS uciid, narozeni FROM sportovci WHERE id = ?");
    $stmt->execute([$sportovecId]);
    $vybranySportovec = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Zátěžový test – nový záznam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1>Zátěžový test – nový záznam</h1>

    <?php if (staffActivePositionIs('coach')): ?>
        <div class="alert alert-info mt-3">
            V pozici Trenér vybíráte sportovce z přidělených skupin a soupisek.
            <?php if (staffCanUsePosition('sports_lead')): ?>
                Pro výběr z celé databáze přepněte v horním menu pracovní pozici na <strong>Vedoucí sportu</strong> a otevřete Zátěžový test.
            <?php else: ?>
                Pokud vám sportovec chybí, požádejte vedoucího sportu o přidělení skupiny nebo soupisky.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form id="zatezovyTestForm" action="ulozit_zatezovy_test.php" method="post" enctype="multipart/form-data" class="card p-4 shadow-sm mt-3">
        <?= csrf_field() ?>

        <!-- Sportovec -->
        <div class="mb-3" id="stress-athlete-picker">
            <label for="stress-athlete-search" class="form-label req">Sportovec</label>
            <input type="hidden" name="sportovec_id" id="stress-athlete-id" value="<?= $vybranySportovec ? (int)$vybranySportovec['id'] : '' ?>">
            <input type="text" class="form-control" id="stress-athlete-search" required maxlength="200"
                   value="<?= $vybranySportovec ? htmlspecialchars(sportovecSearchLabel($vybranySportovec), ENT_QUOTES, 'UTF-8') : '' ?>"
                   placeholder="Začněte psát jméno, příjmení nebo UCI ID…" autocomplete="off"
                   role="combobox" aria-autocomplete="list" aria-expanded="false"
                   aria-controls="stress-athlete-results" aria-describedby="stress-athlete-status">
            <div id="stress-athlete-results" class="list-group mt-1 overflow-auto" role="listbox" aria-label="Nalezení sportovci" style="max-height: 18rem" hidden></div>
            <div class="form-text" id="stress-athlete-status" role="status" aria-live="polite">
                <?= $vybranySportovec ? 'Sportovec je vybrán. Pro změnu upravte hledaný text.' : 'Klikněte pro nabídku sportovců nebo napište alespoň 2 znaky. Poté vyberte konkrétního sportovce.' ?>
            </div>
            <noscript><div class="text-danger">Pro vyhledání a výběr sportovce zapněte JavaScript.</div></noscript>
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

        <button type="submit" class="btn btn-success">💾 Uložit zátěžový test</button>
        <a href="<?= staffActivePositionIs('coach') ? 'prehled_sportovcu.php' : 'pracovni_pozice.php' ?>" class="btn btn-secondary">Zpět</a>
    </form>
</div>


<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>" src="assets/js/stress-athlete-picker.js"></script>
<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>">
// Varování na neuložené změny + Bootstrap validace
(() => {
    const form = document.getElementById('zatezovyTestForm');
    if (!form) return;
    let dirty = false, submitting = false;
    form.addEventListener('input',  () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', e => {
        if (e.defaultPrevented) return;
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
