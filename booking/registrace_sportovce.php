<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=registrace_sportovce.php');
    exit;
}
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/athlete_registration.php';

function athleteRegistrationPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accountId = (int)$_SESSION['verejny_uzivatel_id'];
$errors = [];
$safeValues = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'submit') {
                athleteRegistrationSubmit(
                    $pdo,
                    $accountId,
                    $_POST,
                    is_array($_POST['term_version'] ?? null) ? $_POST['term_version'] : [],
                    is_array($_FILES['internal_photo'] ?? null) ? $_FILES['internal_photo'] : null
                );
                $_SESSION['athlete_registration_success'] = 'Žádost jsme přijali. Administrátor ji zkontroluje a o výsledku vás budeme informovat.';
            } elseif ($action === 'cancel') {
                athleteRegistrationCancel($pdo, (int)($_POST['request_id'] ?? 0), $accountId);
                $_SESSION['athlete_registration_success'] = 'Žádost byla zrušena.';
            } else {
                throw new InvalidArgumentException('Neplatná akce.');
            }
            header('Location: registrace_sportovce.php', true, 303);
            exit;
        } catch (InvalidArgumentException | AthleteRegistrationException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PersonSensitiveException $exception) {
            $errors[] = 'Žádost se nepodařilo bezpečně uložit. Zkontrolujte údaje a zkuste to znovu.';
        } catch (RuntimeException $exception) {
            $errors[] = 'Žádost se nepodařilo bezpečně uložit. Zkontrolujte údaje a zkuste to znovu.';
        } catch (Throwable $exception) {
            error_log('athlete registration submit failed: ' . get_class($exception));
            $errors[] = 'Žádost se nepodařilo bezpečně uložit. Zkuste to prosím později.';
        }
    }
}

