<?php
/**
 * AJAX endpoint – seznam tréninků pro veřejný profil sportovce
 * Params: rok (0 = vše), profile_page; view=measurements and exact typ filter.
 */
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/public_profile_token.php';
require_once __DIR__ . '/includes/sports_measurement_contract.php';
require_once __DIR__ . '/includes/sportovec_note_access.php';
require_once __DIR__ . '/includes/athlete_profile_read.php';
header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtDate(?string $d): string
{
    if (!$d || $d === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($d);
    return $ts ? date('d.m.Y', $ts) : h($d);
}

function renderMereniData(array $mz): string
{
    $typ   = $mz['typ'] ?? '';
    $parts = [];

    if ($typ === 'kolo' || $typ === 'beh') {
        if (!empty($mz['vzdalenost']) && $mz['vzdalenost'] !== '0') {
            $parts[] = '<strong>' . h($mz['vzdalenost']) . '</strong> ' . h(sportsMeasurementDisplayUnit($mz['distance_unit'] ?? null));
        }
        if (!empty($mz['cas'])) {
            $parts[] = '⏱ ' . h($mz['cas']);
        }
        if (!empty($mz['prevod']) && $typ === 'kolo') {
            $parts[] = 'převod ' . h($mz['prevod']);
        }
    } elseif ($typ === 'posilovna') {
        $cvik = $mz['cvik_nazev'] ?: ($mz['cvik_id'] ? '#' . (int)$mz['cvik_id'] : '—');
        $parts[] = '<strong>' . h($cvik) . '</strong>';
        if (!empty($mz['vaha']) && $mz['vaha'] !== '0') {
            $parts[] = h($mz['vaha']) . ' kg';
        }
        if (!empty($mz['opakovani'])) {
            $parts[] = (int)$mz['opakovani'] . '×';
        }
        if (!empty($mz['rpe'])) {
            $parts[] = 'RPE ' . h($mz['rpe']);
        }
    } elseif ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
        $segment = $mz['segment_nazev'] ?? ($mz['segment_id'] ? '#' . (int)$mz['segment_id'] : '—');
        $parts[] = '<strong>' . h($segment) . '</strong>';
        if (!empty($mz['cas'])) {
            $parts[] = '⏱ ' . h($mz['cas']);
        }
    } else {
        return '<span class="text-muted">—</span>';
    }

    return implode(' · ', $parts) ?: '<span class="text-muted">—</span>';
}

// --- auth: short-lived server-side profile session ---
$sportovec_id = public_profile_access_session_athlete_id($pdo) ?? 0;
if ($sportovec_id < 1) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Neplatný přístup.</div>';
    exit;
}
$stmt = $pdo->prepare('SELECT id FROM sportovci WHERE id = ?');
$stmt->execute([$sportovec_id]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Sportovec nenalezen.</div>';
    exit;
}
$canWriteNote = sportovecNoteCanWrite($pdo, $sportovec_id);
$rok = isset($_GET['rok']) && ctype_digit($_GET['rok']) ? (int)$_GET['rok'] : 0;

// Persist lifecycle and authorization updates before either read-only projection.
// Concurrent profile reads and logout must not wait for rendering to finish.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (($_GET['view'] ?? '') === 'measurements') {
    $measurementType = isset($_GET['typ']) && is_string($_GET['typ']) ? $_GET['typ'] : '';
    $measurementPage = AthleteProfileRead::measurements($pdo, $sportovec_id, $measurementType, AthleteProfileRead::requested($_GET));
    $mojeMereni = $measurementPage['rows'];
    require __DIR__ . '/includes/athlete_measurement_list.php';
    exit;
}

$historyPage = AthleteProfileRead::history($pdo, $sportovec_id, $rok, AthleteProfileRead::requested($_GET));
$treninky = $historyPage['rows'];
$notes = $historyPage['notes'];
$mereniByTrenink = $historyPage['measurements'];

// --- výstup ---
if (empty($treninky)) {
    echo '<div class="alert alert-info m-0">Za vybraný rok nejsou žádné tréninky.</div>';
    exit;
}

