<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_operational_overview.php';

header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function operationalPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $overview = adminOperationalOverview($pdo);
    $loadError = '';
} catch (Throwable $exception) {
    error_log('provozni_prehled_admin.php: ' . $exception->getMessage());
    $overview = ['generated_at' => '', 'sections' => [], 'unavailable' => [], 'signal_count' => 0];
    $loadError = 'Provozní přehled se nyní nepodařilo bezpečně načíst.';
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Provozní přehled správce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1250px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-speedometer2 me-2 text-primary"></i>Provozní přehled správce</h1>
            <div class="text-muted">Jedno místo pro signály vyžadující kontrolu. Změny se provádějí až v navazujících auditovaných obrazovkách.</div>
        </div>
        <div class="text-end"><span class="badge text-bg-<?= (int)$overview['signal_count'] > 0 ? 'warning' : 'success' ?> fs-6"><?= (int)$overview['signal_count'] ?> signálů</span><?php if ($overview['generated_at'] !== ''): ?><div class="small text-muted mt-1">Stav k <?= operationalPageH($overview['generated_at']) ?></div><?php endif; ?></div>
    </div>

    <div class="alert alert-info"><strong>Přehled je pouze ke čtení.</strong> Nic nepotvrzuje, neodesílá ani nemění. Po otevření cílové obrazovky stále platí její vlastní oprávnění, potvrzení, CSRF ochrana a audit.</div>
    <?php if ($loadError !== ''): ?><div class="alert alert-danger"><?= operationalPageH($loadError) ?></div><?php endif; ?>
    <?php if ($overview['unavailable'] !== []): ?><div class="alert alert-warning small"><strong>Neúplný přehled:</strong> nelze ověřit zdroje <?= operationalPageH(implode(', ', $overview['unavailable'])) ?>. Nejsou proto vydávány za nulový stav.</div><?php endif; ?>

    <div class="row g-4">
        <?php foreach ($overview['sections'] as $section):
            $sectionCount = array_sum(array_map(static fn (array $item): int => (int)$item['count'], $section['items']));
        ?>
            <div class="col-lg-6">
                <section class="card border-0 shadow-sm h-100" id="<?= operationalPageH($section['key']) ?>">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                        <strong><i class="bi bi-<?= operationalPageH($section['icon']) ?> me-2 text-primary"></i><?= operationalPageH($section['label']) ?></strong>
                        <span class="badge text-bg-<?= $sectionCount > 0 ? 'warning' : 'success' ?>"><?= $sectionCount ?></span>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted"><?= operationalPageH($section['description']) ?></p>
                        <?php if ($section['items'] === []): ?>
                            <div class="alert alert-success mb-0 py-2"><i class="bi bi-check-circle me-2"></i>V dostupných datech není žádná položka vyžadující zásah.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($section['items'] as $item): ?>
                                    <a class="list-group-item list-group-item-action px-0" href="<?= operationalPageH($item['href']) ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div><strong><?= operationalPageH($item['title']) ?></strong><div class="small text-muted mt-1"><?= operationalPageH($item['detail']) ?></div></div>
                                            <span class="badge text-bg-<?= operationalPageH($item['severity']) ?> rounded-pill"><?= (int)$item['count'] ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
