<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
require_once dirname(__DIR__) . '/includes/bar/invoices.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
$actor = barRequireWebActor($pdo, 'invoice_read');
$manage = $actor['position'] === 'bar_admin';
$id = max(0, (int)($_GET['id'] ?? 0));
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        barPostCsrf();
        barActor($pdo, 'invoice_manage');
        $token = barPostText('token', 64);
        $action = barPostText('action', 20);
        if ($action === 'upload') {
            $file = $_FILES['pdf'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)) {
                throw new InvalidArgumentException('Vyberte úplný PDF soubor do 10 MB.');
            }
            $result = barUploadInvoice($pdo, $file['tmp_name'], $token);
        } else {
            $id = barSourceInteger($_POST['id'] ?? null, 1, 2147483647);
            $version = barSourceInteger($_POST['version'] ?? null, 1, 2147483647);
            $reason = barPostText('reason', 1000);
            if ($action === 'save') {
                $input = barInvoiceFormData($_POST);
                $submittedDraft = $input;
                $result = barSaveInvoice($pdo, $id, $version, $input, $reason, $token);
            } elseif ($action === 'receive') {
                $result = barReceiveInvoice(
                    $pdo,
                    $id,
                    $version,
                    barPostText('received_on', 10),
                    ($_POST['goods_confirmed']
    ?? '') === '1',
                    $reason,
                    $token
                );
            } elseif ($action === 'reverse') {
                $result = barReverseInvoice($pdo, $id, $version, $reason, $token);
            } else {
                throw new InvalidArgumentException('Neplatná operace.');
            }
        }
        header('Location: ' . appUiUrl('bar/invoices.php?id=' . $result['invoice_id']), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    }
}
$invoice = $id > 0 ? barInvoice($pdo, $id) : null;
$draft = $invoice['draft'] ?? [];
if ($error !== '' && ($_POST['action'] ?? '') === 'save') {
    $draft = ($submittedDraft ?? $_POST) + ['rows' => []];
}
$products = $manage ? $pdo->query("SELECT p.id,p.name,p.purchase_minor,p.purchase_version FROM kis_bar_products p WHERE p.archived=0 AND p.stock_mode='sledovat'
    AND NOT EXISTS(SELECT 1 FROM kis_bar_recipes r WHERE r.product_id=p.id) ORDER BY p.name,p.id")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$mappings = [];
if ($manage && ($draft['supplier_id'] ?? '') !== '') {
    $query = $pdo->prepare('SELECT * FROM kis_bar_supplier_mappings WHERE supplier_id=?');
    $query->execute([$draft['supplier_id']]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $mapping) {
        $mappings[$mapping['supplier_code'] . '|' . $mapping['invoice_unit']] = $mapping;
    }
}
$money = static fn (array $row, string $key): string => isset($row[$key]) ? (string)$row[$key] : (isset($row[$key . '_minor'])
    ? ((int)$row[$key . '_minor'] < 0 ? '-' : '') . barMinorDecimal(abs((int)$row[$key . '_minor'])) : '0.00');
barCheckoutPageStart($pdo, 'Dodavatelské faktury baru');
?>
<p>Příjem zboží do předskladu. Úhrada dodavateli se zde neeviduje. Podporované jsou běžné faktury v CZK; dobropisy a zálohy vyřizuje
hospodář samostatně.</p>
<p><a href="<?= barH(appUiUrl('bar/invoices.php')) ?>">Přehled a nová faktura</a></p>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= barH($error) ?> Obnovte doklad před dalším potvrzením.</div><?php endif;
?>
<?php if ($invoice === null): ?>
    <?php if ($manage): ?>
    <form method="post" enctype="multipart/form-data" class="card card-body mb-4"><?= csrf_field() ?>
        <input type="hidden" name="action" value="upload"><input type="hidden" name="token" value="<?= bin2hex(random_bytes(32))
        ?>">
        <label>Originál faktury (PDF, nejvýše 10 MB) <input type="file" name="pdf" accept="application/pdf" required></label>
        <button class="btn btn-primary mt-3">Nahrát a zkontrolovat fakturu</button>
    </form><?php endif; ?>
    <table class="table"><thead><tr><th>Doklad</th><th>Dodavatel</th><th>Stav</th></tr></thead><tbody>
    <?php foreach ($pdo->query('SELECT id,status,draft_json FROM kis_bar_invoices ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC) as $row): $header = json_decode($row['draft_json'],
        true, 32, JSON_THROW_ON_ERROR); ?>
        <tr><td><a href="?id=<?= (int)$row['id'] ?>"><?= barH($header['number'] ?? ('Faktura #' . $row['id'])) ?></a></td><td><?=
    barH($header['supplier'] ?? 'K doplnění') ?></td><td><?= $row['status'] === 'received' ? 'Přijato' : 'Rozpracováno' ?></td></tr>
    <?php endforeach; ?></tbody></table>
<?php else: ?>
    <p><a href="<?= barH(appUiUrl('bar/invoice-file.php?id=' . $id)) ?>" target="_blank" rel="noopener">Otevřít originál PDF</a>
· Verze <?= (int)$invoice['lock_version'] ?></p>
    <?php if ($invoice['parse_warning'] !== ''): ?><p class="alert alert-warning"><?= barH($invoice['parse_warning']) ?></p><?php endif;
?>
    <?php if ($manage && $invoice['status'] === 'draft'): ?>
    <form method="post" class="card card-body mb-4" id="invoice-draft"><?= csrf_field() ?>
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="version" value="<?= (int)($_POST['version'] ?? $invoice['lock_version']) ?>"><input type="hidden"
name="token" value="<?= bin2hex(random_bytes(32)) ?>">
        <div class="row g-3">
        <?php foreach (['supplier' => 'Dodavatel', 'supplier_id' => 'IČ dodavatele', 'number' => 'Číslo faktury', 'date' =>
'Datum vystavení'] as $key => $label): ?>
            <label class="col-md-6"><?= $label ?><input class="form-control" name="<?= $key ?>" type="<?= $key === 'date'
? 'date' : 'text' ?>" value="<?= barH($draft[$key] ?? '') ?>" required></label>
        <?php endforeach; ?>
        <input type="hidden" name="currency" value="CZK">
        <?php foreach (['net' => 'Základ', 'vat' => 'DPH', 'gross' => 'Celkem s DPH', 'rounding' => 'Zaokrouhlení', 'payable'
=> 'K úhradě'] as $key => $label): ?>
            <label class="col-md-4"><?= $label ?> (Kč)<input class="form-control" name="<?= $key ?>" value="<?= barH($money(
                $draft,
                $key
            )) ?>" required></label>
        <?php endforeach; ?></div>
        <p class="mt-3">Uveďte celkové částky řádku, nikoli jednotkovou cenu. Nákupní cena produktu se odvozuje z částky s DPH.
