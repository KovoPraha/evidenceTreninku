<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
$variantId = max(0, (int)($_GET['variant_id'] ?? $_POST['variant_id'] ?? 0));
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect='.rawurlencode('mesicni_clenstvi.php'.($variantId ? '?variant_id='
    .$variantId : '')), true, 303);
    exit;
}
require_once dirname(__DIR__).'/db.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
require_once dirname(__DIR__).'/csrf_helper.php';
require_once dirname(__DIR__).'/includes/monthly_membership.php';
require_once dirname(__DIR__).'/includes/ui_shell.php';
function mmH(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mmMoney(int $v): string
{
    return number_format($v / 100, 2, ',', ' ').' Kč';
}
$account = (int)$_SESSION['verejny_uzivatel_id'];
$error = '';
if (!monthlyMembershipTable($pdo, 'monthly_memberships')) {
    http_response_code(503);
    exit('Členství v klubu se připravuje.');
}
if (isset($_GET['download'])) {
    try {
        $path = monthlyMembershipDocumentForAccount($pdo, $account, (int)$_GET['download']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="clensky-doklad.pdf"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    } catch (DomainException) {
        http_response_code(404);
        echo 'Doklad není dostupný.';
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            throw new DomainException('Formulář vypršel. Obnovte stránku.');
        }
        if (($_POST['action'] ?? '') === 'subscribe') {
            monthlyMembershipSubscribe($pdo, $account, (int)($_POST['sportovec_id'] ?? 0), (int)($_POST['tariff_id'] ?? 0), ['name' => (string)($_POST['buyer_name']
    ?? ''),'address' => (string)($_POST['buyer_address'] ?? ''),'registration_id' => ''], (string)($_POST['terms_sha256'] ?? ''), (string)($_POST['request_key']
    ?? ''), isset($_POST['confirmed']), null, (array)($_POST['team_ids'] ?? []));
        } elseif (($_POST['action'] ?? '') === 'cancel') {
            monthlyMembershipCancel($pdo, $account, (int)($_POST['membership_id'] ?? 0), isset($_POST['confirmed']));
        } else {
            throw new DomainException('Neplatná akce.');
        }
        header('Location: mesicni_clenstvi.php', true, 303);
        exit;
    } catch (DomainException|InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Monthly membership UI: '.$e->getMessage());
        $error = 'Operaci se nepodařilo dokončit. Zkontrolujte přehled před dalším pokusem.';
    }
}
$people = familyPortalAuthorizedPeople($pdo, $account);
$product = null;
$tariff = $variantId ? monthlyMembershipTariffForVariant($pdo, $variantId) : null;
if ($tariff !== null && !monthlyMembershipSalesEnabled()) {
    $tariff = null;
    $error = 'Sjednávání členství v klubu je nyní pozastavené.';
}
if ($tariff) {
    $q = $pdo->prepare(('SELECT p.id,pub.public_name,v.sku FROM shop_variants v JOIN shop_products p ON p.id=v.product_id JOIN '
    .
    'shop_product_publications pub ON pub.product_id=p.id WHERE v.id=? AND p.catalog_status=\'active\' AND pub.status=\'active\' AND '
    .
    'v.catalog_status=\'active\''));
    $q->execute([$variantId]);
    $product = $q->fetch(PDO::FETCH_ASSOC);
    if (!$product || $tariff['status'] !== 'active') {
        $tariff = null;
    }
}
$q = $pdo->prepare(('SELECT m.*,t.amount_minor,t.terms_text,t.billing_interval,s.jmeno,s.prijmeni FROM monthly_memberships m JOIN monthly_membership_tariffs t ON '
    .
    't.id=m.tariff_id JOIN sportovci s ON s.id=m.sportovec_id WHERE m.payer_account_id=? ORDER BY m.id DESC'));
$q->execute([$account]);
$memberships = array_values(array_filter($q->fetchAll(PDO::FETCH_ASSOC), static fn (array $m): bool => familyPortalCanViewPerson($pdo, $account, (int)$m['sportovec_id'])));
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Členství v klubu — KOVO Praha</title><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav(); ?><main class="container py-4" style="max-width:1000px">
<h1 class="h3">Členství v klubu</h1>
<p>
<a href="sportovni_prehled.php">Sportovní přehled</a> · <a href="eshop.php">E-shop</a>
</p>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?=mmH($error)?></div><?php endif;
?>
<?php if ($tariff): ?><section class="card card-body mb-4">
<h2 class="h4"><?=mmH($product['public_name'])?></h2>
<p class="fw-semibold"><?=mmMoney((int)$tariff['amount_minor'])?> / <?=mmH(membershipIntervalLabel($tariff))?>,
splatnost <?=mmH($tariff['due_day'])?>. den prvního měsíce období.</p>
<p>Platíte bankovním trvalým příkazem. První období se účtuje v plné výši; na jeho úhradu máte nejméně 7 dní od sjednání.
<?php if (membershipInterval($tariff) === 'year'): ?>
Roční období má 12 kalendářních měsíců od měsíce sjednání; členství lze ukončit na jeho konci. Trvalý příkaz nastavte jednou ročně.
<?php else: ?>Členství můžete ukončit ke konci kalendářního měsíce. Trvalý příkaz nastavte jednou měsíčně.<?php endif; ?></p>
<div class="border rounded p-3 mb-3"><?=nl2br(mmH($tariff['terms_text']))?></div>
<?php
 if ($people === []):
     ?><p>Nejprve <a href="moje_osoby.php?return=<?=rawurlencode('mesicni_clenstvi.php?variant_id='.$variantId)?>#propojeni">
propojte sportovce v Mých osobách</a>. Propojení ověřuje klub.</p><?php else:
    ?><form method="post"><?=
    csrf_field()
    ?><input type="hidden" name="action" value="subscribe">
<input type="hidden" name="variant_id" value="<?=$variantId?>">
<input type="hidden" name="tariff_id" value="<?=(int)$tariff['id']?>">
<input type="hidden" name="terms_sha256" value="<?=mmH($tariff['terms_sha256'])?>">
<input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(32))?>">
<label class="form-label" for="mm-person">Sportovec</label>
<select class="form-select mb-3" id="mm-person" name="sportovec_id" required>
<option value="">Vyberte</option><?php foreach ($people as $person): ?><option value="<?=(int)$person['sportovec_id']
    ?>"><?=mmH($person['jmeno'].' '.$person['prijmeni'])?></option><?php endforeach;
    ?></select>
