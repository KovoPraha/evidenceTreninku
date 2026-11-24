<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/auth_mail_outbox.php';
require_once __DIR__.'/includes/mail_admin_unified.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Database bootstrap unavailable.');
}
header('Cache-Control: no-store, private, max-age=0');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
function mailAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$filters = [
    'source' => (string)($_GET['source'] ?? ''),
    'status' => (string)($_GET['status'] ?? ''),
    'email' => (string)($_GET['email'] ?? ''),
    'reference' => (string)($_GET['reference'] ?? ''),
    'page' => (int)($_GET['page'] ?? 1),
];
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $actor = (int)$_SESSION['trener_id'];
            $source = (string)($_POST['source'] ?? '');
            $id = (int)($_POST['message_id'] ?? 0);
            $reason = (string)($_POST['reason'] ?? '');
            $confirmed = ($_POST['confirmed'] ?? '') === '1';
            switch ((string)($_POST['action'] ?? '')) {
                case 'bcc_toggle':
                    if (!$confirmed) {
                        throw new InvalidArgumentException('Potvrďte změnu testovacího režimu.');
                    }
                    mailAdminBccToggle($pdo, $actor, (int)($_POST['hours'] ?? 0), $reason);
                    $notice = 'Nastavení skryté kopie bylo uloženo.';
                    break;
                case 'bcc_attach':
                    if (!$confirmed) {
                        throw new InvalidArgumentException('Potvrďte přidání skryté kopie.');
                    }
                    mailAdminBccAttach($pdo, $source, $id, (string)($_POST['bcc_email'] ?? ''), $actor, $reason);
                    $notice = 'Skrytá kopie byla přiřazena k čekající zprávě.';
                    break;
                case 'resend':
                    if ($source === 'auth') {
                        mailAdminAuthResend($pdo, $id, $actor, $reason, $confirmed);
                    } else {
                        mailAdminResend($pdo, $source, $id, $actor, $reason, $confirmed);
                    }
                    $notice = 'Zpráva byla auditovaně vrácena do příslušné fronty.';
                    break;
                default: throw new InvalidArgumentException('Neznámá akce.');
            }
            $_SESSION['flash_mail_admin'] = $notice;
            header('Location: mail_admin.php', true, 303);
            exit;
        } catch (InvalidArgumentException|MailAdminException|AuthMailOutboxException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('mail_admin: '.get_class($e).' '.$e->getMessage());
            $errors[] = 'Akce se nepodařila. Podrobnost je v provozním logu.';
        }
    }
}
try {
    $feed = mailAdminUnifiedList($pdo, $filters);
} catch (InvalidArgumentException $e) {
    $errors[] = $e->getMessage();
    $filters = ['source' => '','status' => '','email' => '','reference' => '','page' => 1];
    $feed = mailAdminUnifiedList($pdo);
}
$bccSettings = mailAdminBccSettings($pdo);
$success = (string)($_SESSION['flash_mail_admin'] ?? '');
unset($_SESSION['flash_mail_admin']);
function mailAdminQuery(array $filters, int $page): string
{
    $filters['page'] = $page;
    return '?'.http_build_query($filters);
}
?>
<!doctype html><html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Veškerá pošta · KIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
 integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__.'/hlavicka.php';?>
