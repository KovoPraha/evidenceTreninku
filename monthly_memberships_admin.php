<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/monthly_membership_worker.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
staffRequireActivePosition('finance_manager');
accountingDocumentRequireFinance($pdo);
function mmaH(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$error = '';
$success = '';
if (!monthlyMembershipTable($pdo, 'monthly_memberships')) {
    http_response_code(503);
    exit('Nejdříve aplikujte migraci měsíčních členství.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            throw new DomainException('Formulář vypršel.');
        }
        if (($_POST['action'] ?? '') === 'configure') {
            monthlyMembershipConfigure($pdo, $_POST);
            $success = 'Měsíční tarif byl schválen. Stávající jednorázové varianty se nemění.';
        } elseif (($_POST['action'] ?? '') === 'pause') {
            monthlyMembershipTransaction($pdo, static function () use ($pdo): void {
                $actor = accountingDocumentRequireFinance($pdo);
                if (!isset($_POST['confirmed'])) {
                    throw new DomainException('Potvrďte zastavení nového prodeje.');
                }
                $id = (int)($_POST['tariff_id'] ?? 0);
                $pdo->prepare("UPDATE monthly_membership_tariffs SET status='inactive' WHERE id=?")->execute([$id]);
                monthlyMembershipEvent($pdo, null, 'trainer', $actor, 'tariff_paused', ['tariff_id' => $id]);
            });
            $success = 'Nový prodej zastaven; sjednané závazky zůstávají platné.';
        } elseif (($_POST['action'] ?? '') === 'retry') {
            monthlyMembershipTransaction($pdo, static function () use ($pdo): void {
                $actor = accountingDocumentRequireFinance($pdo);
                $reason = memberChargeAdminReason((string)($_POST['reason'] ?? ''));
                if (!isset($_POST['confirmed'])) {
                    throw new DomainException('Nejdříve ověřte, že zpráva nebyla doručena.');
                }
                $id = (int)($_POST['message_id'] ?? 0);
                $q = $pdo->prepare("UPDATE monthly_membership_messages SET status='pending',claim_token=NULL,last_error=NULL WHERE id=? AND status='failed'");
                $q->execute([$id]);
                if ($q->rowCount() !== 1) {
                    throw new DomainException('Zpráva není v chybovém stavu.');
                }
                monthlyMembershipEvent($pdo, null, 'trainer', $actor, 'message_retry_approved', ['message_id' => $id
    ,'reason' => $reason]);
            });
            $success = 'Zpráva vrácena do fronty. Před odesláním se znovu ověří její aktuálnost.';
        } else {
            throw new DomainException('Neplatná akce.');
        }
    } catch (DomainException|InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Monthly administration: '.$e->getMessage());
        $error = 'Operace se nezdařila. Tarif již může existovat; obnovte přehled.';
    }
}
$variants = $pdo->query(('SELECT v.id,v.sku,p.name,v.amount_minor FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE '
    .
    'p.offer_type=\'program\' ORDER BY p.name,v.id'))->fetchAll(PDO::FETCH_ASSOC);