Balení přepočítejte na skladové kusy. U více řádků stejného produktu použijte shodnou volbu ceny.</p>
        <div id="invoice-rows">
        <?php $rows = $draft['rows'] ?? [];
        $rows[] = [];
        foreach ($rows as $index => $row):
            $suggestion = $mappings[($row['code'] ?? '') . '|' . ($row['unit'] ?? '')] ?? null;
            $selected = (int)($row['product_id'] ?? ($suggestion['product_id'] ?? 0)); ?>
            <fieldset class="border rounded p-3 mb-3 invoice-row"><legend class="h6">Položka <?= $index + 1 ?></legend>
                <div class="row g-2">
                <?php foreach (['code' => 'Kód dodavatele', 'description' => 'Popis', 'quantity' => 'Množství na faktuře',
     'unit' => 'Jednotka', 'net' => 'Základ řádku Kč', 'gross' => 'Celkem řádek Kč'] as $key => $label): ?>
                    <label class="col-md-4"><?= $label ?><input class="form-control" name="rows[<?= $index ?>][<?= $key ?>]" value="<?=
    barH(in_array(
        $key,
        ['net', 'gross'],
        true
    ) ? $money($row, $key) : ($row[$key] ?? (in_array($key, ['quantity'], true) ? 1 : ''))) ?>"></label>
                <?php endforeach; ?>
                <label class="col-md-4">Druh<select class="form-select" name="rows[<?= $index ?>][kind]">
                    <?php foreach (['stock' => 'Zboží na sklad', 'expense' => 'Náklad bez příjmu', 'deposit' => 'Vratný obal / záloha'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($row['kind'] ?? 'stock') === $value ? 'selected' : '' ?>><?=
    $label ?></option><?php endforeach; ?>
                </select></label>
                <label class="col-md-8">Skladový produkt (aktuální nákupní cena)<select class="form-select invoice-product" name="rows[<?=
    $index ?>][product_id]">
                    <option value="0" data-version="0">Vyberte pro skladovou položku</option>
                    <?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>" data-version="<?=
    (int)$product['purchase_version'] ?>" <?= $selected === (int)$product['id'] ? 'selected' : '' ?>><?= barH($product['name']
    . ' — ' . ($product['purchase_minor'] === null ? 'neuvedena' : barFormatMoney((int)$product['purchase_minor']))) ?></option><?php endforeach;
            ?>
                </select></label>
                <input class="purchase-version" type="hidden" name="rows[<?= $index ?>][purchase_version]" value="<?= (int)($row['purchase_version']
            ?? 0) ?>">
                <label class="col-md-4">Kusů v jedné jednotce<input class="form-control" type="number" min="1" name="rows[<?=
            $index ?>][multiplier]" value="<?= (int)($row['multiplier'] ?? ($suggestion['multiplier'] ?? 1)) ?>"></label>
                <label class="col-md-8">Nákupní cena<select class="form-select" name="rows[<?= $index ?>][cost_choice]"><option
