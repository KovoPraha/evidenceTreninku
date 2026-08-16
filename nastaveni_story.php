<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── 1) Mazání nastavení ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        die('Neplatný CSRF token.');
    }
    $typ       = $_POST['typ'];
    $entita_id = (int)$_POST['entita_id'];

    // Smazání starého loga
    $stmtOld = $pdo->prepare("SELECT logo FROM story_nastaveni WHERE typ = :typ AND entita_id = :eid");
    $stmtOld->execute([':typ' => $typ, ':eid' => $entita_id]);
    $oldLogo = $stmtOld->fetchColumn();
    if ($oldLogo && file_exists(__DIR__ . '/loga_story/' . $oldLogo)) {
        @unlink(__DIR__ . '/loga_story/' . $oldLogo);
    }

    $stmt = $pdo->prepare("DELETE FROM story_nastaveni WHERE typ = :typ AND entita_id = :eid");
    $stmt->execute([':typ' => $typ, ':eid' => $entita_id]);
    $_SESSION['flash_success'] = 'Nastavení bylo smazáno.';
    header("Location: nastaveni_story.php");
    exit;
}

// ── 2) Uložení / aktualizace ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        die('Neplatný CSRF token.');
    }
    $typ          = $_POST['typ'];
    $entita_id    = (int)$_POST['entita_id'];
    $barva        = $_POST['barva'];
    $barva_textu  = $_POST['barva_textu'];
    $hlavicka     = trim($_POST['hlavicka'] ?? '');
    $paticka      = trim($_POST['paticka'] ?? '');
    $logoFilename = null;

    // Validace
    if ($entita_id <= 0) {
        $_SESSION['flash_error'] = 'Vyberte skupinu nebo podskupinu.';
        header("Location: nastaveni_story.php");
        exit;
    }

    // Upload loga
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $finfo       = finfo_open(FILEINFO_MIME_TYPE);
        $logoMime    = finfo_file($finfo, $_FILES['logo']['tmp_name']);
        finfo_close($finfo);
        $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (in_array($logoMime, $allowedMime, true)) {
            $uploadDir = __DIR__ . '/loga_story/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) $ext = 'bin';
            $logoFilename = uniqid('logo_') . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $logoFilename);
        }
    }

    $sql = "
        INSERT INTO story_nastaveni (typ, entita_id, barva, barva_textu, hlavicka, paticka, logo)
        VALUES (:typ, :entita_id, :barva, :barva_textu, :hlavicka, :paticka, :logo)
        ON DUPLICATE KEY UPDATE
            barva       = :barva_u,
            barva_textu = :barva_textu_u,
            hlavicka    = :hlavicka_u,
            paticka     = :paticka_u,
            logo        = COALESCE(:logo_u, logo)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':typ'            => $typ,
        ':entita_id'      => $entita_id,
        ':barva'          => $barva,
        ':barva_textu'    => $barva_textu,
        ':hlavicka'       => $hlavicka,
        ':paticka'        => $paticka,
        ':logo'           => $logoFilename,
        ':barva_u'        => $barva,
        ':barva_textu_u'  => $barva_textu,
        ':hlavicka_u'     => $hlavicka,
        ':paticka_u'      => $paticka,
        ':logo_u'         => $logoFilename
    ]);

    $_SESSION['flash_success'] = 'Nastavení uloženo.';
    header("Location: nastaveni_story.php");
    exit;
}

