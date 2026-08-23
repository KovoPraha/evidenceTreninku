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
        if (!is_array($terms) || $terms === [] || (int)($terms[0]['id'] ?? 0) < 1) {
            throw new ClubProgramWizardException('Kroužek nelze zveřejnit, dokud správce jednou neschválí klubové podmínky. Otevřete Pokročilé nástroje → Programy a podmínky.');
        }
        $input[$purpose . '_source'] = 'existing';
        $input[$purpose . '_version_id'] = (string)$terms[0]['id'];
    }
    $input['reason'] = 'Vypsání nového kroužku.';
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
            $input['amount_minor'] = cpwMinor((string)($_POST['amount'] ?? ''));
            $upload = $_FILES['product_image'] ?? null;
            $source = null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int)$upload['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Nahrání obrázku nebylo dokončeno.');
                $source = (string)$upload['tmp_name'];
            }
            $result = clubProgramWizardCreate($pdo, $actorId, $input, $source, true, __DIR__);
            unset($_SESSION['club_program_wizard_key']);
            $_SESSION['club_program_wizard_flash'] = 'Kroužek byl založen a zveřejněn.';
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
$key = (string)$_SESSION['club_program_wizard_key'];
$old = static fn(string $field, string $default = ''): string => (string)($_POST[$field] ?? $default);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    <form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <?= csrf_field() ?><input type="hidden" name="request_key" value="<?= cpwh($key) ?>">
            <div class="col-md-7"><label class="form-label">Název kroužku</label><input class="form-control form-control-lg" name="name" maxlength="160" value="<?= cpwh($old('name')) ?>" required autofocus></div>
            <div class="col-md-5"><label class="form-label">Cena v Kč</label><input class="form-control form-control-lg" name="amount" inputmode="decimal" value="<?= cpwh($old('amount')) ?>" placeholder="např. 2500" required></div>
            <div class="col-12"><label class="form-label">Krátký popis <span class="text-muted">(nepovinné)</span></label><textarea class="form-control" name="description" maxlength="4000" rows="2"><?= cpwh($old('description')) ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Od</label><input class="form-control" type="date" name="starts_on" value="<?= cpwh($old('starts_on', $start->format('Y-m-d'))) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Do</label><input class="form-control" type="date" name="ends_on" value="<?= cpwh($old('ends_on', $end->format('Y-m-d'))) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Počet míst</label><input class="form-control" type="number" min="1" max="100000" name="capacity" value="<?= cpwh($old('capacity', '12')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Nejmladší ročník <span class="text-muted">(nepovinné)</span></label><input class="form-control" type="number" min="1900" max="<?= date('Y') ?>" name="birth_year_from" value="<?= cpwh($old('birth_year_from')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Nejstarší ročník <span class="text-muted">(nepovinné)</span></label><input class="form-control" type="number" min="1900" max="<?= date('Y') ?>" name="birth_year_to" value="<?= cpwh($old('birth_year_to')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Obrázek <span class="text-muted">(nepovinné)</span></label><input class="form-control" type="file" name="product_image" accept="image/jpeg,image/png"></div>
            <?php if ($activeTeams !== []): ?><div class="col-12"><label class="form-label">Soupiska pro přihlášené <span class="text-muted">(nepovinné)</span></label><select class="form-select" name="team_id"><option value="">Vytvořit novou automaticky</option><?php foreach ($activeTeams as $team): ?><option value="<?= (int)$team['id'] ?>" <?= $old('team_id') === (string)$team['id'] ? 'selected' : '' ?>><?= cpwh($team['season_name'] . ' · ' . $team['name']) ?></option><?php endforeach; ?></select><div class="form-text">Pokud nevyberete existující soupisku, systém založí novou jen pro tento kroužek.</div></div><?php endif; ?>
            <div class="col-12"><div class="alert alert-light border mb-0">Systém automaticky použije schválené klubové podmínky a připraví kategorii, prodejní období, produkt i soupisku. Nic dalšího nebude potřeba doplňovat.</div></div>
            <div class="col-md-5 d-grid ms-auto"><button class="btn btn-primary btn-lg"><i class="bi bi-check2-circle me-2"></i>Vypsat a zveřejnit kroužek</button></div>
        </div>
    </form>
</main>
</body>
</html>
