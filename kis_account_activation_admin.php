<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Database bootstrap unavailable.');
}
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/imported_account_activation.php';
require_once __DIR__ . '/includes/app_url.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    http_response_code(403);
    exit('Pouze pro administrátora.');
}
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
function kisActivationH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function kisActivationContactEmails(mixed $raw): string
{
    $values = json_decode((string)$raw, true);
    return is_array($values) ? implode(', ', array_filter($values, 'is_string')) : '';
}
$errors = [];
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } elseif (($_POST['reviewed'] ?? '') !== '1') {
        $errors[] = 'Před odesláním potvrďte individuální kontrolu vybraných účtů a adres.';
    } else {
        $ids = array_values(array_unique(array_map('intval', (array)($_POST['account_ids'] ?? []))));
        if ($ids === [] || count($ids) > 100 || min($ids) < 1) {
            $errors[] = 'Vyberte 1 až 100 účtů.';
        } else {
            $sent = 0;
            foreach ($ids as $id) {
                try {
                    importedAccountInvite(
                        $pdo,
                        $id,
                        (int)$_SESSION['trener_id'],
                        (string)($_POST['reason'] ?? ''),
                        appUrl('booking/nove_heslo.php'),
                        appUrl('booking/aktivace_uctu.php')
                    );
                    $sent++;
                } catch (ImportedAccountActivationException|InvalidArgumentException $exception) {
                    $errors[] = 'Účet #' . $id . ': ' . $exception->getMessage();
                } catch (Throwable $exception) {
                    error_log('KIS invitation #' . $id . ': ' . get_class($exception));
                    $errors[] = 'Účet #' . $id . ': pozvánku nebylo možné bezpečně zařadit.';
                }
            }
            $notice = "Zařazeno {$sent} pozvánek do zabezpečené poštovní fronty.";
        }
    }
}
$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$filter = (string)($_GET['status'] ?? 'all');
if (!in_array($filter, ['all','no_account','pending_role','inactive','unverified','ready','active'], true)) {
    $filter = 'all';
}
$page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(s.jmeno LIKE ? OR s.prijmeni LIKE ? OR c.display_name LIKE ? OR u.email LIKE ? OR c.emails_json LIKE ?)';
    $params = array_fill(0, 5, '%' . $search . '%');
}
$where[] = match ($filter) {
    'no_account' => 'u.id IS NULL',
    'pending_role' => "r.status='pending'",
    'inactive' => 'u.id IS NOT NULL AND u.aktivni=0',
    'unverified' => 'u.id IS NOT NULL AND u.email_overeno=0',
    'ready' => "r.status='approved' AND u.id IS NOT NULL",
    'active' => 'u.aktivni=1 AND u.email_overeno=1',
    default => '1=1',
};
$sql = 'SELECT s.id AS sportovec_id,s.jmeno,s.prijmeni,c.display_name,c.account_state,c.emails_json,'
    . 'u.id AS account_id,u.email,u.aktivni,u.email_overeno,r.status AS role_status,r.relation_role,'
    . 'ca.id AS child_access_id,'
    . "(SELECT status FROM auth_mail_outbox o WHERE o.target_type='public' AND o.target_id=u.id "
    . "AND o.subject_plain='Pozvání do KIS — TJ KOVO Praha' ORDER BY o.id DESC LIMIT 1) AS invite_status "
    . 'FROM sportovci s LEFT JOIN kis_person_profiles p ON p.sportovec_id=s.id '
    . 'LEFT JOIN kis_person_links l ON l.sportovec_id=s.id '
    . 'LEFT JOIN kis_person_contacts c ON c.contact_key=l.contact_key '
    . 'LEFT JOIN account_person_roles r ON r.sportovec_id=s.id AND (r.account_id=c.account_id OR c.account_id IS NULL) '
    . 'LEFT JOIN verejni_uzivatele u ON u.id=COALESCE(c.account_id,r.account_id) '
    . 'LEFT JOIN child_access_accounts ca ON ca.sportovec_id=s.id '
    . "WHERE (p.sportovec_id IS NOT NULL OR NULLIF(TRIM(s.kis_external_id),'') IS NOT NULL) AND "
    . implode(' AND ', $where) . ' ORDER BY s.prijmeni,s.jmeno,s.id,c.contact_key LIMIT 200 OFFSET ' . (($page - 1) * 200);
