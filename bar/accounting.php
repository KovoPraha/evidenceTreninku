<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
require_once dirname(__DIR__) . '/includes/bar/accounting.php';
barRequireWebActor($pdo, 'accounting');
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        barPostCsrf();
        $selection = is_array($_POST['sources'] ?? null) ? $_POST['sources'] : [];
        $result = barCreateAccountingExport($pdo, $selection, barPostText('reason', 1000), barPostText('token', 64));
        header('Location: ' . appUiUrl('bar/accounting.php?export=' . $result['export_id']), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    }
}
if (isset($_GET['download'])) {
    $export = barAccountingExport($pdo, (int)($_GET['export'] ?? 0));
    $csv = $_GET['download'] === 'csv';
    header('Content-Type: ' . ($csv ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="bar-accounting-' . (int)$export['id'] . ($csv ? '.csv' : '.json') .
    '"');
    $manifest = json_decode($export['manifest_json'], true, 64, JSON_THROW_ON_ERROR);
    echo $csv ? barAccountingCsv($manifest['events'], $manifest['source_timezone'] ?? '') : $export['manifest_json'];
    exit;
}
$sources = [];
$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : barBusinessToday()->format('Y-m-01');
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : barBusinessToday()->format('Y-m-d');
$report = null;
try {
    $sources = barAccountingSources($pdo);
    $report = barAccountingReport($pdo, $from, $to);
} catch (InvalidArgumentException | DomainException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
}
$labels = ['sale_settlement' => 'Potvrzený prodej', 'own_consumption' => 'Vlastní spotřeba', 'own_consumption_reversal' =>
    'Storno vlastní spotřeby',
    'sale_refund' => 'Storno a vratka prodeje', 'deposit_return' => 'Vrácení zálohy za kelímky', 'supplier_receipt' => 'Příjem dodavatelské faktury',
    'supplier_receipt_reversal' => 'Storno dodavatelského příjmu', 'drawer_transfer' => 'Vklad nebo výběr hotovosti', 'cashbox_closure'
    => 'Uzávěrka kasy'];
barCheckoutPageStart($pdo, 'Účetní podklady baru');
?>
<div class="alert alert-warning">Podklady ke kontrole, dosud nezaúčtované. Export nic nezapisuje do peněžní evidence KIS.</div>
<p>Karty a QR převody je nutné spojit se skutečným bankovním nebo terminálovým pohybem. Prodej a přijatá bankovní platba nesmějí
vytvořit dvě tržby. Zálohy za kelímky se oddělují od prodeje zboží. Příjem dodavatelské faktury není její úhrada.</p>
<p>Prodejní částky jsou po slevě a včetně daně; rozpis DPH zatím není k dispozici. Nákupní ceny představují historický odhad nákladu.
Neznámá cena se nepovažuje za nulu. Vklady, výběry a uzávěrky jsou kontrolní podklady a nepřičítají se k tržbám.</p>
<?php if ($error !== ''): ?><p class="alert alert-danger"><?= barH($error) ?></p><?php endif; ?>
<p>Čekající platby: <?= (int)$pdo->query("SELECT COUNT(*) FROM kis_bar_sale_payments WHERE status='pending'")->fetchColumn()
?>. Nejsou součástí potvrzených úhrad.</p>
<h2 class="h4">Dosud neexportované podklady</h2>
<form method="get" class="card card-body mb-3">
    <h3 class="h5">Provozní přehled za období</h3>
    <label>Od <input type="date" name="from" value="<?= barH($from) ?>" required></label>
    <label>Do <input type="date" name="to" value="<?= barH($to) ?>" required></label>
    <button class="btn btn-outline-primary">Zobrazit období</button>
</form>
<?php if ($report !== null): ?>
<p>Potvrzené prodeje po slevách a stornech, včetně již exportovaných podkladů. Období se vymezuje v pražském čase podle potvrzení platby nebo provedení vratky.</p>
<table class="table"><tbody>
<?php foreach (['goods' => 'Zboží včetně daně', 'deposits' => 'Změna závazku ze záloh', 'cash' => 'Hotovost po vratkách',
    'card' => 'Karta po vratkách', 'qr' => 'QR po vratkách', 'payments' => 'Úhrady celkem po vratkách'] as $key => $label): ?>
    <tr><th><?= $label ?></th><td><?= barH(barFormatMoney($report[$key])) ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
<h3 class="h5">Nové podklady pro export (všechna období)</h3>
<form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= bin2hex(random_bytes(32)) ?>">
<div class="table-responsive"><table class="table"><thead><tr><th>Výběr</th><th>Podklad</th><th>Čas evidence</th><th>Zboží vč.
daně</th><th>Zálohy</th><th>Úhrada</th><th>Kontrola</th></tr></thead><tbody>
<?php foreach ($sources as $key => $source): $event = $source['event']; ?>
<tr><td><input type="checkbox" name="sources[<?= barH($key) ?>]" value="<?= $source['sha256'] ?>" aria-label="<?= barH($key)
    ?>"></td>
<td><?= barH($labels[$event['kind']] ?? $event['kind']) ?><div class="small"><?= barH($key) ?></div></td><td><?= barH(barDisplayTime($event['occurred_at']))
    ?></td>
<?php foreach (['goods_including_tax_minor', 'deposit_liability_minor', 'payment_minor'] as $field): ?><td><?= isset($event[$field])
    ? barH(barFormatMoney((int)$event[$field])) : '—' ?></td><?php endforeach; ?>
<td><?= array_key_exists('estimated_cost_minor', $event) && $event['estimated_cost_minor'] === null ? 'Chybí nákupní cena. '
    : '' ?><?= barH($event['payment_reference'] ?? $event['reason'] ?? '') ?>
<?php if (isset($event['snapshot']['document'])): ?><br><?= barH($event['snapshot']['document']['supplier'] . ' / ' . $event['snapshot']['document']['number'])
        ?> — <?= barH(barFormatMoney((int)$event['snapshot']['document']['payable_minor'])) ?><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ($sources !== []): ?><label>Důvod a poznámka ke kontrole<input class="form-control" name="reason" minlength="5" maxlength="1000"
required></label><button class="btn btn-primary my-3">Vytvořit neměnný export vybraných podkladů (nejvýše 500)</button><?php else:
    ?><p>Žádné nové podklady.</p><?php endif; ?>
</form>
<h2 class="h4">Vytvořené exporty</h2>
<p>Opakované stažení vrací stejné podklady. Jeden zdroj může patřit pouze do jednoho exportu. CSV je stručný přehled; úplný strojový
podklad včetně faktur a návazností obsahuje JSON.</p>
<ul><?php foreach ($pdo->query('SELECT id,created_at,sha256 FROM kis_bar_accounting_exports ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC) as $row): ?>
<li>#<?= (int)$row['id'] ?> · <?= barH(barDisplayTime($row['created_at'])) ?> · <a href="?export=<?= (int)$row['id'] ?>&amp;download=json">Úplné
podklady JSON</a> · <a href="?export=<?= (int)$row['id'] ?>&amp;download=csv">Přehled CSV</a></li>
<?php endforeach; ?></ul>
<?php barCheckoutPageEnd();
