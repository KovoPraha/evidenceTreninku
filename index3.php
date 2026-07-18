<?php
session_start();
$is_logged_in = isset($_SESSION['trener_id']);
$is_hlavni    = $is_logged_in && ($_SESSION['role'] === 'hlavni');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Tréninková evidence – úvod</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .feature-icon { font-size: 1.5rem; vertical-align: middle; margin-right: .5rem; }
    .section-title { margin-top: 2rem; margin-bottom: .5rem; font-weight: 500; color: #444; }
    .list-group-item-action:hover { background-color: #f8f9fa; }
  </style>
</head>
<body class="bg-light">
  <?php include 'hlavicka.php'; ?>

  <div class="container py-5">
    <h1 class="mb-4 text-center">Vítejte v aplikaci pro evidenci tréninků</h1>

    <?php if (!$is_logged_in): ?>
      <div class="alert alert-info text-center">
        Pro vstup do aplikace se prosím <a href="login.php">přihlaste</a>.
      </div>
    <?php else: ?>
      <div class="row">
        <div class="col-md-6">
          <div class="section-title">📥 Import a zadání</div>
          <div class="list-group">
            <a href="formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-plus-square feature-icon"></i>Vložit nový trénink
            </a>
            <a href="nova_cinnost.php" class="list-group-item list-group-item-action">
              <i class="bi bi-journal-text feature-icon"></i>Zadat další činnost
            </a>
          </div>

          <div class="section-title">📊 Moje přehledy</div>
          <div class="list-group">
            <a href="vypis_vykazu.php" class="list-group-item list-group-item-action">
              <i class="bi bi-file-earmark-spreadsheet feature-icon"></i>Měsíční výkaz činností
            </a>
            <a href="prehled_trenera.php" class="list-group-item list-group-item-action">
              <i class="bi bi-calendar-range feature-icon"></i>Přehled tréninků dle měsíce
            </a>
            <a href="moje_treninky.php" class="list-group-item list-group-item-action">
              <i class="bi bi-pencil-square feature-icon"></i>Moje tréninky – úpravy
            </a>
            <a href="moje_skupiny.php" class="list-group-item list-group-item-action">
              <i class="bi bi-people feature-icon"></i>Moje skupiny – filtr a výpis
            </a>
          </div>
        </div>

        <div class="col-md-6">
          <div class="section-title">🎨 Stories a promo</div>
          <div class="list-group">
            <a href="nastaveni_story.php" class="list-group-item list-group-item-action">
              <i class="bi bi-palette feature-icon"></i>Vzhled stories
            </a>
            <a href="prehled_stories.php" class="list-group-item list-group-item-action">
              <i class="bi bi-images feature-icon"></i>Přehled stories
            </a>
          </div>

          <?php if ($is_hlavni): ?>
            <div class="section-title">⚙️ Administrace</div>
            <div class="list-group">
             <a href="vypis_vsech_vykazu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-ul feature-icon"></i>Výpis všech výkazů
              </a>
                 <a href="sprava_vsech_treninku.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-ul feature-icon"></i>Správa všech tréninků
              </a>
                  <a href="sprava_skupin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-ul feature-icon"></i>Správa skupin
              </a>
              <a href="sprava_podskupin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-columns-reverse feature-icon"></i>Správa podskupin
              </a>
              <a href="sprava_treneru.php" class="list-group-item list-group-item-action">
                <i class="bi bi-people-fill feature-icon"></i>Správa trenérů
              </a>
              <a href="verejny_prehled.php" class="list-group-item list-group-item-action">
                <i class="bi bi-globe feature-icon"></i>Veřejný přehled tréninků
              </a>
            </div>
          <?php endif; ?>

          <div class="section-title">🔗 Ostatní</div>
          <div class="list-group">
            <a href="prehled_sportovcu.php" class="list-group-item list-group-item-action">
              <i class="bi bi-person-lines-fill feature-icon"></i>Seznam sportovců
            </a>
            <a href="prehled_skupin.php" class="list-group-item list-group-item-action">
              <i class="bi bi-list-check feature-icon"></i>Seznam skupin
            </a>
            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
              <i class="bi bi-box-arrow-right feature-icon"></i>Odhlásit se
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
