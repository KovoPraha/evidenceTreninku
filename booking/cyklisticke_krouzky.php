<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/ui_shell.php';
require_once dirname(__DIR__) . '/includes/club_program_storefront.php';
require_once dirname(__DIR__) . '/includes/legacy_club_catalog.php';
require_once dirname(__DIR__) . '/includes/club_landing_filter.php';
require_once dirname(__DIR__) . '/includes/public_seo.php';

function clubLandingH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clubLandingMoney(int $minor, string $currency): string
{
    return number_format($minor / 100, 0, ',', ' ') . ' ' . clubLandingH($currency === 'CZK' ? 'Kč' : $currency);
}

$canonicalPrograms = clubProgramStorefrontCatalog($pdo);
$programs = array_merge($canonicalPrograms, legacyClubCatalogFallback($canonicalPrograms));
$hasSaleableProgram = false;
foreach ($programs as $listedProgram) {
    if (clubLandingFirstSaleableProductId($listedProgram) > 0) {
        $hasSaleableProgram = true;
        break;
    }
}
$fallbackImages = [
    'assets/clubs/children-group.webp',
    'assets/clubs/children-balance-bike.webp',
    'assets/clubs/children-with-coach.webp',
    'assets/clubs/children-trail.webp',
    'assets/clubs/children-descent.webp',
];
$weekdayOptions = [];
foreach ($programs as $program) {
    foreach ($program['schedule'] as $slot) {
        $weekdayOptions[(int)$slot['weekday']] = clubProgramStorefrontWeekdayLabel((int)$slot['weekday']);
    }
    foreach(($program['filter_days']??[])as$weekday)$weekdayOptions[(int)$weekday]=clubProgramStorefrontWeekdayLabel((int)$weekday);
}
ksort($weekdayOptions);
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cyklistické kroužky pro děti — KOVO Praha</title>
  <?php publicSeoHead('Cyklistické kroužky pro děti – KOVO Praha','Vyberte dětský cyklistický kroužek podle věku, dne a místa. Porovnejte rozvrh, volná místa i cenu přímo u KOVO Praha.','booking/cyklisticke_krouzky.php',appUrl('assets/clubs/children-group.webp')); ?>
  <?php appUiAssets(); ?>
  <style nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>">
    :root { --club-blue:#111a6b; --club-red:#ee2722; --club-cream:#fff9ed; }
    body { background:linear-gradient(180deg,#fff 0,#fff 24rem,var(--club-cream) 24rem,#fff 100%); }
    .club-hero { border-radius:1.8rem; color:#fff; overflow:hidden; background:linear-gradient(125deg,var(--club-blue),#263cb4 72%,var(--club-red)); box-shadow:0 1.5rem 3.5rem rgba(17,26,107,.22); }
    .club-hero-photo { min-height:330px; background:center/cover no-repeat; position:relative; }
    .club-hero-photo::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg,rgba(17,26,107,.65),transparent 60%); }
    .club-kicker { letter-spacing:.13em; text-transform:uppercase; font-size:.76rem; font-weight:800; color:#ffd36a; }
    .club-filter { border:0; border-radius:1.1rem; box-shadow:0 .5rem 1.8rem rgba(17,26,107,.09); }
    .club-card { border:0; border-radius:1.35rem; overflow:hidden; box-shadow:0 .55rem 2rem rgba(17,26,107,.1); }
    .club-card img { width:100%; height:148px; padding:.45rem; object-fit:contain; object-position:center; background:var(--club-cream); }
    .club-card { transition:transform .18s ease,box-shadow .18s ease; }
    .club-card:hover { transform:translateY(-2px); box-shadow:0 .85rem 2.2rem rgba(17,26,107,.15); }
    .club-chip { display:inline-flex; align-items:center; gap:.32rem; border-radius:999px; background:#eef0ff; color:var(--club-blue); padding:.34rem .68rem; font-size:.82rem; font-weight:650; }
    .club-offer { border:1px solid #dde1f6; border-radius:1rem; padding:.85rem; background:#fff; }
    .club-offer.featured { border:1px solid #a9bfe7; background:#f2f6ff; }
    .club-offer .price { color:var(--club-blue); font-size:1.14rem; font-weight:800; }
    .club-options { border:1px solid #d5dded; border-radius:1rem; background:#fff; }
    .club-options summary { cursor:pointer; list-style:none; padding:.85rem 1rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .club-options summary::-webkit-details-marker { display:none; }
    .club-options summary::after { content:'⌄'; font-size:1.35rem; line-height:1; color:var(--club-blue); transition:transform .18s ease; }
    .club-options[open] summary::after { transform:rotate(180deg); }
    .club-options-content { padding:0 1rem 1rem; }
    .club-options summary:focus-visible { outline:3px solid #146ad1; outline-offset:2px; }
    .club-empty { border:2px dashed #c8cde9; border-radius:1.2rem; background:#fff; }
    @media (max-width:991.98px) { .club-hero-photo { min-height:160px; } .club-card img { height:135px; } }
    @media (max-width:575.98px) { .club-hero h1 { font-size:1.85rem; } .club-hero .lead { font-size:1rem; } .club-hero-photo { min-height:125px; } }
    @media (prefers-reduced-motion:reduce) { .club-card,.club-options summary::after { transition:none; } .club-card:hover { transform:none; } }
  </style>
</head>
<body>
<?php publicShellNav('programs'); ?>
<main class="container py-4 py-lg-5" style="max-width:1200px">
  <section class="club-hero mb-4 mb-lg-5">
    <div class="row g-0 align-items-stretch">
      <div class="col-lg-7"><div class="p-4 p-lg-5 h-100 d-flex flex-column justify-content-center">
        <div class="club-kicker mb-2">KOVO Praha · tvoje cyklistická cesta</div>
        <h1 class="display-5 fw-bold mb-3">Kroužek vyberete podle dítěte, dne a místa.</h1>
        <p class="lead opacity-75 mb-4">Najděte správnou skupinu podle věku, dne a místa. Rozvrh a ceny máte na jednom místě.</p>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-light btn-lg" href="#nabidka">Vybrat kroužek</a><a class="btn btn-outline-light btn-lg" href="mailto:velodromtrebesin@gmail.com?subject=Dotaz%20na%20cyklistick%C3%BD%20krou%C5%BEek">Potřebuji poradit</a></div>
      </div></div>
      <div class="col-lg-5 club-hero-photo" style="background-image:url('<?=clubLandingH(appUiUrl('assets/clubs/children-group.webp'))?>')" role="img" aria-label="Děti na cyklistickém kroužku"></div>
    </div>
  </section>

  <section id="nabidka" aria-labelledby="nabidka-title">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><h2 id="nabidka-title" class="h2 mb-1">Aktuální kroužky</h2><p class="text-muted mb-0">Jedna skupina může mít více platebních variant. Dítě vždy míří do stejné soupisky.</p></div><?php if($hasSaleableProgram):?><a class="btn btn-outline-primary" href="eshop.php?kategorie=<?=rawurlencode('Kroužky')?>">Celá nabídka v e-shopu</a><?php endif;?></div>
    <?php if($programs!==[]&&!$hasSaleableProgram):?><div class="alert alert-info mb-4" role="status">Přihlášky do kroužků zatím nejsou otevřené. Rozvrh a ceny si můžete prohlédnout níže; na podrobnosti se zeptejte <a href="mailto:velodromtrebesin@gmail.com?subject=Dotaz%20na%20cyklistick%C3%BD%20krou%C5%BEek">klubu e-mailem</a>.</div><?php endif; ?>
    <?php if ($programs !== []): ?>
    <div class="card club-filter mb-3"><div class="card-body row g-3 align-items-end">
      <div class="col-md-3"><label class="form-label fw-semibold" for="filter-age">Věk dítěte</label><input class="form-control" id="filter-age" type="number" min="1" max="99" inputmode="numeric" placeholder="např. 5" aria-describedby="filter-age-help"><div class="form-text" id="filter-age-help">Nabídky bez uvedeného věku se při filtrování skryjí.</div></div>
      <div class="col-md-3"><label class="form-label fw-semibold" for="filter-day">Den</label><select class="form-select" id="filter-day"><option value="">Všechny dny</option><?php foreach ($weekdayOptions as $number=>$label): ?><option value="<?=$number?>"><?=clubLandingH($label)?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label fw-semibold" for="filter-text">Místo nebo název</label><input class="form-control" id="filter-text" type="search" placeholder="např. Třebešín"></div>
      <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="button" id="filter-reset" disabled>Zrušit filtry</button></div>
    </div></div>
    <p class="small text-muted mb-3" id="filter-count" role="status" aria-live="polite"></p>
    <?php endif; ?>

    <div class="row g-4" id="club-list">
    <?php foreach ($programs as $index=>$program):
        $days=array_map('strval',$program['filter_days']??[]);foreach($program['schedule']as$slot)$days[]=(string)(int)$slot['weekday'];$days=array_values(array_unique($days));
        $search=mb_strtolower(implode(' ',[(string)$program['public_name'],(string)$program['public_summary'],(string)$program['location_name'],(string)$program['age_label']]));
        $ageRange=clubLandingAgeRange((string)$program['age_label']);
        $image=$program['images'][0]['image_url']??appUiUrl($fallbackImages[$index%count($fallbackImages)]);
        $firstProductId=clubLandingFirstSaleableProductId($program);
        $lowestOffer=clubLandingLowestOffer($program['offers']);
        $yearSaving=clubLandingYearSaving($program['offers']);
        $offerCount=count($program['offers']);
    ?>
      <div class="col-lg-6 club-entry" data-days="<?=clubLandingH(implode(',',$days))?>" data-search="<?=clubLandingH($search)?>" data-age-min="<?=$ageRange[0]??''?>" data-age-max="<?=$ageRange[1]??''?>">
        <article class="card club-card h-100">
          <img src="<?=clubLandingH($image)?>" alt="<?=clubLandingH($program['images'][0]['alt_text']??('Cyklistický kroužek '.$program['public_name']))?>" loading="lazy">
          <div class="card-body p-4 d-flex flex-column">
            <h3 class="h4 fw-bold mb-2"><?=clubLandingH($program['public_name'])?></h3>
            <?php if((string)$program['public_summary']!==''):?><p class="text-muted"><?=nl2br(clubLandingH($program['public_summary']))?></p><?php endif;?>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php if((string)$program['age_label']!==''):?><span class="club-chip"><i class="bi bi-people"></i><?=clubLandingH($program['age_label'])?></span><?php endif;?>
              <?php if((string)$program['location_name']!==''):?><span class="club-chip"><i class="bi bi-geo-alt"></i><?=clubLandingH($program['location_name'])?></span><?php endif;?>
              <?php if(count($program['schedule'])>1&&str_contains((string)$program['public_name'],'1× týdně')):?><span class="small fw-semibold align-self-center">Jeden z těchto dnů; volbu potvrďte s klubem:</span><?php endif;?>
              <?php foreach($program['schedule']as$slot):?><span class="club-chip"><i class="bi bi-calendar3"></i><?=clubLandingH(clubProgramStorefrontWeekdayLabel((int)$slot['weekday']).' '.substr((string)$slot['starts_at'],0,5).'–'.substr((string)$slot['ends_at'],0,5))?></span><?php endforeach;?>
              <?php if(!empty($program['schedule_label'])):?><span class="club-chip"><i class="bi bi-calendar3"></i><?=clubLandingH($program['schedule_label'])?></span><?php endif;?>
            </div>
            <?php if($offerCount>0):?><details class="club-options mt-auto" <?=$offerCount===1?'open':''?>><summary><span><strong><?=$offerCount===1?'Cena a přihlášení':'Zobrazit možnosti platby'?></strong><?php if($yearSaving>0):?><span class="d-block small text-success">Celý rok ušetří <?=clubLandingMoney($yearSaving,(string)$lowestOffer['currency'])?></span><?php endif;?></span><span class="text-nowrap fw-bold text-primary"><?=$offerCount>1?'od ':''?><?=clubLandingMoney((int)$lowestOffer['amount_minor'],(string)$lowestOffer['currency'])?></span></summary><div class="club-options-content"><div class="vstack gap-2">
              <?php foreach($program['offers']as$offer):?>
                <div class="club-offer <?=!empty($offer['is_featured'])?'featured':''?>">
                  <div class="d-flex justify-content-between align-items-start gap-2"><div><strong><?=clubLandingH($offer['purchase_label'])?></strong><?php if(!empty($offer['is_featured'])&&$yearSaving>0):?><span class="badge text-bg-primary ms-1">výhodnější</span><?php endif;?></div><div class="price text-nowrap"><?=clubLandingMoney((int)$offer['amount_minor'],(string)$offer['currency'])?></div></div>
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2"><?php if(!empty($offer['saleable'])):?><span class="small text-success"><i class="bi bi-check-circle me-1"></i>Lze se přihlásit</span><?php endif; ?>
                  <?php if(!empty($offer['saleable'])):?><a class="btn btn-primary btn-sm" href="rychla_prihlaska.php?product_id=<?=(int)$offer['product_id']?>&amp;variant_id=<?=(int)$offer['variant_id']?>">Přihlásit dítě</a><?php endif;?></div>
                </div>
              <?php endforeach;?>
            </div></div></details><?php else:?><div class="alert alert-info mb-0 mt-auto">Termíny a ceny právě připravujeme.</div><?php endif; ?>
            <?php if($offerCount>0&&$firstProductId===0&&$hasSaleableProgram):?><p class="small text-muted mt-2 mb-0">Přihlášky do této skupiny zatím nejsou otevřené.</p><?php endif;?>
            <div class="vstack gap-2">
              <?php if(!empty($program['interest_enabled'])||$firstProductId===0):?><?php if($firstProductId>0):?><a class="btn btn-link px-0 text-start" href="produkt.php?id=<?=$firstProductId?>#mam-zajem"><i class="bi bi-envelope me-1"></i>Nevyhovuje vám termín? Nechte nám e-mail.</a><?php else:?><a class="btn btn-link px-0 text-start" href="mailto:velodromtrebesin@gmail.com?subject=Dotaz%20na%20krou%C5%BEek%20<?=rawurlencode((string)$program['public_name'])?>"><i class="bi bi-envelope me-1"></i>Zeptat se klubu na tento kroužek</a><?php endif;?><?php endif;?>
            </div>
          </div>
        </article>
      </div>
    <?php endforeach;?>
    <?php if($programs===[]):?><div class="col-12"><div class="club-empty text-center p-5"><i class="bi bi-bicycle display-5 text-primary"></i><h2 class="h4 mt-3">Nabídku kroužků připravujeme</h2><p class="text-muted mb-3">Chcete vědět, až otevřeme přihlášky?</p><a class="btn btn-primary" href="mailto:velodromtrebesin@gmail.com?subject=Z%C3%A1jem%20o%20cyklistick%C3%BD%20krou%C5%BEek">Napište nám</a></div></div><?php endif;?>
    </div>
    <div class="alert alert-secondary mt-4 d-none" id="no-filter-results">Zadaným filtrům neodpovídá žádný kroužek. Zrušte filtry nebo nám napište.</div>
  </section>
</main>
<?php publicShellFooter(); ?>
<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>">
(() => {
  const entries=[...document.querySelectorAll('.club-entry')];
  const day=document.getElementById('filter-day');
  const age=document.getElementById('filter-age');
  const text=document.getElementById('filter-text');
  if(!day||!age||!text)return;
  const reset=document.getElementById('filter-reset');
  const apply=()=>{const q=text.value.trim().toLocaleLowerCase('cs');const selectedAge=age.value===''?null:Number(age.value);let visible=0;entries.forEach(entry=>{const days=(entry.dataset.days||'').split(',');const ageMatches=selectedAge===null||(entry.dataset.ageMin!==''&&selectedAge>=Number(entry.dataset.ageMin)&&selectedAge<=Number(entry.dataset.ageMax));const show=ageMatches&&(!day.value||days.includes(day.value))&&(!q||(entry.dataset.search||'').includes(q));entry.classList.toggle('d-none',!show);if(show)visible++;});document.getElementById('no-filter-results')?.classList.toggle('d-none',visible>0);document.getElementById('filter-count').textContent=`Zobrazeno ${visible} z ${entries.length} kroužků`;if(reset)reset.disabled=selectedAge===null&&!day.value&&!q;};
  day.addEventListener('change',apply);age.addEventListener('input',apply);text.addEventListener('input',apply);reset?.addEventListener('click',()=>{day.value='';age.value='';text.value='';apply();});apply();
})();
</script>
</body>
</html>
