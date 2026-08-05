<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/sports_import_review.php';

header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function sportsImportReviewPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $review = sportsImportReview($pdo);
    $loadError = '';
} catch (Throwable $exception) {
    error_log('sports_import_review_admin.php: ' . $exception->getMessage());
    $review = [
        'generated_at' => '',
        'available' => false,
        'measurements' => ['available' => false, 'ambiguous_rows' => []],
        'races' => ['available' => false, 'ambiguous_rows' => []],
        'legacy_text_table' => ['available' => false, 'total' => 0],
    ];
    $loadError = 'Přípravu importu se nyní nepodařilo bezpečně načíst.';
}

$measurements = $review['measurements'];
$races = $review['races'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Příprava importu sportovních dat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1300px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clipboard-check me-2 text-primary"></i>Příprava importu sportovních dat</h1>
            <div class="text-muted">M3.5d: pokrytí kontraktu v1 a nejednoznačné legacy řádky k ručnímu rozhodnutí. M3.5e: rozpoznávací kontrakt historické textové tabulky měření.</div>
        </div>
        <?php if ($review['generated_at'] !== ''): ?><div class="text-end small text-muted">Stav k <?= sportsImportReviewPageH($review['generated_at']) ?></div><?php endif; ?>
    </div>

    <div class="alert alert-info"><strong>Stránka je pouze ke čtení a nic neimportuje.</strong> Zobrazuje technické hodnoty měření bez jmen a identifikátorů sportovců. Nejednoznačné historické hodnoty se nepřevádějí odhadem; každý řádek níže čeká na ruční rozhodnutí před budoucím jednorázovým importem.</div>
    <?php if ($loadError !== ''): ?><div class="alert alert-danger"><?= sportsImportReviewPageH($loadError) ?></div><?php endif; ?>
    <?php if ($loadError === '' && !$review['available']): ?><div class="alert alert-warning">Databázový kontrakt v1 není v aktuálním schématu úplný. Nejdřív je nutné aplikovat verzovanou migraci M3.5b. Nedostupnost není vydávána za nulový stav.</div><?php endif; ?>

    <?php if ($measurements['available']): ?>
    <section class="card border-0 shadow-sm mb-4" id="mereni-v1">
        <div class="card-header bg-white"><strong class="fs-5">Měření v kontraktu v1</strong><div class="small text-muted">Nové zápisy s výslovnou jednotkou, striktním časem a číselným RPE.</div></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Záznamů celkem</div><div class="h3 mb-0"><?= (int)$measurements['total'] ?></div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">V kontraktu v1</div><div class="h3 mb-0"><?= (int)$measurements['v1_total'] ?></div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Jednotky vzdálenosti v1</div><div class="h5 mb-0"><?= (int)$measurements['v1_units']['m'] ?>× m, <?= (int)$measurements['v1_units']['km'] ?>× km</div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">v1 s časem / s RPE</div><div class="h5 mb-0"><?= (int)$measurements['v1_with_duration'] ?> / <?= (int)$measurements['v1_with_rpe'] ?></div></div></div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4" id="mereni-legacy">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div><strong class="fs-5">Legacy měření k ručnímu rozhodnutí</strong><div class="small text-muted">Řádky bez kontraktu v1 s alespoň jednou hodnotou. Deterministicky převoditelné řádky rozhodnutí nevyžadují.</div></div>
            <span class="badge text-bg-<?= (int)$measurements['ambiguous_count'] > 0 ? 'warning' : 'success' ?>"><?= (int)$measurements['ambiguous_count'] ?> nejednoznačných</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Legacy s hodnotami</div><div class="h4 mb-0"><?= (int)$measurements['legacy_with_values'] ?></div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Deterministicky převoditelné</div><div class="h4 mb-0"><?= (int)$measurements['convertible_count'] ?></div><div class="small text-muted mt-1">Striktní čas nebo číselné RPE bez vzdálenosti; převod počká na jednorázový import.</div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Legacy bez hodnot</div><div class="h4 mb-0"><?= (int)$measurements['legacy_without_values'] ?></div><div class="small text-muted mt-1">Není co normalizovat.</div></div></div>
            </div>
            <?php if ($measurements['truncated']): ?><div class="alert alert-warning">Seznam je zkrácen na prvních <?= count($measurements['ambiguous_rows']) ?> řádků z <?= (int)$measurements['ambiguous_count'] ?>. Zbytek není skryt záměrně: rozhodnutí proběhne po dávkách.</div><?php endif; ?>
            <?php if ($measurements['ambiguous_rows'] === []): ?><div class="alert alert-success mb-0">Žádný legacy řádek měření nečeká na ruční rozhodnutí.</div>
            <?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Řádek</th><th>Typ</th><th>Kontext</th><th>Pole a důvody</th></tr></thead><tbody>
                <?php foreach ($measurements['ambiguous_rows'] as $row): ?>
                <tr>
                    <td class="fw-semibold">#<?= (int)$row['id'] ?></td>
                    <td><?= sportsImportReviewPageH($row['typ']) ?></td>
                    <td class="small text-muted"><?= sportsImportReviewPageH($row['kontext']) ?></td>
                    <td><?php foreach ($row['fields'] as $field): ?><div class="mb-1"><span class="badge text-bg-<?= $field['verdict'] === 'ambiguous' ? 'warning' : 'success' ?> me-2"><?= sportsImportReviewPageH($field['field']) ?></span><code><?= sportsImportReviewPageH($field['value']) ?></code> <span class="small text-muted"><?= sportsImportReviewPageH($field['reason']) ?></span></div><?php endforeach; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($races['available']): ?>
    <section class="card border-0 shadow-sm mb-4" id="zavody-v1">
        <div class="card-header bg-white"><strong class="fs-5">Výsledky závodů v kontraktu v1</strong><div class="small text-muted">Stav rozlišuje entered, finished, DNS, DNF a DSQ.</div></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Účastí celkem</div><div class="h3 mb-0"><?= (int)$races['total'] ?></div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">V kontraktu v1</div><div class="h3 mb-0"><?= (int)$races['v1_total'] ?></div></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Stavy v1</div><div class="h6 mb-0"><?php foreach ($races['v1_statuses'] as $status => $count): ?><span class="me-3"><?= sportsImportReviewPageH($status) ?>: <?= (int)$count ?></span><?php endforeach; ?></div></div></div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4" id="zavody-legacy">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div><strong class="fs-5">Legacy účasti k ručnímu rozhodnutí</strong><div class="small text-muted">Každá historická účast bez kontraktu v1 potřebuje výslovný stav; stav se neodhaduje ani při vyplněném pořadí.</div></div>
            <span class="badge text-bg-<?= (int)$races['ambiguous_count'] > 0 ? 'warning' : 'success' ?>"><?= (int)$races['ambiguous_count'] ?> k rozhodnutí</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Legacy s výsledkem</div><div class="h4 mb-0"><?= (int)$races['legacy_with_result'] ?></div></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Legacy bez výsledku</div><div class="h4 mb-0"><?= (int)$races['legacy_without_result'] ?></div><div class="small text-muted mt-1">Může jít o startovní listinu, DNS/DNF nebo nedoplněný výsledek.</div></div></div>
            </div>
            <?php if ($races['truncated']): ?><div class="alert alert-warning">Seznam je zkrácen na prvních <?= count($races['ambiguous_rows']) ?> řádků z <?= (int)$races['ambiguous_count'] ?>. Zbytek není skryt záměrně: rozhodnutí proběhne po dávkách.</div><?php endif; ?>
            <?php if ($races['ambiguous_rows'] === []): ?><div class="alert alert-success mb-0">Žádná legacy účast nečeká na ruční rozhodnutí.</div>
            <?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Řádek</th><th>Kontext</th><th>Pole a důvody</th></tr></thead><tbody>
                <?php foreach ($races['ambiguous_rows'] as $row): ?>
                <tr>
                    <td class="fw-semibold">ř. <?= (int)$row['radek'] ?></td>
                    <td class="small text-muted"><?= sportsImportReviewPageH($row['kontext']) ?></td>
                    <td><?php foreach ($row['fields'] as $field): ?><div class="mb-1"><span class="badge text-bg-<?= $field['verdict'] === 'ambiguous' ? 'warning' : 'success' ?> me-2"><?= sportsImportReviewPageH($field['field']) ?></span><?php if ($field['value'] !== ''): ?><code><?= sportsImportReviewPageH($field['value']) ?></code> <?php endif; ?><span class="small text-muted"><?= sportsImportReviewPageH($field['reason']) ?></span></div><?php endforeach; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($review['legacy_text_table']['available']): ?>
    <section class="card border-0 shadow-sm mb-4" id="legacy-text">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div><strong class="fs-5">Historická textová tabulka měření</strong><div class="small text-muted">M3.5e: deterministický rozpoznávací kontrakt volného textu vzdálenosti a času. Nic se nepřevádí, jen se klasifikuje pro ruční rozhodnutí.</div></div>
            <span class="badge text-bg-<?= (int)$review['legacy_text_table']['ambiguous_count'] > 0 ? 'warning' : 'success' ?>"><?= (int)$review['legacy_text_table']['ambiguous_count'] ?> nejednoznačných</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Řádků celkem</div><div class="h4 mb-0"><?= (int)$review['legacy_text_table']['total'] ?></div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Rozpoznatelné</div><div class="h4 mb-0"><?= (int)$review['legacy_text_table']['recognized_count'] ?></div><div class="small text-muted mt-1">Vzdálenost i čas odpovídají uznanému vzoru „&lt;číslo&gt; km/m/min“ nebo striktnímu času.</div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Nejednoznačné</div><div class="h4 mb-0"><?= (int)$review['legacy_text_table']['ambiguous_count'] ?></div><div class="small text-muted mt-1">Čekají na ruční rozhodnutí; žádný odhad se neprovádí.</div></div></div>
            </div>
            <p class="text-muted small">Před případným převodem je nutné nejprve schválit formát, jednotky a pravidla pro nejednoznačné hodnoty (viz inventura M3.5a). Tento kontrakt pouze rozpoznává vzor v každém řádku níže, nic nepřevádí.</p>
            <?php if ($review['legacy_text_table']['truncated']): ?><div class="alert alert-warning">Seznam je zkrácen na prvních <?= count($review['legacy_text_table']['rows']) ?> řádků z <?= (int)$review['legacy_text_table']['total'] ?>. Zbytek není skryt záměrně: rozhodnutí proběhne po dávkách.</div><?php endif; ?>
            <?php if ($review['legacy_text_table']['rows'] === []): ?><div class="alert alert-secondary mb-0">Historická tabulka měření je prázdná.</div>
            <?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Řádek</th><th>Kontext</th><th>Verdikt</th><th>Pole a důvody</th></tr></thead><tbody>
                <?php foreach ($review['legacy_text_table']['rows'] as $row): ?>
                <tr>
                    <td class="fw-semibold">#<?= (int)$row['id'] ?></td>
                    <td class="small text-muted"><?= sportsImportReviewPageH($row['kontext']) ?></td>
                    <td><span class="badge text-bg-<?= $row['verdict'] === 'ambiguous' ? 'warning' : 'success' ?>"><?= $row['verdict'] === 'ambiguous' ? 'Nejednoznačné' : 'Rozpoznatelné' ?></span></td>
                    <td><?php foreach ($row['fields'] as $field): ?><div class="mb-1"><span class="badge text-bg-<?= $field['verdict'] === 'ambiguous' ? 'warning' : 'success' ?> me-2"><?= sportsImportReviewPageH($field['field']) ?></span><?php if ($field['value'] !== ''): ?><code><?= sportsImportReviewPageH($field['value']) ?></code> <?php endif; ?><span class="small text-muted"><?= sportsImportReviewPageH($field['reason']) ?></span></div><?php endforeach; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