// ── 3) Načtení dat ──────────────────────────────────────────────
$groups    = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$subgroups = $pdo->query("SELECT id, skupina_id, nazev FROM podskupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$settings  = $pdo->query("SELECT * FROM story_nastaveni ORDER BY typ, entita_id")->fetchAll(PDO::FETCH_ASSOC);

// Mapy pro zobrazení názvů
$groupMap = [];
foreach ($groups as $g) $groupMap[$g['id']] = $g['nazev'];
$subgroupMap = [];
foreach ($subgroups as $s) $subgroupMap[$s['id']] = $s['nazev'];

// Předvyplnění pro editaci
$editData = null;
if (isset($_GET['edit_typ']) && isset($_GET['edit_id'])) {
    $eTyp = $_GET['edit_typ'];
    $eId  = (int)$_GET['edit_id'];
    $stmtE = $pdo->prepare("SELECT * FROM story_nastaveni WHERE typ = ? AND entita_id = ?");
    $stmtE->execute([$eTyp, $eId]);
    $editData = $stmtE->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nastavení Instagram stories</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .preview-card {
      width: 180px;
      height: 320px;
      border-radius: 12px;
      overflow: hidden;
      position: relative;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      transition: all 0.3s ease;
    }
    .preview-header {
      position: absolute; top: 0; left: 0; right: 0;
      padding: 12px 15px;
      font-size: 11px; font-weight: bold;
      background: linear-gradient(to bottom, var(--bar-color) 0%, transparent 100%);
    }
    .preview-footer {
      position: absolute; bottom: 0; left: 0; right: 0;
      padding: 12px 15px;
      font-size: 9px; text-align: center;
      background: linear-gradient(to top, var(--bar-color) 0%, transparent 100%);
    }
    .preview-body {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
      font-size: 48px;
      background: linear-gradient(135deg, var(--bar-color), #333);
    }
    .color-dot {
      display: inline-block;
      width: 24px; height: 24px;
      border-radius: 50%;
      border: 2px solid #dee2e6;
      vertical-align: middle;
    }
    .cfg-card {
      border-left: 4px solid;
      transition: transform 0.15s;
    }
    .cfg-card:hover {
      transform: translateX(3px);
    }
  </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-palette2 fs-2 text-primary"></i>
    <div>
      <h1 class="h3 mb-0">Nastavení vzhledu stories</h1>
      <p class="text-muted mb-0 small">Definujte barvy, loga a texty pro generované Instagram stories</p>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="bi bi-check-circle me-2"></i><?= h($_SESSION['flash_success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="bi bi-exclamation-triangle me-2"></i><?= h($_SESSION['flash_error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Formulář -->
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <h5 class="card-title mb-0">
            <i class="bi bi-pencil-square me-2"></i>
            <?= $editData ? 'Upravit nastavení' : 'Nové nastavení' ?>
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data" id="storyForm">
            <?= csrf_field() ?>
            <input type="hidden" name="save" value="1">

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="typ" class="form-label fw-semibold">Typ</label>
                <select name="typ" id="typ" class="form-select" required>
                  <option value="skupina" <?= ($editData && $editData['typ'] === 'skupina') ? 'selected' : '' ?>>
                    Skupina
                  </option>
                  <option value="podskupina" <?= ($editData && $editData['typ'] === 'podskupina') ? 'selected' : '' ?>>
                    Podskupina
                  </option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="entita_id" class="form-label fw-semibold">Skupina / Podskupina</label>
                <select name="entita_id" id="entita_id" class="form-select" required>
                  <option value="">— vyberte —</option>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="barva" class="form-label fw-semibold">
                  <i class="bi bi-paint-bucket me-1"></i>Barva pozadí
                </label>
                <div class="input-group">
                  <input type="color" name="barva" id="barva" class="form-control form-control-color"
                         value="<?= h($editData['barva'] ?? '#1a1a2e') ?>">
                  <input type="text" class="form-control" id="barvaHex"
                         value="<?= h($editData['barva'] ?? '#1a1a2e') ?>" maxlength="7"
                         pattern="#[0-9a-fA-F]{6}" style="max-width:120px;">
                </div>
              </div>
              <div class="col-md-6">
                <label for="barva_textu" class="form-label fw-semibold">
                  <i class="bi bi-fonts me-1"></i>Barva textu
                </label>
                <div class="input-group">
                  <input type="color" name="barva_textu" id="barva_textu" class="form-control form-control-color"
                         value="<?= h($editData['barva_textu'] ?? '#FFFFFF') ?>">
                  <input type="text" class="form-control" id="barvaTextuHex"
                         value="<?= h($editData['barva_textu'] ?? '#FFFFFF') ?>" maxlength="7"
                         pattern="#[0-9a-fA-F]{6}" style="max-width:120px;">
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="hlavicka" class="form-label fw-semibold">
                <i class="bi bi-type-h1 me-1"></i>Text hlavičky
              </label>
              <input type="text" name="hlavicka" id="hlavicka" class="form-control"
                     value="<?= h($editData['hlavicka'] ?? '') ?>"
                     placeholder="Název klubu / oddílu" maxlength="100">
              <div class="form-text">Zobrazí se v horní části story vedle loga</div>
            </div>
            <div class="mb-3">
              <label for="paticka" class="form-label fw-semibold">
                <i class="bi bi-hash me-1"></i>Text patičky
              </label>
              <input type="text" name="paticka" id="paticka" class="form-control"
                     value="<?= h($editData['paticka'] ?? '') ?>"
                     placeholder="#hashtag nebo poznámka" maxlength="100">
              <div class="form-text">Zobrazí se centrovaně ve spodní části story</div>
            </div>
            <div class="mb-4">
              <label for="logo" class="form-label fw-semibold">
                <i class="bi bi-image me-1"></i>Logo
              </label>
              <input type="file" name="logo" id="logo" class="form-control"
                     accept="image/png, image/jpeg, image/gif, image/webp">
              <div class="form-text">PNG nebo JPG, zobrazí se vlevo nahoře</div>
              <?php if ($editData && !empty($editData['logo'])): ?>
                <div class="mt-2">
                  <small class="text-muted">Aktuální logo:</small>
                  <img src="loga_story/<?= h($editData['logo']) ?>" height="40" alt="Logo" class="ms-2 rounded border">
                </div>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>
                <?= $editData ? 'Aktualizovat' : 'Uložit nastavení' ?>
              </button>
              <?php if ($editData): ?>
                <a href="nastaveni_story.php" class="btn btn-outline-secondary">
                  <i class="bi bi-x-lg me-1"></i>Zrušit úpravu
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Live Preview -->
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <h5 class="card-title mb-0">
            <i class="bi bi-eye me-2"></i>Náhled
          </h5>
        </div>
        <div class="card-body d-flex justify-content-center">
          <div class="preview-card" id="previewCard"
               style="--bar-color: <?= h($editData['barva'] ?? '#1a1a2e') ?>;">
            <div class="preview-body" id="previewBody">
              <i class="bi bi-camera text-white opacity-25"></i>
            </div>
            <div class="preview-header" id="previewHeader"
                 style="color: <?= h($editData['barva_textu'] ?? '#FFFFFF') ?>;">
              <?= h($editData['hlavicka'] ?? 'Hlavička') ?>
            </div>
            <div class="preview-footer" id="previewFooter"
                 style="color: <?= h($editData['barva_textu'] ?? '#FFFFFF') ?>;">
              <?= h($editData['paticka'] ?? '#hashtag') ?>
            </div>
          </div>
        </div>
        <div class="card-footer bg-white text-center">
          <small class="text-muted">Přibližný náhled vzhledu</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Existující konfigurace -->
  <h2 class="h4 mt-5 mb-3">
    <i class="bi bi-gear me-2"></i>Aktuální konfigurace
  </h2>

  <?php if ($settings): ?>
    <div class="row g-3">
      <?php foreach ($settings as $cfg):
        $entityName = '';
        if ($cfg['typ'] === 'skupina') {
            $entityName = $groupMap[$cfg['entita_id']] ?? '(ID: ' . $cfg['entita_id'] . ')';
        } else {
            $entityName = $subgroupMap[$cfg['entita_id']] ?? '(ID: ' . $cfg['entita_id'] . ')';
        }
      ?>
        <div class="col-md-6 col-xl-4">
          <div class="card cfg-card shadow-sm" style="border-left-color: <?= h($cfg['barva']) ?>;">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <span class="badge <?= $cfg['typ'] === 'skupina' ? 'bg-primary' : 'bg-info' ?> mb-1">
                    <?= $cfg['typ'] === 'skupina' ? 'Skupina' : 'Podskupina' ?>
                  </span>
                  <h6 class="mb-0"><?= h($entityName) ?></h6>
                </div>
                <?php if (!empty($cfg['logo'])): ?>
                  <img src="loga_story/<?= h($cfg['logo']) ?>" height="35" alt="Logo" class="rounded">
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2 align-items-center mb-2">
                <span class="color-dot" style="background: <?= h($cfg['barva']) ?>;"></span>
                <small class="text-muted"><?= h($cfg['barva']) ?></small>
                <span class="color-dot" style="background: <?= h($cfg['barva_textu']) ?>;"></span>
                <small class="text-muted"><?= h($cfg['barva_textu']) ?></small>
              </div>

              <?php if (!empty($cfg['hlavicka'])): ?>
                <div class="small"><strong>Hlavička:</strong> <?= h($cfg['hlavicka']) ?></div>
              <?php endif; ?>
              <?php if (!empty($cfg['paticka'])): ?>
                <div class="small"><strong>Patička:</strong> <?= h($cfg['paticka']) ?></div>
              <?php endif; ?>

              <div class="d-flex gap-2 mt-3">
                <a href="nastaveni_story.php?edit_typ=<?= h($cfg['typ']) ?>&edit_id=<?= h($cfg['entita_id']) ?>"
                   class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil me-1"></i>Upravit
                </a>
                <form method="POST" class="m-0"
                      data-confirm="Opravdu smazat nastavení pro <?= h($entityName) ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete" value="1">
                  <input type="hidden" name="typ" value="<?= h($cfg['typ']) ?>">
                  <input type="hidden" name="entita_id" value="<?= h($cfg['entita_id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>Smazat
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i>Žádné nastavení zatím nebylo vytvořeno. Přidejte první konfiguraci výše.
    </div>
  <?php endif; ?>
</div>


<script>
// Data skupin a podskupin z PHP
const groups = <?= json_encode($groups, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const subgroups = <?= json_encode($subgroups, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

const typSelect    = document.getElementById('typ');
const entitySelect = document.getElementById('entita_id');
const barva        = document.getElementById('barva');
const barvaHex     = document.getElementById('barvaHex');
const barvaTextu   = document.getElementById('barva_textu');
const barvaTextuHex = document.getElementById('barvaTextuHex');
const hlavicka     = document.getElementById('hlavicka');
const paticka      = document.getElementById('paticka');
const previewCard  = document.getElementById('previewCard');
const previewHeader = document.getElementById('previewHeader');
const previewFooter = document.getElementById('previewFooter');
const previewBody  = document.getElementById('previewBody');

// Naplnění entity dropdown
function fillEntities() {
    const typ = typSelect.value;
    const items = typ === 'skupina' ? groups : subgroups;
    let html = '<option value="">— vyberte —</option>';
    items.forEach(i => {
        html += `<option value="${i.id}">${i.nazev || i.id}</option>`;
    });
    entitySelect.innerHTML = html;

    // Pokud editujeme, předvyber
    <?php if ($editData): ?>
    const editId = <?= (int)$editData['entita_id'] ?>;
    const editTyp = '<?= h($editData['typ']) ?>';
    if (typ === editTyp) {
        entitySelect.value = editId;
    }
    <?php endif; ?>
}

typSelect.addEventListener('change', fillEntities);
fillEntities();

// Sync color pickers s hex inputy
barva.addEventListener('input', () => {
    barvaHex.value = barva.value;
    updatePreview();
});
barvaHex.addEventListener('input', () => {
    if (/^#[0-9a-fA-F]{6}$/.test(barvaHex.value)) {
        barva.value = barvaHex.value;
        updatePreview();
    }
});
barvaTextu.addEventListener('input', () => {
    barvaTextuHex.value = barvaTextu.value;
    updatePreview();
});
barvaTextuHex.addEventListener('input', () => {
    if (/^#[0-9a-fA-F]{6}$/.test(barvaTextuHex.value)) {
        barvaTextu.value = barvaTextuHex.value;
        updatePreview();
    }
});
hlavicka.addEventListener('input', updatePreview);
paticka.addEventListener('input', updatePreview);

function updatePreview() {
    const bg = barva.value;
    const tx = barvaTextu.value;
    previewCard.style.setProperty('--bar-color', bg);
    previewBody.style.background = `linear-gradient(135deg, ${bg}, #333)`;
    previewHeader.style.color = tx;
    previewFooter.style.color = tx;
    previewHeader.textContent = hlavicka.value || 'Hlavička';
    previewFooter.textContent = paticka.value || '#hashtag';
}
</script>
</body>
</html>