$teams = $pdo->query("SELECT id,name FROM club_teams WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$issuers = $pdo->query('SELECT id,snapshot_json FROM accounting_issuer_versions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$rules = $pdo->query("SELECT id,title,document_kind FROM accounting_rules WHERE source_type='member_charge' ORDER BY id DESC")
    ->fetchAll(PDO::FETCH_ASSOC);
$tariffs = $pdo->query('SELECT t.*,v.sku FROM monthly_membership_tariffs t JOIN shop_variants v ON v.id=t.variant_id ORDER BY t.id DESC')
    ->fetchAll(PDO::FETCH_ASSOC);
$errors = $pdo->query(('SELECT p.id,p.period_key,p.last_error,m.payer_account_id FROM monthly_membership_periods p JOIN monthly_memberships m ON '
    .
    'm.id=p.membership_id WHERE p.last_error IS NOT NULL ORDER BY p.id LIMIT 100'))->fetchAll(PDO::FETCH_ASSOC);
$messages = $pdo->query("SELECT id,kind,last_error FROM monthly_membership_messages WHERE status='failed' ORDER BY id LIMIT 100")
    ->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Měsíční členství</title><?php appUiAssets(); ?></head>
<body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?><main class="container py-4">
<h1 class="h3">Měsíční členství přes e-shop</h1>
<p>
<a href="member_fee_plans_admin.php">Dosavadní měsíční plány</a> · <a href="accounting_documents_admin.php">Doklady a účetní nastavení</a>
</p>
<p>Nejdříve založte samostatný neskladový produkt typu program a jeho variantu s měsíční cenou. Tady schválíte měsíční režim. Varianta
pak nepůjde do jednorázového košíku. Změna ceny nebo podmínek vyžaduje novou variantu; sjednaná členství se nepřepisují.</p>
<?php if ($error): ?><div class="alert alert-danger"><?=mmaH($error)?></div><?php endif; ?><?php


if ($success): ?><div class="alert alert-success"><?=mmaH($success)?></div><?php endif;
?>
<form method="post" class="card card-body mb-4"><?=csrf_field()?><input type="hidden" name="action" value="configure">
<h2 class="h5">Schválit měsíční tarif</h2>
<label>Varianta<select class="form-select mb-3" name="variant_id" required><?php foreach ($variants as $v):
    ?><option value="<?=(int)$v['id']?>"><?=mmaH($v['name'].' · '.$v['sku'].' · '.number_format((int)$v['amount_minor']
    / 100, 2, ',', ' ').' Kč / měsíc')?></option><?php endforeach; ?></select>
</label>
<label>Cílová soupiska<select class="form-select mb-3" name="team_id" required><?php foreach ($teams as $team):
    ?><option value="<?=(int)$team['id']?>"><?=mmaH($team['name'])?></option><?php endforeach;
?></select>
</label>
<label>Den splatnosti v měsíci<input class="form-control mb-3" name="due_day" type="number" min="1" max="28" value="15" required>
</label>
<label>Schválené podmínky členství<textarea class="form-control mb-3" name="terms_text" rows="5" minlength="20" maxlength="10000" required>
</textarea>
</label>
<p>První měsíc v plné ceně, ukončení ke konci měsíce. První splatnost nejdříve 7 dní po sjednání. Zařazení na soupisku vzniká
sjednáním; pravidla případného omezení při dluhu jsou samostatná.</p>
<label>Vystavovatel<select class="form-select mb-3" name="issuer_id" required><?php foreach ($issuers as $issuer): $party = json_decode($issuer['snapshot_json'],
    true); ?><option value="<?=(int)$issuer['id']?>"><?=mmaH($party['name'])?></option><?php endforeach;
?></select>
</label>
<label>Druh dokladu<select class="form-select mb-3" name="document_kind">
<option value="contribution_confirmation">Doklad k členskému příspěvku</option>
<option value="invoice">Faktura</option>
</select>
</label>
<label>Schválené účetní členění<select class="form-select mb-3" name="rule_id" required><?php foreach ($rules as $rule):
    ?><option value="<?=(int)$rule['id']?>"><?=mmaH($rule['title'].' · '.$rule['document_kind'])
    ?></option><?php endforeach;
?></select>
</label>
<label class="mb-3">
<input type="checkbox" name="confirmed" value="1" required> Schvaluji cenu, podmínky a automatické vydávání podle vybrané metodiky a číselné řady.</label>
<button class="btn btn-primary">Schválit měsíční tarif</button>
</form>
<h2 class="h5">Schválené tarify</h2><?php foreach ($tariffs as $t): ?><div class="card card-body mb-2">
<strong><?=mmaH($t['sku'].' · '.$t['status'])?></strong>
<p><?=mmaH(number_format((int)$t['amount_minor'] / 100, 2, ',', ' '))?> Kč / měsíc</p>
<a href="booking/mesicni_clenstvi.php?variant_id=<?=(int)$t['variant_id']?>">Zákaznická nabídka</a><?php


        if ($t['status'] === 'active'): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="pause">
<input type="hidden" name="tariff_id" value="<?=(int)$t['id']?>">
<label>
<input type="checkbox" name="confirmed" required> Potvrzuji zastavení nového prodeje</label>
<button class="btn btn-outline-danger btn-sm">Zastavit nový prodej</button>
</form><?php endif; ?></div><?php endforeach; ?>
<h2 class="h5 mt-4">Doklady vyžadující opravu nastavení</h2><?php foreach ($errors as $e):
    ?><p>Období <?=mmaH($e['period_key'])?> · <?=mmaH($e['last_error'])?></p><?php endforeach;
?><p>Po opravě nastavení worker zopakuje stejné vydání bez dalšího předpisu nebo nového čísla.</p>
<h2 class="h5">Neodeslané zprávy vyžadující kontrolu</h2><?php foreach ($messages as $message):
    ?><form class="card card-body mb-2" method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry">
<input type="hidden" name="message_id" value="<?=(int)$message['id']?>">
<p><?=mmaH($message['kind'].' · '.$message['last_error'])?></p>
<label>Důvod opakování<input class="form-control" name="reason" minlength="5" required>
</label>
<label>
<input type="checkbox" name="confirmed" required> Ověřil jsem, že předchozí zpráva nebyla doručena a má se odeslat znovu.</label>
<button class="btn btn-outline-primary">Povolit nové odeslání</button>
</form><?php endforeach; ?></main>
</body>
</html>
