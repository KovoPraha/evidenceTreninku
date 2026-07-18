<?php
// prehled_skupin.php
require_once 'db.php';

// Překlad dnů
$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

// Načtení všech skupin a jejich hash
$skupiny = $pdo->query("SELECT id, nazev, hash FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);

// Načtení podskupin pro každou skupinu
$podskupinyStmt = $pdo->prepare("SELECT id, nazev, hash FROM podskupiny WHERE skupina_id = :skupina_id ORDER BY poradi, nazev");

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přehled skupin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-5">
    <h1>Přehled skupin</h1>
    <div class="accordion" id="accordionSkupiny">
        <?php foreach ($skupiny as $index => $sk): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?= $index ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false" aria-controls="collapse<?= $index ?>">
                    <?= htmlspecialchars($sk['nazev']) ?>
                </button>
            </h2>
            <div id="collapse<?= $index ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#accordionSkupiny">
                <div class="accordion-body">
                    <!-- Odkaz na přehled celé skupiny -->
                    <a href="prehled_skupina.php?hash=<?= urlencode($sk['hash']) ?>" class="btn btn-sm btn-secondary mb-3">Přehled skupiny</a>
                    <?php
                    $podskupinyStmt->execute([':skupina_id' => $sk['id']]);
                    $podList = $podskupinyStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($podList)): ?>
                        <p><em>Žádné podskupiny</em></p>
                    <?php else: ?>
                        <ul class="list-group">
                        <?php foreach ($podList as $ps): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($ps['nazev']) ?>
                                <a href="prehled_podskupin.php?hash=<?= urlencode($ps['hash']) ?>" class="btn btn-sm btn-primary">Zobrazit</a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