<main class="container-fluid py-4" style="max-width:1600px">
<h1 class="h4">Veškerá pošta</h1>
<p class="text-muted">Přehled zahrnuje požadavky před zařazením a zprávy ze šesti front. „Přijato transportem“ neprokazuje doručení do schránky. Bezpečnostní odkazy a texty se zde nezobrazují.</p>
<?php foreach ($errors as $error):?>
<div class="alert alert-danger">
<?=mailAdminH($error)?>
</div>
<?php endforeach;?>
<?php if ($success !== ''):?>
<div class="alert alert-success">
<?=mailAdminH($success)?>
</div>
<?php endif;?>
<section class="card mb-3">
<div class="card-body">
<h2 class="h6">Testovací skrytá kopie</h2>
<p class="small">Režim je <?= $bccSettings['active'] ? '<strong>zapnutý</strong> do '.mailAdminH($bccSettings['enabled_until']) : '<strong>vypnutý</strong>' ?>.
Kopie obsahuje i jednorázový odkaz, který její příjemce může použít. Vypnutí zastaví kopie dosud čekajících zpráv.</p>
<form method="post" class="row g-2 align-items-end">
<?=csrf_field()?>
<input type="hidden" name="action" value="bcc_toggle">
<div class="col-md-3">
<label class="form-label">Doba testu<select class="form-select" name="hours">
<option value="0">Vypnout</option>
<option value="1">1 hodina</option>
<option value="24">24 hodin</option>
<option value="72">3 dny</option>
<option value="168">7 dní</option>
</select>
</label>
</div>
<div class="col-md-5">
<label class="form-label">Důvod<input class="form-control" name="reason" maxlength="1000" required>
</label>
</div>
<div class="col-md-2">
<label>
<input type="checkbox" name="confirmed" value="1" required> Potvrzuji</label>
</div>
<div class="col-md-2">
<button class="btn btn-outline-primary w-100">Uložit</button>
</div>
</form>
</div>
</section>
<form method="get" class="card card-body row g-2 mb-3">
<div class="col-md-2">
<label class="form-label">Oblast<select class="form-select" name="source">
<option value="">Všechny</option>
<?php foreach ([
    'auth' => 'Ověření / heslo', 'club' => 'Klubové události', 'roster' => 'Hromadné zprávy',
    'weekly' => 'Týdenní souhrny', 'reminder' => 'Upomínky', 'monthly' => 'Členství',
    'registration_request' => 'Registrace před frontou', 'verify_request' => 'Ověření před frontou',
    'reset_request' => 'Obnova před frontou',
] as $key => $label):?>
<option value="<?=mailAdminH($key)?>" <?=$filters['source'] === $key ? 'selected' : ''?>>
<?=mailAdminH($label)?>
</option>
<?php endforeach;?>
</select>
</label>
</div>
<div class="col-md-2">
<label class="form-label">Stav<select class="form-select" name="status">
<option value="">Všechny</option>
<?php foreach (['received','rejected','queued','pending','processing','sent','failed','cancelled'] as $status):?>
<option value="<?=$status?>" <?=$filters['status'] === $status ? 'selected' : ''?>>
<?=$status?>
</option>
<?php endforeach;?>
</select>
</label>
</div>
<div class="col-md-3">
<label class="form-label">Přesný e-mail<input class="form-control" name="email" value="<?=mailAdminH($filters['email'])?>">
</label>
</div>
<div class="col-md-3">
<label class="form-label">Reference<input class="form-control" name="reference" value="<?=mailAdminH($filters['reference'])?>">
</label>
</div>
<div class="col-md-2 d-flex align-items-end">
<button class="btn btn-primary w-100">Vyhledat</button>
</div>
</form>
<p class="small text-muted">Celkem <?=(int)$feed['total']?> záznamů; strana <?=(int)$feed['page']?>. Nezařazené žádosti uchovávají jen otisk adresy; vyhledáte je přesným zadáním.</p>
<div class="table-responsive bg-white">
<table class="table table-sm align-middle">
<thead>
<tr>
<th>Čas</th>
<th>Reference</th>
<th>Oblast / typ</th>
<th>Příjemce</th>
<th>Stav</th>
<th>Pokusy</th>
<th>Další pokus / přijetí</th>
<th>Důvod</th>
<th>Akce</th>
</tr>
</thead>
<tbody>
<?php foreach ($feed['rows'] as $row):?>
<tr>
<td class="small">
<?=mailAdminH($row['created_at'])?>
</td>
<td>
<code>
<?=mailAdminH($row['reference'])?>
</code>
</td>
<td>
<?=mailAdminH($row['source'])?>
<br>
<small>
<?=mailAdminH($row['kind'])?>
</small>
</td>
<td>
<?=mailAdminH($row['recipient_email'] ?? '—')?>
<?php if ($row['bcc_email'] !== null):?>
<br>
<small>BCC: <?=mailAdminH($row['bcc_email'])?> · <?= $row['bcc_accepted_at'] !== null ? 'přijato transportem' : ($row['bcc_skipped_at'] !== null ? 'vypnuto před odesláním' : 'čeká') ?>
</small>
<?php endif;?>
</td>
<td>
<span class="badge text-bg-<?=$row['status'] === 'failed' ? 'danger' : ($row['status'] === 'sent' ? 'success' : 'secondary')?>">
<?=mailAdminH($row['status'] === 'sent' ? 'přijato transportem' : $row['status'])?>
</span>
</td>
<td>
<?=(int)$row['attempts']?>
</td>
<td class="small">
<?=mailAdminH($row['sent_at'] ?? $row['available_at'] ?? '—')?>
</td>
<td class="small">
<?=mailAdminH($row['reason'] ?? '')?>
</td>
<td style="min-width:240px">
<?php if (isset(mailAdminSources()[$row['source']]) && in_array($row['status'], ['failed','sent'], true)):?>
<form method="post" class="d-grid gap-1 mb-2">
<?=csrf_field()?>
<input type="hidden" name="action" value="resend">
<input type="hidden" name="source" value="<?=mailAdminH($row['source'])?>">
<input type="hidden" name="message_id" value="<?=(int)$row['source_id']?>">
<input class="form-control form-control-sm" name="reason" maxlength="1000" required placeholder="Důvod opakování">
<label class="small">
<input type="checkbox" name="confirmed" value="1" required> Potvrzuji nové odeslání</label>
<button class="btn btn-outline-primary btn-sm">Opakovat</button>
</form>
<?php endif;?>
<?php if ($bccSettings['active'] && $row['bcc_email'] === null && isset(mailAdminSources()[$row['source']]) && in_array($row['status'], ['pending','failed'], true)):?>
<form method="post" class="d-grid gap-1">
<?=csrf_field()?>
<input type="hidden" name="action" value="bcc_attach">
<input type="hidden" name="source" value="<?=mailAdminH($row['source'])?>">
<input type="hidden" name="message_id" value="<?=(int)$row['source_id']?>">
<input class="form-control form-control-sm" type="email" name="bcc_email" maxlength="254" required placeholder="Adresa skryté kopie">
<input class="form-control form-control-sm" name="reason" maxlength="1000" required placeholder="Důvod kopie">
<label class="small">
<input type="checkbox" name="confirmed" value="1" required> Potvrzuji přístup k obsahu</label>
<button class="btn btn-outline-secondary btn-sm">Přidat BCC</button>
</form>
<?php endif;?>
</td>
</tr>
<?php endforeach;?>
<?php if ($feed['rows'] === []):?>
<tr>
<td colspan="9" class="text-center text-muted py-4">Žádné záznamy.</td>
</tr>
<?php endif;?>
</tbody>
</table>
</div>
<nav class="d-flex justify-content-between mt-3" aria-label="Stránkování">
<?php if ($feed['page'] > 1):?>
<a class="btn btn-outline-secondary" href="<?=mailAdminH(mailAdminQuery($filters, $feed['page'] - 1))?>">Předchozí</a>
<?php else:?>
<span>
</span>
<?php endif;?>
<?php if ($feed['page'] * 50 < $feed['total']):?>
<a class="btn btn-outline-secondary" href="<?=mailAdminH(mailAdminQuery($filters, $feed['page'] + 1))?>">Další</a>
<?php endif;?>
</nav>
</main>
</body>
</html>
