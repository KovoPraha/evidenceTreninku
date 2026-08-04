<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * AJAX endpoint: vrací HTML accordion-items pro tréninky trenéra
 * GET params: skupina_id (int|''), mesic (1-12|''), rok (int|'')
 */
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId    = (int)$_SESSION['trener_id'];
$skupinaId = $_GET['skupina_id'] ?? '';
$mesic     = $_GET['mesic'] ?? '';
$rok       = $_GET['rok'] ?? '';

// Základní dotaz
$sql = "SELECT t.id, t.datum, t.delka, t.napln, t.poznamka, t.obrazky, t.mereni
        FROM trenink_trener tt
        JOIN treninky t ON tt.trenink_id = t.id";

$where   = ['tt.trener_id = :uid'];
$params  = [':uid' => $userId];

// Filtr skupiny
if ($skupinaId !== '' && ctype_digit((string)$skupinaId)) {
    $sql .= " JOIN trenink_skupina ts2 ON ts2.trenink_id = t.id";
    $where[] = "ts2.skupina_id = :sid";
    $params[':sid'] = (int)$skupinaId;
}

// Filtr roku
if ($rok !== '' && ctype_digit((string)$rok)) {
    $where[] = "YEAR(t.datum) = :rok";
    $params[':rok'] = (int)$rok;
}

// Filtr měsíce
if ($mesic !== '' && ctype_digit((string)$mesic) && (int)$mesic >= 1 && (int)$mesic <= 12) {
    $where[] = "MONTH(t.datum) = :mesic";
    $params[':mesic'] = (int)$mesic;
}

// Fulltext hledání v náplni a poznámce
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "(t.napln LIKE :q OR t.poznamka LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY t.datum DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);

$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

if (empty($treninky)) {
    echo '<div class="alert alert-info">Žádné tréninky neodpovídají filtru.</div>';
    exit;
}

// ── BATCH queries místo N+1 ──────────────────────────────────────────────
$ids = array_column($treninky, 'id');
$in  = implode(',', array_fill(0, count($ids), '?'));

$skupByTr = $psByTr = $trsByTr = $ucByTr = $tagyByTr = [];

try {
    // Skupiny
    $r = $pdo->prepare("SELECT ts.trenink_id, s.nazev FROM trenink_skupina ts JOIN skupiny s ON s.id = ts.skupina_id WHERE ts.trenink_id IN ($in) ORDER BY s.nazev");
    $r->execute($ids);
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $skupByTr[(int)$row['trenink_id']][] = $row['nazev'];

    // Podskupiny
    $r = $pdo->prepare("SELECT tp.trenink_id, p.nazev FROM trenink_podskupina tp JOIN podskupiny p ON p.id = tp.podskupina_id WHERE tp.trenink_id IN ($in) ORDER BY p.nazev");
    $r->execute($ids);
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $psByTr[(int)$row['trenink_id']][] = $row['nazev'];

    // Trenéři
    $r = $pdo->prepare("SELECT tt.trenink_id, tr.jmeno FROM trenink_trener tt JOIN treneri tr ON tr.id = tt.trener_id WHERE tt.trenink_id IN ($in) ORDER BY tr.jmeno");
    $r->execute($ids);
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $trsByTr[(int)$row['trenink_id']][] = $row['jmeno'];

    // Účastníci (příjmení + jméno)
    $r = $pdo->prepare("SELECT ts.trenink_id, CONCAT(sp.prijmeni, ' ', sp.jmeno) AS jmeno FROM trenink_sportovec ts JOIN sportovci sp ON sp.id = ts.sportovec_id WHERE ts.trenink_id IN ($in) ORDER BY sp.prijmeni");
    $r->execute($ids);
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $ucByTr[(int)$row['trenink_id']][] = $row['jmeno'];

    // Tagy
    $r = $pdo->prepare("SELECT ttg.trenink_id, tg.nazev FROM trenink_tag ttg JOIN tagy tg ON tg.id = ttg.tag_id WHERE ttg.trenink_id IN ($in) ORDER BY tg.nazev");
    $r->execute($ids);
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $tagyByTr[(int)$row['trenink_id']][] = $row['nazev'];
} catch (PDOException $e) {
    error_log('ajax_treninky batch queries: ' . $e->getMessage());
}

echo '<div class="accordion" id="accordionTreninky">';