<?php $options = membershipTariffTeams($pdo, (int)$tariff['id']);
    if ($options !== []): ?>
<fieldset class="mb-3"><legend class="h6">Disciplíny</legend>
<p>Vyberte alespoň jednu. Můžete zvolit více disciplín za stejnou cenu; každá vás zařadí do příslušné soupisky.</p>
<?php foreach ($options as $option): ?><label class="d-block mb-2">
<input type="checkbox" name="team_ids[]" value="<?=(int)$option['id']?>"> <?=mmH($option['name'])?></label><?php endforeach; ?>
</fieldset><?php endif; ?>
<label class="form-label" for="mm-name">Jméno odběratele dokladu</label>
<input class="form-control mb-3" id="mm-name" name="buyer_name" maxlength="200" required>
<label class="form-label" for="mm-address">Úplná adresa odběratele dokladu</label>
<input class="form-control mb-3" id="mm-address" name="buyer_address" maxlength="500" required>
<label class="d-block mb-3">
<input type="checkbox" name="confirmed" required> Souhlasím s uvedenými podmínkami a opakovanou platební povinností v uvedeném intervalu. Jsem plátcem za vybraného
sportovce. Doklady a zprávy o neuhrazených předpisech obdržím elektronicky.</label>
<button class="btn btn-primary">Závazně sjednat za <?=mmMoney((int)$tariff['amount_minor'])
?> / <?=mmH(membershipIntervalLabel($tariff))?></button>
</form><?php endif; ?></section><?php endif; ?>
<?php if ($memberships === []): ?><p>Zatím nemáte sjednané členství v klubu. Vyberte je v katalogu.</p><?php


