<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/club_program_year_offer.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
function yearOfferH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function yearOfferMoney(int $minor): string
{
    return number_format($minor / 100, 0, ',', ' ').' Kč';
}
function yearOfferMinor(string $value): int
{
    $value = trim(str_replace([' ',chr(194).chr(160),','], ['','','.'], $value));
    if (preg_match('/^[0-9]{1,7}(?:[.][0-9]{1,2})?$/D', $value) !== 1) {
        throw new InvalidArgumentException('Zadejte schválenou cenu v Kč, například 7900.');
    }
    [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
    $minor = (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
    if ($minor < 1) {
        throw new InvalidArgumentException('Cena musí být kladná.');
    }
    return $minor;
}
$errors = [];
$preview = null;
$candidates = clubProgramYearOfferCandidates($pdo);
$byId = [];
foreach ($candidates as $candidate) {
    $byId[(int)$candidate['base_offer_id']] = $candidate;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'preview') {
                $selected = $_POST['selected'] ?? [];
                if (!is_array($selected) || $selected === [] || count($selected) > 50) {
                    throw new InvalidArgumentException('Vyberte alespoň jednu skupinu.');
                }
                $prices = [];
                $fingerprints = [];
                foreach ($selected as $rawId) {
                    if (!is_scalar($rawId) || !ctype_digit((string)$rawId)) {
                        throw new InvalidArgumentException('Výběr skupin není platný.');
                    }
                    $id = (int)$rawId;
                    $candidate = $byId[$id] ?? null;
                    if ($candidate === null || $candidate['problem'] !== '' || isset($prices[$id])) {
                        throw new InvalidArgumentException('Vybraná skupina není připravena pro celý rok.');
                    }
                    $postedPrices = $_POST['price'] ?? [];
                    if (!is_array($postedPrices) || !isset($postedPrices[$id]) || !is_scalar($postedPrices[$id])) {
                        throw new InvalidArgumentException('Vyplňte schválenou cenu každé vybrané skupiny.');
                    }
                    $prices[$id] = yearOfferMinor((string)$postedPrices[$id]);
                    $fingerprints[$id] = (string)$candidate['fingerprint'];
                }
                $_SESSION['year_offer_preview'] = [
                    'token' => bin2hex(random_bytes(16)),'prices' => $prices,'fingerprints' => $fingerprints,'created_at' => time()
                ];
                $preview = $_SESSION['year_offer_preview'];
            } elseif ($action === 'publish') {
                $stored = $_SESSION['year_offer_preview'] ?? null;
                if (!is_array($stored) || time() - (int)($stored['created_at'] ?? 0) > 900
                    || !hash_equals((string)($stored['token'] ?? ''), (string)($_POST['preview_token'] ?? ''))) {
                    throw new InvalidArgumentException('Náhled vypršel. Připravte jej znovu.');
                }
                if (($_POST['confirmed'] ?? '') !== '1') {
                    throw new InvalidArgumentException('Potvrďte zveřejnění schválených cen.');
                }
                $created = clubProgramYearOfferCreateBatch($pdo, (int)$_SESSION['trener_id'], $stored['prices'], $stored['fingerprints'], true);
                unset($_SESSION['year_offer_preview']);
                $_SESSION['year_offer_flash'] = 'Celoroční nabídky byly zveřejněny: '.count($created).'.';
                header('Location: club_program_year_offer_admin.php', true, 303);
                exit;
            } else {
                throw new InvalidArgumentException('Neznámý krok formuláře.');
            }
        } catch (Throwable $exception) {
            if (!($exception instanceof InvalidArgumentException || $exception instanceof ClubProgramYearOfferException
                || $exception instanceof ClubProgramStorefrontException || $exception instanceof ClubProgramException
                || $exception instanceof ShopManualCatalogException)) {
                error_log('club_program_year_offer_admin.php: '.$exception->getMessage());
            }
            $errors[] = ($exception instanceof InvalidArgumentException || $exception instanceof ClubProgramYearOfferException
                || $exception instanceof ClubProgramStorefrontException || $exception instanceof ClubProgramException
                || $exception instanceof ShopManualCatalogException)
                ? $exception->getMessage() : 'Nabídky se nepodařilo vytvořit; žádná část dávky nebyla uložena.';
        }
    }
}
$flash = (string)($_SESSION['year_offer_flash'] ?? '');
unset($_SESSION['year_offer_flash']);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Doplnit celoroční nabídku</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head><body class="bg-light"><?php include __DIR__.'/hlavicka.php';?>
<main class="container py-4" style="max-width:1100px">
<div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h1 class="h3 mb-1">Doplnit celoroční nabídku</h1>
<p class="text-muted mb-0">Vyberte existující skupiny a zadejte schválené ceny. Produkt, soupiska, kapacita a ročníky zůstanou propojené s pololetími.</p></div>
<a class="btn btn-outline-secondary btn-sm" href="club_program_offers_admin.php">Zpět na kroužky</a></div>
<?php foreach ($errors as $error):?><div class="alert alert-danger"><?=yearOfferH($error)?></div><?php endforeach;?>
<?php if ($flash !== ''):?><div class="alert alert-success"><?=yearOfferH($flash)?></div><?php endif;?>
<?php if ($preview !== null):?>
<div class="card border-primary shadow-sm"><div class="card-header fw-semibold">Kontrola před zveřejněním</div><div class="card-body">
<p>Vybrané nabídky použijí stávající produkt a soupisku. Nový kroužek ani rozvrh nevznikne.</p>
<div class="table-responsive">
<table class="table align-middle">
<thead>
<tr>
<th>Skupina</th>
<th>Platnost a prodej</th>
<th>Soupiska</th>
<th>Kapacita / ročníky</th>
<th>Schválená cena</th>
</tr>
</thead>
<tbody>
<?php foreach ($preview['prices'] as $id => $price):$candidate = $byId[(int)$id] ?? null;
    if ($candidate === null) {
        continue;
    }?>
