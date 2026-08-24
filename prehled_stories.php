<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';
require_once 'csrf_helper.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function softDeleteStoryFile(string $filename): void {
    $base = basename($filename);
    if ($base === '' || str_starts_with($base, 'smazano_')) return;
    $dir = realpath(__DIR__ . '/stories');
    if ($dir === false) return;
    $source = $dir . DIRECTORY_SEPARATOR . $base;
    if (!is_file($source)) return;
    $target = $dir . DIRECTORY_SEPARATOR . 'smazano_' . $base;
    if (is_file($target)) {
        $target = $dir . DIRECTORY_SEPARATOR . 'smazano_' . date('Ymd_His') . '_' . $base;
    }
    @rename($source, $target);
}

// ── Mazání story ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_story'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        die('Neplatný CSRF token.');
    }
    $storyId = (int)$_POST['story_id'];
    $stmtFile = $pdo->prepare("SELECT soubor FROM story_vygenerovane WHERE id = ?");
    $stmtFile->execute([$storyId]);
    $soubor = $stmtFile->fetchColumn();
    if ($soubor) {
        softDeleteStoryFile((string)$soubor);
        $pdo->prepare("DELETE FROM story_vygenerovane WHERE id = ?")->execute([$storyId]);
        $_SESSION['flash_success'] = 'Story bylo smazáno.';
    }
    // Redirect zpět s filtry
    $redirectParams = array_filter([
        'group_id'    => $_GET['group_id'] ?? '',
        'subgroup_id' => $_GET['subgroup_id'] ?? '',
        'page'        => $_GET['page'] ?? '',
    ]);
    header("Location: prehled_stories.php" . ($redirectParams ? '?' . http_build_query($redirectParams) : ''));
    exit;
}