$totalMinutes = $historyPage['minutes'];
$totalH = floor($totalMinutes / 60);
$totalM = (int)($totalMinutes % 60);
$totalHlabel = $totalH > 0 ? "{$totalH} h " . ($totalM > 0 ? "{$totalM} min" : '') : ($totalM > 0 ? "{$totalM} min" : '');
?>

<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <span class="badge bg-light text-dark border fs-6">
        Tréninků: <strong><?= $historyPage['total'] ?></strong>
    </span>
    <?php if ($totalHlabel): ?>
    <span class="badge bg-light text-dark border fs-6">
        Celkem: <strong><?= h($totalHlabel) ?></strong>
    </span>
    <?php endif; ?>
</div>

<?php foreach ($treninky as $t):
    $tid_int = (int)$t['id'];
    $thumbs  = array_filter(array_map('trim', explode(',', $t['obrazky'] ?? '')));
    $note    = $notes[$tid_int] ?? '';
    $mRows   = $mereniByTrenink[$tid_int] ?? [];
    $delka   = !empty($t['delka']) ? $t['delka'] : null;
    ?>
<div class="card mb-3 trenink-card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <strong class="me-1"><?= fmtDate($t['datum']) ?></strong>
        <?php if ($delka !== null): ?>
            <span class="badge bg-secondary"><?= h((string)$delka) ?> h</span>
        <?php endif; ?>
        <?php if (!empty($mRows)): ?>
            <span class="badge bg-info text-dark"><?= count($mRows) ?> měření</span>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <?php if (!empty($thumbs)): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($thumbs as $img): ?>
                    <a href="nahrane_obrazky/<?= h($img) ?>" target="_blank">
                        <img src="nahrane_obrazky/<?= h($img) ?>" class="thumb-tr" alt="">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($t['napln'])): ?>
            <div class="mb-3">
                <div class="fw-semibold text-muted small mb-1">NÁPLŇ TRÉNINKU</div>
                <div class="ps-2 border-start border-2"><?= nl2br(h($t['napln'])) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($mRows)): ?>
            <div class="mb-3">
                <div class="fw-semibold text-muted small mb-2">MOJE MĚŘENÍ</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:100px;">Typ</th>
                                <th>Hodnoty</th>
                                <th style="width:180px;">Poznámka</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mRows as $mz): ?>
                                <tr>
                                    <td>
                                        <?php
                                            $typBadge = ['kolo' => 'bg-primary',
                                                'kolo_krouzek' => 'bg-info',
                                                'kolo_silnice' => 'bg-danger',
                                                'kolo_mtb' => 'bg-warning text-dark',
                                                'beh' => 'bg-success',
                                                'posilovna' => 'bg-warning text-dark'];
                                $cls = $typBadge[$mz['typ']] ?? 'bg-secondary';
                                ?>
                                        <span class="badge <?= $cls ?>"><?= h($mz['typ']) ?></span>
                                    </td>
                                    <td><?= renderMereniData($mz) ?></td>
                                    <td class="text-muted small"><?= nl2br(h($mz['mereni_poznamka'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Poznámka sportovce; veřejný hash je pouze pro čtení. -->
        <div class="mt-3">
            <div class="fw-semibold text-muted small mb-1">TVOJE POZNÁMKA</div>
            <?php if ($canWriteNote): ?>
            <textarea class="form-control athlete-note-input"
                      rows="2"
                      data-tid="<?= $tid_int ?>"><?= h($note) ?></textarea>
            <div class="d-flex align-items-center gap-2 mt-1">
                <button class="btn btn-sm btn-primary save-note-btn" data-tid="<?= $tid_int ?>">
                    Uložit
                </button>
                <span class="note-status text-success small" data-tid="<?= $tid_int ?>" style="display:none;">
                    ✓ Uloženo
                </span>
            </div>
            <?php else: ?>
                <?php if ($note !== ''): ?>
                    <div class="border rounded bg-light p-2"><?= nl2br(h($note)) ?></div>
                <?php else: ?>
                    <div class="text-muted small">Poznámka zatím není vyplněná.</div>
                <?php endif; ?>
                <div class="form-text">
                    Pro úpravu se přihlaste účtem sportovce nebo rodiče.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php endforeach; ?>

<?php AthleteProfileRead::navigation($historyPage, ['rok' => $rok]); ?>
