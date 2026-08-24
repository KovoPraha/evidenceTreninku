<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('eshop_bank_settings')) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/shop_bank_settings.php';

function bankAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$actorId = (int)$_SESSION['trener_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $result = shopBankSettingsSave($pdo, $actorId, [
                'iban' => (string)($_POST['iban'] ?? ''),
                'bic' => (string)($_POST['bic'] ?? ''),
                'account_label' => (string)($_POST['account_label'] ?? ''),
                'due_days' => (int)($_POST['due_days'] ?? 0),
            ], (string)($_POST['reason'] ?? ''), ($_POST['confirm_action'] ?? '') === '1');
            $_SESSION['flash_bank'] = $result['changed']
                ? 'Bankovní účet e-shopu byl uložen. Platí pro objednávky vytvořené od této chvíle.'
                : 'Zadané údaje se shodují s uloženými; nic se neměnilo.';
            header('Location: eshop_bank_admin.php', true, 303);
            exit;
        } catch (Throwable $exception) {
            if (!($exception instanceof InvalidArgumentException
                || $exception instanceof ShopBankSettingsException
                || $exception instanceof ShopCheckoutException)) {
                error_log('eshop_bank_admin.php: ' . $exception->getMessage());
                $errors[] = 'Uložení selhalo bez částečného zápisu.';
            } else {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

$success = (string)($_SESSION['flash_bank'] ?? '');
unset($_SESSION['flash_bank']);
$resolved = shopBankSettingsResolve($pdo);
$effective = $resolved['settings'];
$sample = null;
$sampleError = '';
if ((string)($_GET['ukazka'] ?? '') === '1') {
    if ($effective === null) {
        $sampleError = 'Ukázku nelze vykreslit, dokud není uložený platný účet.';
    } else {
        try {
            $sample = shopBankSampleQr($effective);
        } catch (Throwable $exception) {
            $sampleError = $exception->getMessage();
        }
    }
}
$form = $effective ?? ['iban' => '', 'bic' => '', 'account_label' => '', 'due_days' => 7];
foreach (['iban', 'bic', 'account_label'] as $field) {
    if (isset($_POST[$field])) $form[$field] = (string)$_POST[$field];
}
if (isset($_POST['due_days'])) $form['due_days'] = (int)$_POST['due_days'];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bankovní účet e-shopu</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:960px">
<div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h3 mb-1"><i class="bi bi-bank me-2 text-primary"></i>Bankovní účet e-shopu</h1><p class="text-muted mb-0">Účet, na který zákazníci posílají platby, a splatnost objednávek.</p></div><a class="btn btn-outline-secondary btn-sm" href="pracovni_pozice.php">Finanční rozcestník</a></div>

<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= bankAdminH($error) ?></div><?php endforeach; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= bankAdminH($success) ?></div><?php endif; ?>

<?php if ($resolved['database_error'] !== ''): ?>
<div class="alert alert-danger"><strong>Uložený účet není platný.</strong> <?= bankAdminH($resolved['database_error']) ?> Checkout je do opravy bezpečně vypnutý; konstanty z <code>config.php</code> se záměrně nepoužijí.</div>
<?php endif; ?>

<?php if ($resolved['conflict']): ?>
<div class="alert alert-warning"><strong>Nastavení se liší ve dvou zdrojích.</strong> V databázi i v <code>config.php</code> je uložený jiný účet nebo splatnost. <strong>Platí databáze</strong> — hodnoty níže. Konstanty v <code>config.php</code> se ignorují, dokud tento záznam existuje.
<div class="small mt-2">V <code>config.php</code> je <code><?= bankAdminH($resolved['config']['iban']) ?></code> · <?= bankAdminH($resolved['config']['account_label']) ?> · splatnost <?= (int)$resolved['config']['due_days'] ?> dní.</div></div>
<?php endif; ?>

<section class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-semibold">Aktuálně platné nastavení</span><span class="badge text-bg-<?= $resolved['source'] === 'database' ? 'primary' : ($resolved['source'] === 'config' ? 'secondary' : 'danger') ?>"><?= bankAdminH(shopBankSettingsSourceLabel($resolved['source'])) ?></span></div>
<div class="card-body">
<?php if ($effective === null): ?>
<p class="mb-0 text-danger">Není nastavený žádný účet. Bankovní objednávky se bezpečně nevytvoří, dokud údaje nedoplníte.</p>
<?php else: ?>
<dl class="row mb-0"><dt class="col-sm-3">Účet</dt><dd class="col-sm-9"><?= bankAdminH($effective['account_label']) ?></dd>
<dt class="col-sm-3">IBAN</dt><dd class="col-sm-9"><code class="fs-6"><?= bankAdminH($effective['iban']) ?></code></dd>
<dt class="col-sm-3">BIC</dt><dd class="col-sm-9"><?= $effective['bic'] === '' ? '<span class="text-muted">nevyplněn (pro tuzemský převod se nevyžaduje)</span>' : '<code>' . bankAdminH($effective['bic']) . '</code>' ?></dd>
<dt class="col-sm-3">Splatnost</dt><dd class="col-sm-9"><?= (int)$effective['due_days'] ?> dní</dd>
<?php if ($resolved['source'] === 'database' && $resolved['updated_at'] !== ''): ?><dt class="col-sm-3">Naposledy změnil</dt><dd class="col-sm-9">trenér #<?= (int)$resolved['updated_by_trainer_id'] ?> dne <?= bankAdminH($resolved['updated_at']) ?></dd><?php endif; ?>
</dl>
<div class="mt-3"><a class="btn btn-outline-primary btn-sm" href="eshop_bank_admin.php?ukazka=1"><i class="bi bi-qr-code me-1"></i>Zkontrolovat ukázkovým QR kódem</a>
<div class="form-text">Vykreslí kontrolní QR z uloženého nastavení. Nevznikne objednávka, platba ani jiný záznam.</div></div>
<?php endif; ?>
</div></section>

<?php if ($sampleError !== ''): ?><div class="alert alert-warning"><?= bankAdminH($sampleError) ?></div><?php endif; ?>
<?php if ($sample !== null): ?>
<section class="card border-primary shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Ukázkový platební příkaz — <span class="text-danger">NEPLATIT</span></div>
<div class="card-body row g-3 align-items-center">
<div class="col-md-5 text-center"><img class="img-fluid" style="max-width:260px" src="<?= bankAdminH($sample['data_uri']) ?>" alt="Ukázkový QR kód"></div>
<div class="col-md-7"><dl class="row mb-2"><dt class="col-5">Účet</dt><dd class="col-7"><?= bankAdminH($sample['account_label']) ?><br><code><?= bankAdminH($sample['iban']) ?></code></dd>
<dt class="col-5">Variabilní symbol</dt><dd class="col-7"><code><?= bankAdminH($sample['variable_symbol']) ?></code></dd>
<dt class="col-5">Částka</dt><dd class="col-7"><?= number_format($sample['amount_minor'] / 100, 2, ',', ' ') ?> <?= bankAdminH($sample['currency']) ?></dd>
<dt class="col-5">Splatnost</dt><dd class="col-7"><?= bankAdminH($sample['due_at']) ?></dd></dl>
<p class="small text-muted mb-0">Ukázková částka i variabilní symbol <code><?= bankAdminH($sample['variable_symbol']) ?></code> jsou vymyšlené a nekolidují s řadou skutečných objednávek. Zprávu <code><?= bankAdminH(SHOP_BANK_SAMPLE_MESSAGE) ?></code> nese i samotný QR kód.</p></div>
</div></section>
<?php endif; ?>

<section class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Změnit účet</div>
<div class="card-body">
<div class="alert alert-info py-2 mb-3">Změna platí <strong>jen pro objednávky vytvořené potom</strong>. Objednávky, které už mají vystavený platební příkaz, si drží svůj původní účet, variabilní symbol i QR kód.</div>
<form method="post" class="row g-3"><?= csrf_field() ?>
<div class="col-md-7"><label class="form-label req" for="iban">IBAN</label><input class="form-control font-monospace" id="iban" name="iban" maxlength="42" value="<?= bankAdminH($form['iban']) ?>" required><div class="form-text">Mezery se odstraní automaticky. Kontrolní součet se ověřuje; neplatný účet nejde uložit.</div></div>
<div class="col-md-5"><label class="form-label" for="bic">BIC / SWIFT</label><input class="form-control font-monospace" id="bic" name="bic" maxlength="11" value="<?= bankAdminH($form['bic']) ?>"><div class="form-text">Nepovinné. Pro tuzemský převod se nevyžaduje.</div></div>
<div class="col-md-7"><label class="form-label req" for="account_label">Název účtu</label><input class="form-control" id="account_label" name="account_label" maxlength="120" value="<?= bankAdminH($form['account_label']) ?>" required><div class="form-text">3–120 znaků. Zobrazí se zákazníkovi na platebním příkazu.</div></div>
<div class="col-md-5"><label class="form-label req" for="due_days">Splatnost ve dnech</label><input class="form-control" type="number" min="1" max="30" id="due_days" name="due_days" value="<?= (int)$form['due_days'] ?>" required>
<div class="form-text"><strong>Pozor:</strong> tato hodnota řídí i dobu, po kterou nezaplacená objednávka <strong>drží místo v kroužku</strong>. Nastavením 30 dní zablokujete kapacitu na měsíc. Doporučená hodnota je 7.</div></div>
<div class="col-12"><label class="form-label req" for="reason">Důvod změny</label><input class="form-control" id="reason" name="reason" maxlength="1000" value="<?= bankAdminH((string)($_POST['reason'] ?? '')) ?>" required><div class="form-text">Zapíše se do auditu spolu s předchozími hodnotami a vaším účtem.</div></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" id="confirm" required><label class="form-check-label" for="confirm">Potvrzuji, že na tento účet mají chodit platby klubu.</label></div></div>
<div class="col-12"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Uložit bankovní účet</button></div>
</form></div></section>
</main></body></html>