// ── Filtry ───────────────────────────────────────────────────────
$groups = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$subgroups = [];
if (!empty($_GET['group_id'])) {
    $stmt = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi");
    $stmt->execute([(int)$_GET['group_id']]);
    $subgroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── SQL dotaz ────────────────────────────────────────────────────
$baseSql = "
    FROM story_vygenerovane sv
    JOIN treninky t ON t.id = sv.trenink_id
    JOIN trenink_skupina ts ON ts.trenink_id = t.id
    JOIN skupiny s ON s.id = ts.skupina_id
    LEFT JOIN trenink_trener tt ON tt.trenink_id = t.id
    LEFT JOIN treneri tr ON tr.id = tt.trener_id
";
$where = [];
$params = [];

if (!empty($_GET['group_id'])) {
    $where[] = "ts.skupina_id = :gid";
    $params[':gid'] = (int)$_GET['group_id'];
}
if (!empty($_GET['subgroup_id'])) {
    // FIX: Správný JOIN na trenink_podskupina (dříve chybně filtroval přes trenink_skupina)
    $baseSql .= " JOIN trenink_podskupina tp ON tp.trenink_id = t.id ";
    $where[] = "tp.podskupina_id = :sid";
    $params[':sid'] = (int)$_GET['subgroup_id'];
}

$whereClause = $where ? " WHERE " . implode(" AND ", $where) : "";

// Stránkování
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(DISTINCT sv.id) " . $baseSql . $whereClause;
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalStories = (int)$stmtCount->fetchColumn();
$totalPages   = max(1, (int)ceil($totalStories / $perPage));

$dataSql = "
    SELECT
        sv.id, sv.soubor, sv.created,
        t.datum, t.napln,
        GROUP_CONCAT(DISTINCT tr.jmeno SEPARATOR ', ') AS trener_jmeno,
        GROUP_CONCAT(DISTINCT s.nazev SEPARATOR ', ') AS skupiny
    " . $baseSql . $whereClause . "
    GROUP BY sv.id
    ORDER BY sv.created DESC
    LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$stmtData = $pdo->prepare($dataSql);
$stmtData->execute($params);
$stories = $stmtData->fetchAll(PDO::FETCH_ASSOC);

function pageUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return 'prehled_stories.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Přehled stories</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
  <style>
    .story-card {
      transition: transform 0.2s, box-shadow 0.2s;
      overflow: hidden;
      border-radius: 12px;
    }
    .story-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    .story-thumb {
      width: 100%;
      aspect-ratio: 9 / 16;
      object-fit: cover;
      cursor: pointer;
    }
    .story-meta {
      font-size: 0.8rem;
    }
    .modal-story-img {
      max-height: 85vh;
      object-fit: contain;
    }
  </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-collection-play fs-2 text-primary"></i>
    <div>
      <h1 class="h3 mb-0">Přehled stories</h1>
      <p class="text-muted mb-0 small">
        <?= $totalStories ?> vygenerovan<?= $totalStories === 1 ? 'á story' : ($totalStories >= 2 && $totalStories <= 4 ? 'é stories' : 'ých stories') ?>
      </p>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="bi bi-check-circle me-2"></i><?= h($_SESSION['flash_success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <!-- Filtry -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label for="group" class="form-label fw-semibold">
            <i class="bi bi-people me-1"></i>Skupina
          </label>
          <select id="group" name="group_id" class="form-select">
            <option value="">— všechny —</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= $g['id'] ?>" <?= (isset($_GET['group_id']) && $_GET['group_id'] == $g['id']) ? 'selected' : '' ?>>
                <?= h($g['nazev']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="subgroup" class="form-label fw-semibold">
            <i class="bi bi-diagram-3 me-1"></i>Podskupina
          </label>
          <select id="subgroup" name="subgroup_id" class="form-select">
            <option value="">— všechny —</option>
            <?php foreach ($subgroups as $s): ?>
              <option value="<?= $s['id'] ?>" <?= (isset($_GET['subgroup_id']) && $_GET['subgroup_id'] == $s['id']) ? 'selected' : '' ?>>
                <?= h($s['nazev']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-funnel me-1"></i>Filtrovat
          </button>
          <?php if (!empty($_GET['group_id']) || !empty($_GET['subgroup_id'])): ?>
            <a href="prehled_stories.php" class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-1"></i>Zrušit
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Stories Grid -->
  <?php if ($stories): ?>
    <div class="row g-4">
      <?php foreach ($stories as $st): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card story-card shadow-sm h-100">
            <img src="stories/<?= h($st['soubor']) ?>"
                 class="story-thumb"
                 alt="Story"
                 data-bs-toggle="modal"
                 data-bs-target="#storyModal"
                 data-src="stories/<?= h($st['soubor']) ?>"
                 data-datum="<?= h($st['datum']) ?>"
                 data-skupiny="<?= h($st['skupiny']) ?>"
                 data-trener="<?= h($st['trener_jmeno'] ?? '') ?>"
                 data-soubor="<?= h($st['soubor']) ?>">
            <div class="card-body p-2">
              <div class="story-meta text-muted">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span>
                    <i class="bi bi-calendar3 me-1"></i><?= h($st['datum']) ?>
                  </span>
                  <span class="badge bg-light text-dark"><?= h($st['skupiny']) ?></span>
                </div>
                <?php if (!empty($st['trener_jmeno'])): ?>
                  <div>
                    <i class="bi bi-person me-1"></i><?= h($st['trener_jmeno']) ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="d-flex gap-1 mt-2">
                <a href="stories/<?= h($st['soubor']) ?>" download
                   class="btn btn-sm btn-outline-success flex-fill">
                  <i class="bi bi-download"></i>
                </a>
                <form method="POST" class="m-0"
                      data-confirm="Opravdu smazat tuto story?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete_story" value="1">
                  <input type="hidden" name="story_id" value="<?= h($st['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Stránkování -->
    <?php if ($totalPages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= pageUrl($page - 1) ?>">
              <i class="bi bi-chevron-left"></i>
            </a>
          </li>
          <?php
          $startP = max(1, $page - 2);
          $endP   = min($totalPages, $page + 2);
          if ($startP > 1) {
              echo '<li class="page-item"><a class="page-link" href="' . pageUrl(1) . '">1</a></li>';
              if ($startP > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
          }
          for ($p = $startP; $p <= $endP; $p++):
          ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="<?= pageUrl($p) ?>"><?= $p ?></a>
            </li>
          <?php
          endfor;
          if ($endP < $totalPages) {
              if ($endP < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              echo '<li class="page-item"><a class="page-link" href="' . pageUrl($totalPages) . '">' . $totalPages . '</a></li>';
          }
          ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= pageUrl($page + 1) ?>">
              <i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>

  <?php else: ?>
    <div class="text-center py-5">
      <i class="bi bi-camera text-muted" style="font-size: 4rem;"></i>
      <h4 class="text-muted mt-3">Žádné stories</h4>
      <p class="text-muted">Vygenerujte první story z přehledu tréninků.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<div class="modal fade" id="storyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header border-0 pb-0">
        <div class="text-white">
          <span id="modalDatum" class="fw-bold"></span>
          <span id="modalSkupiny" class="ms-2 badge bg-secondary"></span>
          <span id="modalTrener" class="ms-2 text-white-50 small"></span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-2">
        <img id="modalImg" src="" class="modal-story-img rounded" alt="Story">
      </div>
      <div class="modal-footer border-0 justify-content-center pt-0">
        <a id="modalDownload" href="" download class="btn btn-success">
          <i class="bi bi-download me-2"></i>Stáhnout
        </a>
      </div>
    </div>
  </div>
</div>


<script>
// AJAX podskupiny
document.getElementById('group').addEventListener('change', function() {
    const gid = this.value;
    const sub = document.getElementById('subgroup');
    if (!gid) {
        sub.innerHTML = '<option value="">— všechny —</option>';
        return;
    }
    sub.innerHTML = '<option>Načítám…</option>';
    fetch('nacti_podskupiny.php?skupina_id=' + encodeURIComponent(gid))
        .then(r => r.json())
        .then(data => {
            let html = '<option value="">— všechny —</option>';
            data.forEach(o => {
                html += `<option value="${o.id}">${o.nazev}</option>`;
            });
            sub.innerHTML = html;
        })
        .catch(() => {
            sub.innerHTML = '<option value="">— všechny —</option>';
        });
});

// Lightbox modal
const storyModal = document.getElementById('storyModal');
storyModal.addEventListener('show.bs.modal', function(event) {
    const trigger = event.relatedTarget;
    document.getElementById('modalImg').src = trigger.dataset.src;
    document.getElementById('modalDatum').textContent = trigger.dataset.datum;
    document.getElementById('modalSkupiny').textContent = trigger.dataset.skupiny;
    document.getElementById('modalTrener').textContent = trigger.dataset.trener ? trigger.dataset.trener : '';
    document.getElementById('modalDownload').href = trigger.dataset.src;
});
</script>
</body>
</html>