$statement = $pdo->prepare($sql);
$statement->execute($params);
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
$mailCounts = ['pending' => 0,'processing' => 0,'sent' => 0,'failed' => 0,'cancelled' => 0];
$countQuery = $pdo->prepare('SELECT status,COUNT(*) AS total FROM auth_mail_outbox WHERE subject_plain=? GROUP BY status');
$countQuery->execute(['Pozvání do KIS — TJ KOVO Praha']);
foreach ($countQuery->fetchAll(PDO::FETCH_ASSOC) as $countRow) {
    $mailCounts[(string)$countRow['status']] = (int)$countRow['total'];
}
$decisions = [];
foreach ($rows as $row) {
    $accountId = (int)($row['account_id'] ?? 0);
    if ($accountId > 0 && !isset($decisions[$accountId])) {
        $decisions[$accountId] = importedAccountInviteEligibility($pdo, $accountId);
    }
}
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Aktivace importovaných účtů KIS
</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
 rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
 crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container-fluid py-4" style="max-width:1500px">
<h1 class="h4">Aktivace importovaných účtů KIS
</h1>

<p>Nejprve ověřte totožnost zástupce, vlastnictví kontaktní adresy a vazby na děti v
<a href="eshop_identity_admin.php">účtech a vazbách
</a>. Importní e-mail sám o sobě oprávnění nezakládá. Pozvánka odejde pouze po schválení všech importovaných vazeb daného účtu.
</p>

<p>Pokud existuje jen profil dítěte, rodič si vytvoří
<a href="booking/registrace.php?purpose=nakup">veřejný účet
</a>, ověří e-mail a v
<a href="booking/moje_osoby.php">Moje osoby
</a> požádá o propojení. Administrátor žádost ověří a propojí s existujícím profilem.
</p>

<p class="small text-muted">Pozvánky: <?= (int)$mailCounts['pending'] ?> čeká,
<?= (int)$mailCounts['processing'] ?> zpracovává se, <?= (int)$mailCounts['sent'] ?> odesláno,
<?= (int)$mailCounts['failed'] ?> selhalo, <?= (int)$mailCounts['cancelled'] ?> zrušeno.
</p>
<?php foreach ($errors as $error): ?>
<div class="alert alert-danger"><?= kisActivationH($error) ?>
</div><?php endforeach; ?><?php if ($notice !== ''): ?>
<div class="alert alert-info"><?= kisActivationH($notice) ?>
</div><?php endif; ?>

<form method="get" class="row g-2 mb-3">
<div class="col-md-6">
<input class="form-control" name="q" value="<?= kisActivationH($search) ?>" placeholder="Jméno, příjmení nebo e-mail">
</div>
<div class="col-md-3">
<select class="form-select" name="status"><?php foreach ([
    'all' => 'Vše', 'no_account' => 'Bez účtu', 'pending_role' => 'Čeká vazba',
    'inactive' => 'Neaktivní účet', 'unverified' => 'Neověřený e-mail',
    'ready' => 'Schválená vazba', 'active' => 'Aktivní účet',
] as $key => $label): ?>
<option value="<?= $key ?>" <?= $filter === $key ? 'selected' : '' ?>><?= kisActivationH($label) ?>
</option><?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<button class="btn btn-outline-primary">Filtrovat
</button>
</div>
</form>