foreach ($treninky as $t) {
    $treninkId  = (int)$t['id'];
    try { $dayEng = (new DateTime($t['datum']))->format('l'); }
    catch (Exception $e) { $dayEng = ''; }
    $dayCz      = $czDays[$dayEng] ?? $dayEng;
    $headingId  = "h{$treninkId}";
    $collapseId = "c{$treninkId}";

    $skup = $skupByTr[$treninkId] ?? [];
    $ps   = $psByTr[$treninkId]   ?? [];
    $trs  = $trsByTr[$treninkId]  ?? [];
    $uc   = $ucByTr[$treninkId]   ?? [];
    $tagy = $tagyByTr[$treninkId] ?? [];

    $merHtml = trim((string)($t['mereni'] ?? '')) !== ''
        ? nl2br(h($t['mereni']))
        : '<span class="text-muted">—</span>';

    $thumbs = array_filter(array_map('trim', explode(',', (string)($t['obrazky'] ?? ''))));

    ?>
    <div class="accordion-item mb-2 shadow-sm">
        <h2 class="accordion-header" id="<?= h($headingId) ?>">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?= h($collapseId) ?>"
                    aria-expanded="false"
                    aria-controls="<?= h($collapseId) ?>">
                <div class="w-100 d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= h($t['datum']) ?></strong>
                        <span class="text-muted"> (<?= h($dayCz) ?>)</span>
                        <?php if (!empty($t['delka'])): ?>
                            <span class="ms-2 badge bg-secondary"><?= h($t['delka']) ?> h</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <?php if (!empty($ps)): ?>
                            <span class="badge bg-primary"><?= h(implode(', ', $ps)) ?></span>
                        <?php elseif (!empty($skup)): ?>
                            <span class="badge bg-primary"><?= h(implode(', ', $skup)) ?></span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark">bez skupiny</span>
                        <?php endif; ?>
                    </div>
                </div>
            </button>
        </h2>

        <div id="<?= h($collapseId) ?>" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="kv">
                            <div class="muted-label">Náplň</div>
                            <div><?= nl2br(h($t['napln'] ?? '')) ?: '<span class="text-muted">—</span>' ?></div>
                        </div>

                        <div class="kv mt-3" id="poznamka-wrap-<?= $treninkId ?>">
                            <div class="muted-label d-flex align-items-center gap-2">
                                Poznámka
                                <button type="button" class="btn btn-link btn-sm p-0 lh-1 js-edit-poznamka"
                                        data-id="<?= $treninkId ?>" title="Upravit">✏️</button>
                            </div>
                            <div id="poznamka-text-<?= $treninkId ?>">
                                <?= nl2br(h($t['poznamka'] ?? '')) ?: '<span class="text-muted">—</span>' ?>
                            </div>
                            <div id="poznamka-form-<?= $treninkId ?>" class="d-none mt-1">
                                <textarea class="form-control form-control-sm" rows="3" id="poznamka-ta-<?= $treninkId ?>"><?= h($t['poznamka'] ?? '') ?></textarea>
                                <div class="mt-1 d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary js-save-poznamka" data-id="<?= $treninkId ?>">Uložit</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-cancel-poznamka" data-id="<?= $treninkId ?>">Zrušit</button>
                                </div>
                                <div class="js-poznamka-msg mt-1 small"></div>
                            </div>
                        </div>

                        <div class="kv mt-3">
                            <div class="muted-label">Tagy</div>
                            <div>
                                <?php if (!empty($tagy)): ?>
                                    <?php foreach ($tagy as $tg): ?>
                                        <span class="badge bg-info text-dark me-1"><?= h($tg) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="kv mt-3">
                            <div class="muted-label">Měření</div>
                            <div><?= $merHtml ?></div>
                        </div>

                        <?php if (!empty($thumbs)): ?>
                        <div class="kv mt-3">
                            <div class="muted-label">Fotografie</div>
                            <div>
                                <?php foreach ($thumbs as $img): ?>
                                    <a href="nahrane_obrazky/<?= h($img) ?>" target="_blank">
                                        <img src="nahrane_obrazky/<?= h($img) ?>" class="thumbnail" alt="">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="kv">
                                    <div class="muted-label">Skupiny</div>
                                    <div><?= !empty($skup) ? h(implode(', ', $skup)) : '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="kv mt-2">
                                    <div class="muted-label">Podskupiny</div>
                                    <div><?= !empty($ps) ? h(implode(', ', $ps)) : '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="kv mt-2">
                                    <div class="muted-label">Trenéři</div>
                                    <div><?= !empty($trs) ? h(implode(', ', $trs)) : '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <div class="kv mt-2">
                                    <div class="muted-label">Účastníci</div>
                                    <div><?= !empty($uc) ? h(implode(', ', $uc)) : '<span class="text-muted">—</span>' ?></div>
                                </div>
                                <hr>
                                <div class="d-grid gap-2">
                                    <form method="POST" action="generuj_story.php" class="d-grid">
                                        <input type="hidden" name="id" value="<?= $treninkId ?>"><?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-info">Story</button>
                                    </form>
                                    <a href="edit_trenink.php?id=<?= $treninkId ?>" class="btn btn-sm btn-secondary">Upravit</a>
                                    <form method="POST" action="smazat_trenink.php"
                                          data-confirm="Opravdu smazat trénink?"
                                          class="d-inline w-100">
                                        <input type="hidden" name="trenink_id" value="<?= $treninkId ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger w-100">Smazat</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

echo '</div>';