$success = (string)($_SESSION['athlete_registration_success'] ?? '');
unset($_SESSION['athlete_registration_success']);
$terms = athleteRegistrationCurrentTerms($pdo);
$requests = athleteRegistrationListForAccount($pdo, $accountId);
$statusLabels = [
    'pending' => ['warning', 'Čeká na kontrolu'],
    'approved' => ['success', 'Schváleno'],
    'rejected' => ['danger', 'Zamítnuto'],
    'cancelled' => ['secondary', 'Zrušeno'],
];
$value = static fn(string $key, string $default = ''): string => athleteRegistrationPageH($safeValues[$key] ?? $default);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Registrace sportovce — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-4" style="max-width:960px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h4 mb-1"><i class="bi bi-person-plus me-2 text-primary"></i>Registrace sportovce</h1><p class="text-muted mb-0">Žádost pro dítě nebo pro dospělého sportovce. Zařazení provede až administrátor.</p></div>
        <a class="btn btn-outline-secondary btn-sm" href="moje_osoby.php">Zpět na Moje osoby</a>
    </div>
    <div class="alert alert-info">Odesláním nevzniká členství automaticky. Údaje se neposílají do veřejných výpisů a rodné číslo se ukládá šifrovaně.</div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= athleteRegistrationPageH($error) ?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= athleteRegistrationPageH($success) ?></div><?php endif; ?>

    <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Nová žádost</div><div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-3" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="submit">
            <?php foreach ($terms as $purpose => $term): ?><input type="hidden" name="term_version[<?= athleteRegistrationPageH($purpose) ?>]" value="<?= athleteRegistrationPageH($term['version']) ?>"><?php endforeach; ?>

            <div class="col-md-4"><label for="athlete-role" class="form-label">Kdo žádost podává</label><select id="athlete-role" name="requested_role" class="form-select" required><option value="guardian" <?= $value('requested_role', 'guardian') === 'guardian' ? 'selected' : '' ?>>Rodič / zákonný zástupce dítěte</option><option value="self" <?= $value('requested_role') === 'self' ? 'selected' : '' ?>>Dospělý sportovec za sebe</option></select></div>
            <div class="col-md-4"><label for="athlete-first-name" class="form-label">Jméno sportovce</label><input id="athlete-first-name" name="jmeno" class="form-control" maxlength="100" value="<?= $value('jmeno') ?>" required></div>
            <div class="col-md-4"><label for="athlete-last-name" class="form-label">Příjmení sportovce</label><input id="athlete-last-name" name="prijmeni" class="form-control" maxlength="100" value="<?= $value('prijmeni') ?>" required></div>
            <div class="col-md-4"><label for="athlete-birth-date" class="form-label">Datum narození</label><input id="athlete-birth-date" type="date" name="narozeni" class="form-control" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= $value('narozeni') ?>" required></div>
            <div class="col-md-4"><label for="athlete-phone" class="form-label">Kontaktní telefon</label><input id="athlete-phone" type="tel" name="contact_phone" class="form-control" maxlength="50" value="<?= $value('contact_phone') ?>" required></div>
            <div class="col-md-4"><label for="athlete-citizenship" class="form-label">Státní občanství</label><input id="athlete-citizenship" name="citizenship_country_code" class="form-control text-uppercase" maxlength="2" pattern="[A-Za-z]{2}" value="<?= $value('citizenship_country_code', 'CZ') ?>" aria-describedby="athlete-citizenship-help" required><div id="athlete-citizenship-help" class="form-text">Dvouznakový kód, např. CZ, SK nebo UA.</div></div>

            <div class="col-12"><h2 class="h6 mb-0 mt-2">Adresa sportovce</h2></div>
            <div class="col-md-5"><label for="athlete-street" class="form-label">Ulice</label><input id="athlete-street" name="address_street" class="form-control" maxlength="200" value="<?= $value('address_street') ?>" required></div>
            <div class="col-md-2"><label for="athlete-house" class="form-label">Číslo popisné</label><input id="athlete-house" name="address_house_number" class="form-control" maxlength="20" value="<?= $value('address_house_number') ?>" required></div>
            <div class="col-md-2"><label for="athlete-orientation" class="form-label">Orientační</label><input id="athlete-orientation" name="address_orientation_number" class="form-control" maxlength="20" value="<?= $value('address_orientation_number') ?>"></div>
            <div class="col-md-3"><label for="athlete-postcode" class="form-label">PSČ</label><input id="athlete-postcode" name="address_postcode" class="form-control" maxlength="10" value="<?= $value('address_postcode') ?>" required></div>
            <div class="col-md-6"><label for="athlete-city" class="form-label">Obec</label><input id="athlete-city" name="address_city" class="form-control" maxlength="100" value="<?= $value('address_city') ?>" required></div>

            <div class="col-12"><h2 class="h6 mb-0 mt-2">Rodné číslo</h2></div>
            <div class="col-md-6"><label for="athlete-has-rc" class="form-label">Bylo přiděleno české rodné číslo?</label><select id="athlete-has-rc" name="has_czech_birth_number" class="form-select" required><option value="1" <?= $value('has_czech_birth_number', '1') === '1' ? 'selected' : '' ?>>Ano</option><option value="0" <?= $value('has_czech_birth_number') === '0' ? 'selected' : '' ?>>Ne — jsem cizinec a české RČ mi nebylo přiděleno</option></select></div>
            <div class="col-md-6"><label for="athlete-rc" class="form-label">České rodné číslo</label><input id="athlete-rc" name="birth_number" class="form-control" inputmode="numeric" maxlength="11" autocomplete="new-password" aria-describedby="athlete-rc-help"><div id="athlete-rc-help" class="form-text">Lze zadat s lomítkem i bez něj. Po chybě se pole z bezpečnostních důvodů vyprázdní.</div></div>

            <div class="col-12"><h2 class="h6 mb-0 mt-2">Volitelná fotografie</h2></div>
            <div class="col-md-6"><label for="athlete-photo" class="form-label">Interní evidenční fotografie</label><input id="athlete-photo" type="file" name="internal_photo" class="form-control" accept="image/jpeg,image/png" aria-describedby="athlete-photo-help"><div id="athlete-photo-help" class="form-text">JPG nebo PNG, nejvýše 5 MB. Soubor se uloží mimo veřejnou část a odstraní se z něj metadata.</div></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input id="athlete-photo-internal" type="checkbox" name="photo_internal" value="1" class="form-check-input" <?= isset($safeValues['photo_internal']) ? 'checked' : '' ?>><label for="athlete-photo-internal" class="form-check-label">Souhlasím s interním uložením fotografie podle textu níže.</label></div></div>
            <div class="col-12"><fieldset><legend class="h6">Souhlas se zveřejněním fotografie <span class="text-muted fw-normal">(volitelný, ale zvolte ano nebo ne)</span></legend><div class="d-flex gap-4"><div class="form-check"><input id="athlete-photo-public-no" type="radio" name="photo_public" value="0" class="form-check-input" <?= $value('photo_public', '0') === '0' ? 'checked' : '' ?> required><label for="athlete-photo-public-no" class="form-check-label">Ne</label></div><div class="form-check"><input id="athlete-photo-public-yes" type="radio" name="photo_public" value="1" class="form-check-input" <?= $value('photo_public') === '1' ? 'checked' : '' ?> required><label for="athlete-photo-public-yes" class="form-check-label">Ano</label></div></div></fieldset></div>

            <div class="col-12"><div class="border rounded bg-light p-3 small"><strong>Informace o evidenci člena — <?= athleteRegistrationPageH($terms['member_data_notice']['version']) ?></strong><p class="mb-2"><?= athleteRegistrationPageH($terms['member_data_notice']['text']) ?></p><div class="form-check"><input id="athlete-member-notice" type="checkbox" name="member_data_notice" value="1" class="form-check-input" required <?= isset($safeValues['member_data_notice']) ? 'checked' : '' ?>><label for="athlete-member-notice" class="form-check-label">Potvrzuji, že jsem tuto informaci četl/a.</label></div></div></div>
            <div class="col-12"><div class="border rounded bg-light p-3 small"><strong>Informace k rodnému číslu — <?= athleteRegistrationPageH($terms['birth_number_legal_notice']['version']) ?></strong><p class="mb-2"><?= athleteRegistrationPageH($terms['birth_number_legal_notice']['text']) ?></p><div class="form-check"><input id="athlete-rc-notice" type="checkbox" name="birth_number_legal_notice" value="1" class="form-check-input" required <?= isset($safeValues['birth_number_legal_notice']) ? 'checked' : '' ?>><label for="athlete-rc-notice" class="form-check-label">Potvrzuji, že jsem tuto informaci četl/a.</label></div></div></div>
            <div class="col-md-6"><div class="border rounded p-3 small h-100"><strong>Interní fotografie — <?= athleteRegistrationPageH($terms['photo_internal']['version']) ?></strong><p class="mb-0"><?= athleteRegistrationPageH($terms['photo_internal']['text']) ?></p></div></div>
            <div class="col-md-6"><div class="border rounded p-3 small h-100"><strong>Zveřejnění fotografie — <?= athleteRegistrationPageH($terms['photo_public']['version']) ?></strong><p class="mb-0"><?= athleteRegistrationPageH($terms['photo_public']['text']) ?></p></div></div>
            <div class="col-12"><label for="athlete-message" class="form-label">Poznámka pro administrátora <span class="text-muted">(nepovinná)</span></label><textarea id="athlete-message" name="message" class="form-control" maxlength="1000" rows="2"><?= $value('message') ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Odeslat žádost ke kontrole</button></div>
        </form>
    </div></section>

    <section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Moje registrační žádosti</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Sportovec</th><th>Vztah</th><th>Stav</th><th></th></tr></thead><tbody>
        <?php foreach ($requests as $request): [$color, $label] = $statusLabels[$request['status']] ?? ['secondary', (string)$request['status']]; ?><tr><td><strong><?= athleteRegistrationPageH($request['claimed_prijmeni'] . ' ' . $request['claimed_jmeno']) ?></strong><div class="small text-muted"><?= athleteRegistrationPageH($request['claimed_narozeni']) ?></div></td><td><?= $request['requested_role'] === 'guardian' ? 'rodič / zástupce' : 'vlastní profil' ?></td><td><span class="badge bg-<?= athleteRegistrationPageH($color) ?>"><?= athleteRegistrationPageH($label) ?></span><div class="small text-muted"><?= athleteRegistrationPageH($request['created_at']) ?></div></td><td><?php if ($request['status'] === 'pending'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><button class="btn btn-sm btn-outline-danger">Zrušit</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        <?php if ($requests === []): ?><tr><td colspan="4" class="text-center text-muted py-3">Zatím jste žádnou registraci sportovce neposlali.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<?php publicShellFooter(); ?>
</body>
</html>