<form method="post"><?= csrf_field() ?>
<div class="table-responsive bg-white">
<table class="table table-sm table-striped align-middle">
<thead>
<tr>
<th>Vybrat
</th>
<th>Importovaná osoba
</th>
<th>Kontakt
</th>
<th>Účet
</th>
<th>Vazba
</th>
<th>Přístup sportovce
</th>
<th>Pozvánka
</th>
<th>Další krok
</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): $id = (int)($row['account_id'] ?? 0);
    $decision = $decisions[$id] ?? null;
    $step = $id < 1
        ? 'Dohledat existující účet; případně vytvořit rodičovský účet a podat žádost o vazbu.'
        : ($decision['mode'] === 'blocked'
            ? $decision['reason']
            : ($decision['mode'] === 'activate'
                ? 'Zkontrolovat adresu a zaslat aktivační odkaz.'
                : 'Účet má přístup; při potřebě zaslat obnovu hesla.')); ?>
<tr>

<td><?php if ($id > 0 && $decision['mode'] !== 'blocked'): ?>
<input class="form-check-input" type="checkbox" name="account_ids[]" value="<?= $id ?>" aria-label="Vybrat účet <?= $id ?>"><?php endif; ?>
</td>

<td><?= kisActivationH($row['prijmeni'].' '.$row['jmeno']) ?>
<small>#<?= (int)$row['sportovec_id'] ?>
</small>
</td>

<td><?= kisActivationH($row['display_name'] ?: '—') ?>
<div class="small"><?= kisActivationH(kisActivationContactEmails($row['emails_json'] ?? '')) ?>
</div>
<div class="small text-muted">Při importu: <?= kisActivationH($row['account_state'] ?: '—') ?>
</div>
</td>

<td><?= $id > 0 ? '#'.$id.' '.kisActivationH($row['email']) : 'Nevytvořen' ?><?php if ($id > 0): ?>
<div class="small"><?= (int)$row['aktivni'] === 1 ? 'aktivní' : 'neaktivní' ?> · <?= (int)$row['email_overeno'] === 1 ? 'e-mail ověřen' : 'e-mail neověřen' ?>
</div><?php endif; ?>
</td>

<td><?= kisActivationH($row['role_status'] ?: 'bez vazby') ?> <?= kisActivationH($row['relation_role'] ?: '') ?>
</td>
<td><?= $row['child_access_id'] ? 'samostatný účet' : '—' ?>
</td>
<td><?= kisActivationH($row['invite_status'] ?: '—') ?>
</td>
<td><?= kisActivationH($step) ?>
</td>
</tr><?php endforeach; ?>
<?php if ($rows === []): ?>
<tr>
<td colspan="8" class="text-center text-muted">Žádné záznamy.
</td>
</tr><?php endif; ?>
</tbody>
</table>
</div>

<div class="d-flex gap-3 my-3">
<a href="?<?= kisActivationH(http_build_query(['q' => $search,'status' => $filter,'page' => max(1, $page - 1)])) ?>">Předchozí
</a>
<span>Strana <?= $page ?> (nejvýše 200 řádků)
</span><?php if (count($rows) === 200): ?>
<a href="?<?= kisActivationH(http_build_query(['q' => $search,'status' => $filter,'page' => $page + 1])) ?>">Další
</a><?php endif; ?>
</div>

<div class="card card-body">
<label class="form-label" for="activation-reason">Záznam o kontrole adresy a totožnosti
</label>
<input class="form-control mb-2" id="activation-reason" name="reason" minlength="10" maxlength="1000" required>
<label class="form-check-label mb-3">
<input class="form-check-input" type="checkbox" name="reviewed" value="1" required> Každý vybraný účet a vazbu jsem individuálně zkontroloval(a); uvedená adresa náleží oprávněné osobě.
</label>
<button class="btn btn-primary" style="max-width:300px">Zařadit vybrané pozvánky
</button>
<div class="small text-muted mt-2">Nejvýše 100 účtů na jednu dávku. Stav „sent“ znamená odesláno dopravci; doručení do schránky nelze potvrdit.
</div>
</div>
</form>

</main>
</body>
</html>
