<?php

declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/membership_setup.php';
require_once __DIR__.'/includes/shop_document_automation.php';
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
        if (($_POST['action'] ?? '') === 'shop_documents') {
            if (($_POST['confirmed'] ?? '') !== '1') {
                throw new DomainException('Potvrďte nastavení automatických dokladů.');
            }
            shopDocumentConfigure($pdo, (int)($_POST['issuer_id'] ?? 0), (string)($_POST['approval_evidence'] ?? ''), ($_POST
    ['enabled'] ?? '') === '1');
        } else {
            membershipSaveIssuer($pdo, $_POST);
        }
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
$shopPolicy = shopDocumentPolicy($pdo);
$shopStatus = shopDocumentStatus($pdo);
$issuers = $pdo->query('SELECT id,snapshot_json FROM accounting_issuer_versions ORDER BY id DESC')->fetchAll(
    PDO::FETCH_ASSOC
);
$shopErrors = $pdo->query((('SELECT s.order_id,o.public_code,s.last_error,s.retry_at FROM shop_order_documents s JOIN ' .
    'shop_orders o ON o.id=s.order_id WHERE ') .
    's.last_error IS NOT NULL ORDER BY s.order_id LIMIT 30'))->fetchAll(PDO::FETCH_ASSOC);
if ($error !== '') {
    foreach (['name', 'address', 'registration_id', 'bank_account'] as $key) {
        $party[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }
}
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fakturační údaje klubu</title><?php appUiAssets(); ?></head>
<body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?><main class="container py-4" style="max-width:850px">
<h1 class="h3">Fakturační údaje klubu</h1>
<p>
<a href="monthly_memberships_admin.php">Členství v klubu</a> · <a href="accounting_documents_admin.php">Účetní doklady a číselné řady</a>
</p>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?=masH($error)?></div><?php endif; ?>
<?php if ($saved): ?><div class="alert alert-success">Nastavení bylo uloženo jako nová verze.</div><?php endif; ?>
<p>Údaje se použijí pro nové doklady podle schváleného tarifu. Již vydané doklady a sjednaná členství zachovají svou verzi vystavovatele.</p>
<form method="post" class="card card-body">
<?=csrf_field()?>
<label class="form-label" for="issuer-name">Název vystavovatele</label>
<input class="form-control mb-3" id="issuer-name" name="name" value="<?=masH($party['name'])?>" maxlength="200" required>
<label class="form-label" for="issuer-address">Úplná adresa sídla včetně PSČ</label>
<textarea class="form-control mb-3" id="issuer-address" name="address" maxlength="500" required><?=masH($party['address']
)?></textarea>
<label class="form-label" for="issuer-id">IČ</label>
<input class="form-control mb-3" id="issuer-id" name="registration_id" value="<?=masH($party['registration_id'])?>" maxlength="40" required>
<label class="form-label" for="issuer-bank">Bankovní účet uvedený na dokladech</label>
<input class="form-control mb-3" id="issuer-bank" name="bank_account" value="<?=masH($party['bank_account'] ?? '')?>" maxlength="80">
<p class="small">Účet pro skutečné platby a QR kódy nastavujete v <a href="eshop_bank_admin.php">Bankovním účtu e-shopu</a>.</p>
<label class="form-label" for="issuer-evidence">Podklad nebo poznámka ke schválení</label>
<textarea class="form-control mb-3" id="issuer-evidence" name="approval_evidence" maxlength="1000" required>
</textarea>
<p>Současný modul podporuje doklady neplátce DPH. Pro členství připraví doklad k členskému příspěvku,
členění CLEN / KLUB a řadu CLEN pro letošní a příští rok. Existující řady a jejich čítače zachová.</p>
<label class="mb-3">
<input type="checkbox" name="confirmed" value="1" required>
Potvrzuji úplnost údajů, režim neplátce DPH a uvedené nastavení dokladů k členským příspěvkům.</label>
<button class="btn btn-primary">Uložit fakturační údaje a nastavení dokladů</button>
</form>
<form method="post" class="card card-body mt-4">
<?=csrf_field()?><input type="hidden" name="action" value="shop_documents">
<h2 class="h4">Doklady a e-maily jednorázových objednávek</h2>
<p>Stav: <strong><?=$shopPolicy ? 'Zapnuto' : 'Vypnuto'?></strong>. Evidováno objednávek: <?=(int)$shopStatus['registered'
]?>,
chyby vystavení: <?=(int)$shopStatus['document_errors']?>, e-maily čekající: <?=(int)$shopStatus['pending_emails']?>,
e-maily k prověření: <?=(int)$shopStatus['failed_emails']?>.</p>
<?php foreach ($shopErrors as $problem): ?><p class="alert alert-warning"><?=masH($problem['public_code'])?>:
<?=masH($problem['last_error'])?> (další pokus <?=masH($problem['retry_at'])?>)</p><?php endforeach; ?>
<p>Pro nové nákupy systém vyžádá fakturační adresu, po úhradě vystaví fakturu a při stornu opravný doklad.
Pošle potvrzení objednávky, storna, skutečné vratky a PDF přílohy. Staré objednávky hromadně nezpracuje.
Schvalujete členění ESHOP / PRODEJ a chybějící řady FA / OPR pro letošní a příští rok; existující řady zachová.</p>
<label class="form-label" for="shop-issuer">Schválená verze vystavovatele</label>
<select class="form-select mb-3" name="issuer_id" id="shop-issuer" required>
<?php foreach ($issuers as $issuer): $issuerParty = json_decode($issuer['snapshot_json'], true); ?>
<option value="<?=(int)$issuer['id']?>">#<?=(int)$issuer['id']?> · <?=masH($issuerParty['name'])?> · <?=masH($issuerParty
['address'])?></option>
<?php endforeach; ?></select>
<label class="mb-3">
<input type="checkbox" name="enabled" value="1" <?=$shopPolicy ? 'checked' : ''?>> Zapnout pro nové objednávky (již sjednané objednávky se dokončí i po vypnutí)</label>
<label class="form-label" for="shop-evidence">Důvod a podklad schválení</label>
<textarea class="form-control mb-3" id="shop-evidence" name="approval_evidence" maxlength="1000" required>
</textarea>
<label class="mb-3">
<input type="checkbox" name="confirmed" value="1" required> Potvrzuji vystavovatele, neplátce DPH, účetní členění a automatické zasílání.</label>
<button class="btn btn-primary">Uložit automatické doklady a e-maily</button>
<p class="small mt-3 mb-0">Chyby dokladů worker opakuje po pěti minutách. Oprávnění schvalujícího musí zůstat platné.
Doklady jsou v <a href="accounting_documents_admin.php">účetním přehledu</a>; nejasné doručení se automaticky neopakuje.</p>
</form>
</main>
</body>
</html>