<tr><td><strong><?=yearOfferH($candidate['program_name'])?></strong><div class="small text-muted">Celý rok <?=yearOfferH($candidate['year_label'])?></div></td>
<td><?=yearOfferH($candidate['starts_on'].' – '.$candidate['ends_on'])?><div class="small text-muted">Prodej <?=yearOfferH($candidate['sales_open_at'].' – '.$candidate['sales_close_at'])?></div></td>
<td><?=yearOfferH($candidate['team_name'])?></td>
<td><?=yearOfferH($candidate['capacity'] ?? 'bez limitu')?> / <?=yearOfferH(($candidate['birth_year_from'] ?? '–').'–'.($candidate['birth_year_to'] ?? '–'))?></td>
<td><strong><?=yearOfferH(yearOfferMoney($price))?></strong></td></tr>
<?php endforeach;?></tbody></table></div>
<form method="post" class="d-flex flex-wrap justify-content-between align-items-end gap-3"><?=csrf_field()?>
<input type="hidden" name="action" value="publish"><input type="hidden" name="preview_token" value="<?=yearOfferH($preview['token'])?>">
<a class="btn btn-outline-secondary" href="club_program_year_offer_admin.php">Zpět k výběru</a>
<div><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required> Potvrzuji schválené ceny a zveřejnění všech vybraných nabídek.</label>
<button class="btn btn-primary">Zveřejnit <?=count($preview['prices'])?> <?=count($preview['prices']) === 1 ? 'nabídku' : 'nabídky'?></button></div></form>
</div></div>
<?php else:?>
<div class="alert alert-info">Cena se nepřebírá z pololetí automaticky. Před zveřejněním uvidíte souhrn všech vybraných skupin.</div>
<?php if ($candidates === []):?><div class="alert alert-secondary">Žádný kroužek se dvěma pololetími bez celoroční varianty nyní není k dispozici.</div>
<?php else:?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="preview">
<div class="card border-0 shadow-sm"><div class="card-body">
<label class="form-label" for="year-offer-search">Najít skupinu</label><input class="form-control mb-3" type="search" id="year-offer-search" placeholder="Například Točňáci">
<div class="table-responsive">
<table class="table align-middle mb-0">
<thead>
<tr>
<th>Vybrat</th>
<th>Kroužek a rok</th>
<th>Platnost a soupiska</th>
<th>Pololetní ceny</th>
<th>Schválená cena za celý rok</th>
</tr>
</thead>
<tbody>
<?php foreach ($candidates as $candidate):$id = (int)$candidate['base_offer_id'];?>
<tr data-year-offer-row data-search="<?=yearOfferH(mb_strtolower($candidate['program_name'].' '.$candidate['team_name'].' '.$candidate['year_label'], 'UTF-8'))?>">
<td>
<input class="form-check-input" type="checkbox" name="selected[]" value="<?=$id?>" aria-label="Vybrat <?=yearOfferH($candidate['program_name'])?>" <?=$candidate['problem'] !== '' ? 'disabled' : ''?>>
</td>
<td>
<strong>
<?=yearOfferH($candidate['program_name'])?>
</strong>
<div class="small text-muted">
<?=yearOfferH($candidate['year_label'])?>
</div>
<?php if ($candidate['problem'] !== ''):?>
<div class="small text-danger">
<?=yearOfferH($candidate['problem'])?>
</div>
<?php endif;?>
</td>
<td>
<?=yearOfferH($candidate['starts_on'].' – '.$candidate['ends_on'])?>
<div class="small text-muted">
<?=yearOfferH($candidate['team_name'])?> · kapacita <?=yearOfferH($candidate['capacity'] ?? 'bez limitu')?>
</div>
</td>
<td><?=yearOfferH(yearOfferMoney($candidate['first_price_minor']))?> + <?=yearOfferH(yearOfferMoney($candidate['second_price_minor']))?></td>
<td>
<label class="visually-hidden" for="price-<?=$id?>">Celoroční cena <?=yearOfferH($candidate['program_name'])?>
</label>
<input class="form-control" id="price-<?=$id?>" name="price[<?=$id?>]" inputmode="decimal" placeholder="Kč" <?=$candidate['problem'] !== '' ? 'disabled' : ''?>>
</td>
</tr>
<?php endforeach;?></tbody></table></div></div>
<div class="card-footer bg-white d-flex justify-content-end"><button class="btn btn-primary">Zkontrolovat vybrané nabídky</button></div></div></form>
<?php endif;endif;?>
</main><script nonce="<?=yearOfferH(app_csp_nonce())?>">
(() => {
    const search = document.getElementById('year-offer-search');
    if (!search) return;
    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('cs');
        document.querySelectorAll('[data-year-offer-row]').forEach(row => {
            row.hidden = !row.dataset.search.includes(query);
        });
    });
})();
</script></body></html>
