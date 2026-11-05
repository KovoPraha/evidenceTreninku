<?php

declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/membership_setup.php';
header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
staffRequireActivePosition('finance_manager');
accountingDocumentRequireFinance($pdo);
function masH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$error = '';
$saved = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            throw new DomainException('Formulář vypršel. Obnovte stránku.');
        }
        membershipSaveIssuer($pdo, $_POST);
        $saved = true;
    } catch (DomainException|InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Membership issuer settings: '.$e->getMessage());
        $error = 'Údaje se nepodařilo uložit. Zkontrolujte nastavení dokladů.';
    }
}
$snapshot = $pdo->query('SELECT snapshot_json FROM accounting_issuer_versions ORDER BY id DESC LIMIT 1')->fetchColumn();
$party = $snapshot ? json_decode((string)$snapshot, true, 512, JSON_THROW_ON_ERROR) : [
    'name' => 'Tělovýchovná jednota KOVO Praha', 'address' => '', 'registration_id' => '', 'bank_account' => '',
];
if ($error !== '') {
    foreach (['name', 'address', 'registration_id', 'bank_account'] as $key) {
        $party[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }
}
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fakturační údaje klubu</title><?php appUiAssets(); ?></head>
<body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?><main class="container py-4" style="max-width:850px">
<h1 class="h3">Fakturační údaje klubu</h1>
<p><a href="monthly_memberships_admin.php">Členství v klubu</a> · <a href="accounting_documents_admin.php">Účetní doklady a číselné řady</a></p>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?=masH($error)?></div><?php endif; ?>
<?php if ($saved): ?><div class="alert alert-success">Údaje byly uloženy jako nová verze. Můžete je vybrat při schválení tarifu.</div><?php endif; ?>
<p>Údaje se použijí pro nové doklady podle schváleného tarifu. Již vydané doklady a sjednaná členství zachovají svou verzi vystavovatele.</p>
<form method="post" class="card card-body">
<?=csrf_field()?>
<label class="form-label" for="issuer-name">Název vystavovatele</label>
<input class="form-control mb-3" id="issuer-name" name="name" value="<?=masH($party['name'])?>" maxlength="200" required>
<label class="form-label" for="issuer-address">Úplná adresa sídla včetně PSČ</label>
<textarea class="form-control mb-3" id="issuer-address" name="address" maxlength="500" required><?=masH($party['address'])?></textarea>
<label class="form-label" for="issuer-id">IČ</label>
<input class="form-control mb-3" id="issuer-id" name="registration_id" value="<?=masH($party['registration_id'])?>" maxlength="40" required>
<label class="form-label" for="issuer-bank">Bankovní účet uvedený na dokladech</label>
<input class="form-control mb-3" id="issuer-bank" name="bank_account" value="<?=masH($party['bank_account'] ?? '')?>" maxlength="80">
<p class="small">Účet pro skutečné platby a QR kódy nastavujete v <a href="eshop_bank_admin.php">Bankovním účtu e-shopu</a>.</p>
<label class="form-label" for="issuer-evidence">Podklad nebo poznámka ke schválení</label>
<textarea class="form-control mb-3" id="issuer-evidence" name="approval_evidence" maxlength="1000" required></textarea>
<p>Současný modul podporuje doklady neplátce DPH. Pro členství připraví doklad k členskému příspěvku,
členění CLEN / KLUB a řadu CLEN pro letošní a příští rok. Existující řady a jejich čítače zachová.</p>
<label class="mb-3"><input type="checkbox" name="confirmed" value="1" required>
Potvrzuji úplnost údajů, režim neplátce DPH a uvedené nastavení dokladů k členským příspěvkům.</label>
<button class="btn btn-primary">Uložit fakturační údaje a nastavení dokladů</button>
</form></main></body></html>