value="">Rozhodněte po kontrole originálu</option><option value="apply" <?= ($row['cost_choice'] ?? '') === 'apply' ? 'selected'
            : '' ?>>Použít cenu z faktury (s DPH)</option><option value="keep" <?= ($row['cost_choice'] ?? '') === 'keep' ? 'selected'
            : '' ?>>Ponechat katalogovou cenu</option></select></label>
                </div>
                <label><input type="checkbox" name="rows[<?= $index ?>][confirmed]" value="1" <?= (int)($row['confirmed']
            ?? 0) === 1 ? 'checked' : '' ?>> Ověřil(a) jsem produkt a počet skladových kusů</label><br>
                <label><input type="checkbox" name="rows[<?= $index ?>][remember]" value="1" <?= (int)($row['remember'] ??
            0) === 1 ? 'checked' : '' ?>> Zapamatovat párování dodavatele po příjmu</label>
                <?php if ($suggestion): ?><p>Uložené párování je pouze návrh. Před příjmem jej znovu ověřte.</p><?php endif;
            ?>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">Odebrat položku</button>
            </fieldset>
        <?php endforeach; ?></div>
        <button type="button" class="btn btn-outline-secondary mb-3" id="add-invoice-row">Přidat položku</button>
        <label><input type="checkbox" name="verified" value="1" <?= (int)($draft['verified'] ?? 0) === 1 ? 'checked' : ''
        ?>> Ověřil(a) jsem hlavičku, všechny položky a běžnou fakturu v CZK s originálem</label>
        <label>Důvod úpravy<input class="form-control" name="reason" minlength="5" maxlength="1000" required value="<?= barH($_POST['reason']
        ?? '') ?>"></label>
        <button class="btn btn-primary mt-3">Uložit kontrolu faktury</button>
    </form>
    <script nonce="<?= barH(app_csp_nonce()) ?>">
    (() => {
        const holder = document.getElementById('invoice-rows');
        const form = document.getElementById('invoice-draft');
        form.addEventListener('formdata', (event) => {
            const data = event.formData;
            const payload = { rows: [] };
            const rows = new Map();
            for (const [name, value] of data) {
                const match = name.match(/^rows\[(\d+)\]\[(\w+)\]$/);
                if (match) {
                    if (!rows.has(match[1])) rows.set(match[1], {});
                    rows.get(match[1])[match[2]] = value;
                } else payload[name] = value;
            }
            payload.rows = [...rows.values()];
            for (const name of [...data.keys()]) {
                if (!['csrf_token','action','token','id','version','reason'].includes(name)) data.delete(name);
            }
            data.set('document_json', JSON.stringify(payload));
        });
        let next = <?= count($rows) ?>;
        const template = holder.lastElementChild.cloneNode(true);
        function wire(row) {
            const select = row.querySelector('.invoice-product');
            const sync = () => { row.querySelector('.purchase-version').value = select.selectedOptions[0].dataset.version; };
            select.addEventListener('change', sync);
            // This screen shows the current price explicitly; saving it binds the approval to that version.
            sync();
            row.querySelector('.remove-row').addEventListener('click', () => row.remove());
        }
        holder.querySelectorAll('.invoice-row').forEach(wire);
        document.getElementById('add-invoice-row').addEventListener('click', () => {
            if (holder.children.length >= 200) return;
            const row = template.cloneNode(true);
            row.querySelector('legend').textContent = 'Nová položka';
            row.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace(/rows\[\d+\]/, 'rows[' + next + ']'); if (el.type
=== 'checkbox') el.checked = false; });
            next++; holder.appendChild(row); wire(row);
        });
    })();
    </script>
    <?php endif; ?>
    <?php if ($draft !== [] && isset($draft['net_minor'], $draft['rows'])): ?>
        <p><?= barH($draft['supplier'] ?? '') ?> · <?= barH($draft['number'] ?? '') ?> · K úhradě <?= barH(barFormatMoney((int)$draft['payable_minor']))
        ?></p>
        <p><?= barInvoiceTotalsMatch($draft) ? 'Součet položek souhlasí se souhrnem.' : 'Součet položek nesouhlasí. Příjem je zablokován.'
        ?></p>
    <?php endif; ?>
    <?php if ($manage && $error === ''): ?>
    <form method="post" class="card card-body"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="version" value="<?= (int)$invoice['lock_version']
        ?>"><input type="hidden" name="token" value="<?= bin2hex(random_bytes(32)) ?>">
        <?php if ($invoice['status'] === 'draft'): ?>
            <input type="hidden" name="action" value="receive"><label>Datum skutečného převzetí<input class="form-control" type="date"
name="received_on" value="<?= date('Y-m-d') ?>" required></label>
            <label><input type="checkbox" name="goods_confirmed" value="1" required> Zboží bylo skutečně převzato a odpovídá uložené
kontrole faktury</label>
        <?php else: ?><input type="hidden" name="action" value="reverse"><p>Příjem #<?= (int)$invoice['current_receipt_id']
            ?> je dokončen. Storno odebere celé přijaté množství z předskladu, zachová nákupní ceny a vrátí fakturu k opravě.</p><?php endif;
?>
        <label>Důvod<input class="form-control" name="reason" minlength="5" maxlength="1000" required></label>
        <button class="btn <?= $invoice['status'] === 'draft' ? 'btn-success' : 'btn-outline-danger' ?> mt-3"><?= $invoice['status']
=== 'draft' ? 'Přijmout uložené zboží do předskladu' : 'Stornovat celý příjem' ?></button>
    </form><?php endif; ?>
<?php endif;
barCheckoutPageEnd();
