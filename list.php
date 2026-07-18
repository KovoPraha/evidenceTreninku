<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

// Načteme všechny skupiny s podskupinami
$sql = "SELECT s.id AS skupina_id, s.nazev AS skupina_nazev, s.hash AS skupina_hash,
               p.id AS podskupina_id, p.nazev AS podskupina_nazev, p.hash AS podskupina_hash
        FROM skupiny s
        LEFT JOIN podskupiny p ON p.skupina_id = s.id
        ORDER BY s.poradi, s.nazev, p.poradi, p.nazev";
$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// seskupíme do pole podle skupin
$groups = [];
foreach ($data as $row) {
    $gid = $row['skupina_id'];
    if (!isset($groups[$gid])) {
        $groups[$gid] = [
            'nazev' => $row['skupina_nazev'],
            'hash'  => $row['skupina_hash'],
            'podskupiny' => []
        ];
    }
    if ($row['podskupina_id']) {
        $groups[$gid]['podskupiny'][] = [
            'nazev' => $row['podskupina_nazev'],
            'hash'  => $row['podskupina_hash']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přehled skupin a podskupin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4">
    <h1>?? Přehled všech skupin a podskupin</h1>

    <?php if (empty($groups)): ?>
        <div class="alert alert-info">Zatím nejsou založeny žádné skupiny.</div>
    <?php else: ?>
        <div class="accordion" id="accordionGroups">
            <?php foreach ($groups as $gid => $g): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?= $gid ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse<?= $gid ?>" aria-expanded="false" aria-controls="collapse<?= $gid ?>">
                            <?= htmlspecialchars($g['nazev']) ?>
                        </button>
                    </h2>
                    <div id="collapse<?= $gid ?>" class="accordion-collapse collapse"
                         aria-labelledby="heading<?= $gid ?>" data-bs-parent="#accordionGroups">
                        <div class="accordion-body">
                            <p>
                                <a href="prehled_skupina.php?hash=<?= urlencode($g['hash']) ?>" class="btn btn-sm btn-outline-primary">
                                    ?? Přehled skupiny
                                </a>
                            </p>
                            <?php if (!empty($g['podskupiny'])): ?>
                                <ul>
                                    <?php foreach ($g['podskupiny'] as $p): ?>
                                        <li>
                                            <?= htmlspecialchars($p['nazev']) ?>
                                            (<a href="prehled_podskupin.php?hash=<?= urlencode($p['hash']) ?>">přehled</a>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <em>Žádné podskupiny</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
