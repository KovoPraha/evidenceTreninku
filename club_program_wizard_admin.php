<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/club_program_wizard.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function cpwh(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cpwMinor(string $value): int
{
    $value = trim(str_replace([' ', chr(194) . chr(160), ','], ['', '', '.'], $value));
    if (preg_match('/^[0-9]{1,7}(?:[.][0-9]{1,2})?$/D', $value) !== 1) {
        throw new InvalidArgumentException('Cena musí být částka v Kč, například 2500.');
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
}

/** @param array<string,mixed> $input @param array<string,mixed> $reference @return array<string,mixed> */
function cpwSimpleDefaults(array $input, string $requestKey, array $reference): array
{
    $name = trim((string)($input['name'] ?? ''));
    $startsOn = (string)($input['starts_on'] ?? '');
    $endsOn = (string)($input['ends_on'] ?? '');
    $startYear = preg_match('/^(\d{4})-/', $startsOn, $match) === 1 ? (int)$match[1] : (int)date('Y');
    $endYear = preg_match('/^(\d{4})-/', $endsOn, $match) === 1 ? (int)$match[1] : $startYear + 1;
    $suffix = strtoupper(substr($requestKey, 0, 6));

    $input['description'] = trim((string)($input['description'] ?? '')) ?: 'Kroužek ' . $name;
    $input['currency'] = 'CZK';
    $input['category_path'] = 'Kroužky';
    $input['includes_vat'] = '';
    $input['vat_rate_basis_points'] = '';
    $input['sales_open_at'] = date('Y-m-d') . 'T00:00';
    $input['sales_close_at'] = $startsOn !== '' ? $startsOn . 'T23:59' : '';
    foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
        $terms = $reference['terms'][$purpose] ?? [];
        if (is_array($terms) && $terms !== [] && (int)($terms[0]['id'] ?? 0) > 0) {
            $input[$purpose . '_source'] = 'existing';
            $input[$purpose . '_version_id'] = (string)$terms[0]['id'];
            continue;
        }
        $text=trim((string)($input[$purpose.'_text']??''));
        if($text===''||mb_strlen($text,'UTF-8')>4000||str_contains($text,CLUB_PROGRAM_TERM_DRAFT_MARKER)){
            throw new ClubProgramWizardException('Před prvním zveřejněním vyplňte a zkontrolujte oba texty klubových podmínek. Vzorový text označený jako VZOR nelze zveřejnit.');
        }
        if(($input['terms_confirmed']??'')!=='1')throw new ClubProgramWizardException('Potvrďte, že jste zkontrolovali první znění klubových podmínek.');
        $input[$purpose . '_source'] = 'new';
        $input[$purpose . '_text'] = $text;
    }
    $input['reason'] = ($input['source_mode']??'new')==='existing'
        ? 'Napojení existujícího produktu na kroužkový program.'
        : 'Vypsání nového kroužku.';
    $input['confirmed'] = true;

    if ((int)($input['team_id'] ?? 0) > 0) {
        $input['team_mode'] = 'existing';
        return $input;
    }

    $input['team_mode'] = 'new';
    $input['season_code'] = 'KROUZKY-' . $startYear . '-' . $endYear . '-' . $suffix;
    $input['season_name'] = 'Kroužky ' . $startYear . '/' . $endYear;
    $input['season_type'] = $startYear === $endYear ? 'calendar_year' : 'school_year';
    $input['season_starts_on'] = $startsOn;
    $input['season_ends_on'] = $endsOn;
    $input['team_code'] = 'KROUZEK-' . $suffix;
    $input['team_name'] = $name;
    $input['team_discipline'] = 'Všeobecná cyklistická příprava';
    $from = trim((string)($input['birth_year_from'] ?? ''));
    $to = trim((string)($input['birth_year_to'] ?? ''));
    $input['team_age_label'] = $from !== '' || $to !== '' ? 'Ročníky ' . ($from ?: 'bez omezení') . '–' . ($to ?: 'bez omezení') : 'Děti';
    return $input;
}

$errors = [];
$actorId = (int)$_SESSION['trener_id'];
$reference = clubProgramWizardReferenceData($pdo);
if (!isset($_SESSION['club_program_wizard_key']) || preg_match('/^[a-f0-9]{32}$/D', (string)$_SESSION['club_program_wizard_key']) !== 1) {
    $_SESSION['club_program_wizard_key'] = bin2hex(random_bytes(16));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $key = (string)($_POST['request_key'] ?? '');
            if (!hash_equals((string)$_SESSION['club_program_wizard_key'], $key)) {
                throw new InvalidArgumentException('Formulář už byl odeslán nebo vypršel. Obnovte stránku.');
            }
            $input = cpwSimpleDefaults($_POST, $key, $reference);
            $input['request_key'] = $key;
            $input['amount_minor'] = (string)($_POST['source_mode'] ?? 'new') === 'existing'
                ? 0
                : cpwMinor((string)($_POST['amount'] ?? ''));
            $upload = $_FILES['product_image'] ?? null;
            $source = null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int)$upload['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Nahrání obrázku nebylo dokončeno.');
                $source = (string)$upload['tmp_name'];
            }
            $result = clubProgramWizardCreate($pdo, $actorId, $input, $source, true, __DIR__);
            unset($_SESSION['club_program_wizard_key']);
            $_SESSION['club_program_wizard_flash'] = (string)($_POST['source_mode'] ?? 'new') === 'existing'
                ? 'Existující produkt byl napojen na kroužek a zveřejněn.'
                : 'Kroužek byl založen a zveřejněn.';
            header('Location: club_program_wizard_admin.php?hotovo=' . (int)$result['product_id'], true, 303);
            exit;
        } catch (Throwable $exception) {
            if (!($exception instanceof InvalidArgumentException
                || $exception instanceof ShopManualCatalogException
                || $exception instanceof ShopProductImageException
                || $exception instanceof KisRosterException
                || $exception instanceof ClubProgramException
                || $exception instanceof ClubProgramTermsException
                || $exception instanceof ShopCatalogPublicationException
                || $exception instanceof ClubProgramWizardException)) {
                error_log('club_program_wizard_admin.php: ' . $exception->getMessage());
            }
            $errors[] = $exception->getMessage();
        }
    }
}

