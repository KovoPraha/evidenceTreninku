<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/csrf_helper.php';
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
    <h1 class="mb-4 text-center">Vítejte v aplikaci pro evidenci tréninků a závodů</h1>

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
              <i class="bi bi-file-earmark-spreadsheet feature-icon"></i>Měsíční výkaz činností - příloha faktury
            </a>
            <a href="prehled_trenera.php" class="list-group-item list-group-item-action">
              <i class="bi bi-calendar-range feature-icon"></i>Přehled tréninků dle měsíce - sportovní přehled
            </a>
   
            <a href="moje_treninky.php" class="list-group-item list-group-item-action">
              <i class="bi bi-pencil-square feature-icon"></i>Moje tréninky – úpravy  (detail tréninků)
            </a>
  
            <a href="moje_skupiny.php" class="list-group-item list-group-item-action">
              <i class="bi bi-people feature-icon"></i>Moje skupiny – filtr a výpis
            </a>
          </div>
          
          
          
         <div class="section-title">🔗 Ostatní</div>
          <div class="list-group">
            <a href="prehled_sportovcu.php" class="list-group-item list-group-item-action">
              <i class="bi bi-person-lines-fill feature-icon"></i>Seznam sportovců - odkazy
            </a>
            <a href="prehled_skupin.php" class="list-group-item list-group-item-action">
              <i class="bi bi-list-check feature-icon"></i>Seznam skupin
            </a>
            <form method="post" action="logout.php">
              <?= csrf_field() ?>
              <button type="submit" class="list-group-item list-group-item-action text-danger border-0 text-start w-100">
                <i class="bi bi-box-arrow-right feature-icon"></i>Odhlásit se
              </button>
            </form>
          </div>
        </div>

        <div class="col-md-6">
              <div class="section-title">Kniha jízd</div>
          <div class="list-group">
          
            <a href="jizdy/formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-bicycle feature-icon"></i>Vložit nový závod - výsledky
            </a>
            
               </div>
          <div class="section-title">Sportovní akce</div>
          <div class="list-group">
          
            <a href="formular_zavod.php" class="list-group-item list-group-item-action">
              <i class="bi bi-bicycle feature-icon"></i>Vložit nový závod - výsledky
            </a>
            
               </div>
                  
            <div class="section-title">🧾 Účetnictví</div>
          <div class="list-group">
            <a href="uctenky/formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-receipt feature-icon"></i>Vložit nový doklad</a>
              
            <a href="tankovani/formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>vložit nové tankování</a>
            <a href="udalosti/formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Vložit novou akci (soustředění, závod...)</a>
         
            <?php if ($is_hlavni): ?>  
              
            <a href="uctenky/seznam.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Seznam účtenek</a>
            <a href="tankovani/seznam.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Seznam tankování</a>
            <a href="jizdy/seznam.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>výpis knihy jízd</a>
              
                       <a href="vypis_vsech_vykazu.php" class="list-group-item list-group-item-action">
              <i class="bi bi-list-ul feature-icon"></i>Výpis všech výkazů
            </a>
          <?php endif; ?>
          
           </div>
          
          
          
        <?php if ($is_hlavni): ?>  
              
            <a href="vozidla/seznam.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Seznam vozidel</a>
              
             <a href="vozidla/formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Přidat vozidlo</a>
          
          <?php endif; ?>
          
        <div class="section-title">AUTA</div>
          <div class="list-group">
            <a href="jizdy/formular.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Přidat záznam do knihy jízd</a>
                       <a href="/servis/seznam.php" class="list-group-item list-group-item-action">
              <i class="bi bi-clock-history feature-icon"></i>Seznam vozidel - SERVIS</a>
          </div>
        
          <div class="section-title">🎨 Stories a promo</div>
          <div class="list-group">
            <a href="nastaveni_story.php" class="list-group-item list-group-item-action">
              <i class="bi bi-palette feature-icon"></i>Vzhled stories
            </a>
            <a href="prehled_stories.php" class="list-group-item list-group-item-action">
              <i class="bi bi-images feature-icon"></i>Přehled stories
            </a>
          </div>
          
            
          
          <div class="col-md-6">
          <div class="section-title">🚗 Auto a kniha jízd</div>
          <div class="list-group">
            <a href="kniha_jizd.php" class="list-group-item list-group-item-action">
              <i class="bi bi-car-front feature-icon"></i>Vložit záznam do knihy jízd</a>
            <small class="text-muted ps-4">(doplňte <code>?auto_id=123</code>)</small>
          </div>

          <?php if ($is_hlavni): ?>
            <div class="section-title">⚙️ Administrace</div>
            <div class="list-group">
              <a href="sprava_vsech_treninku.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-ul feature-icon"></i>Správa všech tréninků
              </a>
              <a href="sprava_zavodu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-ol feature-icon"></i>Správa závodů
              </a>
              <a href="sprava_skupin.php" class="list-group-item list-group-item-action">
                <i class="bi bi-list-columns feature-icon"></i>Správa skupin
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
              <a href="prehled_vsech_vykazu.php" class="list-group-item list-group-item-action">
                <i class="bi bi-graph-up feature-icon"></i>Výpis všech výkazů
              </a>
            </div>
          <?php endif; ?>

  
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
