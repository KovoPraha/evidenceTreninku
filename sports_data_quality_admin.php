<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/sports_data_quality.php';

header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function sportsQualityPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $inventory = sportsDataQualityInventory($pdo);
    $loadError = '';
} catch (Throwable $exception) {
    error_log('sports_data_quality_admin.php: ' . $exception->getMessage());
    $inventory = ['generated_at' => '', 'sources' => [], 'unavailable' => [], 'finding_count' => 0, 'total_records' => 0];
    $loadError = 'Přehled kvality sportovních dat se nyní nepodařilo bezpečně načíst.';
}

require_once __DIR__ . '/hlavicka.php';
?>
<main class="container py-4" style="max-width:1300px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clipboard-data me-2 text-primary"></i>Kvalita sportovních dat</h1>
            <div class="text-muted">M3.5a: souhrnná inventura zdrojů, úplnosti, jednotek a ochrany dat.</div>
        </div>
        <div class="text-end"><span class="badge text-bg-<?= (int)$inventory['finding_count'] > 0 ? 'warning' : 'success' ?> fs-6"><?= (int)$inventory['finding_count'] ?> typů zjištění</span><?php if ($inventory['generated_at'] !== ''): ?><div class="small text-muted mt-1">Stav k <?= sportsQualityPageH($inventory['generated_at']) ?></div><?php endif; ?></div>
    </div>

    <div class="alert alert-info"><strong>Přehled je pouze ke čtení a neobsahuje jména ani naměřené hodnoty.</strong> Nic neopravuje, nehodnotí zdravotní stav a nevytváří výkonnostní predikce. Počty mohou upozornit na technický problém, nikoli na kvalitu sportovce.</div>
    <?php if ($loadError !== ''): ?><div class="alert alert-danger"><?= sportsQualityPageH($loadError) ?></div><?php endif; ?>
    <?php if ($inventory['unavailable'] !== []): ?><div class="alert alert-warning"><strong>Nedostupné zdroje:</strong> <?= sportsQualityPageH(implode(', ', $inventory['unavailable'])) ?>. Nedostupnost není vydávána za nulový stav.</div><?php endif; ?>

    <div class="row g-4">
        <?php foreach ($inventory['sources'] as $source):
            $statusClass = ['good' => 'success', 'empty' => 'secondary', 'warning' => 'warning', 'unavailable' => 'danger'][$source['status']] ?? 'secondary';
            $statusLabel = ['good' => 'bez zjištění', 'empty' => 'bez dat', 'warning' => 'vyžaduje návrh', 'unavailable' => 'nedostupné'][$source['status']] ?? $source['status'];
        ?>
            <div class="col-12">
                <section class="card border-0 shadow-sm" id="<?= sportsQualityPageH($source['key']) ?>">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div><strong class="fs-5"><?= sportsQualityPageH($source['label']) ?></strong><div class="small text-muted mt-1"><?= sportsQualityPageH($source['description']) ?></div></div>
                        <span class="badge text-bg-<?= sportsQualityPageH($statusClass) ?>"><?= sportsQualityPageH($statusLabel) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Záznamů zdroje</div><div class="h3 mb-0"><?= (int)$source['record_count'] ?></div></div></div>
                            <?php foreach ($source['metrics'] as $metric): ?><div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted"><?= sportsQualityPageH($metric['label']) ?></div><div class="h3 mb-0"><?= (int)$metric['value'] ?></div><?php if ($metric['detail'] !== ''): ?><div class="small text-muted mt-1"><?= sportsQualityPageH($metric['detail']) ?></div><?php endif; ?></div></div><?php endforeach; ?>
                        </div>
                        <dl class="row small mb-3"><dt class="col-md-2">Citlivost</dt><dd class="col-md-10"><?= sportsQualityPageH($source['classification']) ?></dd><dt class="col-md-2">Přístup</dt><dd class="col-md-10"><?= sportsQualityPageH($source['access']) ?></dd></dl>
                        <?php if (!$source['available']): ?><div class="alert alert-danger mb-0">Zdroj nelze v aktuálním schématu ověřit.</div>
                        <?php elseif ($source['findings'] === []): ?><div class="alert alert-success mb-0">V tomto technickém řezu nebylo nalezeno žádné z definovaných zjištění.</div>
                        <?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Zjištění</th><th class="text-end">Počet</th><th>Význam</th></tr></thead><tbody><?php foreach ($source['findings'] as $finding): ?><tr><td><span class="badge text-bg-<?= sportsQualityPageH($finding['severity']) ?> me-2"><?= sportsQualityPageH($finding['severity'] === 'danger' ? 'blokuje porovnání' : 'návrh') ?></span><strong><?= sportsQualityPageH($finding['label']) ?></strong></td><td class="text-end fw-semibold"><?= (int)$finding['count'] ?></td><td class="small text-muted"><?= sportsQualityPageH($finding['detail']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