$success = (string)($_SESSION['club_program_wizard_flash'] ?? '');
unset($_SESSION['club_program_wizard_flash']);
$activeTeams = array_values(array_filter($reference['teams'], static fn(array $team): bool => (string)$team['status'] === 'active'));
$existingProducts = $reference['products'] ?? [];
$termsReady=true;
foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){$terms=$reference['terms'][$purpose]??[];if(!is_array($terms)||$terms===[]||(int)($terms[0]['id']??0)<1)$termsReady=false;}
$key = (string)$_SESSION['club_program_wizard_key'];
$old = static fn(string $field, string $default = ''): string => (string)($_POST[$field] ?? $default);
$requestedProductId=max(0,(int)($_GET['product_id']??0));$requestedVariantId='';
foreach($existingProducts as$candidate)if((int)$candidate['product_id']===$requestedProductId){$requestedVariantId=(string)$candidate['variant_id'];break;}
$sourceMode=$old('source_mode',$requestedVariantId!==''?'existing':'new');
$today = new DateTimeImmutable('today');
$start = $today->modify('first day of next month');
$end = $start->modify('+9 months -1 day');
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Vypsat kroužek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:900px">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h3 mb-1"><i class="bi bi-plus-circle me-2 text-primary"></i>Vypsat kroužek</h1><p class="text-muted mb-0">Jeden formulář. Po uložení je kroužek rovnou připravený pro přihlášky.</p></div>
        <a class="btn btn-outline-secondary btn-sm" href="club_program_offers_admin.php">Zpět na kroužky</a>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= cpwh($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success d-flex justify-content-between align-items-center"><span><?= cpwh($success) ?></span><a class="btn btn-sm btn-success" href="club_program_offers_admin.php">Otevřít správu kroužků</a></div><?php endif; ?>
    <?php if($termsReady):?><div class="alert alert-success"><i class="bi bi-shield-check me-2"></i>Klubové storno podmínky a souhlas jsou připravené. Průvodce použije jejich poslední schválené znění.</div><?php else:?><div class="alert alert-warning"><strong>Před prvním kroužkem je potřeba jednou zadat klubové podmínky.</strong> Vyplňte je přímo níže; při uložení se bezpečně založí spolu s kroužkem. Další kroužky už převezmou schválené znění automaticky.</div><?php endif;?>
    <form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <?= csrf_field() ?><input type="hidden" name="request_key" value="<?= cpwh($key) ?>">
            <div class="col-12"><fieldset><legend class="form-label fw-semibold">Co chcete udělat?</legend><div class="row g-2"><div class="col-md-6"><label class="border rounded p-3 d-block h-100"><input class="form-check-input me-2" type="radio" name="source_mode" value="existing" <?=$sourceMode==='existing'?'checked':''?> <?= $existingProducts===[]?'disabled':'' ?>><strong>Použít existující produkt</strong><span class="d-block small text-muted mt-1">Například už zveřejněný produkt 243. Nevznikne duplicita.</span></label></div><div class="col-md-6"><label class="border rounded p-3 d-block h-100"><input class="form-check-input me-2" type="radio" name="source_mode" value="new" <?=$sourceMode==='new'?'checked':''?>><strong>Vytvořit nový produkt</strong><span class="d-block small text-muted mt-1">Pro kroužek, který v katalogu ještě není.</span></label></div></div></fieldset></div>
            <div class="col-12" data-existing-product><label class="form-label">Existující produkt a varianta</label><select class="form-select form-select-lg" name="existing_variant_id"><option value="">Vyberte produkt</option><?php foreach($existingProducts as$candidate):$candidateName=(string)($candidate['public_name']?:$candidate['name']);?><option value="<?=(int)$candidate['variant_id']?>" data-name="<?=cpwh($candidateName)?>" data-description="<?=cpwh((string)($candidate['public_summary']?:$candidate['short_description']))?>" data-amount="<?=cpwh(number_format((int)$candidate['amount_minor']/100,2,',',''))?>" <?= $old('existing_variant_id',$requestedVariantId)===(string)$candidate['variant_id']?'selected':'' ?>><?=cpwh('#'.(int)$candidate['product_id'].' · '.$candidateName.' · '.$candidate['sku'].' · '.number_format((int)$candidate['amount_minor']/100,2,',',' ').' Kč')?></option><?php endforeach;?></select><div class="form-text">Průvodce odstraní skladovou logiku, zachová cenu a veřejný text a připojí termín, kapacitu a soupisku.</div></div>
            <div class="col-md-7" data-new-product><label class="form-label">Název kroužku</label><input class="form-control form-control-lg" name="name" maxlength="160" value="<?= cpwh($old('name')) ?>" required autofocus></div>
            <div class="col-md-5" data-new-product><label class="form-label">Cena v Kč</label><input class="form-control form-control-lg" name="amount" inputmode="decimal" value="<?= cpwh($old('amount')) ?>" placeholder="např. 2500" required></div>
            <div class="col-12" data-new-product><label class="form-label">Krátký popis <span class="text-muted">(nepovinné)</span></label><textarea class="form-control" name="description" maxlength="4000" rows="2"><?= cpwh($old('description')) ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Od</label><input class="form-control" type="date" name="starts_on" value="<?= cpwh($old('starts_on', $start->format('Y-m-d'))) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Do</label><input class="form-control" type="date" name="ends_on" value="<?= cpwh($old('ends_on', $end->format('Y-m-d'))) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Počet míst</label><input class="form-control" type="number" min="1" max="100000" name="capacity" value="<?= cpwh($old('capacity', '12')) ?>" required></div>
            <div class="col-md-7"><label class="form-label">První platební varianta</label><select class="form-select" name="purchase_option"><option value="full_year" <?=$old('purchase_option','full_year')==='full_year'?'selected':''?>>Celý rok</option><option value="first_half" <?=$old('purchase_option')==='first_half'?'selected':''?>>1. pololetí</option><option value="second_half" <?=$old('purchase_option')==='second_half'?'selected':''?>>2. pololetí</option><option value="custom" <?=$old('purchase_option')==='custom'?'selected':''?>>Jiná</option></select><div class="form-text">Další pololetí přidáte po uložení u stejné skupiny a soupisky.</div></div>
            <div class="col-md-5 d-flex align-items-center"><label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="is_featured" value="1" <?=isset($_POST['is_featured'])?'checked':''?>> Zvýraznit jako nejvýhodnější</label></div>
            <div class="col-md-4"><label class="form-label">Nejmladší ročník <span class="text-muted">(nepovinné)</span></label><input class="form-control" type="number" min="1900" max="<?= date('Y') ?>" name="birth_year_from" value="<?= cpwh($old('birth_year_from')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Nejstarší ročník <span class="text-muted">(nepovinné)</span></label><input class="form-control" type="number" min="1900" max="<?= date('Y') ?>" name="birth_year_to" value="<?= cpwh($old('birth_year_to')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Obrázek <span class="text-muted">(nepovinné)</span></label><input class="form-control" type="file" name="product_image" accept="image/jpeg,image/png"></div>
            <?php if ($activeTeams !== []): ?><div class="col-12"><label class="form-label">Soupiska pro přihlášené <span class="text-muted">(nepovinné)</span></label><select class="form-select" name="team_id"><option value="">Vytvořit novou automaticky</option><?php foreach ($activeTeams as $team): ?><option value="<?= (int)$team['id'] ?>" <?= $old('team_id') === (string)$team['id'] ? 'selected' : '' ?>><?= cpwh($team['season_name'] . ' · ' . $team['name']) ?></option><?php endforeach; ?></select><div class="form-text">Pokud nevyberete existující soupisku, systém založí novou jen pro tento kroužek.</div></div><?php endif; ?>
            <?php if(!$termsReady):?><div class="col-12"><h2 class="h5 mt-2">První klubové podmínky</h2><p class="text-muted small">Zadejte konečné znění schválené klubem. Text se zobrazí rodiči před objednávkou a uloží se do neměnného snapshotu objednávky.</p></div><div class="col-12"><label class="form-label req">Storno podmínky kroužku</label><textarea class="form-control" name="program_cancellation_text" maxlength="4000" rows="5" required placeholder="Kdy a za jakých podmínek lze účast zrušit a jak se řeší vrácení ceny."><?=cpwh($old('program_cancellation_text'))?></textarea></div><div class="col-12"><label class="form-label req">Souhlas s účastí</label><textarea class="form-control" name="program_consent_text" maxlength="4000" rows="5" required placeholder="S čím rodič nebo účastník souhlasí při přihlášení do kroužku."><?=cpwh($old('program_consent_text'))?></textarea></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="terms_confirmed" value="1" id="terms-confirmed" required <?=$old('terms_confirmed')==='1'?'checked':''?>><label class="form-check-label" for="terms-confirmed">Potvrzuji, že oba texty jsou konečné a schválené klubem.</label></div><?php endif;?>
            <div class="col-12"><div class="alert alert-light border mb-0">Systém připraví kategorii, prodejní období, produkt i soupisku. Podmínky budou před nákupem vždy viditelné rodiči.</div></div>
            <div class="col-md-5 d-grid ms-auto"><button class="btn btn-primary btn-lg"><i class="bi bi-check2-circle me-2"></i>Vypsat a zveřejnit kroužek</button></div>
        </div>
    </form>
</main>
<script>
(()=>{const radios=[...document.querySelectorAll('input[name="source_mode"]')];const existing=document.querySelector('[data-existing-product]');const fresh=[...document.querySelectorAll('[data-new-product]')];const select=existing?.querySelector('select');const apply=()=>{const mode=radios.find(r=>r.checked)?.value||'new';const useExisting=mode==='existing';existing?.classList.toggle('d-none',!useExisting);if(select)select.required=useExisting;for(const block of fresh){block.classList.toggle('d-none',useExisting);for(const input of block.querySelectorAll('input,textarea'))input.required=!useExisting&&input.name!=='description';}};for(const radio of radios)radio.addEventListener('change',apply);apply();})();
</script>
</body>
</html>