endif; ?>
<?php foreach ($memberships as $m): ?><section class="card card-body mb-4">
<h2 class="h5"><?=mmH($m['jmeno'].' '.$m['prijmeni'])?></h2>
<p><?=mmMoney((int)$m['amount_minor'])?> / <?=mmH(membershipIntervalLabel($m))?> · od <?=mmH($m['starts_on'])?><?=$m['ends_on'] !== null ? ' do '
.mmH($m['ends_on']) : ' na dobu neurčitou'?></p>
<dl class="row">
<dt class="col-sm-4">Stálý variabilní symbol plátce</dt>
<dd class="col-sm-8">
<code><?=mmH($m['variable_symbol'])?></code>
</dd>
<dt class="col-sm-4">Bankovní účet pro trvalý příkaz</dt>
<dd class="col-sm-8">
<code><?=mmH($m['iban_snapshot'])?></code>
</dd>
</dl>
<?php $q = $pdo->prepare('SELECT t.name FROM monthly_membership_team_grants g JOIN club_teams t ON t.id=g.team_id WHERE g.membership_id=? ORDER BY t.name');
    $q->execute([$m['id']]);
    $disciplines = $q->fetchAll(PDO::FETCH_COLUMN);
    if ($disciplines !== []): ?>
<p>Disciplíny: <?=mmH(implode(', ', $disciplines))?></p><?php endif; ?>
<p class="small">Stejný VS může zahrnovat více vašich dětí a období. Platby se přidělují nejstarším členským předpisům.
Jednorázové nákupy mají vlastní platební údaje. Částku trvalého příkazu nastavte podle součtu aktivních členství se stejným intervalem. Roční členství se hradí jednou ročně.</p>
<?php $q = $pdo->prepare(('SELECT p.*,c.title_snapshot,c.period_from,c.period_to,c.amount_minor,c.status,c.due_on,d.number,d.status AS document_status FROM '
.
'monthly_membership_periods p JOIN club_member_charges c ON c.id=p.charge_id LEFT JOIN accounting_documents d ON '
.
'd.id=p.document_id WHERE p.membership_id=? ORDER BY p.period_key DESC'));
    $q->execute([$m['id']]); ?><div class="list-group mb-3">
<?php foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $period): ?>
<article class="list-group-item">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
<strong>Období <?=mmH($period['period_from'].' – '.$period['period_to'])?></strong>
<span><?=mmH(['pending' => 'Čeká na úhradu','paid' => 'Uhrazeno','cancelled' => 'Zrušeno'][$period['status']] ?? 'Řeší klub')
    ?></span>
</div>
<p class="mb-1">Předepsáno <?=mmMoney((int)$period['amount_minor'])?> · zbývá
<strong><?=mmMoney(monthlyMembershipRemaining($pdo, (int)$period['charge_id']))?></strong>
</p>
<p class="small text-muted">Splatnost <?=mmH((new DateTimeImmutable($period['due_on']))->format('d. m. Y'))
    ?></p>
<div class="d-flex flex-wrap gap-2">
<?php if ($period['status'] === 'pending'): ?>
<a class="btn btn-outline-primary btn-sm" href="platba_predpisu.php?id=<?=(int)$period['charge_id']
    ?>">Platba / QR</a>
<?php endif; ?>
<?php if ($period['document_status'] === 'issued'): ?>
<a class="btn btn-outline-secondary btn-sm" href="?download=<?=(int)$period['document_id']?>">Doklad <?=
        mmH($period['number'])?> PDF</a>
<?php else: ?><span class="small text-muted">Doklad se připravuje</span><?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
<?php if ($m['ends_on'] === null): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel">
<input type="hidden" name="membership_id" value="<?=(int)$m['id']?>">
<label class="d-block mb-2">
<input type="checkbox" name="confirmed" required> Chci ukončit členství k <?=mmH(membershipPeriodEnd($m['starts_on'], monthlyMembershipToday(), membershipInterval($m)))?>;
dosavadní předpisy tím nezanikají.</label>
<button class="btn btn-outline-danger">Ukončit členství v klubu</button>
</form><?php endif; ?></section><?php endforeach; ?>
<?php if ($memberships !== []): ?><p>Evidovaný nepřidělený přeplatek členství v klubu: <strong><?=


            mmMoney(monthlyMembershipCredit($pdo, $account))?></strong>. Použije se na další předpisy. Po ukončení kontaktujte klub kvůli vyrovnání; peníze se automaticky nevracejí.</p><?php


endif;
?></main><?php publicShellFooter(); ?></body>
</html>
