<?php
require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('hlavni')) {
    header('Location: login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$lastImport = $pdo->query("SELECT * FROM kis_import_runs ORDER BY created_at DESC, id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$cards = [
    [
        'label' => 'KIS import',
        'value' => $lastImport ? date('d.m.Y H:i', strtotime($lastImport['created_at'])) : 'nikdy',
        'hint' => $lastImport ? 'poslední stav: ' . $lastImport['status'] : 'nahrajte první import',
        'href' => 'kis_sync_center.php',
        'class' => !$lastImport ? 'border-danger' : 'border-0',
    ],
    [
        'label' => 'Konflikty importu',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM kis_import_matches WHERE match_status IN ('ambiguous','conflict')")->fetchColumn(),
        'hint' => 'řádky vyžadující ruční kontrolu',
        'href' => 'kis_sync_center.php',
        'class' => 'border-0',
    ],
    [
        'label' => 'Bez skupiny',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci s WHERE NOT EXISTS (SELECT 1 FROM sportovec_skupina ss WHERE ss.sportovec_id=s.id)")->fetchColumn(),
        'hint' => 'členové bez zařazení',
        'href' => 'sprava_sportovcu.php?kis=bez_skupiny',
        'class' => 'border-0',
    ],
    [
        'label' => 'Dluh v KIS',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE COALESCE(kis_neuhrazeno,0)>0")->fetchColumn(),
        'hint' => 'neuhrazené částky',
        'href' => 'sprava_sportovcu.php?kis=dluh',
        'class' => 'border-0',
    ],
    [
        'label' => 'Mimo import',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE kis_last_seen_at IS NULL OR kis_last_seen_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->fetchColumn(),
        'hint' => 'neviděni v KIS 90+ dní',
        'href' => 'sprava_sportovcu.php?kis=mimo_import',
        'class' => 'border-0',
    ],
    [
        'label' => 'Ruční stav + KIS aktivní',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM sportovci WHERE COALESCE(stav_manualni,0)=1 AND COALESCE(kis_aktivni,0)=1")->fetchColumn(),
        'hint' => 'zkontrolujte ruční blokace',
        'href' => 'sprava_sportovcu.php?stav=manualni&kis=kis_aktivni',
        'class' => 'border-0',
    ],
    [
        'label' => 'Otevřená období',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM sportovec_obdobi WHERE datum_do IS NULL")->fetchColumn(),
        'hint' => 'kreditní období',
        'href' => 'prehled_kreditu.php',
        'class' => 'border-0',
    ],
    [
        'label' => 'Nepřiřazené soupisky',
        'value' => (int)$pdo->query("SELECT COUNT(*) FROM soupiska_mapping WHERE skupina_id IS NULL AND podskupina_id IS NULL")->fetchColumn(),
        'hint' => 'mapování KIS soupisek',
        'href' => 'sync_evidence.php?step=2',
        'class' => 'border-0',
    ],
];
if (roleAtLeast('admin')) {
    $lastShopRun = $pdo->query(
        "SELECT r.*, (SELECT COUNT(*) FROM shop_catalog_product_candidates p "
        . "WHERE p.run_id=r.id AND p.review_status='pending') AS pending_count "
        . 'FROM shop_catalog_import_runs r ORDER BY r.created_at DESC, r.id DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    $cards[] = [
        'label' => 'E-shop katalog',
        'value' => $lastShopRun ? (int)$lastShopRun['product_count'] . ' produktů' : 'bez importu',
        'hint' => $lastShopRun
            ? (int)$lastShopRun['pending_count'] . ' položek čeká na kontrolu'
            : 'nejprve uložte Shoptet export do stagingu',
        'href' => 'eshop_admin.php' . ($lastShopRun ? '?run_id=' . (int)$lastShopRun['id'] : ''),
        'class' => $lastShopRun && (int)$lastShopRun['pending_count'] === 0 ? 'border-success' : 'border-warning',
    ];
    $approvedAccountRelations = (int)$pdo->query(
        "SELECT COUNT(*) FROM account_person_roles WHERE status='approved' AND valid_to IS NULL"
    )->fetchColumn();
    $accountsWithoutRelation = (int)$pdo->query(
        "SELECT COUNT(*) FROM verejni_uzivatele vu WHERE vu.aktivni=1 AND NOT EXISTS ("
        . "SELECT 1 FROM account_person_roles r WHERE r.account_id=vu.id "
        . "AND r.status='approved' AND r.valid_to IS NULL)"
    )->fetchColumn();
    $pendingIdentityClaims = (int)$pdo->query(
        "SELECT COUNT(*) FROM account_person_claim_requests WHERE status='pending'"
    )->fetchColumn();
    $cards[] = [
        'label' => 'Účty a sportovci',
        'value' => $approvedAccountRelations . ' vazeb',
        'hint' => $pendingIdentityClaims . ' žádostí čeká; '
            . $accountsWithoutRelation . ' aktivních účtů bez schválené osoby',
        'href' => 'eshop_identity_admin.php',
        'class' => $pendingIdentityClaims > 0 ? 'border-warning' : 'border-success',
    ];
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<div class="container py-4" style="max-width:1180px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Administrace: vyžaduje pozornost</h1>
            <div class="text-muted small">Provozní kontrola členů, KIS importů, plateb a skupin.</div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Rozcestník</a>
    </div>
    <div class="row g-3">
        <?php foreach ($cards as $card): ?>
            <div class="col-md-6 col-xl-3">
                <a href="<?= h($card['href']) ?>" class="text-decoration-none text-reset">
                    <div class="card shadow-sm <?= h($card['class']) ?>">
                        <div class="card-body">
                            <div class="text-muted small"><?= h($card['label']) ?></div>
                            <div class="h3 mb-1"><?= h((string)$card['value']) ?></div>
                            <div class="small text-muted"><?= h($card['hint']) ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
